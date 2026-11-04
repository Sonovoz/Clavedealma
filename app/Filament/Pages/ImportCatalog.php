<?php

namespace App\Filament\Pages;

use App\Modules\Identity\Infrastructure\Models\User;
use App\Platform\CatalogImport\CatalogImporter;
use App\Platform\CatalogImport\ImportReport;
use App\Platform\CatalogImport\PhotoSet;
use App\Platform\CatalogImport\TemplateWriter;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use UnitEnum;

/**
 * "Importar artículos": alta de productos con la hoja del protocolo y el ZIP de fotos
 * (docs/protocolo/README.md). Primero se comprueba; si no hay errores, se importa.
 * Para superadministradores y gestores de catálogo.
 *
 * @property-read Schema $form
 */
class ImportCatalog extends Page
{
    private const DISK = 'local';

    private const FOLDER = 'importaciones';

    protected string $view = 'filament.pages.import-catalog';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static string|UnitEnum|null $navigationGroup = 'Catálogo';

    protected static ?string $navigationLabel = 'Importar artículos';

    protected static ?string $title = 'Importar artículos';

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'importar-articulos';

    /** @var array<string, mixed> */
    public ?array $data = [];

    /** @var array{errors: list<array{fila: ?int, campo: string, mensaje: string}>, warnings: list<array{fila: ?int, campo: string, mensaje: string}>, summary: array<string, int>, applied: bool}|null */
    public ?array $result = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->managesCatalog();
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                FileUpload::make('sheet')
                    ->label('Hoja de artículos (.xlsx o .csv)')
                    ->helperText('Una fila por variante. Descarga la plantilla para ver las columnas.')
                    ->disk(self::DISK)
                    ->directory(self::FOLDER)
                    ->visibility('private')
                    ->preserveFilenames()
                    ->acceptedFileTypes([
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel',
                    ])
                    ->maxSize(10 * 1024)
                    ->required(),
                FileUpload::make('photos')
                    ->label('Fotos (.zip, opcional)')
                    ->helperText('Nombres MODELO_NN.jpg o MODELO_COLOR_NN.jpg. Máximo 8 MB por foto.')
                    ->disk(self::DISK)
                    ->directory(self::FOLDER)
                    ->visibility('private')
                    ->preserveFilenames()
                    ->acceptedFileTypes(['application/zip', 'application/x-zip-compressed', 'multipart/x-zip'])
                    ->maxSize((int) config('clavedealma.import_max_zip_kb')),
                Actions::make([
                    Action::make('check')
                        ->label('Comprobar')
                        ->icon(Heroicon::OutlinedMagnifyingGlass)
                        ->action(fn () => $this->run(apply: false)),
                    Action::make('import')
                        ->label('Importar')
                        ->icon(Heroicon::OutlinedArrowUpTray)
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalDescription('Crea y actualiza los artículos de la hoja. Si hay algún error, no se cambia nada.')
                        ->action(fn () => $this->run(apply: true)),
                ])->key('form-actions'),
            ]);
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('template')
                ->label('Descargar plantilla')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->action(function (TemplateWriter $writer): BinaryFileResponse {
                    $path = storage_path('app/private/plantilla-articulos-'.Str::random(8).'.xlsx');
                    File::ensureDirectoryExists(dirname($path));
                    $writer->write($path);

                    return response()->download($path, 'plantilla-articulos.xlsx')->deleteFileAfterSend();
                }),
        ];
    }

    public function run(bool $apply): void
    {
        $state = $this->form->getState();
        $disk = Storage::disk(self::DISK);
        $sheet = (string) $state['sheet'];
        $photos = null;

        if (filled($state['photos'] ?? null)) {
            try {
                $photos = PhotoSet::fromZip($disk->path((string) $state['photos']), storage_path('app/private/'.self::FOLDER.'/fotos-'.Str::random(12)));
            } catch (\InvalidArgumentException $e) {
                Notification::make()->title($e->getMessage())->danger()->send();

                return;
            }
        }

        try {
            $report = app(CatalogImporter::class)->import($disk->path($sheet), $photos, $apply, auth()->id(), pathinfo($sheet, PATHINFO_EXTENSION));
        } finally {
            $photos?->cleanup();
        }

        $this->result = $this->toArray($report);
        $this->notify($report);

        if ($report->applied) {
            $disk->delete(array_filter([$sheet, $state['photos'] ?? null]));
            $this->form->fill();
        }
        $this->cleanOldUploads();
    }

    /**
     * @return array{errors: list<array{fila: ?int, campo: string, mensaje: string}>, warnings: list<array{fila: ?int, campo: string, mensaje: string}>, summary: array<string, int>, applied: bool}
     */
    private function toArray(ImportReport $report): array
    {
        return [
            'errors' => $report->errors,
            'warnings' => $report->warnings,
            'summary' => $report->labelledSummary(),
            'applied' => $report->applied,
        ];
    }

    private function notify(ImportReport $report): void
    {
        $notification = Notification::make();
        match (true) {
            $report->hasErrors() => $notification->title(count($report->errors).' errores: no se ha cambiado nada')->danger(),
            $report->applied => $notification->title('Importación aplicada')->success(),
            default => $notification->title('Comprobación correcta: ya puedes importar')->success(),
        };
        $notification->send();
    }

    /** Las subidas de comprobaciones abandonadas se borran al día siguiente. */
    private function cleanOldUploads(): void
    {
        $disk = Storage::disk(self::DISK);
        foreach ($disk->files(self::FOLDER) as $file) {
            if ($disk->lastModified($file) < now()->subDay()->getTimestamp()) {
                $disk->delete($file);
            }
        }
    }
}
