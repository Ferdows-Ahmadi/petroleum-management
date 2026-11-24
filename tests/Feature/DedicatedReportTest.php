<?php

namespace Tests\Feature;

use App\Enums\Currency;
use App\Enums\FinancialTransactionType;
use App\Enums\PartnerOpeningBalanceType;
use App\Enums\PartnerRole;
use App\Enums\UserRole;
use App\Services\MoneyMovementService;
use App\Services\TradeConfirmationService;
use App\Services\TradeDraftService;
use App\Support\JalaliDate;
use Dom\HTMLDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesFinanceFixtures;
use Tests\Concerns\CreatesTradeFixtures;
use Tests\TestCase;

class DedicatedReportTest extends TestCase
{
    use CreatesFinanceFixtures, CreatesTradeFixtures, RefreshDatabase;

    public static function menus(): array
    {
        $trade = ['purchases', 'sales', 'weighings', 'inventory'];
        $office = [...$trade, 'accounts', 'settlements'];
        $all = [...$office, 'expenses', 'profit-loss', 'capital'];

        return [
            [UserRole::GeneralManager, $all], [UserRole::Accountant, $all], [UserRole::SystemAdmin, $all],
            [UserRole::PurchaseSalesOfficer, $office], [UserRole::WarehouseOfficer, $trade],
            [UserRole::TransportOfficer, ['weighings']],
        ];
    }

    #[DataProvider('menus')]
    public function test_each_visible_menu_item_opens_a_dedicated_printable_report(UserRole $role, array $allowed): void
    {
        $this->actingAs($this->financeUser($role));
        $index = $this->get(route('reports.index'))->assertOk();
        $document = HTMLDocument::createFromString($index->getContent(), LIBXML_NOERROR, 'UTF-8');
        $links = $document->querySelectorAll('nav.report-directory a');
        $this->assertCount(count($allowed), $links);
        foreach ($links as $link) {
            $url = $link->getAttribute('href');
            $this->assertContains(basename($url), $allowed);
            $report = $this->get($url)->assertOk()->assertSee('window.print()', false);
            $this->assertStringStartsWith('reports.', $report->original->name());
            $dom = HTMLDocument::createFromString($report->getContent(), LIBXML_NOERROR, 'UTF-8');
            $this->assertCount(0, $dom->querySelectorAll('.report-document form[method="post" i]'));
            $this->assertNotSame('', trim($dom->querySelector('.print-header strong')->textContent));
        }

        foreach (array_diff(['accounts', 'inventory', 'settlements', 'profit-loss', 'capital', 'expenses', 'purchases', 'sales', 'weighings'], $allowed) as $denied) {
            $this->get(route('reports.'.$denied))->assertForbidden();
        }
    }

    public function test_positions_and_filtered_statement_keep_confirmed_historical_balances(): void
    {
        $manager = $this->financeUser(UserRole::GeneralManager);
        $customer = $this->tradePartner([PartnerRole::Customer]);
        $supplier = $this->tradePartner([PartnerRole::Supplier]);
        $account = $this->financeAccount($manager);
        foreach ([[$customer, PartnerOpeningBalanceType::Receivable, '1000'], [$supplier, PartnerOpeningBalanceType::Payable, '83.19']] as [$partner, $type, $opening]) {
            $partner->openingBalance()->create(['amount_usd' => $opening, 'balance_type' => $type, 'effective_date' => '2026-08-01', 'created_by' => $manager->id]);
        }
        foreach ([['2026-08-02', '300', true], ['2026-08-08', '500', true], ['2026-08-08', '90', false]] as [$day, $amount, $confirmed]) {
            $this->financeTransaction($this->paymentPayload($account, $customer, $amount, $day), FinancialTransactionType::PaymentReceived, $manager, $confirmed);
        }
        $this->actingAs($manager)->get(route('reports.accounts'))->assertOk()->assertViewHas('receivables', '200.00')->assertViewHas('payables', '83.19');
        $day = app(JalaliDate::class)->format('2026-08-08');
        $this->get(route('reports.accounts', ['partner_id' => $customer->id, 'date_from' => $day, 'date_to' => $day]))
            ->assertOk()->assertViewIs('reports.statement')->assertViewHas('opening', '700.00')->assertViewHas('closing', '200.00')
            ->assertViewHas('rows', fn ($rows) => $rows->count() === 1 && $rows->first()['credit'] === '500.00')
            ->assertSee($day)->assertSee('200.00 USD');

        $restricted = $this->financeUser(UserRole::PurchaseSalesOfficer);
        $saraf = $this->tradePartner([PartnerRole::Saraf]);
        $this->actingAs($restricted)->get(route('reports.accounts', ['partner_id' => $customer->id]))->assertOk()
            ->assertViewIs('reports.accounts')->assertDontSee('200.00')->assertDontSee($saraf->name);
        $this->get(route('reports.accounts', ['partner_id' => $saraf->id]))->assertNotFound();
        $this->get(route('reports.accounts.journal'))->assertForbidden();
    }

