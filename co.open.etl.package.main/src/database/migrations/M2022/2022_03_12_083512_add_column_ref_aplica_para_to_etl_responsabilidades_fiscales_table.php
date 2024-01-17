<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddColumnRefAplicaParaToEtlResponsabilidadesFiscalesTable extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() {
        Schema::table('etl_responsabilidades_fiscales', function(Blueprint $table) {
            $table->string('ref_aplica_para', 20)->nullable()->comment('Aplica para')->after('ref_descripcion');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::table('etl_responsabilidades_fiscales', function(Blueprint $table) {
            $table->dropColumn('ref_aplica_para');
        });
    }
}
