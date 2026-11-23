<?php

namespace App\Support;

class NumberFormat
{
    public function weight(mixed $value): string
    {
        return $this->decimal($value, 3);
    }

    public function rate(mixed $value): string
    {
        return $this->decimal($value, 4);
    }

    public function money(mixed $value): string
    {
        return $this->decimal($value, 2, 2);
    }

    public function decimal(mixed $value, int $maximumDecimals, int $minimumDecimals = 0): string
    {
        $raw = (string) ($value ?? '0');
        if (! preg_match('/^-?\d+(?:\.\d+)?$/', $raw)) {
            throw new \InvalidArgumentException('Expected a plain decimal value.');
        }
        $negative = str_starts_with($raw, '-');
        $absolute = ltrim($raw, '-');
        $increment = $maximumDecimals === 0 ? '0.5' : '0.'.str_repeat('0', $maximumDecimals).'5';
        $rounded = bcadd($absolute, $increment, $maximumDecimals);
        [$integer, $digits] = array_pad(explode('.', $rounded, 2), 2, '');
        $formatted = ($negative && bccomp($rounded, '0', $maximumDecimals) !== 0 ? '-' : '')
            .preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $integer)
            .($maximumDecimals > 0 ? '.'.$digits : '');
        if ($maximumDecimals === $minimumDecimals) {
            return $formatted;
        }

        [$whole, $fraction] = array_pad(explode('.', $formatted, 2), 2, '');
        $fraction = rtrim($fraction, '0');
        if (strlen($fraction) < $minimumDecimals) {
            $fraction = str_pad($fraction, $minimumDecimals, '0');
        }

        return $fraction === '' ? $whole : $whole.'.'.$fraction;
    }

    public function absoluteMoney(string $value): string
    {
        return $this->money(ltrim($value, '-'));
    }
}
