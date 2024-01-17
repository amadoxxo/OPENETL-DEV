<?php
namespace App\Http\Modulos\NominaElectronica;

use Validator;
use App\Traits\DiTrait;
use App\Http\Modulos\NominaElectronica\WriterInput;
use App\Http\Modulos\NominaElectronica\Validaciones;
use App\Http\Modulos\NominaElectronica\ConstantsDataInput;
use App\Http\Modulos\NominaElectronica\DsnCabeceraDocumentosNominaDaop\DsnCabeceraDocumentoNominaDaop;

class DataInput {
    use DiTrait;

    private $classValidaciones;

    /**
     * Array con la información de cabecera del documento de Nómina Electrónica.
     *
     * @var array
     */
    private $cabecera = [];

    public function __construct() {
        $this->classValidaciones = new Validaciones($this);
        $this->classWriterInput  = new WriterInput();
    }

    /**
     * Realiza validaciones sobre la data recibida de un documento de nómina electrónica.
     *
     * @return void
     */
    public function validacionesNominaElectronica() {
        if (!isset($this->data->{ConstantsDataInput::ROOT})) {
            $this->errors[] = ConstantsDataInput::NOT_DOCUMENT_ROOT;
            return;
        }
        $root = $this->data->{ConstantsDataInput::ROOT};

        if (!isset($root->DN)) {
            $this->errors[] = ConstantsDataInput::NOT_TYPE_ALLOWED_DN;
            return;
        }

        $grupos = [
            ConstantsDataInput::DN
        ];

        foreach ($grupos as $grupo) {
            if (isset($root->{$grupo})) {
                $this->docType = $grupo;
                $this->sets = $root->{$grupo};
            } else {
                continue;
            }

            if (!is_array($this->sets)) {
                $this->errors[] = sprintf(ConstantsDataInput::DOCS_ARE_NOT_ARRAY, $this->docType);
                continue;
            }

            // Filtra información para evitar la existencia de documento repetidos dentro del mismo array de procesamiento
            $mapa = [];
            foreach ($this->sets as $indice => $item) {
                if (isset($item->cdn_prefijo) && isset($item->cdn_consecutivo)) {
                    $key = trim($item->cdn_prefijo) . trim($item->cdn_consecutivo);
                    if (array_key_exists($key, $mapa))
                        $mapa[$key][] = $indice;
                    else
                        $mapa[$key] = [$indice];
                }
            }

            // Documento repetidos que serán descartados
            $evitar = [];
            foreach ($mapa as $subgrupo)
                if (count($subgrupo) > 1)
                    $evitar = array_merge($evitar, $subgrupo);

            foreach ($this->sets as $key => $documentoNomina) {
                $this->errors       = [];
                $this->cabecera     = [];
                $est_inicio_proceso = date('Y-m-d H:i:s');
                $json_original      = serialize($documentoNomina); // Serializa el objeto del documento para que permanezca inmutable y poder guardarlo en disco tal cual es recibido

                if (!in_array($key, $evitar)) {
                    if($documentoNomina->tde_codigo == '103' && $documentoNomina->ntn_codigo == '2') {
                        $this->definirAplicaNovedadClasificacion($documentoNomina);
                        $this->validacionesDocumentoNominaEliminar($documentoNomina);
                    } else {
                        $this->validacionesGenerales($documentoNomina);
                        $this->definirAplicaNovedadClasificacion($documentoNomina);
                        $this->validacionesEspecificas($documentoNomina);
                    }
                } else {
                    $doc         = $this->docType . '-' . $documentoNomina->cdn_prefijo . $documentoNomina->cdn_consecutivo;
                    $consecutivo = $documentoNomina->cdn_consecutivo;
                    $prefijo     = $documentoNomina->cdn_prefijo;
                    $this->failure[] = [
                        'documento'           => $doc,
                        'consecutivo'         => $consecutivo,
                        'prefijo'             => $prefijo,
                        'errors'              => ['Existen otros documentos en este bloque de información con el mismo prefijo y consecutivo'],
                        'fecha_procesamiento' => date('Y-m-d'),
                        'hora_procesamiento'  => date('H:i:s')
                    ];
                }

                if (empty($this->errors)) {
                    $this->successful[] = [
                        'json'               => $documentoNomina,
                        'json_original'      => unserialize($json_original),
                        'est_inicio_proceso' => $est_inicio_proceso
                    ];
                } else {
                    if (isset($documentoNomina->cdn_consecutivo) && isset($documentoNomina->cdn_prefijo)) {
                        $doc         = $this->docType . '-' . $documentoNomina->cdn_prefijo . $documentoNomina->cdn_consecutivo;
                        $consecutivo = $documentoNomina->cdn_consecutivo;
                        $prefijo     = $documentoNomina->cdn_prefijo;
                    } else
                        $doc = "documento[".($indice+1)."]";

                    $this->failure[] = [
                        'documento'           => isset($doc) ? $doc : '',
                        'consecutivo'         => isset($consecutivo) ? $consecutivo : '',
                        'prefijo'             => isset($prefijo) ? $prefijo : '',
                        'errors'              => array_merge($this->errors),
                        'fecha_procesamiento' => date('Y-m-d'),
                        'hora_procesamiento'  => date('H:i:s')
                    ];
                }
            }
        }
    }

    /**
     * Realiza las validaciones generales sobre un documento de nómina electrónica
     * 
     * @param \stdClass $documento Documento de nómina electrónica en procesamiento
     */
    private function validacionesGenerales(\stdClass $documento) {
        if(!isset($documento->tde_codigo) || (isset($documento->tde_codigo) && empty($documento->tde_codigo)))
            $this->errors[] = 'La propiedad Tipo Documento Electrónico [tde_codigo] no se encontró o está vacia';

        if(isset($documento->tde_codigo) && $documento->tde_codigo == '102' && isset($documento->ntn_codigo) && !empty($documento->ntn_codigo))
            $this->errors[] = 'La propiedad Tipo de Nota [ntn_codigo] solo puede enviarse con valor para el Tipo de Documento Electrónico [103 - Nota de Ajuste de Documento Soporte de Pago de Nómina Electrónica]';

        if(isset($documento->tde_codigo) && $documento->tde_codigo == '103' && isset($documento->cdn_aplica_novedad) && !empty($documento->cdn_aplica_novedad))
            $this->errors[] = 'La propiedad Aplica Novedad [cdn_aplica_novedad] solo puede enviarse con valor para el Tipo de Documento Electrónico [102 - Documento Soporte de Pago de Nómina Electrónica]';
    }

