<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PremiumController extends Controller
{
    public function index(Request $request): View
    {
        return view('premium', ['member' => $request->user()]);
    }

    public function checkout(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'intent' => ['required', Rule::in(['subscribe', 'donate'])],
        ]);
        if ($data['intent'] === 'donate') {
            return to_route('premium.index')->with('success', 'Thank you, '.$data['name'].'! Your imaginary donation has warmed our very real hearts. No money was collected.');
        }

        $activated = DB::transaction(function () use ($request) {
            $member = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            if ($member->hasPremium()) {
                return false;
            }
            $member->forceFill(['premium_expires_at' => now()->addDays(30)])->save();

            return true;
        });

        return to_route('premium.index')->with('success', $activated
            ? 'Your simulated Premium is active for 30 days! No payment was taken and there is no automatic renewal.'
            : 'Your Premium is already active. Enjoy it until its expiry date; extra clicks do not extend the 30 days.');
    }
}
