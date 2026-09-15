<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_logs', function (Blueprint $table) {
            $table->unique(['ticket_id', 'channel']);
        });

        Schema::table('notification_logs', function (Blueprint $table) {
            $table->dropIndex(['ticket_id', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::table('notification_logs', function (Blueprint $table) {
            $table->index(['ticket_id', 'channel']);
        });

        Schema::table('notification_logs', function (Blueprint $table) {
            $table->dropUnique(['ticket_id', 'channel']);
        });
    }
};
