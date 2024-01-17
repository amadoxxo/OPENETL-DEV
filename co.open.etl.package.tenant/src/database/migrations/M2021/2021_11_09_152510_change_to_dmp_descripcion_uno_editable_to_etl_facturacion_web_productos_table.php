<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChangeToDmpDescripcionUnoEditableToEtlFacturacionWebProductosTable extends Migration {
    /**
     * Run the migrations.
     * 
     * @return void
     */
    public function up() {
        Schema::table('etl_facturacion_web_productos', function(Blueprint $table) {
            $table->string('dmp_descripcion_uno_editable', 2)->nullable()->comment('Descripcion Uno Editable (SI/NO)')->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::table('etl_facturacion_web_productos', function(Blueprint $table) {
            $table->string('dmp_descripcion_uno_editable', 2)->nullable(false)->comment('Descripcion Uno Editable (SI/NO)')->change();
        });
    }
}
