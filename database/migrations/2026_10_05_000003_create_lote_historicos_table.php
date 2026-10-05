<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lote_historicos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lote_id')->constrained('lotes')->cascadeOnDelete();
            $table->unsignedSmallInteger('situacao')->nullable();
            $table->decimal('valor_minimo', 15, 2)->nullable();
            $table->decimal('valor_avaliacao', 15, 2)->nullable();
            $table->timestamp('registrado_em')->nullable();

            $table->index(['lote_id', 'registrado_em']);
        });

        // Estado atual dos lotes já importados, como primeiro registro do histórico.
        DB::table('lote_historicos')->insertUsing(
            ['lote_id', 'situacao', 'valor_minimo', 'valor_avaliacao', 'registrado_em'],
            DB::table('lotes')->select(['id', 'situacao', 'valor_minimo', 'valor_avaliacao', 'created_at']),
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('lote_historicos');
    }
};
