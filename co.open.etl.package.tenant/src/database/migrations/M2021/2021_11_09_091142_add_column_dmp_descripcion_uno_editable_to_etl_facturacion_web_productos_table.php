<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddColumnDmpDescripcionUnoEditableToEtlFacturacionWebProductosTable extends Migration {
    /**
     * Run the migrations.
     * 
     * @return void
     */
    public function up() {
        Schema::table('etl_facturacion_web_productos', function(Blueprint $table) {
            $table->string('dmp_descripcion_uno_editable', 2)->comment('Descripcion Uno Editable (SI/NO)')->after('dmp_descripcion_uno');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::table('etl_facturacion_web_productos', function(Blueprint $table) {
            $table->dropColumn('dmp_descripcion_uno_editable');
        });
    }
}
