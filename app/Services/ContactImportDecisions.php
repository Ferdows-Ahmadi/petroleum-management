<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * Apply explicit source-line decisions only. Existing directory conflicts must
 * still pass the planner's active, exact identity and role checks.
 */
class ContactImportDecisions
{
    public function resolve(string $source, array $groups, array $decisions, array $similar): array
    {
        if (($decisions['source_sha256'] ?? null) !== hash('sha256', $source)
            || ! is_array($decisions['decisions'] ?? null) || ! array_is_list($decisions['decisions']) || ! $decisions['decisions']) {
            throw new InvalidArgumentException('Review decisions must identify the exact source file and list the reviewed cases.');
        }
        $byLine = [];
        foreach ($groups as $name => $group) {
            foreach ($group['lines'] as $line) {
                $byLine[$line] = $name;
            }
        }
        $resolved = $groups;
        $used = [];
        $distinct = [];
        foreach ($decisions['decisions'] as $index => $case) {
            $error = new InvalidArgumentException('Invalid, overlapping or incomplete identity decision #'.($index + 1).'.');
            $lines = $case['lines'] ?? [];
            if (! is_array($lines) || ! array_is_list($lines) || count($lines) < 2) {
                throw $error;
            }
            $names = [];
            foreach ($lines as $line) {
                if (! is_int($line) || ! isset($byLine[$line]) || isset($used[$line])) {
                    throw $error;
                }
                $used[$line] = true;
                $names[$byLine[$line]] = true;
            }
            $spellings = array_keys($names);
            $entries = [];
            foreach ($spellings as $name) {
                if (array_diff($groups[$name]['lines'], $lines) || array_diff($spellings, $similar[$name])) {
                    throw $error;
                }
                array_push($entries, ...$groups[$name]['source_entries']);
            }
            usort($entries, fn ($a, $b) => $a['line'] <=> $b['line']);
            if (($case['identity'] ?? null) === 'distinct' && count($spellings) >= 2
                && ! array_diff(array_keys($case), ['identity', 'lines'])) {
                foreach ($spellings as $name) {
                    $distinct[$name] = array_values(array_diff($spellings, [$name]));
                    $resolved[$name]['decision'] = 'distinct';
                }

                continue;
            }
            $canonicalLine = $case['canonical_line'] ?? null;
            $role = $case['role'] ?? null;
            if (($case['identity'] ?? null) !== 'same' || ! is_int($canonicalLine) || ! in_array($canonicalLine, $lines, true)
                || ! in_array($role, array_column($entries, 'category'), true)
                || array_diff(array_keys($case), ['identity', 'lines', 'canonical_line', 'role'])) {
                throw $error;
            }
            $canonical = $byLine[$canonicalLine];
            foreach ($spellings as $name) {
                if ($name !== $canonical) {
                    unset($resolved[$name]);
                }
            }
            $resolved[$canonical] = [
                'lines' => array_column($entries, 'line'), 'source_entries' => $entries,
                'categories' => array_values(array_unique(array_column($entries, 'category'))),
                'decision' => 'same', 'members' => $spellings, 'approved_role' => $role,
            ];
        }

        return [$resolved, $distinct];
    }
}
