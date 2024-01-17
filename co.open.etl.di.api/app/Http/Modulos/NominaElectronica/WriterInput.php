<?php
namespace App\Http\Modulos\NominaElectronica;

use GuzzleHttp\Client;
use App\Http\Models\AdoAgendamiento;
use App\Http\Modulos\Sistema\EtlProcesamientoJson\EtlProcesamientoJson;
use App\Http\Modulos\NominaElectronica\DsnEstadosDocumentosDaop\DsnEstadoDocumentoDaop;
use App\Http\Modulos\NominaElectronica\DsnDetalleDevengadosDaop\DsnDetalleDevengadoDaop;
use App\Http\Modulos\NominaElectronica\DsnDetalleDeduccionesDaop\DsnDetalleDeduccionDaop;
use App\Http\Modulos\NominaElectronica\DsnCabeceraDocumentosNominaDaop\DsnCabeceraDocumentoNominaDaop;

class WriterInput {
    public function __construct() {}

    /**
     * Genera un token de autenticación para efectuar una peticion a un microservicio.
     *
     * @return string
     */
    public function getToken() {
        $user = auth()->user();
        if ($user)
            return auth()->tokenById($user->usu_id);
        return '';
    }

    /**
     * Construye un cliente de Guzzle para consumir los microservicios
     *
     * @param string $URI Identificador del recurso o microservicio a acceder
     * @return Client Cliente de Guzzle
     */
    private function getCliente(string $URI) {
        return new Client([
            'base_uri' => $URI,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'application/json'
            ]
        ]);
    }

    /**
     * Accede microservicio Main para procesar la información del trabajador.
     *
     * @param array $parametros Parametros del trabajador que son enviados en la petición
     * @return array Array con el resultado del procesamiento, un índice boolean para error y otro índice objeto para el resultado o respuesta de la petición
     */
    public function peticionMain(array $parametros) {
        try {
            $peticionMain = $this->getCliente(config('variables_sistema.APP_URL'))
                ->request(
                    'POST',
                    config('variables_sistema.MAIN_API_URL') . '/api/configuracion/nomina-electronica/trabajadores/di-gestion',
                    [
                        'json' => ['trabajador' => $parametros],
                        'headers' => [
                            'Authorization' => 'Bearer ' . $this->getToken()
                        ]
                    ]
                );

            return[
                'respuesta' => json_decode((string)$peticionMain->getBody()->getContents()),
                'error' => false
            ];
        } catch (\Exception $e) {
            $response = $e->getResponse();
            return[
                'respuesta' => $response->getBody()->getContents(),
                'error' => true
            ];
        }
    }

    /**
     * Guarda la información de cabecera de un documento de nómina electrónica.
     *
     * @param string $lote Lote de procesamiento
     * @param string $origen Origen de procesamiento
     * @param \stdClass $documento Documento en procesamiento
     * @return array Array con información del resultado del procesamiento del documento en cabcera de documentos
     */
    public function guardarCabecera(string $lote, string $origen, \stdClass $documento) {
        try {

            $existe = DsnCabeceraDocumentoNominaDaop::select(['cdn_id', 'estado'])
                ->where('emp_id', $documento->emp_id)
                ->where('cdn_consecutivo', $documento->cdn_consecutivo);

            if(isset($documento->cdn_prefijo) && !empty($documento->cdn_prefijo))
                $existe = $existe->where('cdn_prefijo', $documento->cdn_prefijo);
            else
                $existe = $existe->where('cdn_prefijo', '');

            $existe = $existe->with([
                    'getDoFinalizado:est_id,cdn_id'
                ])
                ->first();

            if($existe && $existe->estado == 'ACTIVO') {
                return [
                    'cdn_id' => null,
                    'error'  => true,
                    'errors' => ['El documento de nómina electrónica [' . $documento->cdn_prefijo . $documento->cdn_consecutivo . '] existe con estado ACTIVO, para poder actualizarlo su estado debe ser INACTIVO'],
                    'traza'  => null
                ];
            }

            if($existe && $existe->estado == 'INACTIVO' && $existe->getDoFinalizado) {
                return [
                    'cdn_id' => null,
                    'error'  => true,
                    'errors' => ['El documento de nómina electrónica [' . $documento->cdn_prefijo . $documento->cdn_consecutivo . '] no puede ser actualizado debido a que ya fue transmitido a la DIAN'],
                    'traza'  => null
                ];
            }

            $cabeceraDocumento = [
                'cdn_origen'                   => $origen,
                'cdn_clasificacion'            => $documento->cdn_clasificacion,
                'cdn_lote'                     => $lote,
                'emp_id'                       => $documento->emp_id,
                'tra_id'                       => $documento->tra_id,
                'tde_id'                       => $documento->tde_id,
                'cdn_aplica_novedad'           => isset($documento->cdn_aplica_novedad) && !empty($documento->cdn_aplica_novedad) ? $documento->cdn_aplica_novedad : null,
                'ntn_id'                       => isset($documento->ntn_id) && !empty($documento->ntn_id) ? $documento->ntn_id : null,
                'cdn_prefijo'                  => isset($documento->cdn_prefijo) ? $documento->cdn_prefijo : '',
                'cdn_consecutivo'              => $documento->cdn_consecutivo,
                'npe_id'                       => isset($documento->npe_id) && !empty($documento->npe_id) ? $documento->npe_id : null,
                'cdn_fecha_emision'            => $documento->cdn_fecha_emision,
                'cdn_fecha_inicio_liquidacion' => isset($documento->cdn_fecha_inicio_liquidacion) && !empty($documento->cdn_fecha_inicio_liquidacion) ? $documento->cdn_fecha_inicio_liquidacion : null,
                'cdn_fecha_fin_liquidacion'    => isset($documento->cdn_fecha_fin_liquidacion) && !empty($documento->cdn_fecha_fin_liquidacion) ? $documento->cdn_fecha_fin_liquidacion : null,
                'cdn_tiempo_laborado'          => isset($documento->cdn_tiempo_laborado) && !empty($documento->cdn_tiempo_laborado) ? $documento->cdn_tiempo_laborado : null,
                'pai_id'                       => $documento->pai_id,
                'dep_id'                       => isset($documento->dep_id) && !empty($documento->dep_id) ? $documento->dep_id : null,
                'mun_id'                       => $documento->mun_id,
                'fpa_id'                       => isset($documento->cdn_medios_pago->fpa_id) ? $documento->cdn_medios_pago->fpa_id : null,
                'mpa_id'                       => isset($documento->cdn_medios_pago->mpa_id) ? $documento->cdn_medios_pago->mpa_id : null,
                'cdn_fechas_pago'              => isset($documento->cdn_fechas_pago) && !empty($documento->cdn_fechas_pago) ? json_encode($documento->cdn_fechas_pago) : null,
                'cdn_notas'                    => isset($documento->cdn_notas) && !empty($documento->cdn_notas) ? json_encode($documento->cdn_notas) : null,
                'mon_id'                       => isset($documento->mon_id) ? $documento->mon_id : null,
                'cdn_trm'                      => isset($documento->cdn_trm) && !empty($documento->cdn_trm) ? $documento->cdn_trm : null,
                'cdn_redondeo'                 => isset($documento->cdn_redondeo) && !empty($documento->cdn_redondeo) ? $documento->cdn_redondeo : 0,
                'cdn_devengados'               => isset($documento->cdn_devengados) && !empty($documento->cdn_devengados) ? $documento->cdn_devengados : 0,
                'cdn_deducciones'              => isset($documento->cdn_deducciones) && !empty($documento->cdn_deducciones) ? $documento->cdn_deducciones : 0,
                'cdn_total_comprobante'        => isset($documento->cdn_total_comprobante) && !empty($documento->cdn_total_comprobante) ? $documento->cdn_total_comprobante : 0,
                'cdn_cune'                     => null,
                'cdn_qr'                       => null,
                'cdn_signaturevalue'           => null,
                'cdn_prefijo_predecesor'       => isset($documento->cdn_documento_predecesor->cdn_prefijo) && !empty($documento->cdn_documento_predecesor->cdn_prefijo) ? $documento->cdn_documento_predecesor->cdn_prefijo : null,
                'cdn_consecutivo_predecesor'   => isset($documento->cdn_documento_predecesor->cdn_consecutivo) && !empty($documento->cdn_documento_predecesor->cdn_consecutivo) ? $documento->cdn_documento_predecesor->cdn_consecutivo : null,
                'cdn_fecha_emision_predecesor' => isset($documento->cdn_documento_predecesor->cdn_fecha_emision) && !empty($documento->cdn_documento_predecesor->cdn_fecha_emision) ? $documento->cdn_documento_predecesor->cdn_fecha_emision : null,
                'cdn_cune_predecesor'          => isset($documento->cdn_documento_predecesor->cdn_cune) && !empty($documento->cdn_documento_predecesor->cdn_cune) ? $documento->cdn_documento_predecesor->cdn_cune : null,
                'cdn_procesar_documento'       => isset($documento->cdn_informacion_adicional->cdn_procesar_documento) && $documento->cdn_informacion_adicional->cdn_procesar_documento === 'SI' ? 'SI' : 'NO',
                'cdn_fecha_procesar_documento' => isset($documento->cdn_informacion_adicional->cdn_procesar_documento) && $documento->cdn_informacion_adicional->cdn_procesar_documento === 'SI' ? date('Y-m-d H:i:s') : null,
                'cdn_fecha_validacion_dian'    => null,
                'cdn_nombre_archivos'          => null,
                'usuario_creacion'             => auth()->user()->usu_id,
                'estado'                       => 'ACTIVO',
            ];
            
            if(!$existe) {
                $existe = DsnCabeceraDocumentoNominaDaop::create($cabeceraDocumento);
            } else {
                $existe->update($cabeceraDocumento);
            }

            return [
                'cdn_id' => $existe->cdn_id,
                'error'  => false,
                'errors' => null,
                'traza'  => null,
            ];
        } catch (\Exception $e) {
            return [
                'cdn_id' => null,
                'error'  => true,
                'errors' => 'Se presentó un error al guardar la información de cabecera del documento de nómina electrónica',
                'traza'  => $e->getFile() . ' - Línea ' . $e->getLine() . ': ' . $e->getMessage() . "\n\nTrace:\n" . $e->getTraceAsString()
            ];
        }
    }

    /**
     * Guarda el detalle de devengados de un documento de nómina electrónica.
     *
     * @param int $cdnId ID de cabecera del documento
     * @param \stdClass $detalleDevengados Detalle de devengados
     * @return array Array con información del resultado del procesamiento del detalle de devengados
     */
    public function guardarDetalleDevengados(int $cdnId, \stdClass $detalleDevengados) {
        try {
            DsnDetalleDevengadoDaop::where('cdn_id', $cdnId)
                ->delete();

            DsnDetalleDevengadoDaop::create([
                'cdn_id'           => $cdnId,
                'ddv_concepto'     => 'Basico',
                'ddv_cantidad'     => $detalleDevengados->basico->dias_trabajados,
                'ddv_valor'        => $detalleDevengados->basico->sueldo_trabajado,
                'estado'           => 'ACTIVO',
                'usuario_creacion' => auth()->user()->usu_id
            ]);

            if(isset($detalleDevengados->transporte) && !empty($detalleDevengados->transporte)) {
                foreach($detalleDevengados->transporte as $transporte) {
                    DsnDetalleDevengadoDaop::create([
                        'cdn_id'                => $cdnId,
                        'ddv_concepto'          => 'Transporte',
                        'ddv_valor'             => isset($transporte->auxilio_transporte) && !empty($transporte->auxilio_transporte) ? $transporte->auxilio_transporte : 0,
                        'ddv_valor_salarial'    => isset($transporte->viatico_salarial) && !empty($transporte->viatico_salarial) ? $transporte->viatico_salarial : 0,
                        'ddv_valor_no_salarial' => isset($transporte->viatico_no_salarial) && !empty($transporte->viatico_no_salarial) ? $transporte->viatico_no_salarial : 0,
                        'estado'                => 'ACTIVO',
                        'usuario_creacion'      => auth()->user()->usu_id
                    ]);
                }
            }

            if(isset($detalleDevengados->horas_extras_recargos) && !empty($detalleDevengados->horas_extras_recargos)) {
                foreach($detalleDevengados->horas_extras_recargos as $horasExtras) {
                    DsnDetalleDevengadoDaop::create([
                        'cdn_id'           => $cdnId,
                        'ddv_concepto'     => 'HorasExtrasRecargos',
                        'ddv_tipo'         => $horasExtras->tipo,
                        'ddv_fecha_inicio' => isset($horasExtras->fecha_inicio) && !empty($horasExtras->fecha_inicio) ? $horasExtras->fecha_inicio : null,
                        'ddv_hora_inicio'  => isset($horasExtras->hora_inicio) && !empty($horasExtras->hora_inicio) ? $horasExtras->hora_inicio : null,
                        'ddv_fecha_fin'    => isset($horasExtras->fecha_fin) && !empty($horasExtras->fecha_fin) ? $horasExtras->fecha_fin : null,
                        'ddv_hora_fin'     => isset($horasExtras->hora_fin) && !empty($horasExtras->hora_fin) ? $horasExtras->hora_fin : null,
                        'ddv_cantidad'     => isset($horasExtras->cantidad) && !empty($horasExtras->cantidad) ? $horasExtras->cantidad : 0,
                        'ddv_porcentaje'   => isset($horasExtras->porcentaje) && !empty($horasExtras->porcentaje) ? $horasExtras->porcentaje : 0,
                        'ddv_valor'        => isset($horasExtras->pago) && !empty($horasExtras->pago) ? $horasExtras->pago : 0,
                        'estado'           => 'ACTIVO',
                        'usuario_creacion' => auth()->user()->usu_id
                    ]);
                }
            }

            if(isset($detalleDevengados->vacaciones) && isset($detalleDevengados->vacaciones->vacaciones_comunes) && !empty($detalleDevengados->vacaciones->vacaciones_comunes)) {
                foreach($detalleDevengados->vacaciones->vacaciones_comunes as $vacacionesComunes) {
                    DsnDetalleDevengadoDaop::create([
                        'cdn_id'           => $cdnId,
                        'ddv_concepto'     => 'Vacaciones',
                        'ddv_tipo'         => 'VacacionesComunes',
                        'ddv_fecha_inicio' => isset($vacacionesComunes->fecha_inicio) && !empty($vacacionesComunes->fecha_inicio) ? $vacacionesComunes->fecha_inicio : null,
                        'ddv_fecha_fin'    => isset($vacacionesComunes->fecha_fin) && !empty($vacacionesComunes->fecha_fin) ? $vacacionesComunes->fecha_fin : null,
                        'ddv_cantidad'     => isset($vacacionesComunes->cantidad) && !empty($vacacionesComunes->cantidad) ? $vacacionesComunes->cantidad : 0,
                        'ddv_valor'        => isset($vacacionesComunes->pago) && !empty($vacacionesComunes->pago) ? $vacacionesComunes->pago : 0,
                        'estado'           => 'ACTIVO',
                        'usuario_creacion' => auth()->user()->usu_id
                    ]);
                }
            }

            if(isset($detalleDevengados->vacaciones) && isset($detalleDevengados->vacaciones->vacaciones_compensadas) && !empty($detalleDevengados->vacaciones->vacaciones_compensadas)) {
                foreach($detalleDevengados->vacaciones->vacaciones_compensadas as $vacacionesCompensadas) {
                    DsnDetalleDevengadoDaop::create([
                        'cdn_id'           => $cdnId,
                        'ddv_concepto'     => 'Vacaciones',
                        'ddv_tipo'         => 'VacacionesCompensadas',
                        'ddv_cantidad'     => isset($vacacionesCompensadas->cantidad) && !empty($vacacionesCompensadas->cantidad) ? $vacacionesCompensadas->cantidad : 0,
                        'ddv_valor'        => isset($vacacionesCompensadas->pago) && !empty($vacacionesCompensadas->pago) ? $vacacionesCompensadas->pago : 0,
                        'estado'           => 'ACTIVO',
                        'usuario_creacion' => auth()->user()->usu_id
                    ]);
                }
            }

            if(isset($detalleDevengados->primas) && !empty($detalleDevengados->primas)) {
                DsnDetalleDevengadoDaop::create([
                    'cdn_id'                => $cdnId,
                    'ddv_concepto'          => 'Primas',
                    'ddv_cantidad'          => isset($detalleDevengados->primas->cantidad) && !empty($detalleDevengados->primas->cantidad) ? $detalleDevengados->primas->cantidad : 0,
                    'ddv_valor'             => isset($detalleDevengados->primas->pago) && !empty($detalleDevengados->primas->pago) ? $detalleDevengados->primas->pago : 0,
                    'ddv_valor_no_salarial' => isset($detalleDevengados->primas->pago_no_salarial) && !empty($detalleDevengados->primas->pago_no_salarial) ? $detalleDevengados->primas->pago_no_salarial : 0,
                    'estado'                => 'ACTIVO',
                    'usuario_creacion'      => auth()->user()->usu_id
                ]);
            }

            if(isset($detalleDevengados->cesantias) && !empty($detalleDevengados->cesantias)) {
                DsnDetalleDevengadoDaop::create([
                    'cdn_id'             => $cdnId,
                    'ddv_concepto'       => 'Cesantias',
                    'ddv_porcentaje'     => isset($detalleDevengados->cesantias->porcentaje) && !empty($detalleDevengados->cesantias->porcentaje) ? $detalleDevengados->cesantias->porcentaje : 0,
                    'ddv_valor'          => isset($detalleDevengados->cesantias->pago) && !empty($detalleDevengados->cesantias->pago) ? $detalleDevengados->cesantias->pago : 0,
                    'ddv_pago_adicional' => isset($detalleDevengados->cesantias->pago_intereses) && !empty($detalleDevengados->cesantias->pago_intereses) ? $detalleDevengados->cesantias->pago_intereses : 0,
                    'estado'             => 'ACTIVO',
                    'usuario_creacion'   => auth()->user()->usu_id
                ]);
            }

            if(isset($detalleDevengados->incapacidades) && !empty($detalleDevengados->incapacidades)) {
                foreach($detalleDevengados->incapacidades as $incapacidad) {
                    DsnDetalleDevengadoDaop::create([
                        'cdn_id'           => $cdnId,
                        'ddv_concepto'     => 'Incapacidades',
                        'ddv_tipo'         => $incapacidad->tipo,
                        'ddv_fecha_inicio' => isset($incapacidad->fecha_inicio) && !empty($incapacidad->fecha_inicio) ? $incapacidad->fecha_inicio : null,
                        'ddv_fecha_fin'    => isset($incapacidad->fecha_fin) && !empty($incapacidad->fecha_fin) ? $incapacidad->fecha_fin : null,
                        'ddv_cantidad'     => isset($incapacidad->cantidad) && !empty($incapacidad->cantidad) ? $incapacidad->cantidad : 0,
                        'ddv_valor'        => isset($incapacidad->pago) && !empty($incapacidad->pago) ? $incapacidad->pago : 0,
                        'estado'           => 'ACTIVO',
                        'usuario_creacion' => auth()->user()->usu_id
                    ]);
                }
            }

            if(isset($detalleDevengados->licencias) && !empty($detalleDevengados->licencias)) {
                foreach($detalleDevengados->licencias as $licencia) {
                    DsnDetalleDevengadoDaop::create([
                        'cdn_id'           => $cdnId,
                        'ddv_concepto'     => 'Licencias',
                        'ddv_tipo'         => $licencia->tipo,
                        'ddv_fecha_inicio' => isset($licencia->fecha_inicio) && !empty($licencia->fecha_inicio) ? $licencia->fecha_inicio : null,
                        'ddv_fecha_fin'    => isset($licencia->fecha_fin) && !empty($licencia->fecha_fin) ? $licencia->fecha_fin : null,
                        'ddv_cantidad'     => isset($licencia->cantidad) && !empty($licencia->cantidad) ? $licencia->cantidad : 0,
                        'ddv_valor'        => isset($licencia->pago) && !empty($licencia->pago) ? $licencia->pago : 0,
                        'estado'           => 'ACTIVO',
                        'usuario_creacion' => auth()->user()->usu_id
                    ]);
                }
            }

            if(isset($detalleDevengados->bonificaciones) && !empty($detalleDevengados->bonificaciones)) {
                foreach($detalleDevengados->bonificaciones as $bonificacion) {
                    DsnDetalleDevengadoDaop::create([
                        'cdn_id'                => $cdnId,
                        'ddv_concepto'          => 'Bonificaciones',
                        'ddv_valor_salarial'    => isset($bonificacion->bonificacion_salarial) && !empty($bonificacion->bonificacion_salarial) ? $bonificacion->bonificacion_salarial : 0,
                        'ddv_valor_no_salarial' => isset($bonificacion->bonificacion_no_salarial) && !empty($bonificacion->bonificacion_no_salarial) ? $bonificacion->bonificacion_no_salarial : 0,
                        'estado'                => 'ACTIVO',
                        'usuario_creacion'      => auth()->user()->usu_id
                    ]);
                }
            }

            if(isset($detalleDevengados->auxilios) && !empty($detalleDevengados->auxilios)) {
                foreach($detalleDevengados->auxilios as $auxilio) {
                    DsnDetalleDevengadoDaop::create([
                        'cdn_id'                => $cdnId,
                        'ddv_concepto'          => 'Auxilios',
                        'ddv_valor_salarial'    => isset($auxilio->auxilio_salarial) && !empty($auxilio->auxilio_salarial) ? $auxilio->auxilio_salarial : 0,
                        'ddv_valor_no_salarial' => isset($auxilio->auxilio_no_salarial) && !empty($auxilio->auxilio_no_salarial) ? $auxilio->auxilio_no_salarial : 0,
                        'estado'                => 'ACTIVO',
                        'usuario_creacion'      => auth()->user()->usu_id
                    ]);
                }
            }

            if(isset($detalleDevengados->huelgas_legales) && !empty($detalleDevengados->huelgas_legales)) {
                foreach($detalleDevengados->huelgas_legales as $huelgaLegal) {
                    DsnDetalleDevengadoDaop::create([
                        'cdn_id'           => $cdnId,
                        'ddv_concepto'     => 'HuelgasLegales',
                        'ddv_fecha_inicio' => isset($huelgaLegal->fecha_inicio) && !empty($huelgaLegal->fecha_inicio) ? $huelgaLegal->fecha_inicio : null,
                        'ddv_fecha_fin'    => isset($huelgaLegal->fecha_fin) && !empty($huelgaLegal->fecha_fin) ? $huelgaLegal->fecha_fin : null,
                        'ddv_cantidad'     => isset($huelgaLegal->cantidad) && !empty($huelgaLegal->cantidad) ? $huelgaLegal->cantidad : 0,
                        'estado'           => 'ACTIVO',
                        'usuario_creacion' => auth()->user()->usu_id
                    ]);
                }
            }

            if(isset($detalleDevengados->otros_conceptos) && !empty($detalleDevengados->otros_conceptos)) {
                foreach($detalleDevengados->otros_conceptos as $otroConcepto) {
                    DsnDetalleDevengadoDaop::create([
                        'cdn_id'                => $cdnId,
                        'ddv_concepto'          => 'OtrosConceptos',
                        'ddv_descripcion'       => isset($otroConcepto->descripcion_concepto) && !empty($otroConcepto->descripcion_concepto) ? $otroConcepto->descripcion_concepto : null,
                        'ddv_valor_salarial'    => isset($otroConcepto->concepto_salarial) && !empty($otroConcepto->concepto_salarial) ? $otroConcepto->concepto_salarial : 0,
                        'ddv_valor_no_salarial' => isset($otroConcepto->concepto_no_salarial) && !empty($otroConcepto->concepto_no_salarial) ? $otroConcepto->concepto_no_salarial : 0,
                        'estado'                => 'ACTIVO',
                        'usuario_creacion'      => auth()->user()->usu_id
                    ]);
                }
            }

            if(isset($detalleDevengados->compensaciones) && !empty($detalleDevengados->compensaciones)) {
                foreach($detalleDevengados->compensaciones as $compensacion) {
                    DsnDetalleDevengadoDaop::create([
                        'cdn_id'                   => $cdnId,
                        'ddv_concepto'             => 'Compensaciones',
                        'ddv_valor_ordinario'      => isset($compensacion->compensacion_ordinaria) && !empty($compensacion->compensacion_ordinaria) ? $compensacion->compensacion_ordinaria : 0,
                        'ddv_valor_extraordinario' => isset($compensacion->compensacion_extraordinaria) && !empty($compensacion->compensacion_extraordinaria) ? $compensacion->compensacion_extraordinaria : 0,
                        'estado'                   => 'ACTIVO',
                        'usuario_creacion'         => auth()->user()->usu_id
                    ]);
                }
            }

            if(isset($detalleDevengados->bono_epctvs) && !empty($detalleDevengados->bono_epctvs)) {
                foreach($detalleDevengados->bono_epctvs as $bonoEpctvs) {
                    DsnDetalleDevengadoDaop::create([
                        'cdn_id'                          => $cdnId,
                        'ddv_concepto'                    => 'BonoEPCTVs',
                        'ddv_valor_salarial'              => isset($bonoEpctvs->pago_salarial) && !empty($bonoEpctvs->pago_salarial) ? $bonoEpctvs->pago_salarial : 0,
                        'ddv_valor_no_salarial'           => isset($bonoEpctvs->pago_no_salarial) && !empty($bonoEpctvs->pago_no_salarial) ? $bonoEpctvs->pago_no_salarial : 0,
                        'ddv_valor_salarial_adicional'    => isset($bonoEpctvs->pago_alimentacion_salarial) && !empty($bonoEpctvs->pago_alimentacion_salarial) ? $bonoEpctvs->pago_alimentacion_salarial : 0,
                        'ddv_valor_no_salarial_adicional' => isset($bonoEpctvs->pago_alimentacion_no_salarial) && !empty($bonoEpctvs->pago_alimentacion_no_salarial) ? $bonoEpctvs->pago_alimentacion_no_salarial : 0,
                        'estado'                          => 'ACTIVO',
                        'usuario_creacion'                => auth()->user()->usu_id
                    ]);
                }
            }

            if(isset($detalleDevengados->comisiones) && !empty($detalleDevengados->comisiones)) {
                foreach($detalleDevengados->comisiones as $comision) {
                    DsnDetalleDevengadoDaop::create([
                        'cdn_id'           => $cdnId,
                        'ddv_concepto'     => 'Comisiones',
                        'ddv_valor'        => isset($comision->comision) && !empty($comision->comision) ? $comision->comision : 0,
                        'estado'           => 'ACTIVO',
                        'usuario_creacion' => auth()->user()->usu_id
                    ]);
                }
            }

            if(isset($detalleDevengados->pagos_terceros) && !empty($detalleDevengados->pagos_terceros)) {
                foreach($detalleDevengados->pagos_terceros as $pagoTercero) {
                    DsnDetalleDevengadoDaop::create([
                        'cdn_id'           => $cdnId,
                        'ddv_concepto'     => 'PagosTerceros',
                        'ddv_valor'        => isset($pagoTercero->pago_tercero) && !empty($pagoTercero->pago_tercero) ? $pagoTercero->pago_tercero : 0,
                        'estado'           => 'ACTIVO',
                        'usuario_creacion' => auth()->user()->usu_id
                    ]);
                }
            }

            if(isset($detalleDevengados->anticipos) && !empty($detalleDevengados->anticipos)) {
                foreach($detalleDevengados->anticipos as $anticipo) {
                    DsnDetalleDevengadoDaop::create([
                        'cdn_id'           => $cdnId,
                        'ddv_concepto'     => 'Anticipos',
                        'ddv_valor'        => isset($anticipo->anticipo) && !empty($anticipo->anticipo) ? $anticipo->anticipo : 0,
                        'estado'           => 'ACTIVO',
                        'usuario_creacion' => auth()->user()->usu_id
                    ]);
                }
            }

            if(isset($detalleDevengados->dotacion) && !empty($detalleDevengados->dotacion)) {
                DsnDetalleDevengadoDaop::create([
                    'cdn_id'           => $cdnId,
                    'ddv_concepto'     => 'Dotacion',
                    'ddv_valor'        => isset($detalleDevengados->dotacion) && !empty($detalleDevengados->dotacion) ? $detalleDevengados->dotacion : 0,
                    'estado'           => 'ACTIVO',
                    'usuario_creacion' => auth()->user()->usu_id
                ]);
            }

            if(isset($detalleDevengados->apoyo_sost) && !empty($detalleDevengados->apoyo_sost)) {
                DsnDetalleDevengadoDaop::create([
                    'cdn_id'           => $cdnId,
                    'ddv_concepto'     => 'ApoyoSost',
                    'ddv_valor'        => isset($detalleDevengados->apoyo_sost) && !empty($detalleDevengados->apoyo_sost) ? $detalleDevengados->apoyo_sost : 0,
                    'estado'           => 'ACTIVO',
                    'usuario_creacion' => auth()->user()->usu_id
                ]);
            }

            if(isset($detalleDevengados->teletrabajo) && !empty($detalleDevengados->teletrabajo)) {
                DsnDetalleDevengadoDaop::create([
                    'cdn_id'           => $cdnId,
                    'ddv_concepto'     => 'Teletrabajo',
                    'ddv_valor'        => isset($detalleDevengados->teletrabajo) && !empty($detalleDevengados->teletrabajo) ? $detalleDevengados->teletrabajo : 0,
                    'estado'           => 'ACTIVO',
                    'usuario_creacion' => auth()->user()->usu_id
                ]);
            }

            if(isset($detalleDevengados->bonificaciones_retiro) && !empty($detalleDevengados->bonificaciones_retiro)) {
                DsnDetalleDevengadoDaop::create([
                    'cdn_id'           => $cdnId,
                    'ddv_concepto'     => 'BonifRetiro',
                    'ddv_valor'        => isset($detalleDevengados->bonificaciones_retiro) && !empty($detalleDevengados->bonificaciones_retiro) ? $detalleDevengados->bonificaciones_retiro : 0,
                    'estado'           => 'ACTIVO',
                    'usuario_creacion' => auth()->user()->usu_id
                ]);
            }

            if(isset($detalleDevengados->indemnizacion) && !empty($detalleDevengados->indemnizacion)) {
                DsnDetalleDevengadoDaop::create([
                    'cdn_id'           => $cdnId,
                    'ddv_concepto'     => 'Indemnizacion',
                    'ddv_valor'        => isset($detalleDevengados->indemnizacion) && !empty($detalleDevengados->indemnizacion) ? $detalleDevengados->indemnizacion : 0,
                    'estado'           => 'ACTIVO',
                    'usuario_creacion' => auth()->user()->usu_id
                ]);
            }

            if(isset($detalleDevengados->reintegro) && !empty($detalleDevengados->reintegro)) {
                DsnDetalleDevengadoDaop::create([
                    'cdn_id'           => $cdnId,
                    'ddv_concepto'     => 'Reintegro',
                    'ddv_valor'        => isset($detalleDevengados->reintegro) && !empty($detalleDevengados->reintegro) ? $detalleDevengados->reintegro : 0,
                    'estado'           => 'ACTIVO',
                    'usuario_creacion' => auth()->user()->usu_id
                ]);
            }

            return [
                'error'  => false,
                'errors' => null,
                'traza'  => null,
            ];
        } catch (\Exception $e) {
            return [
                'error'  => true,
                'errors' => 'Se presentó un error al guardar la información de detalle de devengados del documento de nómina electrónica',
                'traza'  => $e->getFile() . ' - Línea ' . $e->getLine() . ': ' . $e->getMessage() . "\n\nTrace:\n" . $e->getTraceAsString()
            ];
        }
    }

    /**
     * Guarda el detalle de deducciones de un documento de nómina electrónica.
     *
     * @param int $cdnId ID de cabecera del documento
     * @param \stdClass $detalleDeducciones Detalle de deducciones
     * @return array Array con información del resultado del procesamiento del detalle de deducciones
     */
    public function guardarDetalleDeducciones(int $cdnId, \stdClass $detalleDeducciones) {
        try {
            DsnDetalleDeduccionDaop::where('cdn_id', $cdnId)
                ->delete();

            if(isset($detalleDeducciones->salud) && !empty($detalleDeducciones->salud)) {
                DsnDetalleDeduccionDaop::create([
                    'cdn_id'           => $cdnId,
                    'ddd_concepto'     => 'Salud',
                    'ddd_porcentaje'   => isset($detalleDeducciones->salud->porcentaje) && !empty($detalleDeducciones->salud->porcentaje) ? $detalleDeducciones->salud->porcentaje : 0,
                    'ddd_valor'        => isset($detalleDeducciones->salud->deduccion) && !empty($detalleDeducciones->salud->deduccion) ? $detalleDeducciones->salud->deduccion : 0,
                    'estado'           => 'ACTIVO',
                    'usuario_creacion' => auth()->user()->usu_id
                ]);
            }
            
            if(isset($detalleDeducciones->fondo_pension) && !empty($detalleDeducciones->fondo_pension)) {
                DsnDetalleDeduccionDaop::create([
                    'cdn_id'           => $cdnId,
                    'ddd_concepto'     => 'FondoPension',
                    'ddd_porcentaje'   => isset($detalleDeducciones->fondo_pension->porcentaje) && !empty($detalleDeducciones->fondo_pension->porcentaje) ? $detalleDeducciones->fondo_pension->porcentaje : 0,
                    'ddd_valor'        => isset($detalleDeducciones->fondo_pension->deduccion) && !empty($detalleDeducciones->fondo_pension->deduccion) ? $detalleDeducciones->fondo_pension->deduccion : 0,
                    'estado'           => 'ACTIVO',
                    'usuario_creacion' => auth()->user()->usu_id
                ]);
            }
            
            if(isset($detalleDeducciones->fondo_sp) && !empty($detalleDeducciones->fondo_sp)) {
                DsnDetalleDeduccionDaop::create([
                    'cdn_id'                   => $cdnId,
                    'ddd_concepto'             => 'FondoSP',
                    'ddd_porcentaje'           => isset($detalleDeducciones->fondo_sp->porcentaje) && !empty($detalleDeducciones->fondo_sp->porcentaje) ? $detalleDeducciones->fondo_sp->porcentaje : 0,
                    'ddd_valor'                => isset($detalleDeducciones->fondo_sp->deduccion_sp) && !empty($detalleDeducciones->fondo_sp->deduccion_sp) ? $detalleDeducciones->fondo_sp->deduccion_sp : 0,
                    'ddd_porcentaje_adicional' => isset($detalleDeducciones->fondo_sp->porcentaje_sub) && !empty($detalleDeducciones->fondo_sp->porcentaje_sub) ? $detalleDeducciones->fondo_sp->porcentaje_sub : 0,
                    'ddd_valor_adicional'      => isset($detalleDeducciones->fondo_sp->deduccion_sub) && !empty($detalleDeducciones->fondo_sp->deduccion_sub) ? $detalleDeducciones->fondo_sp->deduccion_sub : 0,
                    'estado'                   => 'ACTIVO',
                    'usuario_creacion'         => auth()->user()->usu_id
                ]);
            }
            
            if(isset($detalleDeducciones->sindicatos) && !empty($detalleDeducciones->sindicatos)) {
                foreach($detalleDeducciones->sindicatos as $sindicato) {
                    DsnDetalleDeduccionDaop::create([
                        'cdn_id'           => $cdnId,
                        'ddd_concepto'     => 'Sindicatos',
                        'ddd_porcentaje'   => isset($sindicato->porcentaje) && !empty($sindicato->porcentaje) ? $sindicato->porcentaje : 0,
                        'ddd_valor'        => isset($sindicato->deduccion) && !empty($sindicato->deduccion) ? $sindicato->deduccion : 0,
                        'estado'           => 'ACTIVO',
                        'usuario_creacion' => auth()->user()->usu_id
                    ]);
                }
            }
            
            if(isset($detalleDeducciones->sanciones) && !empty($detalleDeducciones->sanciones)) {
                foreach($detalleDeducciones->sanciones as $sancion) {
                    DsnDetalleDeduccionDaop::create([
                        'cdn_id'              => $cdnId,
                        'ddd_concepto'        => 'Sanciones',
                        'ddd_valor'           => isset($sancion->sancion_publica) && !empty($sancion->sancion_publica) ? $sancion->sancion_publica : 0,
                        'ddd_valor_adicional' => isset($sancion->sancion_privada) && !empty($sancion->sancion_privada) ? $sancion->sancion_privada : 0,
                        'estado'              => 'ACTIVO',
                        'usuario_creacion'    => auth()->user()->usu_id
                    ]);
                }
            }
            
            if(isset($detalleDeducciones->libranzas) && !empty($detalleDeducciones->libranzas)) {
                foreach($detalleDeducciones->libranzas as $libranza) {
                    DsnDetalleDeduccionDaop::create([
                        'cdn_id'           => $cdnId,
                        'ddd_concepto'     => 'Libranzas',
                        'ddd_descripcion'  => isset($libranza->descripcion) && !empty($libranza->descripcion) ? $libranza->descripcion : null,
                        'ddd_valor'        => isset($libranza->deduccion) && !empty($libranza->deduccion) ? $libranza->deduccion : 0,
                        'estado'           => 'ACTIVO',
                        'usuario_creacion' => auth()->user()->usu_id
                    ]);
                }
            }
            
            if(isset($detalleDeducciones->pagos_terceros) && !empty($detalleDeducciones->pagos_terceros)) {
                foreach($detalleDeducciones->pagos_terceros as $pagoTercero) {
                    DsnDetalleDeduccionDaop::create([
                        'cdn_id'           => $cdnId,
                        'ddd_concepto'     => 'PagosTerceros',
                        'ddd_valor'        => isset($pagoTercero->pago_tercero) && !empty($pagoTercero->pago_tercero) ? $pagoTercero->pago_tercero : 0,
                        'estado'           => 'ACTIVO',
                        'usuario_creacion' => auth()->user()->usu_id
                    ]);
                }
            }
            
            if(isset($detalleDeducciones->anticipos) && !empty($detalleDeducciones->anticipos)) {
                foreach($detalleDeducciones->anticipos as $anticipo) {
                    DsnDetalleDeduccionDaop::create([
                        'cdn_id'           => $cdnId,
                        'ddd_concepto'     => 'Anticipos',
                        'ddd_valor'        => isset($anticipo->anticipo) && !empty($anticipo->anticipo) ? $anticipo->anticipo : 0,
                        'estado'           => 'ACTIVO',
                        'usuario_creacion' => auth()->user()->usu_id
                    ]);
                }
            }
            
            if(isset($detalleDeducciones->otras_deducciones) && !empty($detalleDeducciones->otras_deducciones)) {
                foreach($detalleDeducciones->otras_deducciones as $otraDeduccion) {
                    DsnDetalleDeduccionDaop::create([
                        'cdn_id'           => $cdnId,
                        'ddd_concepto'     => 'OtrasDeducciones',
                        'ddd_valor'        => isset($otraDeduccion->otra_deduccion) && !empty($otraDeduccion->otra_deduccion) ? $otraDeduccion->otra_deduccion : 0,
                        'estado'           => 'ACTIVO',
                        'usuario_creacion' => auth()->user()->usu_id
                    ]);
                }
            }
            
            if(isset($detalleDeducciones->pension_voluntaria) && !empty($detalleDeducciones->pension_voluntaria)) {
                DsnDetalleDeduccionDaop::create([
                    'cdn_id'           => $cdnId,
                    'ddd_concepto'     => 'PensionVoluntaria',
                    'ddd_valor'        => isset($detalleDeducciones->pension_voluntaria) && !empty($detalleDeducciones->pension_voluntaria) ? $detalleDeducciones->pension_voluntaria : 0,
                    'estado'           => 'ACTIVO',
                    'usuario_creacion' => auth()->user()->usu_id
                ]);
            }
            
            if(isset($detalleDeducciones->retencion_fuente) && !empty($detalleDeducciones->retencion_fuente)) {
                DsnDetalleDeduccionDaop::create([
                    'cdn_id'           => $cdnId,
                    'ddd_concepto'     => 'RetencionFuente',
                    'ddd_valor'        => isset($detalleDeducciones->retencion_fuente) && !empty($detalleDeducciones->retencion_fuente) ? $detalleDeducciones->retencion_fuente : 0,
                    'estado'           => 'ACTIVO',
                    'usuario_creacion' => auth()->user()->usu_id
                ]);
            }
            
            if(isset($detalleDeducciones->afc) && !empty($detalleDeducciones->afc)) {
                DsnDetalleDeduccionDaop::create([
                    'cdn_id'           => $cdnId,
                    'ddd_concepto'     => 'AFC',
                    'ddd_valor'        => isset($detalleDeducciones->afc) && !empty($detalleDeducciones->afc) ? $detalleDeducciones->afc : 0,
                    'estado'           => 'ACTIVO',
                    'usuario_creacion' => auth()->user()->usu_id
                ]);
            }
            
            if(isset($detalleDeducciones->cooperativa) && !empty($detalleDeducciones->cooperativa)) {
                DsnDetalleDeduccionDaop::create([
                    'cdn_id'           => $cdnId,
                    'ddd_concepto'     => 'Cooperativa',
                    'ddd_valor'        => isset($detalleDeducciones->cooperativa) && !empty($detalleDeducciones->cooperativa) ? $detalleDeducciones->cooperativa : 0,
                    'estado'           => 'ACTIVO',
                    'usuario_creacion' => auth()->user()->usu_id
                ]);
            }
            
            if(isset($detalleDeducciones->embargo_fiscal) && !empty($detalleDeducciones->embargo_fiscal)) {
                DsnDetalleDeduccionDaop::create([
                    'cdn_id'           => $cdnId,
                    'ddd_concepto'     => 'EmbargoFiscal',
                    'ddd_valor'        => isset($detalleDeducciones->embargo_fiscal) && !empty($detalleDeducciones->embargo_fiscal) ? $detalleDeducciones->embargo_fiscal : 0,
                    'estado'           => 'ACTIVO',
                    'usuario_creacion' => auth()->user()->usu_id
                ]);
            }
            
            if(isset($detalleDeducciones->plan_complementarios) && !empty($detalleDeducciones->plan_complementarios)) {
                DsnDetalleDeduccionDaop::create([
                    'cdn_id'           => $cdnId,
                    'ddd_concepto'     => 'PlanComplementarios',
                    'ddd_valor'        => isset($detalleDeducciones->plan_complementarios) && !empty($detalleDeducciones->plan_complementarios) ? $detalleDeducciones->plan_complementarios : 0,
                    'estado'           => 'ACTIVO',
                    'usuario_creacion' => auth()->user()->usu_id
                ]);
            }
            
            if(isset($detalleDeducciones->educacion) && !empty($detalleDeducciones->educacion)) {
                DsnDetalleDeduccionDaop::create([
                    'cdn_id'           => $cdnId,
                    'ddd_concepto'     => 'Educacion',
                    'ddd_valor'        => isset($detalleDeducciones->educacion) && !empty($detalleDeducciones->educacion) ? $detalleDeducciones->educacion : 0,
                    'estado'           => 'ACTIVO',
                    'usuario_creacion' => auth()->user()->usu_id
                ]);
            }
            
            if(isset($detalleDeducciones->reintegro) && !empty($detalleDeducciones->reintegro)) {
                DsnDetalleDeduccionDaop::create([
                    'cdn_id'           => $cdnId,
                    'ddd_concepto'     => 'Reintegro',
                    'ddd_valor'        => isset($detalleDeducciones->reintegro) && !empty($detalleDeducciones->reintegro) ? $detalleDeducciones->reintegro : 0,
                    'estado'           => 'ACTIVO',
                    'usuario_creacion' => auth()->user()->usu_id
                ]);
            }
            
            if(isset($detalleDeducciones->deuda) && !empty($detalleDeducciones->deuda)) {
                DsnDetalleDeduccionDaop::create([
                    'cdn_id'           => $cdnId,
                    'ddd_concepto'     => 'Deuda',
                    'ddd_valor'        => isset($detalleDeducciones->deuda) && !empty($detalleDeducciones->deuda) ? $detalleDeducciones->deuda : 0,
                    'estado'           => 'ACTIVO',
                    'usuario_creacion' => auth()->user()->usu_id
                ]);
            }

            return [
                'error'  => false,
                'errors' => null,
                'traza'  => null,
            ];
        } catch (\Exception $e) {
            return [
                'error'  => true,
                'errors' => 'Se presentó un error al guardar la información de detalle de deducciones del documento de nómina electrónica',
                'traza'  => $e->getFile() . ' - Línea ' . $e->getLine() . ': ' . $e->getMessage() . "\n\nTrace:\n" . $e->getTraceAsString()
            ];
        }
    }

    /**
     * Elimina registros asociados a un documento de nómina electrónica.
     * 
     * Generalmente este proceso se genera debido al registro incompleto de información de un documento de nómina electrónica
     *
     * @param int $cdn_id ID del documento de nómina electrónica
     * @return void
     */
    public function eliminarDsnIncompleto(int $cdn_id) {
        $estados = DsnEstadoDocumentoDaop::select(['est_id'])->where('cdn_id', $cdn_id)->get();
        if($estados) $estados->map(function($registro) { $registro->delete(); });

        $deducciones = DsnDetalleDeduccionDaop::select(['ddd_id'])->where('cdn_id', $cdn_id)->get();
        if($deducciones) $deducciones->map(function($registro) { $registro->delete(); });

        $devengados = DsnDetalleDevengadoDaop::select(['ddv_id'])->where('cdn_id', $cdn_id)->get();
        if($devengados) $devengados->map(function($registro) { $registro->delete(); });

        $cabecera = DsnCabeceraDocumentoNominaDaop::find($cdn_id);
        if($cabecera) $cabecera->delete();
    }

    /**
     * Crea un registro en la tabla de procesamiento json.
     *
     * @param string $pjj_tipo Tipo de procesamiento
     * @param string $pjj_json Json relacionado con el procesamiento
     * @param string $pjj_procesado Indicador de si el proceso ya fue realizado o no
     * @param string|null $pjj_errores Json de errores relacionados con el procesamiento
     * @param integer|0 $age_id Id del agendamiento, si tiene alguno relacionado
     * @param string|null $age_estado_proceso_json Estado del procesamiento
     * @param integer $usu_id ID del usuario
     * @return void
     */
    public function crearProcesamientoJson(string $pjj_tipo, string $pjj_json, string $pjj_procesado, string $pjj_errores = null, int $age_id = 0, string $age_estado_proceso_json = null, int $usu_id) {
        EtlProcesamientoJson::create([
            'pjj_tipo'                => $pjj_tipo,
            'pjj_json'                => $pjj_json,
            'pjj_procesado'           => $pjj_procesado,
            'pjj_errores'             => $pjj_errores,
            'age_id'                  => $age_id,
            'age_estado_proceso_json' => $age_estado_proceso_json,
            'usuario_creacion'        => $usu_id,
            'estado'                  => 'ACTIVO'
        ]);
    }


    /**
     * Crea un agendamiento en el sistema.
     *
     * @param integer $usu_id ID de Usuario
     * @param integer $bdd_id ID de la BD del usuario
     * @param string $age_proceso Proceso a agendar
     * @param integer $age_cantidad_documentos Cantidad de documentos relacionados con el agendamiento
     * @param integer $age_prioridad Prioridad del agendamiento
     * @return AdoAgendamiento Instancia del modelo para el agendamiento creado
     */
    public function crearAgendamiento(int $usu_id, int $bdd_id, string $age_proceso, int $age_cantidad_documentos, int $age_prioridad = null) {
        return AdoAgendamiento::create([
            'usu_id'                    => $usu_id,
            'bdd_id'                    => $bdd_id,
            'age_proceso'               => $age_proceso,
            'age_cantidad_documentos'   => $age_cantidad_documentos,
            'age_prioridad'             => $age_prioridad,
            'usuario_creacion'          => $usu_id,
            'estado'                    => 'ACTIVO'
        ]);
    }

    /**
     * Crea un nuevo estado para un documento de nómina electrónica.
     *
     * @param integer $cdn_id ID del documento para el cual se crea el estado
     * @param string $estado Nombre del estado a crear
     * @param string|null $est_resultado Resultado del estado
     * @param string|null $est_mensaje_resultado Mensaje de resultado del estado
     * @param object|null $est_object Objeto resultado del estado
     * @param string|null $est_correos Correos enviados
     * @param string|null $est_informacion_adicional Información adicional del estado
     * @param integer|null $age_id ID del agendamiento relacionado con el estado
     * @param integer $usu_id ID del usuario que crea el nuevo estado
     * @param string|null $est_inicio_proceso Fecha y hora del inicio de procesamiento
     * @param string|null $est_fin_proceso Fecha y hora del final del procesamiento
     * @param float|null $est_tiempo_procesamiento Tiempo de procesamiento
     * @param string|null $est_ejecucion Estado de ejecución
     * @return void
     */
    public function creaNuevoEstadoDocumento(int $cdn_id, string $estado, string $est_resultado = null, string $est_mensaje_resultado = null, object $est_object = null, string $est_correos = null, string $est_informacion_adicional = null, int $age_id = null, int $usu_id, string $est_inicio_proceso = null, string $est_fin_proceso = null, float $est_tiempo_procesamiento = null, string $est_ejecucion = null) {
        DsnEstadoDocumentoDaop::create([
            'cdn_id'                    => $cdn_id,
            'est_estado'                => $estado,
            'est_resultado'             => $est_resultado,
            'est_mensaje_resultado'     => $est_mensaje_resultado,
            'est_object'                => $est_object ? $est_object : null,
            'est_correos'               => $est_correos,
            'est_informacion_adicional' => $est_informacion_adicional,
            'age_id'                    => $age_id,
            'age_usu_id'                => $usu_id,
            'est_inicio_proceso'        => $est_inicio_proceso,
            'est_fin_proceso'           => $est_fin_proceso,
            'est_tiempo_procesamiento'  => $est_tiempo_procesamiento,
            'est_ejecucion'             => $est_ejecucion,
            'usuario_creacion'          => $usu_id,
            'estado'                    => 'ACTIVO'
        ]);
    }

    /**
     * Crea un agendamiento en el sistema para procesos de nómina electrónica.
     * 
     * Cada agendamiento se encarga de crear los estado correspondiente para cada documento de nómina electrónica
     *
     * @param string $proceso Proceso a agendar
     * @param string $estado Esatdo a crear
     * @param array $documentosAgendar Array con los IDs de los documentos a agendar
     * @param array $prioridades Array con la prioridad de agendamiento por empleador
     * @return void
     */
    public function crearAgendamientoNomina(string $proceso, string $estado, array $documentosAgendar, array $prioridades) {
        foreach($documentosAgendar as $empIdentificacion => $docsAgendarEmpleador) {
            $grupos = array_chunk($docsAgendarEmpleador, auth()->user()->getBaseDatos->bdd_cantidad_procesamiento_edi);
            foreach($grupos as $documentos) {
                $agendamiento = $this->crearAgendamiento(
                    auth()->user()->usu_id,
                    auth()->user()->bdd_id,
                    $proceso,
                    count($documentos),
                    array_key_exists($empIdentificacion, $prioridades) && !empty($prioridades[$empIdentificacion]) ? $prioridades[$empIdentificacion] : null
                );

                foreach($documentos as $cdnId) {
                    $this->creaNuevoEstadoDocumento($cdnId, $estado, null, null, null, null, null, $agendamiento->age_id, auth()->user()->usu_id, null, null, null, null);
                }
            }
        }
    }
}
