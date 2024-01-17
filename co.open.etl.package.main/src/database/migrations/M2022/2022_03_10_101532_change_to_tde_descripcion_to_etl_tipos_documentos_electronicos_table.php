<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChangeToTdeDescripcionToEtlTiposDocumentosElectronicosTable extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() {
        Schema::table('etl_tipos_documentos_electronicos', function(Blueprint $table) {
            $table->string('tde_descripcion', 255)->comment('Descripcion')->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::table('etl_tipos_documentos_electronicos', function(Blueprint $table) {
            $table->string('tde_descripcion', 100)->comment('Descripcion')->change();
        });
    }
}
