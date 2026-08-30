<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cash_entry_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_entry_id')->constrained()->cascadeOnDelete();
            $table->string('disk');
            $table->string('path');
            $table->string('original_name');
            $table->unsignedInteger('size')->nullable();
            $table->timestamps();
        });

        DB::table('cash_entries')
            ->whereNotNull('attachment_path')
            ->whereNotIn('attachment_path', ['', '0'])
            ->orderBy('id')
            ->chunkById(200, function ($entries): void {
                $now = now();
                $attachments = $entries->map(fn (object $entry): array => [
                    'cash_entry_id' => $entry->id,
                    /* Finance attachments were explicitly stored on S3 before this table existed. */
                    'disk' => 's3',
                    'path' => $entry->attachment_path,
                    'original_name' => $entry->attachment_name ?? 'lampiran',
                    'size' => $entry->attachment_size,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                DB::table('cash_entry_attachments')->insert($attachments);
            });

        Schema::table('cash_entries', function (Blueprint $table) {
            $table->dropColumn(['attachment_path', 'attachment_name', 'attachment_size']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cash_entries', function (Blueprint $table) {
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->unsignedInteger('attachment_size')->nullable();
        });

        DB::table('cash_entry_attachments')
            ->orderBy('id')
            ->get()
            ->groupBy('cash_entry_id')
            ->each(function ($attachments, int $cashEntryId): void {
                $attachment = $attachments->first();

                DB::table('cash_entries')
                    ->where('id', $cashEntryId)
                    ->update([
                        'attachment_path' => $attachment->path,
                        'attachment_name' => $attachment->original_name,
                        'attachment_size' => $attachment->size,
                    ]);
            });

        Schema::dropIfExists('cash_entry_attachments');
    }
};
