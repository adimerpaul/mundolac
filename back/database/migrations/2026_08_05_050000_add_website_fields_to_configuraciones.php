<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuraciones', function (Blueprint $table) {
            $table->string('titulo_web', 180)->default('Productos para tu negocio al mejor precio')->after('logo');
            $table->string('whatsapp', 30)->default('+591 73809946')->after('titulo_web');
        });

        DB::table('configuraciones')->whereNull('titulo_web')->update([
            'titulo_web' => 'Productos para tu negocio al mejor precio',
        ]);
        DB::table('configuraciones')->whereNull('whatsapp')->update([
            'whatsapp' => '+591 73809946',
        ]);
    }

    public function down(): void
    {
        Schema::table('configuraciones', function (Blueprint $table) {
            $table->dropColumn(['titulo_web', 'whatsapp']);
        });
    }
};
