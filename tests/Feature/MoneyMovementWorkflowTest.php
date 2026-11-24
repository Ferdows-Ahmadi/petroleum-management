<?php

namespace Tests\Feature;

use App\Enums\Currency;
use App\Enums\MoneyMovementStatus;
use App\Enums\MoneyMovementType;
use App\Enums\PartnerRole;
use App\Enums\UserRole;
use App\Models\BusinessPartner;
use App\Models\Deal;
use App\Models\MoneyMovement;
use App\Models\Product;
use App\Models\User;
use App\Services\DailyJournalService;
use App\Services\DealMoneySummaryService;
use App\Services\DealService;
use App\Services\FinancialAccountBalanceService;
use App\Services\MoneyMovementService;
use App\Services\PartnerLedgerService;
use App\Services\SarafLedgerService;
use App\Support\JalaliDate;
use App\Support\RelatedDeals;
use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\Concerns\CreatesFinanceFixtures;
use Tests\Fixtures\HistoricalDeal;
use Tests\TestCase;

class MoneyMovementWorkflowTest extends TestCase
{
    use CreatesFinanceFixtures, RefreshDatabase;

    public function test_canonical_deal_receipt_and_payment_update_every_projection_once(): void
    {
        [$deal, $supplier, $customer, $saraf, $user] = $this->canonicalContext();

        $receipt = $this->movement($user, [
            'movement_type' => MoneyMovementType::CustomerReceipt->value,
            'counterparty_id' => $customer->id,
            'saraf_id' => $saraf->id,
            'deal_id' => $deal->id,
            'amount' => '25355.00',
        ], true);
        $payment = $this->movement($user, [
            'movement_type' => MoneyMovementType::SupplierPayment->value,
            'counterparty_id' => $supplier->id,
            'saraf_id' => $saraf->id,
            'deal_id' => $deal->id,
            'amount' => '20000.00',
        ], true);

        $ledger = app(PartnerLedgerService::class);
        $this->assertSame('0.05', $ledger->balanceFor($customer));
        $this->assertSame('-3226.00', $ledger->balanceFor($supplier));
        $this->assertSame('5355.00', app(SarafLedgerService::class)->balanceFor($saraf));
        $this->assertCount(2, app(SarafLedgerService::class)->statementFor($saraf));

        $journal = app(DailyJournalService::class)->forSaraf($saraf, '2026-08-30');
        $this->assertSame('25355.00', $journal['received']);
        $this->assertSame('20000.00', $journal['paid']);
        $this->assertSame('5355.00', $journal['closing_balance']);
        $this->assertCount(2, $journal['entries']);

        $summary = app(DealMoneySummaryService::class)->forDeal($deal);
        $this->assertSame('0.05', $summary['remaining_receivable']);
        $this->assertSame('3226.00', $summary['remaining_payable']);
        $this->assertSame('PARTIAL', $summary['settlement_state']);
        $this->assertCount(2, $summary['movements']);
        $this->assertDatabaseCount('money_movements', 2);
        $this->assertDatabaseHas('money_movements', ['id' => $receipt->id, 'status' => 'CONFIRMED']);
        $this->assertDatabaseHas('money_movements', ['id' => $payment->id, 'status' => 'CONFIRMED']);
    }

    public function test_partial_receipts_reduce_customer_balance_over_several_dates(): void
    {
        [$deal, , $customer, $saraf, $user] = $this->simpleContext('1000.00', '1000.00');
        $this->movement($user, ['movement_type' => 'CUSTOMER_RECEIPT', 'counterparty_id' => $customer->id, 'saraf_id' => $saraf->id, 'deal_id' => $deal->id, 'amount' => '300', 'movement_date' => '2026-08-30'], true);
        $this->movement($user, ['movement_type' => 'CUSTOMER_RECEIPT', 'counterparty_id' => $customer->id, 'saraf_id' => $saraf->id, 'deal_id' => $deal->id, 'amount' => '500', 'movement_date' => '2026-08-31'], true);

        $this->assertSame('200.00', app(PartnerLedgerService::class)->balanceFor($customer));
        $this->assertCount(3, app(PartnerLedgerService::class)->statementFor($customer));
    }

