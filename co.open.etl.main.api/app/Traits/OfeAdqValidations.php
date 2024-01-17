<?php

namespace App\Traits;

use Validator;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use openEtl\Main\Traits\FechaVigenciaValidations;
use App\Http\Modulos\Parametros\Paises\ParametrosPais;
use App\Http\Modulos\Parametros\Tributos\ParametrosTributo;
use App\Http\Modulos\Parametros\Municipios\ParametrosMunicipio;
use App\Http\Modulos\Configuracion\Contactos\ConfiguracionContacto;
use App\Http\Modulos\Parametros\Departamentos\ParametrosDepartamento;
use App\Http\Modulos\Parametros\RegimenFiscal\ParametrosRegimenFiscal;
use App\Http\Modulos\Parametros\CodigosPostales\ParametrosCodigoPostal;
use App\Http\Modulos\Parametros\TiposDocumentos\ParametrosTipoDocumento;
use App\Http\Modulos\Parametros\ProcedenciaVendedor\ParametrosProcedenciaVendedor;
use App\Http\Modulos\Sistema\TiemposAceptacionTacita\SistemaTiempoAceptacionTacita;
use App\Http\Modulos\Parametros\ResponsabilidadesFiscales\ParametrosResponsabilidadFiscal;
use App\Http\Modulos\Parametros\TipoOrganizacionJuridica\ParametrosTipoOrganizacionJuridica;

/**
 * Metodos de validacion para Ofes y Adquirentes.
 *
 * Trait OfeAdqValidations
 * @package App\Traits
 */
trait OfeAdqValidations {
    use FechaVigenciaValidations;

    /**
     * Propiedad para almacenar los errores.
     * 
     * @var Array
     */
    protected $errors = [];


    /**
     * Validacion de tiempo aceptacion tacita.
     *
     * @param string $tat_codigo
     * @return int|null
     */
    protected function validarAceptacionTacita(string $tat_codigo){
        $tiempo_aceptacion_tacita = SistemaTiempoAceptacionTacita::select(['tat_id'])
            ->where('estado', 'ACTIVO')
            ->where('tat_codigo', $tat_codigo)
            ->first();
        if (!$tiempo_aceptacion_tacita) {
            $this->errors = $this->adicionarError(['No existe el tiempo de aceptación tácita ' . $tat_codigo ], $this->errors);
            return null;
        }

        return $tiempo_aceptacion_tacita->tat_id;
    }

    /**
     * Validacion de Codigo Postal.
     *
     * @param string $cpo_codigo Codigo Postal.
     * @param string $dep_codigo Codigo del Departamento.
     * @param string $mun_codigo Codigo del Municipio.
     * @param bool $proveedor Indica si se esta procesando información de un proveedor.
     * @return int|null
     */
    protected function validarCodigoPostal(string $cpo_codigo, string $dep_codigo, string $mun_codigo, bool $proveedor = false){
        $codigosPostal = [];
        // Verifica si el código postal existe en la paramétrica
        $codigo_postal = ParametrosCodigoPostal::select(['cpo_id', 'fecha_vigencia_desde', 'fecha_vigencia_hasta'])
            ->where('estado', 'ACTIVO')
            ->where('cpo_codigo', $cpo_codigo)
            ->get()
            ->groupBy('cpo_codigo')
            ->map(function ($item) use (&$codigosPostal) {
                $vigente = $this->validarVigenciaRegistroParametrica($item);
                if ($vigente['vigente']) {
                    $codigosPostal = $vigente['registro'];
                }
            });

        // Si no existe en la paramétrica se valida si corresponde a la concatenación del código del departamento y el código del municipio
        if (empty($codigosPostal)) {
            if($proveedor)
                return null;
                
            if($cpo_codigo == trim($dep_codigo) . trim($mun_codigo)) {
                return null;
            } else {
                $this->errors = $this->adicionarError(['No existe el Codigo Postal ' . $cpo_codigo], $this->errors);
                return null;
            }
        } else {
            return $codigosPostal->cpo_id;
        }
    }

