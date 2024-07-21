<?php

namespace App\Http\Controllers;

use App\Models\Baby;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class BabyController extends Controller
{
    /**
     * Show list of babies
     */
    public function show(): Response
    {
        $babies = Baby::whereBelongsTo(Auth::user())->orderBy('id',  'desc')->get();
        return Inertia::render('Parent/Baby/Show', ['babies' => $babies]);
    }

    /**
     * Show add baby form
     */
    public function create(): Response
    {
        return Inertia::render('Parent/Baby/Create');
    }

    /**
     * Show edit baby form
     */
    public function edit(Baby $baby): Response
    {
        return Inertia::render('Parent/Baby/Edit', ['baby' => $baby]);
    }

    /**
     * Add baby
     */
    public function store(Request $request): RedirectResponse
    {
        Baby::create(
            [
                ...$request->all(),
                'user_id' => Auth::id(),
            ]
        );

        return back();
    }

    /**
     * Update baby
     */
    public function update(Request $request, Baby $baby): RedirectResponse
    {
        $baby->update($request->all());

        return back();
    }
}
