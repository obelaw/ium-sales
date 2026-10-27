<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Obelaw\Ium\Sales\Base\MigrationBase;

return new class extends MigrationBase {
    public function up(): void
    {
        Schema::create($this->prefix . 'fulfillment_batch_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fulfillment_batch_id')->index();
            $table->unsignedBigInteger('sales_order_line_id')->index();
            $table->decimal('quantity', 15, 4)->default(1);
            $table->timestamps();

            $table->foreign('fulfillment_batch_id')
                ->references('id')
                ->on($this->prefix . 'fulfillment_batches')
                ->cascadeOnDelete();

            $table->foreign('sales_order_line_id')
                ->references('id')
                ->on($this->prefix . 'order_lines')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->prefix . 'fulfillment_batch_lines');
    }
};
