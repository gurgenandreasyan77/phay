<?php

namespace App\Billing;

use App\Models\BalanceTransaction;
use App\Models\BusinessBalance;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\NotificationType;
use App\Services\SupportChat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A business's balance in dram and its ledger. There is no payment gateway:
 * the business sends a receipt in its chat with the platform and the super
 * admin tops the balance up; then the business pays from it (App\Billing\
 * BalancePayments), or the platform renews what ends from it on its own.
 *
 * Every change locks the balance row and writes one ledger row with the
 * balance after it, in the same transaction, so the two always agree and
 * the balance never goes below zero.
 */
class Balance
{
    public function __construct(private readonly SupportChat $chat) {}

    /** The business's balance, made (empty, auto-renewing) on first use. */
    public function for(Tenant $tenant): BusinessBalance
    {
        return BusinessBalance::query()->firstOrCreate(['tenant_id' => $tenant->id]);
    }

    public function amount(Tenant $tenant): int
    {
        return (int) (BusinessBalance::query()->where('tenant_id', $tenant->id)->value('amount') ?? 0);
    }

    public function setAutoRenew(Tenant $tenant, bool $on): BusinessBalance
    {
        $balance = $this->for($tenant);
        $balance->forceFill(['auto_renew' => $on])->save();

        return $balance;
    }

    /** The super admin puts money the business sent (a receipt in the chat) on its balance. */
    public function topUp(Tenant $tenant, int $amount, ?string $note, User $by): BalanceTransaction
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => ['Գումարը պետք է լինի դրական։']]);
        }

        $transaction = $this->write($tenant, BalanceTransaction::TOP_UP, $amount, [
            'description' => 'Հաշվեկշռի համալրում',
            'note' => $note,
            'user_id' => $by->id,
        ]);

        $this->chat->notify($tenant, sprintf(
            'Ձեր հաշվեկշիռը համալրվել է %s դր-ով։ Հաշվեկշիռը՝ %s դր։ Դրանից կարող եք վճարել բաժանորդագրության, մոդուլների և AI աշխատակիցների համար, իսկ ժամկետները կերկարաձգվեն ինքնաշխատ։',
            self::money($amount),
            self::money($transaction->balance_after),
        ), [
            'type' => NotificationType::Subscription,
            'title' => sprintf('Հաշվեկշիռը համալրվել է +%s դր', self::money($amount)),
            'url' => '/admin',
        ]);

        return $transaction;
    }

    /**
     * The super admin fixes the balance (a top-up entered wrong): a signed
     * amount, never below zero. The business is not told.
     */
    public function correct(Tenant $tenant, int $amount, string $note, User $by): BalanceTransaction
    {
        if ($amount === 0) {
            throw ValidationException::withMessages(['amount' => ['Գումարը չի կարող լինել զրո։']]);
        }

        return $this->write($tenant, BalanceTransaction::CORRECTION, $amount, [
            'description' => 'Ճշգրտում',
            'note' => $note,
            'user_id' => $by->id,
        ]);
    }

    /**
     * Takes money for something the business buys. Fails (422, `balance`)
     * when the balance doesn't cover it; call inside the transaction that
     * makes what was bought, so both happen or neither.
     *
     * @param  array<string, mixed>  $meta
     */
    public function charge(
        Tenant $tenant,
        int $amount,
        string $purpose,
        string $description,
        ?Model $reference = null,
        ?User $by = null,
        bool $automatic = false,
        array $meta = [],
    ): BalanceTransaction {
        return $this->write($tenant, BalanceTransaction::CHARGE, -$amount, [
            'purpose' => $purpose,
            'description' => $description,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'meta' => $meta === [] ? null : $meta,
            'automatic' => $automatic,
            'user_id' => $by?->id,
        ]);
    }

    /**
     * A payment paid from the balance was taken back (entered by mistake):
     * what it charged goes back on the balance. Nothing for one paid otherwise.
     */
    public function refundFor(Model $payment, ?User $by = null): ?BalanceTransaction
    {
        $charges = BalanceTransaction::query()
            ->where('reference_type', $payment->getMorphClass())
            ->where('reference_id', $payment->getKey());

        $charged = -(int) (clone $charges)->where('type', BalanceTransaction::CHARGE)->sum('amount');
        $refunded = (int) (clone $charges)->where('type', BalanceTransaction::REFUND)->sum('amount');
        $back = $charged - $refunded;

        if ($back <= 0) {
            return null;
        }

        $tenant = Tenant::query()->findOrFail($payment->getAttribute('tenant_id'));

        return $this->write($tenant, BalanceTransaction::REFUND, $back, [
            'purpose' => (clone $charges)->where('type', BalanceTransaction::CHARGE)->value('purpose'),
            'description' => 'Վճարումը չեղարկվեց. գումարը վերադարձվեց հաշվեկշռին',
            'reference_type' => $payment->getMorphClass(),
            'reference_id' => $payment->getKey(),
            'user_id' => $by?->id,
        ]);
    }

    /** Throws the same 422 a charge would when the balance doesn't cover the amount. */
    public function ensureCovers(Tenant $tenant, int $amount): void
    {
        $have = $this->amount($tenant);
        if ($have < $amount) {
            throw self::short($amount, $have);
        }
    }

    /** @param  array<string, mixed>  $attributes */
    private function write(Tenant $tenant, string $type, int $amount, array $attributes): BalanceTransaction
    {
        return DB::transaction(function () use ($tenant, $type, $amount, $attributes) {
            $this->for($tenant);
            $balance = BusinessBalance::query()->where('tenant_id', $tenant->id)->lockForUpdate()->firstOrFail();
            $after = $balance->amount + $amount;

            if ($after < 0) {
                throw $type === BalanceTransaction::CHARGE
                    ? self::short(-$amount, $balance->amount)
                    : ValidationException::withMessages(['amount' => [sprintf('Հաշվեկշիռը չի կարող դառնալ բացասական (հիմա՝ %s դր)։', self::money($balance->amount))]]);
            }

            $balance->forceFill(['amount' => $after])->save();

            return BalanceTransaction::create([
                ...$attributes,
                'tenant_id' => $tenant->id,
                'type' => $type,
                'amount' => $amount,
                'balance_after' => $after,
            ]);
        });
    }

    private static function short(int $need, int $have): ValidationException
    {
        return ValidationException::withMessages(['balance' => [sprintf(
            'Հաշվեկշիռը բավարար չէ. անհրաժեշտ է %s դր, հաշվեկշռում՝ %s դր։ Համալրելու համար ուղարկեք վճարման անդորրագիրը չատում։',
            self::money($need),
            self::money($have),
        )]]);
    }

    public static function money(int $amount): string
    {
        return number_format($amount, 0, '.', ' ');
    }
}
