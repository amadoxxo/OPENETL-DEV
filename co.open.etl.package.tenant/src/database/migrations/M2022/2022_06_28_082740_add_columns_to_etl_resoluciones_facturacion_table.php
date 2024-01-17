<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddColumnsToEtlResolucionesFacturacionTable extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() {
        Schema::table('etl_resoluciones_facturacion', function(Blueprint $table) {
            $table->string('rfa_dias_aviso', 4)        ->nullable()->comment('Dias de Aviso Vencimiento Resolucion')        ->after('cdo_consecutivo_provisional');
            $table->string('rfa_consecutivos_aviso', 4)->nullable()->comment('Consecutivos de Aviso Vencimiento Resolucion')->after('rfa_dias_aviso');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::table('etl_resoluciones_facturacion', function(Blueprint $table) {
            $table->dropColumn('rfa_dias_aviso');
            $table->dropColumn('rfa_consecutivos_aviso');
        });
    }
}
