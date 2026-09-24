# Changelog

All notable changes to `filament-icon-hub` will be documented in this file.

## Unreleased

- **Changed:** Blade Icons are now stored by their Blade Icons name (`heroicon-o-arrow-down-tray`) instead of `heroicons:o-arrow-down-tray`, so stored values work directly with Filament's `->icon()` and `@svg()`. Both formats are still accepted, and forms convert old values on load. Set `blade_icons.store_as` to `id` to keep the previous format.
- **Fixed:** Blade Icons sets and local directories containing numeric file names (e.g. `123.svg`) no longer fail to index.
- Add `IconSelect`: a native searchable Select alternative to `IconPicker`.
- `IconSelect` shows its options as an icon grid by default; use `grid(false)` for a list.
- Initial release: `IconPicker` field, `IconHubColumn`, `IconHubEntry`,
  `<x-icon-hub::icon>`, icon registry with Blade Icons, local SVG, library and
  HTTP provider support, caching, sanitized rendering and the optional icon
  library management screens.
