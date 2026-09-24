# Filament Icon Hub: Architecture

This document records the design of `syriable/filament-icon-hub`. It was
written before implementation and is kept in sync with the code.

> **Note on the frontend brief.** The task referenced a supplied frontend
> design, but none was attached to the repository. The picker UI is therefore
> built to feel native to Filament 5 (Filament modal, input, and badge
> styling) while meeting every behavioral requirement from the brief
> (responsive grid, keyboard navigation, skeleton/loading/error/empty states,
> RTL, dark mode). The UI lives in one Blade view, one small Alpine component,
> and one stylesheet, so a supplied design can later be applied without
> touching the provider architecture.

---

## 0. Scope

**It is:** a lightweight, extensible icon picker plus an icon provider system
for Filament 5.

**It is not:** an icon marketplace, asset manager, media library, design
system, or replacement for Blade Icons.

Non-goals:

- No first-party Flaticon or Font Awesome API integration. Those are
  *optional* providers built on the generic `HttpIconProvider` base, and the
  docs contain a complete example.
- No filesystem or disk management for uploaded icons. Uploaded SVG markup is
  sanitized and stored in a single database column.

---

## 1. Package structure

```
config/icon-hub.php
database/migrations/create_icon_hub_tables.php.stub   (optional library feature)
resources/
  dist/icon-hub-picker.js      Alpine component (hand-written ES module, no build step)
  dist/icon-hub.css            Stylesheet built on Filament CSS variables
  lang/en/icon-hub.php
  views/forms/components/icon-picker.blade.php
  views/components/icon.blade.php
src/
  Contracts/IconProvider.php            The single provider contract
  Contracts/IconVisibility.php          Hidden / ignored icon lookup
  Data/                                 final readonly DTOs
    Icon, IconSource, IconQuery, IconResults, ProviderMetadata, SearchPage
  ValueObjects/IconId.php               "provider:name" identifier
  Enums/ProviderStatus.php, SourceKind.php
  Exceptions/                           ProviderException, InvalidIconId, ProviderNotFound
  Registry/IconRegistry.php             Registers, discovers, and resolves providers
  Registry/IconSearcher.php             Cross-provider search, cursor, error isolation
  Registry/ConfigProviderDiscovery.php  Builds providers from config and Blade Icons
  Providers/
    IndexedIconProvider.php             Abstract base for index-backed sources
    BladeIconSetProvider.php            One provider per Blade Icons set
    LocalSvgProvider.php                Local SVG directories
    LibraryIconProvider.php             Uploaded icons (database)
    HttpIconProvider.php                Abstract base for remote APIs
  Cache/IconCache.php                   Deterministic keys, generation-based flush
  Rendering/IconRenderer.php            Single rendering choke point
  Rendering/SvgSanitizer.php            Sanitization plus safe attribute merging
  Visibility/DefaultIconVisibility.php  Config list plus database list
  Models/ManagedIcon.php, HiddenIcon.php
  Actions/SaveManagedIcon.php, HideIcons.php, RestoreIcon.php
  Rules/ValidIcon.php
  Forms/Components/IconPicker.php
  Tables/Columns/IconHubColumn.php
  Infolists/Components/IconHubEntry.php
  View/Components/Icon.php              <x-icon-hub::icon />
  Filament/Resources/...                Optional library management UI
  Commands/ClearIconCacheCommand.php    php artisan icon-hub:clear
  Facades/IconHub.php
  IconHubServiceProvider.php
  IconHubPlugin.php
```

The flow of a request:

```
Browser (Alpine) ──callSchemaComponentMethod──▶ IconPicker (Filament field)
                                                    │  only the providers allowed on this field
                                                    ▼
                                              IconRegistry ──▶ IconSearcher
                                                    │  try/catch per provider, visibility filter
                                                    ▼
                                    IconProvider (Blade / Local / Library / HTTP / custom)
                                                    │
                                     IconCache (index, search, icon, metadata)
                                                    │
                                       filesystem / database / remote API
```

