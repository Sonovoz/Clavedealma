<?php

namespace App\Filament\Resources\Coupons\Pages;

use App\Filament\Resources\Coupons\CouponResource;
use App\Modules\Promotions\Infrastructure\Models\Coupon;
use App\Platform\Security\SecurityEventType;
use App\Platform\Security\SecurityLog;
use Filament\Resources\Pages\CreateRecord;

class CreateCoupon extends CreateRecord
{
    protected static string $resource = CouponResource::class;

    protected static ?string $title = 'Crear cupón';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return CouponResource::toStorage($data);
    }

    protected function afterCreate(): void
    {
        $coupon = $this->getRecord();
        if ($coupon instanceof Coupon) {
            SecurityLog::record(SecurityEventType::CouponChanged, "Cupón {$coupon->code} creado: {$coupon->label()}.", subject: $coupon->code);
        }
    }
}
