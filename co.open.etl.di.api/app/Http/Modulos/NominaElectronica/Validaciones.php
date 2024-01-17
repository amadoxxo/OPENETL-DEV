<?php
namespace App\Http\Modulos\NominaElectronica;


use Validator;
use App\Traits\DiTrait;
use App\Http\Modulos\NominaElectronica\DataInput;
use App\Http\Modulos\Parametros\Paises\ParametrosPais;
use App\Http\Modulos\NominaElectronica\ConstantsDataInput;
use App\Http\Modulos\Parametros\Municipios\ParametrosMunicipio;
use App\Http\Modulos\Parametros\Departamentos\ParametrosDepartamento;
use App\Http\Modulos\Configuracion\NominaElectronica\Empleadores\ConfiguracionEmpleador;
use App\Http\Modulos\Configuracion\NominaElectronica\Trabajadores\ConfiguracionTrabajador;
use App\Http\Modulos\Parametros\NominaElectronica\NominaTipoHoraExtraRecargo\ParametrosNominaTipoHoraExtraRecargo;

class Validaciones {
    use DiTrait;

    /**
     * Almacena los errores en el proceso.
     *
     * @var array
     */
    public $errors = [];

    /**
     * Almacena información de las paramétricas requeridas en el proceso de Nómina Electrónica.
     *
     * @var array
     */
    private $parametricas = [];

    /**
     * Constructor de Validaciones.
     *
     * @param  DataInput $dataInput
     * @return void
     */
    public function __construct(DataInput $dataInput) {
        $this->loadParametricas();

        $this->dataInput = $dataInput;
    }

    /**
     * Carga la información de paramétricas necesarias para el procesamiento.
     *
     * @return void
     */
    private function loadParametricas() {
        $this->parametricas = $this->parametricas([
            'tipoDocumentoElectronico',
            'nominaTipoNota',
            'nominaPeriodo',
            'mediosPago',
            'formasPago',
            'moneda',
            'tipoHoraExtraRecargo',
            'tipoIncapacidad'
        ], ConstantsDataInput::DN);
    }

    /**
     * Compara si dos cantidades flotantes son iguales o no.
     * 
     * @param float $a Valor calculado
     * @param float $b Valor contra el comparar
     * @return bool
     */
    private function compararFlotantes($a, $b, $codigoTributo = null) {
        if (is_string($a))
            $a = (float)$a;
        if (is_string($b))
            $b = (float)$b;

        return abs(($a-$b)) < 2.000000000;
    }

    /**
     * Retorna un objeto con el error.
     *
     * @param string $msg Mensaje de error
     * @return array
     */
    private function getErrorMsg(string $msg) {
        return ['error' => true, 'message' => $msg];
    }

    /**
     * Efectua la validacion sobre un objeto.
     *
     * @param array $errors Array de errores modificado por referencia para retornar los errores correspondientes
     * @param object $object objeto que sera evaluado
     * @param array $keys LLaves de los objetos a ser procesadas
     * @param string $fieldName Nombre del campo que contiene el objeto
     * @param array $rules Reglas de validacion para cada objeto que contiene el array de objetos
     * @param bool $nullable El objeto puede ser null
     * @param array $opcionales Array de propiedades opcionales dentro del objeto
     * @param bool $puedeSerVacio El objeto puede estar vacio
     * @return array
     */
    private function validateObject(array &$errors, $object, array $keys, string $fieldName, array $rules = [], $nullable = true, $opcionales = [], $puedeSerVacio = false) {
        if ($object === '' || is_null($object)) {
            if ($nullable)
                return;

            $errors[] = "El campo $fieldName debe contener un objeto valido.";
        }

        if (is_object($object)) {
            $item = (array)$object;
            $properties = array_keys($item);
            $sobra = array_diff($properties, $keys);
            $falta = array_diff($keys, $properties);
            $falta = array_diff($falta, $opcionales);

            if (!empty($sobra) || !empty($falta)) {
                $msg = "El campo $fieldName esta mal formado.";
                if (!empty($falta)) {
                    if (count($falta) === 1)
                        $msg = $msg . " Falta el campo: " . implode(',', $falta) . '.';
                    else
                        $msg = $msg . " Faltan los campos: " . implode(',', $falta) . '.';
                }

                if (!empty($sobra)) {
                    if (count($falta) === 1)
                        $msg = $msg . " Sobra el campo: " . implode(',', $sobra) . '.';
                    else
                        $msg = $msg . " Sobran los campos: " . implode(',', $sobra) . '.';
                }

                $errors[] = $msg;
            }

            if (!empty($rules)) {
                $validar = Validator::make(is_object($item) ? (array)$item : $item, $rules, $this->mensajeNumerosPositivos);
                if (!empty($validar->errors()->all())) {
                    $implode = implode(', ', $validar->errors()->all());
                    $errors[] = "El campo $fieldName tiene los siguientes errores: [$implode]";
                }
            }
        } else {
            if ((!$puedeSerVacio || !empty($object)) && !(is_array($object) && empty($object)))
                $errors[] = "El campo $fieldName debe ser un objeto";

            elseif (is_array($object) && empty($object) && !$puedeSerVacio)
                $errors[] = "El campo $fieldName esta vacio";
        }

        return;
    }

    /**
     * Efectua la validacion sobre un array de objetos.
     *
     * @param array $errors Array de errores modificado por referencia para retornar los errores correspondientes
     * @param mixed $object Array que sera evaluado
     * @param array $keys LLaves de los objetos a ser procesadas
     * @param string $fieldName Nombre del campo que contiene el objeto
     * @param array $rules Reglas de validacion para cada objeto que contiene el array de objetos
     * @param bool $nullable El array puede ser null
     * @param array $opcionales Array de propiedades que pueden ser opcionales dentro de cada objeto en el array
     * @return array
     */
    private function validateArrayObjects(array &$errors, $object, array $keys, string $fieldName, array $rules = [], $nullable = true, $opcionales = []) {
        if ($object === '' || is_null($object)) {
            if ($nullable)
                return;

            $errors[] = "El campo $fieldName debe contener un array con objetos validos.";
        }

        if (is_array($object)) {
            foreach ($object as $indice => $item) {
                $item = (array)$item;
                $properties = array_keys($item);

                // Union de las diferencias
                $sobra = array_diff($properties, $keys);
                $falta = array_diff($keys, $properties);
                $falta = array_diff($falta, $opcionales);

                if (!empty($sobra) || !empty($falta)) {
                    $msg = "El campo $fieldName posición [" . ($indice + 1) . "] esta mal formado.";
                    if (!empty($falta)) {
                        if (count($falta) === 1)
                            $msg = $msg . " Falta el campo: " . implode(',', $falta) . '.';
                        else
                            $msg = $msg . " Faltan los campos: " . implode(',', $falta) . '.';
                    }

                    if (!empty($sobra)) {
                        if (count($falta) === 1)
                            $msg = $msg . " Sobra el campo: " . implode(',', $sobra) . '.';
                        else
                            $msg = $msg . " Sobran los campos: " . implode(',', $sobra) . '.';
                    }

                    $errors[] = $msg;
                }

                if (!empty($rules)) {
                    $validar = Validator::make(is_object($item) ? (array)$item : $item, $rules, $this->mensajeNumerosPositivos);
                    if (!empty($validar->errors()->all())) {
                        $implode = implode(', ', $validar->errors()->all());
                        $errors[] = "El campo $fieldName posición [" . ($indice + 1) . "] tiene los siguientes errores: [$implode]";
                    }
                }
            }
        } else
            $errors[] = "El campo $fieldName debe ser un arreglo";

        return;
    }

    /**
     * Verifica si existe un tipo de documento electronico.
     *
     * @param array $errors Array de errores modificado por referencia para retornar los errores correspondientes
     * @param string $tde_codigo Código de Tipo de documento electronico
     * @return int|null $tde_id ID del tipo de documento electrónico
     */
    public function checkTipoDocumentoElectronico(array &$errors, string $tde_codigo) {
        $tde_id = null;
        $tde_codigo = trim($tde_codigo);
        if (!array_key_exists($tde_codigo, $this->parametricas['tipoDocumentoElectronico']))
            $errors[] = 'El tipo de documento electrónico [' . $tde_codigo . '] no existe o no esta vigente.';
        else {
            if(
                array_key_exists($tde_codigo, $this->parametricas['tipoDocumentoElectronico']) &&
                $this->dataInput->docType === ConstantsDataInput::DN && 
                !strstr($this->parametricas['tipoDocumentoElectronico'][$tde_codigo]->tde_aplica_para, ConstantsDataInput::DN)
            )
                $errors[] = 'El tipo de documento electrónico [' . $tde_codigo . '] no aplica para Documento Nómina.';
            else
                $tde_id = $this->parametricas['tipoDocumentoElectronico'][$tde_codigo]->tde_id;
        }

        return $tde_id;
    }

