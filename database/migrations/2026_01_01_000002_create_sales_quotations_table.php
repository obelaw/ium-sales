<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Obelaw\Ium\Sales\Base\MigrationBase;

return new class extends MigrationBase {
    public function up(): void
    {
        Schema::create($this->prefix . 'quotations', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->nullableMorphs('customer');
            $table->string('channel_code')->nullable()->index();
            $table->string('currency', 3)->default('USD');
            $table->string('status')->default('draft');
            $table->timestamp('valid_until')->nullable();
            $table->decimal('subtotal', 15, 4)->default(0);
            $table->decimal('discount_total', 15, 4)->default(0);
            $table->decimal('tax_total', 15, 4)->default(0);
            $table->decimal('shipping_total', 15, 4)->default(0);
            $table->decimal('grand_total', 15, 4)->default(0);
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->prefix . 'quotations');
    }
};
