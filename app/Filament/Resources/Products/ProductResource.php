<?php

namespace App\Filament\Resources\Products;

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\RelationManagers\VariantsRelationManager;
use App\Filament\Support\ActiveStore;
use App\Filament\Support\Fields;
use App\Filament\Support\ScopedToActiveStore;
use App\Modules\Catalog\Application\PublicationCheck;
use App\Modules\Catalog\Contracts\ProductStatus;
use App\Modules\Catalog\Domain\SizeScale;
use App\Modules\Catalog\Infrastructure\Models\BaseGarment;
use App\Modules\Catalog\Infrastructure\Models\Product;
use App\Modules\Inventory\Contracts\StockMode;
use App\Modules\Stores\Infrastructure\Models\Store;
use App\Platform\Ai\AiException;
use App\Storefront\Seo\SeoAssistant;
use App\Storefront\Seo\SeoAudit;
use App\Storefront\Seo\SeoBuilder;
use App\Storefront\Seo\SeoText;
use App\Storefront\Urls;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;
use Livewire\Component;
use UnitEnum;

class ProductResource extends Resource
{
    use ScopedToActiveStore;

    protected static ?string $slug = 'productos';

    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static string|UnitEnum|null $navigationGroup = 'Productos';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Todos los productos';

    protected static ?string $modelLabel = 'producto';

