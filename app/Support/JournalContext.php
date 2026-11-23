<?php

namespace App\Support;

class JournalContext
{
    /** @return array<string, int|string> */
    public static function forPayment(?int $accountId, ?int $sarafId): array
    {
        if ($sarafId > 0) {
            return ['context' => 'saraf', 'saraf_id' => $sarafId];
        }

        return $accountId > 0 ? ['context' => 'account', 'account_id' => $accountId] : [];
    }
}
