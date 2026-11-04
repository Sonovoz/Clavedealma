<?php

namespace App\Platform\Security;

use App\Support\Contracts\HasLabel;

enum SecurityEventType: string implements HasLabel
{
    case Login = 'login';
    case LoginFailed = 'login_failed';
    case Lockout = 'lockout';
    case Logout = 'logout';
    case PasswordReset = 'password_reset';
    case UserCreated = 'user_created';
    case UserUpdated = 'user_updated';
    case UserDeleted = 'user_deleted';
    case RoleChanged = 'role_changed';
    case PasswordChanged = 'password_changed';
    case OrderCancelled = 'order_cancelled';
    case MoneyRefunded = 'money_refunded';
    case FiscalDataChanged = 'fiscal_data_changed';
    case InvoicesIssued = 'invoices_issued';
    case BackupCreated = 'backup_created';
    case BackupDownloaded = 'backup_downloaded';
    case BackupDeleted = 'backup_deleted';
    case BackupScheduleChanged = 'backup_schedule_changed';
    case UpdateApplied = 'update_applied';
    case Maintenance = 'maintenance';

    public function label(): string
    {
        return match ($this) {
            self::Login => 'Acceso al panel',
            self::LoginFailed => 'Acceso fallido',
            self::Lockout => 'Bloqueo por intentos',
            self::Logout => 'Salida del panel',
            self::PasswordReset => 'Contraseña restablecida',
            self::UserCreated => 'Usuario creado',
            self::UserUpdated => 'Usuario modificado',
            self::UserDeleted => 'Usuario borrado',
            self::RoleChanged => 'Rol cambiado',
            self::PasswordChanged => 'Contraseña cambiada',
            self::OrderCancelled => 'Pedido cancelado',
            self::MoneyRefunded => 'Dinero devuelto',
            self::FiscalDataChanged => 'Datos fiscales cambiados',
            self::InvoicesIssued => 'Facturas pendientes emitidas',
            self::BackupCreated => 'Copia de seguridad hecha',
            self::BackupDownloaded => 'Copia de seguridad descargada',
            self::BackupDeleted => 'Copia de seguridad borrada',
            self::BackupScheduleChanged => 'Programación de copias cambiada',
            self::UpdateApplied => 'Actualización aplicada',
            self::Maintenance => 'Modo mantenimiento',
        };
    }

    /** Lo que conviene mirar con atención. */
    public function level(): string
    {
        return match ($this) {
            self::LoginFailed, self::RoleChanged, self::PasswordChanged, self::PasswordReset, self::FiscalDataChanged, self::BackupDownloaded, self::UserDeleted => 'warning',
            self::Lockout, self::BackupDeleted => 'danger',
            default => 'info',
        };
    }
}