    /**
     * Verifica si existe el código de Nómina Tipo Nota.
     *
     * @param array $errors Array de errores modificado por referencia para retornar los errores correspondientes
     * @param string $ntn_codigo Código de Nómina Tipo Nota
     * @return int|null $ntn_id ID del tipo de nota de nómina
     */
    public function checkNominaTipoNota(array &$errors, string $ntn_codigo) {
        $ntn_id = null;
        $ntn_codigo = trim($ntn_codigo);
        if (!array_key_exists($ntn_codigo, $this->parametricas['nominaTipoNota']))
            $errors[] = 'El nómina tipo nota [' . $ntn_codigo . '] no existe o no esta vigente.';
        else
            $ntn_id = $this->parametricas['nominaTipoNota'][$ntn_codigo]->ntn_id;

        return $ntn_id;
    }

    /**
     * Verifica si existe el código de Nómina Periodo.
     *
     * @param array $errors Array de errores modificado por referencia para retornar los errores correspondientes
     * @param string $npe_codigo Código de Nómina Periodo
     * @return int|null ID de nómina periodo
     */
    public function checkNominaPeriodo(array &$errors, string $npe_codigo) {
        $npe_id = null;
        $npe_codigo = trim($npe_codigo);
        if (!array_key_exists($npe_codigo, $this->parametricas['nominaPeriodo']))
            $errors[] = 'El código de nómina periodo [' . $npe_codigo . '] no existe o no esta vigente.';
        else
            $npe_id = $this->parametricas['nominaPeriodo'][$npe_codigo]->npe_id;

        return $npe_id;
    }

    /**
     * Verifica si un empleados existe y se encuentra activo.
     *
     * @param array $errors Array de errores modificado por referencia para retornar los errores correspondientes
     * @param string $emp_identificacion Identificación del empleador
     * @param array $empleadores Array de empleadores modificado por referencia para evitar hacer reconsultas sobre la BD
     * @return int|null ID del empleador
     */
    public function checkEmpleador(array &$errors, string $emp_identificacion, array &$empleadores) {
        $user   = auth()->user();
        $emp_id = null;
        $emp_identificacion = trim($emp_identificacion);
        if (!array_key_exists($emp_identificacion, $empleadores)) {
            $empleador = ConfiguracionEmpleador::select(['emp_id', 'emp_identificacion'])
                ->where('estado', 'ACTIVO')
                ->where('emp_identificacion', $emp_identificacion);

            if (!empty($user->bdd_id_rg))
                $empleador = $empleador->where('bdd_id_rg', $user->bdd_id_rg);
            else
                $empleador = $empleador->whereNull('bdd_id_rg');

            $empleador = $empleador->first();

            if (!$empleador)
                $errors[] = 'El empleador con identificacion [' . $emp_identificacion . '] no existe o se encuentra inactivo.';
            else {
                $emp_id = $empleador->emp_id;
                $empleadores[$emp_identificacion] = $empleador;
            }
        } else {
            $emp_id = $empleadores[$emp_identificacion]->emp_id;
        }

        return $emp_id;
    }

    /**
     * Verifica si un empleado existe y se encuentra activo para el empleador correspondiente.
     *
     * @param array $errors Array de errores modificado por referencia para retornar los errores correspondientes
     * @param string $emp_identificacion Identificación del empleador
     * @param string $tra_identificacion Identificación del trabajador
     * @param array $empleadores Array de empleadores
     * @param array $trabajadores Array de trabajadores modificado por referencia para evitar hacer reconsultas sobre la BD
     * @param bool $crearEditarTrabajador Indica si dentro del procesamiento del documento se creará/editará el trabajador
     * @return int|null ID del trabajador
     */
    public function checkTrabajador(array &$errors, string $emp_identificacion, string $tra_identificacion, $empleadores, array &$trabajadores, bool $crearEditarTrabajador = false) {
        $tra_id = null;
        $tra_identificacion = trim($tra_identificacion);
        $emp_identificacion = trim($emp_identificacion);

        if (!array_key_exists($emp_identificacion, $trabajadores))
            $trabajadores[$emp_identificacion] = [];

        if (!array_key_exists($tra_identificacion, $trabajadores[$emp_identificacion])) {
            $trabajador = ConfiguracionTrabajador::select(['tra_id', 'tra_identificacion'])
                ->where('emp_id', array_key_exists($emp_identificacion, $empleadores) ? $empleadores[$emp_identificacion]->emp_id : null)
                ->where('tra_identificacion', $tra_identificacion)
                ->where('estado', 'ACTIVO')
                ->first();

            if (!$trabajador && !$crearEditarTrabajador)
                $errors[] = 'El trabajador con identificacion [' . $tra_identificacion . '] del empleador con identificacion [' . $emp_identificacion . '] no existe o se encuentra inactivo.';
            elseif($trabajador) {
                $tra_id = $trabajador->tra_id;
                $trabajadores[$emp_identificacion][$tra_identificacion] = $trabajador;
            }
        } else {
            $tra_id = $trabajadores[$emp_identificacion][$tra_identificacion]->tra_id;
        }

        return $tra_id;
    }

    /**
     * Verifica si un código de país existe y se encuentra vigente.
     *
     * @param array $errors Array de errores modificado por referencia para retornar los errores correspondientes
     * @param string $pai_codigo
     * @param array $paises Array de paises modificado por referencia para no hacer reconsultas sobre la BD
     * @return int|null $pai_id ID del país
     */
    public function checkPais(array &$errors, string $pai_codigo, array &$paises) {
        $pai_id = null;
        if (!array_key_exists($pai_codigo, $paises)) {
            $pais = ParametrosPais::select(['pai_id', 'pai_codigo', 'pai_descripcion'])
                ->where('estado', 'ACTIVO')
                ->where('pai_codigo', $pai_codigo)
                ->get()
                ->groupBy('pai_codigo')
                ->map(function ($item) {
                    $vigente = $this->validarVigenciaRegistroParametrica($item);
                    if($vigente['vigente'])
                        return $vigente['registro'];
                })->first();

            if (!$pais)
                $errors[] = 'El país con código [' . $pai_codigo . '] no existe o se encuentra inactivo.';
            else {
                $pai_id = $pais->pai_id;
                $paises[$pai_codigo] = $pais;
            }
        } else {
            $pai_id = $paises[$pai_codigo]->pai_id;
        }

        return $pai_id;
    }

    /**
     * Verifica si un código de departamento existe y se encuentra vigente.
     *
     * @param array $errors Array de errores modificado por referencia para retornar los errores correspondientes
     * @param int $pai_id Id del país
     * @param string $pai_codigo Código de País
     * @param string $dep_codigo Código de Departamento
     * @param array $departamentos Array de departamentos modificado por referencia para no hacer reconsultas sobre la BD
     * @return int|null $dep_id IO del departamento
     */
    public function checkDepartamento(array &$errors, int $pai_id, string $pai_codigo, string $dep_codigo, array &$departamentos) {
        $dep_id = null;
        $key = sprintf("%d_%s", $pai_id, $dep_codigo);
        if (!array_key_exists($key, $departamentos)) {
            $departamento = ParametrosDepartamento::select(['dep_id', 'pai_id', 'dep_codigo', 'dep_descripcion', 'fecha_vigencia_desde', 'fecha_vigencia_hasta'])
                ->where('estado', 'ACTIVO')
                ->where('pai_id', $pai_id)
                ->where('dep_codigo', $dep_codigo)
                ->get()
                ->groupBy(function($item, $key) {
                    return $item->pai_id . '~' . $item->dep_codigo;
                })
                ->map(function ($item) {
                    $vigente = $this->validarVigenciaRegistroParametrica($item);
                    if($vigente['vigente'])
                        return $vigente['registro'];
                })->first();

            if (!$departamento)
                $errors[] = 'El departamento con código [' . $dep_codigo . '] del país [' . $pai_codigo . '] no existe o se encuentra inactivo.';
            else {
                $dep_id = $departamento->dep_id;
                $departamentos[$key] = $departamento;
            }
        } else {
            $dep_id = $departamentos[$key]->dep_id;
        }

        return $dep_id;
    }

    /**
     * Verifica si un código de municipio existe y se encuentra vigente.
     *
     * @param array $errors Array de errores modificado por referencia para retornar los errores correspondientes
     * @param int $pai_id Id de país
     * @param string $pai_codigo Código de País
     * @param int $dep_id Id de departamento, puede ser -1, indicanddo que el municipio no tiene un departamento asociado
     * @param string $dep_codigo Código de Departamento
     * @param string $mun_codigo Código de Municipio
     * @param array $municipios Array de municipios modificado por referencia para no hacer reconsultas sobre la BD
     * @return void
     */
    public function checkMunicipio(array &$errors, int $pai_id, string $pai_codigo, int $dep_id, string $dep_codigo, string $mun_codigo, array &$municipios) {
        $mun_id = null;
        $key = sprintf("%d_%d_%s", $pai_id, $dep_id, $mun_codigo);
        if (!array_key_exists($key, $municipios)) {
            $municipio = ParametrosMunicipio::select(['mun_id', 'pai_id', 'dep_id', 'mun_codigo', 'mun_descripcion', 'fecha_vigencia_desde', 'fecha_vigencia_hasta'])
                ->where('estado', 'ACTIVO')
                ->where('pai_id', $pai_id)
                ->where('mun_codigo', $mun_codigo);

            if ($dep_id !== null)
                $municipio->where('dep_id', $dep_id);

            $municipio = $municipio->get()
                ->groupBy(function($item, $key) {
                    return $item->pai_id . '~' . $item->dep_id . '~' . $item->mun_codigo;
                })
                ->map(function ($item) {
                    $vigente = $this->validarVigenciaRegistroParametrica($item);
                    if($vigente['vigente'])
                        return $vigente['registro'];
                })->first();

            if (!$municipio)
                $errors[] = 'El municipio con código [' . $mun_codigo . '], departamento [' . $dep_codigo . '] del país [' . $pai_codigo . '] no existe o se encuentra inactivo.';
            else {
                $mun_id = $municipio->mun_id;
                $municipios[$key] = $municipio;
            }
        } else {
            $mun_id = $municipios[$key]->mun_id;
        }

        return $mun_id;
    }

