<?php

namespace Tests\Fixtures;

use App\Enums\CapitalTransactionType;
use App\Enums\ContractStatus;
use App\Enums\ContractType;
use App\Enums\Currency;
use App\Enums\ExpenseCategory;
use App\Enums\FinancialAccountType;
use App\Enums\FinancialTransactionType;
use App\Enums\MoneyMovementType;
use App\Enums\PartnerRole;
use App\Enums\UserRole;
use App\Enums\WeighingStatus;
use App\Models\BusinessPartner;
use App\Models\CapitalPartner;
use App\Models\CapitalTransaction;
use App\Models\Contract;
use App\Models\Deal;
use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use App\Models\MoneyMovement;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WeighingRecord;
use App\Services\CapitalTransactionService;
use App\Services\DealService;
use App\Services\DealSettlementService;
use App\Services\FinancialTransactionService;
use App\Services\MoneyMovementService;
use App\Services\TradeConfirmationService;
use App\Services\TradeEntryService;
use App\Services\WeighingService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Fictional demo seeding is restricted to local and testing environments. Provision production users separately.');
        }
        User::firstOrCreate(
            ['email' => 'admin@example.test'],
            [
                'name' => 'مدیر نمونه سیستم',
                'password' => Hash::make(bin2hex(random_bytes(24))),
                'role' => UserRole::SystemAdmin,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        User::firstOrCreate(
            ['email' => 'accountant@example.test'],
            [
                'name' => 'حسابدار نمونه سیستم',
                'password' => Hash::make(bin2hex(random_bytes(24))),
                'role' => UserRole::Accountant,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        foreach ([
            ['name' => 'دیزل', 'code' => 'DSL'],
            ['name' => 'پترول', 'code' => 'PTR'],
            ['name' => 'گاز مایع', 'code' => 'LPG'],
        ] as $product) {
            Product::updateOrCreate(['code' => $product['code']], [...$product, 'unit' => 'kg', 'is_active' => true]);
        }

        foreach ([
            ['name' => 'ذخیره مرکزی نمونه', 'location' => 'کابل'],
            ['name' => 'ذخیره دوم نمونه', 'location' => 'پلچرخی'],
        ] as $warehouse) {
            Warehouse::updateOrCreate(['name' => $warehouse['name']], [...$warehouse, 'is_active' => true]);
        }

        DB::transaction(function (): void {
            $partners = [
                ['name' => 'شرکت ساختمانی سپیدار نمونه', 'phone' => '0700 111 221', 'address' => 'کابل', 'roles' => [PartnerRole::Customer]],
                ['name' => 'تجارت مواد نفتی پامیر نمونه', 'phone' => '0700 111 332', 'address' => 'حیرتان', 'roles' => [PartnerRole::Supplier]],
                ['name' => 'شرکت خدماتی آفتاب نمونه', 'phone' => '0700 111 443', 'address' => 'پغمان', 'roles' => [PartnerRole::Customer, PartnerRole::Supplier]],
                ['name' => 'صرافی اعتماد نمونه', 'phone' => '0700 111 554', 'address' => 'کابل', 'roles' => [PartnerRole::Saraf]],
                ['name' => 'عرضه‌کننده مرحله سوم نمونه', 'phone' => '0700 111 665', 'address' => 'حیرتان', 'roles' => [PartnerRole::Supplier]],
                ['name' => 'مشتری مرحله سوم نمونه', 'phone' => '0700 111 776', 'address' => 'کابل', 'roles' => [PartnerRole::Customer]],
            ];

            foreach ($partners as $data) {
                $roles = $data['roles'];
                unset($data['roles']);
                $partner = BusinessPartner::updateOrCreate(['name' => $data['name']], [...$data, 'is_active' => true]);
                $partner->roles()->delete();
                $partner->roles()->createMany(array_map(fn (PartnerRole $role): array => ['role' => $role], $roles));
            }
        });

        $user = User::where('email', 'admin@example.test')->firstOrFail();
        $product = Product::where('code', 'DSL')->firstOrFail();
        $warehouse = Warehouse::orderBy('id')->firstOrFail();
        $supplier = BusinessPartner::query()
            ->whereHas('roles', fn ($query) => $query->where('role', PartnerRole::Supplier->value))
            ->orderBy('id')
            ->firstOrFail();
        $customer = BusinessPartner::query()
            ->whereHas('roles', fn ($query) => $query->where('role', PartnerRole::Customer->value))
            ->orderBy('id')
            ->firstOrFail();
        $entries = app(TradeEntryService::class);
        $confirmation = app(TradeConfirmationService::class);
        $weighings = app(WeighingService::class);

        $purchase = Purchase::where('reference_number', 'DEMO-PURCHASE-001')->first();
        if (! $purchase) {
            $purchase = $entries->createPurchase([
                'business_partner_id' => $supplier->id,
                'purchase_date' => now()->toDateString(),
                'reference_number' => 'DEMO-PURCHASE-001',
                'currency' => 'USD',
                'notes' => 'معامله آزمایشی برای نمایش گردش ذخیره',
                'items' => [[
                    'product_id' => $product->id,
                    'warehouse_id' => $warehouse->id,
                    'quantity_kg' => '50000',
                    'rate_per_ton' => '1200',
                ]],
            ], $user->id);
        }
        $confirmation->confirmPurchase($purchase, $user->id);

        $sale = Sale::where('reference_number', 'DEMO-SALE-001')->first();
        if (! $sale) {
            $sale = $entries->createSale([
                'business_partner_id' => $customer->id,
                'sale_date' => now()->toDateString(),
                'reference_number' => 'DEMO-SALE-001',
                'currency' => 'USD',
                'notes' => 'معامله آزمایشی با ارقام تأییدشده محاسبه',
                'items' => [[
                    'product_id' => $product->id,
                    'warehouse_id' => $warehouse->id,
                    'requested_quantity_kg' => '20000',
                    'rate_per_ton' => '1310',
                ]],
            ], $user->id);
        }

        $saleItem = $sale->items()->firstOrFail();
        if ($sale->reference_number === 'DEMO-SALE-001' && $saleItem->requested_quantity_kg !== '20000.000') {
            $saleItem->update(['requested_quantity_kg' => '20000.000']);
        }
        $weighing = $saleItem->weighingRecord;

        if (! $weighing && $sale->status->allowsWeighing()) {
            $weighing = $weighings->create([
                'sale_item_id' => $saleItem->id,
                'ticket_number' => 'DEMO-WG-001',
                'station_name' => 'پل باسکول نمونه',
                'weighing_at' => now(),
                'first_weight_kg' => '34475',
                'second_weight_kg' => '15120',
                'vehicle_number' => 'KBL-DEMO-01',
                'driver_name' => 'راننده نمونه',
                'notes' => 'رکورد آزمایشی برای نمایش جریان باسکول',
            ], $user->id);
            $weighings->finalize($weighing, $user->id);
        } elseif (! $weighing) {
            WeighingRecord::create([
                'sale_item_id' => $saleItem->id,
                'ticket_number' => 'DEMO-WG-001',
                'station_name' => 'پل باسکول نمونه',
                'weighing_at' => now(),
                'first_weight_kg' => '34475',
                'second_weight_kg' => '15120',
                'net_weight_kg' => '19355',
                'vehicle_number' => 'KBL-DEMO-01',
                'driver_name' => 'راننده نمونه',
                'notes' => 'رکورد آزمایشی برای نمایش جریان باسکول',
                'status' => WeighingStatus::Finalized,
                'created_by' => $user->id,
                'finalized_by' => $user->id,
                'finalized_at' => now(),
            ]);
        }

        if ($sale->refresh()->status->allowsApproval()) {
            $confirmation->confirmSale($sale, $user->id);
        }

        $usdAccount = FinancialAccount::firstOrCreate(
            ['name' => 'نقد دفتر نمونه'],
            [
                'account_type' => FinancialAccountType::Cash,
                'currency' => Currency::Usd,
                'opening_balance' => '0',
                'opening_balance_date' => now()->toDateString(),
                'description' => 'حساب نقدی ساختگی برای نمایش جریان مالی',
                'is_active' => true,
                'created_by' => $user->id,
            ],
        );
        $afnAccount = FinancialAccount::firstOrCreate(
            ['name' => 'صندوق افغانی نمونه'],
            [
                'account_type' => FinancialAccountType::Cash,
                'currency' => Currency::Afn,
                'opening_balance' => '0',
                'opening_balance_date' => now()->toDateString(),
                'description' => 'حساب افغانی ساختگی برای نمایش تبدیل صریح',
                'is_active' => true,
                'created_by' => $user->id,
            ],
        );
        $financialTransactions = app(FinancialTransactionService::class);

        $financeExamples = [
            [
                'reference' => 'DEMO-RECEIPT-001',
                'type' => FinancialTransactionType::PaymentReceived,
                'data' => ['financial_account_id' => $usdAccount->id, 'business_partner_id' => $customer->id, 'transaction_date' => now()->toDateString(), 'amount' => '25355.00', 'currency' => 'USD', 'exchange_rate_to_usd' => null, 'reference' => 'DEMO-RECEIPT-001', 'description' => 'دریافت ساختگی بابت فروش نمونه'],
            ],
            [
                'reference' => 'DEMO-PAYMENT-001',
                'type' => FinancialTransactionType::PaymentPaid,
                'data' => ['financial_account_id' => $usdAccount->id, 'business_partner_id' => $supplier->id, 'transaction_date' => now()->toDateString(), 'amount' => '4000.00', 'currency' => 'USD', 'exchange_rate_to_usd' => null, 'reference' => 'DEMO-PAYMENT-001', 'description' => 'پرداخت ساختگی به عرضه‌کننده نمونه'],
            ],
            [
                'reference' => 'DEMO-EXPENSE-001',
                'type' => FinancialTransactionType::Expense,
                'data' => ['financial_account_id' => $usdAccount->id, 'transaction_date' => now()->toDateString(), 'amount' => '100.00', 'currency' => 'USD', 'exchange_rate_to_usd' => null, 'expense_category' => ExpenseCategory::Office->value, 'payee' => 'فروشگاه نمونه', 'reference' => 'DEMO-EXPENSE-001', 'description' => 'مصرف ساختگی دفتر'],
            ],
            [
                'reference' => 'DEMO-AFN-EXPENSE-001',
                'type' => FinancialTransactionType::Expense,
                'data' => ['financial_account_id' => $afnAccount->id, 'transaction_date' => now()->toDateString(), 'amount' => '7000.00', 'currency' => 'AFN', 'exchange_rate_to_usd' => '70', 'expense_category' => ExpenseCategory::Office->value, 'payee' => 'فروشگاه نمونه', 'reference' => 'DEMO-AFN-EXPENSE-001', 'description' => '۷۰۰۰ افغانی با نرخ صریح ۷۰ افغانی در برابر یک دالر'],
            ],
        ];

        foreach ($financeExamples as $example) {
            $transaction = FinancialTransaction::where('reference', $example['reference'])->first();
            if (! $transaction) {
                $transaction = $financialTransactions->create($example['data'], $example['type'], $user->id);
            }
            $financialTransactions->confirm($transaction, $user->id);
        }

        $saraf = BusinessPartner::query()->withRole(PartnerRole::Saraf)->orderBy('id')->firstOrFail();
        $phaseThreeSupplier = BusinessPartner::query()->where('name', 'عرضه‌کننده مرحله سوم نمونه')->firstOrFail();
        $phaseThreeCustomer = BusinessPartner::query()->where('name', 'مشتری مرحله سوم نمونه')->firstOrFail();
        $phaseThreeDeal = Deal::query()->where('notes', 'DEMO-V3-MONEY-DEAL')->first();
        if (! $phaseThreeDeal) {
            $phaseThreeDeal = app(DealService::class)->create([
                'deal_date' => now()->toDateString(),
                'product_id' => $product->id,
                'supplier_id' => $phaseThreeSupplier->id,
                'source_quantity_kg' => '19355.000',
                'source_rate_per_ton' => '1200.0000',
                'customer_id' => $phaseThreeCustomer->id,
                'final_quantity_kg' => '19355.000',
                'destination_rate_per_ton' => '1310.0000',
                'notes' => 'DEMO-V3-MONEY-DEAL',
                'submit_action' => 'complete',
            ], $user->id);
        }
        $moneyService = app(MoneyMovementService::class);
        foreach ([
            ['reference' => 'DEMO-V3-RECEIPT', 'type' => MoneyMovementType::CustomerReceipt, 'party' => $phaseThreeCustomer, 'amount' => '25355.00'],
            ['reference' => 'DEMO-V3-PAYMENT', 'type' => MoneyMovementType::SupplierPayment, 'party' => $phaseThreeSupplier, 'amount' => '20000.00'],
        ] as $example) {
            $movement = MoneyMovement::query()->where('reference', $example['reference'])->first();
            if (! $movement) {
                $movement = $moneyService->create([
                    'movement_date' => now()->toDateString(),
                    'movement_type' => $example['type']->value,
                    'counterparty_id' => $example['party']->id,
                    'saraf_id' => $saraf->id,
                    'deal_id' => $phaseThreeDeal->id,
                    'financial_account_id' => null,
                    'amount' => $example['amount'],
                    'currency' => Currency::Usd->value,
                    'exchange_rate_to_usd' => null,
                    'reference' => $example['reference'],
                    'notes' => 'حرکت پول ساختگی برای نمایش جریان مرحله سوم',
                ], $user->id);
            }
            $moneyService->confirm($movement, $user->id);
        }

        if (! $phaseThreeDeal->settlements()->where('status', 'CONFIRMED')->exists()) {
            app(DealSettlementService::class)->confirm($phaseThreeDeal, $user->id, 'DEMO-V4-CANONICAL-SETTLEMENT');
        }

        foreach ([
            ['email' => 'manager@example.test', 'name' => 'مدیر عمومی نمونه', 'role' => UserRole::GeneralManager],
            ['email' => 'trade@example.test', 'name' => 'مسئول خرید و فروش نمونه', 'role' => UserRole::PurchaseSalesOfficer],
            ['email' => 'warehouse@example.test', 'name' => 'مسئول ذخیره نمونه', 'role' => UserRole::WarehouseOfficer],
            ['email' => 'transport@example.test', 'name' => 'مسئول حمل‌ونقل نمونه', 'role' => UserRole::TransportOfficer],
        ] as $demoUser) {
            User::firstOrCreate(['email' => $demoUser['email']], [
                'name' => $demoUser['name'],
                'password' => Hash::make(bin2hex(random_bytes(24))),
                'role' => $demoUser['role'],
                'is_active' => true,
                'email_verified_at' => now(),
            ]);
        }

        $ledgerSupplier = BusinessPartner::updateOrCreate(
            ['name' => 'عرضه‌کننده حساب آزمایشی'],
            ['phone' => '0700 555 010', 'address' => 'کابل', 'is_active' => true],
        );
        $ledgerSupplier->roles()->updateOrCreate(['role' => PartnerRole::Supplier]);
        $ledgerPurchase = Purchase::where('reference_number', 'DEMO-PURCHASE-LEDGER-001')->first();
        if (! $ledgerPurchase) {
            $ledgerPurchase = $entries->createPurchase([
                'business_partner_id' => $ledgerSupplier->id,
                'purchase_date' => now()->toDateString(),
                'reference_number' => 'DEMO-PURCHASE-LEDGER-001',
                'currency' => 'USD',
                'notes' => 'خرید ده هزار دالری برای نمایش حساب عرضه‌کننده',
                'items' => [[
                    'product_id' => $product->id,
                    'warehouse_id' => $warehouse->id,
                    'quantity_kg' => '10000',
                    'rate_per_ton' => '1000',
                ]],
            ], $user->id);
        }
        $confirmation->confirmPurchase($ledgerPurchase, $user->id);

        $ledgerPayment = FinancialTransaction::where('reference', 'DEMO-PAYMENT-LEDGER-001')->first();
        if (! $ledgerPayment) {
            $ledgerPayment = $financialTransactions->create([
                'financial_account_id' => $usdAccount->id,
                'business_partner_id' => $ledgerSupplier->id,
                'transaction_date' => now()->toDateString(),
                'amount' => '4000.00',
                'currency' => 'USD',
                'exchange_rate_to_usd' => null,
                'reference' => 'DEMO-PAYMENT-LEDGER-001',
                'description' => 'پرداخت چهار هزار دالری؛ مانده قابل پرداخت شش هزار دالر',
            ], FinancialTransactionType::PaymentPaid, $user->id);
        }
        $financialTransactions->confirm($ledgerPayment, $user->id);

        if (! Sale::where('reference_number', 'DEMO-SALE-DRAFT-001')->exists()) {
            $entries->createSale([
                'business_partner_id' => $customer->id,
                'sale_date' => now()->toDateString(),
                'reference_number' => 'DEMO-SALE-DRAFT-001',
                'currency' => 'USD',
                'notes' => 'فروش پیش‌نویس در انتظار وزن‌کشی',
                'items' => [[
                    'product_id' => $product->id,
                    'warehouse_id' => $warehouse->id,
                    'requested_quantity_kg' => '5000',
                    'rate_per_ton' => '1300',
                ]],
            ], $user->id);
        }

        $capitalPartner = CapitalPartner::firstOrCreate(
            ['name' => 'شریک سرمایه نمونه'],
            ['phone' => '0700 555 020', 'notes' => 'شخص آزمایشی برای نمایش گردش سرمایه', 'is_active' => true],
        );
        $capitalService = app(CapitalTransactionService::class);
        foreach ([
            ['type' => CapitalTransactionType::InitialCapital, 'amount' => '100000.00', 'account' => $usdAccount->id, 'marker' => 'DEMO-CAPITAL-INITIAL'],
            ['type' => CapitalTransactionType::Contribution, 'amount' => '25000.00', 'account' => $usdAccount->id, 'marker' => 'DEMO-CAPITAL-CONTRIBUTION'],
            ['type' => CapitalTransactionType::ProfitAllocation, 'amount' => '10000.00', 'account' => null, 'marker' => 'DEMO-CAPITAL-PROFIT'],
            ['type' => CapitalTransactionType::Withdrawal, 'amount' => '5000.00', 'account' => $usdAccount->id, 'marker' => 'DEMO-CAPITAL-WITHDRAWAL'],
        ] as $capitalExample) {
            $capitalTransaction = CapitalTransaction::where('notes', $capitalExample['marker'])->first();
            if (! $capitalTransaction) {
                $capitalTransaction = $capitalService->create([
                    'capital_partner_id' => $capitalPartner->id,
                    'financial_account_id' => $capitalExample['account'],
                    'transaction_date' => now()->toDateString(),
                    'transaction_type' => $capitalExample['type']->value,
                    'adjustment_direction' => null,
                    'amount_usd' => $capitalExample['amount'],
                    'notes' => $capitalExample['marker'],
                ], $user->id);
            }
            $capitalService->confirm($capitalTransaction, $user->id);
        }

        Contract::updateOrCreate(['contract_number' => 'DEMO-CONTRACT-001'], [
            'business_partner_id' => $ledgerSupplier->id,
            'contract_type' => ContractType::Purchase,
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->addMonths(3)->toDateString(),
            'agreed_amount' => '50000.00',
            'currency' => Currency::Usd,
            'terms' => 'قرارداد نمونه مستقل از اسناد خرید و فروش برای نمایش بخش قراردادها.',
            'status' => ContractStatus::Active,
            'created_by' => $user->id,
        ]);
    }
}
