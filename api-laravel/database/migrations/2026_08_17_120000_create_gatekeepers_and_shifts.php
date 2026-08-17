<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_companies', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('cnpj', 18)->nullable()->unique();
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('gatekeepers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('security_company_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('name')->index();
            $table->string('registration')->unique();
            $table->string('cpf', 14)->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('gatekeeper_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gatekeeper_id')->constrained()->restrictOnDelete();
            $table->foreignId('access_location_id')->constrained()->restrictOnDelete();
            $table->timestamp('started_at')->index();
            $table->timestamp('break_started_at')->nullable();
            $table->timestamp('break_ended_at')->nullable();
            $table->timestamp('ended_at')->nullable()->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['gatekeeper_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gatekeeper_shifts');
        Schema::dropIfExists('gatekeepers');
        Schema::dropIfExists('security_companies');
    }
};