---

## 2. Provider architecture

### Contract

```php
interface IconProvider
{
    public function id(): string;                        // stable, e.g. "heroicons"
    public function label(): string;                     // cheap, no I/O
    public function status(): ProviderStatus;            // cheap: Available | Unconfigured | Unavailable
    public function metadata(): ProviderMetadata;        // categories, variants, remote, total (may do I/O)
    public function search(IconQuery $query): IconResults;
    public function find(string $name): ?Icon;           // provider-local name
}
```

Six methods, none of them about rendering. A provider only *describes* an
icon, through `IconSource`. Rendering is centralized in `IconRenderer`, so a
provider author cannot accidentally ship an XSS vector, and every icon
renders the same way regardless of its origin.

Two optional base classes remove boilerplate:

- **`IndexedIconProvider`** is for sources whose full icon list can be
  enumerated (Blade Icons sets, local directories, a JSON manifest). The
  subclass implements `buildIndex(): list<array>`. The base class caches the
  index and implements search, pagination, categories, variants, and lookup.
- **`HttpIconProvider`** is for remote APIs. The subclass implements
  `searchRequest()` and `mapSearchResponse()`, plus optionally
  `findRequest()`, `mapIconResponse()`, and `isConfigured()`. The base class
  handles timeouts, HTTP error mapping, malformed JSON, 429 cool-downs,
  result caching, and priming the icon cache from search results.

### Registry

`IconRegistry` is a container singleton, also available through the `IconHub`
facade. It:

- registers and forgets providers (`register`, `forget`, `has`, `provider`,
  `providers`)
- discovers providers lazily on first read (Blade Icons sets, configured local
  directories, the library, and configured provider classes). Discovery never
  overrides an explicitly registered provider with the same ID.
- resolves identifiers: `find('heroicons:o-user')` returns `?Icon`
- delegates cross-provider search to `IconSearcher`
- renders an identifier: `html('heroicons:o-user', ['class' => 'h-5'])`

Every call into a provider is wrapped. One broken provider shows up as a
per-provider error in the UI, and the other providers keep working.

### Lifecycle

`ProviderStatus`:

- `Available`
- `Unconfigured`: for example, an API key is missing. The provider appears
  disabled in the filter and "All" skips it.
- `Unavailable`: `status()` threw an exception, or a provider reported itself
  down.

---

## 3. Data model

### Identifier

`IconId` is a value object with the format `{provider}:{name}`.

- `provider` matches `[a-z0-9][a-z0-9._-]*`
- `name` is everything after the **first** colon. It is provider-defined and
  may contain `/`, `.`, `-`, or further colons.
- Examples: `heroicons:o-user`, `fontawesome-solid:user`, `brand:social/x`,
  `library:company-logo`, `flaticon:512873`

The database column stores only this string, never provider-specific markup.

### DTOs (all `final readonly`)

| DTO | Fields |
|---|---|
| `Icon` | `provider`, `name`, `label`, `source: IconSource`, `category?`, `variant?`, `tags: list<string>`, `metadata: array` |
| `IconSource` | `kind: SourceKind` (`Blade`, `Svg`, `SvgFile`, `Url`), `value: string` |
| `IconQuery` | `search`, `category?`, `variant?`, `page` (1-based), `perPage` |
| `IconResults` | `icons: list<Icon>`, `hasMore`, `total?` |
| `ProviderMetadata` | `categories: array<string,string>`, `variants: array<string,string>`, `remote`, `total?` |
| `SearchPage` | `icons`, `nextCursor?`, `errors: array<providerId, reason>` |

All DTOs serialize to and from plain arrays, so caches never unserialize
objects. This is compatible with Laravel's `cache.serializable_classes`
hardening.

### Database (optional, only when `library.enabled`)

```
icon_hub_icons
  id, name (unique slug), label, collection (nullable, indexed),
  tags (json, nullable), svg (text: sanitized markup),
  is_enabled (bool, indexed), timestamps

icon_hub_hidden_icons
  id, icon (string, unique): the full "provider:name" id, timestamps
```

