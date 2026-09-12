<?php

namespace App\Services;

class StarCalculator
{
    public static function forPct(float $pct): int
    {
        if ($pct < 30) {
            return 0;
        }
        if ($pct < 60) {
            return 1;
        }
        if ($pct < 90) {
            return 2;
        }

        return 3;
    }
}
