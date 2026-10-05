<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lotes', function (Blueprint $table) {
            $table->string('resultado', 20)->nullable()->after('valor_avaliacao');
            $table->decimal('valor_arremate', 15, 2)->nullable()->after('resultado');
        });

        Schema::table('editais', function (Blueprint $table) {
            $table->timestamp('resultado_importado_em')->nullable()->after('importado_em');
        });
    }

    public function down(): void
    {
        Schema::table('lotes', function (Blueprint $table) {
            $table->dropColumn(['resultado', 'valor_arremate']);
        });

        Schema::table('editais', function (Blueprint $table) {
            $table->dropColumn('resultado_importado_em');
        });
    }
};
