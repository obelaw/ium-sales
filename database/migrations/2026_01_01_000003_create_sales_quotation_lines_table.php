<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Obelaw\Ium\Sales\Base\MigrationBase;

return new class extends MigrationBase {
    public function up(): void
    {
        Schema::create($this->prefix . 'quotation_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('quotation_id')->index();
            $table->unsignedInteger('line_number')->default(1);
            $table->nullableMorphs('orderable');
            $table->string('sku')->index();
            $table->string('name');
            $table->decimal('unit_price', 15, 4)->default(0);
            $table->decimal('quantity', 15, 4)->default(1);
            $table->decimal('discount_amount', 15, 4)->default(0);
            $table->decimal('tax_rate', 8, 4)->default(0);
            $table->decimal('tax_amount', 15, 4)->default(0);
            $table->decimal('total_amount', 15, 4)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('quotation_id')
                ->references('id')
                ->on($this->prefix . 'quotations')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->prefix . 'quotation_lines');
    }
};