    /**
     * Verifica el objeto cdn_medios_pago, la obligatoriedad, existencia y vigencia de las propiedades fpa_codigo y mpa_codigo.
     *
     * @param array $errors Array de errores modificado por referencia para retornar los errores correspondientes
     * @param object $mediosPago Objeto conteniendo la información de cdn_medios_pago
     * @return object $mediosPago Objecto contienendo los IDs de la forma y medio de pago
     */
    public function checkMediosPago(array &$errors, $mediosPago) {
        $this->validateObject(
            $errors,
            $mediosPago,
            ['fpa_codigo', 'mpa_codigo'],
            'cdn_medios_pago',
            [
                'fpa_codigo' => 'required|string|max:10',
                'mpa_codigo' => 'required|string|max:10',
            ],
            false
        );

        $fpa_id = null;
        if(isset($mediosPago->fpa_codigo)) {
            if (!array_key_exists($mediosPago->fpa_codigo, $this->parametricas['formasPago']))
                $errors[] = 'La forma de pago [' . $mediosPago->fpa_codigo . '] no existe o no esta vigente.';
            else {
                if(
                    array_key_exists($mediosPago->fpa_codigo, $this->parametricas['formasPago']) &&
                    $this->dataInput->docType === ConstantsDataInput::DN && 
                    !strstr($this->parametricas['formasPago'][$mediosPago->fpa_codigo]->fpa_aplica_para, ConstantsDataInput::DN)
                )
                    $errors[] = 'La forma de pago [' . $mediosPago->fpa_codigo . '] no aplica para Documento Nómina.';
                else
                    $fpa_id = $this->parametricas['formasPago'][$mediosPago->fpa_codigo]->fpa_id;
            }
        }

        $mpa_id = null;
        if(isset($mediosPago->mpa_codigo)) {
            if (!array_key_exists($mediosPago->mpa_codigo, $this->parametricas['mediosPago']))
                $errors[] = 'El medio de pago [' . $mediosPago->mpa_codigo . '] no existe o no esta vigente.';
            else
                $mpa_id = $this->parametricas['mediosPago'][$mediosPago->mpa_codigo]->mpa_id;
        }

        $mediosPago->fpa_id = $fpa_id;
        $mediosPago->mpa_id = $mpa_id;
        unset($mediosPago->fpa_codigo);
        unset($mediosPago->mpa_codigo);

        return $mediosPago;
    }

    /**
     * Verifica si una fecha es correcta en cuanto a formato y existencia.
     *
     * @param string $fecha
     * @return boolean
     */
    private function validarFecha(string $fecha) {
        try {
            $fecha = explode('-', $fecha);
            return checkdate($fecha[1] ?? 1, $fecha[2] ?? 1, $fecha[0] ?? 1);
        } catch(\Exception $e) {
            return false;
        }
    }

    /**
     * Valida el formato de las fechas de pago.
     *
     * @param array $errors Array de errores modificado por referencia para retornar los errores correspondientes
     * @param array $fechasPago Fechas de pago
     * @return void
     */
    public function checkFechasPago(array &$errors, array $fechasPago) {
        foreach ($fechasPago as $fecha) {
            if(!$this->validarFecha($fecha))
                $errors[] = 'La fecha de pago [' . $fecha . '] no es válida';
        }
    }

    /**
     * Verifica la información recibida para Documento Predecesor.
     *
     * @param array $errors Array de errores modificado por referencia para retornar los errores correspondientes
     * @param string $tdeCodigo Código del tipo de documento electrónico
     * @param string $cdnAplicaNovedad Aplica novedad
     * @param object $documentoPredecesor Objeto del documento predecesor
     * @return void
     */
    public function checkDocumentoPredecesor(array &$errors, string $tdeCodigo, string $cdnAplicaNovedad, $documentoPredecesor) {
        $reglas     = [];
        $opcionales = [];

        if($tdeCodigo == '103') {
            $reglas = [
                'cdn_prefijo'       => 'nullable|string|max:10',
                'cdn_consecutivo'   => 'required|numeric|min:1',
                'cdn_fecha_emision' => 'required|date|date_format:Y-m-d',
                'cdn_cune'          => 'required|string'
            ];

            $opcionales = ['cdn_prefijo'];
        } elseif($tdeCodigo == '102' && $cdnAplicaNovedad == 'SI') {
            $reglas = [
                'cdn_prefijo'       => 'nullable|string|max:10',
                'cdn_consecutivo'   => 'nullable|numeric|min:1',
                'cdn_fecha_emision' => 'nullable|date|date_format:Y-m-d',
                'cdn_cune'          => 'required|string'
            ];

            $opcionales = [
                'cdn_prefijo',
                'cdn_consecutivo',
                'cdn_fecha_emision'
            ];
        }

        $this->validateObject(
            $errors,
            $documentoPredecesor,
            [
                'cdn_prefijo',
                'cdn_consecutivo',
                'cdn_fecha_emision',
                'cdn_cune'
            ],
            'cdn_documento_predecesor',
            $reglas,
            false,
            $opcionales
        );
    }

    /**
     * Verifica si existe el código de moneda y está vigente.
     *
     * @param array $errors Array de errores modificado por referencia para retornar los errores correspondientes
     * @param string $mon_codigo Código de moneda
     * @return int|null $mon_id ID de la moneda
     */
    public function checkMoneda(array &$errors, string $mon_codigo) {
        $mon_id    = null;
        $mon_codigo = trim($mon_codigo);
        if (!array_key_exists($mon_codigo, $this->parametricas['moneda']))
            $errors[] = 'La moneda [' . $mon_codigo . '] no existe o no esta vigente.';
        else
            $mon_id = $this->parametricas['moneda'][$mon_codigo]->mon_id;

        return $mon_id;
    }

