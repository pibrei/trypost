<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_data_deletion_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('confirmation_code')->unique();
            $table->string('meta_user_id')->index();
            $table->string('status');
            $table->json('affected_accounts')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_data_deletion_requests');
    }
};
