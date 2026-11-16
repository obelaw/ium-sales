<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Obelaw\Ium\Sales\Base\MigrationBase;

return new class extends MigrationBase {
    public function up(): void
    {
        Schema::create($this->prefix . 'customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable()->index();
            $table->string('phone')->nullable()->index();
            $table->string('company')->nullable();
            $table->string('tax_id')->nullable();
            $table->string('currency', 3)->default('USD');
            $table->string('price_tier')->nullable();
            $table->json('billing_address')->nullable();
            $table->json('shipping_address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->prefix . 'customers');
    }
};
