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
        /** @var array<string, array<string, mixed>> $services */
        $services = config('larawebhook.services', []);
        $serviceNames = array_keys($services);

        /** @var view-string $viewName */
        $viewName = 'larawebhook::dashboard';

        return view($viewName, [
            'services' => $serviceNames,
        ]);
    }
}