    public function test_partial_payment_entry_replays_once_and_shows_confirmed_totals_and_dated_history(): void
    {
        [$deal, $supplier, $customer, $saraf, $user] = $this->simpleContext('750.00', '1000.00');
        $this->actingAs($user);
        $receipt = $this->payload([
            'counterparty_id' => $customer->id, 'saraf_id' => $saraf->id, 'deal_id' => $deal->id,
            'return_to' => 'deal', 'confirm_now' => 1,
        ]);
        foreach ([['500', '2026-09-04', 'Later receipt'], ['300', '2026-09-02', 'Earlier receipt']] as [$amount, $date, $ref]) {
            $post = array_replace($receipt, ['operation_key' => (string) Str::uuid(), 'amount' => $amount, 'movement_date' => $date, 'reference' => $ref]);
            $this->post(route('money-movements.store'), $post)->assertSessionHasNoErrors();
            $this->post(route('money-movements.store'), $post)->assertSessionHasNoErrors();
        }
        $historical = array_replace($receipt, [
            'confirm_now' => 0, 'movement_date' => '2026-09-03', 'amount' => '30', 'reference' => 'Draft receipt',
        ]);
        $this->post(route('money-movements.store'), $historical)->assertSessionHasErrors('confirm_now');
        $withoutConfirmation = $historical;
        unset($withoutConfirmation['confirm_now']);
        $this->post(route('money-movements.store'), $withoutConfirmation)->assertSessionHasErrors('confirm_now');
        $this->assertDatabaseCount('money_movements', 2);
        $this->movement($user, $historical);
        $this->movement($user, ['movement_type' => 'SUPPLIER_PAYMENT', 'counterparty_id' => $supplier->id,
            'saraf_id' => $saraf->id, 'deal_id' => $deal->id, 'amount' => '50'], true);

        foreach ([route('deals.show', $deal), route('office.accounts', ['partner_id' => $customer->id])] as $url) {
            $page = $this->get($url)->assertOk()->assertSeeInOrder(['Earlier receipt', 'Draft receipt', 'Later receipt']);
            $dom = HTMLDocument::createFromString($page->getContent(), LIBXML_NOERROR);
            $summary = $dom->querySelector('[data-payment-side="CUSTOMER_RECEIPT"]');
            $amounts = [];
            foreach ($summary->querySelectorAll('dd') as $value) {
                $amounts[] = trim($value->textContent);
            }
            $this->assertSame(['1,000.00 USD', '800.00 USD', '200.00 USD'], $amounts);
            $this->assertStringContainsString(MoneyMovementStatus::Draft->label(), $dom->querySelector('.payment-history')->textContent);
        }
        $supplierPage = $this->get(route('office.accounts', ['partner_id' => $supplier->id, 'entry' => 'money', 'direction' => 'SUPPLIER_PAYMENT']))->assertOk();
        $dom = HTMLDocument::createFromString($supplierPage->getContent(), LIBXML_NOERROR);
        $this->assertStringContainsString('700.00 USD', $dom->querySelector('[data-payment-side="SUPPLIER_PAYMENT"]')->textContent);
        $this->assertSame('SUPPLIER_PAYMENT', $this->selectedValue($dom->querySelector('#money-entry form'), 'movement_type'));
        $this->assertSame('', $this->selectedValue($dom->querySelector('#money-entry form'), 'money_route'));
        $this->assertDatabaseCount('money_movements', 4);
        $this->assertSame('200.00', app(PartnerLedgerService::class)->balanceFor($customer));
    }

    public function test_multiple_sarafs_each_receive_only_their_own_effect(): void
    {
        [$deal, , $customer, $sarafC, $user] = $this->simpleContext('1000.00', '1000.00');
        $sarafD = $this->party('Saraf D', [PartnerRole::Saraf]);
        $this->movement($user, ['movement_type' => 'CUSTOMER_RECEIPT', 'counterparty_id' => $customer->id, 'saraf_id' => $sarafC->id, 'deal_id' => $deal->id, 'amount' => '400'], true);
        $this->movement($user, ['movement_type' => 'CUSTOMER_RECEIPT', 'counterparty_id' => $customer->id, 'saraf_id' => $sarafD->id, 'deal_id' => $deal->id, 'amount' => '600'], true);

        $this->assertSame('0.00', app(PartnerLedgerService::class)->balanceFor($customer));
        $this->assertSame('400.00', app(SarafLedgerService::class)->balanceFor($sarafC));
        $this->assertSame('600.00', app(SarafLedgerService::class)->balanceFor($sarafD));
    }

    public function test_direct_afn_receipt_requires_rate_and_updates_matching_account_in_native_currency(): void
    {
        [$deal, , $customer, , $user] = $this->simpleContext('100.00', '100.00');
        $account = $this->financeAccount($user, Currency::Afn);
        $movement = $this->movement($user, [
            'movement_type' => 'CUSTOMER_RECEIPT', 'counterparty_id' => $customer->id,
            'financial_account_id' => $account->id, 'deal_id' => $deal->id,
            'amount' => '7000', 'currency' => 'AFN', 'exchange_rate_to_usd' => '70',
        ], true);

        $this->assertSame('100.00', $movement->amount_usd);
        $this->assertSame('0.00', app(PartnerLedgerService::class)->balanceFor($customer));
        $this->assertSame('7000.00', app(FinancialAccountBalanceService::class)->balanceFor($account));
        $this->assertSame('7000.00', app(DailyJournalService::class)->forAccount($account, '2026-08-30')['received']);
    }

