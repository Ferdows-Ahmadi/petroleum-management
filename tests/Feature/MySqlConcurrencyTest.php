<?php

namespace Tests\Feature;

use App\Enums\PartnerRole;
use App\Enums\UserRole;
use App\Models\BusinessPartner;
use App\Models\Deal;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\DealService;
use App\Services\DealSettlementService;
use App\Services\InventoryBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class MySqlConcurrencyTest extends TestCase
{
    private bool $ownsAuditSchema = false;

    protected function setUp(): void
    {
        parent::setUp();
        if (config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== 'petroleum_management_test') {
            $this->markTestSkipped('Requires the explicitly disposable MySQL test schema.');
        }
        $this->ownsAuditSchema = true;
        $this->artisan('migrate:fresh')->assertSuccessful();
    }

    protected function tearDown(): void
    {
        if ($this->ownsAuditSchema) {
            $this->artisan('migrate:fresh')->assertSuccessful();
            RefreshDatabaseState::$migrated = true;
        }
        parent::tearDown();
    }

    public function test_contending_transactions_preserve_stock_party_identity_and_event_uniqueness(): void
    {
        $user = User::factory()->create(['role' => UserRole::GeneralManager]);
        $product = Product::factory()->create();
        $supplier = BusinessPartner::factory()->create();
        $supplier->roles()->create(['role' => PartnerRole::Supplier]);
        $customer = BusinessPartner::factory()->create();
        $customer->roles()->create(['role' => PartnerRole::Customer]);
        $warehouse = Warehouse::factory()->create();
        $service = app(DealService::class);
        $data = ['deal_date' => now()->toDateString(), 'product_id' => $product->id, 'submit_action' => 'draft'];

        $name = 'Concurrent audit '.Str::uuid();
        $parties = $this->workers('party', ['name' => $name]);
        $this->assertTrue($parties[0]['ok'] && $parties[1]['ok']);
        $this->assertSame($parties[0]['party'], $parties[1]['party']);
        $this->assertSame(1, BusinessPartner::where('name', $name)->count());

        $replay = $this->workers('replay', ['user' => $user->id, 'key' => (string) Str::uuid(), 'data' => $data]);
        $this->assertTrue($replay[0]['ok'] && $replay[1]['ok']);
        $this->assertSame($replay[0]['location'], $replay[1]['location']);
        $this->assertDatabaseCount('deals', 1);
        $this->assertDatabaseCount('operation_receipts', 1);

        $input = $service->create($data, $user->id);
        $service->addSource($input, ['source_type' => 'SUPPLIER', 'supplier_id' => $supplier->id, 'quantity_kg' => '1000', 'rate_per_ton' => '1000']);
        $service->addDestination($input, ['destination_type' => 'STORAGE', 'warehouse_id' => $warehouse->id, 'final_quantity_kg' => '1000']);
        $service->complete($input, $user->id);
        app(DealSettlementService::class)->confirm($input, $user->id);
        $outputs = [];
        for ($index = 0; $index < 2; $index++) {
            $deal = $service->create($data, $user->id);
            $service->addSource($deal, ['source_type' => 'STORAGE', 'warehouse_id' => $warehouse->id, 'quantity_kg' => '750']);
            $service->addDestination($deal, ['destination_type' => 'CUSTOMER', 'customer_id' => $customer->id, 'final_quantity_kg' => '750', 'rate_per_ton' => '1100']);
            $service->complete($deal, $user->id);
            $outputs[] = $deal->id;
        }
        $stock = $this->workers('stock', ['user' => $user->id, 'deals' => $outputs]);
        $this->assertSame(1, collect($stock)->where('ok', true)->count());
        $this->assertSame(['warehouse_id'], collect($stock)->firstWhere('ok', false)['validation']);
        $this->assertSame('250.000', app(InventoryBalanceService::class)->balanceFor($warehouse->id, $product->id));
        $this->assertSame(1, Deal::whereIn('id', $outputs)->whereHas('latestConfirmedSettlement')->count());
        $this->assertSame('READ-COMMITTED', DB::selectOne('SELECT @@transaction_isolation AS isolation')->isolation);
    }

    private function workers(string $mode, array $context): array
    {
        $barrier = base_path('storage/framework/testing/barrier-'.Str::uuid());
        if (! is_dir(dirname($barrier))) {
            mkdir(dirname($barrier), 0700, true);
        }
        $workers = [];
        try {
            for ($index = 0; $index < 2; $index++) {
                $worker = new Process([PHP_BINARY, base_path('tests/Support/mysql-concurrency-worker.php'), $mode, $barrier, (string) $index, json_encode($context, JSON_THROW_ON_ERROR)], base_path(), ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => 'petroleum_management_test', 'DB_URL' => '']);
                $worker->setTimeout(20);
                $worker->start();
                $workers[] = $worker;
            }

            return array_map(function (Process $worker): array {
                $worker->wait();
                $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput());

                return json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            }, $workers);
        } finally {
            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop();
                }
            }
            foreach ([0, 1] as $index) {
                if (file_exists($barrier.'-'.$index)) {
                    unlink($barrier.'-'.$index);
                }
            }
        }
    }
}
