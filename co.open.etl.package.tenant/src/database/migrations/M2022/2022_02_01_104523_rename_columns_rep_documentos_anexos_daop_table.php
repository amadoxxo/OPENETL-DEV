<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RenameColumnsRepDocumentosAnexosDaopTable extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() {
        Schema::table('rep_documentos_anexos_daop', function(Blueprint $table) {
            DB::statement("ALTER TABLE `rep_documentos_anexos_daop` CHANGE `dso_id` `dan_id` INT( 10 ) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Id Auto Incremental'");
        });

        Schema::table('rep_documentos_anexos_daop', function (Blueprint $table) {
            $table->renameColumn('dso_lote', 'dan_lote');
            $table->renameColumn('dso_uuid', 'dan_uuid');
            $table->renameColumn('dso_tamano', 'dan_tamano');
            $table->renameColumn('dso_nombre', 'dan_nombre');
            $table->renameColumn('dso_descripcion', 'dan_descripcion');
            $table->renameColumn('dso_envio_openecm', 'dan_envio_openecm');
            $table->renameColumn('dso_respuesta_envio_openecm', 'dan_respuesta_envio_openecm');
        });

        Schema::table('rep_documentos_anexos_daop', function (Blueprint $table) {
            $table->string('dan_nombre', 255)->comment('Nombre del Documento Anexo')->change();
            $table->string('dan_descripcion', 255)->comment('Descripcion del Documento Anexo')->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::table('rep_documentos_anexos_daop', function(Blueprint $table) {
            DB::statement("ALTER TABLE `rep_documentos_anexos_daop` CHANGE `dan_id` `dso_id` INT( 10 ) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Id Auto Incremental'");
        });

        Schema::table('rep_documentos_anexos_daop', function (Blueprint $table) {
            $table->renameColumn('dan_lote', 'dso_lote');
            $table->renameColumn('dan_uuid', 'dso_uuid');
            $table->renameColumn('dan_tamano', 'dso_tamano');
            $table->renameColumn('dan_nombre', 'dso_nombre');
            $table->renameColumn('dan_descripcion', 'dso_descripcion');
            $table->renameColumn('dan_envio_openecm', 'dso_envio_openecm');
            $table->renameColumn('dan_respuesta_envio_openecm', 'dso_respuesta_envio_openecm');
        });

        Schema::table('rep_documentos_anexos_daop', function (Blueprint $table) {
            $table->string('dso_nombre', 255)->comment('Nombre del Documento Soporte')->change();
            $table->string('dso_descripcion', 255)->comment('Descripcion del Documento Soporte')->change();
        });
    }
}