    /**
     * Validación de Responsabilidades fiscales.
     *
     * @param array $ref_codigo Código de Responsabilidades fiscales.
     * @param bool $adq Indica si se esta procesando información de un Adquirente.
     * @param bool $consumidorFinal Indica si se esta procesando información de un Consumidor Final.
     * @return int|null
     */
    protected function validarResponsibilidadFiscal(array $ref_codigo, $adq = false, $consumidorFinal = false){
        $codigosResFiscal = [];
        $responsabilidades_fiscales = ParametrosResponsabilidadFiscal::select(['ref_codigo', 'fecha_vigencia_desde', 'fecha_vigencia_hasta'])
            ->where('estado', 'ACTIVO')
            ->whereIn('ref_codigo', $ref_codigo)
            ->get()
            ->groupBy('ref_codigo')
            ->map( function($item) use (&$codigosResFiscal) {
                $vigente = $this->validarVigenciaRegistroParametrica($item);
                if ($vigente['vigente']) {
                    $codigosResFiscal[] = $vigente['registro']->ref_codigo;
                }
            });

        foreach ($ref_codigo as $codigo) {
            if (!in_array($codigo, $codigosResFiscal)) {
                $this->errors = $this->adicionarError(['La Responsabilidad Fiscal con Código [' . $codigo . '], ya no Está Vigente, se encuentra INACTIVA o no Existe.'], $this->errors);
                return [];
            }
            
            if ($adq && $consumidorFinal && $codigo == 'ZZ') {
                $this->errors = $this->adicionarError(['El tipo de Responsabilidad Fiscal ZZ solo aplica para el Consumidor Final.'], $this->errors);
                return [];
            }
        }

        return $codigosResFiscal;
    }

    /**
     * Validación de Responsabilidades fiscales que aplican para DE y DS.
     *
     * @param array $ref_codigo Código de Responsabilidades fiscales.
     * @param string $tipoDocumento Tipo de Documento a validar.
     * @return array $arrErrores Array con los errores en la validación.
     */
    protected function validarResponsibilidadFiscalDocumentos(array $ref_codigo, string $tipoDocumento){
        $arrErrores = [];
        $arrTipoDocumento = ($tipoDocumento == 'DE') ? ['DE','DE,DS','DS,DE'] : ['DS','DS,DE','DE,DS'];
        $mensajeError = ($tipoDocumento == 'DE') ? 'Documento Electrónico' : 'Documento Soporte';
        ParametrosResponsabilidadFiscal::select(['ref_id', 'ref_codigo', 'ref_descripcion'])
            ->whereIn('ref_codigo', $ref_codigo)
            ->whereNotIn('ref_aplica_para', $arrTipoDocumento)
            ->where('estado', 'ACTIVO')
            ->get()
            ->map(function ($item) use (&$arrErrores, $mensajeError) {
                $this->errors  = $this->adicionarError($this->errors, ['La Responsabilidad Fiscal ['.$item->ref_codigo. ' - ' .$item->ref_descripcion.'] seleccionada no aplica para '. $mensajeError]);
                $arrErrores[] = 'La Responsabilidad Fiscal ['.$item->ref_codigo. ' - ' .$item->ref_descripcion.'] seleccionada no aplica para '. $mensajeError;
            });

        return $arrErrores;
    }

    /**
     * Validación de Tributos.
     *
     * @param array $tri_codigo Código de Tributos.
     * @return int|null
     */
    protected function validarTributo($tri_codigo){
        $codigosTributos = [];
        $tributos = ParametrosTributo::select(['tri_codigo', 'fecha_vigencia_desde', 'fecha_vigencia_hasta'])
            ->where('estado', 'ACTIVO')
            ->where('tri_aplica_persona', 'SI')
            ->whereIn('tri_codigo', $tri_codigo)
            ->get()
            ->groupBy('tri_codigo')
            ->map( function($item) use (&$codigosTributos) {
                $vigente = $this->validarVigenciaRegistroParametrica($item);
                if ($vigente['vigente']) {
                    $codigosTributos[] = $vigente['registro']->tri_codigo;
                }
            });

        foreach ($tri_codigo as $codigo) {
            if (!in_array($codigo, $codigosTributos)) {
                $this->errors = $this->adicionarError(['El Tributo con Código [' . $codigo . '], ya no Está Vigente, se encuentra INACTIVO, el Campo Aplica Persona es Diferente de SI o no Existe.'], $this->errors);
                return [];
            }
        }

        return $codigosTributos;
    }

