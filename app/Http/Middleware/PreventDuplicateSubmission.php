<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

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
            throw ValidationException::withMessages(['operation_key' => 'شناسه ثبت نامعتبر است؛ فورم را دوباره باز کنید.']);
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
                if (! hash_equals($receipt->request_hash, $hash)) {
                    throw ValidationException::withMessages(['operation_key' => 'این فورم قبلاً ثبت شده است؛ برای رویداد جدید فورم تازه باز کنید.']);
                }

                return redirect($receipt->location)->with('success', 'این رویداد قبلاً ثبت شده است؛ ثبت تکراری ایجاد نشد.');
            }
            $response = $next($request);
            if ($response->isRedirect()) {
                DB::table('operation_receipts')->insert([
                    'user_id' => $request->user()->id, 'operation_key' => $key, 'request_hash' => $hash,
                    'location' => $response->headers->get('Location'), 'created_at' => now(),
                ]);
            }

            return $response;
        });
    }
}
