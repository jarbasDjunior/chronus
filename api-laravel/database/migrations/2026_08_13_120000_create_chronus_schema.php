<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->string('slug')->unique();
            $t->timestamps();
        });
        Schema::create('permissions', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->timestamps();
        });
        Schema::create('permission_role', function (Blueprint $t) {
            $t->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $t->foreignId('role_id')->constrained()->cascadeOnDelete();
            $t->primary(['permission_id', 'role_id']);
        });
        Schema::table('users', function (Blueprint $t) {
            $t->foreignId('role_id')->nullable()->constrained();
            $t->string('username')->nullable()->unique();
            $t->boolean('active')->default(true);
        });
        Schema::create('person_categories', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('departments', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('people', function (Blueprint $t) {
            $t->id();
            $t->string('name')->index();
            $t->string('registration')->unique();
            $t->foreignId('category_id')->constrained('person_categories');
            $t->foreignId('department_id')->nullable()->constrained();
            $t->string('job_title')->nullable();
            $t->string('cpf')->nullable();
            $t->string('phone')->nullable();
            $t->string('email')->nullable();
            $t->string('photo_path')->nullable();
            $t->text('notes')->nullable();
            $t->boolean('active')->default(true)->index();
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('vehicles', function (Blueprint $t) {
            $t->id();
            $t->string('plate');
            $t->string('brand');
            $t->string('model')->index();
            $t->string('color');
            $t->string('type');
            $t->unsignedSmallInteger('year')->nullable();
            $t->text('notes')->nullable();
            $t->boolean('active')->default(true)->index();
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['plate', 'active']);
        });
        Schema::create('person_vehicles', function (Blueprint $t) {
            $t->foreignId('person_id')->constrained()->cascadeOnDelete();
            $t->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $t->boolean('primary')->default(false);
            $t->primary(['person_id', 'vehicle_id']);
        });
        Schema::create('access_locations', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->string('description')->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('person_movements', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('person_id')->constrained();
            $t->enum('type', ['entry', 'exit']);
            $t->timestamp('occurred_at')->index();
            $t->foreignId('access_location_id')->constrained();
            $t->foreignId('operator_id')->constrained('users');
            $t->foreignId('vehicle_id')->nullable()->constrained();
            $t->text('notes')->nullable();
            $t->enum('origin', ['online', 'offline'])->default('online');
            $t->timestamp('synced_at')->nullable();
            $t->enum('status', ['valid', 'corrected', 'cancelled'])->default('valid')->index();
            $t->foreignId('corrects_id')->nullable()->constrained('person_movements');
            $t->text('correction_reason')->nullable();
            $t->timestamps();
            $t->index(['person_id', 'occurred_at']);
        });
        Schema::create('vehicle_movements', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('vehicle_id')->constrained();
            $t->foreignId('person_id')->nullable()->constrained();
            $t->enum('type', ['entry', 'exit']);
            $t->timestamp('occurred_at')->index();
            $t->foreignId('access_location_id')->constrained();
            $t->foreignId('operator_id')->constrained('users');
            $t->foreignId('person_movement_id')->nullable()->constrained();
            $t->text('notes')->nullable();
            $t->enum('origin', ['online', 'offline'])->default('online');
            $t->timestamp('synced_at')->nullable();
            $t->enum('status', ['valid', 'corrected', 'cancelled'])->default('valid')->index();
            $t->foreignId('corrects_id')->nullable()->constrained('vehicle_movements');
            $t->text('correction_reason')->nullable();
            $t->timestamps();
            $t->index(['vehicle_id', 'occurred_at']);
        });
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('action');
            $t->string('auditable_type');
            $t->unsignedBigInteger('auditable_id')->nullable();
            $t->json('before')->nullable();
            $t->json('after')->nullable();
            $t->text('reason')->nullable();
            $t->ipAddress('ip_address')->nullable();
            $t->string('device')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['auditable_type', 'auditable_id']);
        });
        Schema::create('sync_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained();
            $t->uuid('batch_uuid')->index();
            $t->unsignedInteger('received')->default(0);
            $t->unsignedInteger('processed')->default(0);
            $t->unsignedInteger('failed')->default(0);
            $t->json('errors')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['sync_logs', 'audit_logs', 'vehicle_movements', 'person_movements', 'access_locations', 'person_vehicles', 'vehicles', 'people', 'departments', 'person_categories', 'permission_role', 'permissions'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', fn (Blueprint $t) => $t->dropConstrainedForeignId('role_id'));
        Schema::dropIfExists('roles');
    }
};
