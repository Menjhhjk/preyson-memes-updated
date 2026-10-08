<?php

namespace App\Http\Controllers;

use App\Models\CornerRequest;
use App\Models\PostingDay;
use App\Support\PowerCharges;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RewardController extends Controller
{
    public function index(Request $request): View
    {
        $member = $request->user()->refresh();

        return view('activity.rewards', [
            'member' => $member, 'days' => PostingDay::where('user_id', $member->id)->orderBy('day_number')->get()->keyBy('day_number'),
            'schedule' => config('engagement.rewards'), 'superBalance' => PowerCharges::balance($member, 'super'),
            'boostBalance' => PowerCharges::balance($member, 'boost'),
            'canRequestCorner' => ! CornerRequest::where('user_id', $member->id)->whereIn('status', ['pending', 'approved'])->exists(),
            'cornerRequests' => CornerRequest::where('user_id', $member->id)->latest('id')->paginate(5),
        ]);
    }
}