Operations map onto the tables like this:

- **Delete:** deletes a managed row. Only possible for uploaded icons.
- **Disable / Enable:** flips `is_enabled` on a managed row.
- **Hide / Ignore:** inserts into `icon_hub_hidden_icons`. Works for *any*
  provider and never touches the source.
- **Restore:** deletes that row.

Hidden icons are removed from picker results. Values already stored on your
models still render, so hiding an icon never breaks existing data.

---

## 4. Rendering strategy

`IconRenderer::render(Icon, array $attributes): HtmlString`:

| Source | Rendering |
|---|---|
| `Blade` | Raw SVG from the Blade Icons factory (developer-installed, trusted). Attributes are merged by our own escaping merger. Blade Icons' own attribute rendering does not escape, so it is bypassed. |
| `SvgFile` | Read with a size limit, then sanitized. The sanitized result is cached per path and mtime. |
| `Svg` | Sanitized on every render (remote or uploaded markup is untrusted). |
| `Url` | `<img src="…" alt="" loading="lazy" decoding="async">`. Only `https:` URLs are accepted (plus `http:` if explicitly allowed). The server never fetches the URL, so there is no SSRF surface. |

Behavior shared by all source types:

- **Attributes:** arbitrary developer attributes (`class`, `style`, `data-*`,
  `aria-*`, `x-*`, `@click`, `:class`) are validated by name and HTML-escaped
  by value. `class` is merged, other attributes replace.
- **Accessibility:** icons get `aria-hidden="true"` unless an accessible name
  is supplied.
- **Unknown icon:** renders an empty string. `IconHubColumn`,
  `IconHubEntry`, and the field fall back to their placeholders.

Integration points:

- `IconHub::html($id, $attrs)` returns an `Htmlable`, so it can be passed to
  any Filament `->icon()` method.
- `<x-icon-hub::icon icon="heroicons:o-user" class="h-5 w-5" />`
- `IconHubColumn` and `IconHubEntry`

---

## 5. Caching strategy

`IconCache` wraps a configurable cache store:

```
key = {prefix}:{globalGeneration}:{provider}:{providerGeneration}:{type}:{sha1(json(params))}
```

- **Types:** `index` (enumerated sources), `search` (remote results), `icon`
  (remote icon lookups, primed by search results), `metadata`, `svg`
  (sanitized file contents).
- **Params:** the full normalized `IconQuery` (search, category, variant,
  page, perPage). Different searches can never collide.
- **TTL:** set per type in config. `null` means forever, and only applies
  when you set it explicitly.
- **Flush:** bump the provider's generation, or the global generation. This
  works on every cache store, including those without tags.
  `php artisan icon-hub:clear [provider]` does this. The command is named
  `icon-hub:clear` because Blade Icons already owns `icons:clear`.
- **Disabled cache:** callbacks run directly.

Which provider caches what:

- **Indexed providers** cache their index. Searching an in-memory index of
  a few thousand entries takes milliseconds, so search results are not
  cached separately.
- **HTTP providers** cache search pages and icon lookups. On HTTP 429 they
  store a cool-down key for the duration of `Retry-After`, so they do not
  hammer the API.
- **The library provider** queries the database directly (indexed columns)
  and flushes its metadata cache on save.

---

## 6. Filament integration strategy

`IconPicker extends Filament\Forms\Components\Field` and uses a Blade view.
State, validation, `required()`, `disabled()`, `hidden()`, `live()`,
`helperText()`, `hint()`, and affixes all come from Filament natively.

- **Data loading:** the Alpine component calls
  `$wire.callSchemaComponentMethod(key, 'searchIcons', {...})`, an
  `#[ExposedLivewireMethod] #[Renderless]` method. Each batch is one request,
  and the response is `{icons: [{id, label, html}], next, errors, filters}`.
  The provider filter and icon HTML come from the server, so no provider
  logic runs in JavaScript.
