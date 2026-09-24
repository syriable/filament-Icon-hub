<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('icon-hub.library.tables.icons', 'icon_hub_icons'), function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('label');
            $table->string('collection')->nullable()->index();
            $table->json('tags')->nullable();
            $table->text('svg');
            $table->boolean('is_enabled')->default(true)->index();
            $table->timestamps();
        });

        Schema::create(config('icon-hub.library.tables.hidden_icons', 'icon_hub_hidden_icons'), function (Blueprint $table): void {
            $table->id();
            $table->string('icon')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('icon-hub.library.tables.hidden_icons', 'icon_hub_hidden_icons'));
        Schema::dropIfExists(config('icon-hub.library.tables.icons', 'icon_hub_icons'));
    }
};
