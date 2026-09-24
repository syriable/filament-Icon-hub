<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Blade Icons
    |--------------------------------------------------------------------------
    |
    | Every Blade Icons set registered in your application (heroicons,
    | fontawesome-solid, lucide, tabler, ...) becomes a provider whose id is
    | the set name. Use "sets" to allow-list sets (null = all) and "except"
    | to exclude some. "variants" maps icon name prefixes to variant labels
    | so the picker can offer a variant filter.
    |
    */

    'blade_icons' => [
        'enabled' => true,

        // How picked Blade Icons are stored:
        //   'name' - the Blade Icons name, e.g. "heroicon-o-arrow-down-tray".
        //            Works directly with Filament's ->icon(), @svg() and
        //            <x-dynamic-component>. (default)
        //   'id'   - the namespaced id, e.g. "heroicons:o-arrow-down-tray".
        // Both formats are always accepted when reading stored values.
        'store_as' => 'name',

        'sets' => null,

        'except' => [],

        'labels' => [
            // 'heroicons' => 'Heroicons',
        ],

        'variants' => [
            'heroicons' => [
                'o-' => 'Outline',
                's-' => 'Solid',
                'm-' => 'Mini',
                'c-' => 'Micro',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Local SVG directories
    |--------------------------------------------------------------------------
    |
    | Register directories of SVG files as providers. Nested folders become
    | categories. The key is the provider id used in stored values.
    |
    */

    'local' => [
        // 'brand' => [
        //     'path' => resource_path('icons/brand'),
        //     'label' => 'Brand',
        //     'variants' => [], // optional: file prefix => label
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom providers
    |--------------------------------------------------------------------------
    |
    | Class names of your own IconProvider implementations. They are resolved
    | from the container, so constructor dependencies are injected. You may
    | also register providers at runtime with IconHub::register().
    |
    */

    'providers' => [
        // App\Icons\CompanyIconProvider::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Hidden icons
    |--------------------------------------------------------------------------
    |
    | Icon ids ("provider:name") that the picker should never offer. When the
    | library is enabled, icons hidden from the admin panel are added to this
    | list. Hidden icons that are already stored keep rendering.
    |
    */

    'hidden' => [
        // 'heroicons:o-trash',
    ],

    /*
    |--------------------------------------------------------------------------
    | Icon library (optional)
    |--------------------------------------------------------------------------
    |
    | Enables uploaded icons and database-backed hiding. Publish and run the
    | migrations, then register IconHubPlugin::make()->manageLibrary() on a
    | panel to get the management screens.
    |
    */

    'library' => [
        'enabled' => false,

        'provider_id' => 'library',

        // Null uses the translated default label.
        'label' => null,

        'models' => [
            'icon' => Syriable\Filament\Plugins\IconHub\Models\ManagedIcon::class,
            'hidden_icon' => Syriable\Filament\Plugins\IconHub\Models\HiddenIcon::class,
        ],

        'tables' => [
            'icons' => 'icon_hub_icons',
            'hidden_icons' => 'icon_hub_hidden_icons',
        ],

        'navigation_group' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | TTLs are in seconds per cache type. Use null to cache forever, which is
    | never the default. Clear with: php artisan icon-hub:clear [provider]
    |
    |   index    - enumerated icon lists (Blade Icons sets, local directories)
    |   search   - remote API search result pages
    |   icon     - remote API icon lookups (primed by search results)
    |   metadata - provider metadata cached by custom providers
    |   svg      - sanitized contents of local SVG files
    |
    */

    'cache' => [
        'enabled' => true,

        'store' => null,

        'prefix' => 'icon-hub',

        'ttl' => [
            'index' => 86_400,
            'search' => 3_600,
            'icon' => 604_800,
            'metadata' => 86_400,
            'svg' => 86_400,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Picker defaults
    |--------------------------------------------------------------------------
    |
    | "providers" limits which providers pickers offer by default (null =
    | all). Every option can be overridden per field.
    |
    */

    'picker' => [
        'providers' => null,

        'per_page' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Security
    |--------------------------------------------------------------------------
    |
    | Untrusted SVG (uploads, remote APIs, local files) is sanitized and
    | rejected above "max_svg_bytes". Remote image URLs must use one of the
    | allowed schemes; they are rendered by the browser and never fetched by
    | the server.
    |
    */

    'security' => [
        'max_svg_bytes' => 262_144,

        'allowed_url_schemes' => ['https'],
    ],

];
