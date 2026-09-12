<?php

namespace App\Services;

use App\Models\Question;

class GradingService
{
    public static function normalizeType(string $type): string
    {
        return $type === 'mcq' ? 'mc_single' : $type;
    }

    public static function grade(Question $question, mixed $given): bool
    {
        $type = self::normalizeType((string) $question->type);
        $payload = $question->payload ?? [];

        return match ($type) {
            'mc_single' => self::gradeMcSingle($payload, $given),
            'true_false' => self::gradeTrueFalse($payload, $given),
            'ordering' => self::gradeOrdering($payload, $given),
            'fill_blank' => self::gradeFillBlank($payload, $given),
            default => false,
        };
    }

    protected static function gradeMcSingle(array $payload, mixed $given): bool
    {
        // Canonical: correct_option_id; legacy seed shape: answer.
        $correct = $payload['correct_option_id'] ?? $payload['answer'] ?? null;

        return $given == $correct;
    }

    protected static function gradeTrueFalse(array $payload, mixed $given): bool
    {
        $correct = (bool) ($payload['correct'] ?? $payload['answer'] ?? false);
        $answer = is_string($given)
            ? (bool) filter_var(trim($given), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? ((bool) $given)
            : (bool) $given;

        return $answer === $correct;
    }

    protected static function gradeOrdering(array $payload, mixed $given): bool
    {
        $correct = array_values((array) ($payload['correct_order'] ?? []));

        if (is_string($given)) {
            $given = array_map('trim', explode(',', $given));
        }
        $given = array_values((array) $given);

        return $given == $correct;
    }

    protected static function gradeFillBlank(array $payload, mixed $given): bool
    {
        $acceptable = $payload['acceptable_answers'] ?? $payload['answers'] ?? [];
        $needle = mb_strtolower(trim((string) $given));
        $haystack = array_map(fn ($a) => mb_strtolower(trim((string) $a)), (array) $acceptable);

        return in_array($needle, $haystack, true);
    }
}
