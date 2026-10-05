<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('edital_id')->constrained('editais')->cascadeOnDelete();
            $table->unsignedInteger('numero');
            $table->unsignedInteger('sequencial')->nullable();
            $table->string('tipo')->nullable()->index();
            $table->unsignedSmallInteger('situacao')->nullable()->index();
            $table->decimal('valor_minimo', 15, 2)->nullable();
            $table->decimal('valor_avaliacao', 15, 2)->nullable();
            $table->boolean('permite_pf')->default(false);
            $table->json('dados')->nullable();
            $table->timestamp('detalhes_importados_em')->nullable();
            $table->timestamps();

            $table->unique(['edital_id', 'numero']);
        });

        Schema::create('lote_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lote_id')->constrained('lotes')->cascadeOnDelete();
            $table->unsignedInteger('ordem');
            $table->text('descricao')->nullable();
            $table->decimal('quantidade', 18, 4)->nullable();
            $table->string('unidade_medida', 30)->nullable();
            $table->string('recinto_armazenador')->nullable();
            $table->string('nr_referencia')->nullable();
            $table->timestamps();
        });

        Schema::create('lote_imagens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lote_id')->constrained('lotes')->cascadeOnDelete();
            $table->unsignedBigInteger('imagem_id');
            $table->string('url', 500);
            $table->string('url_miniatura', 500)->nullable();
            $table->unsignedInteger('largura')->nullable();
            $table->unsignedInteger('altura')->nullable();
            $table->timestamps();

            $table->unique(['lote_id', 'imagem_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lote_imagens');
        Schema::dropIfExists('lote_itens');
        Schema::dropIfExists('lotes');
    }
};
