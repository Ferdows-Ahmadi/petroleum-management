<?php

namespace Tests\Concerns;

use App\Enums\PartnerRole;
use App\Enums\UserRole;
use App\Models\BusinessPartner;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\SaleItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WeighingRecord;
use App\Services\TradeConfirmationService;
use App\Services\TradeDraftService;
use App\Services\WeighingService;

trait CreatesTradeFixtures
{
    protected function tradeUser(UserRole $role = UserRole::PurchaseSalesOfficer): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => true]);
    }

    /** @param array<int, PartnerRole> $roles */
    protected function tradePartner(array $roles): BusinessPartner
    {
        $partner = BusinessPartner::factory()->create(['name' => 'شرکت آزمایشی '.fake()->unique()->numerify('####'), 'is_active' => true]);
        $partner->roles()->createMany(array_map(fn (PartnerRole $role): array => ['role' => $role], $roles));

        return $partner;
    }

    protected function tradeProduct(string $code = 'TST'): Product
    {
        return Product::factory()->create(['name' => 'محصول آزمایشی '.$code, 'code' => $code, 'unit' => 'kg', 'is_active' => true]);
    }

    protected function tradeWarehouse(string $name = 'ذخیره آزمایشی'): Warehouse
    {
        return Warehouse::factory()->create(['name' => $name.' '.fake()->unique()->numerify('###'), 'is_active' => true]);
    }

    /** @return array<string, mixed> */
    protected function purchasePayload(BusinessPartner $supplier, Product $product, Warehouse $warehouse, string $quantity = '1000', string $rate = '1200'): array
    {
        return [
            'business_partner_id' => $supplier->id,
            'purchase_date' => '2026-08-08',
            'reference_number' => 'TEST-PURCHASE',
            'currency' => 'USD',
            'notes' => 'معلومات آزمایشی',
            'items' => [[
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'quantity_kg' => $quantity,
                'rate_per_ton' => $rate,
            ]],
        ];
    }

    /** @return array<string, mixed> */
    protected function salePayload(BusinessPartner $customer, Product $product, Warehouse $warehouse, string $quantity = '500', string $rate = '1310'): array
    {
        return [
            'business_partner_id' => $customer->id,
            'sale_date' => '2026-08-08',
            'reference_number' => 'TEST-SALE',
            'currency' => 'USD',
            'notes' => 'معلومات آزمایشی',
            'items' => [[
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'requested_quantity_kg' => $quantity,
                'rate_per_ton' => $rate,
            ]],
        ];
    }

    protected function finalizeSaleItemWeighing(
        SaleItem $item,
        User $user,
        ?string $firstWeight = null,
        string $secondWeight = '1000',
    ): WeighingRecord {
        $firstWeight ??= bcadd($item->requested_quantity_kg ?? '500', $secondWeight, 3);
        $service = app(WeighingService::class);
        $record = $service->create([
            'sale_item_id' => $item->id,
            'ticket_number' => 'TEST-WG-'.$item->id,
            'station_name' => 'پل باسکول آزمایشی',
            'weighing_at' => '2026-08-08 10:00:00',
            'first_weight_kg' => $firstWeight,
            'second_weight_kg' => $secondWeight,
            'vehicle_number' => 'TEST-VEHICLE',
            'driver_name' => 'راننده آزمایشی',
            'notes' => 'اطلاعات ساختگی آزمون',
        ], $user->id);
        $service->finalize($record, $user->id);

        return $record->refresh();
    }

    protected function addStock(User $user, BusinessPartner $supplier, Product $product, Warehouse $warehouse, string $quantity): Purchase
    {
        $purchase = app(TradeDraftService::class)->createPurchase(
            $this->purchasePayload($supplier, $product, $warehouse, $quantity),
            $user->id,
        );
        app(TradeConfirmationService::class)->confirmPurchase($purchase, $user->id);

        return $purchase->refresh();
    }
}
