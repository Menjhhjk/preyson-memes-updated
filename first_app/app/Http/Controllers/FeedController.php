<?php

namespace App\Http\Controllers;

use App\Support\PostListing;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FeedController extends Controller
{
    public function index(Request $request, PostListing $listing): View
    {
        return view('blog', $listing->data($request));
    }
}
