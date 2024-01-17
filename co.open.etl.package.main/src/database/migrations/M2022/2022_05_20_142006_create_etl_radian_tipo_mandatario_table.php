<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateEtlRadianTipoMandatarioTable extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() {
        Schema::create('etl_radian_tipo_mandatario', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->increments('tim_id')->unsigned()->comment("id Autoincremental");
            $table->string('tim_codigo', 10)->comment("Codigo");
            $table->string('tim_descripcion', 100)->comment("Descripcion");
            $table->datetime('fecha_vigencia_desde')->nullable()->comment('Fecha de Vigencia Desde');
            $table->datetime('fecha_vigencia_hasta')->nullable()->comment('Fecha de Vigencia Hasta');
            $table->integer('usuario_creacion')->unsigned()->comment("Usuario que Creo el Registro");
            $table->dateTime('fecha_creacion')->comment("Fecha de Creacion del Registro");
            $table->dateTime('fecha_modificacion')->comment("Fecha de Modificacion del Registro");
            $table->string('estado', 20)->comment("Estado del Registro");
            $table->timestamp('fecha_actualizacion')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('Fecha Ultima Modificacion del Registro');
        });

        DB::statement("ALTER TABLE etl_radian_tipo_mandatario comment 'Tipo Mandatario'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::dropIfExists('etl_radian_tipo_mandatario');
    }
}
