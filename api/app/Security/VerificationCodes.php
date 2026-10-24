<?php

namespace App\Security;

use App\Enums\VerificationPurpose;
use App\Mail\VerificationCodeMail;
use App\Models\User;
use App\Models\VerificationCode;
use App\Security\Sms\SmsSender;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * 6-digit codes confirming that an email or a phone is the person's. A code
 * lives a few minutes, takes a few attempts and works once; a new one can be
 * asked for after a short pause.
 */
class VerificationCodes
{
    public function __construct(private readonly SmsSender $sms) {}

    public function send(User $user, VerificationPurpose $purpose, string $destination): void
    {
        $last = $this->latest($user, $purpose);
        $pause = (int) config('security.codes.resend_after_seconds');

        if ($last && $last->created_at && $last->created_at->gt(now()->subSeconds($pause)) && $last->consumed_at === null) {
            throw ValidationException::withMessages([
                'code' => ["Նոր կոդ կարելի է խնդրել {$pause} վայրկյանից։"],
            ]);
        }

        // Older codes stop working once a new one is out.
        VerificationCode::query()->where('user_id', $user->getKey())->where('purpose', $purpose)
            ->whereNull('consumed_at')->update(['consumed_at' => now()]);

        $code = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);

        VerificationCode::query()->create([
            'user_id' => $user->getKey(),
            'purpose' => $purpose,
            'destination' => $destination,
            'code_hash' => $this->hash($user, $code),
            'expires_at' => now()->addMinutes((int) config('security.codes.ttl_minutes')),
        ]);

        match ($purpose) {
            VerificationPurpose::Email => Mail::to($destination)->send(new VerificationCodeMail($user, $code)),
            VerificationPurpose::Phone => $this->sms->send($destination, "Servio: հաստատման կոդը՝ {$code}։ Մի փոխանցեք այն ոչ ոքի։"),
        };
    }

    /** The confirmed destination, or a validation error. */
    public function verify(User $user, VerificationPurpose $purpose, string $code): string
    {
        $record = $this->latest($user, $purpose);
        $invalid = fn () => ValidationException::withMessages(['code' => ['Կոդը սխալ է կամ ժամկետանց։']]);

        if (! $record || $record->consumed_at || $record->expires_at->isPast()) {
            throw $invalid();
        }

        if ($record->attempts >= (int) config('security.codes.attempts')) {
            $record->forceFill(['consumed_at' => now()])->save();
            throw ValidationException::withMessages(['code' => ['Չափազանց շատ փորձեր։ Խնդրեք նոր կոդ։']]);
        }

        if (! hash_equals($record->code_hash, $this->hash($user, preg_replace('/\D+/', '', $code) ?? ''))) {
            $record->increment('attempts');
            throw $invalid();
        }

        $record->forceFill(['consumed_at' => now()])->save();

        return $record->destination;
    }

    /** Where the last unexpired code went, if one is waiting (e.g. the phone being confirmed). */
    public function pendingDestination(User $user, VerificationPurpose $purpose): ?string
    {
        $record = $this->latest($user, $purpose);

        return $record && ! $record->consumed_at && $record->expires_at->isFuture() ? $record->destination : null;
    }

    private function latest(User $user, VerificationPurpose $purpose): ?VerificationCode
    {
        return VerificationCode::query()->where('user_id', $user->getKey())->where('purpose', $purpose)
            ->latest('id')->first();
    }

    /** Keyed with APP_KEY and bound to the person: a table dump can't be brute-forced offline. */
    private function hash(User $user, string $code): string
    {
        return hash_hmac('sha256', $user->getKey().'|'.$code, (string) config('app.key'));
    }
}