    public function test_overpayment_becomes_customer_credit_without_reallocation(): void
    {
        [$deal, , $customer, $saraf, $user] = $this->simpleContext('1000.00', '1000.00');
        $this->movement($user, ['movement_type' => 'CUSTOMER_RECEIPT', 'counterparty_id' => $customer->id, 'saraf_id' => $saraf->id, 'deal_id' => $deal->id, 'amount' => '1100'], true);

        $this->assertSame('-100.00', app(PartnerLedgerService::class)->balanceFor($customer));
        $this->assertSame('-100.00', app(DealMoneySummaryService::class)->forDeal($deal)['remaining_receivable']);
        $this->actingAs($user)->get(route('deals.show', $deal))->assertOk()->assertSee('اعتبار مشتری')->assertSee('100.00 USD');
        $this->get(route('office.accounts', ['partner_id' => $customer->id]))->assertOk()->assertSee('اعتبار مشتری');
    }

    public function test_money_movement_never_fabricates_trade_or_inventory_rows(): void
    {
        [$deal, , $customer, $saraf, $user] = $this->simpleContext('1000.00', '1000.00');
        $counts = ['deals' => Deal::count(), 'deal_sources' => $deal->sources()->count(), 'deal_destinations' => $deal->destinations()->count()];
        $this->movement($user, ['movement_type' => 'CUSTOMER_RECEIPT', 'counterparty_id' => $customer->id, 'saraf_id' => $saraf->id, 'deal_id' => $deal->id, 'amount' => '100'], true);

        $this->assertSame($counts['deals'], Deal::count());
        $this->assertSame($counts['deal_sources'], $deal->sources()->count());
        $this->assertSame($counts['deal_destinations'], $deal->destinations()->count());
        $this->assertDatabaseCount('purchases', 0);
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('financial_transactions', 0);
    }

    public function test_deal_completion_alone_creates_no_payment(): void
    {
        [$deal] = $this->simpleContext('1000.00', '1000.00');
        $this->assertSame('COMPLETED', $deal->status->value);
        $this->assertDatabaseCount('money_movements', 0);
        $this->assertSame('UNPAID', app(DealMoneySummaryService::class)->forDeal($deal)['settlement_state']);
    }

    public function test_confirmed_movement_cannot_be_updated_or_deleted(): void
    {
        [$deal, , $customer, $saraf, $user] = $this->simpleContext('1000.00', '1000.00');
        $movement = $this->movement($user, ['movement_type' => 'CUSTOMER_RECEIPT', 'counterparty_id' => $customer->id, 'saraf_id' => $saraf->id, 'deal_id' => $deal->id, 'amount' => '100'], true);

        try {
            app(MoneyMovementService::class)->update($movement, $this->payload(['movement_type' => 'CUSTOMER_RECEIPT', 'counterparty_id' => $customer->id, 'saraf_id' => $saraf->id, 'deal_id' => $deal->id, 'amount' => '200']));
            $this->fail('Confirmed movement update should fail.');
        } catch (ValidationException) {
            $this->assertSame('100.00', $movement->refresh()->amount);
        }

        $this->expectException(ValidationException::class);
        app(MoneyMovementService::class)->delete($movement);
    }

    public function test_draft_can_be_edited_deleted_and_does_not_affect_ledgers(): void
    {
        [$deal, , $customer, $saraf, $user] = $this->simpleContext('1000.00', '1000.00');
        $movement = $this->movement($user, ['movement_type' => 'CUSTOMER_RECEIPT', 'counterparty_id' => $customer->id, 'saraf_id' => $saraf->id, 'deal_id' => $deal->id, 'amount' => '100']);
        $this->assertSame('1000.00', app(PartnerLedgerService::class)->balanceFor($customer));
        app(MoneyMovementService::class)->update($movement, $this->payload(['movement_type' => 'CUSTOMER_RECEIPT', 'counterparty_id' => $customer->id, 'saraf_id' => $saraf->id, 'deal_id' => $deal->id, 'amount' => '200']));
        app(MoneyMovementService::class)->delete($movement);
        $this->assertDatabaseCount('money_movements', 0);
    }

