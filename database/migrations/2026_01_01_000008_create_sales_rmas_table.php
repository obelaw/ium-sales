<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Obelaw\Ium\Sales\Base\MigrationBase;

return new class extends MigrationBase {
    public function up(): void
    {
        Schema::create($this->prefix . 'rmas', function (Blueprint $table) {
            $table->id();
            $table->string('rma_number')->unique();
            $table->unsignedBigInteger('sales_order_id')->index();
            $table->nullableMorphs('customer');
            $table->string('status')->default('draft')->index(); // draft, pending_authorization, authorized, received, approved_for_credit, rejected, closed
            $table->string('reason_code');
            $table->text('reason_description')->nullable();
            $table->string('return_type')->default('refund'); // refund, replacement, store_credit
            $table->timestamp('authorized_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('closed_at')->nullable();
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
        Schema::dropIfExists($this->prefix . 'rmas');
    }
};
