<?php

namespace App\Http\Controllers;

use App\Models\CornerRequest;
use App\Models\User;
use App\Support\MemberInbox;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CornerRequestController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:80'], 'description' => ['required', 'string', 'max:2000']]);
        DB::transaction(function () use ($request, $data) {
            $member = User::lockForUpdate()->findOrFail($request->user()->id);
            abort_unless($member->corner_unlocked_at !== null, 403, 'Complete all 14 posting days to unlock Corner requests.');
            if (CornerRequest::where('user_id', $member->id)->whereIn('status', ['pending', 'approved'])->exists()) {
                throw ValidationException::withMessages(['corner' => 'You already have a pending or approved Corner request.']);
            }
            $corner = CornerRequest::create([...$data, 'user_id' => $member->id]);
            foreach (User::where(fn ($query) => $query->where('role', 'admin')->orWhere('is_admin', true))->pluck('id') as $adminId) {
                MemberInbox::send($adminId, 'corner_request', 'A Corner request is ready', $member->username.' has requested a Corner.', 'corner_admin', $corner->id);
            }
        }, 3);

        return to_route('rewards.index')->with('success', 'Your Corner request has been sent to the administrators.');
    }

    public function index(Request $request): View
    {
        abort_unless($request->user()->isAdmin(), 403);
        $data = $request->validate(['status' => ['nullable', Rule::in(['all', 'pending', 'approved', 'declined'])]]);
        $status = $data['status'] ?? 'pending';
        $requests = CornerRequest::with('user')->when($status !== 'all', fn ($query) => $query->where('status', $status))->oldest('id')->paginate(20)->withQueryString();

        return view('activity.corner-review', compact('requests', 'status'));
    }

    public function update(Request $request, CornerRequest $corner): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $data = $request->validate(['status' => ['required', Rule::in(['approved', 'declined'])], 'review_note' => ['required', 'string', 'max:2000']]);
        DB::transaction(function () use ($request, $corner, $data) {
            $corner = CornerRequest::lockForUpdate()->findOrFail($corner->id);
            if ($corner->status !== 'pending') {
                throw ValidationException::withMessages(['corner' => 'This request has already been reviewed.']);
            }
            $corner->update([...$data, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
            MemberInbox::send($corner->user_id, 'corner_review', 'Your Corner request was '.$data['status'], 'Open Rewards to read the administrator’s response.', 'corner', $corner->id);
        }, 3);

        return back()->with('success', 'Corner request reviewed. The member has been notified.');
    }
}