    public function test_party_may_hold_customer_supplier_and_saraf_roles(): void
    {
        $party = $this->party('Multi Role', [PartnerRole::Customer, PartnerRole::Supplier, PartnerRole::Saraf]);
        $this->assertCount(3, $party->roles);
        $this->actingAs(User::factory()->create(['role' => UserRole::Accountant]))->get(route('office.accounts', ['partner_id' => $party->id]))->assertOk()->assertSee(__('roles.partner.SARAF'));
    }

    public function test_dual_role_profile_identifies_the_fixed_counterparty_for_both_movement_types(): void
    {
        $party = $this->party('Dual Role Contact', [PartnerRole::Customer, PartnerRole::Supplier]);
        $this->actingAs(User::factory()->create(['role' => UserRole::Accountant]));
        $fields = $this->paymentFields(route('office.accounts', ['partner_id' => $party->id]));

        $fixed = $fields->querySelector('[data-fixed-counterparty]');
        $this->assertInstanceOf(Element::class, $fixed);
        $this->assertStringContainsString($party->name, $fixed->textContent);
        $this->assertStringContainsString($party->reference_code, $fixed->textContent);
        $this->assertSame((string) $party->id, $fields->querySelector('input[name=counterparty_id]')->getAttribute('value'));
        $this->assertNull($fields->querySelector('[data-money-party-combo]'));
        foreach (['CUSTOMER_RECEIPT', 'SUPPLIER_PAYMENT'] as $type) {
            $this->assertNotNull($fields->querySelector("select[name=movement_type] option[value=$type]"));
        }
    }

    public function test_editing_a_supplier_payment_draft_initially_shows_its_saved_counterparty(): void
    {
        [$deal, $supplier, , $saraf, $accountant] = $this->simpleContext('900.00', '1200.00');
        $draft = $this->movement($accountant, [
            'movement_type' => 'SUPPLIER_PAYMENT', 'counterparty_id' => $supplier->id,
            'saraf_id' => $saraf->id, 'deal_id' => $deal->id,
        ]);
        $this->actingAs($accountant);
        $fields = $this->paymentFields(route('money-movements.edit', $draft));

        $this->assertSame('SUPPLIER_PAYMENT', $fields->querySelector('select[name=movement_type] option[selected]')->getAttribute('value'));
        $this->assertSame((string) $supplier->id, $fields->querySelector('input[name=counterparty_id]')->getAttribute('value'));
        $this->assertSame($supplier->name, $fields->querySelector('[data-money-party-combo] [data-party-query]')->getAttribute('value'));
    }

    #[TestWith([true], 'two customers and one repeated supplier')]
    #[TestWith([false], 'two suppliers and one repeated customer')]
    public function test_deal_money_form_defaults_each_direction_only_for_one_distinct_eligible_contact(bool $multipleCustomers): void
    {
        [$deal, $supplier, $customer, , $accountant] = $this->dealContext('1000', '900', '1200', historicalDraft: true);
        $other = $this->party('Second Contact', [PartnerRole::Customer, PartnerRole::Supplier]);
        $service = app(DealService::class);
        $service->addSource($deal, [
            'source_type' => 'SUPPLIER', 'supplier_id' => $multipleCustomers ? $supplier->id : $other->id,
            'quantity_kg' => '1000', 'rate_per_ton' => '900',
        ]);
        $service->addDestination($deal, [
            'destination_type' => 'CUSTOMER', 'customer_id' => $multipleCustomers ? $other->id : $customer->id,
            'final_quantity_kg' => '1000', 'rate_per_ton' => '1200',
        ]);
        $service->complete($deal, $accountant->id);
        $this->actingAs($accountant);

        $customerId = $multipleCustomers ? '' : (string) $customer->id;
        $supplierId = $multipleCustomers ? (string) $supplier->id : '';
        foreach ([true, false] as $otherActive) {
            $other->update(['is_active' => $otherActive]);
            $fields = $this->paymentFields(route('deals.show', $deal));
            $this->assertSame($customerId, $fields->getAttribute('data-customer-id') ?? '');
            $this->assertSame($supplierId, $fields->getAttribute('data-supplier-id') ?? '');
            $this->assertSame($customerId, $fields->querySelector('input[name=counterparty_id]')->getAttribute('value'));
            $this->assertSame($multipleCustomers ? '' : $customer->name, $fields->querySelector('[data-money-party-combo] [data-party-query]')->getAttribute('value'));
        }
    }