- **Security boundary:** the exposed method only accepts providers the field
  allows, clamps the search length, and never returns exception messages.
- **Initial render:** nothing is fetched until the modal opens. Only the
  already-selected icons are rendered server-side, so remote providers are
  never called just because a form rendered.
- **Infinite scroll:** an `IntersectionObserver` sentinel loads the next
  page of 60 icons (configurable). An in-flight guard and a request sequence
  number ensure that at most one request runs and stale responses are
  dropped.
- **Search:** debounced by 300 ms and performed on the server.
- **Modal:** `<x-filament::modal>` provides dialog semantics, a focus trap,
  and Escape to close.
- **Grid:** `role="listbox"` containing `role="option"` items with a roving
  tabindex. Arrow keys, Home/End, Enter/Space, and Arrow Down from the search
  field all work.
- **Layout:** `grid-template-columns: repeat(auto-fill, minmax(…))` with
  44 px minimum touch targets. CSS logical properties handle RTL.
- **Morph stability:** the `x-data` expression contains only values that
  do not change with state. The initial selection is read from a
  `wire:ignore` JSON script, so a Livewire re-render never re-initializes
  the component mid-interaction. This was found and fixed during browser
  testing.
- **Validation:** the `ValidIcon` rule checks the identifier format, that the
  provider is allowed, and that the icon exists.
- **Customization:** `extraAttributes()` (native),
  `extraIconAttributes()` (every rendered icon), `extraTriggerAttributes()`,
  and `extraAlpineAttributes()`.
- **Assets:** `IconHubColumn` and `IconHubEntry` implement `HasEmbeddedView`,
  the same approach as Filament's own columns. The Alpine component is
  registered as an async `AlpineComponent` and loaded with `x-load` only
  when a picker is present.
- **Plugin:** `IconHubPlugin` only matters for the optional library
  management resources. The field, column, and entry work without
  registering the plugin.

---

## 7. Security considerations

| Threat | Mitigation |
|---|---|
| Malicious SVG (scripts, `on*` handlers, `javascript:` hrefs, `<foreignObject>`, external references) | `enshrined/svg-sanitize` allow-list with remote references removed. Applied to every non-Blade SVG at render time (defense in depth: uploads are also sanitized on save). |
| Oversized SVG (resource exhaustion) | Configurable byte limit, checked before parsing. |
| Remote API returns HTML | Providers can only return an `IconSource`. There is no raw HTML path. |
| Unsafe preview URLs (`javascript:`, `data:`, credentials in URL) | Scheme allow-list (`https`, or `http` only when opted in). URLs with user-info are rejected. |
| SSRF | The server never fetches provider-supplied URLs. The only outbound calls go to endpoints hard-coded in provider classes. |
| API credentials | Live only in provider classes and config. Never serialized into the Livewire payload, the icon metadata sent to the browser, or error messages. |
| Arbitrary method or provider access from the browser | Only `#[ExposedLivewireMethod]` methods are callable. The provider must be in the field's allow-list, and inputs are clamped. |
| Path traversal in local providers | Names are resolved only through the prebuilt index, never concatenated into a path. |
| Upload abuse | Library management is off by default. The upload accepts only SVG within the size limit, is sanitized, and is rejected if sanitization fails. Access goes through Filament resource authorization (policies). |
| Attribute injection | Attribute names are validated and values escaped. |

---

## Architecture Decision Records

### ADR-001: Providers describe icons, a central renderer draws them

**Decision:** `IconProvider` has no `render()` method. Providers return an
`IconSource` (`Blade`, `Svg`, `SvgFile`, or `Url`), and `IconRenderer` owns
all HTML output.

**Context:** The brief sketches `render()` on the provider and also demands
that remote content never injects HTML.

**Alternatives considered:**
- **Option A, `render()` per provider:** maximum flexibility, but every
  provider becomes an XSS risk.
- **Option B, providers return an `Htmlable`:** the same risk, hidden behind a
  type.
