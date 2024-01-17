<?php
namespace App\Http\Modulos\Recepcion\TransmisionErp\Commons;

use App\Http\Modulos\Recepcion\Documentos\RepEstadosDocumentosDaop\RepEstadoDocumentoDaop;

Trait MethodsTrait {
    /**
     * Crea un nuevo estado para un documento electrónico en el proceso de transmisión ERP de Recepción.
     *
     * @param int $cdo_id ID del documento para el cual se crea el estado
     * @param string $estado Nombre del estado a crear
     * @param string $resultado Resultado del estado
     * @param string $mensajeResultado Mensaje del resultado de procesamiento del estado
     * @param string $inicioProceso Fecha y hora de inicio del procesamiento
     * @param string $finProceso Fecha y hora del final del procesamiento
     * @param string $tiempoProcesamiento Tiempo de procesamiento
     * @param int $ageId del agendamiento relacionado con el estado
     * @param int $ageUsuId del usuario que crea el nuevo estado
     * @param array $estadoInformacionAdicional Información adicional del estado
     * @param string $ejecucion Estado de ejecucion del proceso
     * @return RepEstadoDocumentoDaop
     */
    public function creaNuevoEstadoDocumentoRecepcion(
        $cdo_id,
        $estado,
        $resultado                  = null,
        $mensajeResultado           = null,
        $inicioProceso              = null,
        $finProceso                 = null,
        $tiempoProcesamiento        = null,
        $ageId                      = null,
        $ageUsuId                   = null,
        $estadoInformacionAdicional = null,
        $ejecucion                  = null
    ) {
        $user = auth()->user();

        $estado = RepEstadoDocumentoDaop::create([
            'cdo_id'                    => $cdo_id,
            'est_estado'                => $estado,
            'est_resultado'             => $resultado,
            'est_mensaje_resultado'     => $mensajeResultado,
            'est_inicio_proceso'        => $inicioProceso,
            'est_fin_proceso'           => $finProceso,
            'est_tiempo_procesamiento'  => $tiempoProcesamiento,
            'age_id'                    => $ageId,
            'age_usu_id'                => $ageUsuId,
            'est_informacion_adicional' => ($estadoInformacionAdicional != null && !empty($estadoInformacionAdicional)) ? json_encode($estadoInformacionAdicional) : null,
            'usuario_creacion'          => $user->usu_id,
            'est_ejecucion'             => $ejecucion,
            'estado'                    => 'ACTIVO'
        ]);

        return $estado;
    }

    /**
     * Actualiza un estado para un documento electrónico en el proceso de transmisión ERP de Recepción.
     *
     * @param int $est_id ID del estado a actualizar
     * @param string $resultado Resultado del estado
     * @param string $mensajeResultado Mensaje del resultado de procesamiento del estado
     * @param string $finProceso Fecha y hora del final del procesamiento
     * @param string $tiempoProcesamiento Tiempo de procesamiento
     * @param array $estadoInformacionAdicional Información adicional del estado
     * @param string $ejecucion Estado de ejecucion del proceso
     * @return void
     */
    public function actualizaEstadoDocumentoRecepcion(
        $est_id,
        $resultado                  = null,
        $mensajeResultado           = null,
        $finProceso                 = null,
        $tiempoProcesamiento        = null,
        $estadoInformacionAdicional = null,
        $ejecucion                  = null
    ) {
        RepEstadoDocumentoDaop::find($est_id)
            ->update([
            'est_resultado'             => $resultado,
            'est_mensaje_resultado'     => $mensajeResultado,
            'est_fin_proceso'           => $finProceso,
            'est_tiempo_procesamiento'  => $tiempoProcesamiento,
            'est_informacion_adicional' => ($estadoInformacionAdicional != null && !empty($estadoInformacionAdicional)) ? json_encode($estadoInformacionAdicional) : null,
            'est_ejecucion'             => $ejecucion
        ]);
    }
}
