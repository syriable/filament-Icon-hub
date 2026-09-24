<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\IconHub\Data;

use Syriable\Filament\Plugins\IconHub\Enums\SourceKind;

/**
 * Where an icon's artwork comes from. See {@see SourceKind} for how each kind
 * is rendered.
 */
final readonly class IconSource
{
    public function __construct(
        public SourceKind $kind,
        public string $value,
    ) {}

    public static function blade(string $name): self
    {
        return new self(SourceKind::Blade, $name);
    }

    public static function svg(string $markup): self
    {
        return new self(SourceKind::Svg, $markup);
    }

    public static function svgFile(string $path): self
    {
        return new self(SourceKind::SvgFile, $path);
    }

    public static function url(string $url): self
    {
        return new self(SourceKind::Url, $url);
    }

    /**
     * @return array{kind: string, value: string}
     */
    public function toArray(): array
    {
        return ['kind' => $this->kind->value, 'value' => $this->value];
    }

    /**
     * @param  array{kind: string, value: string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(SourceKind::from($data['kind']), $data['value']);
    }
}
