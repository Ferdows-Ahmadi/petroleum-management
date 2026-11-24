<?php

namespace App\Services;

use App\Enums\PartnerRole;
use App\Models\BusinessPartner;
use InvalidArgumentException;
use Normalizer;

class ContactImportPlanner
{
    public const MAX_BYTES = 1048576;

    public const MAX_ROWS = 1000;

    /**
     * @param  iterable<BusinessPartner>  $partners  Include inactive and soft-deleted contacts, with roles loaded.
     * @return list<array{name:string,lines:list<int>,categories:list<string>,action:string,role:?string,partner_id:?int,reasons:list<string>,candidate_ids:list<int>,candidate_lines:list<int>}>
     */
    public function plan(string $source, iterable $partners, bool $includeGeneral = false, ?array $decisions = null): array
    {
        $groups = $this->parse($source);

        // Index only the bounded source. Every alias is a review suggestion, even if unique.
        $sourceIndex = [];
        $keysByName = [];
        foreach ($groups as $name => $group) {
            $keysByName[$name] = $this->aliasKeys($name);
            foreach ($keysByName[$name] as $key) {
                $sourceIndex[$key][] = $name;
            }
        }
        $matches = [];
        foreach ($partners as $partner) {
            foreach ($this->candidates($this->aliasKeys($partner->name), $sourceIndex) as $name) {
                $matches[$name][] = $partner;
            }
        }

        $sourceGroups = $groups;
        $similar = [];
        foreach ($keysByName as $name => $keys) {
            $similar[$name] = $this->candidates($keys, $sourceIndex);
        }
        $distinct = [];
        if ($decisions !== null) {
            [$groups, $distinct] = (new ContactImportDecisions)->resolve($source, $groups, $decisions, $similar);
        }
        $plan = [];
        foreach ($groups as $name => $group) {
            $categories = $group['categories'];
            $reviewedCategories = isset($group['approved_role']) ? [$group['approved_role']] : $categories;
            $role = count($reviewedCategories) === 1 ? $reviewedCategories[0] : null;
            $reasons = [];
            if (! $includeGeneral && in_array(PartnerRole::Partner->value, $reviewedCategories, true)) {
                $role = null;
                $reasons[] = 'uncategorized';
            }
            if (count($reviewedCategories) > 1) {
                $reasons[] = 'conflicting_source_roles';
            }
            $candidateLines = [];
            $members = $group['members'] ?? [$name];
            $candidates = [];
            foreach ($members as $member) {
                foreach (array_diff($similar[$member], $members, $distinct[$member] ?? []) as $similarName) {
                    array_push($candidateLines, ...$sourceGroups[$similarName]['lines']);
                }
                foreach ($matches[$member] ?? [] as $partner) {
                    if (! in_array($partner->name, $distinct[$member] ?? [], true)) {
                        $candidates[$partner->id] = $partner;
                    }
                }
            }
            $candidateLines = array_values(array_unique($candidateLines));
            if ($candidateLines) {
                $reasons[] = 'possible_source_alias';
            }

            $candidates = array_values($candidates);
            $exact = array_values(array_filter($candidates, fn ($p) => $p->name === $name));
            if (count($candidates) > count($exact)) {
                $reasons[] = 'possible_existing_alias';
            }
            if (count($exact) > 1) {
                $reasons[] = 'multiple_exact_matches';
            }
            foreach ($exact as $partner) {
                if (! $partner->is_active || $partner->trashed()) {
                    $reasons[] = 'inactive_or_deleted';
                }
                if ($role !== null && ! $partner->roles->contains('role', PartnerRole::from($role))) {
                    $reasons[] = 'existing_role_conflict';
                }
            }

            $candidateIds = array_map(fn ($p) => (int) $p->getKey(), $candidates);
            sort($candidateIds, SORT_NUMERIC);
            sort($candidateLines, SORT_NUMERIC);
            $action = $reasons ? 'review' : ($exact ? 'reuse' : 'create');
            $entry = [
                'name' => $name, 'lines' => $group['lines'], 'categories' => $categories,
                'action' => $action, 'role' => $role,
                'partner_id' => $action === 'reuse' ? (int) $exact[0]->getKey() : null,
                'reasons' => array_values(array_unique($reasons)),
                'candidate_ids' => $candidateIds, 'candidate_lines' => $candidateLines,
            ];
            if (isset($group['decision'])) {
                $entry['decision'] = $group['decision'];
                $entry['source_entries'] = $group['source_entries'];
            }
            $plan[] = $entry;
        }

        return $plan;
    }