    /**
     * Define la información para cdn_aplica_novedad y cdn_clasificación de un documento de nómina electrónica
     *
     * @param \stdClass $documentoNomina Documento de nómina electrónica en procesamiento
     * @return void
     */
    private function definirAplicaNovedadClasificacion(\stdClass &$documentoNomina) {
        if(isset($documentoNomina->tde_codigo) && $documentoNomina->tde_codigo == '102') {
            if(isset($documentoNomina->cdn_aplica_novedad) && !empty($documentoNomina->cdn_aplica_novedad))
                $documentoNomina->cdn_aplica_novedad = $documentoNomina->cdn_aplica_novedad;
            else
                $documentoNomina->cdn_aplica_novedad = 'NO';

            $documentoNomina->cdn_clasificacion = $documentoNomina->cdn_aplica_novedad == 'SI' ? ConstantsDataInput::CLASIFICACION_NOVEDAD : ConstantsDataInput::CLASIFICACION_NOMINA;
        } elseif(isset($documentoNomina->tde_codigo) && $documentoNomina->tde_codigo == '103') {
            if(isset($documentoNomina->ntn_codigo) && $documentoNomina->ntn_codigo == '1')
                $documentoNomina->cdn_clasificacion = ConstantsDataInput::CLASIFICACION_AJUSTE;
            elseif(isset($documentoNomina->ntn_codigo) && $documentoNomina->ntn_codigo == '2')
                $documentoNomina->cdn_clasificacion = ConstantsDataInput::CLASIFICACION_ELIMINAR;
        }
    }

    /**
     * Realiza las validaciones de información a nivel de cabecera.
     * 
     * @param \stdClass $documento Documento de nómina electrónica en procesamiento
     * @return void
     */
    private function validacionesCabecera(&$documento) {
        $reglas = DsnCabeceraDocumentoNominaDaop::$rules;

        $reglas['tde_codigo']                   = 'required|string|max:5';
        $reglas['ntn_codigo']                   = 'required_if:tde_codigo,103|string|max:5';
        $reglas['cdn_aplica_novedad']           = 'required_if:tde_codigo,102|string|max:2|in:SI,NO';
        $reglas['emp_identificacion']           = 'required|string|max:20';
        $reglas['tra_identificacion']           = 'required|string|max:20';
        $reglas['cdn_prefijo']                  = 'nullable|string|max:10';
        $reglas['cdn_consecutivo']              = 'required|numeric|min:1';
        $reglas['npe_codigo']                   = 'required|string|max:10';
        $reglas['cdn_fecha_emision']            = 'required|date_format:Y-m-d H:i:s';
        $reglas['cdn_fecha_inicio_liquidacion'] = 'nullable|date_format:Y-m-d';
        $reglas['cdn_fecha_fin_liquidacion']    = 'nullable|date_format:Y-m-d|after_or_equal:cdn_fecha_inicio_liquidacion';
        $reglas['cdn_tiempo_laborado']          = 'nullable|numeric|min:1';
        $reglas['pai_codigo']                   = 'required|string|max:10';
        $reglas['cdn_notas']                    = 'nullable|array';
        $reglas['cdn_fechas_pago']              = 'nullable|array';
        $reglas['mon_codigo']                   = 'required|string|max:10';
        $reglas['cdn_trm']                      = 'required_unless:mon_codigo,COP|numeric|regex:/^[0-9]{1,13}(\.[0-9]{0,6})?$/';
        $reglas['cdn_redondeo']                 = 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{0,6})?$/';
        $reglas['cdn_total_comprobante']        = 'required|numeric|regex:/^[0-9]{1,13}(\.[0-9]{0,6})?$/';

        if($this->cabecera['tde_codigo'] == '102' || ($this->cabecera['tde_codigo'] == '103' && $this->cabecera['ntn_codigo'] == '1')) {
            $reglas['cdn_fecha_inicio_liquidacion'] = 'required|date_format:Y-m-d';
            $reglas['cdn_fecha_fin_liquidacion']    = 'required|date_format:Y-m-d|after_or_equal:cdn_fecha_inicio_liquidacion';
            $reglas['cdn_tiempo_laborado']          = 'required|numeric|min:1';
        }

        $keysEliminar = ['cdn_origen', 'cdn_clasificacion', 'cdn_lote', 'tde_id', 'ntn_id', 'emp_id', 'tra_id', 'npe_id', 'pai_id', 'fpa_id', 'mpa_id', 'cdn_prefijo_predecesor', 'cdn_consecutivo_predecesor', 'cdn_fecha_emision_predecesor', 'cdn_cune_predecesor', 'mon_id'];
        foreach ($keysEliminar as $key)
            unset($reglas[$key]);

        $validarCabecera = Validator::make($this->cabecera, $reglas, $this->mensajeNumerosPositivos);
        if(!empty($validarCabecera->errors()->all()))
            foreach($validarCabecera->errors()->all() as $error)
                $this->errors[] = $error;

        $tdeId = $this->classValidaciones->checkTipoDocumentoElectronico($this->errors, $this->cabecera['tde_codigo']);
        $documento->tde_id = $tdeId;
        unset($documento->tde_codigo);

        if(isset($this->cabecera['ntn_codigo']) && !empty($this->cabecera['ntn_codigo'])) {
            $ntnId = $this->classValidaciones->checkNominaTipoNota($this->errors, $this->cabecera['ntn_codigo']);
            $documento->ntn_id = $ntnId;
            unset($documento->ntn_codigo);
        }

        $empId = $this->classValidaciones->checkEmpleador($this->errors, $this->cabecera['emp_identificacion'], $this->empleadores);
        $documento->emp_id = $empId;
        unset($documento->emp_identificacion);