    /**
     * Validación de Tributos que aplican para DE y DS.
     *
     * @param array $tri_codigo Código de Tributos.
     * @param string $tipoDocumento Tipo de Documento a validar.
     * @return array $arrErrores Array con los errores en la validación.
     */
    protected function validarTributoDocumentos(array $tri_codigo, string $tipoDocumento){
        $arrErrores = [];
        $arrTipoDocumento = ($tipoDocumento == 'DE') ? ['DE','DE,DS','DS,DE'] : ['DS','DS,DE','DE,DS'];
        $mensajeError = ($tipoDocumento == 'DE') ? 'Documento Electrónico' : 'Documento Soporte';
        ParametrosTributo::select(['tri_id', 'tri_codigo', 'tri_descripcion'])
            ->whereIn('tri_codigo', $tri_codigo)
            ->whereNotIn('tri_aplica_para_personas', $arrTipoDocumento)
            ->where('estado', 'ACTIVO')
            ->get()
            ->map(function ($item) use (&$arrErrores, $mensajeError) {
                $this->errors = $this->adicionarError($this->errors, ['El Tributo ['.$item->tri_codigo. ' - ' .$item->tri_descripcion.'] seleccionado no aplica para '. $mensajeError]);
                $arrErrores[] = 'El Tributo ['.$item->tri_codigo. ' - ' .$item->tri_descripcion.'] seleccionado no aplica para '. $mensajeError;
            });

        return $arrErrores;
    }

    /**
     * Validacion de Regimen Fiscal.
     *
     * @param string $rfi_codigo Código de Regimen Fiscal.
     * @return int|null
     */
    protected function validarRegimenFiscal(string $rfi_codigo, $prov = false){
        $codigosRegimenFiscal = [];
        $regimen_fiscal = ParametrosRegimenFiscal::select(['rfi_id', 'fecha_vigencia_desde', 'fecha_vigencia_hasta'])
            ->where('estado', 'ACTIVO')
            ->where('rfi_codigo', $rfi_codigo)
            ->get()
            ->groupBy('rfi_codigo')
            ->map( function($item) use (&$codigosRegimenFiscal) {
                $vigente = $this->validarVigenciaRegistroParametrica($item);
                if ($vigente['vigente']) {
                    $codigosRegimenFiscal = $vigente['registro'];
                }
            });

        if(!empty($codigosRegimenFiscal)) {
            return $codigosRegimenFiscal->rfi_id;
        } elseif (empty($codigosRegimenFiscal) && !$prov) {
            $this->errors = $this->adicionarError(['El Regimen Fiscal con Código [' . $rfi_codigo .'], ya no Está Vigente, se encuentra INACTIVO o no Existe'], $this->errors);
            return null;
        } elseif (empty($codigosRegimenFiscal) && $prov) {
            return null;
        }
    }

    /**
     * Validación de Procedencia Vendedor.
     *
     * @param string $ipv_codigo Código de Procedencia Vendedor.
     * @return int|null
     */
    protected function validarProcedenciaVendedor(string $ipv_codigo, $prov = false){
        $codigosProcedenciaVendedor = [];
        $procedencia_vendedor = ParametrosProcedenciaVendedor::select(['ipv_id', 'fecha_vigencia_desde', 'fecha_vigencia_hasta'])
            ->where('estado', 'ACTIVO')
            ->where('ipv_codigo', $ipv_codigo)
            ->get()
            ->groupBy('ipv_codigo')
            ->map( function($item) use (&$codigosProcedenciaVendedor) {
                $vigente = $this->validarVigenciaRegistroParametrica($item);
                if ($vigente['vigente']) {
                    $codigosProcedenciaVendedor = $vigente['registro'];
                }
            });

        if(!empty($codigosProcedenciaVendedor)) {
            return $codigosProcedenciaVendedor->ipv_id;
        } elseif (empty($codigosProcedenciaVendedor) && !$prov) {
            $this->errors = $this->adicionarError(['La Procedencia Vendedor con Código [' . $ipv_codigo .'], ya no Está Vigente, se encuentra INACTIVO o no Existe'], $this->errors);
            return null;
        } elseif (empty($codigosProcedenciaVendedor) && $prov) {
            return null;
        }
    }

