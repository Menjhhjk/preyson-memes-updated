<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\User;
use App\Support\ReportReasons;
use App\Support\ReportTargets;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function create(Request $request, string $type, int $id): View
    {
        $target = ReportTargets::find($type, $id);
        ReportTargets::authorize($target, $request->user());
        $existing = Report::where('reporter_id', $request->user()->id)->where('target_type', $type)->where('target_id', $id)->first();

        return view('reports.create', [
            'type' => $type, 'targetId' => $id, 'label' => ReportTargets::label($target),
            'targetUrl' => ReportTargets::url($target), 'groups' => ReportReasons::groups(), 'existing' => $existing,
        ]);
    }

    public function store(Request $request, string $type, int $id): RedirectResponse
    {
        $target = ReportTargets::find($type, $id);
        ReportTargets::authorize($target, $request->user());
        $data = $request->validate([
            'reason' => ['required', Rule::in(array_keys(ReportReasons::all()))],
            'description' => [Rule::requiredIf(fn () => is_string($request->input('reason')) && str_starts_with($request->input('reason'), 'other_')), 'nullable', 'string', 'max:3000'],
        ], ['description.required' => 'Please describe the concern when choosing Other.']);

        DB::transaction(function () use ($request, $type, $id, $target, $data) {
            User::query()->lockForUpdate()->findOrFail($request->user()->id);
            if (Report::where('reporter_id', $request->user()->id)->where('target_type', $type)->where('target_id', $id)->exists()) {
                throw ValidationException::withMessages(['report' => 'You have already reported this item. Your report is available to the moderation team.']);
            }
            Report::create([
                'reporter_id' => $request->user()->id, 'target_type' => $type, 'target_id' => $id,
                'target_label' => ReportTargets::label($target), 'target_snapshot' => ReportTargets::snapshot($target),
                'reason' => $data['reason'], 'severity' => ReportReasons::severity($data['reason']),
                'description' => $data['description'] ?? null,
            ]);
        });

        return redirect(ReportTargets::url($target))->with('success', 'Report sent to the moderation team. Thank you for helping keep the community welcoming.');
    }
}
