<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChangeToCdoClasificacionToEtlCabeceraDocumentosDaopTable extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() {
        Schema::table('etl_cabecera_documentos_daop', function(Blueprint $table) {
            $table->string('cdo_clasificacion', 5)->comment('Clasificacion Documento (FC,NC,ND,DS,DS-NC)')->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::table('etl_cabecera_documentos_daop', function(Blueprint $table) {
            $table->string('cdo_clasificacion', 2)->comment('Clasificacion Documento (FC-NC-ND)')->change();
        });
    }
}
