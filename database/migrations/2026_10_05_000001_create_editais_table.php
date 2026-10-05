<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('editais', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('unidade');
            $table->unsignedInteger('numero');
            $table->unsignedSmallInteger('exercicio');
            $table->string('codigo', 30)->nullable();
            $table->unsignedSmallInteger('situacao')->nullable()->index();
            $table->unsignedSmallInteger('tipo')->nullable();
            $table->string('orgao')->nullable();
            $table->string('unidade_nome')->nullable();
            $table->string('cidade')->nullable()->index();
            $table->boolean('permite_pf')->default(false);
            $table->dateTime('data_inicio_propostas')->nullable();
            $table->dateTime('data_fim_propostas')->nullable();
            $table->dateTime('data_classificacao')->nullable();
            $table->dateTime('data_abertura_lances')->nullable()->index();
            $table->text('forma_contato')->nullable();
            $table->text('dados_publicacao')->nullable();
            $table->json('dados')->nullable();
            $table->timestamp('importado_em')->nullable();
            $table->timestamps();

            $table->unique(['unidade', 'numero', 'exercicio']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('editais');
    }
};