    #[TestWith([false], 'inactive customer')]
    #[TestWith([true], 'customer role was removed')]
    public function test_deal_money_form_skips_a_sole_ineligible_customer(bool $active): void
    {
        [$deal, $supplier, $customer, , $accountant] = $this->simpleContext('900.00', '1200.00');
        if ($active) {
            $customer->roles()->where('role', PartnerRole::Customer->value)->delete();
        } else {
            $customer->update(['is_active' => false]);
        }
        $this->actingAs($accountant);
        $fields = $this->paymentFields(route('deals.show', $deal));

        $this->assertSame('', $fields->getAttribute('data-customer-id') ?? '');
        $this->assertSame('', $fields->querySelector('input[name=counterparty_id]')->getAttribute('value'));
        $this->assertSame((string) $supplier->id, $fields->getAttribute('data-supplier-id'));
    }

    public function test_money_routes_enforce_all_six_roles(): void
    {
        [$deal, , $customer, $saraf, $accountant] = $this->simpleContext('1000.00', '1000.00');
        $movement = $this->movement($accountant, ['movement_type' => 'CUSTOMER_RECEIPT', 'counterparty_id' => $customer->id, 'saraf_id' => $saraf->id, 'deal_id' => $deal->id, 'amount' => '100']);
        foreach ([UserRole::GeneralManager, UserRole::Accountant] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get(route('money-movements.show', $movement))->assertOk();
            $this->post(route('money-movements.store'), [])->assertSessionHasErrors();
        }
        $admin = User::factory()->create(['role' => UserRole::SystemAdmin]);
        $this->actingAs($admin)->get(route('money-movements.show', $movement))->assertOk();
        $this->post(route('money-movements.store'), [])->assertForbidden();
        foreach ([UserRole::PurchaseSalesOfficer, UserRole::WarehouseOfficer, UserRole::TransportOfficer] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get(route('money-movements.show', $movement))->assertForbidden();
            $this->post(route('money-movements.store'), [])->assertForbidden();
        }
    }

    public function test_related_deal_picker_finds_and_restores_an_older_deal_by_its_human_details(): void
    {
        [$deal, $supplier, $customer, $saraf, $user] = $this->simpleContext('950.00', '1200.00');
        $otherParty = BusinessPartner::factory()->create();
        $draft = $this->movement($user, [
            'deal_id' => $deal->id, 'counterparty_id' => $customer->id, 'saraf_id' => $saraf->id,
        ]);
        // Put this selection beyond both the first page and the old 100-row cutoff.
        foreach (range(1, 101) as $number) {
            Deal::create(['deal_date' => '2026-09-01', 'status' => 'COMPLETED', 'reference_number' => "TEST-RECENT-{$number}"]);
        }
        Deal::create(['deal_date' => '2026-08-30', 'status' => 'DRAFT', 'reference_number' => 'ONLY-DRAFT']);
        $this->actingAs($user);
        $this->getJson(route('money-movements.deals'))->assertOk()->assertJsonCount(25, 'options')->assertJsonPath('has_more', true);
        foreach ([$deal->reference_code, $customer->name, $supplier->name, $deal->product->name] as $search) {
            $response = $this->getJson(route('money-movements.deals', ['search' => $search]))
                ->assertOk()->assertJsonPath('options.0.id', $deal->id);
            $label = $response->json('options.0.label');
            foreach ([$supplier->name, $customer->name, $deal->product->name, app(JalaliDate::class)->format($deal->deal_date), '1,000 kg', '1,200.00 USD', $deal->reference_code] as $part) {
                $this->assertStringContainsString($part, $label);
            }
        }
        $this->getJson(route('money-movements.deals', ['partner_id' => $otherParty->id, 'search' => $deal->reference_code]))
            ->assertOk()->assertJsonCount(0, 'options');
        $this->getJson(route('money-movements.deals', ['search' => 'ONLY-DRAFT']))->assertOk()->assertJsonCount(0, 'options');
        $this->get(route('money-movements.edit', $draft))->assertOk()
            ->assertSee('value="'.$deal->id.'" selected>'.$label.'</option>', false);
        $this->get(route('money-movements.show', $draft))->assertOk()->assertSee($label);
    }

    public function test_deal_detail_money_form_preserves_the_explicit_unallocated_choice(): void
    {
        [$deal, , $customer, $saraf, $accountant] = $this->simpleContext('900.00', '1200.00');
        $this->actingAs($accountant)->get(route('deals.show', $deal))->assertOk()
            ->assertSee('value="'.$deal->id.'" selected>'.RelatedDeals::label($deal).'</option>', false);
        $this->post(route('money-movements.store'), [
            'movement_type' => 'CUSTOMER_RECEIPT', 'movement_date' => '2026-08-30', 'amount' => '20',
            'money_route' => 'saraf', 'saraf_id' => $saraf->id, 'counterparty_id' => $customer->id,
            'currency' => 'USD', 'deal_id' => '', 'return_to' => 'deal', 'confirm_now' => 1,
        ])->assertRedirect(route('money-movements.show', MoneyMovement::sole()));
        $this->assertNull(MoneyMovement::sole()->deal_id);

        $detail = route('deals.show', $deal);
        $response = $this->from($detail)->followingRedirects()->post(route('money-movements.store'), [
            'movement_type' => 'CUSTOMER_RECEIPT', 'return_to' => 'deal', 'deal_id' => '', 'amount' => 'invalid',
        ])->assertOk()->assertSee(__('common.validation_form.money'));
        $response->assertDontSee('value="'.$deal->id.'" selected>'.RelatedDeals::label($deal).'</option>', false);
    }

