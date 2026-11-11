<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('qr_token', 32)->unique();
            $table->integer('excel_seq')->nullable();
            $table->string('asset_code', 100)->nullable()->index();
            $table->string('name', 500);
            $table->text('specification')->nullable();
            $table->string('category', 150)->nullable()->index();
            $table->string('acquired_date_text', 100)->nullable();
            $table->date('acquired_date')->nullable();
            $table->unsignedSmallInteger('fiscal_year')->default(2569)->index();
            $table->decimal('price', 14, 2)->default(0);
            $table->string('responsible_person', 200)->nullable()->index();
            $table->string('department', 150)->default('สำนักปลัด')->index();
            $table->string('location', 200)->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('unit', 50)->default('หน่วย');
            $table->string('status', 30)->default('usable')->index(); // usable, repair, damaged, disposed, lost
            $table->string('review_status', 30)->default('approved')->index(); // approved, pending
            $table->text('note')->nullable();
            $table->string('photo_path', 255)->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->foreignId('last_checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('asset_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('movement_type', 30)->index(); // register, assign, transfer, status_change, dispose
            $table->string('from_responsible', 200)->nullable();
            $table->string('to_responsible', 200)->nullable();
            $table->string('from_location', 200)->nullable();
            $table->string('to_location', 200)->nullable();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();
            $table->string('reference_doc', 150)->nullable();
            $table->text('remark')->nullable();
            $table->timestamp('moved_at');
            $table->timestamps();
        });

        Schema::create('maintenance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reporter_name', 150)->nullable();
            $table->text('issue_description');
            $table->string('maintenance_status', 30)->default('pending')->index(); // pending, in_progress, completed, unrepairable
            $table->string('vendor_name', 200)->nullable();
            $table->decimal('repair_cost', 12, 2)->default(0);
            $table->text('resolution_note')->nullable();
            $table->timestamp('reported_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_checks', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('title', 255);
            $table->unsignedSmallInteger('fiscal_year')->default(2569);
            $table->string('status', 20)->default('open')->index(); // open, closed
            $table->date('started_at')->nullable();
            $table->date('closed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_check_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_check_id')->constrained('stock_checks')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('checker_name', 150)->nullable();
            $table->string('check_status', 30)->default('found_usable'); // found_usable, found_repair, found_damaged, not_found
            $table->string('actual_responsible', 200)->nullable();
            $table->string('actual_location', 200)->nullable();
            $table->string('scan_method', 20)->default('qr'); // qr, manual
            $table->text('remark')->nullable();
            $table->timestamp('checked_at');
            $table->timestamps();

            $table->unique(['stock_check_id', 'asset_id']);
        });

        Schema::create('asset_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->foreignId('stock_check_id')->nullable()->constrained('stock_checks')->nullOnDelete();
            $table->foreignId('inspector_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('inspector_name', 150)->nullable();
            $table->string('condition_status', 30); // usable, repair, damaged, lost
            $table->string('location_found', 200)->nullable();
            $table->string('scan_method', 20)->default('qr'); // qr, manual
            $table->text('note')->nullable();
            $table->timestamp('inspected_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_inspections');
        Schema::dropIfExists('stock_check_items');
        Schema::dropIfExists('stock_checks');
        Schema::dropIfExists('maintenance_logs');
        Schema::dropIfExists('asset_movements');
        Schema::dropIfExists('assets');
    }
};