    /**
     * Validacion para el tipo de organizacion juridica.
     *
     * @param object|array $data Datos de la parametrica, para poder realizar la validacion.
     * @param string $ofeAdq Posibles valores son Adq y Ofe para poder reorganizar las condiciones.
     * @return int|null
     */
    protected function validarTipoOrganizacionJuridica ($data, string $ofeAdq = 'adq'){
        if ($ofeAdq == 'ofe'){
            $verify_data = $data->all();
            $data = (object) $data->all();
        } else {
            $verify_data = $data->all();
        }

        $codigosTipoOrganizacion = [];
        $tipo_organizacion_juridica = ParametrosTipoOrganizacionJuridica::select(['toj_codigo', 'toj_id', 'fecha_vigencia_desde', 'fecha_vigencia_hasta'])
            ->where('estado', 'ACTIVO')
            ->where('toj_codigo', $data->toj_codigo)
            ->get()
            ->groupBy('toj_codigo')
            ->map( function($item) use (&$codigosTipoOrganizacion) {
                $vigente = $this->validarVigenciaRegistroParametrica($item);
                if ($vigente['vigente']) {
                    $codigosTipoOrganizacion = $vigente['registro'];
                }
            });

        if (!empty($codigosTipoOrganizacion)) {
            switch ($codigosTipoOrganizacion->toj_codigo) {
                case '1':
                    $data->{"{$ofeAdq}_primer_apellido"} = null;
                    $data->{"{$ofeAdq}_segundo_apellido"} = null;
                    $data->{"{$ofeAdq}_primer_nombre"} = null;
                    $data->{"{$ofeAdq}_otros_nombres"} = null;

                    $datosRules = [
                        "{$ofeAdq}_razon_social"     => 'string|required|max:255',
                        "{$ofeAdq}_nombre_comercial" => 'string|required|max:255',
                    ];
                    break;
                default:
                    $data->{"{$ofeAdq}_razon_social"} = null;
                    $data->{"{$ofeAdq}_nombre_comercial"} = null;

                    $datosRules = [
                        "{$ofeAdq}_primer_apellido"  => 'string|required|max:100',
                        "{$ofeAdq}_segundo_apellido" => 'string|nullable|max:100',
                        "{$ofeAdq}_primer_nombre"    => 'string|required|max:255',
                        "{$ofeAdq}_otros_nombres"    => 'string|nullable|max:255',
                    ];
                    break;
            }

            // Validacion de las reglas
            $rules = $datosRules;
            $validator = Validator::make($verify_data, $rules);

            if($validator->fails()) {
                $this->errors = $this->adicionarError($this->errors, $validator->errors()->all());
            }
        } else {
            $this->errors = $this->adicionarError($this->errors, ['El Tipo de Organización Juridica con Código [' . $data->toj_codigo .'], ya no Está Vigente, se encuentra INACTIVO o no Existe']);
        }

        return $codigosTipoOrganizacion && isset($codigosTipoOrganizacion->toj_id) ? $codigosTipoOrganizacion->toj_id : null;
    }

    /**
     * Validacion de Responsabilidades fiscales.
     *
     * @param array $correos_notificacion
     * @return void
     */
    protected function validarCorreosNotificacion (array $correos_notificacion){
        // Se validan cada uno de los correos adicionales
        $erroresCorreos = [];
        $reglas = ['adq_correos_notificacion' => 'email'] ;
        foreach($correos_notificacion as $correo){
            $valEmail['adq_correos_notificacion'] = $correo;                    
            $validaEmail = Validator::make($valEmail, $reglas);                    
            if ($validaEmail->fails()) {
                $erroresCorreos[] = 'El correo ['. $correo.'] no es válido';
            }
        }

        if(!empty($erroresCorreos))
            $this->errors = $this->adicionarError($this->errors, $erroresCorreos);
    }

