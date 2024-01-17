<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddColumnTdoAplicaParaToEtlTiposDeDocumentosTable extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() {
        Schema::table('etl_tipos_de_documentos', function(Blueprint $table) {
            $table->string('tdo_aplica_para', 20)->nullable()->comment('Aplica para')->after('tdo_descripcion');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::table('etl_tipos_de_documentos', function(Blueprint $table) {
            $table->dropColumn('tdo_aplica_para');
        });
    }
}
