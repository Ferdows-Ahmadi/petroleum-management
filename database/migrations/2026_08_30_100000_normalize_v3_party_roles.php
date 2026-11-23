<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $legacyBothPartyIds = DB::table('business_partner_roles')
            ->where('role', 'BOTH')
            ->pluck('business_partner_id');

        foreach ($legacyBothPartyIds as $partyId) {
            DB::table('business_partner_roles')->insertOrIgnore([
                ['business_partner_id' => $partyId, 'role' => 'CUSTOMER'],
                ['business_partner_id' => $partyId, 'role' => 'SUPPLIER'],
            ]);
        }

        DB::table('business_partner_roles')->where('role', 'BOTH')->delete();
    }

    public function down(): void
    {
        // CUSTOMER + SUPPLIER was already V2.1's normalized representation of BOTH.
    }
};