    /**
     * Validacion de Pais.
     *
     * @param string $pai_codigo Código de Pais.
     * @param string $mensaje
     * @param bool $proveedor Indica si se esta procesando información de un proveedor.
     * @param bool $ofe Indica si se esta procesando información de un Oferente.
     * @return int|null
     */
    protected function validarPais(string $pai_codigo, string $mensaje = '', bool $proveedor = false, bool $ofe = false, string $ipv_codigo = '') {
        $codigoPaises = [];
        $pais = ParametrosPais::select(['pai_id', 'pai_codigo', 'pai_descripcion', 'fecha_vigencia_desde', 'fecha_vigencia_hasta'])
            ->where('pai_codigo', $pai_codigo)
            ->where('estado', 'ACTIVO')
            ->get()
            ->groupBy('pai_codigo')
            ->map( function($item) use (&$codigoPaises, $pai_codigo, $ipv_codigo, $mensaje) {
                $vigente = $this->validarVigenciaRegistroParametrica($item);
                if ($vigente['vigente']) {
                    $codigoPaises = $vigente['registro'];
                    if ($ipv_codigo != '' && $pai_codigo != 'CO' && $ipv_codigo == '10') {
                        $this->errors = $this->adicionarError($this->errors, [$mensaje. ' el País ['.$pai_codigo. ' - ' .$vigente['registro']->pai_descripcion.'] seleccionado no es válido para la Procedencia del Vendedor seleccionada']);
                    }
                }
            });

        if(empty($codigoPaises)) {
            if($proveedor)
                return null;

            $this->errors = $this->adicionarError($this->errors, ['El Código del País [' . $pai_codigo . '], ya no Está Vigente, se encuentra INACTIVO o no Existe. ' . $mensaje]);
            return null;
        }

        if($ofe) {
            if($codigoPaises->pai_codigo != 'CO') {
                $this->errors = $this->adicionarError($this->errors, ['Para los OFEs el País válido es [CO - COLOMBIA]' . $mensaje]);
                return null;
            }
        }

        return $codigoPaises->pai_id;
    }

    /**
     * Validacion de Departamento.
     *
     * @param string $pai_codigo Código de Pais.
     * @param string|null $dep_codigo Código de departamento.
     * @param string $mensaje
     * @param bool $proveedor Indica si se esta procesando información de un proveedor.
     * @return bool
     */
    protected function validarDepartamento($pai_codigo, $dep_codigo, $mensaje = '', $proveedor = false){
        if (empty($dep_codigo) && strtoupper($pai_codigo) !== 'C0')
            return null;

        if (empty($dep_codigo)) {
            $this->errors = $this->adicionarError($this->errors, ['No se ha proporcionado el Código del Departamento. ' . $mensaje]);
            return null;
        }

        $codigoPaises = [];
        $pais = ParametrosPais::select(['pai_id', 'pai_codigo', 'fecha_vigencia_desde', 'fecha_vigencia_hasta'])
            ->where('estado', 'ACTIVO')
            ->where('pai_codigo', $pai_codigo)
            ->get()
            ->groupBy('pai_codigo')
            ->map( function($item) use (&$codigoPaises) {
                $vigente = $this->validarVigenciaRegistroParametrica($item);
                if ($vigente['vigente']) {
                    $codigoPaises = $vigente['registro'];
                }
            });

        if(empty($codigoPaises)) {
            if($proveedor)
                return null;

            $this->errors = $this->adicionarError($this->errors, ['El Código del País [' . $pai_codigo . '], ya no Está Vigente, se encuentra INACTIVO o no Existe. ' . $mensaje]);
            return null;
        }

        $codigoDepartamentos = [];
        $departamento = ParametrosDepartamento::select(['dep_id', 'dep_codigo', 'pai_id', 'fecha_vigencia_desde', 'fecha_vigencia_hasta'])
            ->where('estado', 'ACTIVO')
            ->where('pai_id', $codigoPaises->pai_id)
            ->where('dep_codigo', $dep_codigo)
            ->get()
            ->groupBy(function($item, $key) {
                return $item->pai_id . '~' . $item->dep_codigo;
            })
            ->map( function($item) use (&$codigoDepartamentos) {
                $vigente = $this->validarVigenciaRegistroParametrica($item);
                if ($vigente['vigente']) {
                    $codigoDepartamentos = $vigente['registro'];
                }
            });

        if(empty($codigoDepartamentos)) {
            if($proveedor)
                return null;

            $this->errors = $this->adicionarError($this->errors, ['El Código Departamento [' . $dep_codigo . '], para el País [' . $pai_codigo . '], ya no Está Vigente, se encuentra INACTIVO o no Existe. ' . $mensaje]);
            return null;
        }
        return $codigoDepartamentos->dep_id;
    }

