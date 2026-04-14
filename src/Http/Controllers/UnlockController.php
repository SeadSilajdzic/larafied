<?php

declare(strict_types=1);

namespace Larafied\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

final class UnlockController extends Controller
{
    public function show(): View
    {
        return view('larafied::unlock');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        if ($request->input('password') !== config('larafied.password')) {
            return back()->withErrors(['password' => 'Incorrect password.']);
        }

        $request->session()->put('larafied_unlocked', true);

        return redirect()->route('larafied.dashboard');
    }
}
