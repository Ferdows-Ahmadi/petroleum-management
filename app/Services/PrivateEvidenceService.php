<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrivateEvidenceService
{
    public function storePurchaseBill(UploadedFile $file): string
    {
        return $file->store('purchase-bills', 'local');
    }

    public function storeWeighbridgeBill(UploadedFile $file): string
    {
        return $file->store('weighbridge', 'local');
    }

    public function delete(?string $path): void
    {
        if ($path) {
            Storage::disk('local')->delete($path);
        }
    }

    public function response(string $path, string $downloadName): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, $downloadName, [
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