    public function test_related_deal_search_has_the_same_role_boundary_as_recording_money(): void
    {
        foreach (UserRole::cases() as $role) {
            $user = User::factory()->create(['role' => $role]);
            $response = $this->actingAs($user)->getJson(route('money-movements.deals'));
            $response->assertStatus(in_array($role, [UserRole::GeneralManager, UserRole::Accountant], true) ? 200 : 403);
        }
    }

    public function test_accountant_can_confirm_a_saraf_receipt_from_the_account_ui_route(): void
    {
        [$deal, , $customer, $saraf, $accountant] = $this->simpleContext('1000.00', '1000.00');
        $this->actingAs($accountant)->post(route('money-movements.store'), [
            'movement_date' => '1405/06/08', 'movement_type' => 'CUSTOMER_RECEIPT',
            'counterparty_id' => $customer->id, 'saraf_id' => $saraf->id,
            'deal_id' => $deal->id, 'amount' => '800', 'currency' => 'USD',
            'confirm_now' => '1', 'return_to' => 'accounts',
        ])->assertRedirect(route('office.accounts', ['partner_id' => $customer->id]));

        $this->assertDatabaseHas('money_movements', [
            'counterparty_id' => $customer->id, 'saraf_id' => $saraf->id,
            'amount' => '800.00', 'status' => MoneyMovementStatus::Confirmed->value,
        ]);
        $this->assertSame('200.00', app(PartnerLedgerService::class)->balanceFor($customer));
    }

