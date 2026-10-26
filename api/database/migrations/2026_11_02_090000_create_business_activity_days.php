<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The days a business's own people worked in the admin: the platform level
 * shows how active a business is without opening its data
 * (App\Platform\BusinessActivity). One row per business and day, nothing
 * about what was done. Filled back from past sign-ins and sessions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_activity_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->date('day');
            $table->timestamp('created_at')->nullable();

            $table->unique(['tenant_id', 'day']);
        });

        $this->fillFromHistory();
    }

    public function down(): void
    {
        Schema::dropIfExists('business_activity_days');
    }

    /** Sign-ins and the last use of each session of the business's people (platform staff left out). */
    private function fillFromHistory(): void
    {
        $members = DB::table('tenant_user')
            ->join('users', 'users.id', '=', 'tenant_user.user_id')
            ->where('users.is_super_admin', false)
            ->where('users.is_platform_moderator', false)
            ->get(['tenant_user.tenant_id', 'tenant_user.user_id']);

        if ($members->isEmpty()) {
            return;
        }

        $dates = collect();
        $userIds = $members->pluck('user_id')->unique()->values();

        DB::table('security_events')
            ->where('event', 'login_succeeded')
            ->whereIn('user_id', $userIds)
            ->whereNotNull('created_at')
            ->get(['user_id', 'created_at'])
            ->each(fn ($row) => $dates->push([$row->user_id, $row->created_at]));

        DB::table('personal_access_tokens')
            ->where('tokenable_type', 'App\\Models\\User')
            ->whereIn('tokenable_id', $userIds)
            ->get(['tokenable_id', 'created_at', 'last_used_at'])
            ->each(function ($row) use ($dates) {
                foreach ([$row->created_at, $row->last_used_at] as $at) {
                    if ($at !== null) {
                        $dates->push([$row->tokenable_id, $at]);
                    }
                }
            });

        $tenantsOf = $members->groupBy('user_id')->map(fn ($rows) => $rows->pluck('tenant_id'));
        $now = now();

        $rows = $dates
            ->flatMap(fn (array $entry) => ($tenantsOf[$entry[0]] ?? collect())->map(fn ($tenantId) => [
                'tenant_id' => $tenantId,
                'day' => Carbon::parse($entry[1])->toDateString(),
                'created_at' => $now,
            ]))
            ->unique(fn (array $row) => $row['tenant_id'].':'.$row['day'])
            ->values();

        foreach ($rows->chunk(500) as $chunk) {
            DB::table('business_activity_days')->insertOrIgnore($chunk->all());
        }
    }
};
