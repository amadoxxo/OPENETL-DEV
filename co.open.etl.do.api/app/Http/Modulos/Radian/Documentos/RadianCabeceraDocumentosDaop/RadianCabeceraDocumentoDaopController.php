<?php

namespace App\Http\Modulos\Radian\Documentos\RadianCabeceraDocumentosDaop;

use App\Http\Traits\DoTrait;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use openEtl\Main\Traits\FechaVigenciaValidations;
use App\Http\Modulos\NotificarEventosDian\NotificarEventosDianController;
use App\Http\Modulos\Radian\Documentos\RadianEstadosDocumentosDaop\RadianEstadoDocumentoDaop;
use App\Http\Modulos\Parametros\TiposDocumentosElectronicos\ParametrosTipoDocumentoElectronico;
use App\Http\Modulos\Radian\Documentos\RadianCabeceraDocumentosDaop\RadianCabeceraDocumentoDaop;

class RadianCabeceraDocumentoDaopController extends Controller {
    use DoTrait, FechaVigenciaValidations;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->middleware(['jwt.auth', 'jwt.refresh']);
    }

    /**
     * Realiza el reenvío de la notificación de un evento.
     *
     * @param Request $request Parámetros de la petición
     * @return JsonResponse
     */
    public function reenvioNotificacionEvento(Request $request): JsonResponse {
        if (!$request->has('documentos') || !is_array($request->documentos) || empty($request->documentos)) {
            return response()->json([
                'message' => 'Error en la petición',
                'errors'  => ['La petición esta mal formada, no existe la propiedad [documentos] o no es del tipo array']
            ], 422);
        }

        if (!$request->has('evento') || empty($request->evento)) {
            return response()->json([
                'message' => 'Error en la petición',
                'errors'  => ['La petición esta mal formada, no existe la propiedad [evento] o esta vacia']
            ], 422);
        }

        switch(strtolower($request->evento)) {
            case "acuse":
                $evento = 'NOTACUSERECIBO';
                break;
            case "recibobien":
                $evento = 'NOTRECIBOBIEN';
                break;
            case "aceptacion":
                $evento = 'NOTACEPTACION';
                break;
            case "reclamo":
                $evento = 'NOTRECHAZO';
                break;
            default:
                return response()->json([
                    'message' => 'Error al procesar la petición',
                    'errors'  => ['El evento debe corresponder a ACUSE, RECIBOBIEN, ACEPTACION o RECLAMO']
                ], 400);
        }
        
        $classReenvio  = new NotificarEventosDianController();
        $classCabecera = new RadianCabeceraDocumentoDaop();
        $classEstado   = new RadianEstadoDocumentoDaop();

        $estadosNotificarEvento    = [];
        $documentosNoProcesados    = [];
        $documentosNotificarEvento = [];

        foreach($request->documentos as $documento) {
            $tdeID = null;
            ParametrosTipoDocumentoElectronico::select(['tde_id', 'tde_codigo', 'fecha_vigencia_desde', 'fecha_vigencia_hasta'])
                ->where('tde_codigo', $documento['tde_codigo'])
                ->where('estado', 'ACTIVO')
                ->get()
                ->groupBy('tde_codigo')
                ->map(function($registro) use (&$tdeID) {
                    $valida = $this->validarVigenciaRegistroParametrica($registro);
                    if($valida['vigente'])
                        $tdeID = $valida['registro']->tde_id;
                });

            if(!$tdeID) { 
                $documentosNoProcesados[] = 'Para el documento [' . $documento['rfa_prefijo'] . $documento['cdo_consecutivo'] . '] No existe el tipo de documento electrónico [' . $documento['tde_codigo'] . '], o el documento es NO-ELECTRONICO y No se permite el reenvío de notificación.';
                continue;
            }

            $documentoCabecera = $classCabecera::select(['cdo_id', 'cdo_origen'])
                ->where('ofe_identificacion', $documento['ofe_identificacion'])
                ->where('adq_identificacion', $documento['adq_identificacion'])
                ->where('tde_id', $tdeID)
                ->where('cdo_consecutivo', $documento['cdo_consecutivo'])
                ->when(array_key_exists('cdo_cufe', $documento), function ($query) use ($documento) {
                    return $query->where('cdo_cufe', $documento['cdo_cufe']);
                })
                ->when($documento['rfa_prefijo'] != '' && $documento['rfa_prefijo'] != 'null' && $documento['rfa_prefijo'] != null, function ($query) use ($documento) {
                    return $query->where('rfa_prefijo', trim($documento['rfa_prefijo']));
                }, function ($queryElse) {
                    return $queryElse->where(function($query) {
                        $query->whereNull('rfa_prefijo')
                            ->orWhere('rfa_prefijo', '');
                    });
                })
                ->first();

            if(!$documentoCabecera) {
                $documentosNoProcesados[] = 'No existe el documento electrónico [' . $documento['rfa_prefijo'].$documento['cdo_consecutivo'] . '] para el emisor, receptor y tipo de documento electrónicos indicados.';
                continue;
            }

            $estado = $classEstado::select(['est_id', 'cdo_id', 'est_estado', 'est_motivo_rechazo', 'est_informacion_adicional'])
                ->where('cdo_id', $documentoCabecera->cdo_id)
                ->where('est_estado', $evento)
                ->where('est_resultado', 'EXITOSO')
                ->where('est_ejecucion', 'FINALIZADO')
                ->where('estado', 'ACTIVO')
                ->with([
                    'getRadCabeceraDocumentosDaop:cdo_id,act_id,ofe_identificacion,ofe_informacion_adicional,adq_identificacion,adq_informacion_adicional,rfa_prefijo,cdo_consecutivo,cdo_fecha,cdo_hora,cdo_fecha_estado,cdo_fecha_acuse,cdo_fecha_recibo_bien,fecha_creacion',
                    'getRadCabeceraDocumentosDaop.getRadActores:act_id,act_identificacion'
                ])
                ->first();

            if(!$estado) {
                $doc = $classCabecera::select(['cdo_id', 'rfa_prefijo', 'cdo_consecutivo'])
                    ->where('cdo_id', $documentoCabecera->cdo_id)
                    ->first();

                if($doc) {
                    $documentosNoProcesados[] = 'Documento electrónico [' . $documento['rfa_prefijo'] . $documento['cdo_consecutivo'] . '] no cuenta con el estado de Notificación Exitoso para el evento seleccionado.';
                    continue;
                }
            } else {
                $informacionAdicionalAr = ($estado->est_informacion_adicional != '') ? json_decode($estado->est_informacion_adicional, true) : [];

                $attachedDocument = $this->obtenerArchivoDeDisco(
                    'radian',
                    $estado->getRadCabeceraDocumentosDaop->getRadActores->act_identificacion,
                    $estado->getRadCabeceraDocumentosDaop,
                    array_key_exists('est_archivo', $informacionAdicionalAr) ? $informacionAdicionalAr['est_archivo'] : null
                );
                $attachedDocument = $this->eliminarCaracteresBOM($attachedDocument);

                $documentosNotificarEvento[]     = $documentoCabecera->cdo_id;
                $estadosNotificarEvento[$documentoCabecera->cdo_id] = [
                    'est_id'                     => $estado->est_id,
                    'est_estado'                 => $estado->est_estado,
                    'inicio'                     => null,
                    'estadoInformacionAdicional' => $informacionAdicionalAr,
                    'attached_document_pdf'      => $attachedDocument
                ];
            }
        }

        $reenvioNotificacion = $classReenvio->notificarEventosDian($classCabecera, $classEstado, 'radian', $documentosNotificarEvento, $estadosNotificarEvento, auth()->user(), true);

        $documentosProcesados = [];
        foreach($reenvioNotificacion as $reenvio) {
            $documentosProcesados[] = '[' . $reenvio['consecutivo'] . '] ' . $reenvio['mensajeResultado'];
        }

        return response()->json([
            'message'   => 'Reenvio de notificaciones realizados',
            'resultado' => array_merge($documentosProcesados, $documentosNoProcesados)
        ], 200);
    }
}

