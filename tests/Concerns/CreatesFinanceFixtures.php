<?php

namespace Tests\Concerns;

use App\Enums\Currency;
use App\Enums\FinancialAccountType;
use App\Enums\FinancialTransactionType;
use App\Enums\UserRole;
use App\Models\BusinessPartner;
use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use App\Models\User;
use App\Services\FinancialTransactionService;

trait CreatesFinanceFixtures
{
    protected function financeUser(UserRole $role = UserRole::Accountant): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => true]);
    }

    protected function financeAccount(User $user, Currency $currency = Currency::Usd, string $opening = '0', string $date = '2026-08-01'): FinancialAccount
    {
        return FinancialAccount::create([
            'name' => 'حساب آزمایشی '.fake()->unique()->numerify('####'),
            'account_type' => FinancialAccountType::Cash,
            'currency' => $currency,
            'opening_balance' => $opening,
            'opening_balance_date' => $date,
            'description' => 'حساب ساختگی آزمون',
            'is_active' => true,
            'created_by' => $user->id,
        ]);
    }

    /** @return array<string, mixed> */
    protected function paymentPayload(FinancialAccount $account, BusinessPartner $partner, string $amount = '100', string $date = '2026-08-08', ?string $rate = null): array
    {
        return [
            'financial_account_id' => $account->id,
            'business_partner_id' => $partner->id,
            'transaction_date' => $date,
            'amount' => $amount,
            'currency' => $account->currency->value,
            'exchange_rate_to_usd' => $rate,
            'reference' => 'TEST-FIN-'.fake()->unique()->numerify('#####'),
            'description' => 'حرکت مالی ساختگی آزمون',
        ];
    }

    protected function financeTransaction(array $payload, FinancialTransactionType $type, User $user, bool $confirm = false): FinancialTransaction
    {
        $service = app(FinancialTransactionService::class);
        $transaction = $service->create($payload, $type, $user->id);
        if ($confirm) {
            $service->confirm($transaction, $user->id);
        }

        return $transaction->refresh();
    }
}