- **Option C, a closed set of source kinds with a central renderer:** chosen.

**Reason:** A single sanitization choke point, consistent attribute handling,
and a smaller contract.

**Trade-offs:** A provider cannot emit exotic markup, such as an icon font
glyph. A future `SourceKind` can be added if a real need appears.

**Impact:** Public API. Adding a `SourceKind` later is MINOR. Consumers with
exhaustive `match` statements on `SourceKind` should keep a default arm, as
documented.

### ADR-002: Identifier format `provider:name`

**Decision:** Store `{provider}:{name}` and split on the first colon.

**Context:** Names collide across sources (`user` exists everywhere).

**Alternatives considered:**
- **Option A, Blade Icons names (`heroicon-o-user`):** not namespaced for
  non-Blade sources.
- **Option B, `provider:collection:icon`:** forces a collection concept on
  providers that do not have one.
- **Option C, `provider:name`:** chosen. Collection and variant stay inside
  the provider-defined name.

**Reason:** The smallest format that is still unambiguous. Providers own
their namespace.

**Trade-offs:** A stored value is not directly usable as a Blade Icons
component name. `IconHub::html()` and the column and entry cover rendering.

**Impact:** Public API and persisted data. A format change would be MAJOR
and need a data migration. None is planned.

### ADR-003: One provider per Blade Icons set

**Decision:** Every registered Blade Icons set becomes its own provider,
whose ID is the set name.

**Context:** The brief wants `->providers(['heroicons', 'fontawesome-solid'])`
and dynamic discovery.

**Alternatives considered:**
- **Option A, a single "blade" provider with sets as categories:** filters
  become awkward, and allow-listing sets per field is clumsy.
- **Option B, one provider per set:** chosen.

**Reason:** Provider filters and allow-lists map one-to-one onto what
developers install.

**Trade-offs:** Font Awesome registers several sets (`fontawesome-solid`,
`fontawesome-regular`, and so on), so they appear as separate providers.

**Impact:** Public API (provider IDs equal set names). Stable.

### ADR-004: Caching lives in providers, and invalidation uses generations

**Decision:** Providers cache what is expensive for them, through
`IconCache`. The registry does not cache. Flushing bumps a generation
counter.

**Context:** Local indexes, remote searches, and database queries have very
different cost profiles. Many cache stores (file, database) lack tags.

**Alternatives considered:**
- **Option A, registry-level caching of every search:** double-caches cheap
  in-memory searches and caches database results that should be live.
- **Option B, cache tags:** unsupported on common stores.
- **Option C, provider-level caching with generations:** chosen.

**Reason:** Each provider caches exactly what is expensive. Generations give
deterministic, store-agnostic invalidation.

**Trade-offs:** Old entries expire through TTL rather than being deleted
eagerly.

**Impact:** Internal. Config keys are public, and their additions are MINOR.

### ADR-005: Optional two-table library instead of a management CMS

**Decision:** Ship an optional `library` feature (off by default) with two
tables and two small Filament resources: managed icons and hidden icons.

**Context:** The brief requires upload, disable, hide, restore, and delete,
but warns against building a CMS.

**Alternatives considered:**
- **Option A, no persistence:** hiding icons from Font Awesome would be
  impossible without editing config.
- **Option B, full sets/collections/icons schema:** over-engineered.
- **Option C, two tables, with collection as a column:** chosen.

**Reason:** Covers every listed operation with the smallest schema. SVG is
stored as sanitized text, so no disk configuration is needed.

**Trade-offs:** No per-collection metadata such as descriptions or ordering.

**Impact:** Additive, and opt-in via config and the plugin.

### ADR-006: Implementation followed the architecture in the same pass

**Decision:** The architecture was written first (this document) and
implemented in the same working session, with no separate approval gate.

**Context:** The brief explicitly asked to "present the architecture, then
implement it."

**Reason:** This follows the user's explicit instruction.

**Impact:** Any requested design change will be handled as a follow-up
change with its own ADR.
