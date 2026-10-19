<?php

namespace App\Http\Controllers\Api;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\ParseQuickEntry;
use App\Actions\PayRecurring;
use App\Actions\SaveOnboarding;
use App\Http\Controllers\Controller;
use App\Http\Requests\IncomeRequest;
use App\Http\Requests\OnboardingRequest;
use App\Http\Requests\PayRequest;
use App\Http\Requests\QuickEntryRequest;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Card;
use App\Models\IncomeSource;
use App\Models\Receipt;
use App\Models\RecurringItem;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Http\Resources\UserResource;

/** The web app's redirect-style actions (profile, payday, onboarding, pay a bill, income, quick entry) answered in JSON. */
class AccountController extends Controller
{
    public function register(Request $request, CreateNewUser $create): JsonResponse
    {
        $device = $request->validate(['device_name' => ['required', 'string', 'max:80']])['device_name'];
        $user = $create->create($request->only('name', 'email', 'password', 'password_confirmation'));
        event(new Registered($user)); // sends the verification email; most routes need a verified address

        return response()->json([
            'token' => $user->createToken($device)->plainTextToken,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
        ], 201);
    }

    public function profile(ProfileUpdateRequest $request): UserResource
    {
        $user = $request->user()->fill($request->validated());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }
        $user->save();

        return new UserResource($user);
    }

    /** A small JPEG data URI, or null to remove the picture. */
    public function avatar(Request $request): UserResource
    {
        $data = $request->validate(['avatar' => ['nullable', 'string', 'max:40000', 'starts_with:data:image/jpeg;base64,']]);
        $request->user()->forceFill(['avatar' => $data['avatar']])->save();

        return new UserResource($request->user());
    }

    public function payday(Request $request): UserResource
    {
        $data = $request->validate([
            'pay_dates' => ['required', 'array', 'min:1', 'max:2'],
            'pay_dates.*' => ['integer', 'between:1,31', 'distinct'],
        ]);
        $days = array_map('intval', $data['pay_dates']);
        sort($days);
        $request->user()->forceFill(['pay_dates' => $days, 'pay_cycle' => count($days) > 1 ? 'semi_monthly' : 'monthly'])->save();

        return new UserResource($request->user());
    }

    public function keepReceipts(Request $request): UserResource
    {
        $request->user()->forceFill(['keep_receipts' => $request->boolean('keep')])->save();

        return new UserResource($request->user());
    }

    /** The daily Whale tips email: opt in or out, and the Philippine-time hour (HH:MM, 09:00 by default). */
    public function digest(Request $request): UserResource
    {
        $data = $request->validate([
            'digest_enabled' => ['required', 'boolean'],
            'digest_time' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
        ]);
        $request->user()->forceFill([...$data, 'digest_sent_on' => null])->save();

        return new UserResource($request->user());
    }

    public function password(PasswordUpdateRequest $request): JsonResponse
    {
        $request->user()->update(['password' => $request->password]);

        return response()->json(null, 204);
    }

    public function onboard(OnboardingRequest $request, SaveOnboarding $save): JsonResponse
    {
        abort_if(Card::exists(), 409, 'Onboarding is already done.');
        $save->handle($request->user(), $request->validated());

        return response()->json(null, 201);
    }

    /** Marks one bill, subscription or loan payment as made. */
    public function pay(PayRequest $request, int $recurringItem, PayRecurring $pay): TransactionResource
    {
        $d = $request->validated();

        return new TransactionResource($pay->handle(RecurringItem::findOrFail($recurringItem), $d['card_id'], $d['amount'], $d['paid_on']));
    }

    /** "Jollibee 250 gcash" -> a draft { merchant, amount, card_id }; nothing is saved. */
    public function quick(QuickEntryRequest $request, ParseQuickEntry $parse): JsonResponse
    {
        $draft = $parse->handle($request->validated('text'));
        abort_unless($draft, 422, 'Try something like "Jollibee 250".');

        $card = $draft['card'] ? Card::whereRaw('lower(name) = ?', [mb_strtolower($draft['card'])])->first() : null;

        return response()->json(['merchant' => $draft['merchant'], 'amount' => $draft['amount'], 'card_id' => $card?->id]);
    }

    /** A received salary, backed by a parsed proof receipt (purpose=income). Same rules as the web form. */
    public function income(IncomeRequest $request): JsonResponse
    {
        $d = $request->validated();

        $row = DB::transaction(function () use ($d) {
            $receipt = Receipt::lockForUpdate()->findOrFail($d['receipt_id']);
            abort_unless($receipt->status === 'parsed' && $receipt->purpose === 'income', 409, 'This proof was already handled.');
            $receipt->update(['status' => 'confirmed']);

            return IncomeSource::create([
                'name' => $d['name'] ?? null ?: 'Salary',
                'amount' => $d['amount'],
                'received_on' => $d['received_on'],
                'month' => substr($d['received_on'], 0, 7),
                'reference' => $d['reference'],
                'receipt_id' => $receipt->id,
            ]);
        });

        return response()->json($row->only(['id', 'name', 'amount', 'month', 'reference']) + ['received_on' => $row->received_on?->toDateString()], 201);
    }

    public function removeIncome(int $income): JsonResponse
    {
        $row = IncomeSource::whereNotNull('month')->findOrFail($income);

        if ($receipt = $row->receipt_id ? Receipt::find($row->receipt_id) : null) {
            $receipt->path && Storage::disk()->delete($receipt->path);
            $receipt->delete();
        }
        $row->delete();

        return response()->json(null, 204);
    }

    /** Scanned expense receipts still waiting to be confirmed. */
    public function receipts(): JsonResponse
    {
        return response()->json(
            Receipt::where('purpose', 'expense')->where('status', '!=', 'confirmed')->latest('id')->limit(10)->get()->map->toClient()->values()
        );
    }
}
