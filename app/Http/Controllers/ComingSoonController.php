<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\View\View;

final class ComingSoonController extends Controller
{
    public function __invoke(string $module): View
    {
        $titles = [
            'applications' => 'Applications',
            'workflow' => 'Workflow',
            'reports' => 'Reports',
            'settings' => 'Settings',
        ];

        abort_unless(isset($titles[$module]), 404);

        return view('coming-soon', [
            'title' => $titles[$module],
            'module' => $module,
        ]);
    }
}
