<?php

use App\Enums\PartnerRole;
use App\Http\Middleware\PreventDuplicateSubmission;
use App\Models\Deal;
use App\Models\User;
use App\Services\DealService;
use App\Services\DealSettlementService;
use App\Services\InlinePartnerService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== 'petroleum_management_test') {
    throw new RuntimeException('Concurrency workers require the disposable MySQL test schema.');
}

[$script, $mode, $barrier, $index, $json] = $argv;
$context = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

try {
    $result = DB::transaction(function () use ($mode, $barrier, $index, $context): array {
        // Both transactions read before either takes the contested lock. This
        // reproduces the stale snapshot that previously allowed stock overselling.
        DB::table('inventory_movements')->count();
        file_put_contents($barrier.'-'.$index, 'ready');
        $deadline = microtime(true) + 10;
        while (! file_exists($barrier.'-'.(1 - (int) $index))) {
            if (microtime(true) > $deadline) {
                throw new RuntimeException('The second concurrency worker did not reach the barrier.');
            }
            usleep(10000);
        }

        if ($mode === 'party') {
            $party = app(InlinePartnerService::class)->resolve(null, $context['name'], null, PartnerRole::Customer);

            return ['ok' => true, 'party' => $party->id];
        }
        if ($mode === 'replay') {
            $user = User::findOrFail($context['user']);
            $request = Request::create('/deals', 'POST', ['operation_key' => $context['key'], 'scenario' => 'concurrent-audit']);
            $request->setUserResolver(fn () => $user);
            $response = app(PreventDuplicateSubmission::class)->handle($request, function () use ($context) {
                $deal = app(DealService::class)->create($context['data'], $context['user']);

                return redirect('/deals/'.$deal->id);
            });

            return ['ok' => true, 'location' => $response->headers->get('Location')];
        }
        $deal = Deal::findOrFail($context['deals'][(int) $index]);
        app(DealSettlementService::class)->confirm($deal, $context['user']);

        return ['ok' => true, 'deal' => $deal->id];
    });
    echo json_encode($result, JSON_THROW_ON_ERROR);
} catch (ValidationException $error) {
    echo json_encode(['ok' => false, 'validation' => array_keys($error->errors())], JSON_THROW_ON_ERROR);
}
