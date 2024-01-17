<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEtlRadianTiposOperacionTable extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() {
        Schema::create('etl_radian_tipos_operacion', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->increments('tor_id')->unsigned()->comment("Id AutoIncremental");
            $table->string('tor_codigo', 5)->comment("Codigo");
            $table->string('tor_descripcion', 255)->comment("Descripcion");
            $table->integer('ede_id')->unsigned()->comment("id Evento DE RADIAN");
            $table->dateTime('fecha_vigencia_desde')->nullable()->comment("Fecha de Vigencia Desde");
            $table->dateTime('fecha_vigencia_hasta')->nullable()->comment("Fecha de Vigencia Hasta");
            
            $table->integer('usuario_creacion')->unsigned()->comment("Usuario que Creo el Registro");
            $table->dateTime('fecha_creacion')->comment("Fecha de Creacion del Registro");
            $table->dateTime('fecha_modificacion')->comment("Fecha de Modificacion del Registro");
            $table->string('estado', 20)->comment("Estado del Registro");
            $table->timestamp('fecha_actualizacion')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('Fecha Ultima Modificacion del Registro');

            $table->index('usuario_creacion','ii1_tor');
            $table->index('tor_codigo', 'ii2_tor');
            $table->index('ede_id','ii3_tor');
            $table->foreign('ede_id', 'fk1_etl_radian_tipos_operacion_ede_id')->references('ede_id')->on('etl_radian_eventos_documentos_electronicos');
            $table->foreign('usuario_creacion', 'fk2_etl_radian_tipos_operacion_usuario_creacion')->references('usu_id')->on('auth_usuarios');
        });

        DB::statement("ALTER TABLE etl_radian_tipos_operacion comment 'Tipos de operación RADIAN'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::dropIfExists('etl_radian_tipos_operacion');
    }
}
