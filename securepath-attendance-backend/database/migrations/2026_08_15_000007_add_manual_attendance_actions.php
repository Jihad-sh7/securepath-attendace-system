<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table): void {
            $table->renameColumn('checkout_source', 'check_out_source');
        });

        Schema::table('attendance_records', function (Blueprint $table): void {
            $table->string('check_in_source', 32)->nullable()->after('check_in_at')->index();
            $table->foreignId('checked_in_by_user_id')->nullable()->after('check_in_source')->constrained('users')->nullOnDelete();
            $table->foreignId('checked_out_by_user_id')->nullable()->after('check_out_source')->constrained('users')->nullOnDelete();
        });

        DB::table('attendance_records')->where('verification_method', 'face_recognition')->update(['check_in_source' => 'face_recognition']);
        DB::table('attendance_records')->whereNull('check_in_source')->where('source', 'dashboard')->update(['check_in_source' => 'admin_manual']);
        DB::table('attendance_records')->whereNull('check_in_source')->update(['check_in_source' => 'system']);
        DB::table('attendance_records')->where('check_out_source', 'manual')->update(['check_out_source' => 'admin_manual']);
        DB::table('attendance_records')->where('check_out_source', 'system_auto')->update(['check_out_source' => 'auto_overtime_limit']);
        DB::table('attendance_records')->where('check_in_source', 'admin_manual')->update(['checked_in_by_user_id' => DB::raw('created_by')]);
        DB::table('attendance_records')->where('check_out_source', 'admin_manual')->update(['checked_out_by_user_id' => DB::raw('created_by')]);

        Schema::create('attendance_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attendance_id')->constrained('attendance_records')->cascadeOnDelete();
            $table->foreignId('person_id')->constrained()->restrictOnDelete();
            $table->string('type', 32)->index();
            $table->timestamp('occurred_at')->index();
            $table->string('source', 32)->index();
            $table->foreignId('performed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['person_id', 'occurred_at']);
        });

        DB::table('attendance_records')->orderBy('id')->chunkById(500, function ($records): void {
            $events = [];
            foreach ($records as $record) {
                if ($record->check_in_at) {
                    $events[] = [
                        'attendance_id' => $record->id,
                        'person_id' => $record->person_id,
                        'type' => 'check_in',
                        'occurred_at' => $record->check_in_at,
                        'source' => $record->check_in_source ?: 'system',
                        'performed_by_user_id' => $record->checked_in_by_user_id,
                        'note' => $record->notes,
                        'created_at' => $record->created_at,
                        'updated_at' => $record->created_at,
                    ];
                }
                if ($record->check_out_at) {
                    $events[] = [
                        'attendance_id' => $record->id,
                        'person_id' => $record->person_id,
                        'type' => 'check_out',
                        'occurred_at' => $record->check_out_at,
                        'source' => $record->check_out_source ?: 'system',
                        'performed_by_user_id' => $record->checked_out_by_user_id,
                        'note' => $record->auto_checkout_reason,
                        'created_at' => $record->updated_at,
                        'updated_at' => $record->updated_at,
                    ];
                }
            }
            if ($events) DB::table('attendance_events')->insert($events);
        });

        foreach (['attendance.manual_checkin', 'attendance.manual_checkout'] as $name) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $name],
                ['group' => 'attendance', 'description' => str_replace(['.', '_'], ' ', ucfirst($name)), 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    public function down(): void
    {
        DB::table('permissions')->whereIn('name', ['attendance.manual_checkin', 'attendance.manual_checkout'])->delete();
        Schema::dropIfExists('attendance_events');
        Schema::table('attendance_records', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('checked_out_by_user_id');
            $table->dropConstrainedForeignId('checked_in_by_user_id');
            $table->dropColumn('check_in_source');
        });
        Schema::table('attendance_records', function (Blueprint $table): void {
            $table->renameColumn('check_out_source', 'checkout_source');
        });
    }
};

