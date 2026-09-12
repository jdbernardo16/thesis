<?php

namespace App\Services;

class ExpCalculator
{
    public static function forAttempt(int $correct, int $total, int $stars, bool $repeatPerfect): int
    {
        $bonus = [0, 10, 30, 50][$stars] ?? 0;
        $exp = $correct * 10 + $bonus;

        return $repeatPerfect ? intdiv($exp, 5) : $exp;
    }
}
