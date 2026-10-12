<?php

namespace App\Http\Controllers;

use App\Services\LandingPageService;
use Illuminate\View\View;

class LandingController extends Controller
{
    public function __invoke(LandingPageService $landing): View
    {
        return view('landing.index', $landing->data());
    }
}