    /**
     * Validacion de Municipio.
     *
     * @param string $pai_codigo Código de país departamento.
     * @param string|null $dep_codigo Código de departamento departamento.
     * @param string $mun_codigo Código de Municipio.
     * @param string $mensaje
     * @param bool $proveedor Indica si se esta procesando información de un proveedor.
     * @return int|null
     */
    protected function validarMunicipio($pai_codigo, $dep_codigo, $mun_codigo, $mensaje = '', $proveedor = false) {
        $codigoPaises = [];
        $pais = ParametrosPais::select(['pai_id', 'pai_codigo', 'fecha_vigencia_desde', 'fecha_vigencia_hasta'])
            ->where('estado', 'ACTIVO')
            ->where('pai_codigo', $pai_codigo)
            ->get()
            ->groupBy('pai_codigo')
            ->map( function($item) use (&$codigoPaises) {
                $vigente = $this->validarVigenciaRegistroParametrica($item);
                if ($vigente['vigente']) {
                    $codigoPaises = $vigente['registro'];
                }
            });

        if(empty($codigoPaises)) {
            if($proveedor)
                return null;

            $this->errors = $this->adicionarError($this->errors, ['El Código del País [' . $pai_codigo . '], ya no Está Vigente, se encuentra INACTIVO o no Existe. ' . $mensaje]);
            return null;
        }

        if (!empty($dep_codigo)) {
            $codigoDepartamentos = [];
            $departamento = ParametrosDepartamento::select(['dep_id', 'dep_codigo', 'pai_id', 'fecha_vigencia_desde', 'fecha_vigencia_hasta'])
                ->where('estado', 'ACTIVO')
                ->where('dep_codigo', $dep_codigo)
                ->where('pai_id', $codigoPaises->pai_id)
                ->get()
                ->groupBy(function($item, $key) {
                    return $item->pai_id . '~' . $item->dep_codigo;
                })
                ->map( function($item) use (&$codigoDepartamentos) {
                    $vigente = $this->validarVigenciaRegistroParametrica($item);
                    if ($vigente['vigente']) {
                        $codigoDepartamentos = $vigente['registro'];
                    }
                });

            if(empty($codigoDepartamentos) && strtoupper($pai_codigo) === 'CO') {
                if($proveedor)
                    return null;

                $this->errors = $this->adicionarError($this->errors, ['El Código del Departamento [' . $dep_codigo . '], ya no Está Vigente, se encuentra INACTIVO o no Existe. ' . $mensaje]);
                return null;
            }
        }

        $codigoMunicipios = [];
        $municipio = ParametrosMunicipio::select(['mun_id', 'mun_codigo', 'pai_id', 'dep_id', 'fecha_vigencia_desde', 'fecha_vigencia_hasta'])
            ->where('estado', 'ACTIVO')
            ->where('mun_codigo', $mun_codigo)
            ->where('pai_id', $codigoPaises->pai_id);

        if (!empty($codigoDepartamentos))
            $municipio->where('dep_id', $codigoDepartamentos->dep_id);

        $municipio = $municipio->get()
            ->groupBy(function($item, $key) {
                return $item->pai_id . '~' . $item->dep_id . '~' . $item->mun_codigo;
            })
            ->map( function($item) use (&$codigoMunicipios) {
                $vigente = $this->validarVigenciaRegistroParametrica($item);
                if ($vigente['vigente']) {
                    $codigoMunicipios = $vigente['registro'];
                }
            });

        if(empty($codigoMunicipios)) {
            if($proveedor)
                return null;

            $this->errors = $this->adicionarError($this->errors, ['El Código del Municipio [' . $mun_codigo . '], para el País [' .
                $pai_codigo . '] y el Departamento [' . $dep_codigo . '], ya no Está Vigente, se encuentra INACTIVO o no Existe. ' . $mensaje]);
            return null;
        }

        return $codigoMunicipios->mun_id;
    }

