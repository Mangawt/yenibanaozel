<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('import_queue')->where('status', 'waiting')->update(['status' => 'pending']);
        DB::table('import_queue')->where('status', 'processing')->update(['status' => 'running']);
        DB::table('import_queue')->where('status', 'skipped')->update(['status' => 'completed']);
    }

    public function down(): void
    {
        DB::table('import_queue')->where('status', 'pending')->update(['status' => 'waiting']);
        DB::table('import_queue')->where('status', 'running')->update(['status' => 'processing']);
    }
};
