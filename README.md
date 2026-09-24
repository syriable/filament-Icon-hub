# Filament Icon Hub

> A lightweight, extensible icon picker and icon provider system for Filament 5.

```php
IconPicker::make('icon')
```

This single line gives you a searchable, paginated, keyboard-accessible icon
picker covering every Blade Icons set installed in your application.
Advanced applications can add local SVG directories, uploaded icons, or any
remote icon API through a small provider contract, without the picker knowing
where icons come from.

```
IconPicker (field) ──▶ IconRegistry ──▶ IconProvider
                                          ├── Blade Icons sets (heroicons, lucide, tabler, fontawesome, …)
                                          ├── Local SVG directories
                                          ├── Uploaded icons (optional library)
                                          └── Your providers (remote APIs, design systems, …)
```

## Contents

1. [Requirements](#requirements)
2. [Installation](#installation)
3. [Basic usage](#basic-usage)
4. [Blade Icons](#blade-icons)
5. [Local SVG provider](#local-svg-provider)
6. [Custom providers](#custom-providers)
7. [Remote API providers](#remote-api-providers)
8. [Provider registration](#provider-registration)
9. [Search](#search)
10. [Filtering](#filtering)
11. [Caching](#caching)
12. [Customization](#customization)
13. [Rendering](#rendering)
14. [Icon library (uploads and hidden icons)](#icon-library-uploads-and-hidden-icons)
15. [Security](#security)
16. [Creating a provider: checklist](#creating-a-provider-checklist)
17. [Configuration reference](#configuration-reference)
18. [Troubleshooting](#troubleshooting)
19. [Testing](#testing)

## Requirements

| | Version |
|---|---|
| PHP | 8.4+ |
| Laravel | 13+ |
| Filament | 5.8.4+ |
| Livewire | 4.4+ |

## Installation

```bash
composer require syriable/filament-icon-hub
php artisan filament:assets
```

`filament:assets` publishes the picker's small JavaScript and CSS files. Run
it again after every update of the package. It is usually part of your
`composer.json` `post-autoload-dump` scripts already.

Optionally publish the config and translations:

```bash
php artisan vendor:publish --tag="icon-hub-config"
php artisan vendor:publish --tag="icon-hub-translations"
```

You do not need to register a plugin to use the field, column, or entry.
`IconHubPlugin` is only needed for the optional
[icon library screens](#icon-library-uploads-and-hidden-icons).

## Basic usage

### Form field

```php
use Syriable\Filament\Plugins\IconHub\Forms\Components\IconPicker;

IconPicker::make('icon')
    ->required();
```

#### What gets stored

Icons from Blade Icons sets are stored by their **Blade Icons name**, so the
value works directly anywhere Blade Icons or Filament accepts an icon:

```
heroicon-o-arrow-down-tray      Heroicons
fas-house                       Font Awesome (solid)
lucide-user                     Lucide
```

```php
NavigationItem::make('Reports')->icon($category->icon); // Filament
```

```blade
@svg($category->icon, 'h-5 w-5')
```

Icons that have no Blade Icons name are stored as a namespaced
`{provider}:{name}` identifier: local SVG directories, the uploaded library,
and remote or custom providers.

```
brand:social/github
library:company-logo
flaticon:512873
```

Both formats are always accepted when reading values. Older values such as
`heroicons:o-user` still render and validate, and a form converts them to
`heroicon-o-user` when it loads. To store namespaced ids for Blade Icons too,
set `blade_icons.store_as` to `id`. A `string` column of 255 characters is
enough for either format.

`IconPicker` behaves like any Filament field: `required()`, `disabled()`,
`hidden()`, `live()`, `helperText()`, `hint()`, `default()`, `afterStateUpdated()`,
validation, dehydration, and so on.

Multiple selection stores an array, so cast the attribute to `array`:

```php
IconPicker::make('icons')->multiple();
```

### Select input (compact alternative)

`IconSelect` is Filament's native searchable `Select` whose dropdown shows the
icons as a grid of tiles. The selected icon is shown with its name in the
field. Use it when a modal is more than you need, for example in dense forms,
filters, or table actions.

```php
use Syriable\Filament\Plugins\IconHub\Forms\Components\IconSelect;

IconSelect::make('icon');

IconSelect::make('icons')
    ->multiple()
    ->providers(['heroicons', 'brand'])
    ->optionsLimit(30)            // icons per request (default 50)
    ->groupByProvider(false)      // default: grouped when several providers are offered
    ->grid(false)                 // list of "icon + name" rows instead of the tile grid
    ->extraIconAttributes(['class' => 'text-primary-600']);
```

- It stores the same `provider:name` identifiers as `IconPicker` and uses the
  same registry, validation, and hidden-icon rules, so the two fields are
  interchangeable.
- Icons load when the dropdown opens and as you type. Nothing is fetched when
  the form renders, apart from the label of the selected icon.
- In the grid, names appear as tooltips and remain available to screen
  readers. Variants that share a name are labeled, for example *Star
  (Outline)* and *Star (Solid)*. With `grid(false)` the dropdown is a list
  of rows with icon, name, and variant.
- Every `Select` option still works: `required()`, `multiple()`,
  `placeholder()`, `searchDebounce()`, `live()`, and so on.

| | `IconPicker` | `IconSelect` |
|---|---|---|
| UI | Modal with an icon grid | Dropdown with an icon grid (or a list) |
| Browsing | Infinite scroll, provider, style, and category filters | Search, first `optionsLimit` results |
| Best for | Visually choosing from large sets | Compact forms, when users know what they want |

### Table column and infolist entry

```php
use Syriable\Filament\Plugins\IconHub\Infolists\Components\IconHubEntry;
use Syriable\Filament\Plugins\IconHub\Tables\Columns\IconHubColumn;

IconHubColumn::make('icon');                          // also handles arrays
IconHubColumn::make('icon')->size('lg')->showLabel();

IconHubEntry::make('icon')->placeholder('No icon');
```

### Anywhere Filament accepts an icon

`IconHub::html()` returns an `Htmlable`, which every Filament `->icon()`
method accepts:

```php
use Syriable\Filament\Plugins\IconHub\Facades\IconHub;

NavigationItem::make('Reports')->icon(IconHub::html($team->icon));
```

## Blade Icons

Every registered [Blade Icons](https://github.com/driesvints/blade-icons) set
is discovered automatically and becomes a provider whose ID is the set name:

| Package | Provider IDs |
|---|---|
| `blade-ui-kit/blade-heroicons` (bundled with Filament) | `heroicons` |
| `owenvoke/blade-fontawesome` | `fontawesome-solid`, `fontawesome-regular`, `fontawesome-brands` |
| `mallardduck/blade-lucide-icons` | `lucide` |
| `secondnetwork/blade-tabler-icons` | `tabler` |
| `codeat3/blade-phosphor-icons` | `phosphor` |

Install an icon package and it appears in the picker. Limit what is offered
globally in `config/icon-hub.php`:

```php
'blade_icons' => [
    'sets' => ['heroicons', 'lucide'],   // null = every registered set
    'except' => [],
    'labels' => ['heroicons' => 'Heroicons'],
    'variants' => [
        // Name prefix => variant label. Enables the "Style" filter.
        'heroicons' => ['o-' => 'Outline', 's-' => 'Solid', 'm-' => 'Mini', 'c-' => 'Micro'],
    ],
],
```

Or limit it per field:

```php
IconPicker::make('icon')->providers(['heroicons', 'lucide']);
```

Icons in subdirectories of a set (Blade Icons names such as `solid.user`)
automatically get the directory as their category.

## Local SVG provider

Point a provider at a directory of SVG files:

```php
// config/icon-hub.php
'local' => [
    'brand' => [
        'path' => resource_path('icons/brand'),
        'label' => 'Brand',
        'variants' => [], // optional, file-name prefix => label
    ],
],
```

```
resources/icons/brand/
├── logo.svg                 → brand:logo
└── social/
    ├── github.svg           → brand:social/github  (category "social")
    └── x.svg                → brand:social/x
```

- Nested folders become categories and are part of the name.
- The directory is scanned once and the index is cached. Clear it after
  adding files with `php artisan icon-hub:clear brand`.
- File contents are sanitized on render, and the sanitized result is cached
  per file and modification time.

## Custom providers

A provider implements one small interface:

```php
namespace Syriable\Filament\Plugins\IconHub\Contracts;

interface IconProvider
{
    public function id(): string;                             // "company"
    public function label(): string;                          // "Company icons", must be cheap
    public function status(): ProviderStatus;                 // Available | Unconfigured | Unavailable, must be cheap
    public function metadata(): ProviderMetadata;             // categories, variants, remote, total
    public function search(IconQuery $query): IconResults;    // one page of icons
    public function find(string $name): ?Icon;                // resolve a stored value
}
```

Providers **never render HTML**. They return normalized `Icon` objects whose
`IconSource` says where the artwork comes from:

| Source | Use for |
|---|---|
| `IconSource::blade('heroicon-o-user')` | Blade Icons names |
| `IconSource::svgFile('/abs/path.svg')` | Local files |
| `IconSource::svg('<svg …>')` | SVG markup (sanitized on render) |
| `IconSource::url('https://…/icon.png')` | Remote images, rendered with `<img>` |

### When you can list every icon: extend `IndexedIconProvider`

This covers a JSON manifest, a design-system package, or a database table of
a few thousand rows. You only build the list. Caching, search, ranking,
pagination, categories, and variants are handled for you.

```php
use Syriable\Filament\Plugins\IconHub\Data\Icon;
use Syriable\Filament\Plugins\IconHub\Data\IconSource;
use Syriable\Filament\Plugins\IconHub\Providers\IndexedIconProvider;

final class DesignSystemIconProvider extends IndexedIconProvider
{
    public function id(): string
    {
        return 'ds';
    }

    public function label(): string
    {
        return 'Design system';
    }

    protected function buildIndex(): iterable
    {
        $manifest = json_decode(file_get_contents(base_path('design-system/icons.json')), true);

        foreach ($manifest['icons'] as $item) {
            yield new Icon(
                provider: 'ds',
                name: $item['slug'],
                label: $item['title'],
                source: IconSource::svgFile(base_path("design-system/svg/{$item['slug']}.svg")),
                category: $item['group'] ?? null,
                tags: $item['keywords'] ?? [],
            );
        }
    }

    protected function indexCacheKey(): array
    {
        // Rebuild the cached index whenever the manifest changes.
        return [filemtime(base_path('design-system/icons.json'))];
    }
}
```

### Anything else: implement `IconProvider` directly

You are responsible for `search()` pagination (`IconQuery::$page`,
`::$perPage`, `::offset()`) and for caching expensive work through
`Syriable\Filament\Plugins\IconHub\Cache\IconCache`.

## Remote API providers

The core ships no Flaticon, Font Awesome API, or other service-specific
code. Remote APIs extend the generic `HttpIconProvider`, which handles
everything that is not specific to one API:

- connect and request timeouts
- 401/403 mapped to *unauthorized*, 429 to *rate limited* (with a
  `Retry-After` cool-down so the API is not hammered), 5xx to *unavailable*
- malformed JSON and mapping errors mapped to *invalid response*
- search result caching per query (provider + search + category + variant +
  page + per page)
- priming the icon cache from search results, so rendering an icon that was
  picked earlier normally never calls the API

Here is a complete example for a Flaticon-style API:

```php
namespace App\Icons;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Syriable\Filament\Plugins\IconHub\Data\Icon;
use Syriable\Filament\Plugins\IconHub\Data\IconQuery;
use Syriable\Filament\Plugins\IconHub\Data\IconResults;
use Syriable\Filament\Plugins\IconHub\Data\IconSource;
use Syriable\Filament\Plugins\IconHub\Data\ProviderMetadata;
use Syriable\Filament\Plugins\IconHub\Providers\HttpIconProvider;

final class FlaticonProvider extends HttpIconProvider
{
    protected int $timeout = 4;

    public function id(): string
    {
        return 'flaticon';
    }

    public function label(): string
    {
        return 'Flaticon';
    }

    public function metadata(): ProviderMetadata
    {
        return new ProviderMetadata(
            categories: ['arrows' => 'Arrows', 'business' => 'Business', 'social' => 'Social'],
            remote: true,
        );
    }

    // Missing key: the provider shows as "not configured" instead of failing.
    protected function isConfigured(): bool
    {
        return filled(config('services.flaticon.key'));
    }

    // Credentials stay on the server. They are never part of an Icon.
    protected function http(): PendingRequest
    {
        return parent::http()
            ->baseUrl('https://api.flaticon.test/v3')
            ->withToken(config('services.flaticon.key'));
    }

    protected function searchRequest(PendingRequest $http, IconQuery $query): Response
    {
        return $http->get('/search/icons', array_filter([
            'q' => $query->search,
            'category' => $query->category,
            'page' => $query->page,
            'limit' => $query->perPage,
        ]));
    }

    protected function mapSearchResponse(array $data, IconQuery $query): IconResults
    {
        return new IconResults(
            icons: array_map($this->toIcon(...), $data['data']),
            hasMore: $data['metadata']['page'] < $data['metadata']['pages'],
            total: $data['metadata']['total'] ?? null,
        );
    }

    // Optional: resolve icons that are not in the cache yet.
    protected function findRequest(PendingRequest $http, string $name): ?Response
    {
        return $http->get("/item/icon/{$name}");
    }

    protected function mapIconResponse(array $data, string $name): ?Icon
    {
        return isset($data['data']) ? $this->toIcon($data['data']) : null;
    }

    private function toIcon(array $item): Icon
    {
        return new Icon(
            provider: 'flaticon',
            name: (string) $item['id'],
            label: $item['description'],
            source: IconSource::url($item['images']['64']),
            category: $item['category'] ?? null,
            tags: $item['tags'] ?? [],
        );
    }
}
```

Register it and it shows up in every picker:

```php
// config/icon-hub.php
'providers' => [App\Icons\FlaticonProvider::class],
```

### Failure behavior

When a remote provider fails (timeout, HTTP error, bad credentials, rate
limit, malformed data), the picker shows a short provider-specific notice
and **every other provider keeps working**. The form never breaks. Raw
exception messages are logged through `report()` and never sent to the
browser.

## Provider registration

There are three equivalent ways to register a provider:

```php
// 1. config/icon-hub.php, resolved from the container (dependencies injected)
'providers' => [App\Icons\CompanyIconProvider::class],

// 2. At runtime, e.g. in AppServiceProvider::boot()
use Syriable\Filament\Plugins\IconHub\Facades\IconHub;

IconHub::register(new CompanyIconProvider(...));
IconHub::register(CompanyIconProvider::class);

// 3. Lazily, only when icons are first needed
IconHub::discoverUsing(fn () => [new CompanyIconProvider(...)]);
```

Other registry operations:

```php
IconHub::has('company');
IconHub::provider('company');          // throws ProviderNotFound
IconHub::providers(['heroicons', 'company']);
IconHub::forget('fontawesome-brands'); // remove a discovered provider
IconHub::status('flaticon');           // ProviderStatus
IconHub::find('heroicons:o-user');     // ?Icon, never throws
```

A provider registered with an existing ID replaces the previous one. This
lets you override a discovered provider.

## Search

Search runs on the server. The browser only ever receives the current page
of icons (60 by default), each with pre-rendered, sanitized markup.

- **Indexed providers** (Blade Icons, local, custom indexed) match every
  search term against the name, label, tags, and category. Exact matches
  rank first, then prefix matches, then the rest.
- **The library provider** searches with indexed database queries.
- **Remote providers** pass the query to the API.

Search programmatically:

```php
use Syriable\Filament\Plugins\IconHub\Data\IconQuery;

$page = IconHub::search(new IconQuery('arrow', perPage: 30), ['heroicons', 'lucide']);

$page->icons;       // list<Icon>
$page->errors;      // ['flaticon' => 'timeout']
$page->nextCursor;  // pass back for the next page
```

With several providers, results are served provider by provider, and the
cursor remembers where the previous page stopped.

## Filtering

The picker builds its filters from the registered providers. Nothing is
hard-coded.

- **Icon set:** shown when a picker offers more than one provider. Providers
  that are not configured are listed but disabled.
- **Style (variant):** shown when the selected provider exposes variants
  (`ProviderMetadata::$variants`).
- **Category:** shown when the selected provider exposes categories
  (`ProviderMetadata::$categories`).

```php
IconPicker::make('icon')
    ->providers(['heroicons', 'brand', 'flaticon'])
    ->showProviderFilter()      // default: automatic
    ->showVariantFilter()       // default: true
    ->showCategoryFilter();     // default: true
```

## Caching

| Type | What | Default TTL |
|---|---|---|
| `index` | Enumerated icon lists (Blade Icons sets, local directories) | 1 day |
| `search` | Remote API result pages | 1 hour |
| `icon` | Remote icon lookups, primed by search results | 7 days |
| `metadata` | Metadata cached by custom providers | 1 day |
| `svg` | Sanitized local SVG contents | 1 day |

```php
'cache' => [
    'enabled' => true,
    'store' => null,          // any cache store; null = default
    'prefix' => 'icon-hub',
    'ttl' => ['search' => 900], // null caches forever, but only if you set it explicitly
],
```

Cache keys are deterministic and include the provider and every query
parameter (search, category, variant, page, per page), so a search for
`user` can never return the results for `home`. Invalidation bumps a
generation counter and works with every cache store, including those
without tags:

```bash
php artisan icon-hub:clear            # everything
php artisan icon-hub:clear heroicons  # one provider
```

In code, call `app(IconCache::class)->flush('heroicons')`.

## Customization

```php
IconPicker::make('icon')
    ->providers(['heroicons', 'brand'])     // allow-list and order
    ->multiple()                            // store an array
    ->searchable(false)                     // hide the search input
    ->perPage(90)                           // icons per request
    ->placeholder('Pick an icon')
    ->modalHeading('Choose a menu icon')
    ->modalWidth(Width::FiveExtraLarge)
    ->showProviderFilter()
    ->showCategoryFilter(false)
    ->showVariantFilter(false);
```

### Arbitrary attributes

Pass any attributes (`class`, `style`, `data-*`, `aria-*`, `x-*`, `@click`,
`:class`) to each rendered element:

```php
IconPicker::make('icon')
    ->extraAttributes(['data-icon-picker' => 'true'])                     // wrapper
    ->extraAlpineAttributes(['x-on:icon-picked.window' => 'refresh()'])   // wrapper (Alpine)
    ->extraTriggerAttributes(['class' => 'my-trigger', 'data-test' => 'x']) // open button
    ->extraIconAttributes(['class' => 'my-icon', 'x-on:mouseenter' => 'preview($el)']); // every icon
```

Each method accepts a closure and a `merge: true` argument, like Filament's
own `extra*Attributes()` methods.

### Messages and translations

All labels and empty states (*No icons found*, *No icons in this
collection*, *Provider unavailable*, *No providers configured*) live in
`lang/vendor/icon-hub/{locale}/icon-hub.php` after publishing the
translations.

### Styling

The UI uses Filament's own components and color variables, so it follows
your panel's theme, dark mode, and RTL automatically. Hook your own CSS into
the `fi-icon-hub-*` classes. For example, to change the grid density:

```css
.fi-icon-hub-picker { --icon-hub-item-size: 5rem; --icon-hub-icon-size: 2rem; }
```

## Rendering

```blade
{{-- Blade component --}}
<x-icon-hub::icon :icon="$category->icon" class="h-5 w-5 text-primary-600" />

{{-- Facade --}}
{{ \Syriable\Filament\Plugins\IconHub\Facades\IconHub::html($category->icon, ['class' => 'h-5 w-5']) }}
```

- Unknown or unavailable icons render an empty string.
- Icons are decorative (`aria-hidden="true"`) unless you pass `aria-label`,
  in which case they get `role="img"`.
- Blade Icons render inline SVG, so `currentColor` and CSS sizing work.
  Remote URL icons render as `<img>` and cannot be recolored with CSS.

Rendering never calls a remote API for icons that were selected through the
picker: search results prime the icon cache, and lookups are also memoized
per process. Table columns therefore stay cheap.

## Icon library (uploads and hidden icons)

The optional library lets administrators:

| Action | Effect |
|---|---|
| **Upload** | Add an SVG icon (sanitized, stored in the database) to the `library` provider |
| **Disable / Enable** | Keep an uploaded icon but remove it from the picker |
| **Delete** | Permanently remove an uploaded icon |
| **Hide (ignore)** | Remove *any* icon (Font Awesome, Heroicons, remote, …) from the picker without touching its source |
| **Restore** | Make a hidden icon available again |

Icons that are already stored keep rendering after being hidden or disabled,
so existing data never breaks.

1. Enable it:

   ```php
   // config/icon-hub.php
   'library' => ['enabled' => true],
   ```

2. Publish and run the migration (two tables: `icon_hub_icons` and
   `icon_hub_hidden_icons`):

   ```bash
   php artisan vendor:publish --tag="icon-hub-migrations"
   php artisan migrate
   ```

3. Register the management screens on a panel:

   ```php
   use Syriable\Filament\Plugins\IconHub\IconHubPlugin;

   $panel->plugin(IconHubPlugin::make()->manageLibrary());
   ```

The **Icon library** resource manages uploads. The **Hidden icons**
resource lists hidden icons and offers a *Hide icons* action that opens a
multiple icon picker.

Hide icons statically without a database:

```php
'hidden' => ['heroicons:o-trash', 'fontawesome-brands:tiktok'],
```

Or programmatically:

```php
app(\Syriable\Filament\Plugins\IconHub\Actions\HideIcons::class)->handle(['heroicons:o-trash']);
app(\Syriable\Filament\Plugins\IconHub\Actions\RestoreIcon::class)->handle('heroicons:o-trash');
```

**Authorization:** the resources follow Filament's normal policy
resolution. Create `ManagedIconPolicy` and `HiddenIconPolicy` (or register
policies for your own models configured in `library.models`) to restrict who
may upload and hide icons.

## Security

| Concern | How it is handled |
|---|---|
| Malicious SVG | Every non-Blade SVG (uploads, local files, API markup) goes through an allow-list sanitizer (`enshrined/svg-sanitize`) that removes scripts, `on*` handlers, `javascript:` links, `<foreignObject>`, and remote references. Uploads are sanitized again on every render. |
| Oversized SVG | Rejected above `security.max_svg_bytes` before parsing. |
| Remote HTML | Providers cannot return HTML. They return an `IconSource`, and one central renderer produces all markup. |
| Unsafe URLs | Only `https:` URLs without embedded credentials render (configure `security.allowed_url_schemes`). `javascript:` and `data:` URLs never render. |
| SSRF | The server never fetches icon URLs. Browsers load them as images. The only outbound requests go to endpoints hard-coded in your provider class. |
| API credentials | Live in provider classes and config only. Never serialized into Livewire payloads, icon data, or error messages. |
| Browser-supplied input | Only the picker's `#[ExposedLivewireMethod]` methods are callable. They accept only providers allowed on that field, clamp search length and page size, and return nothing when the field is disabled. |
| Stored values | Validated server-side: format, allowed provider, and existence. |
| Attributes | Attribute names are validated and values HTML-escaped. |
| Path traversal | Local icons are resolved only through the prebuilt index, never by concatenating user input into paths. |

## Creating a provider: checklist

- [ ] `id()` is lowercase (`a-z`, `0-9`, `.`, `_`, `-`) and **never changes**:
      it is stored in your database.
- [ ] `label()` and `status()` do no network I/O.
- [ ] `search()` honors `page` and `perPage`, and sets `hasMore` correctly.
- [ ] `find()` resolves every name that `search()` returns.
- [ ] Icons are returned with the provider's own ID in `Icon::$provider`.
- [ ] Anything expensive is cached through `IconCache`, or you extend
      `IndexedIconProvider` or `HttpIconProvider`, which do it for you.
- [ ] Throw exceptions freely. The registry isolates them. Throw
      `ProviderException::timeout()`, `::unauthorized()`, and so on to show a
      specific message.
- [ ] Add `match` default arms when switching on `SourceKind`. New kinds may
      appear in minor releases.

## Configuration reference

| Key | Default | Description |
|---|---|---|
| `blade_icons.enabled` | `true` | Discover Blade Icons sets |
| `blade_icons.store_as` | `name` | Store Blade Icons as their Blade Icons name (`heroicon-o-user`) or as `id` (`heroicons:o-user`) |
| `blade_icons.sets` | `null` | Allow-list of set names (`null` = all) |
| `blade_icons.except` | `[]` | Set names to exclude |
| `blade_icons.labels` | `[]` | Display labels per set |
| `blade_icons.variants` | Heroicons prefixes | Name prefix → variant label, per set |
| `local` | `[]` | Local SVG providers: `id => [path, label?, variants?]` |
| `providers` | `[]` | Custom provider classes (container-resolved) |
| `hidden` | `[]` | Icon IDs never offered by the picker |
| `library.enabled` | `false` | Enables uploads and database hiding |
| `library.provider_id` | `library` | Provider ID for uploaded icons |
| `library.label` | `null` | Provider label (`null` = translated default) |
| `library.models.*` | package models | Swap in your own models |
| `library.tables.*` | `icon_hub_*` | Table names |
| `library.navigation_group` | `null` | Navigation group for the resources |
| `cache.enabled` | `true` | Enable caching |
| `cache.store` | `null` | Cache store (`null` = default) |
| `cache.prefix` | `icon-hub` | Key prefix |
| `cache.ttl.*` | see [Caching](#caching) | TTL in seconds per type (`null` = forever) |
| `picker.providers` | `null` | Default providers for pickers (`null` = all) |
| `picker.per_page` | `60` | Icons per request |
| `security.max_svg_bytes` | `262144` | Maximum SVG size |
| `security.allowed_url_schemes` | `['https']` | Allowed image URL schemes |

## Troubleshooting

**The picker button does nothing, or the modal is empty and unstyled.**
Run `php artisan filament:assets`. The picker's JavaScript and CSS are
Filament assets.

**My new SVG files or icon package do not appear.** The index is cached. Run
`php artisan icon-hub:clear`, or `icon-hub:clear {provider}`.

**A provider shows "(unavailable)" in the filter.** Its `status()` is not
`Available`. For remote providers, `isConfigured()` usually returned
`false` (for example, a missing API key).

**"The icon service did not respond in time."** The remote API timed out.
Other providers still work. Increase `$timeout` on your provider, or check
the logs: every provider failure is reported through `report()`.

**A stored icon renders nothing.** The provider was removed or renamed, the
icon no longer exists in the source, or the provider is not available.
Provider IDs are part of stored values, so never rename them. Register the
old ID again if needed.

**Validation says the icon is invalid.** The value must be
`provider:name`, the provider must be allowed on that field
(`->providers([...])`), and the icon must exist.

**Remote icons are black and cannot be recolored.** URL sources render as
`<img>`. Return `IconSource::svg($markup)` from your provider if the API
offers SVG markup. It will be sanitized.

## Testing

```bash
composer test      # Pest
composer analyse   # PHPStan (level 8, Larastan)
composer format    # Pint
composer serve     # Workbench panel with a demo page at /admin/icon-demo
```

## Architecture

See [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) for the package structure,
provider architecture, data model, rendering and caching strategies,
Filament integration, security considerations, and decision records.

## License

MIT. See [LICENSE.md](LICENSE.md).
