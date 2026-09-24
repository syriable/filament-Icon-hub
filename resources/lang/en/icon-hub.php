<?php

declare(strict_types=1);

return [

    'picker' => [
        'placeholder' => 'Choose an icon',
        'modal_heading' => 'Choose an icon',
        'search_placeholder' => 'Search icons',
        'provider' => 'Icon set',
        'variant' => 'Style',
        'category' => 'Category',
        'all_providers' => 'All icon sets',
        'all_variants' => 'All styles',
        'all_categories' => 'All categories',
        'unavailable' => 'unavailable',
        'icons' => 'Icons',
        'selected' => 'Selected icons',
        'selected_count' => ':count selected',
        'loaded' => ':count icons shown',
        'loading_more' => 'Loading more icons…',
        'clear' => 'Clear',
        'remove' => 'Remove',
        'done' => 'Done',
    ],

    'empty' => [
        'no_results' => 'No icons found',
        'no_icons' => 'No icons in this collection',
        'no_providers' => 'No icon providers are configured',
        'provider_unavailable' => 'This icon provider is currently unavailable',
    ],

    'errors' => [
        'timeout' => 'The icon service did not respond in time.',
        'rate_limited' => 'Too many requests to the icon service. Try again shortly.',
        'unauthorized' => 'The icon service rejected the configured credentials.',
        'invalid_response' => 'The icon service returned an unexpected response.',
        'unconfigured' => 'This icon provider is not configured.',
        'unavailable' => 'This icon provider is currently unavailable.',
        'request_failed' => 'Icons could not be loaded. Please try again.',
    ],

    'validation' => [
        'invalid' => 'The selected icon is invalid.',
    ],

    'library' => [
        'provider_label' => 'Library',
        'navigation_group' => 'Icons',

        'icons' => [
            'label' => 'Icon',
            'plural_label' => 'Icon library',
        ],

        'hidden' => [
            'label' => 'Hidden icon',
            'plural_label' => 'Hidden icons',
            'hide_action' => 'Hide icons',
            'restore_action' => 'Restore',
            'restored' => 'Icon restored',
            'hidden_count' => '{1} :count icon hidden|[2,*] :count icons hidden',
        ],

        'fields' => [
            'preview' => 'Preview',
            'file' => 'SVG file',
            'svg' => 'SVG markup',
            'svg_help' => 'Upload a file or paste SVG markup. Scripts and unsafe content are removed automatically.',
            'label' => 'Label',
            'name' => 'Name',
            'name_help' => 'Stored as ":provider:name". Leave empty to derive it from the label.',
            'collection' => 'Collection',
            'tags' => 'Tags',
            'is_enabled' => 'Enabled',
            'icon' => 'Icon',
            'icons' => 'Icons to hide',
            'created_at' => 'Hidden at',
        ],

        'actions' => [
            'enable' => 'Enable',
            'disable' => 'Disable',
        ],

        'validation' => [
            'invalid_svg' => 'The SVG could not be sanitized into a valid icon.',
            'name_required' => 'A label or name is required.',
            'name_taken' => 'An icon with this name already exists.',
            'svg_required' => 'Upload an SVG file or paste SVG markup.',
        ],
    ],

];
