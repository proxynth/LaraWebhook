<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Display the webhook dashboard
     */
    public function index(): View
    {
        $services = config('larawebhook.services');
        $serviceNames = array_keys($services);

        /** @var View */
        return view('larawebhook::dashboard', [
            'services' => $serviceNames,
        ]);
    }
}
