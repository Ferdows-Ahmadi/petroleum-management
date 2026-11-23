<?php

namespace Tests\Unit;

use App\Enums\PartnerRole;
use App\Models\BusinessPartner;
use App\Models\BusinessPartnerRole;
use App\Services\ContactImportPlanner;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ContactImportPlannerTest extends TestCase
{
    public function test_line_limits_count_contact_rows_and_keep_original_line_numbers(): void
    {
        $name = str_repeat('ن', 150);
        $source = "# fictional fixture\r\n\r\n".str_repeat("SARAF | {$name}\r\n", 1000)."# end\r\n";
        $planner = new ContactImportPlanner;
        [$group] = $planner->plan($source, []);
        $this->assertSame('create', $group['action']);
        $this->assertSame($name, $group['name']);
        $this->assertSame(range(3, 1002), $group['lines']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Line 1004:');
        $planner->plan($source.'CUSTOMER | Final Example', []);
    }

    #[DataProvider('invalidInput')]
    public function test_invalid_input_reports_the_problem_without_echoing_the_row(string $source, string $expected): void
    {
        try {
            (new ContactImportPlanner)->plan($source, []);
            $this->fail('Invalid input was accepted.');
        } catch (InvalidArgumentException $error) {
            $this->assertStringContainsString($expected, $error->getMessage());
            $this->assertStringNotContainsString('Example', $error->getMessage());
        }
    }

    public static function invalidInput(): array
    {
        return [
            'only comments' => ["  # Example\n\n", 'no contact rows'],
            '151 characters' => ['CUSTOMER | Example'.str_repeat('م', 144), 'Line 1:'],
            'bidi override' => ["# comment\nSARAF | Example\u{202E}تست", 'Line 2:'],
            'file byte limit' => ['#'.str_repeat('x', ContactImportPlanner::MAX_BYTES), '1 MiB'],
        ];
    }

    #[DataProvider('similarSpellings')]
    public function test_similar_names_are_suggested_in_both_directions_and_kept_separate(string $first, string $second): void
    {
        [$a, $b] = (new ContactImportPlanner)->plan("CUSTOMER | {$first}\n# another row\nCUSTOMER | {$second}", []);
        $this->assertSame([$first, $second], [$a['name'], $b['name']]);
        $this->assertSame([[3], [1]], [$a['candidate_lines'], $b['candidate_lines']]);
        foreach ([$a, $b] as $row) {
            $this->assertSame('review', $row['action']);
            $this->assertContains('possible_source_alias', $row['reasons']);
            $this->assertNull($row['partner_id']);
        }
    }

    public static function similarSpellings(): array
    {
        return [
            'Dari and Arabic kaf' => ['شرکت کاغذی', 'شركت كاغذی'],
            'diacritic' => ['گلخانه آزمایشی', 'گُلخانه آزمایشی'],
            'reordered words and exchange title' => ['صرافی نمونه مهتاب', 'مهتاب نمونه صراف'],
            'single character typo' => ['ابرستان تخیلی', 'ابرستان تخیلب'],
            'transposed characters' => ['نمونه بارانی', 'نمونه بارنای'],
        ];
    }

    public function test_unique_active_exact_match_reuses_a_contact_with_the_requested_and_another_role(): void
    {
        $partner = new BusinessPartner(['name' => 'گلخانه نمونه', 'is_active' => true]);
        $partner->id = 47;
        $partner->setRelation('roles', collect([
            new BusinessPartnerRole(['role' => PartnerRole::Supplier]),
            new BusinessPartnerRole(['role' => PartnerRole::Customer]),
        ]));
        [$group] = (new ContactImportPlanner)->plan("SUPPLIER | گلخانه نمونه\nSUPPLIER | گلخانه نمونه", [$partner]);
        $this->assertSame('reuse', $group['action']);
        $this->assertSame(47, $group['partner_id']);
        $this->assertSame([1, 2], $group['lines']);
        $this->assertSame([], $group['reasons']);
    }
}