    /**
     * Validación del tipo de documento.
     *
     * @param string $tdo_codigo Código del tipo de documento
     * @param string $aplicaPara Indica el documento para validar el aplica para
     * @param string $ipv_codigo Código de procedencia vendedor
     * @return int|null Id del Tipo de Documento
     */
    protected function validarTipoDocumento(string $tdo_codigo, string $aplicaPara, string $ipv_codigo = '') {
        $codigosTipoDocumento = [];
        $mensajeError  = ($aplicaPara == 'DS') ? 'Documento Soporte' : 'Documento Electrónico';
        $tipoDocumentos = ParametrosTipoDocumento::select(['tdo_id', 'tdo_descripcion', 'fecha_vigencia_desde', 'fecha_vigencia_hasta'])
            ->where('tdo_codigo', $tdo_codigo)
            ->where('tdo_aplica_para', 'LIKE', '%'.$aplicaPara.'%')
            ->where('estado', 'ACTIVO')
            ->get()
            ->groupBy('tdo_codigo')
            ->map( function($item) use (&$codigosTipoDocumento, $tdo_codigo, $ipv_codigo) {
                $vigente = $this->validarVigenciaRegistroParametrica($item);
                if ($vigente['vigente']) {
                    $codigosTipoDocumento = $vigente['registro']->tdo_id;
                    // Se valida el tipo de documento según la procedencia de vendedor seleccionada
                    if ($ipv_codigo != '' && $tdo_codigo != '31' && $ipv_codigo == '10') {
                        $this->errors = $this->adicionarError($this->errors, ['El Tipo de Documento ['.$tdo_codigo. ' - ' .$vigente['registro']->tdo_descripcion.'] no es válido para la Procedencia del Vendedor seleccionada']);
                        return null;
                    }
                }
            });

        if(empty($codigosTipoDocumento)) {
            $this->errors = $this->adicionarError($this->errors, ['El Código de Tipo Documento [' . $tdo_codigo . '], ya no Está Vigente, no Aplica Para '.$mensajeError.', se encuentra INACTIVO o no Existe.']);
            return null;
        }
        return $codigosTipoDocumento;
    }

    /**
     * Validacion de Contacto.
     *
     * @param array $contacto Datos de Contacto.
     * @param string $ofeAdq Posibles valores son Adq y Ofe para poder reorganizar las condiciones.
     * @return bool
     */
    protected function validarContacto(array $contacto, string $ofeAdq = 'adq') {
        $rules = ConfiguracionContacto::$rules;

        if ($ofeAdq == 'ofe'){
            $rules['con_tipo'] = $rules['con_tipo'] . '|in:DespatchContact,AccountingContact,SellerContact';
        } else {
            $rules['con_tipo'] = $rules['con_tipo'] . '|in:DeliveryContact,AccountingContact,BuyerContact';
        }

        $validador = Validator::make($contacto, $rules);

        if ($validador->fails()){
            $this->errors = $this->adicionarError($this->errors, ["Error al Crear el Contacto [".Arr::first($contacto)."] los errores son:\n". implode("\n", $validador->errors()->all() 
            )]);
        }

        return $validador->fails();
    }

