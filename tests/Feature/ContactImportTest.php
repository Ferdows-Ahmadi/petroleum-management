<?php

namespace Tests\Feature;

use App\Enums\FinancialTransactionType;
use App\Enums\PartnerRole;
use App\Models\BusinessPartner;
use App\Services\ContactImportService;
use App\Services\PartnerLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Concerns\CreatesFinanceFixtures;
use Tests\TestCase;

class ContactImportTest extends TestCase
{
    use CreatesFinanceFixtures, RefreshDatabase;

    public function test_preview_apply_and_reimport_preserve_the_existing_ledger_and_spelling(): void
    {
        $existing = BusinessPartner::factory()->create(['name' => 'استودیو آزمایشی نارنج', 'phone' => '000-000', 'is_active' => true]);
        $existing->roles()->create(['role' => PartnerRole::Supplier]);
        $actor = $this->financeUser();
        $opening = $existing->openingBalance()->create([
            'effective_date' => '2026-01-01', 'balance_type' => 'RECEIVABLE',
            'amount_usd' => '45.89', 'created_by' => $actor->id,
        ]);
        $historical = $this->financeTransaction(
            $this->paymentPayload($this->financeAccount($actor, opening: '100'), $existing, '2.13'),
            FinancialTransactionType::PaymentPaid, $actor, true
        );
        $before = app(PartnerLedgerService::class)->balanceFor($existing);
        $source = "\xEF\xBB\xBF# fictional\r\nSUPPLIER | استودیو آزمایشی نارنج\r\nCUSTOMER | گلخانهٔ نیلی\r\nCUSTOMER | گلخانهٔ نیلی\r\nSARAF | صرافی نمایشی شفق\r\nPARTNER | کارگاه بی‌نقش";
        $import = app(ContactImportService::class);
        $preview = $import->preview($source);
        $this->assertSame(['source_rows' => 5, 'groups' => 4, 'create' => 2, 'reuse' => 1, 'review' => 1], $preview['counts']);
        $this->assertSame([3, 4], $preview['groups'][1]['lines']);
        $this->assertDatabaseCount('business_partners', 1);

        $applied = $import->apply($source, $preview['fingerprint']);
        $this->assertSame('applied', $applied['mode']);
        $this->assertSame($existing->id, $applied['groups'][0]['partner_id']);
        foreach ([1 => PartnerRole::Customer, 2 => PartnerRole::Saraf] as $index => $role) {
            $created = BusinessPartner::findOrFail($applied['groups'][$index]['partner_id']);
            $this->assertSame($preview['groups'][$index]['name'], $created->name);
            $this->assertSame($role, $created->roles()->sole()->role);
        }
        $repeat = $import->preview($source);
        $this->assertSame(0, $repeat['counts']['create']);
        $this->assertSame(3, $repeat['counts']['reuse']);
        $import->apply($source, $repeat['fingerprint']);
        $this->assertDatabaseCount('business_partners', 3);
        $this->assertDatabaseCount('business_partner_roles', 3);
        $this->assertDatabaseCount('partner_opening_balances', 1);
        $this->assertSame('45.89', $opening->refresh()->amount_usd);
        $this->assertSame($existing->id, $opening->business_partner_id);
        $this->assertSame('000-000', $existing->refresh()->phone);
        $this->assertDatabaseCount('money_movements', 0);
        $this->assertDatabaseCount('financial_transactions', 1);
        $this->assertSame($existing->id, $historical->refresh()->business_partner_id);
        $this->assertSame($before, app(PartnerLedgerService::class)->balanceFor($existing));
    }

