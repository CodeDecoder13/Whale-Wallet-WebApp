<?php

namespace App\Http\Controllers;

use App\Http\Requests\IncomeRequest;
use App\Models\IncomeSource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Income actually received in a month. Salary varies and replaces the onboarding baseline for that month; side income is added on top. */
class IncomeController extends Controller
{
    /** The proof receipt stays on the private disk as the record of this salary. */
    public function store(IncomeRequest $request): RedirectResponse
    {
        $d = $request->validated();

        IncomeSource::record($d);

        return back();
    }

    /** "Not paid yet this month": the baseline salary counts from next month, so left to spend is 0 until then. */
    public function waiting(Request $request): RedirectResponse
    {
        $waiting = $request->validate(['waiting' => ['required', 'boolean']])['waiting'];

        $request->user()->forceFill(['income_from' => $waiting ? now()->startOfMonth()->addMonth()->format('Y-m') : null])->save();

        return back();
    }

    public function destroy(int $income): RedirectResponse
    {
        IncomeSource::whereNotNull('month')->findOrFail($income)->forget();

        return back();
    }
}
