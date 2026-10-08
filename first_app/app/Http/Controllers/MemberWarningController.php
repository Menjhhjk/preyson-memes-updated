<?php

namespace App\Http\Controllers;

use App\Models\MemberWarning;
use App\Models\User;
use App\Support\MemberInbox;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MemberWarningController extends Controller
{
    public function mine(Request $request): View
    {
        return view('activity.warnings', ['member' => $request->user(), 'staffView' => false,
            'warnings' => MemberWarning::where('user_id', $request->user()->id)->with('issuer')->latest('id')->paginate(15)]);
    }

    public function create(Request $request, User $member): View
    {
        $this->authorizeWarning($request->user(), $member);

        return view('activity.warnings', ['member' => $member, 'staffView' => true,
            'warnings' => MemberWarning::where('user_id', $member->id)->with('issuer')->latest('id')->paginate(15)]);
    }

    public function store(Request $request, User $member): RedirectResponse
    {
        $data = $request->validate(['message' => ['required', 'string', 'max:2000']]);
        DB::transaction(function () use ($request, $member, $data) {
            $member = User::lockForUpdate()->findOrFail($member->id);
            $this->authorizeWarning($request->user(), $member);
            $warning = MemberWarning::create([...$data, 'user_id' => $member->id, 'issued_by' => $request->user()->id]);
            MemberInbox::send($member->id, 'warning', 'A message from the moderation team', $data['message'], 'warning', $warning->id);
        }, 3);

        return back()->with('success', 'Warning sent. The member has been notified.');
    }

    private function authorizeWarning(User $actor, User $member): void
    {
        abort_unless($actor->canModerate() && ! $actor->is($member) && ($actor->isAdmin() || ! $member->canModerate()), 403);
    }
}