    public function test_account_and_saraf_journals_preserve_native_amounts_rates_and_confirmed_daily_totals(): void
    {
        $user = $this->financeUser();
        $account = $this->financeAccount($user, Currency::Afn, '100');
        $customer = $this->tradePartner([PartnerRole::Customer]);
        $saraf = $this->tradePartner([PartnerRole::Saraf]);
        $day = '2026-08-08';
        $legacy = $this->financeTransaction($this->paymentPayload($account, $customer, '7000', $day, '70'), FinancialTransactionType::PaymentReceived, $user, true);
        $pending = $this->financeTransaction($this->paymentPayload($account, $customer, '8800', $day, '80'), FinancialTransactionType::PaymentReceived, $user);
        $money = app(MoneyMovementService::class);
        $remittance = $money->create([
            'movement_type' => 'CUSTOMER_RECEIPT', 'movement_date' => $day, 'counterparty_id' => $customer->id,
            'saraf_id' => $saraf->id, 'amount' => '7350', 'currency' => 'AFN', 'exchange_rate_to_usd' => '73.5',
        ], $user->id);
        $money->confirm($remittance, $user->id);
        $date = app(JalaliDate::class)->format($day);
        $route = 'reports.accounts.journal';
        $this->actingAs($user)->get(route($route, ['book' => 'account:'.$account->id, 'day' => $date]))->assertOk()
            ->assertSee('7,000.00 AFN')->assertSee('7,100.00 AFN')->assertSee($legacy->reference_code)
            ->assertDontSee($pending->reference_code)->assertDontSee($remittance->reference_code);
        $this->get(route($route, ['book' => 'saraf:'.$saraf->id, 'day' => $date]))->assertOk()
            ->assertSee('7,350.00 AFN')->assertSee('73.5')->assertSee('100.00 USD')->assertSee($remittance->reference_code)
            ->assertDontSee($legacy->reference_code);
        $this->get(route($route, ['book' => 'saraf:'.$customer->id]))->assertNotFound();
    }

    public function test_combined_product_and_warehouse_filters_must_match_the_same_trade_line(): void
    {
        $manager = $this->financeUser();
        $supplier = $this->tradePartner([PartnerRole::Supplier]);
        $customer = $this->tradePartner([PartnerRole::Customer]);
        $diesel = $this->tradeProduct('D1');
        $petrol = $this->tradeProduct('P2');
        $north = $this->tradeWarehouse('شمال');
        $south = $this->tradeWarehouse('جنوب');
        $drafts = app(TradeDraftService::class);
        foreach (['purchases', 'sales'] as $kind) {
            if ($kind === 'purchases') {
                $payload = $this->purchasePayload($supplier, $diesel, $north);
                $payload['items'][] = $this->purchasePayload($supplier, $petrol, $south)['items'][0];
                $drafts->createPurchase($payload, $manager->id);
            } else {
                $payload = $this->salePayload($customer, $diesel, $north);
                $payload['items'][] = $this->salePayload($customer, $petrol, $south)['items'][0];
                $drafts->createSale($payload, $manager->id);
            }
            $this->actingAs($manager)->get(route('reports.'.$kind, ['product_id' => $diesel->id, 'warehouse_id' => $south->id]))
                ->assertOk()->assertViewHas('records', fn ($records) => $records->isEmpty());
            $this->get(route('reports.'.$kind, ['product_id' => $diesel->id, 'warehouse_id' => $north->id]))
                ->assertOk()->assertViewHas('records', fn ($records) => $records->count() === 1 && $records->first()->items->count() === 1);
        }
    }

    public function test_inventory_uses_end_of_period_valuation_and_restricts_values(): void
    {
        $user = $this->financeUser();
        $warehouse = $this->tradeWarehouse();
        $supplier = $this->tradePartner([PartnerRole::Supplier]);
        $diesel = $this->tradeProduct('DEMO');
        $other = $this->tradeProduct('OTHER');
        foreach ([[$diesel, '2026-08-01', '1234'], [$diesel, '2026-08-09', '2500'], [$other, '2026-08-01', '800']] as [$product, $date, $rate]) {
            $payload = $this->purchasePayload($supplier, $product, $warehouse, '1000', $rate);
            $payload['purchase_date'] = $date;
            $payload['reference_number'] = $product->code.'-'.$date;
            $purchase = app(TradeDraftService::class)->createPurchase($payload, $user->id);
            app(TradeConfirmationService::class)->confirmPurchase($purchase, $user->id);
        }
        $url = route('reports.inventory', ['date_to' => app(JalaliDate::class)->format('2026-08-08'), 'product_id' => $diesel->id, 'warehouse_id' => $warehouse->id]);
        $this->actingAs($user)->get($url)->assertOk()->assertViewHas('quantity', '1000.000')
            ->assertViewHas('value', '1234.00')->assertSee('1,234.00 USD');
        $this->actingAs($this->financeUser(UserRole::WarehouseOfficer))->get($url)->assertOk()
            ->assertSee('1,000 kg')->assertDontSee('1,234.00')->assertDontSee('ارزش موجودی');
    }
}