    protected static ?string $pluralModelLabel = 'productos';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('Ficha')
                ->columnSpanFull()
                ->persistTabInQueryString()
                ->tabs([
                    Tab::make('General')->icon(Heroicon::OutlinedDocumentText)->columns(2)->schema(self::generalTab()),
                    Tab::make('Tallas y colores')->icon(Heroicon::OutlinedSwatch)->schema(self::variantsTab()),
                    Tab::make('Fotos')->icon(Heroicon::OutlinedPhoto)->schema(self::photosTab()),
                    Tab::make('Precio y fabricación')->icon(Heroicon::OutlinedCurrencyEuro)->columns(2)->schema(self::priceTab()),
                    Tab::make('SEO')->icon(Heroicon::OutlinedMagnifyingGlass)->schema(self::seoTab()),
                    Tab::make('Publicación')->icon(Heroicon::OutlinedGlobeAlt)->schema(self::publicationTab()),
                ]),
        ]);
    }

    /**
     * @return array<\Filament\Schemas\Components\Component|Field>
     */
    private static function generalTab(): array
    {
        return [
            TextEntry::make('store_label')
                ->label('Tienda')
                ->state(fn (?Product $record): string => (string) Store::query()->whereKey($record->store_id ?? ActiveStore::id())->value('name'))
                ->helperText('Se cambia arriba, en «Tienda». Todo lo que crees aquí va a esta tienda.'),
            Select::make('collections')
                ->label('Categorías')
                ->relationship('collections', 'name', fn (Builder $query) => $query->where('store_id', ActiveStore::id()))
                ->multiple()
                ->preload(),
            TextInput::make('reference')
                ->label('Modelo (referencia)')
                ->helperText('Código del artículo, p. ej. FQ-OS-01. Da nombre a las fotos en la hoja de importación.')
                ->maxLength(40)
                ->regex('/^[A-Za-z0-9][A-Za-z0-9-]*$/')
                ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('store_id', ActiveStore::id())),
            TextInput::make('name')->label('Nombre')->required()->maxLength(200),
            Select::make('base_garment_id')
                ->label('Prenda base')
                ->relationship('baseGarment', 'name', fn (Builder $query) => $query->where('active', true)->where('store_id', ActiveStore::id()))
                ->default(fn (): ?int => request()->integer('prenda') ?: null)
                ->searchable()
                ->preload()
                ->live()
                ->helperText(function (?int $state): string {
                    $garment = $state === null ? null : BaseGarment::query()->withCount('sizes')->find($state);
                    if ($garment === null) {
                        return 'De ella salen la composición, los cuidados y la guía de tallas.';
                    }

                    return ($garment->composition ?: '⚠ Sin composición: complétala en Prendas base antes de publicar.')
                        .' · Guía de tallas: '.($garment->sizes_count > 0 ? $garment->sizes_count.' tallas' : 'sin medidas');
                }),
            TextInput::make('slug')
                ->label('Dirección (slug)')
                ->helperText('Se genera del nombre si se deja vacío.')
                ->maxLength(255),
            RichEditor::make('description')->label('Descripción')->columnSpanFull(),
            TextInput::make('model_info')
                ->label('Información del modelo (opcional)')
                ->placeholder('El modelo mide 1,80 m y lleva una M.')
                ->maxLength(255)
                ->columnSpanFull(),
        ];
    }

    /**
     * @return array<\Filament\Schemas\Components\Component|Field>
     */
    private static function variantsTab(): array
    {
        return [
            Section::make(fn (string $operation): string => $operation === 'create' ? 'Tallas y colores del artículo' : 'Añadir tallas o colores')
                ->description('Elige colores y tallas: al guardar se crean todas las combinaciones. Las que ya existen no se duplican. Cada variante se puede retocar después en la tabla «Variantes».')
                ->statePath('generator')
                ->columns(2)
                ->schema([
                    Repeater::make('colors')
                        ->label('Colores')
                        ->defaultItems(0)
                        ->addActionLabel('Añadir color')
                        ->table([TableColumn::make('Color'), TableColumn::make('Muestra')])
                        ->schema([
                            TextInput::make('name')->required()->maxLength(40)->placeholder('Negro'),
                            ColorPicker::make('hex'),
                        ])
                        ->helperText('Sin colores = el artículo no tiene variantes de color.')
                        ->columnSpanFull(),
                    CheckboxList::make('sizes')
                        ->label('Tallas')
                        ->options(array_combine(SizeScale::STANDARD, SizeScale::STANDARD))
                        ->columns(7)
                        ->bulkToggleable()
                        ->disabled(fn (Get $get): bool => (bool) $get('one_size'))
                        ->columnSpanFull(),
                    Toggle::make('one_size')->label('Talla única (gorras, bolsas…)')->live()->columnSpanFull(),
                    Select::make('stock_mode')
                        ->label('Venta de las variantes nuevas')
                        ->options(Fields::options(StockMode::class))
                        ->default(StockMode::OnDemand->value)
                        ->selectablePlaceholder(false)
                        ->live(),
                    TextInput::make('units')
                        ->label('Unidades de cada variante')
                        ->numeric()
                        ->minValue(0)
                        ->visible(fn (Get $get): bool => ($get('stock_mode') ?? StockMode::OnDemand->value) !== StockMode::OnDemand->value)
                        ->required(fn (Get $get): bool => ($get('stock_mode') ?? StockMode::OnDemand->value) !== StockMode::OnDemand->value),
                    TextInput::make('threshold')
                        ->label('Avisar al fabricante al quedar')
                        ->numeric()
                        ->minValue(0)
                        ->suffix('unidades')
                        ->visible(fn (Get $get): bool => ($get('stock_mode') ?? StockMode::OnDemand->value) !== StockMode::OnDemand->value),
                ]),
        ];
    }

    /**
     * @return array<\Filament\Schemas\Components\Component|Field>
     */
    private static function photosTab(): array
    {
        return [
            Repeater::make('images')
                ->hiddenLabel()
                ->relationship()
                ->orderColumn('position')
                ->reorderableWithDragAndDrop()
                ->grid(3)
                ->defaultItems(0)
                ->addActionLabel('Añadir foto')
                ->itemLabel(fn (array $state): string => ($state['is_swatch'] ?? false) ? 'Muestra de tejido · '.($state['color'] ?? '') : ($state['color'] ?? 'Todas las variantes'))
                ->schema([
                    FileUpload::make('path')->label('Foto')->image()->disk('public')->directory('products')->maxSize(8 * 1024)->required(),
                    Select::make('color')
                        ->label('Color')
                        ->placeholder('Todos los colores')
                        ->options(fn (Get $get, Component $livewire): array => self::colorOptions($get, $livewire instanceof EditRecord && $livewire->getRecord() instanceof Product ? $livewire->getRecord() : null)),
                    Toggle::make('is_swatch')
                        ->label('Es la muestra de tejido del color')
                        ->helperText('Se ve como botón para elegir el color.')
                        ->rule(fn (Get $get) => fn (string $attribute, mixed $value, \Closure $fail) => $value && blank($get('color')) ? $fail('Elige el color de la muestra.') : null),
                    TextInput::make('alt')->label('Texto alternativo')->helperText('Describe la foto para quien no la ve.'),
                ])
                ->helperText('Arrastra para ordenar. La primera es la portada. Asigna cada foto a su color para que cambie al elegirlo. Máximo 8 MB por foto.'),
        ];
    }

    /**
     * @return array<\Filament\Schemas\Components\Component|Field>
     */
    private static function priceTab(): array
    {
        return [
            Fields::money('price_cents')->label('Precio con IVA')->required()->helperText('Las variantes pueden tener su propio precio (p. ej. 3XL) en la tabla «Variantes».'),
            Fields::percent('vat_rate_bps')->label('IVA')->default(2100)->required(),
            TextEntry::make('discount')
                ->label('Rebaja')
                ->state(fn (?Product $record): string => self::discountLabel($record))
                ->helperText('El precio tachado no se escribe: es el más bajo de los últimos 30 días (Ley de Comercio Minorista). Basta con bajar el precio.')
                ->columnSpanFull(),
            Fields::percent('commission_bps')
                ->label('Comisión del artista propia')
                ->helperText('Vacío = comisión de la tienda.'),
            Toggle::make('personalized')
                ->label('Personalizado para cada cliente')
                ->helperText('Sin derecho de desistimiento (por ejemplo, con el nombre del cliente).'),
            TextInput::make('lead_time_min_days')->label('Plazo de fabricación mínimo')->numeric()->minValue(0)->maxValue(60)->suffix('días')->default(5)->required(),
            TextInput::make('lead_time_max_days')->label('Plazo de fabricación máximo')->numeric()->minValue(0)->maxValue(60)->suffix('días')->default(7)->required()->gte('lead_time_min_days'),
            FileUpload::make('design_path')
                ->label('Archivo del diseño para producción')
                ->disk('local')
                ->directory('designs')
                ->visibility('private')
                ->downloadable()
                ->columnSpanFull(),
        ];
    }

    /**
     * @return array<\Filament\Schemas\Components\Component|Field>
     */
    private static function publicationTab(): array
    {
        return [
            Select::make('status')
                ->label('Estado')
                ->options(Fields::options(ProductStatus::class))
                ->default(ProductStatus::Draft->value)
                ->required()
                ->helperText('Para publicar hacen falta precio, al menos una variante, una foto y una prenda base con composición. Si falta algo, se guarda como borrador y se avisa.'),
            Callout::make('Falta para publicar')
                ->warning()
                ->description(fn (?Product $record): string => implode(' ', $record === null ? [] : app(PublicationCheck::class)->problems($record)))
                ->visible(fn (?Product $record): bool => $record !== null && app(PublicationCheck::class)->problems($record) !== []),
        ];
    }

    /**
     * Pestaña SEO: cómo se verá en Google, campos propios, semáforo y asistente de IA.
     *
     * @return array<\Filament\Schemas\Components\Component|Field>
     */
    private static function seoTab(): array
    {
        $count = fn (?string $state, int $min, int $max): string => mb_strlen((string) $state).' / '.$max.' caracteres'
            .(filled($state) && (mb_strlen((string) $state) < $min || mb_strlen((string) $state) > $max) ? ' · ideal entre '.$min.' y '.$max : '');
        $ai = fn (): SeoAssistant => app(SeoAssistant::class);
        $aiHint = fn (): ?string => $ai()->available() ? null : 'La IA no está configurada (AI_DRIVER, AI_API_KEY y AI_MODEL en el .env).';

        return [
            Callout::make(fn (?Product $record): string => $record === null ? 'Semáforo SEO' : 'Semáforo SEO: '.app(SeoAudit::class)->product($record)['score'].' / 100')
                ->description(fn (?Product $record): string => $record === null
                    ? 'Guarda el artículo para ver su nota SEO.'
                    : (collect(app(SeoAudit::class)->product($record)['checks'])->where('level', '!=', 'ok')->pluck('message')->implode(' · ') ?: 'Todo en orden.'))
                ->color(fn (?Product $record): string => $record === null ? 'gray' : SeoAudit::color(app(SeoAudit::class)->product($record)['score']))
                ->icon(Heroicon::OutlinedChartBar),

            Section::make('Cómo se verá en Google')
                ->description('Si dejas los campos vacíos, se generan solos con el nombre y la descripción. Escritos a mano posicionan mejor.')
                ->schema([
                    TextEntry::make('google_preview')
                        ->hiddenLabel()
                        ->html()
                        ->state(function (Get $get, ?Product $record): string {
                            $store = $record?->store_id === null ? null : Store::query()->find($record->store_id);
                            $title = filled($get('seo_title')) ? (string) $get('seo_title') : ($record && $store ? SeoBuilder::productTitle($record, $store, $record->baseGarment?->name) : (string) $get('name'));
                            $desc = filled($get('seo_description')) ? (string) $get('seo_description') : ($record && $store ? SeoBuilder::productDescription($record, $store, $record->baseGarment?->name) : '');
                            $url = $record && $store ? Urls::product($store, $record->slug) : 'clavedealma.es/…';

                            return '<div style="font-family:arial,sans-serif;max-width:600px">'
                                .'<div style="font-size:12px;color:#4d5156">'.e($url).'</div>'
                                .'<div style="font-size:20px;line-height:1.3;color:#1a0dab">'.e(SeoText::limit($title, 62)).'</div>'
                                .'<div style="font-size:14px;line-height:1.58;color:#4d5156">'.e(SeoText::limit($desc, 160)).'</div></div>';
                        }),
                    TextInput::make('seo_title')
                        ->label('Título para Google')
                        ->maxLength(70)
                        ->placeholder('Camiseta «La esencia» · Merchandising Farruquito')
                        ->live(debounce: 600)
                        ->helperText(fn (?string $state): string => $count($state, SeoText::TITLE_MIN, SeoText::TITLE_MAX)),
                    Textarea::make('seo_description')
                        ->label('Descripción para Google')
                        ->maxLength(170)
                        ->rows(3)
                        ->live(debounce: 600)
                        ->helperText(fn (?string $state): string => $count($state, SeoText::DESCRIPTION_MIN, SeoText::DESCRIPTION_MAX)),
                ]),

            Section::make('Asistente de IA')
                ->description('La IA propone; tú revisas y pulsas «Guardar cambios». Solo usa los datos del artículo y no inventa nada.')
                ->visible(fn (?Product $record): bool => $record !== null)
                ->schema([
                    Actions::make([
                        Action::make('aiSeo')
                            ->label('Sugerir título y descripción')
                            ->icon(Heroicon::OutlinedSparkles)
                            ->disabled(fn (): bool => ! $ai()->available())
                            ->tooltip($aiHint)
                            ->action(function (Set $set, Product $record) use ($ai): void {
                                $s = self::askAi(fn () => $ai()->productSeo($record));
                                if ($s !== null) {
                                    $set('seo_title', $s['seo_title']);
                                    $set('seo_description', $s['seo_description']);
                                    Notification::make()->title('Propuesta lista: revísala y guarda')->success()->send();
                                }
                            }),
                        Action::make('aiDescription')
                            ->label('Mejorar la descripción del artículo')
                            ->icon(Heroicon::OutlinedPencilSquare)
                            ->disabled(fn (): bool => ! $ai()->available())
                            ->tooltip($aiHint)
                            ->requiresConfirmation()
                            ->modalDescription('Sustituye el texto de «Descripción» (pestaña General) por una propuesta de la IA. No se guarda hasta que pulses «Guardar cambios».')
                            ->action(function (Set $set, Product $record) use ($ai): void {
                                $d = self::askAi(fn () => $ai()->productDescription($record));
                                if ($d !== null) {
                                    $set('description', $d);
                                    Notification::make()->title('Descripción propuesta en la pestaña General: revísala y guarda')->success()->send();
                                }
                            }),
                        Action::make('aiAlts')
                            ->label('Sugerir textos de las fotos')
                            ->icon(Heroicon::OutlinedPhoto)
                            ->disabled(fn (): bool => ! $ai()->available())
                            ->tooltip($aiHint)
                            ->action(function (Get $get, Set $set, Product $record) use ($ai): void {
                                $images = (array) $get('images');
                                $photos = array_values(array_map(fn ($i): array => ['color' => $i['color'] ?? null, 'swatch' => (bool) ($i['is_swatch'] ?? false)], $images));
                                $alts = self::askAi(fn () => $ai()->altTexts($record, $photos));
                                if ($alts !== null && $alts !== []) {
                                    $k = 0;
                                    foreach ($images as $key => $image) {
                                        $images[$key]['alt'] = $alts[$k++] ?? ($image['alt'] ?? null);
                                    }
                                    $set('images', $images);
                                    Notification::make()->title('Textos propuestos en la pestaña Fotos: comprueba que cada uno coincide con su foto')->success()->send();
                                }
                            }),
                    ])->key('seo-ai'),
                ]),
        ];
    }

    /**
     * @template T
     *
     * @param  \Closure(): T  $call
     * @return T|null
     */
    private static function askAi(\Closure $call): mixed
    {
        try {
            return $call();
        } catch (AiException $e) {
            Notification::make()->title('La IA no ha podido responder')->body($e->getMessage())->danger()->send();

            return null;
        }
    }

    /**
     * Colores para asignar fotos: los del generador y los de las variantes ya creadas.
     *
     * @return array<string, string>
     */
    private static function colorOptions(Get $get, ?Product $record): array
    {
        $colors = [];
        foreach ((array) $get('../../generator.colors') as $color) {
            if (is_array($color) && filled($color['name'] ?? null)) {
                $colors[] = trim((string) $color['name']);
            }
        }
        if ($record !== null) {
            $colors = array_merge($record->variants()->whereNotNull('color')->pluck('color')->all(), $colors);
        }
        $colors = array_values(array_unique(array_map(fn (string $c): string => Str::ucfirst($c), $colors)));

        return array_combine($colors, $colors) ?: [];
    }

    private static function discountLabel(?Product $record): string
    {
        $variant = $record?->variants()->where('active', true)->first();
        $discount = $variant?->discount();
        if ($variant === null || $discount === null) {
            return 'Sin rebaja en curso.';
        }

        return sprintf('Antes %s, ahora %s (−%d %%).', Money::fromCents($discount['cents'])->format(), $variant->price()->format(), $discount['percent']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withCount('variants'))
            ->columns([
                TextColumn::make('reference')->label('Modelo')->searchable()->sortable()->placeholder('—'),
                TextColumn::make('name')->label('Producto')->searchable()->sortable(),
                Fields::moneyColumn('price_cents')->label('Precio'),
                TextColumn::make('variants_count')->label('Variantes')->alignEnd(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (ProductStatus $state): string => $state->label())
                    ->color(fn (ProductStatus $state): string => $state->color()),
                TextColumn::make('seo_score')
                    ->label('SEO')
                    ->badge()
                    ->state(fn (Product $record): int => app(SeoAudit::class)->product($record)['score'])
                    ->color(fn (int $state): string => SeoAudit::color($state))
                    ->suffix(' / 100')
                    ->toggleable(),
                TextColumn::make('updated_at')->label('Actualizado')->since()->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Estado')->options(Fields::options(ProductStatus::class)),
                TrashedFilter::make(),
            ])
            ->recordActions([EditAction::make()])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [VariantsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
