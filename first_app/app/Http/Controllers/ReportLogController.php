<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Support\ReportReasons;
use App\Support\ReportTargets;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReportLogController extends Controller
{
    private const STATUSES = ['open', 'in_review', 'resolved', 'dismissed'];

    public function index(Request $request): View
    {
        abort_unless($request->user()->canModerate(), 403);
        $filters = $this->filters($request);
        $query = $this->query($filters)->with(['reporter', 'reviewer']);
        match ($filters['sort']) {
            'oldest' => $query->oldest()->orderBy('id'),
            'severity' => $query->orderByRaw("CASE severity WHEN 'severe' THEN 3 WHEN 'moderate' THEN 2 ELSE 1 END DESC")->oldest()->orderBy('id'),
            default => $query->latest()->orderByDesc('id'),
        };
        $reports = $query->paginate(20)->withQueryString();

        return view('reports.index', [
            'reports' => $reports, 'filters' => $filters, 'groups' => ReportReasons::groups(),
            'reasons' => ReportReasons::all(), 'statuses' => self::STATUSES,
            'targets' => ReportTargets::forRows($reports), 'tab' => 'logs',
        ]);
    }

    public function leaderboard(Request $request): View
    {
        abort_unless($request->user()->canModerate(), 403);
        $filters = $this->filters($request, true);
        $base = $this->query($filters);
        $query = (clone $base)->select(['target_type', 'target_id'])
            ->selectRaw('MAX(target_label) AS target_label, COUNT(*) AS total, MAX(created_at) AS last_report, MIN(created_at) AS first_report')
            ->selectRaw("SUM(CASE WHEN severity = 'severe' THEN 1 ELSE 0 END) AS severe_count")
            ->selectRaw("SUM(CASE WHEN severity = 'moderate' THEN 1 ELSE 0 END) AS moderate_count")
            ->selectRaw("SUM(CASE WHEN severity = 'minor' THEN 1 ELSE 0 END) AS minor_count")
            ->selectRaw("SUM(CASE severity WHEN 'severe' THEN 100 WHEN 'moderate' THEN 10 ELSE 1 END) AS score")
            ->groupBy('target_type', 'target_id');
        match ($filters['sort']) {
            'weighted' => $query->orderByDesc('score')->orderByDesc('severe_count')->orderByDesc('moderate_count'),
            'total' => $query->orderByDesc('total'),
            'newest' => $query->orderByDesc('last_report'),
            'oldest' => $query->orderBy('first_report'),
            default => $query->orderByDesc('severe_count')->orderByDesc('moderate_count')->orderByDesc('minor_count'),
        };
        $rankings = $query->orderBy('target_type')->orderBy('target_id')->paginate(20)->withQueryString();
        $breakdowns = [];
        if ($rankings->isNotEmpty()) {
            $counts = (clone $base)->where(function ($query) use ($rankings) {
                foreach ($rankings as $row) {
                    $query->orWhere(fn ($query) => $query->where('target_type', $row->target_type)->where('target_id', $row->target_id));
                }
            })->select(['target_type', 'target_id', 'reason'])->selectRaw('COUNT(*) AS total')
                ->groupBy('target_type', 'target_id', 'reason')->get();
            foreach ($counts as $count) {
                $breakdowns[$count->target_type.':'.$count->target_id][$count->reason] = (int) $count->getAttribute('total');
            }
        }

        return view('reports.leaderboard', [
            'rankings' => $rankings, 'filters' => $filters, 'groups' => ReportReasons::groups(),
            'reasons' => ReportReasons::all(), 'statuses' => self::STATUSES,
            'targets' => ReportTargets::forRows($rankings), 'breakdowns' => $breakdowns, 'tab' => 'leaderboard',
        ]);
    }

    public function update(Request $request, Report $report): RedirectResponse
    {
        abort_unless($request->user()->canModerate(), 403);
        $data = $request->validate([
            'status' => ['required', Rule::in(self::STATUSES)],
            'moderator_note' => ['nullable', 'string', 'max:3000'],
        ]);
        $report->update([...$data, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);

        return back()->with('success', 'Report review saved.');
    }

    /** @return array<string, mixed> */
    private function filters(Request $request, bool $ranking = false): array
    {
        $data = $request->validate([
            'type' => ['nullable', Rule::in(['all', 'post', 'comment', 'account'])],
            'target' => ['nullable', 'integer', 'min:1'],
            'severity' => ['nullable', Rule::in(['all', 'severe', 'moderate', 'minor'])],
            'reason' => ['nullable', Rule::in(['all', ...array_keys(ReportReasons::all())])],
            'status' => ['nullable', Rule::in(['all', 'active', ...self::STATUSES])],
            'q' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'until' => ['nullable', 'date_format:Y-m-d', ...($request->filled('from') ? ['after_or_equal:from'] : [])],
            'sort' => ['nullable', Rule::in($ranking ? ['priority', 'weighted', 'total', 'newest', 'oldest'] : ['newest', 'oldest', 'severity'])],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ]);

        return [
            'type' => $data['type'] ?? 'all', 'target' => $data['target'] ?? null,
            'severity' => $data['severity'] ?? 'all', 'reason' => $data['reason'] ?? 'all',
            'status' => $data['status'] ?? ($ranking ? 'active' : 'all'), 'q' => trim($data['q'] ?? ''),
            'from' => $data['from'] ?? null, 'until' => $data['until'] ?? null,
            'sort' => $data['sort'] ?? ($ranking ? 'priority' : 'newest'),
        ];
    }

    /** @param array<string, mixed> $filters
     * @return Builder<Report>
     */
    private function query(array $filters): Builder
    {
        $query = Report::query();
        foreach (['type' => 'target_type', 'severity' => 'severity', 'reason' => 'reason'] as $filter => $column) {
            if ($filters[$filter] !== 'all') {
                $query->where($column, $filters[$filter]);
            }
        }
        if ($filters['status'] === 'active') {
            $query->whereIn('status', ['open', 'in_review']);
        } elseif ($filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }
        $query->when($filters['target'], fn ($query, $id) => $query->where('target_id', $id))
            ->when($filters['from'], fn ($query, $date) => $query->where('created_at', '>=', $date.' 00:00:00'))
            ->when($filters['until'], fn ($query, $date) => $query->where('created_at', '<=', $date.' 23:59:59'));
        if ($filters['q'] !== '') {
            $literal = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['q']).'%';
            $query->where(fn ($query) => $query->whereRaw("target_label LIKE ? ESCAPE '!'", [$literal])
                ->orWhereRaw("description LIKE ? ESCAPE '!'", [$literal]));
        }

        return $query;
    }
}
