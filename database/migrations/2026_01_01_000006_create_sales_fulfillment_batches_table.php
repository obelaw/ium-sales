<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Obelaw\Ium\Sales\Base\MigrationBase;

return new class extends MigrationBase {
    public function up(): void
    {
        Schema::create($this->prefix . 'fulfillment_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_number')->unique();
            $table->unsignedBigInteger('sales_order_id')->index();
            $table->string('sourcing_node_id')->index();
            $table->string('carrier_code')->nullable();
            $table->string('tracking_number')->nullable()->index();
            $table->string('service_level')->nullable();
            $table->string('status')->default('pending')->index(); // pending, allocated, released, picked, packed, shipped, delivered, cancelled
            $table->json('shipping_address')->nullable();
            $table->timestamp('promised_ship_date')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
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
        Schema::dropIfExists($this->prefix . 'fulfillment_batches');
    }
};
