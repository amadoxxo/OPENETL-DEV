<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePryCentrosCostoTable extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pry_centros_costo', function (Blueprint $table) {
            $table->increments('cco_id')                                     ->comment('Id Auto Incremental');
            $table->string('cco_codigo', 20)                                 ->comment('Codigo');
            $table->string('cco_descripcion', 255)                           ->comment('Descripcion');
            $table->unsignedInteger('usuario_creacion')                      ->comment('Usuario que Creo el registro');
            $table->datetime('fecha_creacion')                               ->comment('Fecha de Creacion del Registro');
            $table->datetime('fecha_modificacion')                           ->comment('Fecha de Modificacion del Registro');
            $table->string('estado', 20)                                     ->comment('Estado del registro');
            $table->timestamp('fecha_actualizacion')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('Fecha de Ultima Modificacion del Registro');
            $table->index('usuario_creacion', 'ii1_cco');
            $table->unique('cco_codigo', 'iu1_cco');
        });
        DB::statement("ALTER TABLE pry_centros_costo COMMENT 'Centros de Costo'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('pry_centros_costo');
    }
}
