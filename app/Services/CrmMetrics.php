<?php

namespace App\Services;

use App\Models\Inquiry;

class CrmMetrics
{
    /**
     * @return array{stats: array<int, array{label: string, value: string, meta: string}>, funnel: array<int, array{label: string, value: int, context: string}>, topSources: array<int, array{label: string, meta: string}>}
     */
    public function pulse(): array
    {
        return [
            'stats' => $this->pulseStats(),
            'funnel' => $this->pulseFunnel(),
            'topSources' => $this->pulseTopSources(),
        ];
    }

    /**
     * @return array<int, array{label: string, value: string, meta: string}>
     */
    private function pulseStats(): array
    {
        $startOfWeek = now()->startOfWeek();
        $startOfPreviousWeek = now()->subWeek()->startOfWeek();
        $startOfYear = now()->startOfYear();

        $thisWeek = Inquiry::query()->where('created_at', '>=', $startOfWeek)->count();
        $lastWeek = Inquiry::query()
            ->whereBetween('created_at', [$startOfPreviousWeek, $startOfWeek])
            ->count();

        $activePipeline = Inquiry::query()
            ->whereIn('status', ['new', 'active', 'follow_up'])
            ->count();

        $bookedYtd = Inquiry::query()
            ->where('status', 'booked')
            ->where('created_at', '>=', $startOfYear)
            ->count();

        $totalYtd = Inquiry::query()
            ->where('created_at', '>=', $startOfYear)
            ->whereNot('status', 'archived')
            ->count();

        $conversionRate = $totalYtd > 0
            ? round(($bookedYtd / $totalYtd) * 100)
            : 0;

        $weekDelta = $thisWeek - $lastWeek;
        $weekDeltaMeta = $lastWeek === 0 && $thisWeek === 0
            ? 'No inquiries last week either.'
            : ($weekDelta >= 0
                ? '+'.$weekDelta.' vs last week ('.$lastWeek.').'
                : $weekDelta.' vs last week ('.$lastWeek.').');

        return [
            [
                'label' => 'New This Week',
                'value' => (string) $thisWeek,
                'meta' => $weekDeltaMeta,
            ],
            [
                'label' => 'Active Pipeline',
                'value' => (string) $activePipeline,
                'meta' => 'New, active, and follow-up inquiries combined.',
            ],
            [
                'label' => 'Booked YTD',
                'value' => (string) $bookedYtd,
                'meta' => $totalYtd > 0
                    ? $conversionRate.'% conversion rate on '.$totalYtd.' live leads.'
                    : 'No inquiries captured this year yet.',
            ],
            [
                'label' => 'Avg Response Time',
                'value' => '—',
                'meta' => 'Needs a first_contacted_at column on inquiries to compute.',
            ],
        ];
    }

    /**
     * @return array<int, array{label: string, value: int, context: string}>
     */
    private function pulseFunnel(): array
    {
        $funnelLabels = Inquiry::statusOptions();
        $funnelCounts = Inquiry::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $funnelContexts = [
            'new' => 'Waiting for first review.',
            'active' => 'Currently in conversation.',
            'follow_up' => 'Scheduled to circle back.',
            'booked' => 'Contract signed.',
            'archived' => 'Closed or disqualified.',
        ];

        $funnel = [];
        foreach ($funnelLabels as $status => $label) {
            $funnel[] = [
                'label' => $label,
                'value' => (int) ($funnelCounts[$status] ?? 0),
                'context' => $funnelContexts[$status] ?? '',
            ];
        }

        return $funnel;
    }

    /**
     * @return array<int, array{label: string, meta: string}>
     */
    private function pulseTopSources(): array
    {
        return Inquiry::query()
            ->selectRaw('source, count(*) as total')
            ->whereNotNull('source')
            ->where('source', '!=', '')
            ->groupBy('source')
            ->orderByDesc('total')
            ->limit(4)
            ->get()
            ->map(fn ($row) => [
                'label' => str($row->source)->replace('_', ' ')->headline()->toString(),
                'meta' => $row->total.' inquiries tracked.',
            ])
            ->all();
    }
}
