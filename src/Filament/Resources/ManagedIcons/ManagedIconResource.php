<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\IconHub\Filament\Resources\ManagedIcons;

use BackedEnum;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Syriable\Filament\Plugins\IconHub\Models\ManagedIcon;
use Syriable\Filament\Plugins\IconHub\Tables\Columns\IconHubColumn;

/**
 * Manage icons uploaded by administrators. Deleting removes the icon;
 * disabling keeps it but removes it from the picker.
 */
class ManagedIconResource extends Resource
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?string $recordTitleAttribute = 'label';

    /**
     * @return class-string<ManagedIcon>
     */
    public static function getModel(): string
    {
        /** @var class-string<ManagedIcon> */
        return config('icon-hub.library.models.icon', ManagedIcon::class);
    }

    public static function getModelLabel(): string
    {
        return __('icon-hub::icon-hub.library.icons.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('icon-hub::icon-hub.library.icons.plural_label');
    }

    public static function getNavigationGroup(): ?string
    {
        $group = config('icon-hub.library.navigation_group');

        return is_string($group) ? $group : __('icon-hub::icon-hub.library.navigation_group');
    }

    public static function providerId(): string
    {
        return (string) config('icon-hub.library.provider_id', 'library');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('label')
                        ->label(__('icon-hub::icon-hub.library.fields.label'))
                        ->required()
                        ->maxLength(255),
                    TextInput::make('name')
                        ->label(__('icon-hub::icon-hub.library.fields.name'))
                        ->helperText(__('icon-hub::icon-hub.library.fields.name_help', ['provider' => static::providerId()]))
                        ->maxLength(200)
                        ->alphaDash(),
                    TextInput::make('collection')
                        ->label(__('icon-hub::icon-hub.library.fields.collection'))
                        ->maxLength(100)
                        ->datalist(fn (): array => static::getModel()::query()->whereNotNull('collection')->distinct()->orderBy('collection')->pluck('collection')->all()),
                    TagsInput::make('tags')
                        ->label(__('icon-hub::icon-hub.library.fields.tags')),
                    FileUpload::make('svg_file')
                        ->label(__('icon-hub::icon-hub.library.fields.file'))
                        ->acceptedFileTypes(['image/svg+xml'])
                        ->maxSize((int) ceil((int) config('icon-hub.security.max_svg_bytes', 262_144) / 1024))
                        ->storeFiles(false)
                        ->columnSpanFull(),
                    Textarea::make('svg')
                        ->label(__('icon-hub::icon-hub.library.fields.svg'))
                        ->helperText(__('icon-hub::icon-hub.library.fields.svg_help'))
                        ->rows(6)
                        ->columnSpanFull(),
                    Toggle::make('is_enabled')
                        ->label(__('icon-hub::icon-hub.library.fields.is_enabled'))
                        ->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('label')
            ->columns([
                IconHubColumn::make('preview')
                    ->label(__('icon-hub::icon-hub.library.fields.preview'))
                    ->state(fn (ManagedIcon $record): string => static::providerId().':'.$record->name)
                    ->size('lg'),
                TextColumn::make('label')
                    ->label(__('icon-hub::icon-hub.library.fields.label'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label(__('icon-hub::icon-hub.library.fields.name'))
                    ->searchable()
                    ->copyable()
                    ->copyableState(fn (ManagedIcon $record): string => static::providerId().':'.$record->name),
                TextColumn::make('collection')
                    ->label(__('icon-hub::icon-hub.library.fields.collection'))
                    ->badge()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('tags')
                    ->label(__('icon-hub::icon-hub.library.fields.tags'))
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                ToggleColumn::make('is_enabled')
                    ->label(__('icon-hub::icon-hub.library.fields.is_enabled')),
            ])
            ->filters([
                SelectFilter::make('collection')
                    ->label(__('icon-hub::icon-hub.library.fields.collection'))
                    ->options(fn (): array => static::getModel()::query()->whereNotNull('collection')->distinct()->orderBy('collection')->pluck('collection', 'collection')->all()),
                TernaryFilter::make('is_enabled')
                    ->label(__('icon-hub::icon-hub.library.fields.is_enabled')),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('enable')
                        ->label(__('icon-hub::icon-hub.library.actions.enable'))
                        ->icon(Heroicon::OutlinedEye)
                        ->action(fn (Collection $records) => static::getModel()::query()->whereKey($records->modelKeys())->update(['is_enabled' => true]))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('disable')
                        ->label(__('icon-hub::icon-hub.library.actions.disable'))
                        ->icon(Heroicon::OutlinedEyeSlash)
                        ->action(fn (Collection $records) => static::getModel()::query()->whereKey($records->modelKeys())->update(['is_enabled' => false]))
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Extract SVG markup from form data: an uploaded file wins over pasted markup.
     *
     * @param  array<string, mixed>  $data
     */
    public static function svgFromFormData(array $data): string
    {
        $files = $data['svg_file'] ?? null;
        $files = is_array($files) ? $files : [$files];

        foreach ($files as $file) {
            if ($file instanceof TemporaryUploadedFile) {
                return (string) $file->get();
            }
        }

        return is_string($data['svg'] ?? null) ? $data['svg'] : '';
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListManagedIcons::route('/'),
            'create' => Pages\CreateManagedIcon::route('/create'),
            'edit' => Pages\EditManagedIcon::route('/{record}/edit'),
        ];
    }
}