    /**
     * Verifica que el valor devengado corresponde con la sumatoria correspondiente a los valores reportados en el detalle de devengados.
     *
     * @param array $errors Array de errores modificado por referencia para retornar los errores correspondientes
     * @param object $documento Documento de nómina que está siendo procesado
     * @return void
     */
    public function checkValorDevengado(array &$errors, $documento) {
        $totalDevengado = 0;

        if(isset($documento->ddv_detalle_devengados)) {
            $detalleDevengados = $documento->ddv_detalle_devengados;

            if(isset($detalleDevengados->basico) && isset($detalleDevengados->basico->sueldo_trabajado) && !empty($detalleDevengados->basico->sueldo_trabajado))
                $totalDevengado += floatval($detalleDevengados->basico->sueldo_trabajado);

            if(isset($detalleDevengados->transporte) && !empty($detalleDevengados->transporte) && is_array($detalleDevengados->transporte)) {
                foreach ($detalleDevengados->transporte as $pagoTransporte) {
                    if(isset($pagoTransporte->auxilio_transporte) && !empty($pagoTransporte->auxilio_transporte)) $totalDevengado += floatval($pagoTransporte->auxilio_transporte);
                    if(isset($pagoTransporte->viatico_salarial) && !empty($pagoTransporte->viatico_salarial)) $totalDevengado += floatval($pagoTransporte->viatico_salarial);
                    if(isset($pagoTransporte->viatico_no_salarial) && !empty($pagoTransporte->viatico_no_salarial)) $totalDevengado += floatval($pagoTransporte->viatico_no_salarial);
                }
            }

            if(isset($detalleDevengados->horas_extras_recargos) && !empty($detalleDevengados->horas_extras_recargos) && is_array($detalleDevengados->horas_extras_recargos))
                foreach ($detalleDevengados->horas_extras_recargos as $horaExtra)
                    if(isset($horaExtra->pago) && !empty($horaExtra->pago)) $totalDevengado += floatval($horaExtra->pago);

            if(
                isset($detalleDevengados->vacaciones) && !empty($detalleDevengados->vacaciones) && is_object($detalleDevengados->vacaciones) &&
                isset($detalleDevengados->vacaciones->vacaciones_comunes) && !empty($detalleDevengados->vacaciones->vacaciones_comunes) && is_array($detalleDevengados->vacaciones->vacaciones_comunes)
            )
                foreach ($detalleDevengados->vacaciones->vacaciones_comunes as $vacacionComun)
                    if(isset($vacacionComun->pago) && !empty($vacacionComun->pago)) $totalDevengado += floatval($vacacionComun->pago);

            if(
                isset($detalleDevengados->vacaciones) && !empty($detalleDevengados->vacaciones) && is_object($detalleDevengados->vacaciones) &&
                isset($detalleDevengados->vacaciones->vacaciones_compensadas) && !empty($detalleDevengados->vacaciones->vacaciones_compensadas) && is_array($detalleDevengados->vacaciones->vacaciones_compensadas)
            )
                foreach ($detalleDevengados->vacaciones->vacaciones_compensadas as $vacacionCompensada)
                    if(isset($vacacionCompensada->pago) && !empty($vacacionCompensada->pago)) $totalDevengado += floatval($vacacionCompensada->pago);

            if(isset($detalleDevengados->primas) && isset($detalleDevengados->primas->pago) && !empty($detalleDevengados->primas->pago))
                $totalDevengado += floatval($detalleDevengados->primas->pago);
                
            if(isset($detalleDevengados->primas) && isset($detalleDevengados->primas->pago_no_salarial) && !empty($detalleDevengados->primas->pago_no_salarial))
                $totalDevengado += floatval($detalleDevengados->primas->pago_no_salarial);

            if(isset($detalleDevengados->cesantias) && isset($detalleDevengados->cesantias->pago) && !empty($detalleDevengados->cesantias->pago))
                $totalDevengado += floatval($detalleDevengados->cesantias->pago);

            if(isset($detalleDevengados->cesantias) && isset($detalleDevengados->cesantias->pago_intereses) && !empty($detalleDevengados->cesantias->pago_intereses))
                $totalDevengado += floatval($detalleDevengados->cesantias->pago_intereses);

            if(isset($detalleDevengados->incapacidades) && !empty($detalleDevengados->incapacidades) && is_array($detalleDevengados->incapacidades))
                foreach ($detalleDevengados->incapacidades as $incapacidad)
                    if(isset($incapacidad->pago) && !empty($incapacidad->pago)) $totalDevengado += floatval($incapacidad->pago);

            if(isset($detalleDevengados->licencias) && !empty($detalleDevengados->licencias) && is_array($detalleDevengados->licencias))
                foreach ($detalleDevengados->licencias as $licencia)
                    if(isset($licencia->pago) && !empty($licencia->pago)) $totalDevengado += floatval($licencia->pago);

            if(isset($detalleDevengados->bonificaciones) && !empty($detalleDevengados->bonificaciones) && is_array($detalleDevengados->bonificaciones))
                foreach ($detalleDevengados->bonificaciones as $bonificacion) {
                    if(isset($bonificacion->bonificacion_salarial) && !empty($bonificacion->bonificacion_salarial)) $totalDevengado += floatval($bonificacion->bonificacion_salarial);
                    if(isset($bonificacion->bonificacion_no_salarial) && !empty($bonificacion->bonificacion_no_salarial)) $totalDevengado += floatval($bonificacion->bonificacion_no_salarial);
                }

            if(isset($detalleDevengados->auxilios) && !empty($detalleDevengados->auxilios) && is_array($detalleDevengados->auxilios))
                foreach ($detalleDevengados->auxilios as $auxilio) {
                    if(isset($auxilio->auxilio_salarial) && !empty($auxilio->auxilio_salarial)) $totalDevengado += floatval($auxilio->auxilio_salarial);
                    if(isset($auxilio->auxilio_no_salarial) && !empty($auxilio->auxilio_no_salarial)) $totalDevengado += floatval($auxilio->auxilio_no_salarial);
                }

            if(isset($detalleDevengados->otros_conceptos) && !empty($detalleDevengados->otros_conceptos) && is_array($detalleDevengados->otros_conceptos))
                foreach ($detalleDevengados->otros_conceptos as $otroConcepto) {
                    if(isset($otroConcepto->concepto_salarial) && !empty($otroConcepto->concepto_salarial)) $totalDevengado += floatval($otroConcepto->concepto_salarial);
                    if(isset($otroConcepto->concepto_no_salarial) && !empty($otroConcepto->concepto_no_salarial)) $totalDevengado += floatval($otroConcepto->concepto_no_salarial);
                }

            if(isset($detalleDevengados->compensaciones) && !empty($detalleDevengados->compensaciones) && is_array($detalleDevengados->compensaciones))
                foreach ($detalleDevengados->compensaciones as $compensacion) {
                    if(isset($compensacion->compensacion_ordinaria) && !empty($compensacion->compensacion_ordinaria)) $totalDevengado += floatval($compensacion->compensacion_ordinaria);
                    if(isset($compensacion->compensacion_extraordinaria) && !empty($compensacion->compensacion_extraordinaria)) $totalDevengado += floatval($compensacion->compensacion_extraordinaria);
                }

            if(isset($detalleDevengados->bono_epctvs) && !empty($detalleDevengados->bono_epctvs) && is_array($detalleDevengados->bono_epctvs))
                foreach ($detalleDevengados->bono_epctvs as $bono) {
                    if(isset($bono->pago_salarial) && !empty($bono->pago_salarial)) $totalDevengado += floatval($bono->pago_salarial);
                    if(isset($bono->pago_no_salarial) && !empty($bono->pago_no_salarial)) $totalDevengado += floatval($bono->pago_no_salarial);
                    if(isset($bono->pago_alimentacion_salarial) && !empty($bono->pago_alimentacion_salarial)) $totalDevengado += floatval($bono->pago_alimentacion_salarial);
                    if(isset($bono->pago_alimentacion_no_salarial) && !empty($bono->pago_alimentacion_no_salarial)) $totalDevengado += floatval($bono->pago_alimentacion_no_salarial);
                }

            if(isset($detalleDevengados->comisiones) && !empty($detalleDevengados->comisiones) && is_array($detalleDevengados->comisiones))
                foreach ($detalleDevengados->comisiones as $comision)
                    if(isset($comision->comision) && !empty($comision->comision)) $totalDevengado += floatval($comision->comision);

            if(isset($detalleDevengados->pagos_terceros) && !empty($detalleDevengados->pagos_terceros) && is_array($detalleDevengados->pagos_terceros))
                foreach ($detalleDevengados->pagos_terceros as $pagoTercero)
                    if(isset($pagoTercero->pago_tercero) && !empty($pagoTercero->pago_tercero)) $totalDevengado += floatval($pagoTercero->pago_tercero);

            if(isset($detalleDevengados->anticipos) && !empty($detalleDevengados->anticipos) && is_array($detalleDevengados->anticipos))
                foreach ($detalleDevengados->anticipos as $anticipos)
                    if(isset($anticipos->anticipo) && !empty($anticipos->anticipo)) $totalDevengado += floatval($anticipos->anticipo);

            if(isset($detalleDevengados->dotacion) && !empty($detalleDevengados->dotacion))
                $totalDevengado += floatval($detalleDevengados->dotacion);

            if(isset($detalleDevengados->apoyo_sost) && !empty($detalleDevengados->apoyo_sost))
                $totalDevengado += floatval($detalleDevengados->apoyo_sost);

            if(isset($detalleDevengados->teletrabajo) && !empty($detalleDevengados->teletrabajo))
                $totalDevengado += floatval($detalleDevengados->teletrabajo);

            if(isset($detalleDevengados->bonificaciones_retiro) && !empty($detalleDevengados->bonificaciones_retiro))
                $totalDevengado += floatval($detalleDevengados->bonificaciones_retiro);

            if(isset($detalleDevengados->indemnizacion) && !empty($detalleDevengados->indemnizacion))
                $totalDevengado += floatval($detalleDevengados->indemnizacion);

            if(isset($detalleDevengados->reintegro) && !empty($detalleDevengados->reintegro))
                $totalDevengado += floatval($detalleDevengados->reintegro);
        }

        if(!$this->compararFlotantes($totalDevengado , floatval($documento->cdn_devengados)))
            $errors[] = 'El total devengado no corresponde con la sumatoria del detalle devengados';
    }

