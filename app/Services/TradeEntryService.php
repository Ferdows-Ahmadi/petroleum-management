<?php

namespace App\Services;

use App\Enums\TransactionStatus;
use App\Models\Purchase;
use App\Models\Sale;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TradeEntryService
{
    public function __construct(private readonly PetroleumAmountCalculator $amountCalculator) {}

    public function createPurchase(array $data, int $userId): Purchase
    {
        return DB::transaction(function () use ($data, $userId): Purchase {
            $purchase = Purchase::create([
                ...Arr::except($data, ['items', 'ticket_image']),
                'status' => TransactionStatus::PendingApproval,
                'created_by' => $userId,
            ]);
            $this->replacePurchaseItems($purchase, $data['items']);

            return $purchase->load('items');
        });
    }

    public function updatePurchase(Purchase $purchase, array $data): Purchase
    {
        $this->ensureDraft($purchase->status);

        return DB::transaction(function () use ($purchase, $data): Purchase {
            $locked = Purchase::query()->lockForUpdate()->findOrFail($purchase->id);
            $this->ensureDraft($locked->status);
            $locked->update(Arr::except($data, ['items', 'ticket_image']));
            $this->replacePurchaseItems($locked, $data['items']);

            return $locked->load('items');
        });
    }

    public function deletePurchase(Purchase $purchase): void
    {
        DB::transaction(function () use ($purchase): void {
            $locked = Purchase::query()->lockForUpdate()->findOrFail($purchase->id);
            $this->ensureDraft($locked->status);
            $locked->delete();
        });
    }

    public function createSale(array $data, int $userId): Sale
    {
        return DB::transaction(function () use ($data, $userId): Sale {
            $sale = Sale::create([
                ...Arr::except($data, 'items'),
                'status' => TransactionStatus::AwaitingWeighing,
                'created_by' => $userId,
            ]);
            $this->replaceSaleItems($sale, $data['items']);

            return $sale->load('items');
        });
    }

    public function updateSale(Sale $sale, array $data): Sale
    {
        $this->ensureDraft($sale->status);
        $this->ensureSaleHasNoWeighing($sale);

        return DB::transaction(function () use ($sale, $data): Sale {
            $locked = Sale::query()->lockForUpdate()->findOrFail($sale->id);
            $this->ensureDraft($locked->status);
            $this->ensureSaleHasNoWeighing($locked);
            $locked->update(Arr::except($data, 'items'));
            $this->replaceSaleItems($locked, $data['items']);

            return $locked->load('items');
        });
    }

    public function deleteSale(Sale $sale): void
    {
        DB::transaction(function () use ($sale): void {
            $locked = Sale::query()->lockForUpdate()->findOrFail($sale->id);
            $this->ensureDraft($locked->status);
            $this->ensureSaleHasNoWeighing($locked);
            $locked->delete();
        });
    }

    private function replacePurchaseItems(Purchase $purchase, array $items): void
    {
        $purchase->items()->delete();

        foreach ($items as $item) {
            $purchase->items()->create([
                ...$item,
                'amount' => $this->amountCalculator->calculate($item['quantity_kg'], $item['rate_per_ton']),
            ]);
        }
    }

    private function replaceSaleItems(Sale $sale, array $items): void
    {
        $sale->items()->delete();

        foreach ($items as $item) {
            $sale->items()->create([
                ...$item,
                'final_quantity_kg' => null,
                'amount' => '0.00',
            ]);
        }
    }

    private function ensureSaleHasNoWeighing(Sale $sale): void
    {
        if ($sale->items()->whereHas('weighingRecord')->exists()) {
            throw ValidationException::withMessages(['items' => __('weighings.errors.sale_edit_locked')]);
        }
    }

    private function ensureDraft(TransactionStatus $status): void
    {
        if ($status !== TransactionStatus::Draft) {
            throw ValidationException::withMessages(['status' => __('trades.errors.draft_only')]);
        }
    }
}
