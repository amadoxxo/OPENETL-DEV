<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePryCausalesDevolucionTable extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pry_causales_devolucion', function (Blueprint $table) {
            $table->increments('cde_id')                                     ->comment('Id Auto Incremental');
            $table->string('cde_descripcion', 255)                           ->comment('Descripcion');
            $table->unsignedInteger('usuario_creacion')                      ->comment('Usuario que Creo el registro');
            $table->datetime('fecha_creacion')                               ->comment('Fecha de Creacion del Registro');
            $table->datetime('fecha_modificacion')                           ->comment('Fecha de Modificacion del Registro');
            $table->string('estado', 20)                                     ->comment('Estado del registro');
            $table->timestamp('fecha_actualizacion')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('Fecha de Ultima Modificacion del Registro');
            $table->index('usuario_creacion', 'ii1_cde');
        });
        DB::statement("ALTER TABLE pry_causales_devolucion COMMENT 'Causales de Devolucion'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('pry_causales_devolucion');
    }
}
