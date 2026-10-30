<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Obelaw\Ium\Sales\Base\MigrationBase;

return new class extends MigrationBase
{
    public function up(): void
    {
        Schema::create($this->prefix . 'order_payments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('sales_order_id')->index();
            $table->string('payment_reference')->index();
            $table->string('payment_method')->default('bank_transfer');
            $table->decimal('amount', 15, 4);
            $table->bigInteger('amount_minor');
            $table->string('currency', 3)->default('USD');
            $table->string('status', 30)->default('completed')->index();
            $table->timestamp('paid_at')->nullable()->index();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('sales_order_id')
                ->references('id')
                ->on($this->prefix . 'orders')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->prefix . 'order_payments');
    }
};
