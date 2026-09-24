<?php

declare(strict_types=1);

use Syriable\Filament\Plugins\IconHub\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

function fixturePath(string $path = ''): string
{
    return __DIR__.'/Fixtures'.($path !== '' ? '/'.ltrim($path, '/') : '');
}
