<?php

declare(strict_types=1);

use Syriable\Filament\Plugins\IconHub\Rendering\SvgAttributes;
use Syriable\Filament\Plugins\IconHub\Rendering\SvgSanitizer;

describe('SvgSanitizer', function () {
    it('removes scripts, event handlers, javascript urls and foreign objects', function () {
        $svg = (new SvgSanitizer)->sanitize(file_get_contents(fixturePath('icons/brand/evil.svg')));

        expect($svg)->toStartWith('<svg')
            ->not->toContain('<script')
            ->not->toContain('onload')
            ->not->toContain('javascript:')
            ->not->toContain('foreignObject')
            ->not->toContain('alert');
    });

    it('removes external references', function () {
        $svg = (new SvgSanitizer)->sanitize('<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><use xlink:href="https://evil.test/sprite.svg#x"/><image href="https://evil.test/track.png"/></svg>');

        expect($svg)->not->toContain('evil.test');
    });

    it('rejects markup that is not an svg document', function (string $markup) {
        expect((new SvgSanitizer)->sanitize($markup))->toBeNull();
    })->with([
        'html' => '<div onclick="alert(1)">x</div>',
        'script' => '<script>alert(1)</script>',
        'empty' => '',
        'garbage' => 'not markup at all',
    ]);

    it('rejects oversized markup before parsing', function () {
        $markup = '<svg xmlns="http://www.w3.org/2000/svg">'.str_repeat('<g></g>', 1000).'</svg>';

        expect((new SvgSanitizer(maxBytes: 100))->sanitize($markup))->toBeNull();
    });
});

describe('SvgAttributes', function () {
    it('merges classes and replaces other attributes on the root element', function () {
        $svg = SvgAttributes::merge('<svg viewBox="0 0 24 24" class="a" width="24"><path d="M0"/></svg>', [
            'class' => 'b',
            'width' => '16',
            'data-icon' => 'user',
            'x-on:click' => 'open = true',
            '@mouseenter' => 'hover()',
        ]);

        expect($svg)->toContain('viewBox="0 0 24 24"')
            ->toContain('class="a b"')
            ->toContain('width="16"')
            ->not->toContain('width="24"')
            ->toContain('data-icon="user"')
            ->toContain('x-on:click="open = true"')
            ->toContain('@mouseenter="hover()"');
    });

    it('escapes values and drops invalid attribute names', function () {
        $html = SvgAttributes::toHtml([
            'title' => '"><script>alert(1)</script>',
            '"><img src=x onerror=alert(1)>' => 'x',
            'hidden' => true,
            'skipped' => false,
        ]);

        expect($html)->toBe('title="&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;" hidden');
    });
});