        // Si el documento incluye la propiedad 'trabajador' para crear/actualizar el trabajador, se debe validar que las propiedades tra_identificacion del documento y del trabajador sean iguales
        if(isset($documento->trabajador) && !empty($documento->trabajador)) {
            if($documento->trabajador->tra_identificacion != $documento->tra_identificacion)
                $this->errors[] = 'La identificación de trabajador en el documento y dentro del objeto [trabajador] no son iguales';
        }

        $traId = $this->classValidaciones->checkTrabajador($this->errors, $this->cabecera['emp_identificacion'], $this->cabecera['tra_identificacion'], $this->empleadores, $this->trabajadores, (isset($documento->trabajador) && !empty($documento->trabajador) ? true : false));
        $documento->tra_id = $traId;
        unset($documento->tra_identificacion);

        $npeId = $this->classValidaciones->checkNominaPeriodo($this->errors, $this->cabecera['npe_codigo']);
        $documento->npe_id = $npeId;
        unset($documento->npe_codigo);

        $paiId = $this->classValidaciones->checkPais($this->errors, $this->cabecera['pai_codigo'], $this->paises);
        $documento->pai_id = $paiId;
        unset($documento->pai_codigo);

        $depId = $this->classValidaciones->checkDepartamento(
            $this->errors, 
            (array_key_exists($this->cabecera['pai_codigo'], $this->paises) ? $this->paises[$this->cabecera['pai_codigo']]->pai_id : 0),
            $this->cabecera['pai_codigo'],
            $this->cabecera['dep_codigo'],
            $this->departamentos
        );
        $documento->dep_id = $depId;
        unset($documento->dep_codigo);

        $keyPaiDep = sprintf("%d_%s", (array_key_exists($this->cabecera['pai_codigo'], $this->paises) ? $this->paises[$this->cabecera['pai_codigo']]->pai_id : ''), $this->cabecera['dep_codigo']);
        $munId = $this->classValidaciones->checkMunicipio(
            $this->errors, 
            (array_key_exists($this->cabecera['pai_codigo'], $this->paises) ? $this->paises[$this->cabecera['pai_codigo']]->pai_id : 0),
            $this->cabecera['pai_codigo'],
            (array_key_exists($keyPaiDep, $this->departamentos) ? $this->departamentos[$keyPaiDep]->dep_id : 0),
            $this->cabecera['dep_codigo'],
            $this->cabecera['mun_codigo'],
            $this->municipios
        );
        $documento->mun_id = $munId;
        unset($documento->mun_codigo);

