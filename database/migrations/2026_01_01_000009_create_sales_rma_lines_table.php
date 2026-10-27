<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Obelaw\Ium\Sales\Base\MigrationBase;

return new class extends MigrationBase {
    public function up(): void
    {
        Schema::create($this->prefix . 'rma_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rma_id')->index();
            $table->unsignedBigInteger('sales_order_line_id')->index();
            $table->decimal('quantity_requested', 15, 4)->default(1);
            $table->decimal('quantity_received', 15, 4)->default(0);
            $table->decimal('quantity_approved', 15, 4)->default(0);
            $table->string('disposition')->default('pending'); // pending, restock, scrap, repair
            $table->decimal('refund_amount', 15, 4)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('rma_id')
                ->references('id')
                ->on($this->prefix . 'rmas')
                ->cascadeOnDelete();

            $table->foreign('sales_order_line_id')
                ->references('id')
                ->on($this->prefix . 'order_lines')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->prefix . 'rma_lines');
    }
};
