<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Modules\Identity\Contracts\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Cuentas de acceso al panel (equipo interno, fabricantes y artistas). Solo superadministradores.
 */
class UserResource extends Resource
{
    protected static ?string $slug = 'usuarios';

    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|UnitEnum|null $navigationGroup = 'Herramientas';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'usuario';

    protected static ?string $pluralModelLabel = 'usuarios';

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * @return array<string, string>
     */
    public static function roleOptions(): array
    {
        $options = [];
        foreach (Role::panelRoles() as $role) {
            $options[$role->value] = $role->label();
        }

        return $options;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Cuenta')
                ->columns(2)
                ->schema([
                    TextInput::make('name')->label('Nombre')->required()->maxLength(255),
                    TextInput::make('email')->label('Email')->email()->required()->unique(ignoreRecord: true),
                    Select::make('role')
                        ->label('Rol')
                        ->options(self::roleOptions())
                        ->required()
                        ->helperText('Gestor de catálogo: artículos de todas las tiendas. Fabricante: stock y producción. Artista: solo consulta sus ventas.')
                        ->disabled(fn (?User $record): bool => $record !== null && $record->is(auth()->user()))
                        ->dehydrated(fn (?User $record): bool => ! ($record !== null && $record->is(auth()->user()))),
                    TextInput::make('password')
                        ->label('Contraseña')
                        ->password()
                        ->revealable()
                        ->minLength(12)
                        ->required(fn (string $operation): bool => $operation === 'create')
                        ->dehydrated(fn (?string $state): bool => filled($state))
                        ->helperText(fn (string $operation): string => $operation === 'edit' ? 'Vacía = no se cambia.' : 'Mínimo 12 caracteres.'),
                ]),
        ]);
    }

    /**
     * Usuarios = el equipo del panel. Los clientes están en Comercio → Clientes.
     *
     * @return Builder<User>
     */
    public static function getEloquentQuery(): Builder
    {
        return User::query()->withoutRole(Role::Customer->value);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('roles'))
            ->columns([
                TextColumn::make('name')->label('Nombre')->searchable()->sortable(),
                TextColumn::make('email')->label('Email')->searchable(),
                TextColumn::make('roles.name')
                    ->label('Rol')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Role::tryFrom($state)?->label() ?? $state),
                IconColumn::make('two_factor')->label('Dos pasos')->boolean()
                    ->state(fn (User $record): bool => $record->hasTwoFactor())
                    ->tooltip(fn (User $record): string => $record->hasTwoFactor() ? 'Verificación en dos pasos activada' : ($record->mustUseTwoFactor() ? 'La configurará al entrar (obligatoria)' : 'Sin activar (opcional)')),
                TextColumn::make('created_at')->label('Alta')->date('d/m/Y')->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label('Rol')
                    ->options(self::roleOptions())
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null)
                        ? $query->whereHas('roles', fn (Builder $roles) => $roles->where('name', $data['value']))
                        : $query),
            ])
            ->recordActions([
                EditAction::make(),
                // Por si alguien pierde el móvil y sus códigos de recuperación (ADR 0028).
                Action::make('resetTwoFactor')
                    ->label('Quitar dos pasos')
                    ->icon(Heroicon::OutlinedShieldExclamation)
                    ->color('danger')
                    ->visible(fn (User $record): bool => $record->hasTwoFactor())
                    ->requiresConfirmation()
                    ->modalHeading('Quitar la verificación en dos pasos')
                    ->modalDescription(fn (User $record): string => 'Úsalo solo si '.$record->name.' ha perdido el móvil y los códigos de recuperación. Al volver a entrar tendrá que configurarla de nuevo. Queda en el registro de seguridad.')
                    ->action(function (User $record): void {
                        $record->saveAppAuthenticationSecret(null);
                        $record->saveAppAuthenticationRecoveryCodes(null);
                        Notification::make()->title('Verificación en dos pasos quitada')->success()->send();
                    }),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
