<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEtlRadianAlcanceMandatosTable extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() {
        Schema::create('etl_radian_alcance_mandatos', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->increments('ama_id')->unsigned()->comment("Id AutoIncremental");
            $table->string('ama_numero_evento', 5)->comment("Codigo");
            $table->string('ama_documento', 255)->comment("Nombre");
            $table->string('ama_facultades_sne', 20)->nullable()->comment("Facultades Mandatarios Sistema de Negociacion (SNE)");
            $table->string('ama_facultades_pt', 20)->nullable()->comment("Facultades Mandatarios Proveedor Tecnologico (PT)");
            $table->string('ama_facultades_factor', 20)->nullable()->comment("Facultades Mandatarios Factor (F)");
            $table->string('ama_notas', 255)->nullable()->comment("Notas");
            $table->dateTime('fecha_vigencia_desde')->nullable()->comment("Fecha de Vigencia Desde");
            $table->dateTime('fecha_vigencia_hasta')->nullable()->comment("Fecha de Vigencia Hasta");
            
            $table->integer('usuario_creacion')->unsigned()->comment("Usuario que Creo el Registro");
            $table->dateTime('fecha_creacion')->comment("Fecha de Creacion del Registro");
            $table->dateTime('fecha_modificacion')->comment("Fecha de Modificacion del Registro");
            $table->string('estado', 20)->comment("Estado del Registro");
            $table->timestamp('fecha_actualizacion')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('Fecha Ultima Modificacion del Registro');

            $table->index('usuario_creacion','ii1_ama');
            $table->index('ama_numero_Evento', 'ii2_ama');
            $table->foreign('usuario_creacion', 'fk1_etl_radian_alcance_mandatos_usuario_creacion')->references('usu_id')->on('auth_usuarios');
        });

        DB::statement("ALTER TABLE etl_radian_alcance_mandatos comment 'Alcance Mandatos'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::dropIfExists('etl_radian_alcance_mandatos');
    }
}