    private function parse(string $source): array
    {
        if (strlen($source) > self::MAX_BYTES || ! mb_check_encoding($source, 'UTF-8')) {
            throw new InvalidArgumentException('Expected UTF-8 text of at most 1 MiB.');
        }
        $source = preg_replace('/^\x{FEFF}/u', '', $source);
        $groups = [];
        $count = 0;
        foreach (preg_split('/\r\n|\n|\r/', $source) as $index => $line) {
            $lineNumber = $index + 1;
            $line = mb_trim($line, " \t\u{00A0}");
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (++$count > self::MAX_ROWS) {
                throw new InvalidArgumentException("Line {$lineNumber}: exceeded the 1,000 contact row limit.");
            }
            $fields = explode('|', $line);
            if (count($fields) !== 2) {
                throw new InvalidArgumentException("Line {$lineNumber}: expected CATEGORY | name.");
            }
            [$category, $name] = array_map(fn ($field) => mb_trim($field, " \t\u{00A0}"), $fields);
            if (! PartnerRole::tryFrom($category)) {
                throw new InvalidArgumentException("Line {$lineNumber}: unknown or missing category.");
            }
            // ZWNJ and ZWJ are meaningful in Dari. Other control characters can obscure the review text.
            $visible = str_replace(["\u{200C}", "\u{200D}"], '', $name);
            if (! preg_match('/\p{L}/u', $name) || mb_strlen($name) > 150 || preg_match('/[\p{C}\p{Zl}\p{Zp}]/u', $visible)) {
                throw new InvalidArgumentException("Line {$lineNumber}: expected a name containing letters, up to 150 characters, without controls.");
            }
            $groups[$name] ??= ['lines' => [], 'categories' => [], 'source_entries' => []];
            $groups[$name]['lines'][] = $lineNumber;
            $groups[$name]['source_entries'][] = ['line' => $lineNumber, 'category' => $category, 'name' => $name];
            if (! in_array($category, $groups[$name]['categories'], true)) {
                $groups[$name]['categories'][] = $category;
            }
        }
        if ($groups === []) {
            throw new InvalidArgumentException('The source has no contact rows.');
        }

        return $groups;
    }

    /** @return list<string> */
    private function aliasKeys(string $name): array
    {
        $folded = mb_strtolower(Normalizer::normalize($name, Normalizer::FORM_KD));
        $folded = strtr($folded, ['ي' => 'ی', 'ى' => 'ی', 'ك' => 'ک', 'ـ' => '']);
        $folded = preg_replace('/\p{M}+/u', '', $folded);
        $tokens = preg_split('/[^\p{L}\p{N}]+/u', $folded, -1, PREG_SPLIT_NO_EMPTY);
        $spelling = implode('', $tokens);
        $significant = array_diff($tokens, ['صراف', 'صرافی']);
        sort($significant, SORT_STRING);
        $keys = ['spelling:'.$spelling];
        if ($significant) {
            $keys[] = 'words:'.implode(' ', $significant);
        }
        // For ordinary-length names, index a missing, changed or transposed character by
        // removing one code point on either side. Short names produce too many weak hits.
        $letters = mb_str_split($spelling);
        if (count($letters) >= 6 && count($letters) <= 64) {
            $keys[] = 'typo:'.$spelling;
            foreach (array_keys($letters) as $i) {
                $shorter = $letters;
                unset($shorter[$i]);
                $keys[] = 'typo:'.implode('', $shorter);
            }
        }

        return array_unique($keys);
    }

    /** @return list<string> */
    private function candidates(array $keys, array $sourceIndex): array
    {
        $found = [];
        foreach ($keys as $key) {
            foreach ($sourceIndex[$key] ?? [] as $name) {
                $found[$name] = true;
            }
        }

        return array_keys($found);
    }
}
