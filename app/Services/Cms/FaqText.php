<?php

namespace App\Services\Cms;

class FaqText
{
    /**
     * Parse the admin textarea into `[{question, answer}, ...]`. Each FAQ is
     * a line in the form `Question | Answer` — blank lines are ignored,
     * everything else is dropped to avoid bad schema output.
     *
     * @return array<int, array{question: string, answer: string}>|null
     */
    public static function parse(?string $raw): ?array
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $faqs = collect(preg_split('/\r\n|\r|\n/', $raw))
            ->map(fn ($line) => trim((string) $line))
            ->filter(fn (string $line) => $line !== '' && str_contains($line, '|'))
            ->map(function (string $line): ?array {
                [$question, $answer] = array_pad(explode('|', $line, 2), 2, '');
                $question = trim($question);
                $answer = trim($answer);

                if ($question === '' || $answer === '') {
                    return null;
                }

                return ['question' => $question, 'answer' => $answer];
            })
            ->filter()
            ->values()
            ->all();

        return $faqs === [] ? null : $faqs;
    }
}
