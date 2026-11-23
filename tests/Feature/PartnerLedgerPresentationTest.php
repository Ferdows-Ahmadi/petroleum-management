<?php

namespace Tests\Feature;

use App\Enums\FinancialTransactionType;
use App\Enums\PartnerRole;
use App\Enums\UserRole;
use App\Models\BusinessPartner;
use App\Models\Deal;
use App\Models\Product;
use App\Support\JalaliDate;
use Dom\HTMLDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesFinanceFixtures;
use Tests\TestCase;

class PartnerLedgerPresentationTest extends TestCase
{
    use CreatesFinanceFixtures, RefreshDatabase;

    public function test_profile_renders_the_quantity_and_rate_of_each_matching_deal_line(): void
    {
        $this->withoutVite();
        $accountant = $this->financeUser();
        $partner = BusinessPartner::factory()->create();
        foreach ([PartnerRole::Supplier, PartnerRole::Customer] as $role) {
            $partner->roles()->create(['role' => $role]);
        }
        $other = BusinessPartner::factory()->create();
        $deal = Deal::create(['deal_date' => '2026-04-02', 'status' => 'COMPLETED', 'product_id' => Product::factory()->create()->id]);
        $deal->destinations()->create(['customer_id' => $other->id, 'final_quantity_kg' => '500', 'rate_per_ton' => '900', 'amount' => '450']);
        $deal->destinations()->create(['customer_id' => $partner->id, 'final_quantity_kg' => '1000', 'rate_per_ton' => '60.55', 'amount' => '60.55']);
        $deal->sources()->create(['supplier_id' => $partner->id, 'quantity_kg' => '1500', 'rate_per_ton' => '50', 'amount' => '75']);

        $page = $this->actingAs($accountant)->get(route('office.accounts', ['partner_id' => $partner->id]))->assertOk();
        $doc = HTMLDocument::createFromString($page->getContent(), LIBXML_NOERROR);
        $rows = $doc->querySelectorAll('[aria-labelledby="account-ledger-heading"] .ledger-table tbody tr');
        $this->assertCount(2, $rows);
        $values = [];
        foreach ($rows as $row) {
            $cells = $row->querySelectorAll('td');
            $this->assertSame(app(JalaliDate::class)->format($deal->deal_date), trim($cells[0]->textContent));
            $this->assertSame(route('deals.show', $deal), $cells[2]->querySelector('a')->getAttribute('href'));
            $values[trim($cells[1]->textContent)] = array_map(fn ($i) => trim($cells[$i]->textContent), [3, 4, 5, 6, 7]);
        }
        $this->assertSame(['—', '75.00', '-75.00', '1,500', '50'], $values[__('finance.ledger_types.DEAL_SOURCE')]);
        $this->assertSame(['60.55', '—', '-14.45', '1,000', '60.55'], $values[__('finance.ledger_types.DEAL_DESTINATION')]);
    }

    public function test_standalone_statement_preserves_chronology_balance_direction_and_payment_link_permissions(): void
    {
        $this->withoutVite();
        $accountant = $this->financeUser();
        $partner = BusinessPartner::factory()->create();
        $partner->roles()->create(['role' => PartnerRole::Customer]);
        $partner->openingBalance()->create([
            'effective_date' => '2026-08-01', 'balance_type' => 'RECEIVABLE', 'amount_usd' => '100',
            'created_by' => $accountant->id,
        ]);
        $txn = $this->financeTransaction($this->paymentPayload($this->financeAccount($accountant), $partner, '110'), FinancialTransactionType::PaymentReceived, $accountant, true);
        foreach ([$accountant, $this->financeUser(UserRole::PurchaseSalesOfficer)] as $viewer) {
            $page = $this->actingAs($viewer)->get(route('partners.statement', $partner))->assertOk();
            $doc = HTMLDocument::createFromString($page->getContent(), LIBXML_NOERROR);
            $rows = $doc->querySelectorAll('[data-ledger] .ledger-table tbody tr');
            $this->assertCount(2, $rows);
            $this->assertStringContainsString('100.00', $rows[0]->textContent);
            $cells = $rows[1]->querySelectorAll('td');
            $this->assertSame($txn->reference_code, trim($cells[2]->textContent));
            $this->assertSame($viewer->is($accountant) ? route('payments.show', $txn) : null, $cells[2]->querySelector('a')?->getAttribute('href'));
            $this->assertSame('—', trim($cells[3]->textContent));
            $this->assertSame('110.00', trim($cells[4]->textContent));
            $this->assertSame('10.00', $cells[5]->querySelector('strong')->textContent);
            $this->assertSame(__('finance.company_owes_partner'), trim($cells[5]->querySelector('.td-sub')->textContent));
        }
    }

    public function test_directory_keeps_role_and_search_when_opening_a_contact_from_the_visible_name(): void
    {
        $this->withoutVite();
        $partner = BusinessPartner::factory()->create(['name' => 'Ledger Sample']);
        $partner->roles()->create(['role' => PartnerRole::Customer]);
        $filters = ['search' => 'Sample', 'role' => PartnerRole::Customer->value];
        $page = $this->actingAs($this->financeUser())->get(route('office.accounts', $filters))->assertOk();
        $doc = HTMLDocument::createFromString($page->getContent(), LIBXML_NOERROR);
        $region = $doc->querySelector('[role="region"][aria-labelledby="account-directory-heading"]');
        $this->assertSame('0', $region->getAttribute('tabindex'));
        $nameLink = $region->querySelector('tbody tr td:first-child a');
        $this->assertSame($partner->name, $nameLink->textContent);
        $this->assertSame(route('office.accounts', ['partner_id' => $partner->id, ...$filters]), $nameLink->getAttribute('href'));
    }
}
