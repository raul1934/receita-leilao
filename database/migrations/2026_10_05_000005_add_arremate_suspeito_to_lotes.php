<?php

use App\Models\Lote;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lotes', function (Blueprint $table) {
            $table->boolean('arremate_suspeito')->default(false)->after('valor_arremate');
        });

        // Marca os lotes cujo resultado já foi importado.
        DB::table('lotes')->whereNotNull('valor_arremate')->orderBy('id')->chunkById(500, function ($lotes) {
            foreach ($lotes as $lote) {
                if (Lote::arremateSuspeito($lote->valor_arremate, $lote->valor_minimo, $lote->valor_avaliacao)) {
                    DB::table('lotes')->where('id', $lote->id)->update(['arremate_suspeito' => true]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('lotes', function (Blueprint $table) {
            $table->dropColumn('arremate_suspeito');
        });
    }
};
