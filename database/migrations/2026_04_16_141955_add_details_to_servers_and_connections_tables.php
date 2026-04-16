<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->string('name');
            $table->string('host');
            $table->unsignedSmallInteger('port')->default(5432);
            $table->text('notes')->nullable();
        });

        Schema::table('connections', function (Blueprint $table) {
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->string('database');
            $table->string('username');
            $table->text('password');
            $table->string('sslmode')->default('prefer');
            $table->text('last_error')->nullable();
            $table->timestamp('last_connected_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('connections', function (Blueprint $table) {
            $table->dropForeign(['server_id']);
            $table->dropColumn([
                'server_id',
                'database',
                'username',
                'password',
                'sslmode',
                'last_error',
                'last_connected_at',
            ]);
        });

        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn(['name', 'host', 'port', 'notes']);
        });
    }
};
