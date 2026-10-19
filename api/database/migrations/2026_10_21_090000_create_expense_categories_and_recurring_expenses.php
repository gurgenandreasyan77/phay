<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The kinds of a business's expenses (taxes, rent, salaries...): the
        // ready-made ones (key set, see ExpenseCategories::DEFAULTS) and its
        // own. in_profit: whether the net profit subtracts them (a loan
        // repayment or the owner's withdrawal doesn't).
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('key', 40)->nullable();
            $table->string('name', 100);
            $table->boolean('in_profit')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'key']);
            $table->index(['tenant_id', 'sort_order']);
        });

        // Expenses that repeat (monthly rent, quarterly taxes): counted on
        // each due date from starts_on, never stored as rows.
        Schema::create('recurring_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('expense_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 150);
            $table->decimal('amount', 14, 2);
            // TablePeriod: week, month, quarter or year.
            $table->string('frequency', 16);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'starts_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_expenses');
        Schema::dropIfExists('expense_categories');
    }
};