    /**
     * Verifica que el valor de las deducciones corresponde con la sumatoria correspondiente a los valores reportados en el detalle de deducciones.
     *
     * @param array $errors Array de errores modificado por referencia para retornar los errores correspondientes
     * @param object $documento Documento de nómina que está siendo procesado
     * @return void
     */
    public function checkValorDeducciones(array &$errors, $documento) {
        $totalDeducciones = 0;

        if(isset($documento->ddd_detalle_deducciones)) {
            $detalleDeducciones = $documento->ddd_detalle_deducciones;

            if(isset($detalleDeducciones->salud) && isset($detalleDeducciones->salud->deduccion) && !empty($detalleDeducciones->salud->deduccion))
                $totalDeducciones += floatval($detalleDeducciones->salud->deduccion);

            if(isset($detalleDeducciones->fondo_pension) && isset($detalleDeducciones->fondo_pension->deduccion) && !empty($detalleDeducciones->fondo_pension->deduccion))
                $totalDeducciones += floatval($detalleDeducciones->fondo_pension->deduccion);

            if(isset($detalleDeducciones->fondo_sp)){
                if(isset($detalleDeducciones->fondo_sp->deduccion_sp) && !empty($detalleDeducciones->fondo_sp->deduccion_sp)) $totalDeducciones += floatval($detalleDeducciones->fondo_sp->deduccion_sp);
                if(isset($detalleDeducciones->fondo_sp->deduccion_sub) && !empty($detalleDeducciones->fondo_sp->deduccion_sub)) $totalDeducciones += floatval($detalleDeducciones->fondo_sp->deduccion_sub);
            }

            if(isset($detalleDeducciones->sindicatos) && !empty($detalleDeducciones->sindicatos) && is_array($detalleDeducciones->sindicatos))
                foreach ($detalleDeducciones->sindicatos as $sindicato)
                    if(isset($sindicato->deduccion) && !empty($sindicato->deduccion)) $totalDeducciones += floatval($sindicato->deduccion);

            if(isset($detalleDeducciones->sanciones) && !empty($detalleDeducciones->sanciones) && is_array($detalleDeducciones->sanciones))
                foreach ($detalleDeducciones->sanciones as $sancion) {
                    if(isset($sancion->sancion_publica) && !empty($sancion->sancion_publica)) $totalDeducciones += floatval($sancion->sancion_publica);
                    if(isset($sancion->sancion_privada) && !empty($sancion->sancion_privada)) $totalDeducciones += floatval($sancion->sancion_privada);
                }
            
            if(isset($detalleDeducciones->libranzas) && !empty($detalleDeducciones->libranzas) && is_array($detalleDeducciones->libranzas))
                foreach ($detalleDeducciones->libranzas as $libranza)
                    if(isset($libranza->deduccion) && !empty($libranza->deduccion)) $totalDeducciones += floatval($libranza->deduccion);
            
            if(isset($detalleDeducciones->pagos_terceros) && !empty($detalleDeducciones->pagos_terceros) && is_array($detalleDeducciones->pagos_terceros))
                foreach ($detalleDeducciones->pagos_terceros as $pagoTercero)
                    if(isset($pagoTercero->pago_tercero) && !empty($pagoTercero->pago_tercero)) $totalDeducciones += floatval($pagoTercero->pago_tercero);
            
            if(isset($detalleDeducciones->anticipos) && !empty($detalleDeducciones->anticipos) && is_array($detalleDeducciones->anticipos))
                foreach ($detalleDeducciones->anticipos as $anticipo)
                    if(isset($anticipo->anticipo) && !empty($anticipo->anticipo)) $totalDeducciones += floatval($anticipo->anticipo);
            
            if(isset($detalleDeducciones->otras_deducciones) && !empty($detalleDeducciones->otras_deducciones) && is_array($detalleDeducciones->otras_deducciones))
                foreach ($detalleDeducciones->otras_deducciones as $otraDeduccion)
                    if(isset($otraDeduccion->otra_deduccion) && !empty($otraDeduccion->otra_deduccion)) $totalDeducciones += floatval($otraDeduccion->otra_deduccion);

            if(isset($detalleDeducciones->pension_voluntaria) && !empty($detalleDeducciones->pension_voluntaria))
                $totalDeducciones += floatval($detalleDeducciones->pension_voluntaria);

            if(isset($detalleDeducciones->retencion_fuente) && !empty($detalleDeducciones->retencion_fuente))
                $totalDeducciones += floatval($detalleDeducciones->retencion_fuente);

            if(isset($detalleDeducciones->afc) && !empty($detalleDeducciones->afc))
                $totalDeducciones += floatval($detalleDeducciones->afc);

            if(isset($detalleDeducciones->cooperativa) && !empty($detalleDeducciones->cooperativa))
                $totalDeducciones += floatval($detalleDeducciones->cooperativa);

            if(isset($detalleDeducciones->embargo_fiscal) && !empty($detalleDeducciones->embargo_fiscal))
                $totalDeducciones += floatval($detalleDeducciones->embargo_fiscal);

            if(isset($detalleDeducciones->plan_complementarios) && !empty($detalleDeducciones->plan_complementarios))
                $totalDeducciones += floatval($detalleDeducciones->plan_complementarios);

            if(isset($detalleDeducciones->educacion) && !empty($detalleDeducciones->educacion))
                $totalDeducciones += floatval($detalleDeducciones->educacion);

            if(isset($detalleDeducciones->reintegro) && !empty($detalleDeducciones->reintegro))
                $totalDeducciones += floatval($detalleDeducciones->reintegro);

            if(isset($detalleDeducciones->deuda) && !empty($detalleDeducciones->deuda))
                $totalDeducciones += floatval($detalleDeducciones->deuda);
        }

        if(!$this->compararFlotantes($totalDeducciones , floatval($documento->cdn_deducciones)))
            $errors[] = 'El total de deducciones no corresponde con la sumatoria del detalle de deducciones';
    }

    /**
     * Verifica que el total del comprobante corresponda con el cálculo del valor devengado menos las deducciones.
     *
     * @param array $errors Array de errores modificado por referencia para retornar los errores correspondientes
     * @param object $documento Documento que está siendo procesado
     * @return void
     */
    public function checkValorTotalComprobante(array &$errors, $documento) {
        $totalComprobante = floatval($documento->cdn_devengados) - floatval($documento->cdn_deducciones);

        if(!$this->compararFlotantes($totalComprobante , floatval($documento->cdn_total_comprobante)))
            $errors[] = 'El total del comprobante no corresponde con el cálculo del valor devengado menos las deducciones';
    }

    /**
     * Verifica la composición del objeto ddv_detalle_devengados.
     *
     * @param array $errors Array de errores modificado por referencia para retornar los errores correspondientes
     * @param object $devengados Objeto que contiene la información de ddv_detalle_devengados
     * @return void
     */
    public function checkDevengadosComposicion(array &$errors, $devengados) {
        $arrColumnasValidas = ['basico', 'transporte', 'horas_extras_recargos', 'vacaciones', 'primas', 'cesantias', 'incapacidades', 'licencias', 'bonificaciones', 'auxilios', 'huelgas_legales', 'otros_conceptos', 'compensaciones', 'bono_epctvs', 'comisiones', 'pagos_terceros', 'anticipos', 'dotacion', 'apoyo_sost', 'teletrabajo', 'bonificaciones_retiro', 'indemnizacion', 'reintegro'];

        foreach ($devengados as $key => $value) {
            if(!in_array($key, $arrColumnasValidas))
                $errors[] = 'La propiedad [' . $key . '] no es una propiedad válida para la información de Devengados';
        }
    }

    /**
     * Verifica el objeto ddv_detalle_devengados.basico, su composición y la obligatoriedad.
     *
     * @param array $errors Array de errores modificado por referencia para retornar los errores correspondientes
     * @param object $devengadoBasico Objeto que contiene la información de ddv_detalle_devengados.basico
     * @return void
     */
    public function checkDevengadosBasico(array &$errors, $devengadoBasico) {
        $this->validateObject(
            $errors,
            $devengadoBasico,
            ['dias_trabajados', 'sueldo_trabajado'],
            'ddv_detalle_devengados.basico',
            [
                'dias_trabajados'  => 'required|numeric|regex:/^[0-9]{1,13}(\.[0-9]{0,6})?$/',
                'sueldo_trabajado' => 'required|numeric|regex:/^[0-9]{1,13}(\.[0-9]{0,6})?$/',
            ],
            false
        );
    }

    /**
     * Verifica el objeto ddv_detalle_devengados.transporte, su composición y la obligatoriedad.
     *
     * @param array $errors Array de errores modificado por referencia para retornar los errores correspondientes
     * @param array $devengadoTransporte Array de objetos que contiene la información de ddv_detalle_devengados.transporte
     * @return void
     */
    public function checkDevengadosTransporte(array &$errors, $devengadoTransporte) {
        $this->validateArrayObjects(
            $errors,
            $devengadoTransporte,
            ['auxilio_transporte', 'viatico_salarial', 'viatico_no_salarial'],
            'ddv_detalle_devengados.transporte',
            [
                'auxilio_transporte'  => 'required|numeric|regex:/^[0-9]{1,13}(\.[0-9]{0,6})?$/',
                'viatico_salarial'    => 'required|numeric|regex:/^[0-9]{1,13}(\.[0-9]{0,6})?$/',
                'viatico_no_salarial' => 'required|numeric|regex:/^[0-9]{1,13}(\.[0-9]{0,6})?$/'
            ],
            true
        );
    }

