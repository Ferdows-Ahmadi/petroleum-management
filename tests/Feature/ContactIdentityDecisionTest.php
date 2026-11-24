<?php

namespace Tests\Feature;

use App\Enums\PartnerRole;
use App\Models\BusinessPartner;
use App\Services\ContactImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use InvalidArgumentException;
use Tests\TestCase;

class ContactIdentityDecisionTest extends TestCase
{
    use RefreshDatabase;

    private const SOURCE = "SARAF | صرافی آزمایشی سرو\nPARTNER | آزمایشی سرو صراف\nSUPPLIER | کارگاه تمثیلی نیلی\nPARTNER | كارگاه تمثیلی نیلی\nSUPPLIER | کتابخانه خیالی زرین\nPARTNER | کتابخانه خیالی زرین";

    private function decisions(): array
    {
        return [
            'source_sha256' => hash('sha256', self::SOURCE),
            'decisions' => [
                ['lines' => [1, 2], 'identity' => 'same', 'canonical_line' => 1, 'role' => 'SARAF'],
                ['lines' => [3, 4], 'identity' => 'distinct'],
                ['lines' => [5, 6], 'identity' => 'same', 'canonical_line' => 5, 'role' => 'SUPPLIER'],
            ],
        ];
    }

    public function test_reviewed_source_identities_apply_once_and_preserve_every_source_row_and_existing_records(): void
    {
        $sourcePath = tempnam(sys_get_temp_dir(), 'fictional-source-');
        $decisionPath = tempnam(sys_get_temp_dir(), 'fictional-decisions-');
        try {
            file_put_contents($sourcePath, self::SOURCE);
            file_put_contents($decisionPath, "\xEF\xBB\xBF".json_encode($this->decisions(), JSON_THROW_ON_ERROR));
            $args = ['source' => $sourcePath, '--include-general' => true, '--json' => true];
            $this->assertSame(0, Artisan::call('contacts:import', $args));
            $unresolved = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame(5, $unresolved['counts']['review']);

            $args['--decisions'] = $decisionPath;
            $this->assertSame(0, Artisan::call('contacts:import', $args));
            $resolved = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame(['source_rows' => 6, 'groups' => 4, 'create' => 4, 'reuse' => 0, 'review' => 0], $resolved['counts']);
            $entries = array_merge(...array_column($resolved['groups'], 'source_entries'));
            $this->assertSame(range(1, 6), array_column($entries, 'line'));
            $this->assertSame(explode("\n", self::SOURCE), array_map(fn ($entry) => $entry['category'].' | '.$entry['name'], $entries));
            $this->assertSame(1, Artisan::call('contacts:import', $args + ['--apply' => $unresolved['fingerprint']]));
            $this->assertDatabaseEmpty('business_partners');

            $this->assertSame(0, Artisan::call('contacts:import', $args + ['--apply' => $resolved['fingerprint']]));
            $applied = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
            $ids = array_column($applied['groups'], 'partner_id');
            $this->assertCount(4, array_unique($ids));
            foreach ([PartnerRole::Saraf, PartnerRole::Supplier, PartnerRole::Partner, PartnerRole::Supplier] as $i => $role) {
                $person = BusinessPartner::findOrFail($ids[$i]);
                $this->assertSame($applied['groups'][$i]['name'], $person->name);
                $this->assertSame($role, $person->roles()->sole()->role);
            }
            $this->assertSame(0, Artisan::call('contacts:import', $args));
            $repeat = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame(0, $repeat['counts']['create']);
            $this->assertSame(4, $repeat['counts']['reuse']);
            $this->assertSame($ids, array_column($repeat['groups'], 'partner_id'));
            $this->assertSame(0, Artisan::call('contacts:import', $args + ['--apply' => $repeat['fingerprint']]));
            $this->assertDatabaseCount('business_partner_roles', 4);
            $this->assertDatabaseEmpty('partner_opening_balances');
            $this->assertDatabaseEmpty('financial_transactions');
        } finally {
            unlink($sourcePath);
            unlink($decisionPath);
        }
    }

    public function test_decisions_cannot_merge_an_existing_alias_retired_person_or_role_conflict(): void
    {
        $alias = BusinessPartner::factory()->create(['name' => 'آزمایشی سرو صراف', 'is_active' => true]);
        $alias->roles()->create(['role' => PartnerRole::Saraf]);
        $retired = BusinessPartner::factory()->create(['name' => 'کارگاه تمثیلی نیلی', 'is_active' => false]);
        $retired->roles()->create(['role' => PartnerRole::Supplier]);
        $other = BusinessPartner::factory()->create(['name' => 'کتابخانه خیالی زرین', 'is_active' => true]);
        $other->roles()->create(['role' => PartnerRole::Customer]);
        $import = app(ContactImportService::class);
        $report = $import->preview(self::SOURCE, true, $this->decisions());
        $this->assertContains('possible_existing_alias', $report['groups'][0]['reasons']);
        $this->assertContains('inactive_or_deleted', $report['groups'][1]['reasons']);
        $this->assertContains('existing_role_conflict', $report['groups'][3]['reasons']);
        $this->assertSame(3, $report['counts']['review']);
        $this->assertDatabaseCount('business_partners', 3);
    }

    public function test_changing_a_reviewed_identity_or_database_candidate_expires_the_original_approval(): void
    {
        $import = app(ContactImportService::class);
        $decisions = $this->decisions();
        $fingerprint = $import->preview(self::SOURCE, true, $decisions)['fingerprint'];
        $different = $decisions;
        $different['decisions'][0] = ['lines' => [1, 2], 'identity' => 'distinct'];
        try {
            $import->apply(self::SOURCE, $fingerprint, true, $different);
            $this->fail('Expected changed owner decisions to invalidate approval.');
        } catch (InvalidArgumentException) {
            $this->assertDatabaseEmpty('business_partners');
        }
        $existing = BusinessPartner::factory()->create(['name' => 'آزمایشی سرو صراف', 'is_active' => true]);
        $existing->roles()->create(['role' => PartnerRole::Saraf]);
        $this->expectException(InvalidArgumentException::class);
        try {
            $import->apply(self::SOURCE, $fingerprint, true, $decisions);
        } finally {
            $this->assertSame($existing->id, BusinessPartner::sole()->id);
        }
    }

    public function test_stale_overlapping_or_unrelated_line_decisions_are_rejected_before_writes(): void
    {
        $original = $this->decisions();
        $variants = [['source_sha256' => str_repeat('a', 64)] + $original];
        foreach ([
            ['lines' => [1, 5], 'identity' => 'distinct'],
            ['lines' => [5, 6], 'identity' => 'distinct'],
            ['lines' => [1, 2], 'identity' => 'same', 'canonical_line' => 3, 'role' => 'SARAF'],
            ['lines' => [1, 2], 'identity' => 'same', 'canonical_line' => 1, 'role' => 'CUSTOMER'],
            ['lines' => [1, 90], 'identity' => 'distinct'],
        ] as $invalid) {
            $variants[] = ['decisions' => [$invalid]] + $original;
        }
        $variants[] = ['decisions' => [...$original['decisions'], $original['decisions'][0]]] + $original;
        foreach ($variants as $decisions) {
            try {
                app(ContactImportService::class)->preview(self::SOURCE, true, $decisions);
                $this->fail('Expected an invalid decision to be rejected.');
            } catch (InvalidArgumentException) {
                $this->assertDatabaseEmpty('business_partners');
            }
        }
    }
}
