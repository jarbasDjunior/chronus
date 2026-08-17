<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('people') || ! Schema::hasTable('person_categories')) {
            return;
        }

        $category = DB::table('person_categories')->where('name', 'Porteiro')->first();
        if (! $category) {
            return;
        }

        $now = now();
        DB::table('people')
            ->where('category_id', $category->id)
            ->whereNull('deleted_at')
            ->update(['active' => false, 'deleted_at' => $now, 'updated_at' => $now]);

        DB::table('person_categories')
            ->where('id', $category->id)
            ->update(['active' => false, 'deleted_at' => $now, 'updated_at' => $now]);
    }

    public function down(): void
    {
        $category = DB::table('person_categories')->where('name', 'Porteiro')->first();
        if (! $category) {
            return;
        }

        DB::table('person_categories')
            ->where('id', $category->id)
            ->update(['active' => true, 'deleted_at' => null, 'updated_at' => now()]);
        DB::table('people')
            ->where('category_id', $category->id)
            ->update(['active' => true, 'deleted_at' => null, 'updated_at' => now()]);
    }
};
