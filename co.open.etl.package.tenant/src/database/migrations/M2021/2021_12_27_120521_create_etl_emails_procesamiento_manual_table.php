<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEtlEmailsProcesamientoManualTable extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('etl_emails_procesamiento_manual', function (Blueprint $table) {
            $table->increments('epm_id')                                     ->comment('Id Autoincremental');
            $table->string('ofe_identificacion', 20)                         ->comment('Identificacion');
            $table->text('epm_subject')                                      ->comment('Subject del Correo');
            $table->string('epm_id_carpeta', 255)                            ->comment('Identificador de la Carpeta del Correo');
            $table->datetime('epm_fecha_correo')                             ->comment('Fecha y Hora del Correo');
            $table->text('epm_cuerpo_correo')                                ->comment('Cuerpo del Correo');
            $table->string('epm_procesado', 2)->nullable()                   ->comment('Indica si el Correo fue Procesado o NO (SI/NO)');
            $table->integer('epm_procesado_usuario')->unsigned()->nullable() ->comment('ID del usuario que realizó el procesamiento');
            $table->datetime('epm_procesado_fecha')->nullable()              ->comment('Fecha de Procesamiento');
            $table->integer('usuario_creacion')->unsigned()                  ->comment('Usuario que Creo el registro');
            $table->datetime('fecha_creacion')                               ->comment('Fecha de Creacion del Registro');
            $table->datetime('fecha_modificacion')                           ->comment('Fecha de Modificacion del Registro');
            $table->string('estado', 20)                                     ->comment('Estado del registro');
            $table->timestamp('fecha_actualizacion')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('Fecha de Ultima Modificacion del Registro');
            $table->index('usuario_creacion', 'ii1_epm');
        });
        DB::statement("ALTER TABLE etl_emails_procesamiento_manual COMMENT 'Emails Para Procesamiento Manual'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('etl_emails_procesamiento_manual');
    }
}
