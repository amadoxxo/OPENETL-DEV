<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddColumnsToEtlTributosTable extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() {
        Schema::table('etl_tributos', function(Blueprint $table) {
            $table->string('tri_aplica_para_personas', 20)->nullable()->comment('Aplica para Personas')->after('tri_aplica_persona');
            $table->string('tri_aplica_para_tributo', 20)->nullable()->comment('Aplica para Tributo')->after('tri_aplica_tributo');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::table('etl_tributos', function(Blueprint $table) {
            $table->dropColumn('tri_aplica_para_personas');
            $table->dropColumn('tri_aplica_para_tributo');
        });
    }
}
