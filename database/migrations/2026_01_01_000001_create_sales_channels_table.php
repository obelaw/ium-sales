<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Obelaw\Ium\Sales\Base\MigrationBase;

return new class extends MigrationBase {
    public function up(): void
    {
        Schema::create($this->prefix . 'channels', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('type')->default('ecommerce'); // ecommerce, mobile, b2b, pos, manual, marketplace
            $table->string('currency', 3)->default('USD');
            $table->string('default_price_tier')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->prefix . 'channels');
    }
};
