<?php

namespace App\Services;

use App\Models\BusinessPartner;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class ContactImportService
{
    public function __construct(private readonly ContactImportPlanner $planner) {}

    public function preview(string $source): array
    {
        return $this->report($source);
    }

    public function apply(string $source, string $fingerprint): array
    {
        return DB::transaction(function () use ($source, $fingerprint): array {
            // Serialize with other imports and the inline contact creator, including absent names.
            if (! DB::table('operation_locks')->where('name', 'inline-party')->lockForUpdate()->first()) {
                throw new RuntimeException('The contact creation lock is missing.');
            }
            $report = $this->report($source);
            if (! hash_equals($report['fingerprint'], $fingerprint)) {
                throw new InvalidArgumentException('The preview no longer matches. Run a new preview and review it before applying.');
            }

            foreach ($report['groups'] as $i => $group) {
                if ($group['action'] === 'create') {
                    $partner = BusinessPartner::create(['name' => $group['name'], 'is_active' => true]);
                    $partner->roles()->create(['role' => $group['role']]);
                    $report['groups'][$i]['partner_id'] = $partner->id;
                }
            }
            $report['mode'] = 'applied';

            return $report;
        }, 3);
    }

    private function report(string $source): array
    {
        $partners = BusinessPartner::withTrashed()->select('id', 'name', 'is_active', 'deleted_at')
            ->with('roles:id,business_partner_id,role')->orderBy('id')->get()->keyBy('id');
        $groups = $this->planner->plan($source, $partners);
        $candidates = [];
        $counts = ['source_rows' => 0, 'groups' => count($groups), 'create' => 0, 'reuse' => 0, 'review' => 0];
        foreach ($groups as $group) {
            $counts['source_rows'] += count($group['lines']);
            $counts[$group['action']]++;
            foreach ($group['candidate_ids'] as $id) {
                $partner = $partners->get($id);
                $candidates[$id] = [
                    'name' => $partner->name,
                    'roles' => $partner->roles->map(fn ($entry) => $entry->role->value)->sort()->values()->all(),
                    'state' => $partner->trashed() ? 'deleted' : ($partner->is_active ? 'active' : 'inactive'),
                ];
            }
        }
        $db = DB::connection();
        $target = [$db->getDriverName(), $db->getConfig('host'), $db->getConfig('port'), $db->getDatabaseName(), $db->getTablePrefix()];
        $fingerprint = hash('sha256', json_encode([$target, hash('sha256', $source), $groups, $candidates], JSON_THROW_ON_ERROR));

        return ['mode' => 'preview', 'fingerprint' => $fingerprint, 'counts' => $counts, 'groups' => $groups, 'candidates' => $candidates];
    }
}
