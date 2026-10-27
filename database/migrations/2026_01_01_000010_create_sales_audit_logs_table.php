<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Obelaw\Ium\Sales\Base\MigrationBase;

return new class extends MigrationBase {
    public function up(): void
    {
        Schema::create($this->prefix . 'audit_logs', function (Blueprint $table) {
            $table->id();
            $table->morphs('auditable');
            $table->string('action'); // created, confirmed, allocated, released, split, cancelled, rma_requested, rma_authorized, etc.
            $table->string('actor_type')->nullable();
            $table->string('actor_id')->nullable();
            $table->string('origin_channel')->nullable();
            $table->json('before_payload')->nullable();
            $table->json('after_payload')->nullable();
            $table->string('reason_code')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->prefix . 'audit_logs');
    }
};
