<?php

declare(strict_types=1);

namespace Larafied\Http\Controllers;

use Illuminate\Http\Response;

final class DashboardController extends Controller
{
    public function index(): Response
    {
        return response()->view('larafied::app', [
            'config' => [
                'prefix'  => config('larafied.prefix', 'larafied'),
                'title'   => config('larafied.title', 'Larafied'),
                'version' => '1.0.0',
            ],
        ]);
    }
}
