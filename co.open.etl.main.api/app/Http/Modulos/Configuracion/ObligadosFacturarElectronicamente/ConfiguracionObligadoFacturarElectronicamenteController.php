<?php

namespace App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente;

use Validator;
use App\Http\Models\User;
use App\Traits\MainTrait;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Traits\insertTributos;
use App\Traits\insertContactos;
use Illuminate\Validation\Rule;
use App\Traits\OfeAdqValidations;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\OpenTenantController;
use App\Http\Modulos\Sistema\Roles\SistemaRol;
use App\Http\Modulos\Commons\CommonsController;
use App\Http\Modulos\Parametros\Paises\ParametrosPais;
use App\Console\Commands\ProcesarCargaParametricaCommand;
use App\Http\Modulos\Sistema\Agendamiento\AdoAgendamiento;
use App\Http\Modulos\Parametros\Tributos\ParametrosTributo;
use App\Http\Modulos\Sistema\RolesUsuario\SistemaRolesUsuario;
use App\Http\Modulos\Parametros\Municipios\ParametrosMunicipio;
use App\Http\Modulos\Configuracion\Contactos\ConfiguracionContacto;
use App\Http\Modulos\Parametros\Departamentos\ParametrosDepartamento;
use App\Http\Modulos\Parametros\RegimenFiscal\ParametrosRegimenFiscal;
use App\Http\Modulos\Configuracion\Adquirentes\ConfiguracionAdquirente;
use App\Http\Modulos\Parametros\CodigosPostales\ParametrosCodigoPostal;
use App\Http\Modulos\Sistema\EtlProcesamientoJson\EtlProcesamientoJson;
use App\Http\Modulos\Parametros\TiposDocumentos\ParametrosTipoDocumento;
use App\Http\Modulos\Configuracion\Adquirentes\ConfiguracionAdquirenteController;
use App\Http\Modulos\Sistema\TiemposAceptacionTacita\SistemaTiempoAceptacionTacita;
use App\Http\Modulos\Parametros\ResponsabilidadesFiscales\ParametrosResponsabilidadFiscal;
use App\Http\Modulos\Parametros\TipoOrganizacionJuridica\ParametrosTipoOrganizacionJuridica;
use App\Http\Modulos\Configuracion\ResolucionesFacturacion\ConfiguracionResolucionesFacturacion;
use App\Http\Modulos\ProyectosEspeciales\Emision\DHLExpress\DocumentosCCO\PryDatoComunPickupCash;
use App\Http\Modulos\Configuracion\ObservacionesGeneralesFactura\ConfiguracionObservacionesGeneralesFactura;
use App\Http\Modulos\Configuracion\SoftwareProveedoresTecnologicos\ConfiguracionSoftwareProveedorTecnologico;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

class ConfiguracionObligadoFacturarElectronicamenteController extends OpenTenantController {
    use OfeAdqValidations, insertTributos, insertContactos;

    /**
     * Nombre del modelo en singular.
     *
     * @var String
     */
    public $nombre = 'Oferente';

    /**
     * Nombre del modelo en plural.
     *
     * @var String
     */
    public $nombrePlural = 'Oferentes';

    /**
     * Modelo relacionado a la paramétrica.
     *
     * @var Illuminate\Database\Eloquent\Model
     */
    public $className = ConfiguracionObligadoFacturarElectronicamente::class;

    /**
     * Mensaje de error para status code 422 al crear.
     *
     * @var String
     */
    public $mensajeErrorCreacion422 = 'No Fue Posible Crear el Oferente';

    /**
     * Mensaje de errores al momento de crear.
     *
     * @var String
     */
    public $mensajeErroresCreacion = 'Errores al Crear el Oferente';

    /**
     * Mensaje de error para status code 422 al actualizar.
     *
     * @var String
     */
    public $mensajeErrorModificacion422 = 'Errores al Actualizar el Oferente';

    /**
     * Mensaje de errores al momento de crear.
     *
     * @var String
     */
    public $mensajeErroresModificacion = 'Errores al Actualizar el Oferente';

    /**
     * Mensaje de error cuando el objeto no existe.
     *
     * @var String
     */
    public $mensajeObjectNotFound = 'El Id del Oferente [%s] no Existe';

    /**
     * Mensaje de error cuando el objeto no existe.
     *
     * @var String
     */
    public $mensajeObjectDisabled = 'El Id del Oferente [%s] Esta Inactivo';

    /**
     * Propiedad para almacenar los errores.
     *
     * @var Array
     */
    protected $errors = [];

    /**
     * Propiedad para saber si se valido correctamente tiempo aceptacion tacita.
     *
     * @var Bool
     */
    private $tiempoAceptacionTacita = false;

    /**
     * Propiedad para saber si se valido correctamente el regimen fiscal.
     *
     * @var Bool
     */
    private $regimenFiscal = false;

    /**
     * Propiedad para saber si se valido correctamente la responsabilidad fiscal.
     *
     * @var Bool
     */
    private $responsabilidadFiscal = false;

    /**
     * Propiedad para saber si se valido correctamente el codigo postal
     *
     * @var Bool
     */
    private $codigoPostal = false;

    /**
     * Propiedad Contiene las datos del usuario autenticado.
     *
     * @var Object
     */
    protected $user;

    /**
     * Nombre del dato para cambiar estado.
     *
     * @var String
     */
    public $nombreDatoCambiarEstado = 'identificaciones';

    /**
     * Nombre del campo de la identificación.
     *
     * @var String
     */
    public $nombreCampoIdentificacion = 'ofe_identificacion';

    /**
     * Extensiones permitidas para el cargue de registros.
     * 
     * @var Array
     */
    public $arrExtensionesPermitidas = ['xlsx', 'xls'];

    /**
     * Almacena las columnas que se generan en la interfaz de Excel.
     * 
     * @var Array
     */
    public $columnasExcel = [
        'NIT OFE',
        'RAZON SOCIAL',
        'NOMBRE COMERCIAL',
        'PRIMER NOMBRE',
        'PRIMER APELLIDO',
        'SEGUNDO APELLIDO',
        'OTROS NOMBRES',
        'CODIGO TIPO DOCUMENTO',
        'TIPO DOCUMENTO',
        'CODIGO TIPO ORGANIZACION JURIDICA',
        'TIPO ORGANIZACION JURIDICA',
        'CODIGO PAIS',
        'PAIS',
        'CODIGO DEPARTAMENTO',
        'DEPARTAMENTO',
        'CODIGO MUNICIPIO',
        'MUNICIPIO',
        'CODIGO POSTAL',
        'DIRECCION',
        'DIRECCIONES ADICIONALES',
        'CODIGO PAIS DOMICILIO FISCAL',
        'PAIS DOMICILIO FISCAL',
        'CODIGO DEPARTAMENTO DOMICILIO FISCAL',
        'DEPARTAMENTO DOMICILIO FISCAL',
        'CODIGO MUNICIPIO DOMICILIO FISCAL',
        'MUNICIPIO DOMICILIO FISCAL',
        'CODIGO POSTAL DOMICILIO FISCAL',
        'DIRECCION DOMICILIO FISCAL',
        'NOMBRE CONTACTO',
        'TELEFONO',
        'FAX',
        'CORREO',
        'NOTAS',
        'WEB',
        'TWITTER',
        'FACEBOOK',
        'CODIGO REGIMEN FISCAL',
        'REGIMEN FISCAL',
        'CODIGO RESPONSABILIDADES FISCALES',
        'RESPONSABLE TRIBUTOS',
        'MATRICULA MERCANTIL',
        'ACTIVIDAD ECONOMICA',
        'SOFTWARE PROVEEDOR TECNOLOGICO EMISION',
        'SOFTWARE PROVEEDOR TECNOLOGICO DOCUMENTO SOPORTE',
        'CORREOS NOTIFICACION',
        'NOTIFICACION UN SOLO CORREO',
        'CORREOS AUTORESPUESTA'
    ];

