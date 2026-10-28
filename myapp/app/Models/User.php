<?php

namespace App\Models;

use App\Mail\VerifyCode;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'pin', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /** A 6-digit code instead of a link: sent on sign-up and by "Resend" (Fortify calls this for both). */
    public function sendEmailVerificationNotification(): void
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Cache::put("otp:{$this->id}", Hash::make($code), now()->addMinutes(10));
        Cache::forget("otp-tries:{$this->id}");

        // sent right after the page loads, in the same process: no queue worker or scheduler is needed, and the page does not wait on the mail server
        $user = $this;
        dispatch(fn () => Mail::to($user)->send(new VerifyCode($user->name, $code)))->afterResponse();
    }

    /** Rows cascade in the database; files and polymorphic tokens do not. */
    protected static function booted(): void
    {
        static::deleting(function (self $user) {
            $user->tokens()->delete();
            Storage::disk()->delete($user->receipts()->withoutGlobalScopes()->whereNotNull('path')->pluck('path')->all());
        });
    }

    public function incomeSources(): HasMany
    {
        return $this->hasMany(IncomeSource::class);
    }

    public function cards(): HasMany
    {
        return $this->hasMany(Card::class);
    }

    public function pods(): HasMany
    {
        return $this->hasMany(Pod::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function recommendations(): HasMany
    {
        return $this->hasMany(Recommendation::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class);
    }

    public function chatThreads(): HasMany
    {
        return $this->hasMany(ChatThread::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function recurringItems(): HasMany
    {
        return $this->hasMany(RecurringItem::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'ai_consent_at' => 'datetime',
            'keep_receipts' => 'boolean',
            'digest_enabled' => 'boolean',
            'release_emails' => 'boolean',
            'suspended_at' => 'datetime',
            'last_admin_gate_at' => 'datetime',
            'digest_sent_on' => 'date',
            'pay_dates' => 'array',
        ];
    }
}
