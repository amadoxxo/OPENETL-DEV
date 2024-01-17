<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RenameTableRepDocumentosSoporteDaopToRepDocumentosAnexosDaopTable extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() {
        Schema::table('rep_documentos_soporte_daop', function (Blueprint $table) {
            $table->dropForeign('fk1_rep_documentos_soporte_daop_cdo_id');
            $table->dropIndex('ii1_dso');
            $table->dropIndex('ii2_dso');
        });

        Schema::rename('rep_documentos_soporte_daop', 'rep_documentos_anexos_daop');

        Schema::table('rep_documentos_anexos_daop', function (Blueprint $table) {
            $table->index('cdo_id', 'ii1_dan');
            $table->index('usuario_creacion', 'ii2_dan');
        });

        Schema::disableForeignKeyConstraints();
        Schema::table('rep_documentos_anexos_daop', function (Blueprint $table) {
            $table->foreign('cdo_id', 'fk1_rep_documentos_anexos_daop_cdo_id')->references('cdo_id')->on('rep_cabecera_documentos_daop');
        });
        Schema::enableForeignKeyConstraints();

        Schema::table('rep_documentos_anexos_daop', function (Blueprint $table) {
            DB::statement("ALTER TABLE `rep_documentos_anexos_daop` COMMENT 'Documentos Anexos'");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::table('rep_documentos_anexos_daop', function (Blueprint $table) {
            $table->dropForeign('fk1_rep_documentos_anexos_daop_cdo_id');
            $table->dropIndex('ii1_dan');
            $table->dropIndex('ii2_dan');
        });

        Schema::rename('rep_documentos_anexos_daop', 'rep_documentos_soporte_daop');

        Schema::table('rep_documentos_soporte_daop', function (Blueprint $table) {
            $table->index('cdo_id', 'ii1_dso');
            $table->index('usuario_creacion', 'ii2_dso');
        });

        Schema::disableForeignKeyConstraints();
        Schema::table('rep_documentos_soporte_daop', function (Blueprint $table) {
            $table->foreign('cdo_id', 'fk1_rep_documentos_soporte_daop_cdo_id')->references('cdo_id')->on('rep_cabecera_documentos_daop');
        });
        Schema::enableForeignKeyConstraints();

        Schema::table('rep_documentos_soporte_daop', function (Blueprint $table) {
            DB::statement("ALTER TABLE `rep_documentos_soporte_daop` COMMENT 'Documentos Soporte'");
        });
    }
}