    #[TestWith(['account', false], 'account with saved movement location')]
    #[TestWith(['saraf', false], 'saraf with saved movement location')]
    #[TestWith(['account', true], 'account with legacy journal location')]
    #[TestWith(['saraf', true], 'saraf with legacy journal location')]
    public function test_duplicate_journal_payment_opens_a_fresh_entry_on_the_original_route(string $route, bool $legacyReceipt): void
    {
        $user = $this->financeUser();
        $firstAccount = $this->financeAccount($user);
        $firstAccount->update(['name' => 'A cash']);
        $account = $this->financeAccount($user, Currency::Afn);
        $account->update(['name' => 'Z cash']);
        $firstSaraf = $this->party('A saraf', [PartnerRole::Saraf]);
        $saraf = $this->party('Z saraf', [PartnerRole::Saraf]);
        $customer = $this->party('Customer', [PartnerRole::Customer]);
        $accountRoute = $route === 'account';
        $context = $accountRoute
            ? ['context' => 'account', 'account_id' => $account->id]
            : ['context' => 'saraf', 'saraf_id' => $saraf->id];

        $this->actingAs($user);
        $initialForm = $this->journalMoneyForm(route('finance.journal', ['entry' => 'money', ...$context]));
        $key = $initialForm->querySelector('input[name=operation_key]')->getAttribute('value');
        $data = $this->payload([
            'counterparty_id' => $customer->id, 'money_route' => $route,
            'financial_account_id' => $accountRoute ? $account->id : null, 'saraf_id' => $accountRoute ? null : $saraf->id,
            'amount' => $accountRoute ? '7000' : '100', 'currency' => $accountRoute ? 'AFN' : 'USD',
            'exchange_rate_to_usd' => $accountRoute ? '70' : null,
            'return_to' => 'journal', 'confirm_now' => 1, 'operation_key' => $key,
        ]);
        $this->post(route('money-movements.store'), $data)->assertSessionHasNoErrors()->assertRedirect(route('finance.journal', $context));
        $saved = $legacyReceipt ? route('finance.journal', $context) : route('money-movements.show', MoneyMovement::sole());
        if ($legacyReceipt) {
            DB::table('operation_receipts')->where('operation_key', $key)->update(['location' => $saved]);
        }
        $balances = fn () => [
            app(FinancialAccountBalanceService::class)->balanceFor($firstAccount),
            app(FinancialAccountBalanceService::class)->balanceFor($account),
            app(SarafLedgerService::class)->balanceFor($firstSaraf),
            app(SarafLedgerService::class)->balanceFor($saraf),
            app(PartnerLedgerService::class)->balanceFor($customer),
        ];
        $afterPayment = $balances();
        $this->assertSame(['0.00', $accountRoute ? '7000.00' : '0.00', '0.00', $accountRoute ? '0.00' : '100.00', '-100.00'], $afterPayment);

        $this->post(route('money-movements.store'), $data)->assertRedirect($saved);
        $record = $this->get($saved)->assertOk()->assertSee(__('common.operation_saved'));
        $fresh = HTMLDocument::createFromString($record->getContent(), LIBXML_NOERROR)
            ->querySelector('[data-operation-recovery] a')->getAttribute('href');
        parse_str(parse_url($fresh, PHP_URL_QUERY), $params);
        $this->assertEquals(['entry' => 'money', ...$context], $params);
        $this->assertSame('money-entry', parse_url($fresh, PHP_URL_FRAGMENT));
        $form = $this->journalMoneyForm($fresh);
        $this->assertSame($route, $this->selectedValue($form, 'money_route'));
        $this->assertSame($accountRoute ? (string) $account->id : '', $this->selectedValue($form, 'financial_account_id'));
        $this->assertSame($accountRoute ? '' : (string) $saraf->id, $form->querySelector('input[name=saraf_id]')->getAttribute('value'));
        $this->assertSame($accountRoute ? '' : $saraf->name, $form->querySelector('[data-saraf-field] [data-party-query]')->getAttribute('value'));
        $this->assertSame($data['currency'], $this->selectedValue($form, 'currency'));
        foreach (['amount', 'counterparty_id', 'exchange_rate_to_usd', 'reference'] as $field) {
            $this->assertSame('', $form->querySelector("input[name=$field]")->getAttribute('value'));
        }
        $freshKey = $form->querySelector('input[name=operation_key]')->getAttribute('value');
        $this->assertTrue(Str::isUuid($freshKey));
        $this->assertNotSame($key, $freshKey);
        $this->assertDatabaseMissing('operation_receipts', ['operation_key' => $freshKey]);

        // A consumed key may be replayed after the operator changes both the route and its ID.
        $this->post(route('money-movements.store'), array_replace($data, [
            'money_route' => $accountRoute ? 'saraf' : 'account',
            'financial_account_id' => $accountRoute ? null : $firstAccount->id,
            'saraf_id' => $accountRoute ? $firstSaraf->id : null,
        ]))->assertRedirect($saved)->assertSessionHasErrors('operation_key');
        $this->get($saved)->assertOk()->assertSee(__('common.operation_conflict'))->assertSee($fresh);

        // The same link must not substitute the first available account or saraf if eligibility changes.
        if ($accountRoute) {
            $account->update(['is_active' => false]);
        } else {
            $saraf->roles()->where('role', PartnerRole::Saraf->value)->delete();
        }
        $form = $this->journalMoneyForm($fresh);
        $filter = $form->ownerDocument->querySelector('[data-journal-context-form]');
        $this->assertSame('', $this->selectedValue($filter, $accountRoute ? 'account_id' : 'saraf_id'));
        $this->assertSame($route, $this->selectedValue($form, 'money_route'));
        $this->assertSame('', $this->selectedValue($form, 'financial_account_id'));
        $this->assertSame('', $form->querySelector('input[name=saraf_id]')->getAttribute('value'));
        $this->assertSame('', $form->querySelector('[data-saraf-field] [data-party-query]')->getAttribute('value'));
        $this->assertSame($afterPayment, $balances());
        $this->assertDatabaseCount('money_movements', 1);
        $this->assertSame(MoneyMovementStatus::Confirmed, MoneyMovement::sole()->status);
        $this->assertDatabaseCount('operation_receipts', 1);
        $this->assertDatabaseHas('operation_receipts', ['user_id' => $user->id, 'operation_key' => $key]);
    }

    #[TestWith(['deal'])]
    #[TestWith(['accounts'])]
    public function test_duplicate_payment_from_a_deal_or_partner_profile_keeps_its_entry_page(string $origin): void
    {
        [$deal, , $customer, $saraf, $user] = $this->simpleContext('900.00', '1200.00');
        $data = $this->payload([
            'deal_id' => $deal->id, 'counterparty_id' => $customer->id, 'saraf_id' => $saraf->id,
            'return_to' => $origin, 'confirm_now' => 1, 'operation_key' => (string) Str::uuid(),
        ]);
        $this->actingAs($user)->post(route('money-movements.store'), $data)->assertSessionHasNoErrors();
        $saved = route('money-movements.show', MoneyMovement::sole());
        $this->post(route('money-movements.store'), $data)->assertRedirect($saved);
        $expected = $origin === 'deal'
            ? route('deals.show', ['deal' => $deal->id, 'entry' => 'money'])
            : route('office.accounts', ['partner_id' => $customer->id, 'entry' => 'money']);
        $this->get($saved)->assertOk()->assertSee($expected.'#money-entry');
        $this->assertDatabaseCount('money_movements', 1);
    }