    /**
     * Verifica el objeto ddv_detalle_devengados.horas_extras_recargos, su composición y la obligatoriedad.
     *
     * @param array $errors Array de errores modificado por referencia para retornar los errores correspondientes
     * @param array $devengadoHorasExtrasRecargos Array de objetos que contiene la información de ddv_detalle_devengados.horas_extras_recargos
     * @return void
     */
    public function checkDevengadosHorasExtrasRecargos(array &$errors, $devengadoHorasExtrasRecargos) {
        $this->validateArrayObjects(
            $errors,
            $devengadoHorasExtrasRecargos,
            ['tipo', 'fecha_inicio', 'hora_inicio', 'fecha_fin', 'hora_fin', 'cantidad', 'porcentaje', 'pago'],
            'ddv_detalle_devengados.horas_extras_recargos',
            [
                'tipo'         => 'required|string|max:6|in:HEDs,HENs,HRNs,HEDDFs,HRDDFs,HENDFs,HRNDFs',
                'fecha_inicio' => 'nullable|date|date_format:Y-m-d',
                'hora_inicio'  => 'required_with:fecha_inicio|date_format:H:i:s',
                'fecha_fin'    => 'required_with:fecha_inicio|date_format:Y-m-d|after_or_equal:fecha_inicio',
                'hora_fin'     => 'required_with:fecha_fin|date_format:H:i:s',
                'cantidad'     => 'required|numeric|min:0|regex:/^[0-9]{1,13}(\.[0-9]{0,6})?$/',
                'porcentaje'   => 'required|numeric|min:0|regex:/^[0-9]{1,3}(\.[0-9]{1,3})?$/',
                'pago'         => 'required|numeric|min:0|regex:/^[0-9]{1,13}(\.[0-9]{0,6})?$/'
            ],
            false,
            ['fecha_inicio', 'hora_inicio', 'fecha_fin', 'hora_fin']
        );

        foreach($devengadoHorasExtrasRecargos as $indice => $horaExtra) {
            $existe = ParametrosNominaTipoHoraExtraRecargo::select(['nth_id', 'nth_codigo', 'nth_descripcion', 'nth_porcentaje', 'fecha_vigencia_desde', 'fecha_vigencia_hasta'])
                ->where('estado', 'ACTIVO')
                ->orderBy('nth_codigo', 'desc')
                ->where('nth_porcentaje', $horaExtra->porcentaje)
                ->get()
                ->groupBy('nth_codigo')
                ->map(function ($item) {
                    $vigente = $this->validarVigenciaRegistroParametrica($item);
                    if($vigente['vigente'])
                        return $vigente['registro'];
                })
                ->first();

            if(!$existe)
                $errors[] = "El campo [porcentaje] en ddv_detalle_devengados.horas_extras_recargos posición [" . ($indice + 1) . "] no existe o no se encuentra vigente";
        }
    }

    /**
     * Verifica el objeto ddv_detalle_devengados.vacaciones, su composición y la obligatoriedad.
     *
     * @param array $errors Array de errores modificado por referencia para retornar los errores correspondientes
     * @param object $devengadoVacaciones Objeto que puede estar compuesto por las propiedades vaciones_comunes y/o vacaciones_compensadas, y cada una de ellas debe ser un array de objetos
     * @return void
     */
    public function checkDevengadosVacaciones(array &$errors, $devengadoVacaciones) {
        if(isset($devengadoVacaciones->vacaciones_comunes) && !empty($devengadoVacaciones->vacaciones_comunes)) {
            $this->validateArrayObjects(
                $errors,
                $devengadoVacaciones->vacaciones_comunes,
                ['fecha_inicio', 'fecha_fin', 'cantidad', 'pago'],
                'ddv_detalle_devengados.vacaciones.vacaciones_comunes',
                [
                    'fecha_inicio' => 'nullable|date|date_format:Y-m-d',
                    'fecha_fin'    => 'required_with:fecha_inicio|date_format:Y-m-d|after_or_equal:fecha_inicio',
                    'cantidad'     => 'required|numeric|regex:/^[0-9]{1,2}$/',
                    'pago'         => 'required|numeric|regex:/^[0-9]{1,13}(\.[0-9]{0,6})?$/'
                ],
                true,
                ['fecha_inicio', 'fecha_fin']
            );
        }

        if(isset($devengadoVacaciones->vacaciones_compensadas) && !empty($devengadoVacaciones->vacaciones_compensadas)) {
            $this->validateArrayObjects(
                $errors,
                $devengadoVacaciones->vacaciones_compensadas,
                ['cantidad', 'pago'],
                'ddv_detalle_devengados.vacaciones.vacaciones_compensadas',
                [
                    'cantidad'     => 'required|numeric|regex:/^[0-9]{1,2}$/',
                    'pago'         => 'required|numeric|regex:/^[0-9]{1,13}(\.[0-9]{0,6})?$/'
                ],
                true
            );
        }
    }

