<?php

namespace Tests\Unit;

use App\Support\JalaliDate;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class JalaliDateTest extends TestCase
{
    public function test_gregorian_date_is_formatted_as_jalali(): void
    {
        $this->assertSame('1405/05/19', (new JalaliDate)->format('2026-08-10'));
    }

    #[DataProvider('jalaliInputs')]
    public function test_jalali_inputs_are_strictly_converted(string $input): void
    {
        $this->assertSame('2026-08-10', (new JalaliDate)->toGregorianDate($input));
    }

    /** @return array<string, array{string}> */
    public static function jalaliInputs(): array
    {
        return [
            'western digits' => ['1405/05/19'],
            'Persian digits' => ['۱۴۰۵/۰۵/۱۹'],
            'Arabic digits' => ['١٤٠٥/٠٥/١٩'],
        ];
    }

    public function test_jalali_datetime_converts_and_preserves_time(): void
    {
        $this->assertSame('2026-08-10 14:30', (new JalaliDate)->toGregorianDateTime('1405/05/19 14:30'));
    }

    public function test_invalid_jalali_date_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new JalaliDate)->toGregorianDate('1405/13/40');
    }
}
