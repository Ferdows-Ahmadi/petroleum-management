<?php

namespace App\Support;

use Carbon\Carbon;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use Morilog\Jalali\Jalalian;
use Throwable;

class JalaliDate
{
    public function format(DateTimeInterface|string|null $date, bool $withTime = false): string
    {
        if ($date === null || $date === '') {
            return '—';
        }

        $carbon = $date instanceof DateTimeInterface ? Carbon::instance($date) : Carbon::parse($date);

        return Jalalian::fromCarbon($carbon)->format($withTime ? 'Y/m/d H:i' : 'Y/m/d');
    }

    public function toGregorianDate(string $value): string
    {
        $value = $this->westernDigits(trim($value));

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
            if ($date && $date->format('Y-m-d') === $value) {
                return $value;
            }
        }

        if (! preg_match('/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/', $value, $parts)) {
            throw new InvalidArgumentException('Invalid Jalali date format.');
        }

        try {
            return (new Jalalian((int) $parts[1], (int) $parts[2], (int) $parts[3]))->toCarbon()->format('Y-m-d');
        } catch (Throwable $exception) {
            throw new InvalidArgumentException('Invalid Jalali date.', previous: $exception);
        }
    }

    public function toGregorianDateTime(string $value): string
    {
        $value = $this->westernDigits(trim(str_replace('T', ' ', $value)));
        if (! preg_match('/^(.+?)\s+(\d{1,2}):(\d{2})$/', $value, $parts)) {
            throw new InvalidArgumentException('Invalid Jalali date-time format.');
        }

        $date = $this->toGregorianDate($parts[1]);
        $hour = (int) $parts[2];
        $minute = (int) $parts[3];
        if ($hour > 23 || $minute > 59) {
            throw new InvalidArgumentException('Invalid Jalali time.');
        }

        return sprintf('%s %02d:%02d', $date, $hour, $minute);
    }

    private function westernDigits(string $value): string
    {
        return strtr($value, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
    }
}
