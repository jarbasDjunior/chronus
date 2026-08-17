<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            // These fields belong to the access-control domain, but are not
            // required by the employee vehicle registration form.
            $table->string('brand')->nullable()->change();
            $table->string('type')->nullable()->change();
        });

        Schema::table('person_vehicles', function (Blueprint $table) {
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('person_vehicles', function (Blueprint $table) {
            $table->dropTimestamps();
        });

        DB::table('vehicles')->whereNull('brand')->update(['brand' => '']);
        DB::table('vehicles')->whereNull('type')->update(['type' => '']);

        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('brand')->nullable(false)->change();
            $table->string('type')->nullable(false)->change();
        });
    }
};
