<?php

namespace App\Http\Middleware;

use App\Models\MoneyMovement;
use App\Models\User;
use App\Support\JournalContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PreventDuplicateSubmission
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->input('operation_key');
        // Older API clients remain supported. Every current event-entry form supplies a token.
        if ($key === null) {
            return $next($request);
        }
        if (! is_string($key) || ! Str::isUuid($key)) {
            return $this->reject($request, __('common.operation_invalid'));
        }
        $payload = $request->except(['_token', 'operation_key']);
        foreach ($request->allFiles() as $field => $file) {
            $payload[$field] = is_array($file) ? array_map(fn ($f) => hash_file('sha256', $f->getRealPath()), $file) : hash_file('sha256', $file->getRealPath());
        }
        ksort($payload);
        $hash = hash('sha256', $request->method().'|'.$request->path().'|'.json_encode($payload));

        return DB::transaction(function () use ($request, $next, $key, $hash): Response {
            User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $receipt = DB::table('operation_receipts')->where('user_id', $request->user()->id)->where('operation_key', $key)->first();
            if ($receipt) {
                $savedPage = $this->receiptDestination($request, $receipt->location);
                $destination = $savedPage ? url($savedPage->getRequestUri()) : null;
                $sameSubmission = hash_equals($receipt->request_hash, $hash);
                $fresh = $this->freshForm($request, $sameSubmission ? $request : $savedPage);
                if (! $sameSubmission) {
                    return $this->reject($request, __('common.operation_conflict'), $destination, $fresh);
                }

                return redirect($destination ?? $fresh)->with('operation_recovery', [
                    'message' => __('common.operation_saved'),
                    'fresh' => $fresh,
                ]);
            }
            $response = $next($request);
            if ($response->isRedirect() && ($response->exception ?? null) === null) {
                DB::table('operation_receipts')->insert([
                    'user_id' => $request->user()->id, 'operation_key' => $key, 'request_hash' => $hash,
                    'location' => $request->attributes->get('operation_record_location') ?? $response->headers->get('Location'),
                    'created_at' => now(),
                ]);
            }

            return $response;
        });
    }

    private function reject(Request $request, string $message, ?string $destination = null, ?string $fresh = null): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'errors' => ['operation_key' => [$message]]], 422);
        }

        if ($request->is('api/*') || ! $request->hasSession()) {
            throw ValidationException::withMessages(['operation_key' => $message]);
        }

        $fresh ??= $this->freshForm($request);

        return redirect($destination ?? $fresh)
            ->withErrors(['operation_key' => $message])
            ->withInput([])
            ->with('operation_recovery', ['message' => $message, 'fresh' => $fresh, 'error' => true]);
    }

    private function freshForm(Request $request, ?Request $contextSource = null): string
    {
        return match ($request->route()?->getName()) {
            'deals.store' => route('deals.index').'#new-deal',
            'expenses.store' => route('expenses.create'),
            'office.finance.expenses.store' => route('finance.journal', ['entry' => 'expense']).'#expense-entry',
            'money-movements.store', 'office.accounts.money.store' => $this->freshMoneyForm($request, $contextSource),
            default => route('dashboard'),
        };
    }

    private function freshMoneyForm(Request $request, ?Request $contextSource): string
    {
        $dealId = $request->integer('deal_id');
        if ($request->input('return_to') === 'deal' && $dealId > 0) {
            return route('deals.show', ['deal' => $dealId, 'entry' => 'money']).'#money-entry';
        }

        $partnerId = $request->integer('counterparty_id') ?: $request->integer('saraf_id');
        if ($request->input('return_to') !== 'journal' && $partnerId > 0) {
            return route('office.accounts', ['partner_id' => $partnerId, 'entry' => 'money']).'#money-entry';
        }

        $context = [];
        if ($request->input('return_to') === 'journal' && $contextSource) {
            if ($contextSource === $request) {
                // The receipt hash verified these exact payment fields.
                $context = JournalContext::forPayment($request->integer('financial_account_id'), $request->integer('saraf_id'));
            } elseif ($contextSource->routeIs('money-movements.show')) {
                $movement = MoneyMovement::find($contextSource->route('moneyMovement'));
                $context = JournalContext::forPayment($movement?->financial_account_id, $movement?->saraf_id);
            } elseif ($contextSource->routeIs('finance.journal')) {
                // Older receipts stored the successful journal redirect itself.
                $context = match ($contextSource->query('context')) {
                    'account' => JournalContext::forPayment($contextSource->integer('account_id'), null),
                    'saraf' => JournalContext::forPayment(null, $contextSource->integer('saraf_id')),
                    default => [],
                };
            }
        }

        return route('finance.journal', ['entry' => 'money', ...$context]).'#money-entry';
    }

    private function receiptDestination(Request $request, string $location): ?Request
    {
        $path = parse_url($location, PHP_URL_PATH);
        if (! is_string($path) || ! str_starts_with($path, '/') || str_starts_with($path, '//') || str_contains($path, '\\')) {
            return null;
        }
        if (parse_url($location, PHP_URL_HOST) && ($baseUrl = $request->getBaseUrl()) !== '' && str_starts_with($path, $baseUrl.'/')) {
            $path = substr($path, strlen($baseUrl));
        }

        $query = parse_url($location, PHP_URL_QUERY);
        $page = Request::create($path.(is_string($query) && $query !== '' ? '?'.$query : ''));
        try {
            $route = Route::getRoutes()->match($page);
        } catch (HttpException) {
            return null;
        }

        // A key may have been reused across forms or after a user's role changed.
        $allowed = match ($request->route()?->getName()) {
            'deals.store', null => ['deals.show'],
            'expenses.store', 'office.finance.expenses.store' => ['expenses.show', 'finance.journal'],
            'money-movements.store', 'office.accounts.money.store' => ['money-movements.show', 'deals.show', 'office.accounts', 'finance.journal'],
            default => [],
        };
        if (! in_array($route->getName(), $allowed, true)) {
            return null;
        }

        $page->setRouteResolver(fn () => $route);

        return $page;
    }
}
