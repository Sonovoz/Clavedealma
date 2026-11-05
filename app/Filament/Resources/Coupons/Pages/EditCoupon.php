<?php

namespace App\Filament\Resources\Coupons\Pages;

use App\Filament\Resources\Coupons\CouponResource;
use App\Modules\Promotions\Infrastructure\Models\Coupon;
use App\Platform\Security\SecurityEventType;
use App\Platform\Security\SecurityLog;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCoupon extends EditRecord
{
    protected static string $resource = CouponResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()->visible(fn (Coupon $record): bool => ! $record->redemptions()->exists())];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return CouponResource::toForm($data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return CouponResource::toStorage($data);
    }

    protected function afterSave(): void
    {
        $coupon = $this->getRecord();
        if ($coupon instanceof Coupon) {
            SecurityLog::record(SecurityEventType::CouponChanged, "Cupón {$coupon->code} cambiado: {$coupon->label()}".($coupon->active ? '' : ' (desactivado)').'.', subject: $coupon->code);
        }
    }
}
