<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEtlFormaGeneracionTransmisionTable extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() {
        Schema::create('etl_forma_generacion_transmision', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->increments('fgt_id')->unsigned()->comment("Id Auto Incremental");
            $table->string('fgt_codigo', 2)->comment("Codigo");
            $table->string('fgt_descripcion', 100)->comment("Descripcion");
            $table->dateTime('fecha_vigencia_desde')->nullable()->comment("Fecha de Vigencia Desde");
            $table->dateTime('fecha_vigencia_hasta')->nullable()->comment("Fecha de Vigencia Hasta");
            $table->integer('usuario_creacion')->unsigned()->comment("Usuario que Creo el Registro");
            $table->dateTime('fecha_creacion')->comment("Fecha de Creacion del Registro");
            $table->dateTime('fecha_modificacion')->comment("Fecha de Modificacion del Registro");
            $table->string('estado', 20)->comment("Estado del Registro");
            $table->timestamp('fecha_actualizacion')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('Fecha Ultima Modificacion del Registro');

            $table->index('usuario_creacion','ii1_rfi');
            $table->index('fgt_codigo', 'ii2_rfi');
            $table->foreign('usuario_creacion', 'fk1_etl_forma_generacion_transmision_usuario_creacion')->references('usu_id')->on('auth_usuarios');
        });

        DB::statement("ALTER TABLE etl_forma_generacion_transmision comment 'Forma de Generacion y Transmision'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::dropIfExists('etl_forma_generacion_transmision');
    }
}