    /**
     * Constructor
     */
    public function __construct() {
        $this->middleware(['jwt.auth', 'jwt.refresh']);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionOFE,ConfiguracionOFENuevo,ConfiguracionOFEEditar,ConfiguracionOFEVer,ConfiguracionOFECambiarEstado,ConfiguracionOFESubir,ConfigurarDocumentoElectronico,ConfigurarDocumentoSoporte,ConfigurarServicios,ValoresDefectoDocumento'
        ])->except([
            'index',
            'show',
            'store',
            'update',
            'cambiarEstado',
            'buscarOfeNgSelect',
            'busqueda',
            'crearAdquirenteConsumidorFinal',
            'configuracionDocumentoElectronico',
            'datosFacturacionWeb',
            'configuracionDocumentoSoporte',
            'configuracionServicios',
            'generarInterfaceOfe',
            'cargarOferentes',
            'listaResolucionesFacturacion'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionOFENuevo'
        ])->only([
            'store'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionOFEEditar'
        ])->only([
            'update'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionOFEVer'
        ])->only([
            'busqueda'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionOFEVer,ConfiguracionOFEEditar,ConfigurarDocumentoElectronico,ConfigurarDocumentoSoporte,ConfigurarServicios,ValoresDefectoDocumento'
        ])->only([
            'show'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionOFECambiarEstado'
        ])->only([
            'cambiarEstado'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionOFESubir'
        ])->only([
            'generarInterfaceOfe',
            'cargarOferentes',
            'getListaErroresOfe',
            'descargarListaErroresOfe'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfigurarDocumentoElectronico'
        ])->only([
            'configuracionDocumentoElectronico',
            'datosFacturacionWeb'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfigurarDocumentoSoporte'
        ])->only([
            'configuracionDocumentoSoporte'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfigurarServicios'
        ])->only([
            'configuracionServicios'
        ]);
    }

    /**
     * Trae los detalles de todos los software.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function index() {
        $user = auth()->user();

        $ofes = $this->className::with('getUsuarioCreacion');

        if(!empty($user->bdd_id_rg)) {
            $ofes = $ofes->where('bdd_id_rg', $user->bdd_id_rg);
        } else {
            $ofes = $ofes->whereNull('bdd_id_rg');
        }

        $ofes = $ofes->where('estado', 'ACTIVO')
            ->orderBy('ofe_razon_social', 'asc')
            ->get()
            ->map(function($ofes) {
                // Se envían los campos personalizados de cabecera y de ítem en un array
                $ofes['valores_personalizados']         = '';
                $ofes['valores_personalizados_ds']      = '';
                $ofes['valores_personalizados_item']    = '';
                $ofes['valores_personalizados_item_ds'] = '';
                $arrKey = ['valores_personalizados','valores_personalizados_ds','valores_personalizados_item','valores_personalizados_item_ds'];
                if ($ofes->ofe_campos_personalizados_factura_generica != '' && $ofes->ofe_campos_personalizados_factura_generica != null) {
                    foreach ($ofes->ofe_campos_personalizados_factura_generica as $key => $camposPersonalizados) {
                        if (!empty($camposPersonalizados) && in_array($key, $arrKey)) {
                            foreach ($camposPersonalizados as $campo) {
                                $nameCampo = "";
                                $campo = (array) $campo;
                                foreach ($campo as $llave => $valorCampo) {
                                    if ($llave == 'campo')
                                        $nameCampo = $valorCampo;
                                }
                                $valorSanitizado = $this->sanear_string(str_replace(' ', '_', mb_strtolower($nameCampo, 'UTF-8')));

                                switch ($key) {
                                    case 'valores_personalizados':
                                        $arrCamposPersonalizados[0][] = $campo;
                                        $arrCamposPersonalizados[1][] = $valorSanitizado;
                                        break;
                                    case 'valores_personalizados_item':
                                        $arrCamposPersonalizadosItem[0][] = $campo;
                                        $arrCamposPersonalizadosItem[1][] = $valorSanitizado;
                                        break;
                                    case 'valores_personalizados_ds':
                                        $arrCamposPersonalizadosDS[0][] = $campo;
                                        $arrCamposPersonalizadosDS[1][] = $valorSanitizado;
                                        break;
                                    case 'valores_personalizados_item_ds':
                                        $arrCamposPersonalizadosItemDS[0][] = $campo;
                                        $arrCamposPersonalizadosItemDS[1][] = $valorSanitizado;
                                        break;
                                    default:
                                        break;
                                }
                            }
                            switch ($key) {
                                case 'valores_personalizados':
                                    $ofes['valores_personalizados'] = $arrCamposPersonalizados;
                                    break;
                                case 'valores_personalizados_item':
                                    $ofes['valores_personalizados_item'] = $arrCamposPersonalizadosItem;
                                    break;
                                case 'valores_personalizados_ds':
                                    $ofes['valores_personalizados_ds'] = $arrCamposPersonalizadosDS;
                                    break;
                                case 'valores_personalizados_item_ds':
                                    $ofes['valores_personalizados_item_ds'] = $arrCamposPersonalizadosItemDS;
                                    break;
                                default:
                                    break;
                            }
                        }
                    }
                }

                return $ofes;
            });
        
        return response()->json([
            'data' => $ofes
        ], Response::HTTP_OK);
    }

    /**
     * Configura las reglas para poder actualizar o crear los datos de los adqs en funcion de los codigos proporcionados
     *
     * @param Request $request
     * @return mixed
     */
    private function getRules(Request $request) {
        $rules = $this->className::$rules;
        unset(
            $rules['sft_id'],
            $rules['sft_id_ds'],
            $rules['tdo_id'],
            $rules['toj_id'],
            $rules['pai_id'],
            $rules['dep_id'],
            $rules['mun_id'],
            $rules['cpo_id'],
            $rules['rfi_id'],
            $rules['ref_id'],
            $rules['tat_id'],
            $rules['pai_id_domicilio_fiscal'],
            $rules['dep_id_domicilio_fiscal'],
            $rules['mun_id_domicilio_fiscal'],
            $rules['cpo_id_domicilio_fiscal'],
            $rules['ofe_campos_personalizados_factura_generica']
        );

        if ($request['tdo_codigo'] == '13' || $request['tdo_codigo'] == '31') {
            $rules['ofe_identificacion'] = 'required|string|regex:/^[1-9]/';
        }

        $rules['sft_identificador'] = Rule::requiredIf(function() use ($request) {
            return !isset($request->sft_identificador_ds) || (isset($request->sft_identificador_ds) && empty($request->sft_identificador_ds));
        });

        $rules['sft_identificador_ds'] = Rule::requiredIf(function() use ($request) {
            return !isset($request->sft_identificador) || (isset($request->sft_identificador) && empty($request->sft_identificador));
        });
        
        $rules['tdo_codigo'] = 'required|string|max:2';
        $rules['toj_codigo'] = 'required|string|max:2';
        $rules['rfi_codigo'] = 'nullable|string|max:2';
        $rules['ref_codigo'] = 'nullable|array|max:20';
        $rules['tat_codigo'] = 'nullable|string|max:5';

        $rules['pai_codigo']    = 'nullable|string|max:10';
        $rules['dep_codigo']    = 'nullable|string|max:10';
        $rules['mun_codigo']    = 'nullable|string|max:10';
        $rules['cpo_codigo']    = 'nullable|string|max:10';
        $rules['ofe_direccion'] = 'nullable|string|max:255';
        $rules['ofe_telefono']  = 'nullable|string|max:50';
        $rules['ofe_nombre_contacto'] = 'nullable|string|max:255';
        $rules['ofe_fax']             = 'nullable|string|max:50';

        $rules['pai_codigo_domicilio_fiscal']     = 'nullable|string|max:10';
        $rules['dep_codigo_domicilio_fiscal']     = 'nullable|string|max:10';
        $rules['mun_codigo_domicilio_fiscal']     = 'nullable|string|max:10';
        $rules['cpo_codigo_domicilio_fiscal']     = 'nullable|string|max:10';
        $rules['ofe_direccion_domicilio_fiscal']  = 'nullable|string|max:255';
        $rules['ofe_correos_notificacion']        = 'nullable|string';
        $rules['ofe_notificacion_un_solo_correo'] = 'nullable|string|in:SI,NO';
        $rules['ofe_integracion_ecm']   		  = 'nullable|string|in:SI,NO';
        $rules['ofe_integracion_ecm_conexion']    = 'nullable|json';

        // Condicionales para direcciones de domicilio
        $rules['pai_codigo'] = Rule::requiredIf(function() use ($request) {
            return !empty($request->dep_codigo) || !empty($request->mun_codigo) || !empty($request->ofe_direccion) || !empty($request->cpo_codigo);
        });

        $rules['ofe_direccion'] = Rule::requiredIf(function() use ($request) {
            return !empty($request->pai_codigo) || !empty($request->mun_codigo) || !empty($request->dep_codigo) || !empty($request->cpo_codigo);
        });

        // Condicionales para direcciones fiscales
        $rules['pai_codigo_domicilio_fiscal'] = Rule::requiredIf(function() use ($request) {
            return !empty($request->dep_codigo_domicilio_fiscal) || !empty($request->mun_codigo_domicilio_fiscal) || !empty($request->ofe_direccion_domicilio_fiscal) || !empty($request->cpo_codigo_domicilio_fiscal);
        });

        $rules['ofe_direccion_domicilio_fiscal'] = Rule::requiredIf(function() use ($request) {
            return !empty($request->pai_codigo_domicilio_fiscal) || !empty($request->mun_codigo_domicilio_fiscal) || !empty($request->dep_codigo_domicilio_fiscal) || !empty($request->cpo_codigo_domicilio_fiscal);
        });

        return $rules;
    }

    /**
     * Reemplaza los codigos e identificaciones proporcionadas en los datos de entrada por los correspodientes IDs de las parametricas que pueden componer a un OFE.
     *
     * @param $origin
     * @param $datosParseados
     */
    private function buildData(&$origin, $datosParseados) {
        unset(
            $origin['sft_identificador'],
            $origin['sft_identificador_ds'],
            $origin['tdo_codigo'],
            $origin['toj_codigo'],
            $origin['pai_codigo'],
            $origin['dep_codigo'],
            $origin['mun_codigo'],
            $origin['cpo_codigo'],
            $origin['rfi_codigo'],
            $origin['ref_codigo'],
            $origin['tat_codigo'],
            $origin['pai_codigo_domicilio_fiscal'],
            $origin['dep_codigo_domicilio_fiscal'],
            $origin['mun_codigo_domicilio_fiscal'],
            $origin['cpo_codigo_domicilio_fiscal'],
            $origin['responsable_tributos'],
            $origin['contactos'],
            $origin['usuarios_cargas_manuales'],
            $origin['usuarios_asignados']
        );

        $origin['tdo_id']    = array_key_exists('tdo_id', $datosParseados) ? $datosParseados['tdo_id'] : null;
        $origin['toj_id']    = array_key_exists('toj_id', $datosParseados) ? $datosParseados['toj_id'] : null;
        $origin['pai_id']    = array_key_exists('pai_id', $datosParseados) ? $datosParseados['pai_id'] : null;
        $origin['dep_id']    = array_key_exists('dep_id', $datosParseados) ? $datosParseados['dep_id'] : null;
        $origin['mun_id']    = array_key_exists('mun_id', $datosParseados) ? $datosParseados['mun_id'] : null;
        $origin['cpo_id']    = array_key_exists('cpo_id', $datosParseados) ? $datosParseados['cpo_id'] : null;
        $origin['rfi_id']    = array_key_exists('rfi_id', $datosParseados) && !empty($datosParseados['rfi_id']) ? $datosParseados['rfi_id'] : null;
        $origin['ref_id']    = array_key_exists('responsabilidades_fiscales', $datosParseados) && !empty($datosParseados['responsabilidades_fiscales']) ? implode(';', $datosParseados['responsabilidades_fiscales']) : null;
        $origin['tat_id']    = array_key_exists('tat_id', $datosParseados) ? $datosParseados['tat_id'] : null;
        $origin['sft_id']    = array_key_exists('sft_id', $datosParseados) ? $datosParseados['sft_id'] : null;
        $origin['sft_id_ds'] = array_key_exists('sft_id_ds', $datosParseados) ? $datosParseados['sft_id_ds'] : null;

        $origin['pai_id_domicilio_fiscal'] = array_key_exists('pai_id_domicilio_fiscal', $datosParseados) ? $datosParseados['pai_id_domicilio_fiscal'] : null;
        $origin['dep_id_domicilio_fiscal'] = array_key_exists('dep_id_domicilio_fiscal', $datosParseados) ? $datosParseados['dep_id_domicilio_fiscal'] : null;
        $origin['mun_id_domicilio_fiscal'] = array_key_exists('mun_id_domicilio_fiscal', $datosParseados) ? $datosParseados['mun_id_domicilio_fiscal'] : null;
        $origin['cpo_id_domicilio_fiscal'] = array_key_exists('cpo_id_domicilio_fiscal', $datosParseados) ? $datosParseados['cpo_id_domicilio_fiscal'] : null;
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function show($id) {
        $arrRelaciones = [
            'getUsuarioCreacion',
            'getParametrosDepartamento',
            'getParametrosMunicipio',
            'getParametrosPais',
            'getConfiguracionSoftwareProveedorTecnologico',
            'getConfiguracionSoftwareProveedorTecnologicoDs',
            'getConfiguracionObservacionesGeneralesFactura',
            'getContactos',
            'getParametrosRegimenFiscal',
            'getResolucionesFacturacion',
            'getParametroDomicilioFiscalPais',
            'getParametroDomicilioFiscalDepartamento',
            'getParametroDomicilioFiscalMunicipio',
            'getTipoOrganizacionJuridica',
            'getTiempoAceptacionTacita',
            'getTributos:tri_id,ofe_id',
            'getTributos.getDetalleTributo:tri_id,tri_codigo,tri_nombre,tri_descripcion',
            'getResponsabilidadFiscal:ref_id,ref_codigo,ref_descripcion',
            'getCodigoPostal',
            'getCodigoPostalDomicilioFiscal',
            'getParametroTipoDocumento'
        ];
        $response = $this->procesadorShow($id, $arrRelaciones, true);

        if (is_array($response)) {
            $responsabilidades_fiscales = [];
            if (!empty($response['ref_id'])) {
                $responsabilidades_fiscales = $this->listarResponsabilidadesFiscales($response['ref_id']);
            }

            $arrOfe = $response;
            $arrOfe['responsabilidades_fiscales'] = $responsabilidades_fiscales;

            // Verifica si existe logo de notificacion eventos DIAN para el OFE e incluirlo en la respuesta
            $logoEventosDian = $this->obtenerLogoEventosDian($id);
            if(array_key_exists('errors', $logoEventosDian) && !empty($logoEventosDian)) {
                $arrOfe['logoEventosDian'] = '';
            } else {
                $arrOfe['logoEventosDian'] = $logoEventosDian['data'];
            }

            if ($arrOfe){
                return response()->json([
                    'data' => $arrOfe
                ], Response::HTTP_OK);
            }
        }

        return $response;
    }


    /**
     * Muestra una lista de OFE.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     * @throws \Exception
     */
    public function getListaOfe(Request $request) {
        // Base de datos del usuario autenticado
        $user = auth()->user();
        if(empty($user->bdd_id_rg)) {
            $filtros = [
                'AND' => [
                    ['bdd_id_rg', '=', null]
                ]
            ];
        } else {
            $filtros = [
                'AND' => [
                    ['bdd_id_rg', '=', $user->bdd_id_rg]
                ]
            ];
        }

        $condiciones = [
            'filters' => $filtros,
        ];
        $columnas = [
            'ofe_id',
            'sft_id',
            'sft_id_ds',
            'tdo_id',
            'toj_id',
            'ofe_identificacion',
            'ofe_razon_social',
            'ofe_nombre_comercial',
            'ofe_primer_apellido',
            'ofe_segundo_apellido',
            'ofe_primer_nombre',
            'ofe_otros_nombres',
            'pai_id',
            'dep_id',
            'mun_id',
            'cpo_id',
            'ofe_direccion',
            'ofe_direcciones_adicionales',
            'pai_id_domicilio_fiscal',
            'dep_id_domicilio_fiscal',
            'mun_id_domicilio_fiscal',
            'cpo_id_domicilio_fiscal',
            'ofe_direccion_domicilio_fiscal',
            'ofe_nombre_contacto',
            'ofe_fax',
            'ofe_notas',
            'ofe_telefono',
            'ofe_web',
            'ofe_correo',
            'ofe_twitter',
            'ofe_facebook',
            'rfi_id',
            'ref_id',
            'ofe_matricula_mercantil',
            'ofe_actividad_economica',
            'ofe_correos_notificacion',
            'ofe_notificacion_un_solo_correo',
            'ofe_correos_autorespuesta',
            'ofe_tiene_representacion_grafica_personalizada',
            'ofe_tiene_representacion_grafica_personalizada_ds',
            'ofe_emision',
            'ofe_documento_soporte',
            'ofe_cadisoft_activo',
            'ofe_prioridad_agendamiento',
            'usuario_creacion',
            'fecha_modificacion',
            'estado',
        ];
        $relaciones = [
            'getUsuarioCreacion',
            'getParametrosDepartamento',
            'getParametrosMunicipio',
            'getParametrosPais',
            'getConfiguracionSoftwareProveedorTecnologico',
            'getConfiguracionSoftwareProveedorTecnologicoDs',
            'getConfiguracionObservacionesGeneralesFactura',
            'getContactos',
            'getParametrosRegimenFiscal',
            'getResolucionesFacturacion',
            'getParametroDomicilioFiscalPais',
            'getParametroDomicilioFiscalDepartamento',
            'getParametroDomicilioFiscalMunicipio',
            'getTipoOrganizacionJuridica',
            'getResponsabilidadFiscal',
            'getTiempoAceptacionTacita',
            // 'getRegimenFiscal',
            'getTributos',
            'getTributos.getDetalleTributo:tri_id,tri_codigo,tri_nombre,tri_descripcion',
            'getCodigoPostal',
            'getCodigoPostalDomicilioFiscal',
            'getParametroTipoDocumento'
        ];
        $exportacion = [
            'columnas' => [
                'ofe_identificacion'    => 'NIT OFE',
                'ofe_razon_social'      => 'RAZON SOCIAL',
                'ofe_nombre_comercial'  => 'NOMBRE COMERCIAL',
                'ofe_primer_nombre'     => 'PRIMER NOMBRE',
                'ofe_primer_apellido'   => 'PRIMER APELLIDO',
                'ofe_segundo_apellido'  => 'SEGUNDO APELLIDO',
                'ofe_otros_nombres'     => 'OTROS NOMBRES',
                [
                    'multiple' => true,
                    'relation' => 'getParametroTipoDocumento',
                    'fields' => [
                        [
                            'label' => 'CODIGO TIPO DOCUMENTO',
                            'field' => 'tdo_codigo'
                        ],
                        [
                            'label' => 'TIPO DOCUMENTO',
                            'field' => 'tdo_descripcion'
                        ]
                    ]
                ],
                [
                    'multiple' => true,
                    'relation' => 'getTipoOrganizacionJuridica',
                    'fields' => [
                        [
                            'label' => 'CODIGO TIPO ORGANIZACION JURIDICA',
                            'field' => 'toj_codigo'
                        ],
                        [
                            'label' => 'TIPO ORGANIZACION JURIDICA',
                            'field' => 'toj_descripcion'
                        ]
                    ]
                ],
                [
                    'multiple' => true,
                    'relation' => 'getParametrosPais',
                    'fields' => [
                        [
                            'label' => 'CODIGO PAIS',
                            'field' => 'pai_codigo'
                        ],
                        [
                            'label' => 'PAIS',
                            'field' => 'pai_descripcion'
                        ]
                    ]
                ],
                [
                    'multiple' => true,
                    'relation' => 'getParametrosDepartamento',
                    'fields' => [
                        [
                            'label' => 'CODIGO DEPARTAMENTO',
                            'field' => 'dep_codigo'
                        ],
                        [
                            'label' => 'DEPARTAMENTO',
                            'field' => 'dep_descripcion'
                        ]
                    ]
                ],
                [
                    'multiple' => true,
                    'relation' => 'getParametrosMunicipio',
                    'fields' => [
                        [
                            'label' => 'CODIGO MUNICIPIO',
                            'field' => 'mun_codigo'
                        ],
                        [
                            'label' => 'MUNICIPIO',
                            'field' => 'mun_descripcion'
                        ]
                    ]
                ],
                'cpo_id' => [
                    'label' => 'CODIGO POSTAL',
                    'relation' => ['name' => 'getCodigoPostal', 'field' => 'cpo_codigo']
                ],
                'ofe_direccion' => 'DIRECCION',
                'ofe_direcciones_adicionales' => [
                    'label' => 'DIRECCIONES ADICIONALES',
                    'type' => self::TYPE_ARRAY
                ],
                'pai_id_domicilio_fiscal' => [
                    'multiple' => true,
                    'relation' => 'getParametroDomicilioFiscalPais',
                    'fields' => [
                        [
                            'label' => 'CODIGO PAIS DOMICILIO FISCAL',
                            'field' => 'pai_codigo'
                        ],
                        [
                            'label' => 'PAIS DOMICILIO FISCAL',
                            'field' => 'pai_descripcion'
                        ]
                    ]
                ],
                'dep_id_domicilio_fiscal' => [
                    'multiple' => true,
                    'relation' => 'getParametroDomicilioFiscalDepartamento',
                    'fields' => [
                        [
                            'label' => 'CODIGO DEPARTAMENTO DOMICILIO FISCAL',
                            'field' => 'dep_codigo'
                        ],
                        [
                            'label' => 'DEPARTAMENTO DOMICILIO FISCAL',
                            'field' => 'dep_descripcion'
                        ]
                    ]
                ],
                'mun_id_domicilio_fiscal' => [
                    'multiple' => true,
                    'relation' => 'getParametroDomicilioFiscalMunicipio',
                    'fields' => [
                        [
                            'label' => 'CODIGO MUNICIPIO DOMICILIO FISCAL',
                            'field' => 'mun_codigo'
                        ],
                        [
                            'label' => 'MUNICIPIO DOMICILIO FISCAL',
                            'field' => 'mun_descripcion'
                        ]
                    ]
                ],
                'cpo_id_domicilio_fiscal' => [
                    'label' => 'CODIGO POSTAL DOMICILIO FISCAL',
                    'relation' => ['name' => 'getCodigoPostalDomicilioFiscal', 'field' => 'cpo_codigo']
                ],
                'ofe_direccion_domicilio_fiscal' => 'DIRECCION DOMICILIO FISCAL',
                'ofe_nombre_contacto'            => 'NOMBRE CONTACTO',
                'ofe_telefono'                   => 'TELEFONO',
                'ofe_fax'                        => 'FAX',
                'ofe_correo'                     => 'CORREO',
                'ofe_notas'                      => 'NOTAS',
                'ofe_web'                        => 'WEB',
                'ofe_twitter'                    => 'TWITTER',
                'ofe_facebook'                   => 'FACEBOOK',
                'rfi_id' => [
                    'multiple' => true,
                    'relation' => 'getParametrosRegimenFiscal',
                    'fields' => [
                        [
                            'label' => 'CODIGO REGIMEN FISCAL',
                            'field' => 'rfi_codigo'
                        ],
                        [
                            'label' => 'REGIMEN FISCAL',
                            'field' => 'rfi_descripcion'
                        ]
                    ]
                ],
                'ref_id' => 'CODIGO RESPONSABILIDADES FISCALES',
                // 'ref_id' => [
                //     'multiple' => true,
                //     'relation' => 'getResponsabilidadFiscal',
                //     'fields' => [
                //         [
                //             'label' => 'CODIGO RESPONSABILIDADES FISCALES',
                //             'field' => 'ref_codigo'
                //         ],
                //         [
                //             'label' => 'RESPONSABILIDADES FISCALES',
                //             'field' => 'ref_descripcion'
                //         ]
                //     ]
                // ],
                'tributos' => [
                    'callback' => true,
                    'relation' => 'getTributos',
                    'label' => 'RESPONSABLE TRIBUTOS',
                    'fields' => [
                        [
                            'field' => 'tri_id'
                        ],
                    ],
                    'function' => function ($extraDatos){
                        $codigos = ParametrosTributo::select('tri_codigo')->whereIn('tri_id', $extraDatos)->get();
                        if (!empty($codigos)) {
                            $codigos = collect($codigos->toArray())->pluck('tri_codigo')->toArray();
                            return implode(',', $codigos);
                        }
                        return '';
                    }
                ],
                'ofe_matricula_mercantil' => 'MATRICULA MERCANTIL',
                'ofe_actividad_economica' => 'ACTIVIDAD ECONOMICA',
                'sft_id' => [
                    'label' => 'SOFTWARE PROVEEDOR TECNOLOGICO EMISION',
                    'relation' => ['name' => 'getConfiguracionSoftwareProveedorTecnologico', 'field' => 'sft_identificador']
                ],
                'sft_id_ds' => [
                    'label' => 'SOFTWARE PROVEEDOR TECNOLOGICO DOCUMENTO SOPORTE',
                    'relation' => ['name' => 'getConfiguracionSoftwareProveedorTecnologicoDs', 'field' => 'sft_identificador']
                ],
                'ofe_correos_notificacion' => 'CORREOS NOTIFICACION',
                'ofe_notificacion_un_solo_correo' => 'NOTIFICACION UN SOLO CORREO',
                'ofe_correos_autorespuesta' => 'CORREOS AUTORESPUESTA',
                'ofe_prioridad_agendamiento' => 'PRIORIDAD AGENDAMIENTO',
                'usuario_creacion' => [
                    'label' => 'USUARIO CREACION',
                    'relation' => ['name' => 'getUsuarioCreacion', 'field' => 'usu_nombre']
                ],
                'fecha_creacion' => [
                    'label' => 'FECHA CREACION',
                    'type' => self::TYPE_CARBON
                ],
                'fecha_modificacion' => [
                    'label' => 'FECHA MODIFICACION',
                    'type' => self::TYPE_CARBON
                ],
                'estado' => 'ESTADO'
            ],
            'titulo' => 'oferentes'
        ];
        return $this->procesadorTracking($request, $condiciones, $columnas, $relaciones, $exportacion, false, [], 'configuracion');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function store(Request $request) {
        $this->user = auth()->user();
        $this->errors = [];
        $arrErroresObservacion = [];
        $arrErroresContactos = [];
        $request->merge([
            'bdd_id_rg' => $this->user->bdd_id_rg
        ]);

        $data = $request->all();
        if ($request->has('ofe_motivo_rechazo')) {
            $data['ofe_motivo_rechazo'] = json_encode($request->ofe_motivo_rechazo);
            $request->ofe_motivo_rechazo = json_encode($request->ofe_motivo_rechazo);
        }

        if (!empty($data['ref_codigo']) && is_string($data['ref_codigo'])){
            $codigos_responsabilidades_fiscales = explode(';', $data['ref_codigo'] ?? '');
            $data['ref_codigo'] = array_filter($codigos_responsabilidades_fiscales);
            $request->merge(['ref_codigo'=> $data['ref_codigo']]);
        } else if (empty($data['ref_codigo'])) {
            $data['ref_codigo'] = [];
        }

        if($request->has('ofe_identificador_unico_adquirente') && !empty($request->ofe_identificador_unico_adquirente)) {
            $data['ofe_identificador_unico_adquirente'] = json_encode(explode(',', $request->ofe_identificador_unico_adquirente));
        }

        if($request->has('ofe_informacion_personalizada_adquirente') && !empty($request->ofe_informacion_personalizada_adquirente)) {
            $data['ofe_informacion_personalizada_adquirente'] = json_encode(explode(',', $request->ofe_informacion_personalizada_adquirente));
        }

        // Validando los datos enviados
        $validador = Validator::make($data, $this->getRules($request));

        if (!$validador->fails()) {
            $ofeExiste = $this->className::where('ofe_identificacion', $request->ofe_identificacion)
                ->count();

            if ($ofeExiste) {
                return response()->json([
                    'message' => 'Errores al crear el OFE',
                    'errors' => ['El OFE [' . $request->ofe_identificacion . '] ya existe.']
                ], 409);
            }

            $datosParseados = $this->validarDatos($request);
            $arrObservaciones = [];
            $arrContactos = [];

            $arrMotivosRechazo = [];
            $arrMotivosRechazoExiste = [];
            if (!empty($request->ofe_motivo_rechazo)) {
                try {
                    $motivos = (array)json_decode($request->ofe_motivo_rechazo);
                } catch (\Exception $e) {
                    $motivos = [];
                }

                foreach ($motivos as $motivo) {
                    if (!in_array($motivo->motivo_codigo, $arrMotivosRechazoExiste)) {
                        $arrMotivosRechazo[][$motivo->motivo_codigo] = $motivo->motivo_descripcion;
                        $arrMotivosRechazoExiste[] = $motivo->motivo_codigo;
                    } else {
                        $this->adicionarErrorLocal($arrErrores, ['Se encontraron códigos repetidos en los motivos de rechazo']);
                    }
                }
            }

            if (!empty($request->observaciones)) {
                foreach ($request->observaciones as $observacion) {
                    $observacion = $observacion;
                    $validadorVacio = $this->revisarArregloVacio($observacion);

                    if ($validadorVacio) {
                        $objValidatorCogf = Validator::make($observacion, ConfiguracionObservacionesGeneralesFactura::$rules);
                        if (empty($objValidatorCogf->errors()->all())) {
                            $this->adicionarErrorLocal($arrErrores, $objValidatorCogf->errors()->all());
                        } else {
                            array_push($arrObservaciones, $observacion);
                        }
                    }
                }
            }

            $data = $request->all();
            if (empty($this->errors)) {
                $this->buildData($data, $datosParseados);
                $this->prepararDatos($data, $request);
                unset($data['ofe_representacion_grafica'],
                    $data['ofe_columnas_personalizadas'],
                    $data['ofe_conexion_ftp']);

                $data['ofe_tiene_representacion_grafica_personalizada'] = "SI";
                $data['ofe_campos_personalizados_factura_generica']     = null;
                $data['estado']                                         = "ACTIVO";

                // Verifica que la BD seleccionada exista
                $exiteBddIdRg = MainTrait::existeBddIdRg($request, 'crear');
                if(is_array($exiteBddIdRg)) {
                    return response()->json($exiteBddIdRg, 404);
                }

                // Verificación sobre el campo bdd_id_rg del usuario autenticado
                $bddIdRgCorresponde = MainTrait::bddIdRgCorresponde($request, $this->user, 'crear');
                if(is_array($bddIdRgCorresponde)) {
                    return response()->json($bddIdRgCorresponde, 422);
                }

                // Datos para documentos manuales
                $data['ofe_datos_documentos_manuales'] = config('variables_sistema.FACTURACION_WEB_DATOS_PRECARGADOS');
                
                //insertando data
                $objOfe = $this->className::create($data);

                if ($objOfe) {

                    if (isset($request->usuarios_cargas_manuales) || isset($request->usuarios_asignados)) {
                        $solicitados = [];
                        $asignados = [];

                        if (isset($request->usuarios_asignados) && $request->usuarios_asignados)
                            $asignados = explode(',', $request->usuarios_asignados);

                        if (isset($request->usuarios_cargas_manuales) && $request->usuarios_cargas_manuales)
                            $solicitados = explode(',', $request->usuarios_cargas_manuales);

                        $eliminar = array_diff($asignados, $solicitados);
                        $nuevos = array_diff($solicitados, $asignados);

                        $usuarios = User::whereIn('usu_email', $nuevos)->where('estado', 'ACTIVO')->get();
                        foreach ($usuarios as $user) {
                            $relaciones = [];
                            // Obtiene los OFEs con los que está relacionado el usuario
                            if (isset($user->usu_relaciones) && !is_null($user->usu_relaciones) && $user->usu_relaciones !== '') {
                                $relaciones = json_decode($user->usu_relaciones);
                                if (isset($relaciones->oferentes) && !in_array($objOfe->ofe_identificacion, $relaciones->oferentes))
                                    $relaciones->oferentes[] = $objOfe->ofe_identificacion;
                                else
                                    $relaciones->oferentes = [$objOfe->ofe_identificacion];
                                $relaciones->oferentes = array_values($relaciones->oferentes);
                                $user->usu_relaciones = json_encode($relaciones);
                                $user->save();
                            } else {
                                $relaciones = [
                                    'oferentes' => [$objOfe->ofe_identificacion]
                                ];
                                $user->usu_relaciones = json_encode($relaciones);
                                $user->save();
                            }
                        }

                        $usuarios = User::whereIn('usu_email', $eliminar)->where('estado', 'ACTIVO')->get();
                        foreach ($usuarios as $user) {
                            // Obtiene los OFEs con los que está relacionado el usuario
                            if (isset($user->usu_relaciones) && !is_null($user->usu_relaciones) && $user->usu_relaciones != '') {
                                $relaciones = json_decode($user->usu_relaciones);
                                if (isset($relaciones->oferentes) && is_array($relaciones->oferentes)) {
                                    $ofes = [];
                                    foreach ($relaciones->oferentes as $item)
                                        if ($item !== $objOfe->ofe_identificacion)
                                            $ofes[] = $item;
                                    $relaciones->oferentes = $ofes;
                                    $user->usu_relaciones = json_encode($relaciones);
                                    $user->save();
                                }
                            }
                        }
                    }

                    foreach ($arrObservaciones as $key => $observacion) {
                        $mObservacion = ConfiguracionObservacionesGeneralesFactura::create([
                            'ofe_id' => $objOfe->ofe_id,
                            'ogf_observacion' => $observacion['ogf_observacion'],
                            'usuario_creacion' => $this->user->usu_id,
                            'estado' => 'ACTIVO'
                        ]);

                        if (!$mObservacion) {
                            $llave = $key + 1;
                            $arrErroresObservacion = $this->adicionarErrorLocal($arrErroresObservacion, ["Error al Guardar la Observación General de Factura [{$llave}]."]);
                        }
                    }

                    if (!empty($arrErroresObservacion)) {
                        return response()->json([
                            'message' => 'Errores al crear Observaciones del OFE',
                            'errors' => $arrErroresObservacion
                        ], 422);
                    }

                    if ($objOfe) {
                        $contactos = [];
                        if ($request->has('contactos') && !empty($request->contactos))
                            $contactos = json_decode($request->contactos, true);
                        $this->insertContactos('ofe_id', $contactos, $objOfe, $this->user, 'ofe');
                    }

                    //Verificamos que no tengamos error para guardar los tributos
                    if (empty($this->errors)) {
                        if ($request->responsable_tributos === '' || $request->responsable_tributos === null)
                            $request->merge([
                                'responsable_tributos' => []
                            ]);

                        if (!empty($request->responsable_tributos) || is_array($request->responsable_tributos))
                            $this->insertTributos('ofe_id', $request->responsable_tributos, $objOfe, $this->user);
                    }
                } else {
                    return response()->json([
                        'message' => 'Errores al crear el OFE',
                        'errors' => []
                    ], 422);
                }


                if (!empty($erroresTributos)) {
                    $arrErrores = $this->adicionarError($arrErrores, $erroresTributos);
                }

                if (empty($arrErrores)) {
                    return response()->json([
                        'success' => true,
                        'ofe_id' => $objOfe->ofe_id
                    ], 200);
                } else {
                    return response()->json([
                        'message' => 'Errores al crear el OFE',
                        'errors' => $arrErrores
                    ], 400);
                }
            } else {
                return response()->json([
                    'message' => 'Errores al crear el OFE',
                    'errors' => $this->errors
                ], 400);
            }

        } else {
            return response()->json([
                'message' => 'Errores al crear el Ofe',
                'errors' => $validador->errors()->all()
            ], 400);
        }
    }

    /**
     * Prepara la data que va ser insertada o actualizada.
     *
     * @param array $data
     * @param $request
     */
    private function prepararDatos(array &$data, $request)
    {
        $data['usuario_creacion'] = $this->user->usu_id;
        //columnas JSON
        // $data['ofe_representacion_grafica'] = (json_encode($request->ofe_representacion_grafica)) ? json_encode($request->ofe_representacion_grafica) : null;
        // $data['ofe_columnas_personalizadas'] = (json_encode($request->ofe_columnas_personalizadas)) ? json_encode($request->ofe_columnas_personalizadas) : null;
        // $data['ofe_filtros'] = (json_encode($request->ofe_filtros)) ? json_encode($request->ofe_filtros) : null;
        // $data['ofe_conexion_ftp'] = (json_encode($request->ofe_conexion_ftp)) ? json_encode($request->ofe_conexion_ftp) : null;
        // $data['ofe_motivo_rechazo'] = (!empty($request->ofe_motivo_rechazo)) ? $request->ofe_motivo_rechazo : null;

        switch ($request->toj_codigo) {
            //Este case solo se cumple cuando es organizacion JURIDICA.
            case '1':
                $data['ofe_razon_social']     = $data['ofe_razon_social'];
                $data['ofe_nombre_comercial'] = ($data['ofe_nombre_comercial'] == "" ? $data['ofe_razon_social'] : $data['ofe_nombre_comercial']);
                $data['ofe_primer_nombre']    = NULL;
                $data['ofe_otros_nombres']    = NULL;
                $data['ofe_primer_apellido']  = NULL;
                $data['ofe_segundo_apellido'] = NULL;
                break;
            default:
                $data['ofe_razon_social']     = NULL;
                $data['ofe_nombre_comercial'] = NULL;
                $data['ofe_primer_nombre']    = $data['ofe_primer_nombre'];
                $data['ofe_otros_nombres']    = $data['ofe_otros_nombres'];
                $data['ofe_primer_apellido']  = $data['ofe_primer_apellido'];
                $data['ofe_segundo_apellido'] = $data['ofe_segundo_apellido'];
                break;
        }

        $data['ofe_nombre_contacto']            = $request->has('ofe_nombre_contacto') && !empty($request->ofe_nombre_contacto) ? $this->sanitizarStrings($request->ofe_nombre_contacto) : null;
        $data['ofe_fax']                        = $request->has('ofe_fax') && !empty($request->ofe_fax) ? $this->sanitizarStrings($request->ofe_fax) : null;
        $data['ofe_notas']                      = $request->has('ofe_notas') && !empty($request->ofe_notas) ? $this->sanitizarStrings($request->ofe_notas) : null;
        $data['ofe_telefono']                   = $request->has('ofe_telefono') && !empty($request->ofe_telefono) ? $this->sanitizarStrings($request->ofe_telefono) : null;
        $data['ofe_matricula_mercantil']        = $request->has('ofe_matricula_mercantil') && !empty($request->ofe_matricula_mercantil) ? $this->sanitizarStrings($request->ofe_matricula_mercantil) : null;
        $data['ofe_actividad_economica']        = $request->has('ofe_actividad_economica') && !empty($request->ofe_actividad_economica) ? $this->sanitizarStrings($request->ofe_actividad_economica) : null;
        $data['ofe_direccion']                  = $request->has('ofe_direccion') && !empty($request->ofe_direccion) ? $this->sanitizarStrings($request->ofe_direccion) : null;
        $data['ofe_direccion_domicilio_fiscal'] = $request->has('ofe_direccion_domicilio_fiscal') && !empty($request->ofe_direccion_domicilio_fiscal) ? $this->sanitizarStrings($request->ofe_direccion_domicilio_fiscal) : null;
    }


    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id) {
        $this->user = auth()->user();
        $this->errors = [];
        $arrErroresObservacion = [];
        $objOfe = $this->className::where('ofe_identificacion', $id)->first();
        $data = $request->all();

        if ($request->has('ofe_motivo_rechazo'))
            $data['ofe_motivo_rechazo'] = json_encode($request->ofe_motivo_rechazo);

        if (!empty($data['ref_codigo']) && is_string($data['ref_codigo'])){
            $codigos_responsabilidades_fiscales = explode(';', $data['ref_codigo'] ?? '');
            $data['ref_codigo'] = array_filter($codigos_responsabilidades_fiscales);
            $request->merge(['ref_codigo'=> $data['ref_codigo']]);
        } else if (empty($data['ref_codigo'])) {
            $data['ref_codigo'] = [];
        }

        if($request->has('ofe_identificador_unico_adquirente') && !empty($request->ofe_identificador_unico_adquirente)) {
            $data['ofe_identificador_unico_adquirente'] = json_encode(explode(',', $request->ofe_identificador_unico_adquirente));
        }

        if($request->has('ofe_informacion_personalizada_adquirente') && !empty($request->ofe_informacion_personalizada_adquirente)) {
            $data['ofe_informacion_personalizada_adquirente'] = json_encode(explode(',', $request->ofe_informacion_personalizada_adquirente));
        }

        // Validando los datos enviados
        $validador = Validator::make($data, $this->getRules($request));

        if ($validador->fails()) {
            return response()->json([
                'message' => 'Errores al actualizar el Oferente',
                'errors' => $validador->errors()->all()
            ], 400);
        }

        if ($objOfe) {
            // if($objOfe->estado == 'INACTIVO'){
            //     return response()->json([
            //         'message' => 'Errores al actualizar el OFE', 
            //         'errors' => ['No se permiten actualizar registros en estado INACTIVO.']
            //     ], 400);
            // }
            if ($id !== $request->ofe_identificacion) {
                $ofeExiste = $this->className::where('ofe_identificacion', $request->ofe_identificacion)
                    ->count();

                if ($ofeExiste) {
                    return response()->json([
                        'message' => 'Errores al crear el OFE',
                        'errors' => ['El OFE [' . $request->ofe_identificacion . '] ya existe.']
                    ], 409);
                }
            }

            $datosParseados = $this->validarDatos($request);
            $arrObservaciones = [];

            $arrMotivosRechazo = [];
            $arrMotivosRechazoExiste = [];
            if (!empty($request->ofe_motivo_rechazo)) {
                try {
                    $motivos = (array)json_decode($request->ofe_motivo_rechazo);
                } catch (\Exception $e) {
                    $motivos = [];
                }

                foreach ($motivos as $motivo) {
                    if (!in_array($motivo->motivo_codigo, $arrMotivosRechazoExiste)) {
                        $arrMotivosRechazo[][$motivo->motivo_codigo] = $motivo->motivo_descripcion;
                        $arrMotivosRechazoExiste[] = $motivo->motivo_codigo;
                    } else {
                        $this->adicionarErrorLocal($this->errors, ['Se encontraron códigos repetidos en los motivos de rechazo']);
                    }
                }
            }

            if (!empty($request->observaciones)) {
                foreach ($request->observaciones as $observacion) {
                    $validadorVacio = $this->revisarArregloVacio($observacion);

                    if ($validadorVacio) {
                        $objValidatorCogf = Validator::make($observacion, ConfiguracionObservacionesGeneralesFactura::$rules);
                        if (empty($objValidatorCogf->errors()->all())) {
                            $this->adicionarErrorLocal($this->errors, $objValidatorCogf->errors()->all());
                        } else {
                            array_push($arrObservaciones, $observacion);
                        }
                    }
                }
            }

            if (empty($this->errors)) {
                $this->buildData($data, $datosParseados);
                $this->prepararDatos($data, $request);

                $data['estado'] = $request->has('estado') ? $request->estado : $objOfe->estado;
                unset($data['ofe_representacion_grafica'],
                    $data['ofe_columnas_personalizadas'],
                    $data['ofe_conexion_ftp']);

                if ($request->has('ofe_campos_personalizados_factura_generica') && !empty($request->ofe_campos_personalizados_factura_generica)) {
                    $opciones = ['valores_resumen' => explode(',', $request->ofe_campos_personalizados_factura_generica)];
                    $data['ofe_campos_personalizados_factura_generica'] = $opciones;
                }

                if(!$request->has('ofe_actividad_economica'))
                    $data['ofe_actividad_economica'] = null;

                // Verifica que la BD seleccionada exista
                $exiteBddIdRg = MainTrait::existeBddIdRg($request, 'actualizar');
                if(is_array($exiteBddIdRg)) {
                    return response()->json($exiteBddIdRg, 404);
                }

                // Verificación sobre el campo bdd_id_rg del usuario autenticado
                $bddIdRgCorresponde = MainTrait::bddIdRgCorresponde($request, $this->user, 'actualizar');
                if(is_array($bddIdRgCorresponde)) {
                    return response()->json($bddIdRgCorresponde, 422);
                }

                // Datos para documentos manuales
                if(empty($objOfe->ofe_datos_documentos_manuales))
                    $data['ofe_datos_documentos_manuales'] = config('variables_sistema.FACTURACION_WEB_DATOS_PRECARGADOS');

                //actulizando data
                $objOfe->update($data);

                if ($objOfe) {
                    $aObservaciones = ConfiguracionObservacionesGeneralesFactura::where(['ofe_id' => $objOfe->ofe_id, 'estado' => 'ACTIVO'])->delete();
                    $aContactos = ConfiguracionContacto::where(['ofe_id' => $objOfe->ofe_id, 'estado' => 'ACTIVO'])->delete();
                    foreach ($arrObservaciones as $key => $observacion) {
                        $mObservacion = ConfiguracionObservacionesGeneralesFactura::create([
                                'ofe_id' => $objOfe->ofe_id,
                                'ogf_observacion' => $observacion['ogf_observacion'],
                                'usuario_creacion' => $this->user->usu_id,
                                'estado' => 'ACTIVO'
                            ]
                        );

                        if (!$mObservacion) {
                            $llave = $key + 1;
                            $arrErroresObservacion = $this->adicionarErrorLocal($arrErroresObservacion, ["Error al Guardar la Observación General de Factura [{$llave}]."]);
                        }
                    }

                    if (count($arrErroresObservacion) > 0) {
                        return response()->json([
                            'message' => 'Errores al crear Observaciones del OFE',
                            'errors' => $arrErroresObservacion
                        ], 422);
                    }


                    //Guardamos por si tenemos error los volvemos a crear
                    $contactosActuales = ConfiguracionContacto::where(['ofe_id' => $objOfe->ofe_id, 'estado' => 'ACTIVO'])->get()->toArray();

                    //Borrando los contactos existentes para insertarlos nuevamente
                    ConfiguracionContacto::where(['ofe_id' => $objOfe->ofe_id, 'estado' => 'ACTIVO'])->delete();

                    if ($objOfe) {
                        $contactos = [];
                        if ($request->has('contactos') && !empty($request->contactos))
                            $contactos = json_decode($request->contactos, true);
                        $this->insertContactos('ofe_id', $contactos, $objOfe, $this->user, 'ofe');
                    }

                    //Verificamos que no tengamos error para guardar los tributos
                    if (empty($this->errors)) {
                        if ($request->responsable_tributos === '' || $request->responsable_tributos === null)
                            $request->merge([
                                'responsable_tributos' => []
                            ]);

                        if (!empty($request->responsable_tributos) || is_array($request->responsable_tributos))
                            $this->insertTributos('ofe_id', $request->responsable_tributos, $objOfe, $this->user);
                    }

                    if (!empty($this->errors)) {
                        return response()->json([
                            'message' => "Errores al Actualizar el Oferente [{$objOfe->ofe_id}] ",
                            'errors' => $this->errors
                        ], 409);
                    }
                } else {
                    return response()->json([
                        'message' => 'Errores al actualizar el OFE',
                        'errors' => []
                    ], 422);
                }

                if (empty($this->errors)) {
                    return response()->json([
                        'success' => true
                    ], 200);
                } else {
                    return response()->json([
                        'message' => 'Errores al actualizar el OFE',
                        'errors' => []
                    ], 422);
                }
            } else {
                return response()->json([
                    'message' => 'Errores al actualizar el OFE',
                    'errors' => $this->errors
                ], 400);
            }

        } else {
            return response()->json([
                'message' => 'Errores al actualizar el OFE',
                'errors' => ['El Id del OFE [' . $id . '] no existe.']
            ], 409);
        }
    }

    /**
     * Cambia el estado de los OFE seleccionados.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function cambiarEstado(Request $request) {
        return $this->procesadorCambiarEstado($request);
    }

    /**
     * Toma los errores generados y los mezcla en un sólo arreglo para dar respuesta al usuarip
     *
     * @param array $arrErrores
     * @param array $objValidator
     * @return void
     */
    private function adicionarErrorLocal(&$arrErrores, $objValidator) {
        foreach ($objValidator as $error) {
            array_push($arrErrores, $error);
        }

        return $arrErrores;
    }

    /**
     * Metodo para Validar los Datos por la Opcion de Nuevo y Editar.
     * 
     * @param Request $request
     * @return array  $datos
     */
    public function validarDatos($request) {
        $datos = [];

        /**
         * Validaciones particulares
         */
        if (!$request->ofe_razon_social && !$request->ofe_primer_apellido && !$request->ofe_primer_nombre)
            $this->errors = $this->adicionarError($this->errors, ['No se encontró la razón social o nombres del OFE']);

        $datos['ofe_razon_social']     = $request->has('ofe_razon_social') && !empty($request->ofe_razon_social) ? $this->sanitizarStrings($request->ofe_razon_social) : null;
        $datos['ofe_nombre_comercial'] = $request->has('ofe_nombre_comercial') && !empty($request->ofe_nombre_comercial) ? $this->sanitizarStrings($request->ofe_nombre_comercial) : null;
        $datos['ofe_primer_apellido']  = $request->has('ofe_primer_apellido') && !empty($request->ofe_primer_apellido) ? $this->sanitizarStrings($request->ofe_primer_apellido) : null;
        $datos['ofe_segundo_apellido'] = $request->has('ofe_segundo_apellido') && !empty($request->ofe_segundo_apellido) ? $this->sanitizarStrings($request->ofe_segundo_apellido) : null;
        $datos['ofe_primer_nombre']    = $request->has('ofe_primer_nombre') && !empty($request->ofe_primer_nombre) ? $this->sanitizarStrings($request->ofe_primer_nombre) : null;
        $datos['ofe_otros_nombres']    = $request->has('ofe_otros_nombres') && !empty($request->ofe_otros_nombres) ? $this->sanitizarStrings($request->ofe_otros_nombres) : null;

        /**
         * Validaciones particulares
         */
        $datos['tdo_id'] = $this->validarTipoDocumento($request->tdo_codigo, 'DE');
        if ($request->has('pai_codigo') && !empty($request->pai_codigo))
            $datos['pai_id'] = $this->validarPais($request->pai_codigo, '', false, true);
        if ($request->has('pai_codigo') && $request->has('dep_codigo') && !empty($request->dep_codigo) && $datos['pai_id'] !== null)
            $datos['dep_id'] = $this->validarDepartamento($request->pai_codigo, $request->dep_codigo);
        if ($request->has('pai_codigo') && $request->has('mun_codigo') && !empty($request->mun_codigo) && $datos['pai_id'] !== null)
            $datos['mun_id'] = $this->validarMunicipio($request->pai_codigo, $request->dep_codigo, $request->mun_codigo);

        if ($request->has('tat_codigo') && !empty($request->tat_codigo))
            $datos['tat_id'] = $this->validarAceptacionTacita($request->tat_codigo);

        if ($request->has('cpo_codigo') && !empty($request->cpo_codigo))
            $datos['cpo_id'] = $this->validarCodigoPostal(
                $request->cpo_codigo,
                isset($request->dep_codigo) ? $request->dep_codigo : '',
                isset($request->mun_codigo) ? $request->mun_codigo : ''
            );

        if ($request->has('ref_codigo') && !empty($request->ref_codigo))
            $datos['responsabilidades_fiscales'] = $this->validarResponsibilidadFiscal($request->ref_codigo);

        if ($request->has('responsable_tributos') && !empty($request->responsable_tributos)) {
            $tributos = explode(",", $request->responsable_tributos);
            $datos['responsable_tributos'] = $this->validarTributo($tributos);
        }

        if ($request->has('rfi_codigo') && !empty($request->rfi_codigo))
            $datos['rfi_id'] = $this->validarRegimenFiscal($request->rfi_codigo);

        if ($request->has('ref_codigo') && !empty($request->ref_codigo))
            $this->validarResponsibilidadFiscalDocumentos($request->ref_codigo, 'DE');
        
        if ($request->has('responsable_tributos') && !empty($request->responsable_tributos)) {
            $codTributos = explode(",", $request->responsable_tributos);
            $this->validarTributoDocumentos($codTributos, 'DE');
        }

        // Validaciones para dirección de correspondencia
        $this->validacionGeneralDomicilio($request, 'ofe', 'correspondencia');
        // Validaciones para domicilio fiscal
        $this->validacionGeneralDomicilio($request, 'ofe', 'fiscal');

        if ($request->has('pai_codigo_domicilio_fiscal') && !empty($request->pai_codigo_domicilio_fiscal))
            $datos['pai_id_domicilio_fiscal'] = $this->validarPais($request->pai_codigo_domicilio_fiscal, 'En domicilio fiscal', false, true);

        if ($request->has('pai_codigo_domicilio_fiscal') && $request->has('dep_codigo_domicilio_fiscal') && !empty($request->dep_codigo_domicilio_fiscal) && $datos['pai_id_domicilio_fiscal'] !== null)
            $datos['dep_id_domicilio_fiscal'] = $this->validarDepartamento($request->pai_codigo_domicilio_fiscal, $request->dep_codigo_domicilio_fiscal, 'En domicilio fiscal');

        if ($request->has('pai_codigo_domicilio_fiscal') && $request->has('mun_codigo_domicilio_fiscal') && !empty($request->mun_codigo_domicilio_fiscal) && $datos['pai_id_domicilio_fiscal'] !== null)
            $datos['mun_id_domicilio_fiscal'] = $this->validarMunicipio($request->pai_codigo_domicilio_fiscal, $request->dep_codigo_domicilio_fiscal, $request->mun_codigo_domicilio_fiscal, 'En domicilio fiscal');

        if($request->has('cpo_codigo_domicilio_fiscal') && !empty($request->cpo_codigo_domicilio_fiscal))
            $datos['cpo_id_domicilio_fiscal'] = $this->validarCodigoPostal(
                $request->cpo_codigo_domicilio_fiscal,
                isset($request->dep_codigo_domicilio_fiscal) ? $request->dep_codigo_domicilio_fiscal : '' ,
                isset($request->mun_codigo_domicilio_fiscal) ? $request->mun_codigo_domicilio_fiscal : '' 
            );
        
        if ($request->has('ofe_direccion_domicilio_fiscal') && !empty($request->ofe_direccion_domicilio_fiscal))
            $datos['ofe_direccion_domicilio_fiscal'] = $this->sanitizarStrings($request->ofe_direccion_domicilio_fiscal);

        $datos['sft_id']    = null;
        $datos['sft_id_ds'] = null;
        if($request->filled('sft_id') || $request->filled('sft_identificador')) {
            $datos['sft_id'] = $this->validarSoftwareProveedorTecnologico($request, 'DE');
            if($datos['sft_id'] == null)
                $this->errors = $this->adicionarError($this->errors, ['No existe el Software Proveedor Tecnológico para [DE]']);
        }

        if($request->filled('sft_id_ds') || $request->filled('sft_identificador_ds')) {
            $datos['sft_id_ds'] = $this->validarSoftwareProveedorTecnologico($request, 'DS');
            if($datos['sft_id_ds'] == null)
                $this->errors = $this->adicionarError($this->errors, ['No existe el Software Proveedor Tecnológico para [DS]']);
        }

        if($datos['sft_id'] == null && $datos['sft_id_ds'] == null)
            $this->errors = $this->adicionarError($this->errors, ['Debe indicar el Software Identificador Tecnológico DE o DS']);

        //Validamos el tipo de organizacion juridica.
        $datos['toj_id'] = $this->validarTipoOrganizacionJuridica($request, 'ofe');

        return $datos;
    }

    /**
     * Verifica que exista el software proveedor tecnológico y que aplique dependiendo si se esta configurando para DE o DS.
     *
     * @param Request $request Petición
     * @param string $aplicaPara
     * @return null|int
     */
    protected function validarSoftwareProveedorTecnologico(Request $request, string $aplicaPara) {
        $softwareProveedorTecnologico = ConfiguracionSoftwareProveedorTecnologico::select(['sft_id'])
            ->where('sft_aplica_para', 'LIKE', '%' . $aplicaPara . '%')
            ->where('estado', 'ACTIVO');
        
        if(empty(auth()->user()->bdd_id_rg))
            $softwareProveedorTecnologico = $softwareProveedorTecnologico->whereNull('bdd_id_rg');
        else
            $softwareProveedorTecnologico = $softwareProveedorTecnologico->where('bdd_id_rg', auth()->user()->bdd_id_rg);

        if(!$request->filled('sft_id') && !$request->filled('sft_identificador') && !$request->filled('sft_id_ds') && !$request->filled('sft_identificador_ds')) {
            return null;
        } else {
            if($aplicaPara == 'DE') {
                if($request->filled('sft_id'))
                    $softwareProveedorTecnologico = $softwareProveedorTecnologico->where('sft_id', $request->sft_id);
                elseif($request->filled('sft_identificador'))
                    $softwareProveedorTecnologico = $softwareProveedorTecnologico->where('sft_identificador', $request->sft_identificador);
            } else {
                if($request->filled('sft_id_ds'))
                    $softwareProveedorTecnologico = $softwareProveedorTecnologico->where('sft_id', $request->sft_id_ds);
                elseif($request->filled('sft_identificador_ds'))
                    $softwareProveedorTecnologico = $softwareProveedorTecnologico->where('sft_identificador', $request->sft_identificador_ds);
            }
        }

        $softwareProveedorTecnologico = $softwareProveedorTecnologico->first();

        if (!$softwareProveedorTecnologico)
            return null;
        else
            return $softwareProveedorTecnologico->sft_id;
    }

    /**
     * Genera una Interfaz de Oferentes para guardar en Excel.
     *
     * @return \App\Http\Modulos\Utils\ExcelExports\ExcelExport
     */
    public function generarInterfaceOfe(Request $request) {
        return $this->generarInterfaceToTenant($this->columnasExcel, 'oferentes');
    }

    /**
     * Toma una interfaz en Excel y la almacena en la tabla de Oferentes.
     *
     * @param Request $request Paramétros de la petición
     * @return Response
     * @throws \Exception
     */
    public function cargarOferentes(Request $request) {
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        $objUser = auth()->user();

        if ($request->hasFile('archivo')) {
            $archivo = explode('.', $request->file('archivo')->getClientOriginalName());
            if (
                (!empty($request->file('archivo')->extension()) && !in_array($request->file('archivo')->extension(), $this->arrExtensionesPermitidas)) ||
                !in_array($archivo[count($archivo) - 1], $this->arrExtensionesPermitidas)
            )
                return response()->json([
                    'message' => 'Errores al guardar los Oferentes',
                    'errors'  => ['Solo se permite la carga de archivos EXCEL.']
                ], 409);


            $data = $this->parserExcel($request);
            if (!empty($data)) {
                // Se obtinen las columnas de la interfaz sanitizadas
                $tempColumnas = [];
                foreach ($this->columnasExcel as $k) {
                    $tempColumnas[] = strtolower($this->sanear_string(str_replace(' ', '_', $k)));
                }

                // Se obtienen las columnas del excel cargado
                $columnas = [];
                foreach ($data as $fila => $columna) {
                    // Se eliminan las columnas que son propias del excel generado desde el Tracking
                    unset(
                        $columna['prioridad_agendamiento'],
                        $columna['usuario_creacion'],
                        $columna['fecha_creacion'],
                        $columna['fecha_modificacion'],
                        $columna['estado']
                    );

                    $columnas = (array)$columna;
                    break;
                }

                // Valida que las columnas del excel cargado correspondan con las columnas de la interfaz
                $diferenciasFaltan = array_diff($tempColumnas, array_keys($columnas));
                $diferenciasSobran = array_diff(array_keys($columnas), $tempColumnas);
                if (!empty($diferenciasFaltan) || !empty($diferenciasSobran)) {
                    $errores = [];
                    if(!empty($diferenciasFaltan))
                        $errores[] = 'Faltan las columnas: ' . strtoupper(str_replace('_', ' ', implode(', ', $diferenciasFaltan)));

                    if(!empty($diferenciasSobran))
                        $errores[] = 'Sobran las columnas: ' . strtoupper(str_replace('_', ' ', implode(', ', $diferenciasSobran)));

                    return response()->json([
                        'message' => 'La estructura del archivo no corresponde con la interfaz solicitada',
                        'errors'  => $errores
                    ], 400);
                }
            } else {
                return response()->json([
                    'message' => 'Errores al guardar los Oferentes',
                    'errors'  => ['El archivo subido no tiene datos.']
                ], 400);
            }

            $arrErrores                         = [];
            $arrResultado                       = [];
            $arrExisteOfe                       = [];
            $arrExistePais                      = [];
            $arrExistePaisDepto                 = [];
            $arrExisteMunDepto                  = [];
            $arrExisteTipoDocumento             = [];
            $arrExisteRegimenFiscal             = [];
            $arrExisteTipoOrgJuridica           = [];
            $arrOfeInterOperabilidad            = [];
            $arrAdqIds                          = [];
            $arrOfeProceso                      = [];
            $arrExisteTiempoAceptacionTacita    = [];
            $arrExisteResponsabilidadesFiscales = [];
            $arrExisteResponsablesTributos      = [];
            $arrExisteSft                       = [];
            $arrExisteSftDs                     = [];

            SistemaTiempoAceptacionTacita::where('estado', 'ACTIVO')->get()->map(function ($tat) use (&$arrExisteTiempoAceptacionTacita) {
                $arrExisteTiempoAceptacionTacita[$tat->tat_codigo] = $tat;
            });

            ParametrosResponsabilidadFiscal::where('estado', 'ACTIVO')->get()
                ->groupBy('ref_codigo')
                ->map(function ($ref) use (&$arrExisteResponsabilidadesFiscales) {
                    $vigente = $this->validarVigenciaRegistroParametrica($ref);
                    if ($vigente['vigente']) {
                        $arrExisteResponsabilidadesFiscales[$vigente['registro']->ref_codigo] = $vigente['registro'];
                    }
                });

            ParametrosTipoDocumento::where('tdo_aplica_para', 'LIKE', '%DE%')
                ->where('estado', 'ACTIVO')
                ->get()
                ->groupBy('tdo_codigo')
                ->map(function ($doc) use (&$arrExisteTipoDocumento) {
                    $vigente = $this->validarVigenciaRegistroParametrica($doc);
                    if ($vigente['vigente']) {
                        $arrExisteTipoDocumento[$vigente['registro']->tdo_codigo] = $vigente['registro'];
                    }   
                });

            ParametrosRegimenFiscal::where('estado', 'ACTIVO')->get()
                ->groupBy('rfi_codigo')
                ->map(function ($rfi) use (&$arrExisteRegimenFiscal) {
                    $vigente = $this->validarVigenciaRegistroParametrica($rfi);
                    if ($vigente['vigente']) {
                        $arrExisteRegimenFiscal[$vigente['registro']->rfi_codigo] = $vigente['registro'];
                    }
                });

            ParametrosTipoOrganizacionJuridica::where('estado', 'ACTIVO')->get()
                ->groupBy('toj_codigo')
                ->map(function ($toj) use (&$arrExisteTipoOrgJuridica) {
                    $vigente = $this->validarVigenciaRegistroParametrica($toj);
                    if ($vigente['vigente']) {
                        $arrExisteTipoOrgJuridica[$vigente['registro']->toj_codigo] = $vigente['registro'];
                    }
                });

            ParametrosTributo::where('estado', 'ACTIVO')->where('tri_aplica_persona', 'SI')->get()
                ->groupBy('tri_codigo')
                ->map(function ($tri) use (&$arrExisteResponsablesTributos) {
                    $vigente = $this->validarVigenciaRegistroParametrica($tri);
                    if ($vigente['vigente']) {
                        $arrExisteResponsablesTributos[$vigente['registro']->tri_codigo] = $vigente['registro'];
                    }
                });

            $softwareProveedorTecnologico = ConfiguracionSoftwareProveedorTecnologico::where('sft_aplica_para', 'LIKE', '%DE%')
                ->orWhere('sft_aplica_para', 'LIKE', '%DS%')
                ->where('estado', 'ACTIVO');
                
            if(empty($objUser->bdd_id_rg))
                $softwareProveedorTecnologico = $softwareProveedorTecnologico->whereNull('bdd_id_rg');
            else
                $softwareProveedorTecnologico = $softwareProveedorTecnologico->where('bdd_id_rg', $objUser->bdd_id_rg);
                
            $softwareProveedorTecnologico = $softwareProveedorTecnologico->get()
                ->map(function ($sft) use (&$arrExisteSft, &$arrExisteSftDs) {
                    if($sft->sft_aplica_para == 'DE')
                        $arrExisteSft[$sft->sft_identificador] = $sft;
                    elseif($sft->sft_aplica_para == 'DS')
                        $arrExisteSftDs[$sft->sft_identificador] = $sft;
                });

            ParametrosCodigoPostal::where('estado', 'ACTIVO')->select('cpo_id', 'cpo_codigo', 'fecha_vigencia_desde', 'fecha_vigencia_hasta')->get()
                ->groupBy('cpo_codigo')
                ->map(function ($cpo) use (&$arrExisteCodigoPostal) {
                    $vigente = $this->validarVigenciaRegistroParametrica($cpo);
                    if ($vigente['vigente']) {
                        $arrExisteCodigoPostal[$vigente['registro']->cpo_codigo] = $vigente['registro'];
                    }
                });

            foreach ($data as $fila => $columnas) {
                $Acolumnas = $columnas;
                $columnas = (object)$columnas;

                $nCantidadVacias = 0;
                foreach ($Acolumnas as $key => $value) {
                    $Acolumnas[$key] = trim($value);
                    if ($Acolumnas[$key] == "") {
                        $nCantidadVacias++;
                    }
                }

                if ($nCantidadVacias == count($Acolumnas)) {
                    unset($data[$fila]);
                } else {
                    $arrOfe = [];
                    $arrFaltantes = $this->checkFields($Acolumnas, [
                        'nit_ofe',
                        'codigo_tipo_organizacion_juridica',
                        'codigo_tipo_documento',
                        'correo',
                        'codigo_regimen_fiscal',
                        'codigo_responsabilidades_fiscales'
                    ], $fila);

                    if (empty($columnas->codigo_departamento)){
                        $columnas->codigo_departamento == '';
                    }

                    /* if ($columnas->codigo_pais == 'CO') {
                        array_push($campos, 'codigo_departamento');
                    } */

                    if (empty($columnas->codigo_departamento_domicilio_fiscal)){
                        $columnas->codigo_departamento_domicilio_fiscal == '';
                    }

                    /* if ($columnas->pais_domicilio_fiscal == 'CO') {
                        array_push($campos, 'codigo_departamento_domicilio_fiscal');
                    } */


                    if (!empty($arrFaltantes)) {
                        $vacio = $this->revisarArregloVacio($columnas);

                        if ($vacio) {
                            $arrErrores = $this->adicionarError($arrErrores, $arrFaltantes, $fila);
                        } else {
                            unset($data[$fila]);
                        }
                    } else {
                        //nit_ofe
                        $arrOfe['ofe_id'] = 0;
                        $arrOfe['ofe_identificacion'] = (string)$columnas->nit_ofe;

                        if (!preg_match("/^[1-9]/", $arrOfe['ofe_identificacion']) && 
                            ($columnas->codigo_tipo_documento == '13' || $columnas->codigo_tipo_documento == '31')
                        ) {
                            $arrErrores = $this->adicionarError($arrErrores, ['El formato del campo Identificación del Oferente es inválido.'], $fila);
                        }

                        if (array_key_exists($columnas->nit_ofe, $arrExisteOfe)) {
                            $objExisteOfe = $arrExisteOfe[$columnas->nit_ofe];
                        } else {
                            $objExisteOfe = $this->className::where('ofe_identificacion', $columnas->nit_ofe)
                                ->first();
                            $arrExisteOfe[$columnas->nit_ofe] = $objExisteOfe;
                        }

                        if (!empty($objExisteOfe)) {
                            if ($objExisteOfe->estado == 'INACTIVO') {
                                $arrErrores = $this->adicionarError($arrErrores, ['No se permiten actualizar registros en estado INACTIVO.'], $fila);
                            } else {
                                $arrOfe['ofe_id'] = $objExisteOfe->ofe_id;
                            }
                        }

                        // El usuario autenticado tiene base de datos asignada
                        if(!empty($objUser->bdd_id_rg)) {
                            if (!empty($objExisteOfe)) {
                                if ($objExisteOfe->estado == 'INACTIVO') {
                                    $arrErrores = $this->adicionarError($arrErrores, ['No se permiten actualizar registros en estado INACTIVO.'], $fila);
                                } else {
                                    if($objExisteOfe->bdd_id_rg != $objUser->bdd_id_rg) {
                                        $arrErrores = $this->adicionarError($arrErrores, ['OFE no válido.'], $fila);
                                    } else {
                                        $arrOfe['ofe_id']    = $objExisteOfe->ofe_id;
                                        $arrOfe['bdd_id_rg'] = $objExisteOfe->bdd_id_rg;
                                    }
                                }
                            } else {
                                $arrOfe['bdd_id_rg'] = $objUser->bdd_id_rg;
                            }
                        } else {
                            if (empty($objExisteOfe)) {
                                $arrOfe['bdd_id_rg'] = null;
                            } else {
                                $arrOfe['bdd_id_rg'] = $objExisteOfe->bdd_id_rg;
                            }
                        }

                        $arrOfe['ref_id'] = '';
                        if (!empty($columnas->codigo_responsabilidades_fiscales)) {

                            $codigos_responsabilidades_fiscales = explode(';', $columnas->codigo_responsabilidades_fiscales);
                            $responsabilidades_fiscales = ParametrosResponsabilidadFiscal::select(['ref_codigo'])
                                ->where('estado', 'ACTIVO')
                                ->whereIn('ref_codigo', $codigos_responsabilidades_fiscales)
                                ->get()
                                ->groupBy('ref_codigo')
                                ->map(function ($item) {
                                    $vigente = $this->validarVigenciaRegistroParametrica($item);
                                    if ($vigente['vigente']) {
                                        return $vigente['registro'];
                                    }
                                });

                            $codigos = [];
                            if (!empty($responsabilidades_fiscales)) {
                                $codigos = $responsabilidades_fiscales->pluck('ref_codigo')->toArray();
                            }

                            foreach ($codigos_responsabilidades_fiscales as $codigo) {
                                if (!in_array($codigo, $codigos)) {
                                    $arrErrores = $this->adicionarError($arrErrores, ['El Código de la responsabilidad fiscal ['.$codigo.'], ya no está vigente, se encuentra INACTIVO o no existe.'], $fila);
                                }
                            }
                            if (!empty($codigos)){
                                $arrOfe['ref_id'] = implode(';',$codigos);
                            }
                        }

                        // $arrOfe['tat_id'] = '';
                        // if (property_exists($columnas, 'tiempo_aceptacion_tacita') && !empty($columnas->tiempo_aceptacion_tacita)) {
                        //     if (array_key_exists($columnas->tiempo_aceptacion_tacita, $arrExisteTiempoAceptacionTacita)) {
                        //         $objExisteTiempoAceptacionTacita = $arrExisteTiempoAceptacionTacita[$columnas->tiempo_aceptacion_tacita];
                        //         $arrOfe['tat_id'] = $objExisteTiempoAceptacionTacita->tat_id;
                        //     } else {
                        //         $arrErrores = $this->adicionarError($arrErrores, ['la Descripción del Tiempo Aceptación Tacita [' . $columnas->tiempo_aceptacion_tacita . '] no existe'], $fila);
                        //     }
                        // }

                        $arrOfe['cpo_id'] = null;
                        if (!empty($columnas->codigo_postal)) {
                            if (array_key_exists($columnas->codigo_postal, $arrExisteCodigoPostal)) {
                                $objExisteCodigoPostal = $arrExisteCodigoPostal[$columnas->codigo_postal];
                                $arrOfe['cpo_id'] = $objExisteCodigoPostal->cpo_id;
                            } elseif ($columnas->codigo_postal == $columnas->codigo_departamento . $columnas->codigo_municipio) {
                                $arrOfe['cpo_id'] = null;
                            } else {
                                $arrErrores = $this->adicionarError($arrErrores, ['El Código Postal [' . $columnas->codigo_postal . '], ya no está vigente, se encuentra INACTIVO o no existe.'], $fila);
                            }
                        }

                        $arrOfe['cpo_id_domicilio_fiscal'] = null;
                        if (!empty($columnas->codigo_postal_domicilio_fiscal)) {
                            if (array_key_exists($columnas->codigo_postal_domicilio_fiscal, $arrExisteCodigoPostal)) {
                                $objExisteCodigoPostal = $arrExisteCodigoPostal[$columnas->codigo_postal_domicilio_fiscal];
                                $arrOfe['cpo_id_domicilio_fiscal'] = $objExisteCodigoPostal->cpo_id;
                            } elseif ($columnas->codigo_postal_domicilio_fiscal == $columnas->codigo_departamento_domicilio_fiscal . $columnas->codigo_municipio_domicilio_fiscal) {
                                $arrOfe['cpo_id_domicilio_fiscal'] = null;
                            } else {
                                $arrErrores = $this->adicionarError($arrErrores, ['El Código Postal del Domicilio Fiscal [' . $columnas->codigo_postal_domicilio_fiscal . '], ya no está vigente, se encuentra INACTIVO o no existe.'], $fila);
                            }
                        }

                        $arrOfe['tributos'] = [];
                        if (property_exists($columnas, 'responsable_tributos') && !empty($columnas->responsable_tributos)) {
                            $tributos = explode(',', $columnas->responsable_tributos);

                            if (count($tributos) > 0) {
                                foreach ($tributos as $tributo) {
                                    if (array_key_exists($tributo, $arrExisteResponsablesTributos)) {
                                        $objExisteTributos = $arrExisteResponsablesTributos[$tributo];
                                        array_push($arrOfe['tributos'], $objExisteTributos->tri_id);
                                    } else {
                                        $arrErrores = $this->adicionarError($arrErrores, ['El Código del Tributo [' . $tributo . '], ya no está vigente, se encuentra INACTIVO, no existe o el campo Aplica Persona es diferente de SI'], $fila);
                                    }
                                }
                            }
                        }

                        if (!empty($columnas->codigo_responsabilidades_fiscales)) {
                            $codFiscales = explode(';', $columnas->codigo_responsabilidades_fiscales);
                            $arrErrores = $this->adicionarError($arrErrores, $this->validarResponsibilidadFiscalDocumentos($codFiscales, 'DE'), $fila);
                        }

                        if (!empty($columnas->responsable_tributos)) {
                            $codTributos = explode(',', $columnas->responsable_tributos);
                            $arrErrores = $this->adicionarError($arrErrores, $this->validarTributoDocumentos($codTributos, 'DE'), $fila);
                        }

                        // DIRECCIÓN CORRESPONDENCIA
                        $arrOfe['pai_id'] = null;
                        $arrOfe['dep_id'] = null;
                        $arrOfe['mun_id'] = null;
                        // Si llega el pais, departamento, ciudad o direccion, el país y dirección del apartado de domicilio son obligatorios
                        if(
                            (isset($columnas->codigo_pais) && $columnas->codigo_pais != '') ||
                            (isset($columnas->codigo_departamento) && $columnas->codigo_departamento != '') ||
                            (isset($columnas->codigo_municipio) && $columnas->codigo_municipio != '') ||
                            (isset($columnas->direccion) && $columnas->direccion != '')
                        ) {
                            $seccionCompleta = true;
                            if(!isset($columnas->codigo_pais) || (isset($columnas->codigo_pais) && $columnas->codigo_pais == ''))
                                $seccionCompleta = false;

                            if(!isset($columnas->direccion) || (isset($columnas->direccion) && $columnas->direccion == ''))
                                $seccionCompleta = false;


                            if(!$seccionCompleta)
                                $arrErrores = $this->adicionarError($arrErrores, ['Los campos país y dirección son obligatorios para la dirección de correspondencia '], $fila);
                            else {
                                if (isset($columnas->codigo_pais)) {
                                    if (array_key_exists($columnas->codigo_pais, $arrExistePais))
                                        $objExistePais = $arrExistePais[$columnas->codigo_pais];
                                    else {
                                        $objExistePais = ParametrosPais::where('pai_codigo', $columnas->codigo_pais)
                                            ->first();
                                        $arrExistePais[$columnas->codigo_pais] = $objExistePais;
                                    }
                                }

                                if (!empty($objExistePais) && $objExistePais->pai_codigo == 'CO') {
                                    $arrOfe['pai_id'] = $objExistePais->pai_id;

                                    if (isset($columnas->codigo_departamento)) {
                                        if (array_key_exists($columnas->codigo_pais . '-' . $columnas->codigo_departamento, $arrExistePaisDepto)) {
                                            $objExistePaisDepto = $arrExistePaisDepto[$columnas->codigo_pais . '-' . $columnas->codigo_departamento];
                                        } else {
                                            $objExistePaisDepto = ParametrosDepartamento::where('estado', 'ACTIVO')->where('pai_id', $objExistePais->pai_id)
                                                ->where('dep_codigo', $columnas->codigo_departamento)->first();
                                            $arrExistePaisDepto[$columnas->codigo_pais . '-' . $columnas->codigo_departamento] = $objExistePaisDepto;
                                        }
                                    }

                                    if (isset($objExistePaisDepto) || $columnas->codigo_pais != 'CO') {
                                        $objExisteMunDepto = null;

                                        if (!empty($objExistePaisDepto)){
                                            $arrOfe['dep_id'] = $objExistePaisDepto->dep_id ?? '';
                                        }
                                        $indice = $columnas->codigo_pais . '-' . ($columnas->codigo_departamento ?? '') . '-' . $columnas->codigo_municipio;
                                        if (array_key_exists($indice, $arrExisteMunDepto)) {
                                            $objExisteMunDepto = $arrExisteMunDepto[$indice];
                                        } else {
                                            $objExisteMunDepto = ParametrosMunicipio::where('estado', 'ACTIVO')->where('mun_codigo', $columnas->codigo_municipio);
                                            if (!empty($objExistePaisDepto)) {
                                                $objExisteMunDepto->where('dep_id', ($objExistePaisDepto->dep_id ?? '') );
                                            }
                                            $objExisteMunDepto = $objExisteMunDepto->first();

                                            $arrExisteMunDepto[$indice] = $objExisteMunDepto;
                                        }
                                        if (isset($objExisteMunDepto)) {
                                            $arrOfe['mun_id'] = $objExisteMunDepto->mun_id;
                                        } else {
                                            $arrErrores = $this->adicionarError($arrErrores, ['El Id del Municipio [' . $columnas->codigo_municipio . '] no existe para el Departamento [' . $columnas->codigo_departamento . '].'], $fila);
                                        }
                                    }
                                } elseif (!empty($objExistePais) && $objExistePais->pai_codigo != 'CO') {
                                    $arrErrores = $this->adicionarError($arrErrores, ['Para los OFEs el País válido es [CO - COLOMBIA]'], $fila);
                                } elseif (empty($objExistePais)) {
                                    $arrErrores = $this->adicionarError($arrErrores, ['El Id del País [' . $columnas->codigo_pais . '] no existe'], $fila);
                                }
                            }
                        }

                        //DOMICILIO FISCAL
                        $arrOfe['pai_id_domicilio_fiscal'] = null;
                        $arrOfe['dep_id_domicilio_fiscal'] = null;
                        $arrOfe['mun_id_domicilio_fiscal'] = null;
                        // Si llega el pais, departamento, ciudad o direccion fiscal, el país y dirección del apartado de domicilio fiscal son obligatorios
                        if(
                            (isset($columnas->codigo_pais_domicilio_fiscal) && $columnas->codigo_pais_domicilio_fiscal != '') ||
                            (isset($columnas->codigo_departamento_domicilio_fiscal) && $columnas->codigo_departamento_domicilio_fiscal != '') ||
                            (isset($columnas->codigo_municipio_domicilio_fiscal) && $columnas->codigo_municipio_domicilio_fiscal != '') ||
                            (isset($columnas->direccion_domicilio_fiscal) && $columnas->direccion_domicilio_fiscal != '')
                        ) {
                            $seccionCompleta = true;
                            if(!isset($columnas->codigo_pais_domicilio_fiscal) || (isset($columnas->codigo_pais_domicilio_fiscal) && $columnas->codigo_pais_domicilio_fiscal == ''))
                                $seccionCompleta = false;

                            if(!isset($columnas->direccion_domicilio_fiscal) || (isset($columnas->direccion_domicilio_fiscal) && $columnas->direccion_domicilio_fiscal == ''))
                                $seccionCompleta = false;

                            if(!$seccionCompleta)
                                $arrErrores = $this->adicionarError($arrErrores, ['Los campos país y dirección son obligatorios para el domicilio fiscal '], $fila);
                            else {
                                if (isset($columnas->codigo_pais_domicilio_fiscal)) {
                                    if (array_key_exists($columnas->codigo_pais_domicilio_fiscal, $arrExistePais))
                                        $objExistePaisDomFiscal = $arrExistePais[$columnas->codigo_pais_domicilio_fiscal];
                                    else {
                                        $objExistePaisDomFiscal = ParametrosPais::where('estado', 'ACTIVO')->where('pai_codigo', $columnas->codigo_pais_domicilio_fiscal)
                                            ->first();
                                        $arrExistePais[$columnas->codigo_pais_domicilio_fiscal] = $objExistePais;
                                    }
                                }

                                if (!empty($objExistePaisDomFiscal) && $objExistePaisDomFiscal->pai_codigo == 'CO') {
                                    $arrOfe['pai_id_domicilio_fiscal'] = $objExistePaisDomFiscal->pai_id;

                                    if (isset($columnas->codigo_departamento_domicilio_fiscal) && $columnas->codigo_departamento_domicilio_fiscal != '' && $columnas->codigo_pais_domicilio_fiscal == 'CO') {
                                        if (array_key_exists($columnas->codigo_pais_domicilio_fiscal . '-' . $columnas->codigo_departamento_domicilio_fiscal, $arrExistePaisDepto)) {
                                            $objExistePaisDeptoDomFiscal = $arrExistePaisDepto[$columnas->codigo_pais_domicilio_fiscal . '-' . $columnas->codigo_departamento_domicilio_fiscal];
                                        } else {
                                            $objExistePaisDeptoDomFiscal = ParametrosDepartamento::where('estado', 'ACTIVO')->where('pai_id', $objExistePaisDomFiscal->pai_id)
                                                ->where('dep_codigo', $columnas->codigo_departamento_domicilio_fiscal)->first();
                                            $arrExistePaisDepto[$columnas->codigo_pais_domicilio_fiscal . '-' . $columnas->codigo_departamento_domicilio_fiscal] = $objExistePaisDeptoDomFiscal;
                                        }
                                    }

                                    if (isset($objExistePaisDeptoDomFiscal) || $columnas->codigo_pais_domicilio_fiscal != 'CO') {
                                        $objExisteMunDeptoDomFiscal = null;
                                        if (!empty($objExistePaisDeptoDomFiscal)) {
                                            $arrOfe['dep_id_domicilio_fiscal'] = $objExistePaisDeptoDomFiscal->dep_id ?? '';
                                        }
                                        $indice = $columnas->codigo_pais_domicilio_fiscal . '-' . ($columnas->codigo_departamento_domicilio_fiscal ?? '') . '-' . $columnas->codigo_municipio_domicilio_fiscal;

                                        if (array_key_exists($indice, $arrExisteMunDepto)) {
                                            $objExisteMunDeptoDomFiscal = $arrExisteMunDepto[$indice];
                                        } else {
                                            $objExisteMunDeptoDomFiscal = ParametrosMunicipio::where('estado', 'ACTIVO')->where('mun_codigo', $columnas->codigo_municipio_domicilio_fiscal);
                                            if (!empty($objExistePaisDepto)) {
                                                $objExisteMunDeptoDomFiscal->where('dep_id', ($objExistePaisDepto->dep_id ?? '') );
                                            }
                                            $objExisteMunDeptoDomFiscal = $objExisteMunDepto->first();

                                            $arrExisteMunDepto[$indice] = $objExisteMunDeptoDomFiscal;
                                        }
                                        if (isset($objExisteMunDeptoDomFiscal)) {
                                            $arrOfe['mun_id_domicilio_fiscal'] = $objExisteMunDeptoDomFiscal->mun_id;
                                        } else {
                                            $arrErrores = $this->adicionarError($arrErrores, ['El Id del Municipio del Domicilio Fiscal [' . $columnas->codigo_municipio_domicilio_fiscal . '] no existe para el Departamento Del Domicilio Fiscal [' . $columnas->codigo_departamento_domicilio_fiscal . '].'], $fila);
                                        }
                                    }
                                } elseif (!empty($objExistePaisDomFiscal) && $objExistePaisDomFiscal->pai_codigo != 'CO') {
                                    $arrErrores = $this->adicionarError($arrErrores, ['Para los OFEs el País Domicilio Fiscal válido es [CO - COLOMBIA]'], $fila);
                                } elseif (empty($objExistePaisDomFiscal)) {
                                    $arrErrores = $this->adicionarError($arrErrores, ['El Id del País Del Domicilio Fiscal [' . $columnas->codigo_pais_domicilio_fiscal . '] no existe'], $fila);
                                }
                            }
                        }
                        //FIN DE DOMICILIO FISCAL

                        if (array_key_exists($columnas->codigo_tipo_documento, $arrExisteTipoDocumento)) {
                            $objExisteTipoDocumento = $arrExisteTipoDocumento[$columnas->codigo_tipo_documento];
                            $arrOfe['tdo_id'] = $objExisteTipoDocumento->tdo_id;
                        } else {
                            $arrErrores = $this->adicionarError($arrErrores, ['El Código del Tipo de Documento [' . $columnas->codigo_tipo_documento . '], ya no está vigente, no aplica para Documento Electrónico, se encuentra INACTIVO o no existe.'], $fila);
                        }

                        $arrOfe['rfi_id'] = '';
                        if (array_key_exists($columnas->codigo_regimen_fiscal, $arrExisteRegimenFiscal)) {
                            $objExisteRegimenFiscal = $arrExisteRegimenFiscal[$columnas->codigo_regimen_fiscal];
                            $arrOfe['rfi_id'] = $objExisteRegimenFiscal->rfi_id;
                        } else {
                            $arrErrores = $this->adicionarError($arrErrores, ['El Código del Régimen Fiscal [' . $columnas->codigo_regimen_fiscal . '], ya no está vigente, se encuentra INACTIVO o no existe.'], $fila);
                        }

                        $arrOfe['sft_id']    = null;
                        $arrOfe['sft_id_ds'] = null;
                        if(
                            (!isset($columnas->software_proveedor_tecnologico_emision) || (isset($columnas->software_proveedor_tecnologico_emision) && empty($columnas->software_proveedor_tecnologico_emision))) &&
                            (!isset($columnas->software_proveedor_tecnologico_documento_soporte) || (isset($columnas->software_proveedor_tecnologico_documento_soporte) && empty($columnas->software_proveedor_tecnologico_documento_soporte)))
                        ) {
                            $arrErrores = $this->adicionarError($arrErrores, ['Debe ingresar la Identificación del Software Proveedor Tecnológico para Emisión y/o Documento Soporte'], $fila);
                        } else {
                            if (isset($columnas->software_proveedor_tecnologico_emision) && !empty($columnas->software_proveedor_tecnologico_emision) && array_key_exists($columnas->software_proveedor_tecnologico_emision, $arrExisteSft)) {
                                $objExisteSft = $arrExisteSft[$columnas->software_proveedor_tecnologico_emision];
                                $arrOfe['sft_id'] = $objExisteSft->sft_id;
                            } elseif (isset($columnas->software_proveedor_tecnologico_emision) && !empty($columnas->software_proveedor_tecnologico_emision) && !array_key_exists($columnas->software_proveedor_tecnologico_emision, $arrExisteSft)) {
                                $arrErrores = $this->adicionarError($arrErrores, ['La Identificación del Software Proveedor Tecnólogico Emisión [' . $columnas->software_proveedor_tecnologico_emision . '] no existe'], $fila);
                            }

                            if (isset($columnas->software_proveedor_tecnologico_documento_soporte) && !empty($columnas->software_proveedor_tecnologico_documento_soporte) && array_key_exists($columnas->software_proveedor_tecnologico_documento_soporte, $arrExisteSftDs)) {
                                $objExisteSftDs = $arrExisteSftDs[$columnas->software_proveedor_tecnologico_documento_soporte];
                                $arrOfe['sft_id_ds'] = $objExisteSftDs->sft_id;
                            } elseif (isset($columnas->software_proveedor_tecnologico_documento_soporte) && !empty($columnas->software_proveedor_tecnologico_documento_soporte) && !array_key_exists($columnas->software_proveedor_tecnologico_documento_soporte, $arrExisteSftDs)) {
                                $arrErrores = $this->adicionarError($arrErrores, ['La Identificación del Software Proveedor Tecnológico Documento Soporte [' . $columnas->software_proveedor_tecnologico_documento_soporte . '] no existe'], $fila);
                            }
                        }
                        
                        $objExisteToj = $arrExisteTipoOrgJuridica[$columnas->codigo_tipo_organizacion_juridica] ?? null;

                        if (!empty($objExisteToj)) {
                            $arrOfe['toj_id'] = $objExisteToj->toj_id;
                            switch (strtoupper($objExisteToj->toj_codigo)) {
                                //Este case solo se cumple cuando es organizacion JURIDICA.
                                case '1':
                                    $arrFaltantes = $this->checkFields($Acolumnas, [
                                        'razon_social'
                                    ]);
                                    $columnas->nombre_comercial = ($columnas->nombre_comercial == "" ? $columnas->razon_social : $columnas->nombre_comercial);
                                    $columnas->primer_nombre = NULL;
                                    $columnas->primer_apellido = NULL;
                                    $columnas->segundo_apellido = NULL;
                                break;
                                default:
                                    $arrFaltantes = $this->checkFields($Acolumnas, [
                                        'primer_nombre',
                                        'primer_apellido'
                                    ]);

                                    $columnas->razon_social = NULL;
                                    $columnas->nombre_comercial = NULL;
                                    break;
                            }

                            if (count($arrFaltantes) > 0) {
                                $arrErrores = $this->adicionarError($arrErrores, $arrFaltantes);
                            } else {
                                $arrOfe['ofe_razon_social']     = $this->sanitizarStrings($columnas->razon_social);
                                $arrOfe['ofe_nombre_comercial'] = $this->sanitizarStrings($columnas->nombre_comercial);
                                $arrOfe['ofe_primer_apellido']  = $this->sanitizarStrings($columnas->primer_apellido);
                                $arrOfe['ofe_segundo_apellido'] = $this->sanitizarStrings($columnas->segundo_apellido);
                                $arrOfe['ofe_primer_nombre']    = $this->sanitizarStrings($columnas->primer_nombre);
                                $arrOfe['ofe_otros_nombres']    = $this->sanitizarStrings($columnas->otros_nombres);
                            }
                        } else {
                            $arrErrores = $this->adicionarError($arrErrores, ["El cóodigo Tipo Organización Juridica [{$columnas->codigo_tipo_organizacion_juridica}], ya no está vigente, se encuentra INACTIVO o no existe."], $fila);
                        }


                        $arrOfe['ofe_direccion']                            = $this->sanitizarStrings($columnas->direccion);
                        $arrOfe['ofe_direcciones_adicionales']              = ($columnas->direcciones_adicionales != '') ? json_encode(explode('|', $columnas->direcciones_adicionales)) : null;
                        $arrOfe['ofe_direccion_domicilio_fiscal']           = $this->sanitizarStrings($columnas->direccion_domicilio_fiscal);
                        $arrOfe['ofe_matricula_mercantil']                  = (property_exists($columnas, 'matricula_mercantil')) ? $this->sanitizarStrings($columnas->matricula_mercantil) : '';
                        $arrOfe['ofe_actividad_economica']                  = (property_exists($columnas, 'actividad_economica')) ? $this->sanitizarStrings($columnas->actividad_economica) : '';
                        $arrOfe['ofe_nombre_contacto']                      = $this->sanitizarStrings($columnas->nombre_contacto);
                        $arrOfe['ofe_fax']                                  = $this->sanitizarStrings((string)$columnas->fax, 50);
                        $arrOfe['ofe_notas']                                = $this->sanitizarStrings($columnas->notas);
                        $arrOfe['ofe_telefono']                             = $this->sanitizarStrings((string)$columnas->telefono, 50);
                        $arrOfe['ofe_correo']                               = $this->soloEmail($columnas->correo);
                        $arrOfe['ofe_correos_notificacion']                 = $this->soloEmails($columnas->correos_notificacion) != '' ? $this->soloEmails($columnas->correos_notificacion) : null;
                        $arrOfe['ofe_notificacion_un_solo_correo']          = $this->sanitizarStrings((string)$columnas->notificacion_un_solo_correo, 2);
                        $arrOfe['ofe_correos_autorespuesta']                = $this->soloEmails($columnas->correos_autorespuesta) != '' ? $this->soloEmails($columnas->correos_autorespuesta) : null;
                        $arrOfe['ofe_mostrar_seccion_correos_notificacion'] = empty($arrOfe['ofe_correos_notificacion']) ? 'NO' : 'SI';

                        //OPTIONALS
                        $arrOfe['ofe_web']      = (property_exists($columnas, 'web')) ? $this->sanitizarStrings($columnas->web) : '';
                        $arrOfe['ofe_twitter']  = (property_exists($columnas, 'twitter')) ? $this->sanitizarStrings($columnas->twitter) : '';
                        $arrOfe['ofe_facebook'] = (property_exists($columnas, 'facebook')) ? $this->sanitizarStrings($columnas->facebook) : '';

                        if (empty($arrErrores)) {
                            $rules = $this->className::$rules;
                            unset($rules['sft_id'], $rules['sft_id_ds']);

                            $rules['sft_id'] = Rule::requiredIf(function() use ($arrOfe) {
                                return !isset($arrOfe['sft_id_ds']) || (isset($arrOfe['sft_id_ds']) && empty($arrOfe['sft_id_ds']));
                            });

                            $rules['sft_id_ds'] = Rule::requiredIf(function() use ($arrOfe) {
                                return !isset($arrOfe['sft_id']) || (isset($arrOfe['sft_id']) && empty($arrOfe['sft_id']));
                            });

                            $objValidator = Validator::make($arrOfe, $rules);
                            if (!empty($objValidator->errors()->all())) {
                                $arrErrores = $this->adicionarError($arrErrores, $objValidator->errors()->all(), $fila);
                            } else {
                                $arrResultado[] = $arrOfe;
                            }
                        }
                    }
                }
                if ($fila % 500 === 0) {
                    $this->renovarConexion($objUser);
                }
            }

            if (!empty($arrErrores)) {
                $agendamiento = AdoAgendamiento::create([
                    'usu_id'                  => $objUser->usu_id,
                    'age_proceso'             => 'FINALIZADO',
                    'age_cantidad_documentos' => 0,
                    'age_prioridad'           => null,
                    'usuario_creacion'        => $objUser->usu_id,
                    'fecha_creacion'          => date('Y-m-d H:i:s'),
                    'estado'                  => 'ACTIVO',
                ]);

                EtlProcesamientoJson::create([
                    'pjj_tipo'                => ProcesarCargaParametricaCommand::$TYPE_OFE,
                    'pjj_json'                => json_encode([]),
                    'pjj_procesado'           => 'SI',
                    'age_estado_proceso_json' => 'FINALIZADO',
                    'pjj_errores'             => json_encode($arrErrores),
                    'age_id'                  => $agendamiento->age_id,
                    'usuario_creacion'        => $objUser->usu_id,
                    'fecha_creacion'          => date('Y-m-d H:i:s'),
                    'estado'                  => 'ACTIVO'
                ]);

                return response()->json([
                    'message' => 'Errores al guardar los Oferentes',
                    'errors'  => ['Verifique el Log de Errores'],
                ], 400);

            } else {
                $insertOfes = [];

                foreach ($arrResultado as $ofe) {
                    $data = [
                        'ofe_id'                                   => $ofe['ofe_id'],
                        'ofe_identificacion'                       => $ofe['ofe_identificacion'],
                        'ofe_razon_social'                         => !empty($ofe['ofe_razon_social']) ? $this->sanitizarStrings($ofe['ofe_razon_social']) : null,
                        'ofe_nombre_comercial'                     => !empty($ofe['ofe_nombre_comercial']) ? $this->sanitizarStrings($ofe['ofe_nombre_comercial']) : null,
                        'ofe_primer_nombre'                        => !empty($ofe['ofe_primer_nombre']) ? $this->sanitizarStrings($ofe['ofe_primer_nombre']) : null,
                        'ofe_primer_apellido'                      => !empty($ofe['ofe_primer_apellido']) ? $this->sanitizarStrings($ofe['ofe_primer_apellido']) : null,
                        'ofe_segundo_apellido'                     => !empty($ofe['ofe_segundo_apellido']) ? $this->sanitizarStrings($ofe['ofe_segundo_apellido']) : null,
                        'ofe_otros_nombres'                        => !empty($ofe['ofe_otros_nombres']) ? $this->sanitizarStrings($ofe['ofe_otros_nombres']) : null,
                        'tdo_id'                                   => $ofe['tdo_id'],
                        'toj_id'                                   => $ofe['toj_id'],
                        'pai_id'                                   => !empty($ofe['pai_id']) ? $ofe['pai_id'] : null,
                        'dep_id'                                   => !empty($ofe['dep_id']) ? $ofe['dep_id'] : null,
                        'mun_id'                                   => !empty($ofe['mun_id']) ? $ofe['mun_id'] : null,
                        'cpo_id'                                   => !empty($ofe['cpo_id']) ? $this->sanitizarStrings($ofe['cpo_id']) : null,
                        'ofe_direccion'                            => !empty($ofe['ofe_direccion']) ? $ofe['ofe_direccion'] : null,
                        'ofe_direcciones_adicionales'              => !empty($ofe['ofe_direcciones_adicionales']) ? $ofe['ofe_direcciones_adicionales'] : null,
                        'pai_id_domicilio_fiscal'                  => !empty($ofe['pai_id_domicilio_fiscal']) ? $ofe['pai_id_domicilio_fiscal'] : null,
                        'dep_id_domicilio_fiscal'                  => !empty($ofe['dep_id_domicilio_fiscal']) ? $ofe['dep_id_domicilio_fiscal'] : null,
                        'mun_id_domicilio_fiscal'                  => !empty($ofe['mun_id_domicilio_fiscal']) ? $ofe['mun_id_domicilio_fiscal'] : null,
                        'cpo_id_domicilio_fiscal'                  => !empty($ofe['cpo_id_domicilio_fiscal']) ? $this->sanitizarStrings($ofe['cpo_id_domicilio_fiscal']) : null,
                        'ofe_direccion_domicilio_fiscal'           => !empty($ofe['ofe_direccion_domicilio_fiscal']) ? $this->sanitizarStrings($ofe['ofe_direccion_domicilio_fiscal']) : null,
                        'ofe_nombre_contacto'                      => !empty($ofe['ofe_nombre_contacto']) ? $this->sanitizarStrings($ofe['ofe_nombre_contacto']) : null,
                        'ofe_telefono'                             => !empty($ofe['ofe_telefono']) ? $this->sanitizarStrings($ofe['ofe_telefono']) : null,
                        'ofe_fax'                                  => !empty($ofe['ofe_fax']) ? $this->sanitizarStrings($ofe['ofe_fax']) : null,
                        'ofe_correo'                               => $this->sanitizarStrings($ofe['ofe_correo']),
                        'ofe_notas'                                => !empty($ofe['ofe_notas']) ? $this->sanitizarStrings($ofe['ofe_notas']) : null,
                        'ofe_web'                                  => $this->sanitizarStrings($ofe['ofe_web']),
                        'ofe_twitter'                              => $this->sanitizarStrings($ofe['ofe_twitter']),
                        'ofe_facebook'                             => $this->sanitizarStrings($ofe['ofe_facebook']),
                        'rfi_id'                                   => $ofe['rfi_id'],
                        'ref_id'                                   => $ofe['ref_id'],
                        'tributos'                                 => $ofe['tributos'],
                        'ofe_matricula_mercantil'                  => !empty($ofe['ofe_matricula_mercantil']) ? $this->sanitizarStrings($ofe['ofe_matricula_mercantil']) : null,
                        'ofe_actividad_economica'                  => !empty($ofe['ofe_actividad_economica']) ? $this->sanitizarStrings($ofe['ofe_actividad_economica']) : null,
                        'sft_id'                                   => $ofe['sft_id'],
                        'sft_id_ds'                                => $ofe['sft_id_ds'],
                        'ofe_correos_notificacion'                 => $this->soloEmails($ofe['ofe_correos_notificacion']) != '' ? $this->soloEmails($ofe['ofe_correos_notificacion']) : null,
                        'ofe_notificacion_un_solo_correo'          => $ofe['ofe_notificacion_un_solo_correo'],
                        'ofe_correos_autorespuesta'                => $this->soloEmails($ofe['ofe_correos_autorespuesta']) != '' ? $this->soloEmails($ofe['ofe_correos_autorespuesta']) : null,
                        'ofe_mostrar_seccion_correos_notificacion' => $ofe['ofe_mostrar_seccion_correos_notificacion'],
                        'bdd_id_rg'                                => $ofe['bdd_id_rg']
                    ];

                    array_push($insertOfes, $data);
                }

                if (count($insertOfes) > 0) {
                    $bloques = array_chunk($insertOfes, 100);
                    foreach ($bloques as $bloque) {

                        $agendamiento = AdoAgendamiento::create([
                            'usu_id'                  => $objUser->usu_id,
                            'age_proceso'             => ProcesarCargaParametricaCommand::$NOMBRE_COMANDO,
                            'age_cantidad_documentos' => count($bloque),
                            'age_prioridad'           => null,
                            'usuario_creacion'        => $objUser->usu_id,
                            'fecha_creacion'          => date('Y-m-d H:i:s'),
                            'estado'                  => 'ACTIVO',
                        ]);

                        if ($agendamiento) {
                            EtlProcesamientoJson::create([
                                'pjj_tipo'         => ProcesarCargaParametricaCommand::$TYPE_OFE,
                                'pjj_json'         => json_encode($bloque),
                                'pjj_procesado'    => 'NO',
                                'age_id'           => $agendamiento->age_id,
                                'usuario_creacion' => $objUser->usu_id,
                                'fecha_creacion'   => date('Y-m-d H:i:s'),
                                'estado'           => 'ACTIVO'
                            ]);
                        }
                    }
                }

                if (!empty($arrErrores)) {
                    return response()->json([
                        'message' => 'Errores al guardar los Oferentes',
                        'errors' => $arrErrores
                    ], 422);
                } else {
                    return response()->json([
                        'success' => true
                    ], 200);
                }
            }
        } else {
            return response()->json([
                'message' => 'Errores al guardar los Oferentes',
                'errors' => ['No se ha subido ningún archivo.']
            ], 400);
        }
    }

    /**
     * Obtiene la lista de errores de procesamiento de cargas masivas de oferentes.
     *
     * @param Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     * @throws \Exception
     */
    public function getListaErroresOfe()
    {
        return $this->getListaErrores(ProcesarCargaParametricaCommand::$TYPE_OFE);
    }

    /*
     * Retorna una lista en Excel de errores de cargue de oferentes.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function descargarListaErroresOfe()
    {
        return $this->getListaErrores(ProcesarCargaParametricaCommand::$TYPE_OFE, true, 'carga_oferentes_log_errores');
    }

    /**
     * Controla la creacion o eliminacion de usuarios de ofes para cargas masivas que son gestionadas por medio de etlWeb.
     *
     * @param $nuevosUsuarios
     * @param $eliminarUsuarios
     */
    private function usuariosOfeWeb($nuevosUsuarios, $eliminarUsuarios, $ofe)
    {
        $interoperabilidad = new InteroperabilidadController();
        $registros = [];

        // Se chequea si el arreglo de nuevos usuarios posee valores
        if ($nuevosUsuarios !== null && count($nuevosUsuarios) > 0) {
            User::where(function ($query) use ($nuevosUsuarios, $ofe) {
                // se obtiene los usuarios ubicandolos por su email
                foreach ($nuevosUsuarios as $item) {
                    $query->orWhere('usu_email', 'like', $item->usu_email);
                }
            })
                ->get()
                ->map(function ($usuario) use (&$registros, $ofe) {
                    $registros[] = [
                        'usu_email' => trim($usuario->usu_email),
                        'usu_identificacion' => trim($usuario->usu_identificacion),
                        'usu_nombre' => trim($usuario->usu_nombre),
                        'usu_direccion' => trim($usuario->usu_direccion),
                        'usu_telefono' => trim($usuario->usu_telefono),
                        'usu_movil' => trim($usuario->usu_movil),
                        'usu_password' => str_random(8),
                        'ofes' => $ofe,
                        'action' => 'new'
                    ];
                });
        }

        // Se chequea si el arreglo de eliminar usuarios posee valores
        if ($eliminarUsuarios !== null && count($eliminarUsuarios) > 0) {
            User::where(function ($query) use ($eliminarUsuarios, $ofe) {
                // se obtiene los usuarios ubicandolos por su email
                foreach ($eliminarUsuarios as $item) {
                    $query->orWhere('usu_email', 'like', $item->usu_email);
                }
            })
                ->get()
                ->map(function ($usuario) use (&$registros, $ofe) {
                    $registros[] = [
                        'usu_email' => trim($usuario->usu_email),
                        'usu_identificacion' => trim($usuario->usu_identificacion),
                        'usu_nombre' => trim($usuario->usu_nombre),
                        'usu_direccion' => trim($usuario->usu_direccion),
                        'usu_telefono' => trim($usuario->usu_telefono),
                        'usu_movil' => trim($usuario->usu_movil),
                        'ofes' => $ofe,
                        'action' => 'delete'
                    ];
                });
        }

        // Existen registros por crear o eliminar
        if (count($registros) > 0) {
            $r = $interoperabilidad->manager2ConRetorno(null, 'POST',
                'gestionar-usuarios-ofe', ['registros' => $registros]);
            $respuesta = $r['respuesta'];
        }
    }

    /**
     * Busca los oferentes en base a un criterio predictivo.
     *
     * @param [type] $valorBuscar
     * @return Response
     */
    public function buscarOfeNgSelect($valorBuscar)
    {
        $comodin = "'%$valorBuscar%'";
        $sqlRaw = "(ofe_razon_social LIKE $comodin OR ofe_nombre_comercial LIKE $comodin OR CONCAT(ofe_primer_nombre, ' ', ofe_otros_nombres, ' ', ofe_primer_apellido, ' ', ofe_segundo_apellido) LIKE $comodin)";
        $oferentes = $this->className::where('estado', 'ACTIVO')
            ->whereRaw($sqlRaw)
            ->select(['ofe_id', 'ofe_identificacion',
                \DB::raw('IF(ofe_razon_social IS NULL OR ofe_razon_social = "", CONCAT(COALESCE(ofe_primer_nombre, ""), " ", COALESCE(ofe_otros_nombres, ""), " ", COALESCE(ofe_primer_apellido, ""), " ", COALESCE(ofe_segundo_apellido, "")), ofe_razon_social) as ofe_razon_social')])
            ->get();

        return response()->json([
            'data' => $oferentes
        ], 200);
    }

    /**
     * Almacena la imagen del logo del OFE.
     *
     * @param Illuminate\Http\Request $request
     * @param string $campoLogoRequest Campo del request en donde llega el logo
     * @param bool $integracionCadisoft Indica si se trata de una integración de Cadisfot
     * @return array
     */
    private function almacenarLogo($request, $campoLogoRequest = 'logo', $integracionCadisoft = false) {
        if (
            (isset($request->{$campoLogoRequest}) && !empty($request->{$campoLogoRequest})) ||
            (isset($request->ofe_cadisoft_activo) && $request->ofe_cadisoft_activo == 'SI')
        ) {
            if($request->hasFile($campoLogoRequest)){
                MainTrait::setFilesystemsInfo();
                $disk = config('variables_sistema.ETL_LOGOS_STORAGE');
                $bdd = (!empty(auth()->user()->bdd_id_rg)) ? auth()->user()->getBaseDatosRg->bdd_nombre : auth()->user()->getBaseDatos->bdd_nombre;
                $bdd = str_replace(config('variables_sistema.PREFIJO_BASE_DATOS'), 'etl_', $bdd);
                $logoSubido = $request->file($campoLogoRequest);
                $extension = $logoSubido->getClientOriginalExtension();
                $validadorLogo = Validator::make([$campoLogoRequest => $request->file($campoLogoRequest)], [
                    "logo" => [
                        function ($attribute, $value, $fail) {
                            try {
                                list($width, $heigth) = getimagesize($value->getRealPath());
                            } catch (\Exception $e) {
                                $width = $width ?? null;
                                $heigth = $heigth ?? null;
                            }

                            if ($value->getType() != 'file' && empty($width)) {
                                $fail('El logo no es una imagen.');
                            }

                            if ($width >= 200 && $width < 1){
                                $fail('El logo no cumple con el ancho correcto de la imagen.');
                            }

                            if ($heigth >= 150 && $heigth < 1){
                                $fail('El logo no cumple con el alto correcto de la imagen.');
                            }
                        },
                    ]
                ]);
                
                if ($validadorLogo->fails()) {
                    return [
                        'errores' => $validadorLogo->errors()->all(),
                        'codigo' => 400
                    ];
                }

                try {
                    $sufijo       = ($request->filled('tipoConfiguracion')) ? '_ds' : '';
                    $files        = Storage::disk($disk)->files($bdd . '/'. $request->ofe_identificacion . '/assets');
                    $fileNoDelete = ($sufijo === '_ds') ? 'logo' . $request->ofe_identificacion . '.png' : 'logo' . $request->ofe_identificacion . '_ds.png';
                    foreach ($files as $file) {
                        $arrFile = explode('/', $file);
                        if(!in_array($fileNoDelete, $arrFile))
                            Storage::disk($disk)->delete($file);
                    }
                    Storage::disk($disk)->put($bdd . '/'. $request->ofe_identificacion . '/assets/logo' . $request->ofe_identificacion . $sufijo . '.' . 'png', file_get_contents($logoSubido->getRealPath()));
                } catch (\Exception $e) {
                    return [
                        'errores' => ['Ocurrió un error al intentar guardar la imagen del logo'],
                        'codigo' => 422
                    ];
                }
            } else {
                if(isset($request->ofe_cadisoft_activo) && $request->ofe_cadisoft_activo == 'SI') {
                    return [
                        'errores' => ['Se trata de una integración de Cadisoft por lo que debe subir una imagen para el logo'],
                        'codigo' => 422
                    ];
                }
            }
        } else {
            if(!isset($request->{$campoLogoRequest}) && empty($request->{$campoLogoRequest})) {
                try {
                    MainTrait::setFilesystemsInfo();
                    $userAuth = auth()->user();
                    $disk = config('variables_sistema.ETL_LOGOS_STORAGE');
                    $bdd = (!empty($userAuth->bdd_id_rg)) ? $userAuth->getBaseDatosRg->bdd_nombre : $userAuth->getBaseDatos->bdd_nombre;
                    $bdd = str_replace(config('variables_sistema.PREFIJO_BASE_DATOS'), 'etl_', $bdd);
                    $sufijo     = ($request->filled('tipoConfiguracion')) ? '_ds' : '';
                    $files      = Storage::disk($disk)->files($bdd . '/'. $request->ofe_identificacion . '/assets');
                    $fileDelete = 'logo' . $request->ofe_identificacion . $sufijo .'.png';
                    foreach ($files as $file) {
                        $arrFile = explode('/', $file);
                        if(in_array($fileDelete, $arrFile))
                            Storage::disk($disk)->delete($file);
                    }
                } catch (\Exception $e) {
                    return [
                        'errores' => ['Ocurrió un error al eliminar el logo'],
                        'codigo' => 422
                    ];
                }
            }
        }

        return [
            'errores' => null,
            'codigo' => null
        ];
    }

    /**
     * Efectua un proceso de busqueda en la parametrica
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function busqueda(Request $request) {
        $columnas = [
            'ofe_id',
            'sft_id',
            'tdo_id',
            'toj_id',
            'ofe_identificacion',
            'ofe_razon_social',
            'ofe_nombre_comercial',
            'ofe_primer_apellido',
            'ofe_segundo_apellido',
            'ofe_primer_nombre',
            'ofe_otros_nombres',
            \DB::raw('IF(ofe_razon_social IS NULL OR ofe_razon_social = "", CONCAT(COALESCE(ofe_primer_nombre, ""), " ", COALESCE(ofe_otros_nombres, ""), " ", COALESCE(ofe_primer_apellido, ""), " ", COALESCE(ofe_segundo_apellido, "")), ofe_razon_social) as nombre_completo'),
            'pai_id',
            'dep_id',
            'mun_id',
            'cpo_id',
            'ofe_direccion',
            'pai_id_domicilio_fiscal',
            'dep_id_domicilio_fiscal',
            'mun_id_domicilio_fiscal',
            'cpo_id_domicilio_fiscal',
            'ofe_direccion_domicilio_fiscal',
            'ofe_nombre_contacto',
            'ofe_fax',
            'ofe_notas',
            'ofe_telefono',
            'ofe_web',
            'ofe_correo',
            'ofe_twitter',
            'ofe_facebook',
            'rfi_id',
            'ref_id',
            'ofe_matricula_mercantil',
            'ofe_actividad_economica',
            'ofe_correos_notificacion',
            'ofe_notificacion_un_solo_correo',
            'ofe_correos_autorespuesta',
            'ofe_prioridad_agendamiento',
            'usuario_creacion',
            'fecha_modificacion',
            'estado'
        ];

        $incluir = [
            'getUsuarioCreacion',
            'getParametrosDepartamento',
            'getParametrosMunicipio',
            'getParametrosPais',
            'getConfiguracionSoftwareProveedorTecnologico',
            'getConfiguracionSoftwareProveedorTecnologicoDs',
            'getConfiguracionObservacionesGeneralesFactura',
            'getContactos',
            'getAdquirentes',
            'getParametrosRegimenFiscal',
            'getResolucionesFacturacion',
            'getParametroDomicilioFiscalPais',
            'getParametroDomicilioFiscalDepartamento',
            'getParametroDomicilioFiscalMunicipio',
            'getTipoOrganizacionJuridica',
            'getResponsabilidadFiscal',
            'getTiempoAceptacionTacita',
            'getTributos:tri_id,ofe_id',
            'getTributos.getDetalleTributo:tri_id,tri_codigo,tri_nombre,tri_descripcion',
            'getCodigoPostal',
            'getCodigoPostalDomicilioFiscal',
            'getParametroTipoDocumento'
        ];
        $resultado = $this->procesadorBusqueda($request, $columnas, $incluir);
        $resultado = json_decode($resultado->getContent(), true);
        
        return response()->json([
            'data' => $resultado['data']
        ], Response::HTTP_OK);
    }

    /**
     * Procesa información relacionada con la configuración de documento soporte de un OFE.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function configuracionDocumentoSoporte(Request $request) {
        $request->merge([
            'tipoConfiguracion' => 'DS'
        ]);
        return $this->configuracionDocumentoElectronico($request);
    }

    /**
     * Procesa información relacionada con la configuración de documento electrónico de un OFE.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function configuracionDocumentoElectronico(Request $request) {
        $arrErrores = [];

        // Consulta el OFE
        $ofe = ConfiguracionObligadoFacturarElectronicamente::where('ofe_identificacion', $request->ofe_identificacion)
            ->validarAsociacionBaseDatos()
            ->first();

        if(!$ofe) {
            return response()->json([
                'message' => 'Errores al procesar la información',
                'errors' => ['El OFE con identificación [' . $request->ofe_identificacion . '] no existe.']
            ], Response::HTTP_CONFLICT);
        }

        $resultadoAlmacenarLogo = $this->almacenarLogo($request);
        if ($resultadoAlmacenarLogo['errores']) {
            return response()->json([
                'message' => 'Errores al crear el OFE',
                'errors' => $resultadoAlmacenarLogo['errores']
            ], $resultadoAlmacenarLogo['codigo']);
        }

        $camposPersonalizados = [];
        if ($request->has('aplica_sector_salud') && !is_null($request->aplica_sector_salud) && !empty($request->aplica_sector_salud) && !$request->filled('tipoConfiguracion')) {
            $camposPersonalizados['aplica_sector_salud'] = $request->aplica_sector_salud;
        }

        $sufijo = '';
        if($request->filled('tipoConfiguracion') && $request->tipoConfiguracion === 'DS')
            $sufijo = '_ds';

        if ($request->has('valores_personalizados') && !is_null($request->valores_personalizados) && !empty($request->valores_personalizados)) {
            $camposPersonalizados['valores_personalizados'.$sufijo] = json_decode($request->valores_personalizados);
            $arrCampos = [];
            foreach ($camposPersonalizados['valores_personalizados'.$sufijo] as $key => $campo) {
                if (in_array(mb_strtoupper($campo->campo), MainTrait::$columnasDefault) || in_array(mb_strtoupper($campo->campo), MainTrait::$columnasItemDefault) || in_array(mb_strtoupper($campo->campo), MainTrait::$columnasAdicionalesDefault)) {
                    $arrErrores[] = 'El campo personalizado [' . $campo->campo . '] es una columna reservada del sistema.';
                }

                if (in_array(strtolower($this->sanear_string($campo->campo)), $arrCampos)) {
                    $arrErrores[] = 'El campo personalizado [' . $campo->campo . '] ya existe en las filas anteriores.';
                }
                $arrCampos[] = strtolower($this->sanear_string($campo->campo));

                if ($campo->tipo != '' && $campo->campo == '') {
                    $arrErrores[] = 'El campo personalizado no puede ser vacío.';
                }

                if ($campo->tipo == 'multiple' && count($campo->opciones) <= 1) {
                    $arrErrores[] = 'El campo personalizado [' . $campo->campo . '] es de selección múltiple y debe tener más de una opción.';
                }

                if ($campo->tipo == 'texto' && $campo->longitud != '' && !preg_match('/^\d+$/', $campo->longitud)) {
                    $arrErrores[] = 'La longitud del campo personalizado [' . $campo->campo . '] debe ser entera.';
                }

                if ($campo->tipo == 'numerico' && !preg_match('/^\d{1,9}(\.\d{1,9})$/', $campo->longitud)) {
                    $arrErrores[] = 'La longitud del campo personalizado [' . $campo->campo . '] debe ser decimal.';
                }

                if ($campo->tipo == 'por_defecto') {
                    if ($campo->opciones == '') {
                        $arrErrores[] = 'El valor por defecto del campo personalizado [' . $campo->campo . '] no puede ser vacío.';
                    }

                    if ($campo->longitud != '' && !preg_match('/^\d+$/', $campo->longitud)) {
                        $arrErrores[] = 'La longitud del campo personalizado [' . $campo->campo . '] debe ser entera.';
                    }
                }
            }
        }

        if ($request->has('valores_personalizados_item') && !is_null($request->valores_personalizados_item) && !empty($request->valores_personalizados_item)) {
            $camposPersonalizados['valores_personalizados_item'.$sufijo] = json_decode($request->valores_personalizados_item);
            $arrCamposItem = [];
            foreach ($camposPersonalizados['valores_personalizados_item'.$sufijo] as $key => $campo) {
                if (in_array(mb_strtoupper($campo->campo), MainTrait::$columnasDefault) || in_array(mb_strtoupper($campo->campo), MainTrait::$columnasItemDefault) || in_array(mb_strtoupper($campo->campo), MainTrait::$columnasAdicionalesDefault)) {
                    $arrErrores[] = 'El campo personalizado ítem [' . $campo->campo . '] es una columna reservada del sistema.';
                }

                if (in_array(strtolower($this->sanear_string($campo->campo)), $arrCamposItem)) {
                    $arrErrores[] = 'El campo personalizado ítem [' . $campo->campo . '] ya existe en las filas anteriores.';
                }
                $arrCamposItem[] = strtolower($this->sanear_string($campo->campo));

                if ($campo->tipo != '' && $campo->campo == '') {
                    $arrErrores[] = 'El campo personalizado ítem no puede ser vacío.';
                }

                if ($campo->tipo == 'multiple' && count($campo->opciones) <= 1) {
                    $arrErrores[] = 'El campo personalizado ítem [' . $campo->campo . '] es de selección múltiple y debe tener más de una opción.';
                }

                if ($campo->tipo == 'texto' && $campo->longitud != '' && !preg_match('/^\d+$/', $campo->longitud)) {
                    $arrErrores[] = 'La longitud del campo personalizado ítem [' . $campo->campo . '] debe ser entera.';
                }

                if ($campo->tipo == 'numerico' && !preg_match('/^\d{1,9}(\.\d{1,9})$/', $campo->longitud)) {
                    $arrErrores[] = 'La longitud del campo personalizado ítem [' . $campo->campo . '] debe ser decimal.';
                }

                if ($campo->tipo == 'por_defecto') {
                    if ($campo->opciones == '') {
                        $arrErrores[] = 'El valor por defecto del campo personalizado ítem [' . $campo->campo . '] no puede ser vacío.';
                    }

                    if ($campo->longitud != '' && !preg_match('/^\d+$/', $campo->longitud)) {
                        $arrErrores[] = 'La longitud del campo personalizado ítem [' . $campo->campo . '] debe ser entera.';
                    }
                }
            }
        }

        if ($request->has('valores_resumen') && !is_null($request->valores_resumen) && !empty($request->valores_resumen)) {
            $camposPersonalizados['valores_resumen'.$sufijo] = explode(',', $request->valores_resumen);
        }

        if ($request->has('cargos_cabecera_personalizados') && !is_null($request->cargos_cabecera_personalizados) && !empty($request->cargos_cabecera_personalizados)) {
            $camposPersonalizados['cargos_cabecera_personalizados'.$sufijo] = explode(',', $request->cargos_cabecera_personalizados);

            foreach ($camposPersonalizados['cargos_cabecera_personalizados'.$sufijo] as $campo) {
                if (in_array($campo, MainTrait::$columnasDefault) || in_array($campo, MainTrait::$columnasItemDefault) || in_array($campo, MainTrait::$columnasAdicionalesDefault)) {
                    $arrErrores[] = 'El campo personalizado cabecera [' . $campo . '] es una columna reservada del sistema.';
                }
            }
        }

        if ($request->has('descuentos_cabecera_personalizados') && !is_null($request->descuentos_cabecera_personalizados) && !empty($request->descuentos_cabecera_personalizados)) {
            $camposPersonalizados['descuentos_cabecera_personalizados'.$sufijo] = explode(',', $request->descuentos_cabecera_personalizados);

            foreach ($camposPersonalizados['descuentos_cabecera_personalizados'.$sufijo] as $campo) {
                if (in_array($campo, MainTrait::$columnasDefault) || in_array($campo, MainTrait::$columnasItemDefault) || in_array($campo, MainTrait::$columnasAdicionalesDefault)) {
                    $arrErrores[] = 'El campo personalizado descuento [' . $campo . '] es una columna reservada del sistema.';
                }
            }
        }

        if ($request->has('cargos_items_personalizados') && !is_null($request->cargos_items_personalizados) && !empty($request->cargos_items_personalizados)) {
            $camposPersonalizados['cargos_items_personalizados'.$sufijo] = explode(',', $request->cargos_items_personalizados);
            
            foreach ($camposPersonalizados['cargos_items_personalizados'.$sufijo] as $campo) {
                if (in_array($campo, MainTrait::$columnasDefault) || in_array($campo, MainTrait::$columnasItemDefault) || in_array($campo, MainTrait::$columnasAdicionalesDefault)) {
                    $arrErrores[] = 'El campo personalizado cargos ítem [' . $campo . '] es una columna reservada del sistema.';
                }
            }
        }

        if ($request->has('descuentos_items_personalizados') && !is_null($request->descuentos_items_personalizados) && !empty($request->descuentos_items_personalizados)) {
            $camposPersonalizados['descuentos_items_personalizados'.$sufijo] = explode(',', $request->descuentos_items_personalizados);

            foreach ($camposPersonalizados['descuentos_items_personalizados'.$sufijo] as $campo) {
                if (in_array($campo, MainTrait::$columnasDefault) || in_array($campo, MainTrait::$columnasItemDefault) || in_array($campo, MainTrait::$columnasAdicionalesDefault)) {
                    $arrErrores[] = 'El campo personalizado descuentos ítem [' . $campo . '] es una columna reservada del sistema.';
                }
            }
        }

        if ($request->has('encabezado') && !is_null($request->encabezado) && !empty($request->encabezado)) {
            $camposPersonalizados['encabezado'.$sufijo] = $request->encabezado;
        }

        if ($request->has('piePagina') && !is_null($request->piePagina) && !empty($request->piePagina)) {
            $camposPersonalizados['pie'.$sufijo] = $request->piePagina;
        }

        $camposPersonalizadosActual = $ofe->ofe_campos_personalizados_factura_generica;
        $arrCampos = [
            'aplica_sector_salud',
            'valores_personalizados'.$sufijo,
            'valores_personalizados_item'.$sufijo,
            'valores_resumen'.$sufijo,
            'cargos_cabecera_personalizados'.$sufijo,
            'descuentos_cabecera_personalizados'.$sufijo,
            'cargos_items_personalizados'.$sufijo,
            'descuentos_items_personalizados'.$sufijo,
            'encabezado'.$sufijo,
            'pie'.$sufijo
        ];
        if($sufijo === '_ds')
            unset($arrCampos['aplica_sector_salud']);

        foreach ($arrCampos as $campo) {
            if(array_key_exists($campo, $camposPersonalizados))
                $camposPersonalizadosActual[$campo] = $camposPersonalizados[$campo];
            else
                unset($camposPersonalizadosActual[$campo]);
        }

        if(!empty($camposPersonalizadosActual)) {
            $data['ofe_campos_personalizados_factura_generica'] = $camposPersonalizadosActual;
        } else {
            $data['ofe_campos_personalizados_factura_generica'] = null;
        }

        if (!empty($arrErrores)) {
            return response()->json([
                'message' => 'Errores al procesar la información',
                'errors' => $arrErrores
            ], Response::HTTP_CONFLICT);
        }

        // Se debe actualizar la columna ofe_columnas_personalizadas con los valores recibidos en valores_resumen y valores_personalizados
        $columnasPersonalizadas = $this->actualizarColumnasPersonalizadas($ofe->ofe_columnas_personalizadas, $camposPersonalizadosActual, $sufijo);

        if(!empty($columnasPersonalizadas)) {
            $data['ofe_columnas_personalizadas'] = $columnasPersonalizadas;
        } else {
            $data['ofe_columnas_personalizadas'] = null;
        }

        $data['ofe_tiene_representacion_grafica_personalizada'.$sufijo] = ($request->representacion_grafica_estandar == 'SI') ? 'NO' : 'SI';

        $ofe->update($data);

        return response()->json([
            'message' => 'Información actualizada'
        ], Response::HTTP_OK);
    }

    /**
     * Procesa información relacionada con los datos de documentos manuales.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function datosFacturacionWeb(Request $request) {
        $arrErrores = [];

        // Consulta el OFE
        $ofe = ConfiguracionObligadoFacturarElectronicamente::where('ofe_identificacion', $request->ofe_identificacion)
            ->validarAsociacionBaseDatos()
            ->first();

        if(!$ofe) {
            return response()->json([
                'message' => 'Errores al procesar la información',
                'errors' => ['El OFE con identificación [' . $request->ofe_identificacion . '] no existe.']
            ], Response::HTTP_CONFLICT);
        }

        // Se realiza la validación de los parámetros por defecto
        $arrDatosParametricos = json_decode($request->ofe_datos_documentos_manuales);

        foreach ($arrDatosParametricos as $datosParametricos) {
            if ($datosParametricos->tabla != '' && $datosParametricos->valor != '') {

                $tdoAplicaPara = 'FC';
                if ($datosParametricos->tabla == 'etl_tipos_operacion') {
                    $tipoNotaCredito = strpos($datosParametricos->descripcion, 'Nota Crédito');
                    $tipoNotaDebito  = strpos($datosParametricos->descripcion, 'Nota Débito');

                    if ($tipoNotaCredito !== false)
                        $tdoAplicaPara = 'NC';

                    if ($tipoNotaDebito !== false) 
                        $tdoAplicaPara = 'ND';
                }

                $commonsController = new CommonsController();
                $requestCommons = new Request();
                $requestCommons->merge([
                    'tabla'           => $datosParametricos->tabla,
                    'campo'           => $datosParametricos->tabla == 'etl_resoluciones_facturacion' ? 'rfa_id' : $datosParametricos->campo,
                    'valor'           => $datosParametricos->valor,
                    'ofe_id'          => $datosParametricos->tabla == 'etl_resoluciones_facturacion' ? $ofe->ofe_id : '',
                    'top_aplica_para' => $datosParametricos->tabla == 'etl_tipos_operacion' ? $tdoAplicaPara : '',
                    'editar'          => false
                ]);
                $consulta  = $commonsController->searchParametricasExacto($requestCommons, false);
                $resultado = json_decode((string)$consulta->getContent(), true);

                switch ($datosParametricos->tabla) {
                    case "etl_tipos_documentos_electronicos":
                        if(empty($resultado['data']))
                            $arrErrores[] = "El código de tipo de documento electrónico [" . $datosParametricos->valor . "], ya no está vigente, se encuentra INACTIVO o no existe.";
                        break;
                    case "etl_tipos_operacion":
                        if(empty($resultado['data']))
                            $arrErrores[] = "El código de tipo de operación [" . $datosParametricos->valor . "], ya no está vigente, se encuentra INACTIVO o no existe.";
                        break;
                    case "etl_resoluciones_facturacion":
                        if(empty($resultado['data']))
                            $arrErrores[] = "La resolución de facturación se encuentra INACTIVA o no existe.";
                        break;
                    case "etl_formas_pago":
                        if(empty($resultado['data']))
                            $arrErrores[] = "El código de forma de pago [" . $datosParametricos->valor . "], ya no está vigente, se encuentra INACTIVA o no existe.";
                        break;
                    case "etl_medios_pago":
                        if(empty($resultado['data']))
                            $arrErrores[] = "El código de medio de pago [" . $datosParametricos->valor . "], ya no está vigente, se encuentra INACTIVO o no existe.";
                        break;
                    case "etl_monedas":
                        if(empty($resultado['data']))
                            $arrErrores[] = "El código de la moneda [" . $datosParametricos->valor . "], ya no está vigente, se encuentra INACTIVO o no existe.";
                        break;
                }
            }
        }

        if (!empty($arrErrores)) {
            return response()->json([
                'message' => 'Errores al actualizar el valor por defecto',
                'errors' => $arrErrores
            ], 422);
        }

        if ($request->has('ofe_datos_documentos_manuales') && !is_null($request->ofe_datos_documentos_manuales) && !empty($request->ofe_datos_documentos_manuales))
            $ofe->update([
                'ofe_datos_documentos_manuales' => $request->ofe_datos_documentos_manuales
            ]);

        return response()->json([
            'message' => 'Información actualizada'
        ], Response::HTTP_OK);
    }

    /**
     * Actualiza la información de columnas personalizadas.
     *
     * @param array $columnasPersonalizadas Array de columnas personalizadas del OFE
     * @param array $nuevasColumnas Array de columnas que se agregarán
     * @return array $columnasRetorno Array de columnas resultante
     */
    private function actualizarColumnasPersonalizadas($columnasPersonalizadas, $nuevasColumnas, $sufijo) {
        $columnasCabecera      = [];
        $columnasDetalle       = [];
        $columnasCabeceraNotas = [];
        $columnasDetalleNotas  = [];

        if(array_key_exists('valores_resumen'.$sufijo, $nuevasColumnas) && !empty($nuevasColumnas['valores_resumen'.$sufijo])) {
            $valoresResumen        = $this->procesarValoresResumenOfe($nuevasColumnas, $sufijo);
            $columnasCabecera      = array_merge($valoresResumen['cabecera'], $columnasCabecera);
            $columnasDetalle       = array_merge($valoresResumen['detalle'], $columnasDetalle);
            $columnasCabeceraNotas = array_merge($valoresResumen['cabecera'], $columnasCabeceraNotas);
            $columnasDetalleNotas  = array_merge($valoresResumen['detalle'], $columnasDetalleNotas);
        }

        if(array_key_exists('valores_personalizados'.$sufijo, $nuevasColumnas) && !empty($nuevasColumnas['valores_personalizados'.$sufijo])) {
            foreach ($nuevasColumnas['valores_personalizados'.$sufijo] as $key => $campo) {
                $columnasCabecera[]      = mb_strtoupper($campo->campo);
                $columnasCabeceraNotas[] = mb_strtoupper($campo->campo);
            }
        }

        if(array_key_exists('valores_personalizados_item'.$sufijo, $nuevasColumnas) && !empty($nuevasColumnas['valores_personalizados_item'.$sufijo])) {
            foreach ($nuevasColumnas['valores_personalizados_item'.$sufijo] as $key => $campo) {
                $columnasDetalle[]      = mb_strtoupper($campo->campo);
                $columnasDetalleNotas[] = mb_strtoupper($campo->campo);
            }
        }

        if($sufijo === '_ds') {
            $columnasRetorno = [
                'DS' => [
                    'cabecera' => array_values(array_unique($columnasCabecera)),
                    'detalle' => array_values(array_unique($columnasDetalle))
                ]
            ];
            $indices = ['DS'];
        } else {
            $columnasRetorno = [
                'FC' => [
                    'cabecera' => array_values(array_unique($columnasCabecera)),
                    'detalle' => array_values(array_unique($columnasDetalle))
                ],
                'NC-ND' => [
                    'cabecera' => array_values(array_unique($columnasCabeceraNotas)),
                    'detalle' => array_values(array_unique($columnasDetalleNotas))
                ]
            ];
            $indices = ['FC','NC-ND'];
        }

        if(is_array($columnasPersonalizadas)) {
            foreach($columnasPersonalizadas as $indice => $valores) {
                if(!in_array($indice, $indices)) {
                    $columnasRetorno[$indice] = $valores;
                }
            }
        }

        return $columnasRetorno;
    }

    /**
     * Procesa un array de valores resumen de un OFE para definir que columnas deben agregarse a cabecera o detalle de un documento electrónico.
     *
     * @param array $valoresOpcionalesRG Array con valores opcionales para RG
     * @return array $arrColumnas Array con las columnas a agregar en cabecera o detalle
     */
    private function procesarValoresResumenOfe($valoresOpcionalesRG, $sufijo) {
        $arrColumnas['cabecera'] = [];
        $arrColumnas['detalle'] = [];

        // CABECERA
        if(in_array('anticipos', $valoresOpcionalesRG['valores_resumen'.$sufijo])) {
            $arrColumnas['cabecera'][] = 'ANTICIPO IDENTIFICADO PAGO';
            $arrColumnas['cabecera'][] = 'ANTICIPO VALOR';
            $arrColumnas['cabecera'][] = 'ANTICIPO VALOR MONEDA EXTRANJERA';
            $arrColumnas['cabecera'][] = 'ANTICIPO FECHA RECIBIO';
        }

        if(in_array('cargos-a-nivel-documento', $valoresOpcionalesRG['valores_resumen'.$sufijo])) {
            if(array_key_exists('cargos_cabecera_personalizados', $valoresOpcionalesRG) && !empty($valoresOpcionalesRG['cargos_cabecera_personalizados'.$sufijo])) {
                foreach($valoresOpcionalesRG['cargos_cabecera_personalizados'.$sufijo] as $nombreCampo) {
                    $arrColumnas['cabecera'][] = 'CARGO ' . mb_strtoupper($nombreCampo, "UTF-8");
                    $arrColumnas['cabecera'][] = 'CARGO ' . mb_strtoupper($nombreCampo, "UTF-8") . ' PORCENTAJE';
                    $arrColumnas['cabecera'][] = 'CARGO ' . mb_strtoupper($nombreCampo, "UTF-8") . ' BASE';
                    $arrColumnas['cabecera'][] = 'CARGO ' . mb_strtoupper($nombreCampo, "UTF-8") . ' VALOR';
                    $arrColumnas['cabecera'][] = 'CARGO ' . mb_strtoupper($nombreCampo, "UTF-8") . ' BASE MONEDA EXTRANJERA';
                    $arrColumnas['cabecera'][] = 'CARGO ' . mb_strtoupper($nombreCampo, "UTF-8") . ' VALOR MONEDA EXTRANJERA';
                }
            } else {
                $arrColumnas['cabecera'][] = 'CARGO DESCRIPCION';
                $arrColumnas['cabecera'][] = 'CARGO PORCENTAJE';
                $arrColumnas['cabecera'][] = 'CARGO BASE';
                $arrColumnas['cabecera'][] = 'CARGO VALOR';
                $arrColumnas['cabecera'][] = 'CARGO BASE MONEDA EXTRANJERA';
                $arrColumnas['cabecera'][] = 'CARGO VALOR MONEDA EXTRANJERA';
            }
        }

        if(in_array('descuentos-a-nivel-documento', $valoresOpcionalesRG['valores_resumen'.$sufijo])) {
            if(array_key_exists('descuentos_cabecera_personalizados', $valoresOpcionalesRG) && !empty($valoresOpcionalesRG['descuentos_cabecera_personalizados'.$sufijo])) {
                foreach($valoresOpcionalesRG['descuentos_cabecera_personalizados'.$sufijo] as $nombreCampo) {
                    $arrColumnas['cabecera'][] = 'DESCUENTO ' . mb_strtoupper($nombreCampo, "UTF-8");
                    $arrColumnas['cabecera'][] = 'DESCUENTO ' . mb_strtoupper($nombreCampo, "UTF-8") . ' CODIGO';
                    $arrColumnas['cabecera'][] = 'DESCUENTO ' . mb_strtoupper($nombreCampo, "UTF-8") . ' PORCENTAJE';
                    $arrColumnas['cabecera'][] = 'DESCUENTO ' . mb_strtoupper($nombreCampo, "UTF-8") . ' BASE';
                    $arrColumnas['cabecera'][] = 'DESCUENTO ' . mb_strtoupper($nombreCampo, "UTF-8") . ' VALOR';
                    $arrColumnas['cabecera'][] = 'DESCUENTO ' . mb_strtoupper($nombreCampo, "UTF-8") . ' BASE MONEDA EXTRANJERA';
                    $arrColumnas['cabecera'][] = 'DESCUENTO ' . mb_strtoupper($nombreCampo, "UTF-8") . ' VALOR MONEDA EXTRANJERA';
                }
            } else {
                $arrColumnas['cabecera'][] = 'DESCUENTO CODIGO';
                $arrColumnas['cabecera'][] = 'DESCUENTO DESCRIPCION';
                $arrColumnas['cabecera'][] = 'DESCUENTO PORCENTAJE';
                $arrColumnas['cabecera'][] = 'DESCUENTO BASE';
                $arrColumnas['cabecera'][] = 'DESCUENTO VALOR';
                $arrColumnas['cabecera'][] = 'DESCUENTO BASE MONEDA EXTRANJERA';
                $arrColumnas['cabecera'][] = 'DESCUENTO VALOR MONEDA EXTRANJERA';
            }
        }

        if(in_array('reteica-a-nivel-documento', $valoresOpcionalesRG['valores_resumen'.$sufijo])) {
            $arrColumnas['cabecera'][] = 'RETENCION SUGERIDA RETEICA DESCRIPCION';
            $arrColumnas['cabecera'][] = 'RETENCION SUGERIDA RETEICA PORCENTAJE';
            $arrColumnas['cabecera'][] = 'RETENCION SUGERIDA RETEICA BASE';
            $arrColumnas['cabecera'][] = 'RETENCION SUGERIDA RETEICA VALOR';
            $arrColumnas['cabecera'][] = 'RETENCION SUGERIDA RETEICA BASE MONEDA EXTRANJERA';
            $arrColumnas['cabecera'][] = 'RETENCION SUGERIDA RETEICA VALOR MONEDA EXTRANJERA';
        }

        if(in_array('reteiva-a-nivel-documento', $valoresOpcionalesRG['valores_resumen'.$sufijo])) {
            $arrColumnas['cabecera'][] = 'RETENCION SUGERIDA RETEIVA DESCRIPCION';
            $arrColumnas['cabecera'][] = 'RETENCION SUGERIDA RETEIVA PORCENTAJE';
            $arrColumnas['cabecera'][] = 'RETENCION SUGERIDA RETEIVA BASE';
            $arrColumnas['cabecera'][] = 'RETENCION SUGERIDA RETEIVA VALOR';
            $arrColumnas['cabecera'][] = 'RETENCION SUGERIDA RETEIVA BASE MONEDA EXTRANJERA';
            $arrColumnas['cabecera'][] = 'RETENCION SUGERIDA RETEIVA VALOR MONEDA EXTRANJERA';
        }

        if(in_array('retefuente-a-nivel-documento', $valoresOpcionalesRG['valores_resumen'.$sufijo])) {
            $arrColumnas['cabecera'][] = 'RETENCION SUGERIDA RETEFUENTE DESCRIPCION';
            $arrColumnas['cabecera'][] = 'RETENCION SUGERIDA RETEFUENTE PORCENTAJE';
            $arrColumnas['cabecera'][] = 'RETENCION SUGERIDA RETEFUENTE BASE';
            $arrColumnas['cabecera'][] = 'RETENCION SUGERIDA RETEFUENTE VALOR';
            $arrColumnas['cabecera'][] = 'RETENCION SUGERIDA RETEFUENTE BASE MONEDA EXTRANJERA';
            $arrColumnas['cabecera'][] = 'RETENCION SUGERIDA RETEFUENTE VALOR MONEDA EXTRANJERA';
        }

        // DETALLE
        if(in_array('cargos-a-nivel-item', $valoresOpcionalesRG['valores_resumen'.$sufijo])) {
            if(array_key_exists('cargos_items_personalizados', $valoresOpcionalesRG) && !empty($valoresOpcionalesRG['cargos_items_personalizados'.$sufijo])) {
                foreach($valoresOpcionalesRG['cargos_items_personalizados'.$sufijo] as $nombreCampo) {
                    $arrColumnas['detalle'][] = 'CARGO ' . mb_strtoupper($nombreCampo, "UTF-8");
                    $arrColumnas['detalle'][] = 'CARGO ' . mb_strtoupper($nombreCampo, "UTF-8") . ' PORCENTAJE';
                    $arrColumnas['detalle'][] = 'CARGO ' . mb_strtoupper($nombreCampo, "UTF-8") . ' BASE';
                    $arrColumnas['detalle'][] = 'CARGO ' . mb_strtoupper($nombreCampo, "UTF-8") . ' VALOR';
                    $arrColumnas['detalle'][] = 'CARGO ' . mb_strtoupper($nombreCampo, "UTF-8") . ' BASE MONEDA EXTRANJERA';
                    $arrColumnas['detalle'][] = 'CARGO ' . mb_strtoupper($nombreCampo, "UTF-8") . ' VALOR MONEDA EXTRANJERA';
                }
            } else {
                $arrColumnas['detalle'][] = 'CARGO DESCRIPCION';
                $arrColumnas['detalle'][] = 'CARGO PORCENTAJE';
                $arrColumnas['detalle'][] = 'CARGO BASE';
                $arrColumnas['detalle'][] = 'CARGO VALOR';
                $arrColumnas['detalle'][] = 'CARGO BASE MONEDA EXTRANJERA';
                $arrColumnas['detalle'][] = 'CARGO VALOR MONEDA EXTRANJERA';
            }
        }

        if(in_array('descuentos-a-nivel-item', $valoresOpcionalesRG['valores_resumen'.$sufijo])) {
            if(array_key_exists('descuentos_items_personalizados', $valoresOpcionalesRG) && !empty($valoresOpcionalesRG['descuentos_items_personalizados'.$sufijo])) {
                foreach($valoresOpcionalesRG['descuentos_items_personalizados'.$sufijo] as $nombreCampo) {
                    $arrColumnas['detalle'][] = 'DESCUENTO ' . mb_strtoupper($nombreCampo, "UTF-8");
                    $arrColumnas['detalle'][] = 'DESCUENTO ' . mb_strtoupper($nombreCampo, "UTF-8") . ' CODIGO';
                    $arrColumnas['detalle'][] = 'DESCUENTO ' . mb_strtoupper($nombreCampo, "UTF-8") . ' PORCENTAJE';
                    $arrColumnas['detalle'][] = 'DESCUENTO ' . mb_strtoupper($nombreCampo, "UTF-8") . ' BASE';
                    $arrColumnas['detalle'][] = 'DESCUENTO ' . mb_strtoupper($nombreCampo, "UTF-8") . ' VALOR';
                    $arrColumnas['detalle'][] = 'DESCUENTO ' . mb_strtoupper($nombreCampo, "UTF-8") . ' BASE MONEDA EXTRANJERA';
                    $arrColumnas['detalle'][] = 'DESCUENTO ' . mb_strtoupper($nombreCampo, "UTF-8") . ' VALOR MONEDA EXTRANJERA';
                }
            } else {
                $arrColumnas['detalle'][] = 'DESCUENTO CODIGO';
                $arrColumnas['detalle'][] = 'DESCUENTO DESCRIPCION';
                $arrColumnas['detalle'][] = 'DESCUENTO PORCENTAJE';
                $arrColumnas['detalle'][] = 'DESCUENTO BASE';
                $arrColumnas['detalle'][] = 'DESCUENTO VALOR';
                $arrColumnas['detalle'][] = 'DESCUENTO BASE MONEDA EXTRANJERA';
                $arrColumnas['detalle'][] = 'DESCUENTO VALOR MONEDA EXTRANJERA';
            }
        }

        if(in_array('reteica-a-nivel-item', $valoresOpcionalesRG['valores_resumen'.$sufijo])) {
            $arrColumnas['detalle'][] = 'RETENCION SUGERIDA RETEICA DESCRIPCION';
            $arrColumnas['detalle'][] = 'RETENCION SUGERIDA RETEICA PORCENTAJE';
            $arrColumnas['detalle'][] = 'RETENCION SUGERIDA RETEICA BASE';
            $arrColumnas['detalle'][] = 'RETENCION SUGERIDA RETEICA VALOR';
            $arrColumnas['detalle'][] = 'RETENCION SUGERIDA RETEICA BASE MONEDA EXTRANJERA';
            $arrColumnas['detalle'][] = 'RETENCION SUGERIDA RETEICA VALOR MONEDA EXTRANJERA';
        }

        if(in_array('reteiva-a-nivel-item', $valoresOpcionalesRG['valores_resumen'.$sufijo])) {
            $arrColumnas['detalle'][] = 'RETENCION SUGERIDA RETEIVA DESCRIPCION';
            $arrColumnas['detalle'][] = 'RETENCION SUGERIDA RETEIVA PORCENTAJE';
            $arrColumnas['detalle'][] = 'RETENCION SUGERIDA RETEIVA BASE';
            $arrColumnas['detalle'][] = 'RETENCION SUGERIDA RETEIVA VALOR';
            $arrColumnas['detalle'][] = 'RETENCION SUGERIDA RETEIVA BASE MONEDA EXTRANJERA';
            $arrColumnas['detalle'][] = 'RETENCION SUGERIDA RETEIVA VALOR MONEDA EXTRANJERA';
        }

        if(in_array('retefuente-a-nivel-item', $valoresOpcionalesRG['valores_resumen'.$sufijo])) {
            $arrColumnas['detalle'][] = 'RETENCION SUGERIDA RETEFUENTE DESCRIPCION';
            $arrColumnas['detalle'][] = 'RETENCION SUGERIDA RETEFUENTE PORCENTAJE';
            $arrColumnas['detalle'][] = 'RETENCION SUGERIDA RETEFUENTE BASE';
            $arrColumnas['detalle'][] = 'RETENCION SUGERIDA RETEFUENTE VALOR';
            $arrColumnas['detalle'][] = 'RETENCION SUGERIDA RETEFUENTE BASE MONEDA EXTRANJERA';
            $arrColumnas['detalle'][] = 'RETENCION SUGERIDA RETEFUENTE VALOR MONEDA EXTRANJERA';
        }

        return $arrColumnas;
    }

    /**
     * Consulta un adquirente para verificar si existe para el ofe indicado.
     * 
     * @param int $ofe_id ID del OFE
     * @param string $adq_identificacion Identificación del OFE
     */
    private function adquirenteExiste($ofe_id, $adq_identificacion) {
        $adq = ConfiguracionAdquirente::select(['adq_id'])
            ->where('ofe_id', $ofe_id)
            ->where('adq_identificacion', $adq_identificacion)
            ->first();

        if($adq)
            return true;
        else
            return false;
    }

    /**
     * Crear Adquirente Consumidor Final para los OFE seleccionados.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function crearAdquirenteConsumidorFinal(Request $request) {
        $user = auth()->user();

        if(!$request->has($this->nombreDatoCambiarEstado) || empty($request->{$this->nombreDatoCambiarEstado})) {
            return response()->json([
                'message' => "Error al crear el adquirente consumidor final",
                'errors'  => ["No se ha proporcionado el campo {$this->nombreDatoCambiarEstado} que contiene las identificaciones de los OFEs"]
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        
        $arrErrores    = [];
        $arrCreados    = [];
        $arrExistentes = [];
        $arrOfes       = explode(',', $request->{$this->nombreDatoCambiarEstado});

        $adqController = new ConfiguracionAdquirenteController();
        foreach($arrOfes as $identificacionOfe){
            $ofe = $this->className::select(['ofe_id', 'ofe_identificacion'])
                ->where($this->nombreCampoIdentificacion, $identificacionOfe)
                ->where('estado', 'ACTIVO')
                ->first();

            if($ofe){
                $infoAdquirenteConsumidorFinal = json_decode(config('variables_sistema.ADQUIRENTE_CONSUMIDOR_FINAL'), true);
                foreach($infoAdquirenteConsumidorFinal as $adquirente) {
                    $existe = $this->adquirenteExiste($ofe->ofe_id, $adquirente['adq_identificacion']);

                    if($existe) {
                        $arrExistentes[] = $adquirente['adq_identificacion'];
                    } else {
                        $adqController->crearAdquirenteConsumidorFinal($ofe->ofe_id, $adquirente, $user);
                        $arrCreados[] = $adquirente['adq_identificacion'];
                    }
                }
            } else
                $this->adicionarErrorArray($arrErrores, ["El {$this->nombre} con identificacion [{$identificacionOfe}] No Existe o se encuentra en estado INACTIVO."]);
        }

        if(empty($arrErrores)) {
            $existentes = '';
            if(!empty($arrExistentes)) {
              if (count($arrExistentes) == 1) {
                $existentes = ' El consumidor final ' . implode(', ', array_unique($arrExistentes)) . ' ya se encuentra creado';
              } else {
                $existentes = ' Los consumidores finales ' . implode(', ', array_unique($arrExistentes)) . ' ya se encuentran creados ';
              }
            }
                
                
            $creados = '';
            if(!empty($arrCreados)) {
              if (count($arrCreados) == 1) {
                $creados = ' El consumidor final ' . implode(', ', array_unique($arrCreados)) . ' se creo con exito';
              } else {
                $creados = ' Los consumidore finales ' . implode(', ', array_unique($arrCreados)) . ' se crearon con exito';
              }
            }
            return response()->json([
                'message' => 'Proceso Crear Adquirente Consumidor Final realizado.' . $existentes . $creados
            ], 201);
        } else {
            return response()->json([
                'message' => "Error al crear Adquirente Consumidor Finaal",
                'errors' => $arrErrores
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Crea un usuario en el sistema para el OFE con integración de Cadisoft.
     * 
     * @param int $bdd_id ID de la base de datos del usuario autenticado
     * @param int $bdd_id_rg ID de la base de datos de RG del usuario autenticado
     * @param string $bdd_nombre Nombre de la base de datos sin el prefijo etl_
     * @param string $identificacion Identificacion del OFE
     * 
     * @return void
     */
    private function crearUsuarioIntegracionCadisoft($bdd_id, $bdd_id_rg, $bdd_nombre, $identificacion) {
        $correo = $bdd_nombre . '@cadisoft.co';
        $existe = User::select(['usu_id'])
            ->where('usu_email', $correo)
            ->first();

        if(!$existe) {
            $password = 'c4d1s0ft.0p3n10';
            $usuario = User::create([
                'usu_email'          => $correo,
                'usu_identificacion' => $identificacion,
                'usu_nombre'         => 'Usuario Integración ' . strtoupper($bdd_nombre),
                'usu_password'       => Hash::make($password),
                'usu_type'           => 'INTEGRACION',
                'bdd_id'             => $bdd_id,
                'bdd_id_rg'          => $bdd_id_rg,
                'usuario_creacion'   => auth()->user()->usu_id,
                'estado'             => 'ACTIVO'
            ]);

            SistemaRolesUsuario::create([
                'usu_id'           => $usuario->usu_id,
                'rol_id'           => 5,
                'usuario_creacion' => auth()->user()->usu_id,
                'estado'           => 'ACTIVO'
            ]);
        }
    }

    /**
     * Obtiene las resoluciones de facturación asociadas a un OFE.
     * 
     * @param Request $request
     * @return Response
     */
    public function listaResolucionesFacturacion(Request $request) {
        $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'bdd_id_rg'])
            ->where('ofe_id', $request->ofe_id)
            ->validarAsociacionBaseDatos()
            ->where('estado', 'ACTIVO')
            ->first();

        if(!$ofe)
            return response()->json([
                'message' => 'Error en la consulta de resoluciones de facturación',
                'errors'  => ['El OFE no existe o se encuentra inactivo']
            ], 404);

        $resoluciones = ConfiguracionResolucionesFacturacion::select(['rfa_id', 'rfa_resolucion', 'rfa_prefijo', 'rfa_fecha_desde', 'rfa_fecha_hasta', 'cdo_control_consecutivos', 'cdo_consecutivo_provisional'])
            ->where('ofe_id', $request->ofe_id)
            ->where('estado', 'ACTIVO');

        if($request->has('proyecto_especial_pickup_cash') && $request->proyecto_especial_pickup_cash == true) {
            // Se debe consultar la paramétrica del proyecto especial de pickup cash el dato común 'Prefijo Resolución de Facturación'
            $prefijo = PryDatoComunPickupCash::select(['dcp_valor'])
                ->where('dcp_descripcion', 'Prefijo Resolución de Facturación')
                ->where('estado', 'ACTIVO')
                ->first();

            if($prefijo && !empty($prefijo->dcp_valor))
                $resoluciones = $resoluciones->where('rfa_prefijo', $prefijo->dcp_valor);
        }

        if($request->filled('aplica_para') && $request->aplica_para === 'DS')
            $resoluciones = $resoluciones->where('rfa_tipo', 'DOCUMENTO_SOPORTE');
        else if($request->filled('aplica_para') && $request->aplica_para !== 'DS') {
            $resoluciones = $resoluciones->where(function ($query) {
                $query->where('rfa_tipo','!=', 'DOCUMENTO_SOPORTE')
                    ->orWhereNull('rfa_tipo');
            });
        }

        $resoluciones = $resoluciones->get();

        if(empty($resoluciones))
            return response()->json([
                'message' => 'Error en la consulta de resoluciones de facturación',
                'errors'  => ['El OFE no no tiene resoluciones de facturación asociadas']
            ], 404);

        return response()->json([
            'data' => [
                'resoluciones' => $resoluciones
            ]
        ], 200);
    }

    /**
     * Crea un usuario en el sistema para procesar las transmisiones a través de ERP
     * 
     * @param int $bdd_id ID de la base de datos del usuario autenticado
     * @param int $bdd_id_rg ID de la base de datos de RG del usuario autenticado
     * @param string $bdd_nombre Nombre de la base de datos sin el prefijo etl_
     * @param string $identificacion Identificacion del OFE
     * 
     * @return void
     */
    private function crearUsuarioTransmisionErp($bdd_id, $bdd_id_rg, $bdd_nombre, $identificacion) {
        $correo = 'erp.' . $bdd_nombre . '@open.io';
        $existe = User::select(['usu_id'])
            ->where('usu_email', $correo)
            ->first();

        if(!$existe) {
            $password = Hash::make('uSuar103rP.0p3n10');
            User::create([
                'usu_email'          => $correo,
                'usu_identificacion' => $identificacion,
                'usu_nombre'         => 'Usuario ERP ' . strtoupper($bdd_nombre),
                'usu_password'       => Hash::make($password),
                'usu_type'           => 'INTEGRACION',
                'bdd_id'             => $bdd_id,
                'bdd_id_rg'          => $bdd_id_rg,
                'usuario_creacion'   => auth()->user()->usu_id,
                'estado'             => 'ACTIVO'
            ]);
        }
    }

    /**
     * Crea un usuario en el sistema el proceso de Recepción
     * 
     * @param int $bdd_id ID de la base de datos del usuario autenticado
     * @param int $bdd_id_rg ID de la base de datos de RG del usuario autenticado
     * @param string $bdd_nombre Nombre de la base de datos sin el prefijo etl_
     * @param string $identificacion Identificacion del OFE
     * 
     * @return void
     */
    private function crearUsuarioRecepcion($bdd_id, $bdd_id_rg, $bdd_nombre, $identificacion) {
        $correo = 'recepcion.' . $bdd_nombre . '@open.io';
        $existe = User::select(['usu_id'])
            ->where('usu_email', $correo)
            ->first();

        $existeUsuario = User::select(['usu_id'])
            ->where('usu_email', $correo)
            ->where(function($query) use ($bdd_id, $bdd_id_rg) {
                $query->where('bdd_id', $bdd_id)
                    ->orWhere('bdd_id', $bdd_id_rg);
            })
            ->first();

        if(!$existe) {
            $password = Hash::make('R3c3pC10n.0p3n10');
            $userRecepcion = User::create([
                'usu_email'          => $correo,
                'usu_identificacion' => $identificacion,
                'usu_nombre'         => 'Usuario Recepción ' . strtoupper($bdd_nombre),
                'usu_password'       => Hash::make($password),
                'usu_type'           => 'INTEGRACION',
                'bdd_id'             => $bdd_id,
                'bdd_id_rg'          => $bdd_id_rg,
                'usuario_creacion'   => auth()->user()->usu_id,
                'estado'             => 'ACTIVO'
            ]);

            // Se obtiene el rol UsuarioIntegracion el cual tiene asignados los permisos para pder crear y editar proveedores
            $rol = SistemaRol::select(['rol_id'])
                ->where('rol_codigo', 'UsuarioIntegracion')
                ->first();

            // Asigna el rol UsuarioIntegracion al nuevo usuario
            SistemaRolesUsuario::create([
                'usu_id'           => $userRecepcion->usu_id,
                'rol_id'           => $rol->rol_id,
                'usuario_creacion' => 1,
                'estado'           => 'ACTIVO'
            ]);
        }
    }

    /**
     * Valida la información recibida en el parámetro ofe_recepcion_conexion_erp.
     * 
     * La validación consiste en verificar que las propiedades obligatorios lleguen, que la única propiedad que no puede estar vacia es frecuencia_minutos, y frecuencia_dias_semana cuando no es vacio puede tener los valores L M X J V S  (uno o varios de ellos separados por comas).
     * 
     * @param object $conexion Objeto con la información de conexión al ERP
     * 
     * @return array Vacio cuando pasa la verificación y con error cuando no pasa la verificación
     */
    private function validaRecepcionConexionErp($conexion) {
        if(
            !isset($conexion->frecuencia_dias) ||
            (
                !isset($conexion->frecuencia_dias_semana) ||
                (
                    (isset($conexion->frecuencia_dias_semana) && $conexion->frecuencia_dias_semana != '') &&
                    (
                        !strstr($conexion->frecuencia_dias_semana, 'L') &&
                        !strstr($conexion->frecuencia_dias_semana, 'M') &&
                        !strstr($conexion->frecuencia_dias_semana, 'X') &&
                        !strstr($conexion->frecuencia_dias_semana, 'J') &&
                        !strstr($conexion->frecuencia_dias_semana, 'V') &&
                        !strstr($conexion->frecuencia_dias_semana, 'S') &&
                        !strstr($conexion->frecuencia_dias_semana, 'D')
                    )
                )
            ) ||
            !isset($conexion->frecuencia_horas) ||
            (!isset($conexion->frecuencia_minutos) || (isset($conexion->frecuencia_minutos) && $conexion->frecuencia_minutos == ''))
        ) {
            return [
                'message' => 'Errores al procesar el OFE',
                'errors'  => ['Verifique que dentro de la información del JSON de la Conexión ERP incluya los parámetros: frecuencia_dias, frecuencia_dias_semana, frecuencia_horas y frecuencia_minutos. Teniendo en cuenta que la única propiedad que no puede estar vacia es frecuencia_minutos, y frecuencia_dias_semana cuando no es vacio puede tener los valores L M X J V S (uno o varios de ellos separados por comas)']
            ];
        }

        return [];
    }

    /**
     * Obtiene el logo para notificacion eventos DIAN del OFE
     * 
     * @param string $ofe_identificacion Identificacion del OFE
     * @return array Array con el resultado del procesamiento en DO
     */
    private function obtenerLogoEventosDian($ofe_identificacion) {
        $parametros = [
            'bdd_id'             => auth()->user()->bdd_id,
            'bdd_id_rg'          => auth()->user()->bdd_id_rg,
            'ofe_identificacion' => $ofe_identificacion
        ];

        return MainTrait::peticionMicroservicio('DO', 'POST', '/api/notificacion-eventos-dian/logo-ofe', $parametros, 'json');
    }

    /**
     * Envía una petición al microservicio DO para crear/actualizar la estructura de carpetas relacionada con la notificación de eventos DIAN del OFE
     * 
     * @param Request $request
     * @return array Array con el resultado del procesamiento en DO
     */
    private function configuracionNotificacionEventosDian(Request $request) {
        $parametros = [];
        if($request->hasFile('logoNotificacionEventosDian')) {
            $nombreArchivo = $request->file('logoNotificacionEventosDian')->getClientOriginalName();
            
            $extension = $request->file('logoNotificacionEventosDian')->extension();
            if(strtolower($extension) != 'png') {
                $arrErrores[] = 'La extension del archivo ' . $nombreArchivo . ' no es válida, solo se permite: [png]';
                return [
                    'message' => 'Error en parámetros del OFE',
                    'errors'  => ['La extension del archivo ' . $nombreArchivo . ' no es válida, solo se permite [png] para el logo de la notificación de eventos DIAN'],
                    'status'  => 409
                ];
            }

            $parametros[] = [
                'name'      => 'logoNotificacionEventosDian',
                'contents'  => File::get($request->logoNotificacionEventosDian),
                'filename'  => $nombreArchivo
            ];
        }

        $parametros = array_merge($parametros, [
            [
                'name'     => 'bdd_id',
                'contents' => auth()->user()->bdd_id
            ],
            [
                'name'     => 'bdd_id_rg',
                'contents' => auth()->user()->bdd_id_rg
            ],
            [
                'name'     => 'ofe_identificacion',
                'contents' =>$request->ofe_identificacion
            ],
            [
                'name'     => 'ofe_recepcion_correo_estandar',
                'contents' =>$request->ofe_recepcion_correo_estandar
            ]
        ]);

        return MainTrait::peticionMicroservicio('DO', 'POST', '/api/notificacion-eventos-dian/configuracion', $parametros, 'multipart');
    }

    /**
     * Procesa información relacionada con la configuración de los servicios de un OFE.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function configuracionServicios(Request $request) {
        $this->user = auth()->user();

        // Consulta el OFE
        $ofe = ConfiguracionObligadoFacturarElectronicamente::where('ofe_identificacion', $request->ofe_identificacion)
            ->validarAsociacionBaseDatos()
            ->first();

        if(!$ofe) {
            return response()->json([
                'message' => 'Errores al procesar la información',
                'errors' => ['El OFE con identificación [' . $request->ofe_identificacion . '] no existe.']
            ], Response::HTTP_CONFLICT);
        }

        // Validando los datos enviados
        if ($request->has('ofe_prioridad_agendamiento') && $request->ofe_prioridad_agendamiento != '' && $request->ofe_prioridad_agendamiento != null) {
            $rules['ofe_prioridad_agendamiento'] = 'nullable|string|regex:/^[0-9]{1,2}$/';
        }

        $rules['ofe_recepcion_transmision_erp'] = 'nullable|string|in:SI,NO';
        $rules['ofe_recepcion_conexion_erp']    = 'nullable|json';
        $validador = Validator::make($request->all(), $rules);

        if ($validador->fails()) {
            return response()->json([
                'message' => 'Errores al actualizar la configuración de servicios del OFE',
                'errors' => $validador->errors()->all()
            ], 400);
        }

        $data['ofe_emision']           = ($request->has('ofe_emision') && !empty($request->ofe_emision)) ? $request->ofe_emision : null;
        $data['ofe_recepcion']         = ($request->has('ofe_recepcion') && !empty($request->ofe_recepcion)) ? $request->ofe_recepcion : null;
        $data['ofe_documento_soporte'] = ($request->has('ofe_documento_soporte') && !empty($request->ofe_documento_soporte)) ? $request->ofe_documento_soporte : null;

        if($request->has('ofe_recepcion') && $request->ofe_recepcion == 'SI') {
            // Creación del usuario de integración que permite la ejecución de procesos relacionados con Recepción
            $nombreBaseDatos = !empty($this->user->bdd_id_rg) ? $this->user->getBaseDatosRg->bdd_nombre : $this->user->getBaseDatos->bdd_nombre;
            $this->crearUsuarioRecepcion($this->user->bdd_id, $this->user->bdd_id_rg, str_replace('etl_', '', $nombreBaseDatos), $request->ofe_identificacion);
        }

        if($request->has('ofe_recepcion') && $request->ofe_recepcion == 'SI' && 
            $request->has('ofe_recepcion_correo_estandar') && $request->ofe_recepcion_correo_estandar == 'SI') {
            // Procesamiento para el logo del OFE a utilizar en las notificaciones de eventos de la DIAN
            $configuracionNotificacionEventosDian = $this->configuracionNotificacionEventosDian($request);
            if(array_key_exists('errors', $configuracionNotificacionEventosDian) && !empty($configuracionNotificacionEventosDian)) {
                return response()->json([
                    'message' => 'Errores al actualizar la configuración de servicios del OFE',
                    'errors'  => $configuracionNotificacionEventosDian['errors']
                ], $configuracionNotificacionEventosDian['status']);
            }
        }

        if(
            $request->has('ofe_emision') && $request->ofe_emision == 'SI' &&
            $request->has('ofe_emision_eventos_contratados_titulo_valor') && !empty($request->ofe_emision_eventos_contratados_titulo_valor)
        ) {
            $emisionEventosDian = json_decode($request->ofe_emision_eventos_contratados_titulo_valor);
            // Si llegan eventos contratados en emisión, válida que exista el evento ACEPTACIONT
            if(!empty($emisionEventosDian)) {
                foreach ($emisionEventosDian as $eventoDian) {
                    $existeEvento = false;
                    if ($eventoDian->evento == 'ACEPTACIONT') {
                        $existeEvento = true;
                        break;
                    }
                }

                if (!$existeEvento)
                    return response()->json([
                        'message' => 'Errores al actualizar la configuración de servicios del OFE',
                        'errors' => ['Debe seleccionar el evento de Aceptación Tácita para poder seleccionar los demás eventos contratados en Emisión.']
                    ], 400);
            }

            $data['ofe_emision_eventos_contratados_titulo_valor'] = json_decode($request->ofe_emision_eventos_contratados_titulo_valor);
        } elseif(
            $request->has('ofe_emision') && $request->ofe_emision == 'SI' &&
            $request->has('ofe_emision_eventos_contratados_titulo_valor') && empty($request->ofe_emision_eventos_contratados_titulo_valor)
        ) {
            $data['ofe_emision_eventos_contratados_titulo_valor'] = null;
        } elseif(
            $request->has('ofe_emision') && $request->ofe_emision == 'NO' && 
            $request->has('ofe_emision_eventos_contratados_titulo_valor') 
        ) {
            $data['ofe_emision_eventos_contratados_titulo_valor'] = null;
        }

        if(
            $request->has('ofe_recepcion') && $request->ofe_recepcion == 'SI' &&
            $request->has('ofe_recepcion_eventos_contratados_titulo_valor') && !empty($request->ofe_recepcion_eventos_contratados_titulo_valor)
        ) {
            $data['ofe_recepcion_eventos_contratados_titulo_valor'] = json_decode($request->ofe_recepcion_eventos_contratados_titulo_valor);
        } elseif(
            $request->has('ofe_recepcion') && $request->ofe_recepcion == 'SI' &&
            $request->has('ofe_recepcion_eventos_contratados_titulo_valor') && empty($request->ofe_recepcion_eventos_contratados_titulo_valor)
        ) {
            $data['ofe_recepcion_eventos_contratados_titulo_valor'] = null;
        } elseif(
            $request->has('ofe_recepcion') && $request->ofe_recepcion == 'NO' && 
            $request->has('ofe_recepcion_eventos_contratados_titulo_valor') 
        ) {
            $data['ofe_recepcion_eventos_contratados_titulo_valor'] = null;
        }

        if(!$request->has('ofe_eventos_notificacion') || ($request->has('ofe_eventos_notificacion') && empty($request->ofe_eventos_notificacion))) {
            $data['ofe_eventos_notificacion'] = null;
        } else {
            $data['ofe_eventos_notificacion'] = $request->ofe_eventos_notificacion;
        }

        $data['ofe_prioridad_agendamiento']    = ($request->has('ofe_prioridad_agendamiento') && !empty($request->ofe_prioridad_agendamiento)) ? $this->sanitizarStrings($request->ofe_prioridad_agendamiento) : null;
        $data['ofe_recepcion_correo_estandar'] = ($request->has('ofe_recepcion_correo_estandar') && !empty($request->ofe_recepcion_correo_estandar)) ? $request->ofe_recepcion_correo_estandar : null;

        if($request->has('ofe_envio_notificacion_amazon_ses') && $request->ofe_envio_notificacion_amazon_ses == 'SI' && $request->has('ofe_conexion_smtp') && !empty($request->ofe_conexion_smtp)) {
            $smtpConfig = json_decode($request->ofe_conexion_smtp);
            if(
                json_last_error() == JSON_ERROR_NONE &&
                isset($smtpConfig->AWS_ACCESS_KEY_ID) && !empty($smtpConfig->AWS_ACCESS_KEY_ID) &&
                isset($smtpConfig->AWS_SECRET_ACCESS_KEY) && !empty($smtpConfig->AWS_SECRET_ACCESS_KEY) &&
                isset($smtpConfig->AWS_FROM_EMAIL) && !empty($smtpConfig->AWS_FROM_EMAIL) &&
                isset($smtpConfig->AWS_REGION) && !empty($smtpConfig->AWS_REGION) &&
                isset($smtpConfig->AWS_SES_CONFIGURATION_SET) && !empty($smtpConfig->AWS_SES_CONFIGURATION_SET)
            ) {
                $data['ofe_conexion_smtp'] = json_decode($request->ofe_conexion_smtp);
            } else {
                $data['ofe_conexion_smtp'] = null;
            }
        } elseif($request->has('ofe_envio_notificacion_amazon_ses') && $request->ofe_envio_notificacion_amazon_ses == 'NO') {
            $smtpConfig = json_decode($request->ofe_conexion_smtp);
            
            if(!empty($request->ofe_conexion_smtp)) {
                if(
                    json_last_error() == JSON_ERROR_NONE &&
                    isset($smtpConfig->driver) && !empty($smtpConfig->driver) &&
                    isset($smtpConfig->host) && !empty($smtpConfig->host) &&
                    isset($smtpConfig->port) && !empty($smtpConfig->port) &&
                    isset($smtpConfig->from_email) && !empty($smtpConfig->from_email) &&
                    isset($smtpConfig->from_nombre) && !empty($smtpConfig->from_nombre) &&
                    isset($smtpConfig->encryption) && !empty($smtpConfig->encryption) &&
                    isset($smtpConfig->usuario) && !empty($smtpConfig->usuario) &&
                    isset($smtpConfig->password) && !empty($smtpConfig->password)
                ) {
                    $data['ofe_conexion_smtp'] = json_decode($request->ofe_conexion_smtp);
                } elseif(
                    json_last_error() == JSON_ERROR_NONE &&
                    !isset($smtpConfig->driver) && empty($smtpConfig->driver) &&
                    !isset($smtpConfig->host) && empty($smtpConfig->host) &&
                    !isset($smtpConfig->port) && empty($smtpConfig->port) &&
                    isset($smtpConfig->from_email) && !empty($smtpConfig->from_email) &&
                    !isset($smtpConfig->from_nombre) && empty($smtpConfig->from_nombre) &&
                    !isset($smtpConfig->encryption) && empty($smtpConfig->encryption) &&
                    !isset($smtpConfig->usuario) && empty($smtpConfig->usuario) &&
                    !isset($smtpConfig->password) && empty($smtpConfig->password)
                ) {
                    $data['ofe_conexion_smtp'] = json_decode($request->ofe_conexion_smtp);
                } else {
                    $data['ofe_conexion_smtp'] = null;
                }
            } else {
                $data['ofe_conexion_smtp'] = null;
            }
        } else {
            $data['ofe_conexion_smtp'] = ['from_email' => config('variables_sistema.MAIL_FROM_ADDRESS')];
        }

        $data['ofe_envio_notificacion_amazon_ses'] = $request->has('ofe_envio_notificacion_amazon_ses') && !empty($request->ofe_envio_notificacion_amazon_ses) ? $request->ofe_envio_notificacion_amazon_ses : 'NO';

        if(
            $request->has('ofe_recepcion_transmision_erp') && $request->ofe_recepcion_transmision_erp == 'SI' &&
            $request->has('ofe_recepcion_conexion_erp') && !empty($request->ofe_recepcion_conexion_erp)
        ) {
            $data['ofe_recepcion_conexion_erp'] = json_decode($request->ofe_recepcion_conexion_erp);

            $validaRecepcionConexionErp = $this->validaRecepcionConexionErp($data['ofe_recepcion_conexion_erp']);
            if(!empty($validaRecepcionConexionErp))
                return response()->json($validaRecepcionConexionErp, 400);

            // Creación del usuario de integración que permite la ejecución del comando de procesar documentos para transmisión a ERP
            $nombreBaseDatos = !empty($this->user->bdd_id_rg) ? $this->user->getBaseDatosRg->bdd_nombre : $this->user->getBaseDatos->bdd_nombre;
            $this->crearUsuarioTransmisionErp($this->user->bdd_id, $this->user->bdd_id_rg, str_replace('etl_', '', $nombreBaseDatos), $request->ofe_identificacion);
        } else {
            unset($data['ofe_recepcion_conexion_erp']);
        }

        $data['ofe_recepcion_transmision_erp'] = $request->has('ofe_recepcion_transmision_erp') && !empty($request->ofe_recepcion_transmision_erp) ? $request->ofe_recepcion_transmision_erp : null;

        if($request->has('ofe_cadisoft_activo') && $request->ofe_cadisoft_activo == 'SI') {
            // Las RG de Cadisoft son genéricas para ellos ya que envian el PDF en base64 y se debe es editar
            // $data['ofe_tiene_representacion_grafica_personalizada'] = "NO";
            $data['ofe_cadisoft_activo'] = "SI";
            $data['ofe_cadisoft_configuracion'] = $request->ofe_cadisoft_configuracion;

            // Procesamiento para el logo del OFE en la integración con Cadisoft
            $resultadoAlmacenarLogo = $this->almacenarLogo($request, 'logoCadisoft', true);
            if ($resultadoAlmacenarLogo['errores']) {
                return response()->json([
                    'message' => 'Errores al actualizar la configuración de servicios del OFE',
                    'errors'  => $resultadoAlmacenarLogo['errores']
                ], $resultadoAlmacenarLogo['codigo']);
            }

            // Creación del usuario de integración que permite la ejecución del comando de procesar documentos de Cadisoft
            $nombreBaseDatos = !empty($this->user->bdd_id_rg) ? $this->user->getBaseDatosRg->bdd_nombre : $this->user->getBaseDatos->bdd_nombre;
            $this->crearUsuarioIntegracionCadisoft($this->user->bdd_id, $this->user->bdd_id_rg, str_replace('etl_', '', $nombreBaseDatos), $request->ofe_identificacion);
        } else {
            $data['ofe_cadisoft_activo']        = 'NO';
            $data['ofe_cadisoft_configuracion'] = null;
        }

        if($request->has('ofe_integracion_ecm') && $request->ofe_integracion_ecm == 'SI' && !empty($request->ofe_integracion_ecm_conexion)) {
            $data['ofe_integracion_ecm'] 			= $request->ofe_integracion_ecm;
            $data['ofe_integracion_ecm_conexion']	= $request->ofe_integracion_ecm_conexion;
        } elseif ($request->has('ofe_integracion_ecm') && $request->ofe_integracion_ecm == 'NO') {
            $data['ofe_integracion_ecm'] 			= $request->ofe_integracion_ecm;
            $data['ofe_integracion_ecm_conexion']	= null;
        }

        // Se actualiza la configuración de servicios para el Ofe
        $ofe->update($data);

        return response()->json([
            'message' => 'Información actualizada'
        ], Response::HTTP_OK);
    }
}
