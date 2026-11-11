<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->string('position_title', 200)->nullable()->index()->after('responsible_person');
        });

        Schema::table('stock_check_items', function (Blueprint $table) {
            $table->string('actual_position', 200)->nullable()->after('actual_responsible');
        });

        // Backfill position_title from parenthesized role names in existing records (excluding village numbers)
        $assets = DB::table('assets')->select('id', 'responsible_person')->whereNotNull('responsible_person')->get();
        foreach ($assets as $row) {
            if (preg_match('/\(([^)]+)\)/u', $row->responsible_person, $m)) {
                $inside = trim($m[1]);
                if ($inside !== '' && ! str_contains($inside, 'หมู่ที่')) {
                    DB::table('assets')->where('id', $row->id)->update(['position_title' => $inside]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('stock_check_items', function (Blueprint $table) {
            $table->dropColumn('actual_position');
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn('position_title');
        });
    }
};
