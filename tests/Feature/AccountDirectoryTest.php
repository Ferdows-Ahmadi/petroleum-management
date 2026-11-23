<?php

namespace Tests\Feature;

use App\Enums\Currency;
use App\Enums\PartnerRole;
use App\Enums\UserRole;
use App\Models\BusinessPartner;
use App\Models\Deal;
use App\Models\FinancialTransaction;
use App\Models\MoneyMovement;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\PartnerLedgerService;
use App\Support\NumberFormat;
use Dom\HTMLDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Morilog\Jalali\Jalalian;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\Concerns\CreatesFinanceFixtures;
use Tests\TestCase;

class AccountDirectoryTest extends TestCase
{
    use CreatesFinanceFixtures, RefreshDatabase;

    public function test_every_directory_row_matches_the_individual_statement(): void
    {
        $this->withoutVite();
        $accountant = $this->financeUser();
        $account = $this->financeAccount($accountant, Currency::Afn);
        $customer = $this->partner('Group customer', PartnerRole::Customer);
        $supplier = $this->partner('Group supplier', PartnerRole::Supplier);
        $dual = $this->partner('Group dual', PartnerRole::Customer, PartnerRole::Supplier);
        $saraf = $this->partner('Routed saraf', PartnerRole::Saraf);
        $settled = $this->partner('Settled', PartnerRole::Customer);
        $idle = $this->partner('No activity', PartnerRole::Customer);
        $openingOnly = $this->partner('Opening only', PartnerRole::Supplier);
        $paymentOnly = $this->partner('Payment only', PartnerRole::Supplier);
        $openingOnly->openingBalance()->create(['effective_date' => '2026-01-15', 'balance_type' => 'PAYABLE', 'amount_usd' => '20.50', 'created_by' => $accountant->id]);
        $supplier->update(['is_active' => false]);
        $dual->openingBalance()->create(['effective_date' => '2026-02-01', 'balance_type' => 'PAYABLE', 'amount_usd' => '13.51', 'created_by' => $accountant->id]);
        $settled->openingBalance()->create(['effective_date' => '2026-02-01', 'balance_type' => 'RECEIVABLE', 'amount_usd' => '9.28', 'created_by' => $accountant->id]);

        $item = ['product_id' => Product::factory()->create()->id, 'warehouse_id' => Warehouse::factory()->create()->id, 'rate_per_ton' => '1'];
        $sale = $customer->sales()->create(['sale_date' => '2026-03-09', 'status' => 'CONFIRMED']);
        // Summing these two amounts as floating point would lose the cent.
        foreach (['1000000000000000.00', '0.01'] as $amount) {
            $sale->items()->create([...$item, 'final_quantity_kg' => '1', 'amount' => $amount]);
        }
        $purchase = $supplier->purchases()->create(['purchase_date' => '2026-03-08', 'status' => 'CONFIRMED']);
        foreach (['16.76', '2.02'] as $amount) {
            $purchase->items()->create([...$item, 'quantity_kg' => '1', 'amount' => $amount]);
        }
        // A confirmed parent with no lines contributes zero and still advances the date.
        $supplier->purchases()->create(['purchase_date' => '2026-05-07', 'status' => 'CONFIRMED']);
        $draftSale = $customer->sales()->create(['sale_date' => '2026-12-01', 'status' => 'DRAFT']);
        $draftSale->items()->create([...$item, 'final_quantity_kg' => '1', 'amount' => '822.22']);
        $draftPurchase = $supplier->purchases()->create(['purchase_date' => '2026-12-02', 'status' => 'DRAFT']);
        $draftPurchase->items()->create([...$item, 'quantity_kg' => '1', 'amount' => '723.23']);

        foreach (['COMPLETED' => '2026-04-06', 'DRAFT' => '2026-12-03'] as $status => $date) {
            $deal = Deal::create(['deal_date' => $date, 'status' => $status, 'product_id' => $item['product_id']]);
            $deal->sources()->create(['supplier_id' => $dual->id, 'amount' => '2.03']);
            $deal->destinations()->create(['customer_id' => $dual->id, 'amount' => '4.05']);
        }

        $legacy = ['financial_account_id' => $account->id, 'currency' => 'AFN', 'amount' => '700.70',
            'exchange_rate_to_usd' => '70', 'amount_usd' => '10.01', 'created_by' => $accountant->id, 'status' => 'CONFIRMED'];
        FinancialTransaction::create([...$legacy, 'transaction_type' => 'PAYMENT_RECEIVED', 'business_partner_id' => $customer->id, 'transaction_date' => '2026-01-01', 'amount' => '700.00', 'amount_usd' => '10.00']);
        FinancialTransaction::create([...$legacy, 'transaction_type' => 'PAYMENT_PAID', 'business_partner_id' => $supplier->id, 'transaction_date' => '2026-03-08']);
        FinancialTransaction::create([...$legacy, 'transaction_type' => 'PAYMENT_PAID', 'business_partner_id' => $paymentOnly->id, 'transaction_date' => '2026-05-01']);
        FinancialTransaction::create([...$legacy, 'transaction_type' => 'EXPENSE', 'business_partner_id' => $dual->id, 'transaction_date' => '2026-12-04']);
        FinancialTransaction::create([...$legacy, 'transaction_type' => 'PAYMENT_PAID', 'business_partner_id' => $supplier->id, 'transaction_date' => '2026-12-05', 'status' => 'DRAFT']);
        $money = ['saraf_id' => $saraf->id, 'currency' => 'AFN', 'amount' => '649.60', 'exchange_rate_to_usd' => '70', 'amount_usd' => '9.28', 'status' => 'CONFIRMED'];
        MoneyMovement::create([...$money, 'movement_type' => 'CUSTOMER_RECEIPT', 'counterparty_id' => $settled->id, 'movement_date' => '2026-03-04']);
        MoneyMovement::create([...$money, 'movement_type' => 'SUPPLIER_PAYMENT', 'counterparty_id' => $dual->id, 'movement_date' => '2026-01-05']);
        MoneyMovement::create([...$money, 'movement_type' => 'CUSTOMER_RECEIPT', 'counterparty_id' => $dual->id, 'movement_date' => '2026-12-06', 'status' => 'DRAFT']);
        MoneyMovement::create([...$money, 'movement_type' => 'OTHER_PAYMENT', 'counterparty_id' => $dual->id, 'movement_date' => '2026-12-07']);
        // Near-offsets also exercise each driver's individual DECIMAL casts before netting.
        foreach (['CUSTOMER_RECEIPT' => '10000000000000.10', 'SUPPLIER_PAYMENT' => '10000000000000.12'] as $type => $amount) {
            MoneyMovement::create(['movement_type' => $type, 'movement_date' => '2026-02-12', 'counterparty_id' => $dual->id,
                'saraf_id' => $saraf->id, 'currency' => 'USD', 'amount' => $amount, 'amount_usd' => $amount, 'status' => 'CONFIRMED']);
        }

        $this->actingAs($accountant);
        $this->assertRowsMatchStatements([$customer, $supplier, $dual, $saraf, $settled, $idle, $openingOnly, $paymentOnly]);
        $this->assertRowsMatchStatements([$supplier, $dual], ['role' => 'SUPPLIER', 'search' => 'Group']);
    }

