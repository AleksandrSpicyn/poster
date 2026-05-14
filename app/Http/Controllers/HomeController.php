<?php

namespace App\Http\Controllers;

use App\Data\HomepageData;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Welcome', [
            'homepage' => HomepageData::fromApprovedContent()->toArray(),
        ]);
    }
}
