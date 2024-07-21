<?php

namespace App\Http\Controllers;

use App\Models\BabyAction;
use App\Models\BabyActionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BabyActionController extends Controller
{
    /**
     * Show add baby form
     */
    public function create(): Response
    {
        return Inertia::render('Parent/BabyActions/Create', [
            'babies' => auth()->user()->babies,
            'babyActionTypes' => BabyActionType::all()
        ]);
    }

    /**
     * Add baby
     */
    public function store(Request $request): RedirectResponse
    {
        BabyAction::create(
            [
                ...$request->all(),
            ]
        );

        return back();
    }

    /**
     * Show list of babies
     */
    public function show(): Response
    {
        $babyActions = BabyAction::query()->with(['baby', 'babyActionType'])
            ->whereHas('baby.user', fn(Builder $userQuery) => $userQuery->where('id', auth()->user()->id))
            ->orderBy('id', 'desc')
            ->get();
        return Inertia::render('Parent/BabyActions/Show', ['babyActions' => $babyActions]);
    }

    /**
     * Show edit baby form
     */
    public function edit(BabyAction $babyAction): Response
    {
        return Inertia::render('Parent/BabyActions/Edit', [
                'babies' => auth()->user()->babies,
                'babyActionTypes' => BabyActionType::all(),
            'babyAction' => $babyAction]);
    }
    /**
     * Update baby
     */
    public function update(Request $request, BabyAction $babyAction): RedirectResponse
    {
        $babyAction->update($request->all());

        return back();
    }
}
