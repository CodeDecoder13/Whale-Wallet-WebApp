<?php

namespace App\Http\Controllers;

use App\Http\Requests\IncomeRequest;
use App\Models\IncomeSource;
use Illuminate\Http\RedirectResponse;

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

    public function destroy(int $income): RedirectResponse
    {
        IncomeSource::whereNotNull('month')->findOrFail($income)->forget();

        return back();
    }
}
