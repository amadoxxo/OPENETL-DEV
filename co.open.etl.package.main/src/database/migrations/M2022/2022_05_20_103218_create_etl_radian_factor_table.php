<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEtlRadianFactorTable extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() {
        Schema::create('etl_radian_factor', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->increments('fac_id')->unsigned()->comment("Id AutoIncremental");
            $table->string('fac_codigo', 5)->comment("Codigo");
            $table->string('fac_descripcion', 255)->comment("Descripcion");
            $table->dateTime('fecha_vigencia_desde')->nullable()->comment("Fecha de Vigencia Desde");
            $table->dateTime('fecha_vigencia_hasta')->nullable()->comment("Fecha de Vigencia Hasta");
            
            $table->integer('usuario_creacion')->unsigned()->comment("Usuario que Creo el Registro");
            $table->dateTime('fecha_creacion')->comment("Fecha de Creacion del Registro");
            $table->dateTime('fecha_modificacion')->comment("Fecha de Modificacion del Registro");
            $table->string('estado', 20)->comment("Estado del Registro");
            $table->timestamp('fecha_actualizacion')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('Fecha Ultima Modificacion del Registro');

            $table->index('usuario_creacion','ii1_fac');
            $table->index('fac_codigo', 'ii2_fac');
            $table->foreign('usuario_creacion', 'fk1_etl_radian_factor_usuario_creacion')->references('usu_id')->on('auth_usuarios');
        });

        DB::statement("ALTER TABLE etl_radian_factor comment 'Factor'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::dropIfExists('etl_radian_factor');
    }
}