    /**
     * Valida la composición de campos y obligatoriedad de los mismos para el domicilio fiscal
     *
     * @param Illuminate\Http\Request $request Peticion de procesamiento
     * @param string $ofeAdq Indicador de procesamiento para ofe o adq
     * @param string $tipoDireccion Indica si se trata de la dirección de correspondencia o dirección fiscal
     * @return void
     */
    protected function validacionGeneralDomicilio(Request $request, string $ofeAdq, string $tipoDireccion = 'correspondencia') {
        $sufijo = '';
        if($tipoDireccion == 'fiscal')
            $sufijo = '_domicilio_fiscal';

        $campoPais         = 'pai_codigo' . $sufijo;
        $campoDepartamento = 'dep_codigo' . $sufijo;
        $campoMunicipio    = 'mun_codigo' . $sufijo;
        $campoCodigoPostal = 'cpo_codigo' . $sufijo;
        $campoDireccion    = $ofeAdq . '_direccion' . $sufijo;
        
        // Aplica si no es adquirente y no es proveedor o si es adquirente pero el OFE es diferente de DHL Express
        // if(($ofeAdq != 'adq' && $ofeAdq != 'pro') || ($ofeAdq == 'adq' && $request->ofe_identificacion != '860502609' && $request->ofe_identificacion != '830076778')) {
        if (($ofeAdq != 'adq' && $ofeAdq != 'pro')) {
            // Si llega el pais, dirección, ciudad o direccion fiscal, el país y dirección del apartado del domicilio en procesamiento son obligatorios
            if (
                ($request->has($campoPais) && $request->$campoPais != '') ||
                ($request->has($campoDepartamento) && $request->$campoDepartamento != '') ||
                ($request->has($campoMunicipio) && $request->$campoMunicipio != '') ||
                ($request->has($campoDireccion) && $request->$campoDireccion != '') ||
                ($request->has($campoCodigoPostal) && $request->$campoCodigoPostal != '')
            ) {
                $seccionCompleta = true;
                if (!$request->has($campoPais) || ($request->has($campoPais) && $request->$campoPais == '')) {
                    $seccionCompleta = false;
                }

                if (!$request->has($campoDireccion) || ($request->has($campoDireccion) && $request->$campoDireccion == '')) {
                    $seccionCompleta = false;
                }

                if (!$seccionCompleta) {
                    $this->errors = $this->adicionarError(['Los campos país y dirección son obligatorios para ' . ($tipoDireccion == 'correspondencia' ? 'la dirección de correspondencia '  : 'el domicilio fiscal ')], $this->errors);
                }
            }
        }
        // Aplica cuando es adquirente y es DHL Express
        /* } elseif($ofeAdq == 'adq' && ($request->ofe_identificacion == '860502609' || $request->ofe_identificacion == '830076778')) {
            //En el Domicilio Fiscala los campos de País, Departamento ,Municipio y Dirección son obligatorios cuando el tipo de documento es 31 ​(NIT)
            if($request->tdo_codigo == '31' && $tipoDireccion == 'fiscal') {
                if(
                    (!$request->has($campoPais) || ($request->has($campoPais) && $request->$campoPais == '')) ||
                    (!$request->has($campoDepartamento) || ($request->has($campoDepartamento) && $request->$campoDepartamento == '')) ||
                    (!$request->has($campoMunicipio) || ($request->has($campoMunicipio) && $request->$campoMunicipio == '')) ||
                    (!$request->has($campoDireccion) || ($request->has($campoDireccion) && $request->$campoDireccion == ''))
                ) {
                    $this->errors = $this->adicionarError(['Los campos país, departamento, municipio y dirección son obligatorios para ' . ($tipoDireccion == 'correspondencia' ? 'la dirección de correspondencia '  : 'el domicilio fiscal ')], $this->errors);
                }
            }
            //Es obligatorio para todos los tipos de documento
            if(
                $tipoDireccion == 'correspondencia' && (
                    (!$request->has($campoPais) || ($request->has($campoPais) && $request->$campoPais == '')) ||
                    (!$request->has($campoDepartamento) || ($request->has($campoDepartamento) && $request->$campoDepartamento == '')) ||
                    (!$request->has($campoMunicipio) || ($request->has($campoMunicipio) && $request->$campoMunicipio == '')) ||
                    (!$request->has($campoDireccion) || ($request->has($campoDireccion) && $request->$campoDireccion == ''))
                )
            ) {
                $this->errors = $this->adicionarError(['Los campos país, departamento, municipio y dirección son obligatorios para la dirección de correspondencia '], $this->errors);
            }
        } */
    }
}

