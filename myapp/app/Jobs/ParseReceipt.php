<?php

namespace App\Jobs;

use App\Contracts\AiProvider;
use App\Models\Card;
use App\Models\Category;
use App\Models\Receipt;
use App\Models\Transaction;
use App\Services\Ai\AiErrors;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;
use Throwable;
use UnexpectedValueException;

/**
 * Reads one uploaded receipt into a draft. Nothing becomes a transaction here: the user confirms first.
 * A worker has no signed-in user, so the BelongsToUser scope is off and every query filters user_id by hand.
 * UnexpectedValueException messages are written for the user; any other error is mapped by AiErrors.
 */
class ParseReceipt implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 1;

    public int $timeout = 90;

    private const MAX_SIDE = 1600;

    public function __construct(public int $receiptId) {}

    public function handle(AiProvider $ai): void
    {
        $receipt = Receipt::withoutGlobalScopes()->find($this->receiptId);

        if (! $receipt || $receipt->status !== 'queued') {
            return;
        }

        $receipt->update(['status' => 'parsing']);
        $uid = $receipt->user_id;

        try {
            $path = $this->downscale($receipt);
            $cards = Card::withoutGlobalScopes()->where('user_id', $uid)->get(['id', 'name']);
            $categories = Category::withoutGlobalScopes()->where('user_id', $uid)->get(['id', 'name']);

            $raw = $ai->parseReceipt($path, $categories->pluck('name')->all());
            $draft = self::normalize($raw);

            $at = Date::parse($draft['occurred_at']);
            if ($at->gt(now()->addDay()) || $at->lt(now()->subYear())) {
                throw new UnexpectedValueException('The date on this receipt is in the future or over a year old. Add the expense manually.');
            }

            $category = $categories->first(fn ($c) => mb_strtolower($c->name) === mb_strtolower($raw['category']));
            $app = mb_strtolower(trim($raw['payment_app']));
            $card = $app === '' ? null : $cards->first(fn ($c) => str_contains($app, mb_strtolower($c->name)) || str_contains(mb_strtolower($c->name), $app));

            $draft += [
                'category_id' => $category?->id, 'category' => $category?->name,
                'card_id' => $card?->id,
                'duplicate_of' => $this->duplicates($uid, $draft, $at),
            ];

            $receipt->update(['status' => 'parsed', 'parsed_json' => $draft, 'confidence' => $draft['confidence'], 'error' => null]);
        } catch (UnexpectedValueException $e) {
            $this->markFailed($receipt, $e->getMessage());
        } catch (Throwable $e) {
            report($e);
            $this->markFailed($receipt, AiErrors::message($e));
        }
    }

    /** Last resort when the job dies outside handle() (timeout, crash). */
    public function failed(Throwable $e): void
    {
        report($e);

        if ($receipt = Receipt::withoutGlobalScopes()->find($this->receiptId)) {
            $this->markFailed($receipt, AiErrors::message($e));
        }
    }

    /**
     * Raw model strings to a draft: amount in centavos, date as Y-m-d.
     *
     * @param  array<string, mixed>  $raw
     */
    public static function normalize(array $raw): array
    {
        $amount = str_replace([',', '₱', ' '], '', (string) preg_replace('/php/i', '', (string) $raw['amount']));

        if (! is_numeric($amount) || (int) round((float) $amount * 100) <= 0) {
            throw new UnexpectedValueException('Could not find a valid amount on this receipt. Add the expense manually.');
        }

        $currency = strtoupper(trim((string) $raw['currency']));
        if (! in_array($currency, ['', 'PHP', '₱', 'PESO', 'PESOS'], true)) {
            throw new UnexpectedValueException('Only peso (PHP) receipts are supported. Add the expense manually.');
        }

        try {
            $date = trim((string) $raw['occurred_at']);
            $at = $date === '' ? throw new UnexpectedValueException : Date::parse($date);
        } catch (Throwable) {
            throw new UnexpectedValueException('Could not find a valid date on this receipt. Add the expense manually.');
        }

        return [
            'merchant' => mb_substr(trim((string) $raw['merchant']), 0, 120),
            'amount' => (int) round((float) $amount * 100),
            'currency' => 'PHP',
            'occurred_at' => $at->toDateString(),
            'reference' => trim((string) $raw['reference']) ?: null,
            'confidence' => max(0, min(1, (float) $raw['confidence'])),
        ];
    }

    /**
     * Same amount and merchant within 48 hours, or the same non-empty reference.
     *
     * @return list<int> ids of the earlier transactions
     */
    private function duplicates(int $uid, array $draft, CarbonInterface $at): array
    {
        return Transaction::withoutGlobalScopes()->where('user_id', $uid)->where('type', 'expense')
            ->where(function ($q) use ($draft, $at) {
                $q->where(fn ($q) => $q->where('amount', $draft['amount'])
                    ->whereRaw('lower(merchant) = ?', [mb_strtolower($draft['merchant'])])
                    ->whereBetween('occurred_at', [$at->subHours(48), $at->addHours(48)]));

                if ($draft['reference']) {
                    $q->orWhere('reference', $draft['reference']);
                }
            })->limit(5)->pluck('id')->all();
    }

    /** Re-encodes the stored image as JPEG with its longest side at most 1600px; returns the absolute path. */
    private function downscale(Receipt $receipt): string
    {
        $disk = Storage::disk('local');
        $src = @imagecreatefromstring((string) @file_get_contents($disk->path($receipt->path)));

        if (! $src) {
            throw new UnexpectedValueException('That file could not be read as an image.');
        }

        [$w, $h] = [imagesx($src), imagesy($src)];
        $scale = min(1, self::MAX_SIDE / max($w, $h));
        [$nw, $nh] = [max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale))];

        $out = imagecreatetruecolor($nw, $nh);
        imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255)); // flatten PNG transparency
        imagecopyresampled($out, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

        $new = preg_replace('/\.\w+$/', '.jpg', $receipt->path);
        imagejpeg($out, $disk->path($new), 85);

        if ($new !== $receipt->path) {
            $disk->delete($receipt->path);
            $receipt->update(['path' => $new]);
        }

        return $disk->path($new);
    }

    /** A receipt that cannot be read has no further use for its image. */
    private function markFailed(Receipt $receipt, string $message): void
    {
        if ($receipt->path) {
            Storage::disk('local')->delete($receipt->path);
        }

        $receipt->update(['status' => 'failed', 'error' => $message, 'path' => null]);
    }
}
