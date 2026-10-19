<?php

namespace App\Http\Controllers;

use App\Actions\FundPod;
use App\Http\Requests\FundPodRequest;
use App\Http\Requests\PodRequest;
use App\Models\Card;
use App\Models\Pod;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PodController extends Controller
{
    public function index(): Response
    {
        $pods = Pod::orderBy('name')->get();

        // money added to or taken from each pod, latest 10, for the detail panel
        $moves = Transaction::with('card:id,name')->where('type', 'transfer')
            ->where(fn ($q) => $q->whereIn('to_pod_id', $pods->modelKeys())->orWhereIn('from_pod_id', $pods->modelKeys()))
            ->latest('occurred_at')->latest('id')->limit(300)->get()->groupBy(fn ($t) => $t->to_pod_id ?? $t->from_pod_id);

        return Inertia::render('pods/Index', [
            'pods' => $pods->map(fn ($p) => [...$p->toArray(), 'transfers' => ($moves[$p->id] ?? collect())->take(10)->map(fn ($t) => [
                'id' => $t->id, 'amount' => $t->amount, 'in' => $t->to_pod_id === $p->id, 'card' => $t->card?->name,
                'on' => $t->occurred_at->toDateString(), 'reference' => $t->reference,
            ])->values()]),
            'cards' => Card::orderBy('name')->get(['id', 'name', 'balance']),
            'consented' => (bool) request()->user()->ai_consent_at,
        ]);
    }

    /** Moves money from a card into the pod, with the reference number and the receipt that prove it. */
    public function fund(FundPodRequest $request, Pod $pod, FundPod $fund): RedirectResponse
    {
        $fund->handle($pod, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Money added to the pod.']);

        return back();
    }

    public function store(PodRequest $request): RedirectResponse
    {
        Pod::create($request->validated());

        return back();
    }

    public function update(PodRequest $request, Pod $pod): RedirectResponse
    {
        $pod->update($request->validated());

        return back();
    }

    public function destroy(Pod $pod): RedirectResponse
    {
        $pod->delete();

        return back();
    }
}
