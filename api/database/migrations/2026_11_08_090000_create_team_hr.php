<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The team side of a business (App\Team): who is away when (vacations,
 * days off, sick leave), the jobs it is hiring for and the candidates of
 * each, and what a new employee still has to go through (onboarding). The
 * HR agent Anya (key "hr") prepares changes of these as cards the owner
 * applies (hr_changes), like the table agent's.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('email', 150)->nullable()->after('phone');
            // First working day; birthday (the bell reminds of it).
            $table->date('hired_on')->nullable()->after('email');
            $table->date('birth_date')->nullable()->after('hired_on');
            // [{title, done}]: what a new employee still has to do or get.
            $table->json('onboarding')->nullable()->after('birth_date');
        });

        Schema::create('employee_absences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            // vacation, day_off, sick, unpaid, other (App\Team\AbsenceKind).
            $table->string('kind', 16);
            $table->date('starts_on');
            // Inclusive: a day off has starts_on = ends_on.
            $table->date('ends_on');
            $table->string('note', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'starts_on', 'ends_on']);
        });

        Schema::create('job_openings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('title', 150);
            // The posting: duties, requirements, conditions (plain text).
            $table->text('description')->nullable();
            $table->string('salary', 100)->nullable();
            // full_time, part_time, shift, freelance.
            $table->string('employment', 16)->nullable();
            // open, paused, closed.
            $table->string('status', 16)->default('open');
            $table->timestamps();
        });

        Schema::create('candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_opening_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 100);
            $table->string('phone', 50)->nullable();
            $table->string('email', 150)->nullable();
            // Where they came from: staff.am, Instagram, a friend...
            $table->string('source', 100)->nullable();
            // new, screening, interview, offer, hired, rejected (App\Team\CandidateStage).
            $table->string('stage', 16)->default('new');
            // The business's local time, "Y-m-d H:i:s".
            $table->dateTime('interview_at')->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->text('notes')->nullable();
            // The employee they became.
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'stage']);
            $table->index(['tenant_id', 'interview_at']);
        });

        Schema::create('hr_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // What the card says: title and a line per change.
            $table->string('title', 250);
            // [{action: create|update|delete, model, id, before, after}].
            $table->json('ops');
            // The ids made by create ops, by op index.
            $table->json('result')->nullable();
            // planned, applied, undone or discarded.
            $table->string('status', 16);
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('undone_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        $now = now();
        DB::table('module_prices')->insertOrIgnore([
            'feature_key' => 'agent:hr',
            'price' => 2900,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        foreach (DB::table('subscriptions')->where('unlimited', true)->pluck('tenant_id') as $tenantId) {
            DB::table('tenant_agents')->insertOrIgnore([
                'tenant_id' => $tenantId,
                'agent_key' => 'hr',
                'active' => true,
                'activated_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_changes');
        Schema::dropIfExists('candidates');
        Schema::dropIfExists('job_openings');
        Schema::dropIfExists('employee_absences');
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['email', 'hired_on', 'birth_date', 'onboarding']);
        });
        DB::table('tenant_agents')->where('agent_key', 'hr')->delete();
        DB::table('module_prices')->where('feature_key', 'agent:hr')->delete();
    }
};