    public function test_money_route_rejects_both_or_neither_saraf_and_account(): void
    {
        [$deal, , $customer, $saraf, $user] = $this->simpleContext('1000.00', '1000.00');
        $account = $this->financeAccount($user);
        foreach ([
            ['saraf_id' => null, 'financial_account_id' => null],
            ['saraf_id' => $saraf->id, 'financial_account_id' => $account->id],
        ] as $route) {
            try {
                $this->movement($user, [
                    'movement_type' => 'CUSTOMER_RECEIPT', 'counterparty_id' => $customer->id,
                    'deal_id' => $deal->id, 'amount' => '100', ...$route,
                ]);
                $this->fail('Invalid money route should fail.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('money_route', $exception->errors());
                $this->assertDatabaseCount('money_movements', 0);
            }
        }
    }

    private function journalMoneyForm(string $url): Element
    {
        $page = $this->get($url)->assertOk();
        $form = HTMLDocument::createFromString($page->getContent(), LIBXML_NOERROR)->querySelector('#money-entry[open] form');
        $this->assertInstanceOf(Element::class, $form);

        return $form;
    }

    private function selectedValue(Element $form, string $name): string
    {
        $select = $form->querySelector("select[name=$name]");

        return ($select->querySelector('option[selected]') ?? $select->querySelector('option'))->getAttribute('value');
    }

    private function paymentFields(string $url): Element
    {
        $html = $this->get($url)->assertOk()->getContent();
        $fields = HTMLDocument::createFromString($html, LIBXML_NOERROR)->querySelector('[data-money-movement-fields]');
        $this->assertInstanceOf(Element::class, $fields);

        return $fields;
    }

    /** @return array{Deal, BusinessPartner, BusinessPartner, BusinessPartner, User} */
    private function canonicalContext(): array
    {
        return $this->dealContext('19355.000', '1200', '1310');
    }

    /** @return array{Deal, BusinessPartner, BusinessPartner, BusinessPartner, User} */
    private function simpleContext(string $purchase, string $sale): array
    {
        return $this->dealContext('1000.000', $purchase, $sale);
    }

    /** @return array{Deal, BusinessPartner, BusinessPartner, BusinessPartner, User} */
    private function dealContext(string $quantity, string $purchaseRate, string $saleRate, bool $historicalDraft = false): array
    {
        $user = User::factory()->create(['role' => UserRole::Accountant]);
        $supplier = $this->party('Supplier A', [PartnerRole::Supplier]);
        $customer = $this->party('Customer B', [PartnerRole::Customer]);
        $saraf = $this->party('Saraf C', [PartnerRole::Saraf]);
        $data = [
            'deal_date' => '2026-08-30', 'product_id' => Product::factory()->create()->id,
            'supplier_id' => $supplier->id, 'source_quantity_kg' => $quantity, 'source_rate_per_ton' => $purchaseRate,
            'customer_id' => $customer->id, 'final_quantity_kg' => $quantity, 'destination_rate_per_ton' => $saleRate,
        ];
        $deal = $historicalDraft ? HistoricalDeal::draft($data, $user->id)
            : app(DealService::class)->create($data + ['submit_action' => 'complete'], $user->id);

        return [$deal, $supplier, $customer, $saraf, $user];
    }

    private function party(string $name, array $roles): BusinessPartner
    {
        $party = BusinessPartner::factory()->create(['name' => $name]);
        $party->roles()->createMany(array_map(fn (PartnerRole $role) => ['role' => $role], $roles));

        return $party->load('roles');
    }

    private function movement(User $user, array $overrides, bool $confirm = false): MoneyMovement
    {
        $service = app(MoneyMovementService::class);
        $movement = $service->create($this->payload($overrides), $user->id);
        if ($confirm) {
            $service->confirm($movement, $user->id);
        }

        return $movement->refresh();
    }

    private function payload(array $overrides): array
    {
        return [...[
            'movement_date' => '2026-08-30', 'movement_type' => MoneyMovementType::CustomerReceipt->value,
            'counterparty_id' => null, 'saraf_id' => null, 'deal_id' => null, 'financial_account_id' => null,
            'amount' => '100', 'currency' => Currency::Usd->value, 'exchange_rate_to_usd' => null,
            'reference' => 'TEST-MM', 'notes' => 'حرکت پول آزمایشی',
        ], ...$overrides];
    }
}
