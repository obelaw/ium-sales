<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Obelaw\Ium\Sales\Base\MigrationBase;

return new class extends MigrationBase {
    public function up(): void
    {
        Schema::create($this->prefix . 'order_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sales_order_id')->index();
            $table->unsignedInteger('line_number')->default(1);
            $table->nullableMorphs('orderable');
            $table->string('sku')->index();
            $table->string('name');
            $table->decimal('unit_price', 15, 4)->default(0);
            $table->decimal('quantity', 15, 4)->default(1);
            $table->decimal('allocated_quantity', 15, 4)->default(0);
            $table->decimal('shipped_quantity', 15, 4)->default(0);
            $table->decimal('returned_quantity', 15, 4)->default(0);
            $table->decimal('discount_amount', 15, 4)->default(0);
            $table->decimal('tax_rate', 8, 4)->default(0);
            $table->decimal('tax_amount', 15, 4)->default(0);
            $table->decimal('total_amount', 15, 4)->default(0);
            $table->string('status')->default('pending'); // pending, allocated, released, shipped, delivered, cancelled, returned
            $table->string('sourcing_node_id')->nullable()->index();
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
        Schema::dropIfExists($this->prefix . 'order_lines');
    }
};