        if(empty($this->errors)) {
            $documento->cdn_medios_pago = $this->classValidaciones->checkMediosPago($this->errors, $this->cabecera['cdn_medios_pago']);
            $this->classValidaciones->checkFechasPago($this->errors, $this->cabecera['cdn_fechas_pago']);

            if(
                (
                    $this->cabecera['tde_codigo'] == 103 ||
                    (
                        $this->cabecera['tde_codigo'] == 102 &&
                        $this->cabecera['cdn_aplica_novedad'] == 'SI'
                    )
                ) &&
                array_key_exists('cdn_documento_predecesor', $this->cabecera) &&
                !empty($this->cabecera['cdn_documento_predecesor'])
            )
                $this->classValidaciones->checkDocumentoPredecesor($this->errors, $this->cabecera['tde_codigo'], $this->cabecera['cdn_aplica_novedad'], $this->cabecera['cdn_documento_predecesor']);
            elseif(
                (
                    $this->cabecera['tde_codigo'] == 103 ||
                    (
                        $this->cabecera['tde_codigo'] == 102 &&
                        $this->cabecera['cdn_aplica_novedad'] == 'SI'
                    )
                ) &&
                (
                    !array_key_exists('cdn_documento_predecesor', $this->cabecera) ||
                    (
                        array_key_exists('cdn_documento_predecesor', $this->cabecera) &&
                        empty($this->cabecera['cdn_documento_predecesor'])
                    )
                )
            )
                $this->errors[] = 'La propiedad Documento Predecesor [cdn_documento_predecesor] no se encontró o está vacia';
            elseif(
                $this->cabecera['tde_codigo'] == 102 &&
                $this->cabecera['cdn_aplica_novedad'] != 'SI' &&
                array_key_exists('cdn_documento_predecesor', $this->cabecera) &&
                !empty($this->cabecera['cdn_documento_predecesor'])
            )
                $this->errors[] = 'La propiedad Documento Predecesor [cdn_documento_predecesor] no debe ser enviada cuando el tipo de documento electrónico es [102] y no aplica novedad';

            $monId = $this->classValidaciones->checkMoneda($this->errors, $this->cabecera['mon_codigo']);
            $documento->mon_id = $monId;
            unset($documento->mon_codigo);

            if($this->origen != ConstantsDataInput::ORIGEN_MANUAL) {
                $this->classValidaciones->checkValorDevengado($this->errors, $documento);
                $this->classValidaciones->checkValorDeducciones($this->errors, $documento);
                $this->classValidaciones->checkValorTotalComprobante($this->errors, $documento);
            }
        }
    }

    /**
     * Realiza las validaciones de información a nivel de detalle de devengados.
     * 
     * @param \stdClass $documento Documento de nómina electrónica en procesamiento
     * @return void
     */
    private function validacionesDetalleDevengados(&$documento) {
        if(isset($documento->ddv_detalle_devengados) && !empty($documento->ddv_detalle_devengados))
            $this->classValidaciones->checkDevengadosComposicion($this->errors, $documento->ddv_detalle_devengados);

        if(isset($documento->ddv_detalle_devengados) && isset($documento->ddv_detalle_devengados->basico) && !empty($documento->ddv_detalle_devengados->basico))
            $this->classValidaciones->checkDevengadosBasico($this->errors, $documento->ddv_detalle_devengados->basico);
        else 
            $this->errors[] = 'La sección [ddv_detalle_devengados.basico] no existe o está vacia';

        if(isset($documento->ddv_detalle_devengados) && isset($documento->ddv_detalle_devengados->transporte) && !empty($documento->ddv_detalle_devengados->transporte))
            $this->classValidaciones->checkDevengadosTransporte($this->errors, $documento->ddv_detalle_devengados->transporte);

        if(isset($documento->ddv_detalle_devengados) && isset($documento->ddv_detalle_devengados->horas_extras_recargos) && !empty($documento->ddv_detalle_devengados->horas_extras_recargos))
            $this->classValidaciones->checkDevengadosHorasExtrasRecargos($this->errors, $documento->ddv_detalle_devengados->horas_extras_recargos);

        if(isset($documento->ddv_detalle_devengados) && isset($documento->ddv_detalle_devengados->vacaciones) && !empty($documento->ddv_detalle_devengados->vacaciones))
            $this->classValidaciones->checkDevengadosVacaciones($this->errors, $documento->ddv_detalle_devengados->vacaciones);

        if(isset($documento->ddv_detalle_devengados) && isset($documento->ddv_detalle_devengados->primas) && !empty($documento->ddv_detalle_devengados->primas))
            $this->classValidaciones->checkDevengadosPrimas($this->errors, $documento->ddv_detalle_devengados->primas);

        if(isset($documento->ddv_detalle_devengados) && isset($documento->ddv_detalle_devengados->cesantias) && !empty($documento->ddv_detalle_devengados->cesantias))
            $this->classValidaciones->checkDevengadosCesantias($this->errors, $documento->ddv_detalle_devengados->cesantias);

        if(isset($documento->ddv_detalle_devengados) && isset($documento->ddv_detalle_devengados->incapacidades) && !empty($documento->ddv_detalle_devengados->incapacidades))
            $documento->ddv_detalle_devengados->incapacidades = $this->classValidaciones->checkDevengadosIncapacidades($this->errors, $documento->ddv_detalle_devengados->incapacidades);

        if(isset($documento->ddv_detalle_devengados) && isset($documento->ddv_detalle_devengados->licencias) && !empty($documento->ddv_detalle_devengados->licencias))
            $this->classValidaciones->checkDevengadosLicencias($this->errors, $documento->ddv_detalle_devengados->licencias);

        if(isset($documento->ddv_detalle_devengados) && isset($documento->ddv_detalle_devengados->bonificaciones) && !empty($documento->ddv_detalle_devengados->bonificaciones))
            $this->classValidaciones->checkDevengadosBonificaciones($this->errors, $documento->ddv_detalle_devengados->bonificaciones);

        if(isset($documento->ddv_detalle_devengados) && isset($documento->ddv_detalle_devengados->auxilios) && !empty($documento->ddv_detalle_devengados->auxilios))
            $this->classValidaciones->checkDevengadosAuxilios($this->errors, $documento->ddv_detalle_devengados->auxilios);

        if(isset($documento->ddv_detalle_devengados) && isset($documento->ddv_detalle_devengados->huelgas_legales) && !empty($documento->ddv_detalle_devengados->huelgas_legales))
            $this->classValidaciones->checkDevengadosHuelgasLegales($this->errors, $documento->ddv_detalle_devengados->huelgas_legales);

        if(isset($documento->ddv_detalle_devengados) && isset($documento->ddv_detalle_devengados->otros_conceptos) && !empty($documento->ddv_detalle_devengados->otros_conceptos))
            $this->classValidaciones->checkDevengadosOtrosConceptos($this->errors, $documento->ddv_detalle_devengados->otros_conceptos);

        if(isset($documento->ddv_detalle_devengados) && isset($documento->ddv_detalle_devengados->compensaciones) && !empty($documento->ddv_detalle_devengados->compensaciones))
            $this->classValidaciones->checkDevengadosCompensaciones($this->errors, $documento->ddv_detalle_devengados->compensaciones);

        if(isset($documento->ddv_detalle_devengados) && isset($documento->ddv_detalle_devengados->bono_epctvs) && !empty($documento->ddv_detalle_devengados->bono_epctvs))
            $this->classValidaciones->checkDevengadosBonoEpctvs($this->errors, $documento->ddv_detalle_devengados->bono_epctvs);

        if(isset($documento->ddv_detalle_devengados) && isset($documento->ddv_detalle_devengados->comisiones) && !empty($documento->ddv_detalle_devengados->comisiones))
            $this->classValidaciones->checkDevengadosComisiones($this->errors, $documento->ddv_detalle_devengados->comisiones);

        if(isset($documento->ddv_detalle_devengados) && isset($documento->ddv_detalle_devengados->pagos_terceros) && !empty($documento->ddv_detalle_devengados->pagos_terceros))
            $this->classValidaciones->checkDevengadosPagosTerceros($this->errors, $documento->ddv_detalle_devengados->pagos_terceros);

        if(isset($documento->ddv_detalle_devengados) && isset($documento->ddv_detalle_devengados->anticipos) && !empty($documento->ddv_detalle_devengados->anticipos))
            $this->classValidaciones->checkDevengadosAnticipos($this->errors, $documento->ddv_detalle_devengados->anticipos);

        $reglasDetalleDevengados = [
            'dotacion'              => 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{0,6})?$/',
            'apoyo_sost'            => 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{0,6})?$/',
            'teletrabajo'           => 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{0,6})?$/',
            'bonificaciones_retiro' => 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{0,6})?$/',
            'indemnizacion'         => 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{0,6})?$/',
            'reintegro'             => 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{0,6})?$/'
        ];

        $camposValidar = [
            'dotacion'              => $documento->ddv_detalle_devengados->dotacion ?? null,
            'apoyo_sost'            => $documento->ddv_detalle_devengados->apoyo_sost ?? null,
            'teletrabajo'           => $documento->ddv_detalle_devengados->teletrabajo ?? null,
            'bonificaciones_retiro' => $documento->ddv_detalle_devengados->bonificaciones_retiro ?? null,
            'indemnizacion'         => $documento->ddv_detalle_devengados->indemnizacion ?? null,
            'reintegro'             => $documento->ddv_detalle_devengados->reintegro ?? null
        ];

        $validarDetalleDevengados = Validator::make($camposValidar, $reglasDetalleDevengados, $this->mensajeNumerosPositivos);
        if(!empty($validarDetalleDevengados->errors()->all()))
            foreach($validarDetalleDevengados->errors()->all() as $error)
                $this->errors[] = $error;
    }

    /**
     * Realiza las validaciones de información a nivel de detalle de deducciones.
     * 
     * @param \stdClass $documento Documento de nómina electrónica en procesamiento
     * @return void
     */
    private function validacionesDetalleDeducciones($documento) {
        if(isset($documento->ddd_detalle_deducciones) && !empty($documento->ddd_detalle_deducciones))
            $this->classValidaciones->checkDeduccionesComposicion($this->errors, $documento->ddd_detalle_deducciones);

        if(isset($documento->ddd_detalle_deducciones) && isset($documento->ddd_detalle_deducciones->salud) && !empty($documento->ddd_detalle_deducciones->salud))
            $this->classValidaciones->checkDeduccionesSalud($this->errors, $documento->ddd_detalle_deducciones->salud);
        else 
            $this->errors[] = 'La sección [ddd_detalle_deducciones.salud] no existe o está vacia';
        
        if(isset($documento->ddd_detalle_deducciones) && isset($documento->ddd_detalle_deducciones->fondo_pension) && !empty($documento->ddd_detalle_deducciones->fondo_pension))
            $this->classValidaciones->checkDeduccionesFondoPension($this->errors, $documento->ddd_detalle_deducciones->fondo_pension);
        else 
            $this->errors[] = 'La sección [ddd_detalle_deducciones.fondo_pension] no existe o está vacia';

        if(isset($documento->ddd_detalle_deducciones) && isset($documento->ddd_detalle_deducciones->fondo_sp) && !empty($documento->ddd_detalle_deducciones->fondo_sp))
            $this->classValidaciones->checkDeduccionesFondoSp($this->errors, $documento->ddd_detalle_deducciones->fondo_sp);
        
        if(isset($documento->ddd_detalle_deducciones) && isset($documento->ddd_detalle_deducciones->sindicatos) && !empty($documento->ddd_detalle_deducciones->sindicatos))
            $this->classValidaciones->checkDeduccionesSindicatos($this->errors, $documento->ddd_detalle_deducciones->sindicatos);
        
        if(isset($documento->ddd_detalle_deducciones) && isset($documento->ddd_detalle_deducciones->sanciones) && !empty($documento->ddd_detalle_deducciones->sanciones))
            $this->classValidaciones->checkDeduccionesSanciones($this->errors, $documento->ddd_detalle_deducciones->sanciones);
        
        if(isset($documento->ddd_detalle_deducciones) && isset($documento->ddd_detalle_deducciones->libranzas) && !empty($documento->ddd_detalle_deducciones->libranzas))
            $this->classValidaciones->checkDeduccionesLibranzas($this->errors, $documento->ddd_detalle_deducciones->libranzas);
        
        if(isset($documento->ddd_detalle_deducciones) && isset($documento->ddd_detalle_deducciones->pagos_terceros) && !empty($documento->ddd_detalle_deducciones->pagos_terceros))
            $this->classValidaciones->checkDeduccionesPagosTerceros($this->errors, $documento->ddd_detalle_deducciones->pagos_terceros);
        
        if(isset($documento->ddd_detalle_deducciones) && isset($documento->ddd_detalle_deducciones->anticipos) && !empty($documento->ddd_detalle_deducciones->anticipos))
            $this->classValidaciones->checkDeduccionesAnticipos($this->errors, $documento->ddd_detalle_deducciones->anticipos);
        
        if(isset($documento->ddd_detalle_deducciones) && isset($documento->ddd_detalle_deducciones->otras_deducciones) && !empty($documento->ddd_detalle_deducciones->otras_deducciones))
            $this->classValidaciones->checkDeduccionesOtrasDeducciones($this->errors, $documento->ddd_detalle_deducciones->otras_deducciones);

        $reglasDetalleDeducciones = [
            'pension_voluntaria'   => 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{0,6})?$/',
            'retencion_fuente'     => 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{0,6})?$/',
            'afc'                  => 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{0,6})?$/',
            'cooperativa'          => 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{0,6})?$/',
            'embargo_fiscal'       => 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{0,6})?$/',
            'plan_complementarios' => 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{0,6})?$/',
            'educacion'            => 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{0,6})?$/',
            'reintegro'            => 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{0,6})?$/',
            'deuda'                => 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{0,6})?$/'
        ];

        $camposValidar = [
            'pension_voluntaria'   => $documento->ddd_detalle_deducciones->pension_voluntaria ?? null,
            'retencion_fuente'     => $documento->ddd_detalle_deducciones->retencion_fuente ?? null,
            'afc'                  => $documento->ddd_detalle_deducciones->afc ?? null,
            'cooperativa'          => $documento->ddd_detalle_deducciones->cooperativa ?? null,
            'embargo_fiscal'       => $documento->ddd_detalle_deducciones->embargo_fiscal ?? null,
            'plan_complementarios' => $documento->ddd_detalle_deducciones->plan_complementarios ?? null,
            'educacion'            => $documento->ddd_detalle_deducciones->educacion ?? null,
            'reintegro'            => $documento->ddd_detalle_deducciones->reintegro ?? null,
            'deuda'                => $documento->ddd_detalle_deducciones->deuda ?? null
        ];

        $validarDetalleDeducciones = Validator::make($camposValidar, $reglasDetalleDeducciones, $this->mensajeNumerosPositivos);
        if(!empty($validarDetalleDeducciones->errors()->all()))
            foreach($validarDetalleDeducciones->errors()->all() as $error)
                $this->errors[] = $error;
    }


    /**
     * Realiza las validaciones de información del documento nómina eliminar.
     * 
     * @param \stdClass $documento Documento de nómina electrónica en procesamiento
     * @return void
     */
    private function validacionesDocumentoNominaEliminar($documento) {
        $this->cabecera = [
            'cdn_origen'               => isset($documento->cdn_origen) ? $documento->cdn_origen : '',
            'cdn_clasificacion'        => isset($documento->cdn_clasificacion) ? $documento->cdn_clasificacion : '',
            'cdn_lote'                 => isset($documento->cdn_lote) ? $documento->cdn_lote : '',
            'tde_codigo'               => isset($documento->tde_codigo) ? $documento->tde_codigo : '',
            'ntn_codigo'               => isset($documento->ntn_codigo) ? $documento->ntn_codigo : '',
            'emp_identificacion'       => isset($documento->emp_identificacion) ? $documento->emp_identificacion : '',
            'tra_identificacion'       => isset($documento->tra_identificacion) ? $documento->tra_identificacion : '',
            'cdn_prefijo'              => isset($documento->cdn_prefijo) ? $documento->cdn_prefijo : '',
            'cdn_consecutivo'          => isset($documento->cdn_consecutivo) ? $documento->cdn_consecutivo : '',
            'cdn_fecha_emision'        => isset($documento->cdn_fecha_emision) ? $documento->cdn_fecha_emision : '',
            'pai_codigo'               => isset($documento->pai_codigo) ? $documento->pai_codigo : '',
            'dep_codigo'               => isset($documento->dep_codigo) ? $documento->dep_codigo : '',
            'mun_codigo'               => isset($documento->mun_codigo) ? $documento->mun_codigo : '',
            'cdn_notas'                => isset($documento->cdn_notas) ? $documento->cdn_notas : '',
            'cdn_documento_predecesor' => isset($documento->cdn_documento_predecesor) ? $documento->cdn_documento_predecesor : ''
        ];

        $reglas['tde_codigo']         = 'required|string|max:5';
        $reglas['ntn_codigo']         = 'required_if:tde_codigo,103|string|max:5';
        $reglas['emp_identificacion'] = 'required|string|max:20';
        $reglas['cdn_prefijo']        = 'nullable|string|max:10';
        $reglas['cdn_consecutivo']    = 'required|numeric|min:1';
        $reglas['cdn_fecha_emision']  = 'required|date_format:Y-m-d H:i:s';
        $reglas['pai_codigo']         = 'required|string|max:10';
        $reglas['cdn_notas']          = 'nullable|array';

        $validarCabecera = Validator::make($this->cabecera, $reglas, $this->mensajeNumerosPositivos);
        if(!empty($validarCabecera->errors()->all()))
            foreach($validarCabecera->errors()->all() as $error)
                $this->errors[] = $error;

        $tdeId = $this->classValidaciones->checkTipoDocumentoElectronico($this->errors, $this->cabecera['tde_codigo']);
        $documento->tde_id = $tdeId;
        unset($documento->tde_codigo);

        if(isset($this->cabecera['ntn_codigo']) && !empty($this->cabecera['ntn_codigo'])) {
            $ntnId = $this->classValidaciones->checkNominaTipoNota($this->errors, $this->cabecera['ntn_codigo']);
            $documento->ntn_id = $ntnId;
            unset($documento->ntn_codigo);
        }

        $empId = $this->classValidaciones->checkEmpleador($this->errors, $this->cabecera['emp_identificacion'], $this->empleadores);
        $documento->emp_id = $empId;
        unset($documento->emp_identificacion);

        // Si el documento incluye la propiedad 'trabajador' para crear/actualizar el trabajador, se debe validar que las propiedades tra_identificacion del documento y del trabajador sean iguales
        if(isset($documento->trabajador) && !empty($documento->trabajador)) {
            if($documento->trabajador->tra_identificacion != $documento->tra_identificacion)
                $this->errors[] = 'La identificación de trabajador en el documento y dentro del objeto [trabajador] no son iguales';
        }

        $traId = $this->classValidaciones->checkTrabajador($this->errors, $this->cabecera['emp_identificacion'], $this->cabecera['tra_identificacion'], $this->empleadores, $this->trabajadores, (isset($documento->trabajador) && !empty($documento->trabajador) ? true : false));
        $documento->tra_id = $traId;
        unset($documento->tra_identificacion);

        $paiId = $this->classValidaciones->checkPais($this->errors, $this->cabecera['pai_codigo'], $this->paises);
        $documento->pai_id = $paiId;
        unset($documento->pai_codigo);

        $depId = $this->classValidaciones->checkDepartamento(
            $this->errors, 
            (array_key_exists($this->cabecera['pai_codigo'], $this->paises) ? $this->paises[$this->cabecera['pai_codigo']]->pai_id : 0),
            $this->cabecera['pai_codigo'],
            $this->cabecera['dep_codigo'],
            $this->departamentos
        );
        $documento->dep_id = $depId;
        unset($documento->dep_codigo);

        $keyPaiDep = sprintf("%d_%s", (array_key_exists($this->cabecera['pai_codigo'], $this->paises) ? $this->paises[$this->cabecera['pai_codigo']]->pai_id : ''), $this->cabecera['dep_codigo']);
        $munId = $this->classValidaciones->checkMunicipio(
            $this->errors, 
            (array_key_exists($this->cabecera['pai_codigo'], $this->paises) ? $this->paises[$this->cabecera['pai_codigo']]->pai_id : 0),
            $this->cabecera['pai_codigo'],
            (array_key_exists($keyPaiDep, $this->departamentos) ? $this->departamentos[$keyPaiDep]->dep_id : 0),
            $this->cabecera['dep_codigo'],
            $this->cabecera['mun_codigo'],
            $this->municipios
        );
        $documento->mun_id = $munId;
        unset($documento->mun_codigo);

        if(isset($this->cabecera['cdn_documento_predecesor']) && !empty($this->cabecera['cdn_documento_predecesor']))
            $this->classValidaciones->checkDocumentoPredecesor($this->errors, $this->cabecera['tde_codigo'], '', $this->cabecera['cdn_documento_predecesor']);
        else
            $this->errors[] = 'La propiedad Documento Predecesor [cdn_documento_predecesor] no se encontró o está vacia';
    }

    /**
     * Realiza las validaciones específicas sobre un documento de nómina electrónica
     * 
     * @param \stdClass $documento Documento de nómina electrónica en procesamiento
     */
    private function validacionesEspecificas(\stdClass &$documento) {
        $this->cabecera = [
            'cdn_origen'                   => isset($documento->cdn_origen) ? $documento->cdn_origen : '',
            'cdn_clasificacion'            => isset($documento->cdn_clasificacion) ? $documento->cdn_clasificacion : '',
            'cdn_lote'                     => isset($documento->cdn_lote) ? $documento->cdn_lote : '',
            'tde_codigo'                   => isset($documento->tde_codigo) ? $documento->tde_codigo : '',
            'ntn_codigo'                   => isset($documento->ntn_codigo) ? $documento->ntn_codigo : '',
            'cdn_aplica_novedad'           => isset($documento->cdn_aplica_novedad) ? $documento->cdn_aplica_novedad : '',
            'emp_identificacion'           => isset($documento->emp_identificacion) ? $documento->emp_identificacion : '',
            'tra_identificacion'           => isset($documento->tra_identificacion) ? $documento->tra_identificacion : '',
            'cdn_prefijo'                  => isset($documento->cdn_prefijo) ? $documento->cdn_prefijo : '',
            'cdn_consecutivo'              => isset($documento->cdn_consecutivo) ? $documento->cdn_consecutivo : '',
            'npe_codigo'                   => isset($documento->npe_codigo) ? $documento->npe_codigo : '',
            'cdn_fecha_emision'            => isset($documento->cdn_fecha_emision) ? $documento->cdn_fecha_emision : '',
            'cdn_fecha_inicio_liquidacion' => isset($documento->cdn_fecha_inicio_liquidacion) ? $documento->cdn_fecha_inicio_liquidacion : '',
            'cdn_fecha_fin_liquidacion'    => isset($documento->cdn_fecha_fin_liquidacion) ? $documento->cdn_fecha_fin_liquidacion : '',
            'cdn_tiempo_laborado'          => isset($documento->cdn_tiempo_laborado) ? $documento->cdn_tiempo_laborado : '',
            'pai_codigo'                   => isset($documento->pai_codigo) ? $documento->pai_codigo : '',
            'dep_codigo'                   => isset($documento->dep_codigo) ? $documento->dep_codigo : '',
            'mun_codigo'                   => isset($documento->mun_codigo) ? $documento->mun_codigo : '',
            'mon_codigo'                   => isset($documento->mon_codigo) ? $documento->mon_codigo : '',
            'cdn_notas'                    => isset($documento->cdn_notas) ? $documento->cdn_notas : '',
            'cdn_medios_pago'              => isset($documento->cdn_medios_pago) ? $documento->cdn_medios_pago : '',
            'cdn_fechas_pago'              => isset($documento->cdn_fechas_pago) ? $documento->cdn_fechas_pago : '',
            'cdn_documento_predecesor'     => isset($documento->cdn_documento_predecesor) ? $documento->cdn_documento_predecesor : '',
            'cdn_trm'                      => isset($documento->cdn_trm) ? $documento->cdn_trm : '',
            'cdn_redondeo'                 => isset($documento->cdn_redondeo) && !empty($documento->cdn_redondeo) ? $documento->cdn_redondeo : '0',
            'cdn_devengados'               => isset($documento->cdn_devengados) ? $documento->cdn_devengados : '',
            'cdn_deducciones'              => isset($documento->cdn_deducciones) ? $documento->cdn_deducciones : '',
            'cdn_total_comprobante'        => isset($documento->cdn_total_comprobante) ? $documento->cdn_total_comprobante : ''
        ];

        $this->validacionesCabecera($documento);
        $this->validacionesDetalleDevengados($documento);
        $this->validacionesDetalleDeducciones($documento);
    }

    /**
     * Procesa los documentos que pasaron las validaciones del sistema.
     *
     * @return void
     */
    public function guardarDocumentosNominaElectronica() {
        $agendarXmlNomina = [];
        $this->errors     = [];
        $this->procesados = [];
        foreach ($this->successful as $indice => $documentoNomina) {
            $json_original      = $documentoNomina['json_original'];
            $estInicioProceso   = date('Y-m-d H:i:s');
            $estInicioProcesoMT = microtime(true);
            $documentoNomina    = $documentoNomina['json'];
            if (isset($documentoNomina->cdn_consecutivo)) {
                $doc         = $this->docType . '-' . $documentoNomina->cdn_prefijo . $documentoNomina->cdn_consecutivo;
                $consecutivo = $documentoNomina->cdn_consecutivo;
                $prefijo     = $documentoNomina->cdn_prefijo;
            } else
                $doc = "documento[".($indice+1)."]";

            if(isset($documentoNomina->trabajador) && !empty($documentoNomina->trabajador)) {
                $procesarTrabajador = $this->classWriterInput->peticionMain((array)$documentoNomina->trabajador);

                if($procesarTrabajador['error']){
                    $respuesta = json_decode($procesarTrabajador['respuesta'], true);
                    $msjError  = array_merge((array) $respuesta['message'], (array) $respuesta['errors']);
                    $this->errors[] = [
                        'documento'           => isset($doc) ? $doc : '',
                        'consecutivo'         => isset($consecutivo) ? $consecutivo : '',
                        'prefijo'             => isset($prefijo) ? $prefijo : '',
                        'errors'              => $msjError,
                        'fecha_procesamiento' => date('Y-m-d'),
                        'hora_procesamiento'  => date('H:i:s')
                    ];

                    continue;
                } else {
                    $traId = $this->classValidaciones->checkTrabajador($this->errors, $json_original->emp_identificacion, $json_original->tra_identificacion, $this->empleadores, $this->trabajadores, false);
                    $documentoNomina->tra_id = $traId;
                }
            }
            
            $procesarCabecera = $this->classWriterInput->guardarCabecera($this->lote, $this->origen, $documentoNomina);
            if ($procesarCabecera['errors']) {
                $this->errors[] = [
                    'documento'           => isset($doc) ? $doc : '',
                    'consecutivo'         => isset($consecutivo) ? $consecutivo : '',
                    'prefijo'             => isset($prefijo) ? $prefijo : '',
                    'errors'              => $procesarCabecera['errors'],
                    'traza'               => array_key_exists('traza', $procesarCabecera) && !empty($procesarCabecera['traza']) ? $procesarCabecera['traza'] : '',
                    'fecha_procesamiento' => date('Y-m-d'),
                    'hora_procesamiento'  => date('H:i:s')
                ];
                continue;
            }

            if(isset($documentoNomina->ddv_detalle_devengados)) {
                $procesarDetalleDevengados = $this->classWriterInput->guardarDetalleDevengados($procesarCabecera['cdn_id'], $documentoNomina->ddv_detalle_devengados);

                if ($procesarDetalleDevengados['errors']) {
                    $this->errors[] = [
                        'documento'           => isset($doc) ? $doc : '',
                        'consecutivo'         => isset($consecutivo) ? $consecutivo : '',
                        'prefijo'             => isset($prefijo) ? $prefijo : '',
                        'errors'              => $procesarDetalleDevengados['errors'],
                        'traza'               => array_key_exists('traza', $procesarDetalleDevengados) && !empty($procesarDetalleDevengados['traza']) ? $procesarDetalleDevengados['traza'] : '',
                        'fecha_procesamiento' => date('Y-m-d'),
                        'hora_procesamiento'  => date('H:i:s')
                    ];

                    // Se generó error al guardar la información de detalle de devengados se debe eliminar el registro de cabecera porque el documento no fue guardado de manera completa
                    $this->classWriterInput->eliminarDsnIncompleto($procesarCabecera['cdn_id']);
                    continue;
                }
            }

            if(isset($documentoNomina->ddd_detalle_deducciones)) {
                $procesarDetalleDeducciones = $this->classWriterInput->guardarDetalleDeducciones($procesarCabecera['cdn_id'], $documentoNomina->ddd_detalle_deducciones);

                if ($procesarDetalleDeducciones['errors']) {
                    $this->errors[] = [
                        'documento'           => isset($doc) ? $doc : '',
                        'consecutivo'         => isset($consecutivo) ? $consecutivo : '',
                        'prefijo'             => isset($prefijo) ? $prefijo : '',
                        'errors'              => $procesarDetalleDeducciones['errors'],
                        'traza'               => array_key_exists('traza', $procesarDetalleDeducciones) && !empty($procesarDetalleDeducciones['traza']) ? $procesarDetalleDeducciones['traza'] : '',
                        'fecha_procesamiento' => date('Y-m-d'),
                        'hora_procesamiento'  => date('H:i:s')
                    ];

                    // Se generó error al guardar la información de detalle de devengados se debe eliminar el registro de cabecera porque el documento no fue guardado de manera completa
                    $this->classWriterInput->eliminarDsnIncompleto($procesarCabecera['cdn_id']);
                    continue;
                }
            }

            $this->procesados[] = [
                'cdn_id'                => $procesarCabecera['cdn_id'],
                'cdn_prefijo'           => isset($prefijo) ? $prefijo : '',
                'cdn_consecutivo'       => isset($consecutivo) ? $consecutivo : '',
                'tipo'                  => $this->docType,
                'fecha_procesamiento'   => date('Y-m-d'),
                'hora_procesamiento'    => date('H:i:s'),
                'json_original'         => $json_original,
                'est_inicio_proceso'    => $estInicioProceso,
                'est_inicio_proceso_mt' => $estInicioProcesoMT,
                'est_fin_proceso'       => date('Y-m-d H:i:s'),
                'est_fin_proceso_mt'    => microtime(true)
            ];
        }

        // Guarda en la tabla de procesamiento Json el log de errores
        if((!empty($this->errors) || !empty($this->failure)) && $this->origen == ConstantsDataInput::ORIGEN_API) {
            // Se unen los arrays de errores y fallidos y se inicializa vacio nuevamente los fallidos, esto es porque en errores existen índices (ej, traza) con información que no debe pasar en los fallidos
            // Adicionalmente porque la información que se encuentra registrada en fallidos también debe registrarse a nivel de base de datos
            $this->errors  = array_merge($this->errors, $this->failure);
            $this->failure = [];
            foreach ($this->errors as $errores) {
                $this->classWriterInput->crearProcesamientoJson(
                    'API-DN',
                    json_encode([]),
                    'SI',
                    json_encode([$errores]),
                    0,
                    'FINALIZADO',
                    auth()->user()->usu_id
                );

                if(is_array($errores) && array_key_exists('traza', $errores))
                    unset($errores['traza']);

                $this->failure[] = $errores;
            }
        } elseif(!empty($this->errors) && $this->origen == ConstantsDataInput::ORIGEN_MANUAL) {
            $this->failure = array_merge($this->errors, $this->failure);
        }

        // Crea el estado correspondiente para cada documento de nómina electrónica procesado correctamente y guarda cada uno de lso archivos Json en disco
        if(!empty($this->procesados)) {
            $prioridades      = [];
            $procesados       = $this->procesados;
            $this->procesados = [];
            foreach ($procesados as $procesado) {
                $documentoNomina = DsnCabeceraDocumentoNominaDaop::select(['cdn_id', 'emp_id', 'cdn_prefijo', 'cdn_consecutivo', 'cdn_procesar_documento', 'fecha_creacion'])
                    ->where('cdn_id', $procesado['cdn_id'])
                    ->with([
                        'getEmpleador:emp_id,emp_identificacion,emp_prioridad_agendamiento'
                    ])
                    ->first();

                $archivo = $this->guardarArchivoEnDisco(
                    $documentoNomina->getEmpleador->emp_identificacion,
                    $documentoNomina,
                    'nomina',
                    'json',
                    'json',
                    base64_encode(json_encode((array)$procesado['json_original']))
                );

                $this->classWriterInput->creaNuevoEstadoDocumento(
                    $procesado['cdn_id'],
                    'NDI',
                    'EXITOSO',
                    null,
                    null,
                    null,
                    json_encode(['est_json' => $archivo]),
                    null,
                    auth()->user()->usu_id,
                    $procesado['est_inicio_proceso'],
                    $procesado['est_fin_proceso'],
                    number_format(($procesado['est_fin_proceso_mt'] - $procesado['est_inicio_proceso_mt']), 3, '.', ''),
                    'FINALIZADO'
                );

                unset($procesado['json_original']);
                unset($procesado['est_inicio_proceso']);
                unset($procesado['est_fin_proceso']);
                unset($procesado['est_inicio_proceso_mt']);
                unset($procesado['est_fin_proceso_mt']);
                $this->procesados[] = $procesado;

                if($documentoNomina->cdn_procesar_documento == 'SI') {
                    $prioridades[$documentoNomina->getEmpleador->emp_identificacion] = $documentoNomina->getEmpleador->emp_prioridad_agendamiento;
                    $agendarXmlNomina[$documentoNomina->getEmpleador->emp_identificacion][] = $procesado['cdn_id'];
                }
            }
        }

        if(!empty($agendarXmlNomina))
            $this->classWriterInput->crearAgendamientoNomina('XMLNOMINA', 'XML', $agendarXmlNomina, $prioridades);
    }
}