    /**
     * Verifica el objeto ddv_detalle_devengados.primas, su composición y la obligatoriedad.
     *
     * @param array $errors Array de errores modificado por referencia para retornar los errores correspondientes
     * @param object $devengadoPrimas Objeto que contiene la información de ddv_detalle_devengados.primas
     * @return void
     */
    public function checkDevengadosPrimas(array &$errors, $devengadoPrimas) {
        $this->validateObject(
            $errors,
            $devengadoPrimas,
            ['cantidad', 'pago', 'pago_no_salarial'],
            'ddv_detalle_devengados.primas',
            [
                'cantidad'         => 'required|numeric|regex:/^[0-9]{1,13}(\.[0-9]{0,6})?$/',
                'pago'             => 'required|numeric|regex:/^[0-9]{1,13}(\.[0-9]{0,6})?$/',
                'pago_no_salarial' => 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{0,6})?$/'
            ],
            false,
            ['pago_no_salarial']
        );
    }

    /**
     * Verifica el objeto ddv_detalle_devengados.cesantias, su composición y la obligatoriedad.
     *
     * @param array $errors Array de errores modificado por referencia para retornar los errores correspondientes
     * @param object $devengadoCesantias Objeto que contiene la información de ddv_detalle_devengados.cesantias
     * @return void
     */
    public function checkDevengadosCesantias(array &$errors, $devengadoCesantias) {
        $this->validateObject(
            $errors,
            $devengadoCesantias,
            ['porcentaje', 'pago', 'pago_intereses'],
            'ddv_detalle_devengados.cesantias',
            [
                'porcentaje'     => 'required|numeric|min:0|max:100|regex:/^[0-9]{1,3}(\.[0-9]{1,3})?$/',
                'pago'           => 'required|numeric|regex:/^[0-9]{1,13}(\.[0-9]{0,6})?$/',
                'pago_intereses' => 'required|numeric|regex:/^[0-9]{1,13}(\.[0-9]{0,6})?$/'
            ],
            false,
            ['pago_no_salarial']
        );
    }

    /**
     * Verifica el objeto ddv_detalle_devengados.incapacidades, su composición y la obligatoriedad.
     *
     * @param array $errors Array de errores modificado por referencia para retornar los errores correspondientes
     * @param array $devengadoIncapacidades Array de objetos que contiene la información de ddv_detalle_devengados.incapacidades
     * @return array $incapacidades Array en donde se reemplaza el código del tipo de incapacidad por el ID correspondiente
     */
    public function checkDevengadosIncapacidades(array &$errors, $devengadoIncapacidades) {
        $this->validateArrayObjects(
            $errors,
            $devengadoIncapacidades,
            ['tipo', 'fecha_inicio', 'fecha_fin', 'cantidad', 'pago'],
            'ddv_detalle_devengados.incapacidades',
            [
                'tipo'         => 'required|string|max:10',
                'fecha_inicio' => 'nullable|date|date_format:Y-m-d',
                'fecha_fin'    => 'required_with:fecha_inicio|date_format:Y-m-d|after_or_equal:fecha_inicio',
                'cantidad'     => 'required|numeric|min:0|regex:/^[0-9]{1,13}(\.[0-9]{0,6})?$/',
                'pago'         => 'required|numeric|min:0|regex:/^[0-9]{1,13}(\.[0-9]{0,6})?$/'
            ],
            false,
            ['fecha_inicio', 'fecha_fin']
        );

        $incapacidades = [];
        foreach ($devengadoIncapacidades as $indice => $incapacidad) {
            if (isset($incapacidad->tipo) && !array_key_exists($incapacidad->tipo, $this->parametricas['tipoIncapacidad']))
                $errors[] = "El campo [tipo] con valor [{$incapacidad->tipo}] en ddv_detalle_devengados.incapacidades posición [" . ($indice + 1) . "] no existe o no se encuentra vigente";
            else {
                $incapacidad->tipo = $this->parametricas['tipoIncapacidad'][$incapacidad->tipo]->nti_id;
            }

            $incapacidades[] = $incapacidad;
        }

        return $incapacidades;
    }

    /**
     * Verifica el objeto ddv_detalle_devengados.licencias, su composición y la obligatoriedad.
     *
     * @param array $errors Array de errores modificado por referencia para retornar los errores correspondientes
     * @param array $devengadoLicencias Array de objetos que contiene la información de ddv_detalle_devengados.licencias
     * @return void
     */
    public function checkDevengadosLicencias(array &$errors, $devengadoLicencias) {
        $this->validateArrayObjects(
            $errors,
            $devengadoLicencias,
            ['tipo', 'fecha_inicio', 'fecha_fin', 'cantidad', 'pago'],
            'ddv_detalle_devengados.licencias',
            [
                'tipo'         => 'required|string|in:LicenciaMP,LicenciaR,LicenciaNR',
                'fecha_inicio' => 'nullable|date|date_format:Y-m-d',
                'fecha_fin'    => 'required_with:fecha_inicio|date_format:Y-m-d|after_or_equal:fecha_inicio',
                'cantidad'     => 'required|numeric|min:0|regex:/^[0-9]{1,13}(\.[0-9]{0,6})?$/',
                'pago'         => 'required_unless:tipo,LicenciaNR|numeric|min:0|regex:/^[0-9]{1,13}(\.[0-9]{0,6})?$/'
            ],
            false,
            ['fecha_inicio', 'fecha_fin', 'pago']
        );

        foreach ($devengadoLicencias as $indice => $licencia) {
            if (isset($licencia->tipo) && $licencia->tipo == 'LicenciaNR' && isset($licencia->pago) && $licencia->pago > 0)
                $errors[] = "Para la licencia [{$licencia->tipo}] en ddv_detalle_devengados.licencias posición [" . ($indice + 1) . "] el valor del pago no puede ser mayor a cero";
            elseif (isset($licencia->tipo) && $licencia->tipo != 'LicenciaNR' && (!isset($licencia->pago) || (isset($licencia->pago) && $licencia->pago == 0)) )
                $errors[] = "Para la licencia [{$licencia->tipo}] en ddv_detalle_devengados.licencias posición [" . ($indice + 1) . "] el valor del pago no puede ser cero";
        }
    }

    /**
     * Verifica el objeto ddv_detalle_devengados.bonificaciones, su composición y la obligatoriedad.
     *
     * @param array $errors Array de errores modificado por referencia para retornar los errores correspondientes
     * @param array $devengadoBonificaciones Array de objetos que contiene la información de ddv_detalle_devengados.bonificaciones
     * @return void
     */
    public function checkDevengadosBonificaciones(array &$errors, $devengadoBonificaciones) {
        $this->validateArrayObjects(
            $errors,
            $devengadoBonificaciones,
            ['bonificacion_salarial', 'bonificacion_no_salarial'],
            'ddv_detalle_devengados.bonificaciones',
            [
                'bonificacion_salarial'    => 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{0,6})?$/',
                'bonificacion_no_salarial' => 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{0,6})?$/'
            ],
            true,
            ['bonificacion_salarial', 'bonificacion_no_salarial']
        );
    }

    /**
     * Verifica el objeto ddv_detalle_devengados.auxilios, su composición y la obligatoriedad.
     *
     * @param array $errors Array de errores modificado por referencia para retornar los errores correspondientes
     * @param array $devengadoAuxilios Array de objetos que contiene la información de ddv_detalle_devengados.auxilios
     * @return void
     */
    public function checkDevengadosAuxilios(array &$errors, $devengadoAuxilios) {
        $this->validateArrayObjects(
            $errors,
            $devengadoAuxilios,
            ['auxilio_salarial', 'auxilio_no_salarial'],
            'ddv_detalle_devengados.auxilios',
            [
                'auxilio_salarial'    => 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{0,6})?$/',
                'auxilio_no_salarial' => 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{0,6})?$/'
            ],
            true,
            ['auxilio_salarial', 'auxilio_no_salarial']
        );
    }

    /**
     * Verifica el objeto ddv_detalle_devengados.huelgas_legales, su composición y la obligatoriedad.
     *
     * @param array $errors Array de errores modificado por referencia para retornar los errores correspondientes
     * @param array $devengadoHuelgasLegales Array de objetos que contiene la información de ddv_detalle_devengados.huelgas_legales
     * @return void
     */
    public function checkDevengadosHuelgasLegales(array &$errors, $devengadoHuelgasLegales) {
        $this->validateArrayObjects(
            $errors,
            $devengadoHuelgasLegales,
            ['fecha_inicio', 'fecha_fin', 'cantidad'],
            'ddv_detalle_devengados.huelgas_legales',
            [
                'fecha_inicio' => 'nullable|date|date_format:Y-m-d',
                'fecha_fin'    => 'required_with:fecha_inicio|date_format:Y-m-d|after_or_equal:fecha_inicio',
                'cantidad'     => 'required|numeric|regex:/^[0-9]{1,13}(\.[0-9]{0,6})?$/'
            ],
            true,
            ['fecha_inicio', 'fecha_fin']
        );
    }

    /**
     * Verifica el objeto ddv_detalle_devengados.otros_conceptos, su composición y la obligatoriedad.
     *
     * @param array $errors Array de errores modificado por referencia para retornar los errores correspondientes
     * @param array $devengadoOtrosConceptos Array de objetos que contiene la información de ddv_detalle_devengados.otros_conceptos
     * @return void
     */
    public function checkDevengadosOtrosConceptos(array &$errors, $devengadoOtrosConceptos) {
        $this->validateArrayObjects(
            $errors,
            $devengadoOtrosConceptos,
            ['descripcion_concepto', 'concepto_salarial', 'concepto_no_salarial'],
            'ddv_detalle_devengados.otros_conceptos',
            [
                'descripcion_concepto' => 'required|string',
                'concepto_salarial'    => 'required_without:concepto_no_salarial|numeric|regex:/^[0-9]{1,13}(\.[0-9]{0,6})?$/',
                'concepto_no_salarial' => 'required_without:concepto_salarial|numeric|regex:/^[0-9]{1,13}(\.[0-9]{0,6})?$/'
            ],
            false,
            ['concepto_salarial', 'concepto_no_salarial']
        );
    }

    /**
     * Verifica el objeto ddv_detalle_devengados.compensaciones, su composición y la obligatoriedad.
     *
     * @param array $errors Array de errores modificado por referencia para retornar los errores correspondientes
     * @param array $devengadoCompensaciones Array de objetos que contiene la información de ddv_detalle_devengados.compensaciones
     * @return void
     */
    public function checkDevengadosCompensaciones(array &$errors, $devengadoCompensaciones) {
        $this->validateArrayObjects(
            $errors,
            $devengadoCompensaciones,
            ['compensacion_ordinaria', 'compensacion_extraordinaria'],
            'ddv_detalle_devengados.compensaciones',
            [
                'compensacion_ordinaria'      => 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{0,6})?$/',
                'compensacion_extraordinaria' => 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{0,6})?$/'
            ],
            true,
            ['compensacion_ordinaria', 'compensacion_extraordinaria']
        );
    }

    /**
     * Verifica el objeto ddv_detalle_devengados.bono_epctvs, su composición y la obligatoriedad.
     *
     * @param array $errors Array de errores modificado por referencia para retornar los errores correspondientes
     * @param array $devengadoEpctvs Array de objetos que contiene la información de ddv_detalle_devengados.bono_epctvs
     * @return void
     */
    public function checkDevengadosBonoEpctvs(array &$errors, $devengadoEpctvs) {
        $this->validateArrayObjects(
            $errors,
            $devengadoEpctvs,
            ['pago_salarial', 'pago_no_salarial', 'pago_alimentacion_salarial', 'pago_alimentacion_no_salarial'],
            'ddv_detalle_devengados.bono_epctvs',
            [
                'pago_salarial'                 => 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{0,6})?$/',
                'pago_no_salarial'              => 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{0,6})?$/',
                'pago_alimentacion_salarial'    => 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{0,6})?$/',
                'pago_alimentacion_no_salarial' => 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{0,6})?$/'
            ],
            true,
            ['pago_salarial', 'pago_no_salarial', 'pago_alimentacion_salarial', 'pago_alimentacion_no_salarial']
        );
    }

    /**
     * Verifica el objeto ddv_detalle_devengados.comisiones, su composición y la obligatoriedad.
     *
     * @param array $errors Array de errores modificado por referencia para retornar los errores correspondientes
     * @param array $devengadoComisiones Array de objetos que contiene la información de ddv_detalle_devengados.comisiones
     * @return void
     */
    public function checkDevengadosComisiones(array &$errors, $devengadoComisiones) {
        $this->validateArrayObjects(
            $errors,
            $devengadoComisiones,
            ['comision'],
            'ddv_detalle_devengados.comisiones',
            [
                'comision' => 'required|numeric|regex:/^[0-9]{1,13}(\.[0-9]{0,6})?$/'
            ],
            false,
            ['comision']
        );
    }

    /**
     * Verifica el objeto ddv_detalle_devengados.pagos_terceros, su composición y la obligatoriedad.
     *
     * @param array $errors Array de errores modificado por referencia para retornar los errores correspondientes
     * @param array $devengadoPagosTerceros Array de objetos que contiene la información de ddv_detalle_devengados.pagos_terceros
     * @return void
     */
    public function checkDevengadosPagosTerceros(array &$errors, $devengadoPagosTerceros) {
        $this->validateArrayObjects(
            $errors,
            $devengadoPagosTerceros,
            ['pago_tercero'],
            'ddv_detalle_devengados.pagos_terceros',
            [
                'pago_tercero' => 'required|numeric|regex:/^[0-9]{1,13}(\.[0-9]{0,6})?$/'
            ],
            false,
            ['pago_tercero']
        );
    }

    /**
     * Verifica el objeto ddv_detalle_devengados.anticipos, su composición y la obligatoriedad.
     *
     * @param array $errors Array de errores modificado por referencia para retornar los errores correspondientes
     * @param array $devengadoAnticipos Array de objetos que contiene la información de ddv_detalle_devengados.anticipos
     * @return void
     */
    public function checkDevengadosAnticipos(array &$errors, $devengadoAnticipos) {
        $this->validateArrayObjects(
            $errors,
            $devengadoAnticipos,
            ['anticipo'],
            'ddv_detalle_devengados.anticipos',
            [
                'anticipo' => 'required|numeric|regex:/^[0-9]{1,13}(\.[0-9]{0,6})?$/'
            ],
            false,
            ['anticipo']
        );
    }

    /**
     * Verifica la composición del objeto ddd_detalle_deducciones.
     *
     * @param array $errors Array de errores modificado por referencia para retornar los errores correspondientes
     * @param object $deducciones Objeto que contiene la información de ddd_detalle_deducciones
     * @return void
     */
    public function checkDeduccionesComposicion(array &$errors, $deducciones) {
        $arrColumnasValidas = ['salud', 'fondo_pension', 'fondo_sp', 'sindicatos', 'sanciones', 'libranzas', 'pagos_terceros', 'anticipos', 'otras_deducciones', 'pension_voluntaria', 'retencion_fuente', 'afc', 'cooperativa', 'embargo_fiscal', 'plan_complementarios', 'educacion', 'reintegro', 'deuda'];

        foreach ($deducciones as $key => $value) {
            if(!in_array($key, $arrColumnasValidas))
                $errors[] = 'La propiedad [' . $key . '] no es una propiedad válida para la información de Deducciones';
        }
    }

    /**
     * Verifica el objeto ddd_detalle_deducciones.salud, su composición y la obligatoriedad.
     *
     * @param array $errors Array de errores modificado por referencia para retornar los errores correspondientes
     * @param object $deduccionesSalud Array de objetos que contiene la información de ddd_detalle_deducciones.salud
     * @return void
     */
    public function checkDeduccionesSalud(array &$errors, $deduccionesSalud) {
        $this->validateObject(
            $errors,
            $deduccionesSalud,
            ['porcentaje', 'deduccion'],
            'ddd_detalle_deducciones.salud',
            [
                'porcentaje' => 'required_with:deduccion|numeric|min:0|max:100|regex:/^[0-9]{1,3}(\.[0-9]{1,3})?$/',
                'deduccion'  => 'required_with:porcentaje|numeric|regex:/^[0-9]{1,13}(\.[0-9]{0,6})?$/'
            ],
            true,
            ['porcentaje', 'deduccion'],
            true
        );
    }

    /**
     * Verifica el objeto ddd_detalle_deducciones.fondo_pension, su composición y la obligatoriedad.
     *
     * @param array $errors Array de errores modificado por referencia para retornar los errores correspondientes
     * @param object $deduccionesFondoPension Array de objetos que contiene la información de ddd_detalle_deducciones.fondo_pension
     * @return void
     */
    public function checkDeduccionesFondoPension(array &$errors, $deduccionesFondoPension) {
        $this->validateObject(
            $errors,
            $deduccionesFondoPension,
            ['porcentaje', 'deduccion'],
            'ddd_detalle_deducciones.fondo_pension',
            [
                'porcentaje' => 'required_with:deduccion|numeric|min:0|max:100|regex:/^[0-9]{1,3}(\.[0-9]{1,3})?$/',
                'deduccion'  => 'required_with:porcentaje|numeric|regex:/^[0-9]{1,13}(\.[0-9]{0,6})?$/'
            ],
            true,
            ['porcentaje', 'deduccion'],
            true
        );
    }

    /**
     * Verifica el objeto ddd_detalle_deducciones.fondo_sp, su composición y la obligatoriedad.
     *
     * @param array $errors Array de errores modificado por referencia para retornar los errores correspondientes
     * @param object $deduccionesFondoSp Array de objetos que contiene la información de ddd_detalle_deducciones.fondo_sp
     * @return void
     */
    public function checkDeduccionesFondoSp(array &$errors, $deduccionesFondoSp) {
        $this->validateObject(
            $errors,
            $deduccionesFondoSp,
            ['porcentaje', 'deduccion_sp', 'porcentaje_sub', 'deduccion_sub'],
            'ddd_detalle_deducciones.fondo_sp',
            [
                'porcentaje'     => 'required_with:deduccion_sp|numeric|min:0|max:100|regex:/^[0-9]{1,3}(\.[0-9]{1,3})?$/',
                'deduccion_sp'   => 'required_with:porcentaje|numeric|regex:/^[0-9]{1,13}(\.[0-9]{0,6})?$/',
                'porcentaje_sub' => 'required_with:deduccion_sub|numeric|min:0|max:100|regex:/^[0-9]{1,3}(\.[0-9]{1,3})?$/',
                'deduccion_sub'  => 'required_with:porcentaje_sub|numeric|regex:/^[0-9]{1,13}(\.[0-9]{0,6})?$/'
            ],
            true,
            ['porcentaje', 'deduccion_sp', 'porcentaje_sub', 'deduccion_sub'],
            true
        );
    }

    /**
     * Verifica el objeto ddv_detalle_deducciones.sindicatos, su composición y la obligatoriedad.
     *
     * @param array $errors Array de errores modificado por referencia para retornar los errores correspondientes
     * @param array $deduccionesSindicatos Array de objetos que contiene la información de ddv_detalle_deducciones.sindicatos
     * @return void
     */
    public function checkDeduccionesSindicatos(array &$errors, $deduccionesSindicatos) {
        $this->validateArrayObjects(
            $errors,
            $deduccionesSindicatos,
            ['porcentaje', 'deduccion'],
            'ddv_detalle_deducciones.sindicatos',
            [
                'porcentaje' => 'required_with:deduccion|numeric|min:0|max:100|regex:/^[0-9]{1,3}(\.[0-9]{1,3})?$/',
                'deduccion'  => 'required_with:porcentaje|numeric|regex:/^[0-9]{1,13}(\.[0-9]{0,6})?$/'
            ],
            false,
            ['porcentaje', 'deduccion']
        );
    }

    /**
     * Verifica el objeto ddv_detalle_deducciones.sanciones, su composición y la obligatoriedad.
     *
     * @param array $errors Array de errores modificado por referencia para retornar los errores correspondientes
     * @param array $deduccionesSanciones Array de objetos que contiene la información de ddv_detalle_deducciones.sanciones
     * @return void
     */
    public function checkDeduccionesSanciones(array &$errors, $deduccionesSanciones) {
        $this->validateArrayObjects(
            $errors,
            $deduccionesSanciones,
            ['sancion_publica', 'sancion_privada'],
            'ddv_detalle_deducciones.sanciones',
            [
                'sancion_publica' => 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{0,6})?$/',
                'sancion_privada' => 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{0,6})?$/'
            ],
            false,
            ['sancion_publica', 'sancion_privada']
        );
    }

    /**
     * Verifica el objeto ddv_detalle_deducciones.librazas, su composición y la obligatoriedad.
     *
     * @param array $errors Array de errores modificado por referencia para retornar los errores correspondientes
     * @param array $deduccionesLibranzas Array de objetos que contiene la información de ddv_detalle_deducciones.librazas
     * @return void
     */
    public function checkDeduccionesLibranzas(array &$errors, $deduccionesLibranzas) {
        $this->validateArrayObjects(
            $errors,
            $deduccionesLibranzas,
            ['descripcion', 'deduccion'],
            'ddv_detalle_deducciones.librazas',
            [
                'descripcion' => 'nullable|string',
                'deduccion'   => 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{0,6})?$/'
            ],
            false,
            ['descripcion', 'deduccion']
        );
    }

    /**
     * Verifica el objeto ddv_detalle_deducciones.pagos_terceros, su composición y la obligatoriedad.
     *
     * @param array $errors Array de errores modificado por referencia para retornar los errores correspondientes
     * @param array $deduccionesPagosTerceros Array de objetos que contiene la información de ddv_detalle_deducciones.pagos_terceros
     * @return void
     */
    public function checkDeduccionesPagosTerceros(array &$errors, $deduccionesPagosTerceros) {
        $this->validateArrayObjects(
            $errors,
            $deduccionesPagosTerceros,
            ['pago_tercero'],
            'ddv_detalle_deducciones.pagos_terceros',
            [
                'pago_tercero' => 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{0,6})?$/'
            ],
            false,
            ['pago_tercero']
        );
    }

    /**
     * Verifica el objeto ddv_detalle_deducciones.anticipos, su composición y la obligatoriedad.
     *
     * @param array $errors Array de errores modificado por referencia para retornar los errores correspondientes
     * @param array $deduccionesAnticipos Array de objetos que contiene la información de ddv_detalle_deducciones.anticipos
     * @return void
     */
    public function checkDeduccionesAnticipos(array &$errors, $deduccionesAnticipos) {
        $this->validateArrayObjects(
            $errors,
            $deduccionesAnticipos,
            ['anticipo'],
            'ddv_detalle_deducciones.anticipos',
            [
                'anticipo' => 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{0,6})?$/'
            ],
            false,
            ['anticipo']
        );
    }

    /**
     * Verifica el objeto ddv_detalle_deducciones.otras_deducciones, su composición y la obligatoriedad.
     *
     * @param array $errors Array de errores modificado por referencia para retornar los errores correspondientes
     * @param array $deduccionesOtrasDeducciones Array de objetos que contiene la información de ddv_detalle_deducciones.otras_deducciones
     * @return void
     */
    public function checkDeduccionesOtrasDeducciones(array &$errors, $deduccionesOtrasDeducciones) {
        $this->validateArrayObjects(
            $errors,
            $deduccionesOtrasDeducciones,
            ['otra_deduccion'],
            'ddv_detalle_deducciones.otras_deducciones',
            [
                'otra_deduccion' => 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{0,6})?$/'
            ],
            false,
            ['otra_deduccion']
        );
    }
}
