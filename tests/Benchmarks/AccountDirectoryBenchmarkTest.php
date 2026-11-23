<?php

namespace Tests\Benchmarks;

use App\Enums\PartnerOpeningBalanceType;
use App\Enums\PartnerRole;
use App\Enums\UserRole;
use App\Models\BusinessPartner;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

// Run explicitly with php vendor/bin/phpunit -c phpunit.xml tests/Benchmarks/AccountDirectoryBenchmarkTest.php
class AccountDirectoryBenchmarkTest extends TestCase
{
    #[TestWith([50, false])]
    #[TestWith([250, false])]
    #[TestWith([500, false])]
    #[TestWith([50, true])]
    #[TestWith([250, true])]
    #[TestWith([500, true])]
    public function test_accountant_directory_render(int $size, bool $withOpening): void
    {
        config([
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'database.default' => 'benchmark',
            'database.connections.benchmark' => ['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true],
            'session.driver' => 'array',
            'cache.default' => 'array',
        ]);
        $this->withoutVite();
        Artisan::call('migrate', ['--database' => 'benchmark', '--force' => true]);
        $accountant = User::factory()->create(['role' => UserRole::Accountant]);
        $this->actingAs($accountant);
        for ($i = 1; $i <= $size; $i++) {
            $partner = BusinessPartner::create(['name' => sprintf('Benchmark contact %04d', $i), 'is_active' => true]);
            $partner->roles()->create(['role' => PartnerRole::Customer]);
            if ($withOpening) {
                $partner->openingBalance()->create([
                    'effective_date' => '2026-01-01', 'amount_usd' => '100.01', 'created_by' => $accountant->id,
                    'balance_type' => $i % 2 === 0 ? PartnerOpeningBalanceType::Receivable : PartnerOpeningBalanceType::Payable,
                ]);
            }
        }

        $count = 0;
        DB::listen(function () use (&$count): void {
            $count++;
        });
        $url = route('office.accounts');
        $durations = [];
        $counts = [];
        for ($run = 0; $run <= 5; $run++) {
            $count = 0;
            $start = hrtime(true);
            $response = $this->get($url);
            $elapsedMs = (hrtime(true) - $start) / 1_000_000;
            $queries = $count;
            $response->assertOk();
            $this->assertCount($size, $response->viewData('partnerRows'));
            if ($run > 0) {
                $durations[] = $elapsedMs;
                $counts[] = $queries;
            }
        }
        $this->assertCount(1, array_unique($counts));
        sort($durations, SORT_NUMERIC);
        fprintf(STDERR, "%s | %d contacts | %d SQL | %.1f ms median\n",
            $withOpening ? 'One opening per contact' : 'Empty ledgers', $size, $counts[0], $durations[2]);
    }
}