    public function test_conflicts_and_aliases_across_the_source_and_existing_directory_are_held(): void
    {
        $supplier = BusinessPartner::factory()->create(['name' => 'نمونه کوه زرد', 'is_active' => true]);
        $supplier->roles()->create(['role' => PartnerRole::Supplier]);
        $inactive = BusinessPartner::factory()->create(['name' => 'نساجی ساختگی لعل', 'is_active' => false]);
        $deleted = BusinessPartner::factory()->create(['name' => 'بایگانی بنفش']);
        $deleted->delete();
        $source = implode("\n", [
            'CUSTOMER | نمونه کوه زرد', 'SUPPLIER | نساجی ساختگی لعل', 'CUSTOMER | بایگانی بنفش',
            'SARAF | صرافی ماه سبز', 'PARTNER | ماه سبز صراف',
            'CUSTOMER | تمثیل روشن', 'SUPPLIER | تمثیل روشن', 'CUSTOMER | نمونه كوه زرد',
        ]);
        $import = app(ContactImportService::class);
        $report = $import->preview($source);
        $this->assertSame(7, $report['counts']['review']);
        $this->assertSame(0, $report['counts']['create']);
        $this->assertContains(5, $report['groups'][3]['candidate_lines']);
        $this->assertContains(4, $report['groups'][4]['candidate_lines']);
        $this->assertContains($supplier->id, $report['groups'][6]['candidate_ids']);
        $this->assertSame('inactive', $report['candidates'][$inactive->id]['state']);
        $this->assertSame('deleted', $report['candidates'][$deleted->id]['state']);
        $import->apply($source, $report['fingerprint']);
        $this->assertDatabaseCount('business_partners', 3);
        $this->assertDatabaseCount('business_partner_roles', 1);
    }

    public function test_multiple_exact_matches_are_never_chosen_by_database_order(): void
    {
        $partners = BusinessPartner::factory(2)->create(['name' => 'مثال هم‌نام', 'is_active' => true]);
        foreach ($partners as $partner) {
            $partner->roles()->create(['role' => PartnerRole::Customer]);
        }
        $group = app(ContactImportService::class)->preview('CUSTOMER | مثال هم‌نام')['groups'][0];
        $this->assertSame('review', $group['action']);
        $this->assertEqualsCanonicalizing($partners->modelKeys(), $group['candidate_ids']);
    }

    public function test_stale_reviews_abort_the_entire_apply_before_creating_any_contacts(): void
    {
        $import = app(ContactImportService::class);
        $source = "CUSTOMER | کارگاه مثالی فیروزه\nSUPPLIER | دکان کاغذی لاله";
        $initial = $import->preview($source);
        $existing = BusinessPartner::factory()->create(['name' => 'دکان کاغذی لاله', 'is_active' => true]);
        $existing->roles()->create(['role' => PartnerRole::Customer]);
        foreach ([$initial['fingerprint'], $import->preview($source."\n# edited")['fingerprint']] as $stale) {
            try {
                $import->apply($source, $stale);
                $this->fail('Expected a stale preview to abort.');
            } catch (InvalidArgumentException) {
                $this->assertDatabaseCount('business_partners', 1);
                $this->assertDatabaseCount('business_partner_roles', 1);
            }
        }
    }

    #[DataProvider('invalidSources')]
    public function test_invalid_input_rejects_the_whole_file(string $invalid): void
    {
        $source = "CUSTOMER | کارگاه خیالی\n".$invalid;
        $this->expectException(InvalidArgumentException::class);
        try {
            app(ContactImportService::class)->preview($source);
        } finally {
            $this->assertDatabaseCount('business_partners', 0);
        }
    }

    public static function invalidSources(): array
    {
        return [['OTHER | Test'], ['SARAF | '], ['CUSTOMER | a | b'], ["SUPPLIER | \xff"], ['CUSTOMER | '.str_repeat('م', 151)]];
    }

    public function test_apply_rolls_back_contacts_and_roles_if_a_later_create_fails(): void
    {
        $source = "CUSTOMER | Fictional Violet\nSUPPLIER | Imaginary Amber";
        $import = app(ContactImportService::class);
        $preview = $import->preview($source);
        BusinessPartner::creating(function (BusinessPartner $partner) {
            if ($partner->name === 'Imaginary Amber') {
                throw new RuntimeException('Simulated interruption');
            }
        });
        $this->expectExceptionMessage('Simulated interruption');
        try {
            $import->apply($source, $preview['fingerprint']);
        } finally {
            $this->assertDatabaseCount('business_partners', 0);
            $this->assertDatabaseCount('business_partner_roles', 0);
        }
    }
}
