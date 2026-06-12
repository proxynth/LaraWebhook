<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Http\Controllers;

use Illuminate\Routing\Controller;
use Proxynth\Larawebhook\Http\Controllers\view;

class DashboardController extends Controller
{
    /**
     * Display the webhook dashboard
     */
    public function index(): \Illuminate\View\View
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
