<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\User;

class ReportNavigation
{
    /** @return array<string, string> Named report routes and their tab labels, in display order. */
    public static function tabsFor(User $user): array
    {
        $finance = $user->hasAnyRole(UserRole::GeneralManager, UserRole::SystemAdmin, UserRole::Accountant);
        $office = $finance || $user->hasAnyRole(UserRole::PurchaseSalesOfficer);
        $trade = $office || $user->hasAnyRole(UserRole::WarehouseOfficer);

        $tabs = $trade ? ['reports.purchases' => 'خرید', 'reports.sales' => 'فروش'] : [];
        $tabs['reports.weighings'] = 'وزن‌کشی';
        if ($office) {
            $tabs['reports.settlements'] = 'تصفیه';
            $tabs['reports.accounts'] = 'حساب‌ها';
        }
        if ($trade) {
            $tabs['reports.inventory'] = 'ذخیره';
        }
        if ($finance) {
            $tabs['reports.expenses'] = 'مصارف';
            $tabs['reports.profit-loss'] = 'نتیجه مالی';
            $tabs['reports.capital'] = 'سرمایه';
        }

        return $tabs;
    }

    public static function defaultRoute(User $user): string
    {
        return array_key_first(self::tabsFor($user));
    }
}
