<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChangeToCcoDescripcionToEtlConceptosCorreccionTable extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() {
        Schema::table('etl_conceptos_correccion', function(Blueprint $table) {
            $table->string('cco_descripcion', 255)->comment('Descripcion')->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::table('etl_conceptos_correccion', function(Blueprint $table) {
            $table->string('cco_descripcion', 100)->comment('Descripcion')->change();
        });
    }
}
