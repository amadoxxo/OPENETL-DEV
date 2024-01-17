<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateEtlRadianTiempoMandatosTable extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() {
        Schema::create('etl_radian_tiempo_mandatos', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->increments('tie_id')->unsigned()->comment("id Autoincremental");
            $table->string('tie_codigo', 5)->comment("Codigo");
            $table->string('tie_nombre', 100)->comment("Nombre");
            $table->string('tie_descripcion', 255)->comment("Descripcion");
            $table->datetime('fecha_vigencia_desde')->nullable()->comment('Fecha de Vigencia Desde');
            $table->datetime('fecha_vigencia_hasta')->nullable()->comment('Fecha de Vigencia Hasta');
            $table->integer('usuario_creacion')->unsigned()->comment("Usuario que Creo el Registro");
            $table->dateTime('fecha_creacion')->comment("Fecha de Creacion del Registro");
            $table->dateTime('fecha_modificacion')->comment("Fecha de Modificacion del Registro");
            $table->string('estado', 20)->comment("Estado del Registro");
            $table->timestamp('fecha_actualizacion')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('Fecha Ultima Modificacion del Registro');
        });

        DB::statement("ALTER TABLE etl_radian_tiempo_mandatos comment 'Tiempo Mandatos'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::dropIfExists('etl_radian_tiempo_mandatos');
    }
}
