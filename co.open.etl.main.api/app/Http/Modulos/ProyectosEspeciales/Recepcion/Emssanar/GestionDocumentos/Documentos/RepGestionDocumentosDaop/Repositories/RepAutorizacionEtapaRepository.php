<?php
namespace App\Http\Modulos\ProyectosEspeciales\Recepcion\Emssanar\GestionDocumentos\Documentos\RepGestionDocumentosDaop\Repositories;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Modulos\ProyectosEspeciales\Recepcion\Emssanar\GestionDocumentos\Documentos\RepGestionDocumentosDaop\RepGestionDocumentoDaop;

/**
 * Clase encargada del procesamiento lógico frente al motor de base de datos.
 */
class RepAutorizacionEtapaRepository {

    public function __construct() {}

    /**
     * Consulta el documento dependiendo los filtros seleccionados.
     *
     * @param Request $request Parámetros de la petición
     * @return RepGestionDocumentoDaop
     */
    public function consultarGestionDocumento(Request $request) {
        $documento = RepGestionDocumentoDaop::select([
                'rep_gestion_documentos_daop.gdo_id',
                'rep_gestion_documentos_daop.ofe_id',
                'rep_gestion_documentos_daop.gdo_clasificacion',
                'rep_gestion_documentos_daop.rfa_prefijo',
                'rep_gestion_documentos_daop.gdo_consecutivo',
                'rep_gestion_documentos_daop.gdo_fecha',
                'rep_gestion_documentos_daop.gdo_estado_etapa1',
                'rep_gestion_documentos_daop.gdo_estado_etapa2',
                'rep_gestion_documentos_daop.gdo_estado_etapa3',
                'rep_gestion_documentos_daop.gdo_estado_etapa4',
                'rep_gestion_documentos_daop.gdo_estado_etapa5',
                'rep_gestion_documentos_daop.gdo_estado_etapa6',
                'rep_gestion_documentos_daop.gdo_estado_etapa7'
            ])
            ->where('rep_gestion_documentos_daop.ofe_id', $request->ofe_id)
            ->when($request->filled('gdo_identificacion') && !empty($request->gdo_identificacion), function ($query) use ($request) {
                $query->whereIn('rep_gestion_documentos_daop.gdo_identificacion', $request->gdo_identificacion);
            })
            ->when($request->filled('gdo_clasificacion'), function ($query) use ($request) {
                $query->where('rep_gestion_documentos_daop.gdo_clasificacion', $request->gdo_clasificacion);
            })
            ->when($request->filled('rfa_prefijo'), function ($query) use ($request) {
                $query->where('rep_gestion_documentos_daop.rfa_prefijo', $request->rfa_prefijo);
            })
            ->with([
                'getConfiguracionObligadoFacturarElectronicamente' => function($query) {
                    $query->select([
                        'ofe_id',
                        'ofe_identificacion',
                        DB::raw('IF(ofe_razon_social IS NULL OR ofe_razon_social = "", CONCAT(COALESCE(ofe_primer_nombre, ""), " ", COALESCE(ofe_otros_nombres, ""), " ", COALESCE(ofe_primer_apellido, ""), " ", COALESCE(ofe_segundo_apellido, "")), ofe_razon_social) as nombre_completo')
                    ]);
                }
            ])
            ->rightJoin('etl_obligados_facturar_electronicamente', function($query) {
                $query->whereRaw('rep_gestion_documentos_daop.ofe_id = etl_obligados_facturar_electronicamente.ofe_id')
                    ->where(function($query) {
                        if(!empty(auth()->user()->bdd_id_rg))
                            $query->where('etl_obligados_facturar_electronicamente' . '.bdd_id_rg', auth()->user()->bdd_id_rg);
                        else
                            $query->whereNull('etl_obligados_facturar_electronicamente' . '.bdd_id_rg');
                    });
            })
            ->where('rep_gestion_documentos_daop.gdo_consecutivo', $request->gdo_consecutivo)
            ->whereBetween('rep_gestion_documentos_daop.gdo_fecha', [$request->gdo_fecha_desde, $request->gdo_fecha_hasta])
            ->first();

        return $documento;
    }

    /**
     * Actualiza el estado del documento seleccionado para devolverlo a la etapa anterior.
     *
     * @param Request $request Parámetros de la petición
     * @return array
     */
    public function autorizarEtapa(Request $request): array {
        $arrErrores = [];
        $objUser    = auth()->user();
        $etapa      = $request->etapa;

        // Se actualizan los campos dependiendo de la etapa actual del documento
        $arrUpdate = [];
        if ($etapa == 1) 
            $arrUpdate = [
                "gdo_estado_etapa1"      => 'SIN_GESTION',
                "gdo_observacion_etapa1" => null
            ];
        else
            $arrUpdate = [
                "gdo_estado_etapa".($etapa-1)       => 'SIN_GESTION',
                "gdo_observacion_etapa".($etapa-1)  => null,
                "gdo_estado_etapa".$etapa           => null,
                "gdo_observacion_etapa".$etapa      => null,
            ];

        $documento = RepGestionDocumentoDaop::find($request->gdo_id);
        if ($documento) {
            $arrHistoricoEtapas = !empty($documento->gdo_historico_etapas) ? json_decode($documento->gdo_historico_etapas, true) : [];
            $arrHistorico[] = [
                "accion"           => "DEVOLVER_ETAPA",
                "etapa"            => $request->etapa,
                "usu_id"           => $objUser->usu_id,
                "usu_correo"       => $objUser->usu_email,
                "usu_nombre"       => $objUser->usu_nombre,
                "fecha"            => date('Y-m-d'),
                "hora"             => date('H:i:s'),
                "observacion"      => $request->observacion
            ];
            $arrHistoricoEtapas = array_merge($arrHistoricoEtapas, $arrHistorico);
            $arrUpdate = array_merge($arrUpdate, [
                "gdo_historico_etapas"  => json_encode($arrHistoricoEtapas)
            ]);

            try {
                $documento->update($arrUpdate);
            } catch (\Exception $e) {
                $arrErrores[] = "Error al actualizar el documento [{$documento->rfa_prefijo}-{$documento->gdo_consecutivo}]";
            }
        } else 
            $arrErrores[] = "No existe el documento con Id [{$request->gdo_id}]";

        return $arrErrores;
    }
}