    public function test_a_legacy_supplier_line_with_no_amount_has_activity_and_zero_effect(): void
    {
        $this->withoutVite();
        $supplier = $this->partner('Historical supplier', PartnerRole::Supplier);
        $deal = Deal::create(['deal_date' => '2026-04-08', 'status' => 'COMPLETED']);
        $deal->sources()->create(['supplier_id' => $supplier->id, 'amount' => null]);

        $row = $this->actingAs($this->financeUser())->get(route('office.accounts'))->assertOk()->viewData('partnerRows')->sole();
        $this->assertSame('0.00', $row['balance']);
        $this->assertSame('2026-04-08', $row['last_activity']);
    }

    #[TestWith([UserRole::PurchaseSalesOfficer])]
    #[TestWith([UserRole::SystemAdmin])]
    public function test_nonfinancial_directory_hides_sarafs_and_ledger_columns(UserRole $role): void
    {
        $this->withoutVite();
        $visible = $this->partner('Visible customer', PartnerRole::Customer);
        $hidden = $this->partner('Customer with saraf role', PartnerRole::Customer, PartnerRole::Saraf);
        $page = $this->actingAs($this->financeUser($role))->get(route('office.accounts'))->assertOk()
            ->assertSee($visible->name)->assertDontSee($hidden->name);
        $table = HTMLDocument::createFromString($page->getContent(), LIBXML_NOERROR)->querySelector('table');
        $this->assertCount(4, $table->querySelectorAll('thead th'));
        $this->assertCount(4, $table->querySelectorAll('tbody td'));
    }

    public function test_directory_query_count_does_not_grow_per_contact(): void
    {
        $this->withoutVite();
        $user = $this->financeUser();
        $this->actingAs($user);
        $measure = function (int $newContacts) use ($user): int {
            foreach (BusinessPartner::factory()->count($newContacts)->create() as $partner) {
                $partner->roles()->create(['role' => PartnerRole::Customer]);
                $partner->openingBalance()->create(['effective_date' => '2026-01-01', 'balance_type' => 'RECEIVABLE', 'amount_usd' => '15.01', 'created_by' => $user->id]);
            }
            DB::enableQueryLog();
            DB::flushQueryLog();
            $this->get(route('office.accounts'))->assertOk();
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $count;
        };

        $small = $measure(2);
        $this->assertLessThanOrEqual($small, $measure(25));
    }

    private function partner(string $name, PartnerRole ...$roles): BusinessPartner
    {
        $partner = BusinessPartner::factory()->create(['name' => $name]);
        foreach ($roles as $role) {
            $partner->roles()->create(['role' => $role]);
        }

        return $partner;
    }

    /** @param list<BusinessPartner> $partners */
    private function assertRowsMatchStatements(array $partners, array $query = []): void
    {
        $expected = [];
        foreach ($partners as $partner) {
            $last = app(PartnerLedgerService::class)->statementFor($partner)->last();
            $balance = $last['running_balance'] ?? '0.00';
            $expected[$partner->reference_code] = [
                app(NumberFormat::class)->absoluteMoney($balance).' USD',
                match (bccomp($balance, '0', 2)) {
                    1 => 'دریافتنی', -1 => 'پرداختنی', default => 'تصفیه'
                },
                $last ? Jalalian::fromDateTime($last['date'])->format('Y/m/d') : '—',
            ];
        }

        $response = $this->get(route('office.accounts', $query))->assertOk();
        $document = HTMLDocument::createFromString($response->getContent(), LIBXML_NOERROR);
        $actual = [];
        $rows = $document->querySelectorAll('table tbody tr');
        $this->assertCount(count($partners), $rows);
        foreach ($rows as $row) {
            $cells = $row->querySelectorAll('td');
            $actual[trim($cells[1]->textContent)] = [
                trim($cells[3]->querySelector('strong')->textContent),
                trim($cells[3]->querySelector('.td-sub')->textContent),
                trim($cells[4]->textContent),
            ];
        }
        ksort($expected);
        ksort($actual);
        $this->assertSame($expected, $actual);
    }
}
