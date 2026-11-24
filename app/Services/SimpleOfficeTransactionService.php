<?php

namespace App\Services;

use App\Enums\Currency;
use App\Enums\FinancialTransactionType;
use App\Enums\PartnerRole;
use App\Enums\WeighingMode;
use App\Models\FinancialAccount;
use App\Models\Purchase;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;

class SimpleOfficeTransactionService
{
    public function __construct(
        private readonly InlinePartnerService $partners,
        private readonly TradeEntryService $entries,
        private readonly TradeConfirmationService $confirmation,
        private readonly WeighingService $weighings,
        private readonly FinancialTransactionService $finance,
        private readonly PrivateEvidenceService $evidence,
    ) {}

    /** @param array<string, mixed> $data */
    public function createPurchase(array $data, int $userId, bool $canRecordPayment): Purchase
    {
        $path = isset($data['ticket_image']) ? $this->evidence->storePurchaseBill($data['ticket_image']) : null;

        try {
            return DB::transaction(function () use ($data, $userId, $canRecordPayment, $path): Purchase {
                $partner = $this->partners->resolve(
                    isset($data['business_partner_id']) ? (int) $data['business_partner_id'] : null,
                    $data['partner_name'] ?? null,
                    $data['partner_phone'] ?? null,
                    PartnerRole::Supplier,
                );
                $purchase = $this->entries->createPurchase([
                    'business_partner_id' => $partner->id,
                    'purchase_date' => $data['purchase_date'],
                    'reference_number' => $data['reference_number'] ?? null,
                    'currency' => Currency::Usd->value,
                    'notes' => $data['notes'] ?? null,
                    'ticket_image_path' => $path,
                    'items' => $data['items'],
                ], $userId);

                if ($data['submit_action'] === 'confirm') {
                    $this->confirmation->confirmPurchase($purchase, $userId);
                }

                if (! empty($data['payment_amount'])) {
                    abort_unless($canRecordPayment, 403);
                    $this->recordPartnerMoney($partner->id, $data, FinancialTransactionType::PaymentPaid, $purchase->reference_code, $userId);
                }

                return $purchase->refresh();
            });
        } catch (\Throwable $exception) {
            $this->evidence->delete($path);
            throw $exception;
        }
    }

    /** @param array<string, mixed> $data */
    public function createSale(array $data, int $userId, bool $canWeigh, bool $canRecordPayment): Sale
    {
        $path = isset($data['ticket_image']) ? $this->evidence->storeWeighbridgeBill($data['ticket_image']) : null;

        try {
            return DB::transaction(function () use ($data, $userId, $canWeigh, $canRecordPayment, $path): Sale {
                $partner = $this->partners->resolve(
                    isset($data['business_partner_id']) ? (int) $data['business_partner_id'] : null,
                    $data['partner_name'] ?? null,
                    $data['partner_phone'] ?? null,
                    PartnerRole::Customer,
                );
                $sale = $this->entries->createSale([
                    'business_partner_id' => $partner->id,
                    'sale_date' => $data['sale_date'],
                    'reference_number' => $data['reference_number'] ?? null,
                    'currency' => Currency::Usd->value,
                    'notes' => $data['notes'] ?? null,
                    'items' => $data['items'],
                ], $userId);

                $weighing = null;
                if (! empty($data['first_weight_kg']) || ! empty($data['net_weight_kg'])) {
                    abort_unless($canWeigh, 403);
                    $weighing = $this->weighings->create([
                        'sale_item_id' => $sale->items->first()->id,
                        'weighing_at' => $data['weighing_at'],
                        'weighing_mode' => $data['weighing_mode'] ?? WeighingMode::TwoStage->value,
                        'first_weight_kg' => $data['first_weight_kg'] ?? null,
                        'second_weight_kg' => $data['second_weight_kg'] ?? null,
                        'net_weight_kg' => $data['net_weight_kg'] ?? null,
                        'ticket_number' => $data['ticket_number'] ?? null,
                        'station_name' => $data['station_name'] ?? null,
                        'vehicle_number' => $data['vehicle_number'] ?? null,
                        'driver_name' => $data['driver_name'] ?? null,
                        'ticket_image_path' => $path,
                    ], $userId);
                }

                if ($data['submit_action'] === 'confirm') {
                    abort_unless($canWeigh && $weighing, 403);
                    $this->weighings->finalize($weighing, $userId);
                    $this->confirmation->confirmSale($sale, $userId);
                }

                if (! empty($data['payment_amount'])) {
                    abort_unless($canRecordPayment, 403);
                    $this->recordPartnerMoney($partner->id, $data, FinancialTransactionType::PaymentReceived, $sale->reference_code, $userId);
                }

                return $sale->refresh();
            });
        } catch (\Throwable $exception) {
            $this->evidence->delete($path);
            throw $exception;
        }
    }

    /** @param array<string, mixed> $data */
    private function recordPartnerMoney(int $partnerId, array $data, FinancialTransactionType $type, string $reference, int $userId): void
    {
        $account = FinancialAccount::query()->findOrFail($data['financial_account_id']);
        $transaction = $this->finance->create([
            'financial_account_id' => $account->id,
            'business_partner_id' => $partnerId,
            'transaction_date' => $data['purchase_date'] ?? $data['sale_date'],
            'amount' => $data['payment_amount'],
            'currency' => $account->currency->value,
            'exchange_rate_to_usd' => $account->currency === Currency::Afn ? ($data['exchange_rate_to_usd'] ?? null) : null,
            'reference' => $reference,
            'description' => $data['notes'] ?? null,
        ], $type, $userId);
        $this->finance->confirm($transaction, $userId);
    }
}
