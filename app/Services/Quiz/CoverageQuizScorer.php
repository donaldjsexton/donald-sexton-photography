<?php

namespace App\Services\Quiz;

class CoverageQuizScorer
{
    public const MOMENTS = [
        'ready' => ['Getting ready', 1],
        'firstlook' => ['A first look', 1],
        'formals' => ['Family formals', 0],
        'sunset' => ['Sunset portraits', 1],
        'reception' => ['Reception entrance, dances and toasts', 1],
        'sendoff' => ['A send-off', 1],
        'rehearsal' => ['Rehearsal or welcome party', 2],
    ];

    /**
     * @param  array<string, mixed>  $a  validated quiz answers
     * @return array{result: string, score: int, second: bool, reasons: array<int, string>}
     */
    public function score(array $a): array
    {
        $day = $a['day'] ?? 'unsure';
        if (in_array($day, ['elopement', 'small'], true)) {
            return ['result' => 'elopement', 'score' => 0, 'second' => false, 'reasons' => []];
        }

        $score = 0;
        $reasons = [];

        if ($day === 'full') {
            $score += 2;
        }

        $guests = $a['guests'] ?? null;
        if ($guests === '150plus') {
            $score += 2;
            $reasons[] = 'with 150+ guests there are a lot of faces and family moments to cover';
        } elseif ($guests === '75-150') {
            $score += 1;
            $reasons[] = 'a guest list of 75 or more adds family and group moments';
        }

        $loc = $a['locations'] ?? 'same';
        if (in_array($loc, ['short', 'far'], true)) {
            $score += 1;
            $reasons[] = 'moving between locations eats real time';
        }

        $picked = [];
        foreach ((array) ($a['moments'] ?? []) as $key) {
            if (isset(self::MOMENTS[$key])) {
                $score += self::MOMENTS[$key][1];
                $picked[] = strtolower(self::MOMENTS[$key][0]);
            }
        }
        if ($picked !== []) {
            $top = array_slice($picked, 0, 3);
            $last = array_pop($top);
            $list = $top === [] ? $last : implode(', ', $top).' and '.$last;
            $reasons[] = 'you want photos of '.$list.(count($picked) > 3 ? ' and more' : '');
        }

        if (($a['start'] ?? null) === 'evening') {
            $score += 1;
            $reasons[] = 'an evening start pushes the reception later';
        }

        $result = $score >= 10 ? 'extended' : ($score >= 6 ? 'complete' : 'essential');
        $second = ($a['second'] ?? null) === 'yes' || $guests === '150plus';

        return ['result' => $result, 'score' => $score, 'second' => $second, 'reasons' => $reasons];
    }

    /** Build the short "why" paragraph from the reasons (max three). */
    public function why(array $scored): string
    {
        $r = array_slice($scored['reasons'], 0, 3);
        if ($r === []) {
            return 'Your answers point to a simple day, so this length covers it without extra padding.';
        }

        return 'Here is why: '.implode('; ', $r).'.';
    }
}
