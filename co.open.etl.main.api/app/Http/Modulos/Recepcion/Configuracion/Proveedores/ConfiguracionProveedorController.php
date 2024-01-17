<?php

namespace App\Http\Modulos\Recepcion\Configuracion\Proveedores;

use Validator;
use App\Http\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Traits\OfeAdqValidations;
use App\Traits\GruposTrabajoTrait;
use Illuminate\Support\Facades\DB;
use openEtl\Tenant\Traits\TenantTrait;
use openEtl\Main\Traits\PackageMainTrait;
use App\Http\Controllers\OpenTenantController;
use App\Http\Modulos\Sistema\Roles\SistemaRol;
use App\Http\Modulos\Parametros\Paises\ParametrosPais;
use App\Console\Commands\ProcesarCargaParametricaCommand;
use App\Http\Modulos\Sistema\Agendamiento\AdoAgendamiento;
use App\Http\Modulos\Parametros\Municipios\ParametrosMunicipio;
use App\Http\Modulos\Parametros\Departamentos\ParametrosDepartamento;
use App\Http\Modulos\Parametros\RegimenFiscal\ParametrosRegimenFiscal;
use App\Http\Modulos\Parametros\CodigosPostales\ParametrosCodigoPostal;
use App\Http\Modulos\Sistema\EtlProcesamientoJson\EtlProcesamientoJson;
use App\Http\Modulos\Parametros\TiposDocumentos\ParametrosTipoDocumento;
use App\Http\Modulos\Recepcion\Configuracion\Proveedores\ConfiguracionProveedor;
use App\Http\Modulos\Parametros\ResponsabilidadesFiscales\ParametrosResponsabilidadFiscal;
use App\Http\Modulos\Parametros\TipoOrganizacionJuridica\ParametrosTipoOrganizacionJuridica;
use App\Http\Modulos\Configuracion\GruposTrabajo\GruposTrabajoUsuarios\ConfiguracionGrupoTrabajoUsuario;
use App\Http\Modulos\ProyectosEspeciales\DHLExpress\CorreosNotificacionBasware\PryCorreoNotificacionBasware;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

class ConfiguracionProveedorController extends OpenTenantController {
    use OfeAdqValidations, GruposTrabajoTrait, PackageMainTrait;

    /**
     * Nombre del modelo en singular.
     *
     * @var String
     */
    public $nombre = 'Proveedor';

    /**
     * Nombre del modelo en plural.
     *
     * @var String
     */
    public $nombrePlural = 'Proveedores';

    /**
     * Modelo relacionado a la paramétrica.
     *
     * @var Illuminate\Database\Eloquent\Model
     */
    public $className = ConfiguracionProveedor::class;

    /**
     * constante para almacenar los nit asociados a DHLEXPRESS.
     *
     * @var Array
     */
    public const NIT_DHLEXPRESS = ['860502609','830076778'];

    /**
     * Mensaje de error para status code 422 al crear.
     *
     * @var String
     */
    public $mensajeErrorCreacion422 = 'No Fue Posible Crear el Proveedor';

    /**
     * Mensaje de errores al momento de crear.
     *
     * @var String
     */
    public $mensajeErroresCreacion = 'Errores al Crear el Proveedor';

    /**
     * Mensaje de error para status code 422 al actualizar.
     *
     * @var String
     */
    public $mensajeErrorModificacion422 = 'Errores al Actualizar el Proveedor';

    /**
     * Mensaje de errores al momento de crear.
     *
     * @var String
     */
    public $mensajeErroresModificacion = 'Errores al Actualizar el Proveedor';

    /**
     * Mensaje de error cuando el objeto no existe.
     *
     * @var String
     */
    public $mensajeObjectNotFound = 'El Id del Proveedor [%s] no Existe';

    /**
     * Mensaje de error cuando el objeto no existe.
     *
     * @var String
     */
    public $mensajeObjectDisabled = 'El Id del Proveedor [%s] Esta Inactivo';

    /**
     * constante para almacenar el tipo proveedor.
     *
     * @var String
     */
    public const PROVEEDORES = 'proveedores';

    /**
     * Propiedad para almacenar los errores.
     *
     * @var Array
     */
    protected $errors = [];

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
     * Propiedad para saber si se valido correctamente el codigo postal.
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
     * Nombre del capo de identificación del proveedor. 
     *
     * @var String
     */
    public $nombreCampoIdentificacion = 'pro_identificacion';

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
        'ID PERSONALIZADO',
        'RAZON SOCIAL',
        'NOMBRE COMERCIAL',
        'PRIMER APELLIDO',
        'SEGUNDO APELLIDO',
        'PRIMER NOMBRE',
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
        'TELEFONO',
        'CODIGO PAIS DOMICILIO FISCAL',
        'PAIS DOMICILIO FISCAL',
        'CODIGO DEPARTAMENTO DOMICILIO FISCAL',
        'DEPARTAMENTO DOMICILIO FISCAL',
        'CODIGO MUNICIPIO DOMICILIO FISCAL',
        'MUNICIPIO DOMICILIO FISCAL',
        'CODIGO POSTAL DOMICILIO FISCAL',
        'DIRECCION DOMICILIO FISCAL',
        'CORREO',
        'CODIGO REGIMEN FISCAL',
        'REGIMEN FISCAL',
        'CODIGO RESPONSABILIDADES FISCALES',
        'MATRICULA MERCANTIL',
        'CORREOS NOTIFICACION',
        'USUARIOS GESTION DOCUMENTOS RECIBIDOS'
    ];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->middleware(['jwt.auth', 'jwt.refresh']);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionProveedores,RecepcionDocumentosRecibidos,ConfiguracionProveedoresCambiarEstado,ConfiguracionProveedoresEditar,ConfiguracionProveedoresNuevo,ConfiguracionProveedoresSubir,ConfiguracionProveedoresVer'
        ])->except([
            'show',
            'store',
            'update',
            'cambiarEstado',
            'searchProveedores',
            'updateCompuesto',
            'showCompuesto',
            'generarInterfaceProveedores',
            'cargarProveedores',
            'getListaErroresProveedores',
            'descargarListaErroresProveedores',
            'busqueda',
            'obtenerUsuarios'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionProveedoresNuevo'
        ])->only([
            'store',
            'obtenerUsuarios'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionProveedoresEditar,RecepcionDocumentosRecibidos'
        ])->only([
            'update',
            'updateCompuesto',
            'obtenerUsuarios'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionProveedoresVer,ConfiguracionProveedoresEditar'
        ])->only([
            'show',
            'showCompuesto'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionProveedoresVer'
        ])->only([
            'busqueda',
            'obtenerUsuarios'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionProveedoresCambiarEstado'
        ])->only([
            'cambiarEstado'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionProveedoresSubir'
        ])->only([
            'generarInterfaceProveedores',
            'cargarProveedores',
            'getListaErroresProveedores',
            'descargarListaErroresProveedores'
        ]);

        array_splice($this->columnasExcel, 1, 0, [$this->getTituloIdentificacion()]);
    }

    /**
     * Evalua la URI para determinar que tipo de entidad se esta solicitando.
     *
     * @return array
     */
    private function getFiltroTipo() {
        return  [
            'error_message' => 'El proveedor [%s] no existe'
        ];
    }

    /**
     * Construye el mensaje de titulo de mensaje de error al intentar crear/actualizar un Proveedor.
     *
     * @param string $accion
     * @param bool $di
     * @return string
     */
    private function getMensajeErrrorActualizarCrear(string $accion = 'crear') {
        return "Errores al $accion el Proveedor";
    }

    /**
     * Configura las reglas para poder actualizar o crear los datos de los proveedores en funcion de los codigos proporcionados
     *
     * @param Request $request
     * @return mixed
     */
    private function getRules(Request $request) {
        $rules = $this->className::$rules;
        unset(
            $rules['ofe_id'],
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
            $rules['pro_razon_social'],
            $rules['pro_nombre_comercial'],
            $rules['pro_primer_apellido'],
            $rules['pro_segundo_apellido'],
            $rules['pro_primer_nombre'],
            $rules['pro_otros_nombres'],
            $rules['pro_usuarios_recepcion']
        );

        if ($request['tdo_codigo'] == '13' || $request['tdo_codigo'] == '31') {
            $rules['pro_identificacion'] = 'required|string|regex:/^[1-9]/';
        }

        $rules['ofe_identificacion']             = 'required|string|max:20';
        $rules['tdo_codigo']                     = 'nullable|string|max:2';
        $rules['toj_codigo']                     = 'nullable|string|max:2';
        $rules['rfi_codigo']                     = 'nullable|string';
        $rules['ref_codigo']                     = 'nullable|array|max:255';

        $rules['pai_codigo']                     = 'nullable|string|max:10';
        $rules['dep_codigo']                     = 'nullable|string|max:10';
        $rules['mun_codigo']                     = 'nullable|string|max:10';
        $rules['cpo_codigo']                     = 'nullable|string|max:10';
        $rules['pro_direccion']                  = 'nullable|string|max:255';
        $rules['pro_telefono']                   = 'nullable|string|max:255';

        $rules['dep_codigo_domicilio_fiscal']    = 'nullable|string|max:10';
        $rules['pai_codigo_domicilio_fiscal']    = 'nullable|string|max:10';
        $rules['mun_codigo_domicilio_fiscal']    = 'nullable|string|max:10';
        $rules['cpo_codigo_domicilio_fiscal']    = 'nullable|string|max:10';
        $rules['pro_direccion_domicilio_fiscal'] = 'nullable|string|max:255';

        // Condicionales para direcciones de domicilio
        // Este bloque está comentado para NO obligar los datos de país, dirección, país domicilio fiscal y dirección domicilio fiscal de acuerdo a los condicionales comentados
        /* $rules['pai_codigo'] = Rule::requiredIf(function() use ($request) {
            return !empty($request->dep_codigo) || !empty($request->mun_codigo) || !empty($request->pro_direccion) || !empty($request->cpo_codigo);
        });

        $rules['pro_direccion'] = Rule::requiredIf(function() use ($request) {
            return !empty($request->pai_codigo) || !empty($request->mun_codigo) || !empty($request->dep_codigo) || !empty($request->cpo_codigo);
        });

        // Condicionales para direcciones fiscales
        $rules['pai_codigo_domicilio_fiscal'] = Rule::requiredIf(function() use ($request) {
            return !empty($request->dep_codigo_domicilio_fiscal) || !empty($request->mun_codigo_domicilio_fiscal) || !empty($request->pro_direccion_domicilio_fiscal) || !empty($request->cpo_codigo_domicilio_fiscal);
        });

        $rules['pro_direccion_domicilio_fiscal'] = Rule::requiredIf(function() use ($request) {
            return !empty($request->pai_codigo_domicilio_fiscal) || !empty($request->mun_codigo_domicilio_fiscal) || !empty($request->dep_codigo_domicilio_fiscal) || !empty($request->cpo_codigo_domicilio_fiscal);
        }); */

        return $rules;
    }

    /**
     * Reemplaza los codigos e identificaciones proporcionadas en los datos de entrada por los correspodientes IDs de
     * las parametricas que pueden componer a un Proveedor
     *
     * @param $origin
     * @param $datosParseados
     * @param $ofe
     */
    private function buildData(&$origin, $datosParseados, $ofe) {
        unset(
            $origin['ofe_identificacion'],
            $origin['tdo_codigo'],
            $origin['toj_codigo'],
            $origin['pai_codigo'],
            $origin['dep_codigo'],
            $origin['mun_codigo'],
            $origin['cpo_codigo'],
            $origin['rfi_codigo'],
            $origin['ref_codigo']
        );

        $origin['ofe_id'] = $ofe->ofe_id;
        $origin['tdo_id'] = array_key_exists('tdo_id', $datosParseados) ? $datosParseados['tdo_id'] : null;
        $origin['toj_id'] = array_key_exists('toj_id', $datosParseados) ? $datosParseados['toj_id'] : null;
        $origin['pai_id'] = array_key_exists('pai_id', $datosParseados) ? $datosParseados['pai_id'] : null;
        $origin['dep_id'] = array_key_exists('dep_id', $datosParseados) ? $datosParseados['dep_id'] : null;
        $origin['mun_id'] = array_key_exists('mun_id', $datosParseados) ? $datosParseados['mun_id'] : null;
        $origin['cpo_id'] = array_key_exists('cpo_id', $datosParseados) ? $datosParseados['cpo_id'] : null;
        $origin['pai_id_domicilio_fiscal'] = array_key_exists('pai_id_domicilio_fiscal', $datosParseados) ? $datosParseados['pai_id_domicilio_fiscal'] : null;
        $origin['dep_id_domicilio_fiscal'] = array_key_exists('dep_id_domicilio_fiscal', $datosParseados) ? $datosParseados['dep_id_domicilio_fiscal'] : null;
        $origin['mun_id_domicilio_fiscal'] = array_key_exists('mun_id_domicilio_fiscal', $datosParseados) ? $datosParseados['mun_id_domicilio_fiscal'] : null;
        $origin['cpo_id_domicilio_fiscal'] = array_key_exists('cpo_id_domicilio_fiscal', $datosParseados) ? $datosParseados['cpo_id_domicilio_fiscal'] : null;
        $origin['rfi_id'] = array_key_exists('rfi_id', $datosParseados) ? $datosParseados['rfi_id'] : null;
        $origin['ref_id'] = array_key_exists('ref_id', $datosParseados) ? $datosParseados['ref_id'] : null;
        $origin['tat_id'] = array_key_exists('tat_id', $datosParseados) ? $datosParseados['tat_id'] : null;

        $origin['pro_razon_social']     = array_key_exists('pro_razon_social', $datosParseados) ? $datosParseados['pro_razon_social'] : null;
        $origin['pro_nombre_comercial'] = array_key_exists('pro_nombre_comercial', $datosParseados) ? $datosParseados['pro_nombre_comercial'] : null;
        $origin['pro_primer_apellido']  = array_key_exists('pro_primer_apellido', $datosParseados) ? $datosParseados['pro_primer_apellido'] : null;
        $origin['pro_segundo_apellido'] = array_key_exists('pro_segundo_apellido', $datosParseados) ? $datosParseados['pro_segundo_apellido'] : null;
        $origin['pro_primer_nombre']    = array_key_exists('pro_primer_nombre', $datosParseados) ? $datosParseados['pro_primer_nombre'] : null;
        $origin['pro_otros_nombres']    = array_key_exists('pro_otros_nombres', $datosParseados) ? $datosParseados['pro_otros_nombres'] : null;
    }

    /**
     * Almacena un recurso recién creado.
     *
     * @param Request $request
     * @return JsonResponse|Response
     */
    public function store(Request $request) {
        $this->errors = [];
        // Usuario autenticado
        $this->user = auth()->user();

        $camposCorreo = ['pro_correo','pro_correos_notificacion'];
        foreach ($camposCorreo as $campo) {
            $validatorEmail = $this->validationEmailRule($request->{$campo});
            if (!empty($validatorEmail['errors']))
                $this->errors = array_merge($this->errors, $validatorEmail['errors']);
        }
        if (!empty($this->errors))
            return response()->json([
                'message' => $this->getMensajeErrrorActualizarCrear('crear'),
                'errors'  => $this->errors
            ], 404);

        // Validando los datos enviados
        $validador = Validator::make($request->all(), $this->getRules($request));
        if (!$validador->fails()) {
            // Valida que las llaves foraneas existan
            $datos = $this->validarDatos($request);

            if (
                ($request->has('origen') && $request->origen == 'procesamiento_documentos') ||
                (
                    (!$request->has('origen') || ($request->has('origen') && empty($request->origen))) &&
                    empty($this->errors)
                )
            ) {
                $ofe = ConfiguracionObligadoFacturarElectronicamente::select([
                        'ofe_id', 
                        'ofe_identificacion', 
                        'ofe_correo',
                        \DB::raw('IF(ofe_razon_social IS NULL OR ofe_razon_social = "", CONCAT(ofe_primer_nombre, " ", ofe_otros_nombres, " ", ofe_primer_apellido, " ", ofe_segundo_apellido), ofe_razon_social) as nombre_completo'),
                        'bdd_id_rg'
                    ])
                    ->where('ofe_identificacion', $request->ofe_identificacion)
                    ->validarAsociacionBaseDatos()
                    ->where('estado', 'ACTIVO')
                    ->first();

                if ($ofe === null) {
                    return response()->json([
                        'message' => $this->getMensajeErrrorActualizarCrear('crear'),
                        'errors' => [sprintf("El Oferente [%s] no existe.", $request->ofe_identificacion)]
                    ], Response::HTTP_NOT_FOUND);
                }

                $existeProveedor = $this->className::select('pro_id', 'estado')
                    ->where('ofe_id', $ofe->ofe_id)
                    ->where('pro_identificacion', $request->pro_identificacion)
                    ->first();

                if ($existeProveedor) {
                    return response()->json([
                        'message' => $this->getMensajeErrrorActualizarCrear('crear'),
                        'errors' =>['Ya existe un Proveedor con el numero de identificacion [' . $request->pro_identificacion . '] para el OFE [' . $request->ofe_identificacion . '].']
                    ], Response::HTTP_NOT_FOUND);
                }

                $input = $request->all();
                $this->buildData($input, $datos, $ofe);
                $this->prepararDatos($input, $request);
                $input['estado'] = 'ACTIVO';

                $input['pro_usuarios_recepcion'] = null;
                if($request->has('pro_usuarios_recepcion') && !empty($request->pro_usuarios_recepcion)) {
                    $proUsuariosRecepcion = json_decode($request->pro_usuarios_recepcion);
                    
                    $usuariosRecepcion = [];
                    foreach ($proUsuariosRecepcion as $usuarioRecepcion) {
                        $existe = User::where('usu_id', $usuarioRecepcion->usu_id)
                            ->where('bdd_id', $this->user->bdd_id)
                            ->where('estado', 'ACTIVO')
                            ->first();

                        if($existe) {
                            $usuariosRecepcion[] = [
                                'usu_id'             => $existe->usu_id,
                                'usu_identificacion' => $existe->usu_identificacion
                            ];
                        }
                    }

                    if(!empty($usuariosRecepcion))
                        $input['pro_usuarios_recepcion'] = json_encode($usuariosRecepcion);
                }

                //Si el Nit corresponde al DHL EXPRESS Y DHL EXPRESS AGENCIA, se debe enviar el valor de integracion ERP
                $input['pro_integracion_erp'] = null;
                if(in_array($request->ofe_identificacion, self::NIT_DHLEXPRESS)){
                    //Si en el request se envia valor para el campo pro_integracion_erp se guarda el valor enviado
                    if($request->has('pro_integracion_erp') && !empty($request->pro_integracion_erp)) {
                        if ($request->pro_integracion_erp == 'SI' || $request->pro_integracion_erp == 'NO') {
                            $input['pro_integracion_erp'] = $request->pro_integracion_erp;
                        } else {
                            $this->errors = $this->adicionarError($this->errors, ["El valor de Notificacion a Bassware no es valido."]);
                        }
                    } else {
                        //El sistema ingresara por esta opcion cuando se guarda el proveedor desde 
                        //otra opcion diferente a web, es decir, desde la carga de documentos recibidos en el 
                        //modulo de recepcion.
                        //Para la creacion del proveedor se realiza la siguiente logica:
                        //se debe buscar el NIT del proveedor en la tabla pry_correos_notificacion_basware 
                        //por el campo pro_identificacion, si este existe en la tabla se guarda SI, 
                        //de lo contrario se guara NO
                        $buscaProveedorBasware = PryCorreoNotificacionBasware::where('pro_identificacion', $request->pro_identificacion)
                            ->where('estado', 'ACTIVO')
                            ->first();
                        $input['pro_integracion_erp'] = !empty($buscaProveedorBasware) ? 'SI' : 'NO';
                    }
                }

                // Crea el proveedor
                $objProveedor = $this->className::create($input);

                if (empty($this->errors)) {
                    if ($objProveedor->pro_razon_social != '') {
                        $nombreProveedor = $objProveedor->pro_razon_social;
                    } else {
                        $nombreProveedor = $objProveedor->pro_primer_nombre . ' ' . $objProveedor->pro_otros_nombres . ' ' . $objProveedor->pro_primer_apellido . ' ' . $objProveedor->pro_segundo_apellido;
                    }

                    $datosProveedor['pro_id']             = $objProveedor->pro_id;
                    $datosProveedor['pro_identificacion'] = $objProveedor->pro_identificacion;
                    $datosProveedor['pro_correo']         = $objProveedor->pro_correo;
                    $datosProveedor['pro_razon_social']   = $nombreProveedor;
                    $datosProveedor['ofe_id']             = $objProveedor->ofe_id;

                    $this->asociarProveedorGrupoTrabajo($datosProveedor);

                    return response()->json([
                        'success' => true,
                        'pro_id'  => $objProveedor->pro_id
                    ], 201);
                } else {
                    return response()->json([
                        'message' => $this->getMensajeErrrorActualizarCrear('crear'),
                        'errors'  => $this->errors,
                        'pro_id'  => $objProveedor->pro_id
                    ], 400);
                }
            } else {
                return response()->json([
                    'message' => $this->getMensajeErrrorActualizarCrear('crear'),
                    'errors'  => $this->errors
                ], 400);
            }
        } else {
            return response()->json([
                'message' => $this->getMensajeErrrorActualizarCrear('crear'),
                'errors'  => $validador->errors()->all()
            ], 400);
        }
    }

    /**
     * Muestra el recurso especificado.
     *
     * @param int $id Identificador unico tomado desde la URL.
     * @return Response
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function show($id) {
        $arrRelaciones = [
            'getUsuarioCreacion',
            'getConfiguracionObligadoFacturarElectronicamente',
            'getParametroTipoDocumento',
            'getParametroPais',
            'getParametroDepartamento',
            'getParametroMunicipio',
            'getParametroTipoOrganizacionJuridica',
            'getRegimenFiscal',
            'getResponsabilidadFiscal:ref_id,ref_codigo,ref_descripcion,fecha_vigencia_desde,fecha_vigencia_hasta,estado',
            'getTiempoAceptacionTacita',
            'getCodigoPostal',
            'getParametroDomicilioFiscalPais',
            'getParametroDomicilioFiscalDepartamento',
            'getParametroDomicilioFiscalMunicipio',
            'getCodigoPostalDomicilioFiscal',
        ];
        return $this->procesadorShow($id, $arrRelaciones);
    }

    /**
     * Muestra el proveedor especificado por la identificación del ofe al que esta asociado y
     * la identifación del individuo u organización.
     *
     * @param $ofe_identificacion
     * @param $pro_identificacion
     * @return Response
     */
    public function showCompuesto($ofe_identificacion, $pro_identificacion) {
        $arrRelaciones = [
            'getUsuarioCreacion',
            'getConfiguracionObligadoFacturarElectronicamente',
            'getParametroTipoDocumento',
            'getParametroPais',
            'getParametroDepartamento',
            'getParametroMunicipio',
            'getParametroTipoOrganizacionJuridica',
            'getRegimenFiscal',
            'getResponsabilidadFiscal:ref_id,ref_codigo,ref_descripcion,fecha_vigencia_desde,fecha_vigencia_hasta,estado',
            'getTiempoAceptacionTacita',
            'getCodigoPostal',
            'getParametroDomicilioFiscalPais',
            'getParametroDomicilioFiscalDepartamento',
            'getParametroDomicilioFiscalMunicipio',
            'getCodigoPostalDomicilioFiscal',
        ];

        $master = [
            'property' => 'ofe_identificacion',
            'value' => $ofe_identificacion,
            'id_key' => 'ofe_id'
        ];

        $slave = [
            'property' => 'pro_identificacion',
            'value' => $pro_identificacion,
        ];

        $recursos = $this->getFiltroTipo();

        $objMaster = ConfiguracionObligadoFacturarElectronicamente::select([$master['id_key'], 'bdd_id_rg'])
            ->where($master['property'],  $master['value'])
            ->validarAsociacionBaseDatos()
            ->where('estado', 'ACTIVO')
            ->first();

        if ($objMaster === null) {
            return response()->json([
                'message' =>sprintf("El Oferente [%s] no existe.", $master['value']),
                'errors' => []
            ], Response::HTTP_NOT_FOUND);
        }

        if (!empty($arrRelaciones))
            $objetoModel = $this->className::with($arrRelaciones)
                ->where($master['id_key'],  $objMaster->{$master['id_key']})
                ->where($slave['property'],  $slave['value'])
                ->first();
        else
            $objetoModel = $this->className::where($master['id_key'],  $objMaster->{$master['id_key']})
                ->where($slave['property'],  $slave['value'])
                ->first();

        if ($objetoModel){
            $arrProveedor = $objetoModel->toArray();

            $responsabilidades_fiscales = [];
            if (!empty($objetoModel['ref_id'])) {
                $responsabilidades_fiscales = $this->listarResponsabilidadesFiscales($objetoModel['ref_id']);
            }
            $arrProveedor['get_responsabilidad_fiscal'] = $responsabilidades_fiscales;

            if($objetoModel['pro_usuarios_recepcion'] != '') {
                $usuariosRecepcion = [];
                foreach($objetoModel['pro_usuarios_recepcion'] as $usuarioRecepcion) {
                    $usuariosRecepcion[] = $usuarioRecepcion->usu_id;
                }

                // Obtiene los usuarios autorizados para gestión de documentos
                $usuariosRecepcion = User::select(['usu_id', 'usu_identificacion', 'usu_nombre', DB::raw('CONCAT(usu_identificacion, " - ", usu_nombre) as usuario')])
                        ->whereIn('usu_id', $usuariosRecepcion)
                        ->get();

                $arrProveedor['usuarios_recepcion'] = json_encode($usuariosRecepcion->toArray());
            }

            return response()->json([
                'data' => $arrProveedor
            ], Response::HTTP_OK);
        } else {
            return response()->json([
                'message' =>sprintf("El Proveedor [%s], para el Oferente [%s] no existe.", $slave['value'], $master['value']),
                'errors' => []
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'message' => sprintf($recursos['error_message'], $slave['value']),
            'errors' => []
        ], Response::HTTP_NOT_FOUND);
    }

    /**
     * Actualiza el recurso especificado.
     *
     * @param Request $request
     * @param string$identificacion Identificacion tomada desde la URL.
     * @return Response
     */
    public function update(Request $request, $ofe_identificacion, $pro_identificacion) {
        $this->errors = [];
        $this->user = auth()->user();
        $validador = Validator::make($request->all(), $this->className::$rulesUpdate);

        // Validando los datos enviados
        if ($validador->fails()) {
            return response()->json([
                'message' => 'Errores al actualizar el Proveedor',
                'errors' => $validador->errors()->all()
            ], 400);
        }

        $oferente = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'bdd_id_rg'])
            ->where('ofe_identificacion', $ofe_identificacion)
            ->validarAsociacionBaseDatos()
            ->first();
        if (!$oferente) {
            $this->errors = $this->adicionarError($this->errors, ["El Oferente con Idenficación [{$ofe_identificacion}] no existe"]);
        }

        $objProveedor = $this->className::select('pro_id', 'usuario_creacion', 'pro_integracion_erp', 'estado', 'ofe_id')
            ->where('ofe_id', $oferente->ofe_id)
            ->where('pro_identificacion', $pro_identificacion)
            ->first();

        if (!empty($objProveedor)) {
            if ($pro_identificacion !== $request->pro_identificacion) {
                $existeProveedor = $this->className::select('pro_id')
                    ->where('ofe_id', $request->ofe_id)
                    ->where('pro_identificacion', $request->pro_identificacion)
                    ->first();

                if (!empty($existeProveedor)) {
                    $this->errors = $this->adicionarError($this->errors, ['Ya exisete un Proveedor con el numero de identificacion [' . $request->pro_identificacion . '] para el OFE [' . $request->ofe_id . '].']);
                }
            }

            // Valida que las llaves foraneas existan
            $datos = $this->validarDatos($request);

            if (
                ($request->has('origen') && $request->origen == 'procesamiento_documentos') ||
                (
                    (!$request->has('origen') || ($request->has('origen') && empty($request->origen))) &&
                    empty($this->errors)
                )
            ) {
                $input = $request->all();
                $this->buildData($input, $datos, $oferente);
                $this->prepararDatos($input, $request);
                $input['estado'] = $request->has('estado') ? $request->estado : $objProveedor->estado;

                if($request->has('pro_usuarios_recepcion')) {
                    $input['pro_usuarios_recepcion'] = null;
                    if(!empty($request->pro_usuarios_recepcion)) {
                        $proUsuariosRecepcion = json_decode($request->pro_usuarios_recepcion);
                        
                        $usuariosRecepcion = [];
                        foreach ($proUsuariosRecepcion as $usuarioRecepcion) {
                            $existe = User::where('usu_id', $usuarioRecepcion->usu_id)
                                ->where('bdd_id', $this->user->bdd_id)
                                ->where('estado', 'ACTIVO')
                                ->first();

                            if($existe) {
                                $usuariosRecepcion[] = [
                                    'usu_id'             => $existe->usu_id,
                                    'usu_identificacion' => $existe->usu_identificacion
                                ];
                            }
                        }
                        if(!empty($usuariosRecepcion))
                            $input['pro_usuarios_recepcion'] = json_encode($usuariosRecepcion);
                    }
                }

                //Si el Nit corresponde al DHL EXPRESS Y DHL EXPRESS AGENCIA, se debe enviar si aplica o no
                //para el proveedor la integracion contra el ERP
                $input['pro_integracion_erp'] = null;
                if (in_array($request->ofe_identificacion, self::NIT_DHLEXPRESS)) {
                    if ($request->has('pro_integracion_erp') && !empty($request->pro_integracion_erp)) {
                        if ($request->pro_integracion_erp == 'SI' || $request->pro_integracion_erp == 'NO') {
                            $input['pro_integracion_erp'] = $request->pro_integracion_erp;
                        } else {
                            $this->errors = $this->adicionarError($this->errors, ["El valor de Notificacion a Bassware no es valido."]);
                        }
                    } else {
                        //El sistema ingresara por esta opcion cuando se guarda el proveedor desde 
                        //otra opcion diferente a web, es decir, desde la carga de documentos recibidos en el 
                        //modulo de recepcion.
                        //Si no se envia el campo pro_integracion_erp se respeta el valor guardado en la tabla,
                        //si este es diferente de null.
                        //Si el valor del campo pro_integracion_erp es null, se realiza la siguiente logica:
                        //se debe buscar el NIT del proveedor en la tabla pry_correos_notificacion_basware 
                        //por el campo pro_identificacion, si este existe en la tabla se guarda SI, 
                        //de lo contrario se guara NO
                        if ($objProveedor->pro_integracion_erp != 'null' && $objProveedor->pro_integracion_erp !== null) {
                            $input['pro_integracion_erp'] = $objProveedor->pro_integracion_erp;
                        } else {
                            $buscaProveedorBasware = PryCorreoNotificacionBasware::where('pro_identificacion', $request->pro_identificacion)
                                ->where('estado', 'ACTIVO')
                                ->first();
                            $input['pro_integracion_erp'] = !empty($buscaProveedorBasware) ? 'SI' : 'NO';
                        }
                    }
                }

                //actualizando data
                $objProveedor->update($input);

                if (empty($this->errors)) {
                    return response()->json([
                        'success' => true,
                        'pro_id'  => $objProveedor->pro_id
                    ], 200);
                } else {
                    return response()->json([
                        'message' => $this->getMensajeErrrorActualizarCrear('actualizar'),
                        'errors'  => $this->errors,
                        'pro_id'  => $objProveedor->pro_id
                    ], 400);
                }
            } else {
                return response()->json([
                    'message' => $this->getMensajeErrrorActualizarCrear('actualizar'),
                    'errors'  => $this->errors
                ], 400);
            }
        } else {
            return response()->json([
                'message' => $this->getMensajeErrrorActualizarCrear('actualizar'),
                'errors'  => ['El proveedor con identificacion [' . $pro_identificacion . '] no existe.']
            ], 400);
        }
    }

    /**
     * Permite editar un proveedor en funcion de un oferente
     *
     * @param Request $request
     * @param $ofe_identificacion
     * @param $pro_identificacion
     * @param bool $di
     * @return JsonResponse
     */
    public function updateCompuesto(Request $request, $ofe_identificacion, $pro_identificacion = true) {
        $this->errors = [];
        $this->user = auth()->user();

        $objMaster = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'bdd_id_rg'])
            ->where('ofe_identificacion',  $ofe_identificacion)
            ->validarAsociacionBaseDatos()
            ->where('estado', 'ACTIVO')
            ->first();

        if ($objMaster === null) {
            return response()->json([
                'message' => $this->getMensajeErrrorActualizarCrear('actualizar'),
                'errors' => [sprintf("El Oferente [%s] no existe.", $ofe_identificacion)]
            ], Response::HTTP_NOT_FOUND);
        }

        $objProveedor = $this->className::select('pro_id', 'pro_razon_social', 'pro_primer_nombre', 'pro_otros_nombres', 'pro_primer_apellido', 'pro_segundo_apellido', 'pro_identificacion', 'pro_correo', 'ofe_id', 'pro_integracion_erp', 'usuario_creacion', 'estado')
            ->where('ofe_id', $objMaster->ofe_id)
            ->where('pro_identificacion', $pro_identificacion)
            ->first();

        if (!empty($objProveedor)) {
            // Validando los datos enviados
            $validador = Validator::make($request->all(), $this->getRules($request));
            if ($validador->fails()) {
                return response()->json([
                    'message' => $this->getMensajeErrrorActualizarCrear('actualizar'),
                    'errors' => $validador->errors()->all()
                ], 400);
            }

            $camposCorreo = ['pro_correo','pro_correos_notificacion'];
            foreach ($camposCorreo as $campo) {
                $validatorEmail = $this->validationEmailRule($request->{$campo});
                if (!empty($validatorEmail['errors']))
                    $this->errors = array_merge($this->errors, $validatorEmail['errors']);
            }
            if (!empty($this->errors))
                return response()->json([
                    'message' => $this->getMensajeErrrorActualizarCrear('actualizar'),
                    'errors'  => $this->errors
                ], 404);

            if ($pro_identificacion !== $request->pro_identificacion) {
                $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id'])
                    ->where('ofe_identificacion', $request->ofe_identificacion)
                    ->validarAsociacionBaseDatos()
                    ->where('estado', 'ACTIVO')
                    ->first();

                if ($ofe === null) {
                    return response()->json([
                        'message' => $this->getMensajeErrrorActualizarCrear('actualizar'),
                        'errors' => [sprintf("El Oferente [%s] no existe.", $request->ofe_identificacion)]
                    ], Response::HTTP_NOT_FOUND);
                }

                $existeProveedor = $this->className::select('pro_id')
                    ->where('ofe_id', $ofe->ofe_id)
                    ->where('pro_identificacion', $request->pro_identificacion)
                    ->first();

                if (!empty($existeProveedor)) {
                    return response()->json([
                        'message' => $this->getMensajeErrrorActualizarCrear('actualizar'),
                        'errors' =>['Ya existe un Proveedor con el numero de identificacion [' . $request->pro_identificacion . '] para el OFE [' . $ofe_identificacion . '].']
                    ], Response::HTTP_NOT_FOUND);
                }
            }
            else
                $ofe = $objMaster;

            // Valida que las llaves foraneas existan
            $datos = $this->validarDatos($request);
            if (
                ($request->has('origen') && $request->origen == 'procesamiento_documentos') ||
                (
                    (!$request->has('origen') || ($request->has('origen') && empty($request->origen))) &&
                    empty($this->errors)
                )
            ) {
                $input = $request->all();
                $this->buildData($input, $datos, $ofe);
                $this->prepararDatos($input, $request);
                $input['estado'] = $request->has('estado') ? $request->estado : $objProveedor->estado;
                if(array_key_exists('usuario_creacion', $input))
                    unset($input['usuario_creacion']);

                if($request->has('pro_usuarios_recepcion')) {
                    $input['pro_usuarios_recepcion'] = null;
                    if(!empty($request->pro_usuarios_recepcion)) {
                        $proUsuariosRecepcion = json_decode($request->pro_usuarios_recepcion);
                        
                        $usuariosRecepcion = [];
                        foreach ($proUsuariosRecepcion as $usuarioRecepcion) {
                            $existe = User::where('usu_id', $usuarioRecepcion->usu_id)
                                ->where('bdd_id', $this->user->bdd_id)
                                ->where('estado', 'ACTIVO')
                                ->first();

                            if($existe) {
                                $usuariosRecepcion[] = [
                                    'usu_id'             => $existe->usu_id,
                                    'usu_identificacion' => $existe->usu_identificacion
                                ];
                            }
                        }
                        if(!empty($usuariosRecepcion))
                            $input['pro_usuarios_recepcion'] = json_encode($usuariosRecepcion);
                    }
                }

                //Si el Nit corresponde al DHL EXPRESS Y DHL EXPRESS AGENCIA, se debe enviar si aplica o no
                //para el proveedor la integracion contra el ERP
                $input['pro_integracion_erp'] = null;
                if (in_array($request->ofe_identificacion, self::NIT_DHLEXPRESS)) {
                    //Si en el request se envia valor para el campo pro_integracion_erp se guarda el valor enviado
                    if ($request->has('pro_integracion_erp') && !empty($request->pro_integracion_erp)) {
                        if ($request->pro_integracion_erp == 'SI' || $request->pro_integracion_erp == 'NO') {
                            $input['pro_integracion_erp'] = $request->pro_integracion_erp;
                        } else {
                            $this->errors = $this->adicionarError($this->errors, ["El valor de Notificacion a Bassware no es valido."]);
                        }
                    } else {
                        //El sistema ingresara por esta opcion cuando se guarda el proveedor desde 
                        //otra opcion diferente a web, es decir, desde la carga de documentos recibidos en el 
                        //modulo de recepcion.
                        //Si no se envia el campo pro_integracion_erp se respeta el valor guardado en la tabla,
                        //si este es diferente de null.
                        //Si el valor del campo pro_integracion_erp es null, se realiza la siguiente logica:
                        //se debe buscar el NIT del proveedor en la tabla pry_correos_notificacion_basware 
                        //por el campo pro_identificacion, si este existe en la tabla se guarda SI, 
                        //de lo contrario se guara NO
                        if ($objProveedor->pro_integracion_erp != 'null' && $objProveedor->pro_integracion_erp !== null) {
                            $input['pro_integracion_erp'] = $objProveedor->pro_integracion_erp;
                        } else {
                            $buscaProveedorBasware = PryCorreoNotificacionBasware::where('pro_identificacion', $request->pro_identificacion)
                                ->where('estado', 'ACTIVO')
                                ->first();
                            $input['pro_integracion_erp'] = !empty($buscaProveedorBasware) ? 'SI' : 'NO';
                        }
                    }
                }
                //actualizando data
                $objProveedor->update($input);

                if (empty($this->errors)) {
                    if ($objProveedor->pro_razon_social != '') {
                        $nombreProveedor = $objProveedor->pro_razon_social;
                    } else {
                        $nombreProveedor = $objProveedor->pro_primer_nombre . ' ' . $objProveedor->pro_otros_nombres . ' ' . $objProveedor->pro_primer_apellido . ' ' . $objProveedor->pro_segundo_apellido;
                    }

                    $datosProveedor['pro_id']             = $objProveedor->pro_id;
                    $datosProveedor['pro_identificacion'] = $objProveedor->pro_identificacion;
                    $datosProveedor['pro_correo']         = $objProveedor->pro_correo;
                    $datosProveedor['pro_razon_social']   = $nombreProveedor;
                    $datosProveedor['ofe_id']             = $objProveedor->ofe_id;

                    $this->asociarProveedorGrupoTrabajo($datosProveedor, false);

                    return response()->json([
                        'success' => true,
                        'pro_id'  => $objProveedor->pro_id
                    ], 200);
                } else {
                    return response()->json([
                        'message' => $this->getMensajeErrrorActualizarCrear('actualizar'),
                        'errors'  => $this->errors,
                        'pro_id'  => $objProveedor->pro_id
                    ], 400);
                }
            } else {
                return response()->json([
                    'message' => $this->getMensajeErrrorActualizarCrear('actualizar'),
                    'errors'  => $this->errors
                ], 400);
            }
        } else {
            return response()->json([
                'message' => $this->getMensajeErrrorActualizarCrear('actualizar'),
                'errors'  => ['El Proveedor con identificacion [' . $pro_identificacion . '] no existe.']
            ], 400);
        }
    }

    /**
     * Prepara la data que va ser insertada o actualizada
     *
     * @param array $data
     * @param Request $request
     * @return void
     */
    private function prepararDatos(array &$data, $request) {
        $data['usuario_creacion'] = $this->user->usu_id;

        switch ($request->toj_codigo) {
            //Este case solo se cumple cuando es organizacion JURIDICA.
            case '1':
                $data['pro_razon_social'] = $data['pro_razon_social'];
                $data['pro_nombre_comercial'] = ($data['pro_nombre_comercial'] == "" ? $data['pro_razon_social'] : $data['pro_nombre_comercial']);
                $data['pro_primer_nombre'] = NULL;
                $data['pro_otros_nombres'] = NULL;
                $data['pro_primer_apellido'] = NULL;
                $data['pro_segundo_apellido'] = NULL;
                break;
            default:
                $data['pro_razon_social'] = NULL;
                $data['pro_nombre_comercial'] = NULL;
                $data['pro_primer_nombre'] = $data['pro_primer_nombre'];
                $data['pro_otros_nombres'] = $data['pro_otros_nombres'];
                $data['pro_primer_apellido'] = $data['pro_primer_apellido'];
                $data['pro_segundo_apellido'] = $data['pro_segundo_apellido'];
                break;
        }
    }

    /**
     * Obtiene el titulo para la Identificacion del Proveedor.
     *
     * @return String
     */
    private function getTituloIdentificacion(){
        $tipo = $this->getTituloCorrecto();
        return 'NIT '.substr(strtoupper($tipo['title']), 0, strlen($tipo['title']) - 2);
    }

    /**
     * Devuelve una lista paginada de proveedores registrados
     *
     * @param Request $request
     * @return Response
     * @throws \Exception
     */
    public function getListaProveedores(Request $request) {
        $user = auth()->user();

        $tipo = $this->getTituloCorrecto();
        $columnas = [
            'pro_id',
            'pro_identificacion',
            'pro_id_personalizado',
            'pro_razon_social',
            'pro_nombre_comercial',
            'pro_primer_apellido',
            'pro_segundo_apellido',
            'pro_primer_nombre',
            'pro_otros_nombres',
            'tdo_id',
            'toj_id',
            'pai_id',
            'dep_id',
            'mun_id',
            'ofe_id',
            'cpo_id',
            'pro_direccion',
            'pro_telefono',
            'pai_id_domicilio_fiscal',
            'dep_id_domicilio_fiscal',
            'mun_id_domicilio_fiscal',
            'cpo_id_domicilio_fiscal',
            'pro_direccion_domicilio_fiscal',
            'pro_correo',
            'rfi_id',
            'ref_id',
            'pro_matricula_mercantil',
            'pro_correos_notificacion',
            'pro_usuarios_recepcion',
            'fecha_creacion',
            'fecha_modificacion',
            'usuario_creacion',
            'estado',
            \DB::raw('IF(pro_razon_social IS NULL OR pro_razon_social = "", CONCAT(pro_primer_nombre, " ", pro_otros_nombres, " ", pro_primer_apellido, " ", pro_segundo_apellido), pro_razon_social) as nombre_completo'),
        ];

        $relaciones = [
            'getUsuarioCreacion',
            'getConfiguracionObligadoFacturarElectronicamente',
            'getParametroTipoDocumento',
            'getParametroPais',
            'getParametroDepartamento',
            'getParametroMunicipio',
            'getParametroTipoOrganizacionJuridica',
            'getRegimenFiscal',
            'getResponsabilidadFiscal:ref_id,ref_codigo,ref_descripcion,fecha_vigencia_desde,fecha_vigencia_hasta,estado',
            'getTiempoAceptacionTacita',
            'getCodigoPostal',
            'getParametroDomicilioFiscalPais',
            'getParametroDomicilioFiscalDepartamento',
            'getParametroDomicilioFiscalMunicipio',
            'getCodigoPostalDomicilioFiscal',
            'getUsuariosPortales:upp_id,ofe_id,pro_id,upp_identificacion,upp_nombre,upp_correo,estado'
        ];

        $whereHasConditions = [];
        if(!empty($user->bdd_id_rg)) {
            $whereHasConditions[] = [
                'relation' => 'getConfiguracionObligadoFacturarElectronicamente',
                'function' => function($query) use ($user) {
                    $query->where('bdd_id_rg', $user->bdd_id_rg);
                }
            ];
        } else {
            $whereHasConditions[] = [
                'relation' => 'getConfiguracionObligadoFacturarElectronicamente',
                'function' => function($query){
                    $query->whereNull('bdd_id_rg');
                }
            ];
        }

        $exportacion = [
            'columnas' => [
                'ofe_id' => [
                    'label' => 'NIT OFE',
                    'relation' => ['name' => 'getConfiguracionObligadoFacturarElectronicamente', 'field' => 'ofe_identificacion']
                ],
                'pro_identificacion' => $this->getTituloIdentificacion(),
                'pro_id_personalizado' => 'ID PERSONALIZADO',
                'pro_razon_social' => 'RAZON SOCIAL',
                'pro_nombre_comercial' => 'NOMBRE COMERCIAL',
                'pro_primer_apellido' => 'PRIMER APELLIDO',
                'pro_segundo_apellido' => 'SEGUNDO APELLIDO',
                'pro_primer_nombre' => 'PRIMER NOMBRE',
                'pro_otros_nombres' => 'OTROS NOMBRES',
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
                    'relation' => 'getParametroTipoOrganizacionJuridica',
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
                    'relation' => 'getParametroPais',
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
                    'relation' => 'getParametroDepartamento',
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
                    'relation' => 'getParametroMunicipio',
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
                'pro_direccion' => 'DIRECCION',
                'pro_telefono' => 'TELEFONO',
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
                'pro_direccion_domicilio_fiscal' => 'DIRECCION DOMICILIO FISCAL',
                'pro_correo' => 'CORREO',
                'rfi_id' => [
                    'multiple' => true,
                    'relation' => 'getRegimenFiscal',
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
                'pro_matricula_mercantil' => 'MATRICULA MERCANTIL',
                'pro_correos_notificacion' => 'CORREOS NOTIFICACION',
                'pro_usuarios_recepcion' => [
                    'label' => 'USUARIOS GESTION DOCUMENTOS RECIBIDOS',
                    'type' => self::TYPE_ARRAY_OBJECTS
                ],
                'pro_usuarios_portales' => [
                    'callback' => true,
                    'relation' => 'getUsuariosPortales',
                    'label' => 'PORTAL PROVEEDORES',
                    'fields' => [
                        [
                            'field' => 'estado'
                        ]
                    ],
                    'function' => function ($extraDatos){
                        $activos = Arr::where($extraDatos, function($value, $key) {
                            return $value == 'ACTIVO';
                        });
                        
                        return count($activos) > 0 ? 'SI' : 'NO';
                    }
                ]
            ],
            'titulo' => $tipo['title']
        ];

        if ($request->has('excel') && ($request->excel || $request->excel == 'true'))
            return $this->procesadorTracking($request, [], $columnas, $relaciones, $exportacion, false, $whereHasConditions, 'recepcion');
        else {
            $data = $this->procesadorTracking($request, [], $columnas, $relaciones, $exportacion, true, $whereHasConditions, 'recepcion');
            
            // Cantidad de usuarios de portal proveedores admitidos
            TenantTrait::GetVariablesSistemaTenant();
            $data['cantidad_usuarios_portal_proveedores'] = config('variables_sistema_tenant.CANTIDAD_USUARIOS_PORTAL_PROVEEDORES');
            return response()->json($data, Response::HTTP_OK);
        }
    }

    /**
     * Cambia el estado de los registros.
     *
     * @param Request $request Parámetros de la petición
     * @return Response
     *
     */
    public function cambiarEstado(Request $request) {
        return $this->procesadorCambiarEstadoCompuesto(
            ConfiguracionObligadoFacturarElectronicamente::class,
            'ofe_id',
            'ofe_identificacion',
            'pro_identificacion',
            'pro_identificacion',
            $request->all(),
            'El OFE [%s] no existe o se encuentra INACTIVO.'
        );
    }

    /**
     * Arma una consulta de proveedores en relación con el usuario autenticado
     * 
     * @param Request $request
     * @param boolean $asignados Indica si se debe buscar solamente en los proveedores en donde el usuario autenticado este asignado
     * @return Collection
     */
    private function armarConsultaProveedores(Request $request, $asignados = true) {
        $user        = auth()->user();
        $proveedores = $this->className::select(
            [
                'pro_id',
                'pro_identificacion',
                'pro_razon_social',
                'pro_nombre_comercial',
                'pro_primer_apellido',
                'pro_segundo_apellido',
                'pro_primer_nombre',
                'pro_otros_nombres',
                \DB::raw('IF(pro_razon_social IS NULL OR pro_razon_social = "", CONCAT(COALESCE(pro_primer_nombre, ""), " ", COALESCE(pro_otros_nombres, ""), " ", COALESCE(pro_primer_apellido, ""), " ", COALESCE(pro_segundo_apellido, "")), pro_razon_social) as nombre_completo')
            ]);

        if ($request->filled('ofe_id')) {
            $proveedores->where('ofe_id', $request->ofe_id);
        }
        
        $proveedores->where('estado', 'ACTIVO');

        if ($request->has('ofe_id') && !empty($request->ofe_id)) {
            $proveedores->where('ofe_id', $request->ofe_id);
        }

        $permisosUser = [];
        $user->usuarioPermisos()->map(function ($rol) use (&$permisosUser) {
            $permisosUser[] = $rol->rec_alias;
        });

        if (!$user->esSuperadmin()) {
            if (!in_array('ConfiguracionGrupoTrabajoProveedorNuevo', $permisosUser)) {
                // Verifica si el usuario autenticado se encuentra parametrizado a nivel de grupos de trabajo
                // de ser así, solamente se deben listar los proveedores que se encuentren en el mismo grupo de trabajo
                $gruposTrabajoUsuario = ConfiguracionGrupoTrabajoUsuario::select(['gtr_id'])
                    ->where('usu_id', $user->usu_id)
                    ->where('estado', 'ACTIVO')
                    ->get()
                    ->pluck('gtr_id')
                    ->toArray();

                if(!empty($gruposTrabajoUsuario)) {
                    $proveedores->whereHas('getProveedorGruposTrabajo', function($query) use ($gruposTrabajoUsuario) {
                        $query->whereIn('gtr_id', $gruposTrabajoUsuario)
                            ->where('estado', 'ACTIVO');
                    });
                } elseif(empty($gruposTrabajoUsuario) && $asignados) {
                    $proveedores->where(function ($query) use ($user) {
                        $query->where('pro_usuarios_recepcion', 'like', '%"' . $user->usu_identificacion . '"%');
                    });
                }
            }
        }

        $proveedores->where(function ($query) use ($request) {
            $query->where('pro_razon_social', 'like', '%' . $request->buscar . '%')
                ->orWhere('pro_nombre_comercial', 'like', '%' . $request->buscar . '%')
                ->orWhere('pro_primer_apellido', 'like', '%' . $request->buscar . '%')
                ->orWhere('pro_segundo_apellido', 'like', '%' . $request->buscar . '%')
                ->orWhere('pro_primer_nombre', 'like', '%' . $request->buscar . '%')
                ->orWhere('pro_otros_nombres', 'like', '%' . $request->buscar . '%')
                ->orWhere('pro_identificacion', 'like', '%' . $request->buscar . '%')
                ->orWhereRaw("REPLACE(CONCAT(COALESCE(pro_primer_nombre, ''), ' ', COALESCE(pro_otros_nombres, ''), ' ', COALESCE(pro_primer_apellido, ''), ' ', COALESCE(pro_segundo_apellido, '')), '  ', ' ') like ?", ['%' . $request->buscar . '%']);
        });

        return $proveedores;
    }

    /**
     * Obtiene los proveedores en busqueda predictiva dado un Ofe_Id y el término a buscar
     *
     * @param Request $request
     * @return Response
     */
    public function searchProveedores(Request $request) {
        $proveedores = $this->armarConsultaProveedores($request, true);
        $proveedores = $proveedores->get();

        if($proveedores->count() == 0) {
            $proveedores = $this->armarConsultaProveedores($request, false);
            $proveedores = $proveedores->get();
        }

        return response()->json([
            'data' => $proveedores
        ], 200);
    }

    /**
     * Responde con el titulo Correcto segun el menu desde cual se realiza la request.
     *
     * @return array
     */
    private function getTituloCorrecto() {
        return  [
            'title' => ConfiguracionProveedorController::PROVEEDORES
        ];
    }

    /**
     * Genera una Interfaz de Proveedores para guardar en Excel.
     *
     * @return App\Http\Modulos\Utils\ExcelExports\ExcelExport
     */
    public function generarInterfaceProveedores(Request $request) {
        $this->user = auth()->user();

        $titulo = $this->getTituloCorrecto();
        return $this->generarInterfaceToTenant($this->columnasExcel, $titulo['title']);
    }

    /**
     * Toma una interfaz en Excel y la almacena en la tabla de Proveedores.
     *
     * @param Request $request
     * @return Response
     * @throws \Exception
     */
    function cargarProveedores(Request $request) {
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        $this->user = auth()->user();

        $titulo = $this->getTituloCorrecto();

        if ($request->hasFile('archivo')) {
            $archivo = explode('.', $request->file('archivo')->getClientOriginalName());
            if (
                (!empty($request->file('archivo')->extension()) && !in_array($request->file('archivo')->extension(), $this->arrExtensionesPermitidas)) ||
                !in_array($archivo[count($archivo) - 1], $this->arrExtensionesPermitidas)
            )
                return response()->json([
                    'message' => 'Errores al guardar los ' . ucwords($titulo['title']),
                    'errors'  => ['Solo se permite la carga de archivos EXCEL.']
                ], 409);

            $columnas     = [];
            $tempColumnas = [];
            $data         = $this->parserExcel($request);
            if (!empty($data)) {
                // Se obtinen las columnas de la interfaz sanitizadas
                foreach ($this->columnasExcel as $k) {
                    $tempColumnas[] = strtolower($this->sanear_string(str_replace(' ', '_', $k)));
                }

                // Se obtienen las columnas del excel cargado
                foreach ($data as $fila => $columna) {
                    // Se eliminan las columnas que son propias del excel generado desde el Tracking
                    unset($columna['portal_proveedores']);

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
                    'message' => 'Errores al guardar los ' . ucwords($titulo['title']),
                    'errors' => ['El archivo subido no tiene datos.']
                ], 400);
            }

            // Recorre toda la data para verificar que cada fila tenga las columnas correspondientes asi estén vacias
            // Esto es porque el método parserExcel() al leer el archivo puede retornar filas con columnas faltantes oir estar vacias
            foreach ($data as $fila => $columnas) {
                foreach($tempColumnas as $colInterfaz) {
                    if(!array_key_exists($colInterfaz, $columnas))
                        $data[$fila][$colInterfaz] = '';
                }
            }

            $arrErrores = [];
            $arrResultado = [];
            $arrExisteOfe = [];
            $arrExistePais = [];
            $arrExistePaisDepto = [];
            $arrExisteMunDepto = [];
            $arrExisteTipoDocumento = [];
            $arrExisteRegimenFiscal = [];
            $arrExisteTipoOrgJuridica = [];
            $arrProveedorInterOperabilidad = [];
            $arrProvIds = [];
            $arrProveedorProceso = [];
            $arrExisteResponsabilidadesFiscales = [];
            $arrExisteCodigoPostal = [];

            ParametrosResponsabilidadFiscal::where('ref_aplica_para', 'LIKE', '%DE%')
                ->where('estado', 'ACTIVO')
                ->get()
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

            ParametrosCodigoPostal::where('estado', 'ACTIVO')->select('cpo_id', 'cpo_codigo', 'fecha_vigencia_desde', 'fecha_vigencia_hasta')->get()
                ->groupBy('cpo_codigo')
                ->map(function ($cpo) use (&$arrExisteCodigoPostal) {
                    $vigente = $this->validarVigenciaRegistroParametrica($cpo);
                    if ($vigente['vigente']) {
                        $arrExisteCodigoPostal[$vigente['registro']->cpo_codigo] = $vigente['registro'];
                    }
                });

            $campo_identificacion = strtolower(str_replace(' ','_', $this->getTituloIdentificacion()));

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
                    $arrProveedor = [];

                    $campos = [
                        'nit_ofe',
                        $campo_identificacion,
                        'codigo_tipo_organizacion_juridica',
                        'codigo_tipo_documento'
                    ];


                    if (empty($columnas->codigo_departamento)){
                        $columnas->codigo_departamento == '';
                    }

                    if ($columnas->codigo_pais == 'CO') {
                        array_push($campos, 'codigo_departamento');
                    }

                    $arrFaltantes = $this->checkFields($Acolumnas, $campos, $fila);

                    if (!empty($arrFaltantes)) {
                        $vacio = $this->revisarArregloVacio($columnas);

                        if ($vacio) {
                            $arrErrores = $this->adicionarError($arrErrores, $arrFaltantes, $fila);
                        } else {
                            unset($data[$fila]);
                        }
                    } else {
                        $arrProveedor['ofe_identificacion'] = $columnas->nit_ofe;
                        $pro_identificacion = $this->sanitizarStrings($columnas->{$campo_identificacion});
                        if (array_key_exists($pro_identificacion, $arrProveedorProceso)) {
                            $arrErrores = $this->adicionarError($arrErrores, ['EL Nit del ' . ucwords($titulo['title']) . ' ' . $pro_identificacion . ' ya existe en otras filas.'], $fila);
                        } else {
                            $arrProveedorProceso[$pro_identificacion] = true;
                        }

                        //nit_ofe
                        if (array_key_exists($columnas->nit_ofe, $arrExisteOfe)) {
                            $objExisteOfe = $arrExisteOfe[$columnas->nit_ofe];
                        } else {
                            $objExisteOfe = ConfiguracionObligadoFacturarElectronicamente::where('ofe_identificacion', $columnas->nit_ofe)
                                ->validarAsociacionBaseDatos()
                                ->where('estado', 'ACTIVO')
                                ->first();
                            $arrExisteOfe[$columnas->nit_ofe] = $objExisteOfe;
                        }

                        if ($objExisteOfe) {
                            $arrProveedor['ofe_id'] = $objExisteOfe->ofe_id;

                            $objExisteProveedor = $this->className::where('ofe_id', $objExisteOfe->ofe_id)
                                ->where('pro_identificacion', $pro_identificacion)
                                ->first();

                            if (!empty($objExisteProveedor)) {
                                if ($objExisteProveedor->estado == 'INACTIVO') {
                                    $arrErrores = $this->adicionarError($arrErrores, ['No se permiten actualizar registros en estado INACTIVO.'], $fila);
                                } else {
                                    $arrProveedor['pro_id'] = $objExisteProveedor->pro_id;
                                }
                            } else {
                                $arrProveedor['pro_id'] = 0;
                            }

                            $arrProveedor['pro_identificacion'] = (string)$this->sanitizarStrings($columnas->{$campo_identificacion});
                            $arrProveedor['pro_id_personalizado'] = (isset($columnas->id_personalizado) && !empty($columnas->id_personalizado)) ? (string)$columnas->id_personalizado : null;

                        } else {
                            $arrErrores = $this->adicionarError($arrErrores, ['El Id del OFE [' . $columnas->nit_ofe . '] no existe.'], $fila);
                        }

                        if (!preg_match("/^[1-9]/", $columnas->{$campo_identificacion}) && 
                            ($columnas->codigo_tipo_documento == '13' || $columnas->codigo_tipo_documento == '31')
                        ) {
                            $arrErrores = $this->adicionarError($arrErrores, ['El formato del campo Identificación del Proveedor es inválido.'], $fila);
                        }

                        $arrProveedor['ref_id'] = '';
                        $arrResFiscales = [];
                        if (!empty($columnas->codigo_responsabilidades_fiscales)) {
                            $codigos_responsabilidades_fiscales = explode(';', $columnas->codigo_responsabilidades_fiscales);
                            foreach ($codigos_responsabilidades_fiscales as $codigo) {
                                if (array_key_exists($codigo, $arrExisteResponsabilidadesFiscales)) {
                                    $objExisteResFiscal = $arrExisteResponsabilidadesFiscales[$codigo];
                                    $arrResFiscales[]   = $objExisteResFiscal->ref_codigo;
                                } else {
                                    $arrErrores = $this->adicionarError($arrErrores, ['El Código de la responsabilidad fiscal ['.$codigo.'], ya no está vigente, no aplica para Documento Electrónico, se encuentra INACTIVO o no existe.'], $fila);
                                }
                            }

                            if (!empty($arrResFiscales)){
                                $arrProveedor['ref_id'] = implode(';',$arrResFiscales);
                            }
                        }

                        $arrProveedor['cpo_id'] = null;
                        if (!empty($columnas->codigo_postal)) {
                            if (array_key_exists($columnas->codigo_postal, $arrExisteCodigoPostal)) {
                                $objExisteCodigoPostal = $arrExisteCodigoPostal[$columnas->codigo_postal];
                                $arrProveedor['cpo_id'] = $objExisteCodigoPostal->cpo_id;
                            } elseif ($columnas->codigo_postal == $columnas->codigo_departamento . $columnas->codigo_municipio) {
                                $arrProveedor['cpo_id'] = null;
                            } else {
                                $arrErrores = $this->adicionarError($arrErrores, ['El Código Postal [' . $columnas->codigo_postal . '], ya no está vigente, se encuentra INACTIVO o no existe.'], $fila);
                            }
                        }

                        $arrProveedor['cpo_id_domicilio_fiscal'] = null;
                        if (!empty($columnas->codigo_postal_domicilio_fiscal)) {
                            if (array_key_exists($columnas->codigo_postal_domicilio_fiscal, $arrExisteCodigoPostal)) {
                                $objExisteCodigoPostal = $arrExisteCodigoPostal[$columnas->codigo_postal_domicilio_fiscal];
                                $arrProveedor['cpo_id_domicilio_fiscal'] = $objExisteCodigoPostal->cpo_id;
                            } elseif ($columnas->codigo_postal_domicilio_fiscal == $columnas->codigo_departamento_domicilio_fiscal . $columnas->codigo_municipio_domicilio_fiscal) {
                                $arrProveedor['cpo_id_domicilio_fiscal'] = null;
                            } else {
                                $arrErrores = $this->adicionarError($arrErrores, ['El Código Postal del Domicilio Fiscal [' . $columnas->codigo_postal_domicilio_fiscal . '], ya no está vigente, se encuentra INACTIVO o no existe.'], $fila);
                            }
                        }

                        // DIRECCIÓN CORRESPONDENCIA
                        $arrProveedor['pai_id'] = null;
                        $arrProveedor['dep_id'] = null;
                        $arrProveedor['mun_id'] = null;
                        // Si llega el pais, ciudad o direccion, los demas campos del apartado de domicilio son obligatorios
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
                                $arrProveedor['pai_codigo'] = $columnas->codigo_pais ?? 0;
                                if (isset($columnas->codigo_pais)) {
                                    if (array_key_exists($columnas->codigo_pais, $arrExistePais))
                                        $objExistePais = $arrExistePais[$columnas->codigo_pais];
                                    else {
                                        $objExistePais = ParametrosPais::where('pai_codigo', $columnas->codigo_pais)
                                            ->first();
                                        $arrExistePais[$columnas->codigo_pais] = $objExistePais;
                                    }
                                }

                                $arrProveedor['cpo_codigo'] = $columnas->codigo_postal;
                                $arrProveedor['dep_codigo'] = $columnas->codigo_departamento;
                                $arrProveedor['mun_codigo'] = $columnas->codigo_municipio;
                                if (isset($objExistePais)) {
                                    $arrProveedor['pai_id'] = $objExistePais->pai_id;

                                    if (isset($columnas->codigo_departamento)) {
                                        if (array_key_exists($columnas->codigo_pais . '-' . $columnas->codigo_departamento, $arrExistePaisDepto)) {
                                            $objExistePaisDepto = $arrExistePaisDepto[$columnas->codigo_pais . '-' . $columnas->codigo_departamento];
                                        } else {
                                            $objExistePaisDepto = ParametrosDepartamento::where('pai_id', $objExistePais->pai_id)
                                                ->where('dep_codigo', $columnas->codigo_departamento)->first();
                                            $arrExistePaisDepto[$columnas->codigo_pais . '-' . $columnas->codigo_departamento] = $objExistePaisDepto;
                                        }
                                    }

                                    if (isset($objExistePaisDepto) || $columnas->codigo_pais != 'CO') {

                                        $objExisteMunDepto = null;
                                        if (!empty($objExistePaisDepto)) {
                                            $arrProveedor['dep_id'] = $objExistePaisDepto->dep_id;
                                        }
                                        $indice = $columnas->codigo_pais . '-' . ($columnas->codigo_departamento ?? '') . '-' . $columnas->codigo_municipio;
                                        if (array_key_exists($indice, $arrExisteMunDepto)) {
                                            $objExisteMunDepto = $arrExisteMunDepto[$indice];
                                        } else {
                                            $objExisteMunDepto = ParametrosMunicipio::where('estado', 'ACTIVO')
                                                ->where('pai_id', $objExistePais->pai_id)
                                                ->where('mun_codigo', $columnas->codigo_municipio);

                                            if (!empty($objExistePaisDepto)) {
                                                $objExisteMunDepto->where('dep_id', ($objExistePaisDepto->dep_id ?? '') );
                                            }
                                            $objExisteMunDepto = $objExisteMunDepto->first();

                                            $arrExisteMunDepto[$indice] = $objExisteMunDepto;
                                        }

                                        if (!empty($objExisteMunDepto)) {
                                            $arrProveedor['mun_id'] = $objExisteMunDepto->mun_id;
                                        } else {
                                            $arrErrores = $this->adicionarError($arrErrores, ['El Id del Municipio [' . $columnas->codigo_municipio . '] no existe para el Departamento [' . $columnas->codigo_departamento . '].'], $fila);
                                        }
                                    }
                                }
                            }
                        }

                        //DOMICILIO FISCAL
                        $arrProveedor['pai_id_domicilio_fiscal'] = null;
                        $arrProveedor['dep_id_domicilio_fiscal'] = null;
                        $arrProveedor['mun_id_domicilio_fiscal'] = null;
                        // Si llega el pais, departamento, ciudad o direccion fiscal, el àís y dirección del apartado de domicilio fiscal son obligatorios
                        if(
                            (isset($columnas->codigo_pais_domicilio_fiscal) && $columnas->codigo_pais_domicilio_fiscal != '') ||
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

                                if (!empty($objExistePaisDomFiscal)) {
                                    $arrProveedor['pai_id_domicilio_fiscal'] = $objExistePaisDomFiscal->pai_id;

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
                                            $arrProveedor['dep_id_domicilio_fiscal'] = $objExistePaisDeptoDomFiscal->dep_id ?? '';
                                        }
                                        $indice = $columnas->codigo_pais_domicilio_fiscal . '-' . ($columnas->codigo_departamento_domicilio_fiscal ?? '') . '-' . $columnas->codigo_municipio_domicilio_fiscal;

                                        if (array_key_exists($indice, $arrExisteMunDepto)) {
                                            $objExisteMunDeptoDomFiscal = $arrExisteMunDepto[$indice];
                                        } else {
                                            $objExisteMunDeptoDomFiscal = ParametrosMunicipio::where('estado', 'ACTIVO')->where('mun_codigo', $columnas->codigo_municipio_domicilio_fiscal);
                                            if (!empty($objExistePaisDepto)) {
                                                $objExisteMunDeptoDomFiscal->where('dep_id', ($objExistePaisDepto->dep_id ?? '') );
                                            }
                                            $objExisteMunDeptoDomFiscal = $objExisteMunDeptoDomFiscal->first();

                                            $arrExisteMunDepto[$indice] = $objExisteMunDeptoDomFiscal;
                                        }
                                        if (isset($objExisteMunDeptoDomFiscal)) {
                                            $arrProveedor['mun_id_domicilio_fiscal'] = $objExisteMunDeptoDomFiscal->mun_id;
                                        } else {
                                            $arrErrores = $this->adicionarError($arrErrores, ['El Id del Municipio del Domicilio Fiscal [' . $columnas->codigo_municipio_domicilio_fiscal . '] no existe para el Departamento Del Domicilio Fiscal [' . $columnas->codigo_departamento_domicilio_fiscal . '].'], $fila);
                                        }
                                    }
                                } else {
                                    $arrErrores = $this->adicionarError($arrErrores, ['El Id del País Del Domicilio Fiscal [' . $columnas->codigo_pais_domicilio_fiscal . '] no existe'], $fila);
                                }
                            }
                        }
                        //FIN DE DOMICILIO FISCAL

                        $arrProveedor['tdo_codigo'] = $columnas->codigo_tipo_documento;
                        if (array_key_exists($columnas->codigo_tipo_documento, $arrExisteTipoDocumento)) {
                            $objExisteTipoDocumento = $arrExisteTipoDocumento[$columnas->codigo_tipo_documento];
                            $arrProveedor['tdo_id'] = $objExisteTipoDocumento->tdo_id;
                        } else {
                            $arrErrores = $this->adicionarError($arrErrores, ['El Código del Tipo de Documento [' . $columnas->codigo_tipo_documento . '], ya no está vigente, no aplica para Documento Electrónico, se encuentra INACTIVO o no existe.'], $fila);
                        }

                        $arrProveedor['rfi_id'] = null;
                        if (!empty($columnas->codigo_regimen_fiscal)) {
                            if (array_key_exists($columnas->codigo_regimen_fiscal, $arrExisteRegimenFiscal)) {
                                $objExisteRegimenFiscal = $arrExisteRegimenFiscal[$columnas->codigo_regimen_fiscal];
                                $arrProveedor['rfi_id'] = $objExisteRegimenFiscal->rfi_id;
                            } else {
                                $arrErrores = $this->adicionarError($arrErrores, ['El Código del Régimen Fiscal [' . $columnas->codigo_regimen_fiscal . '], ya no está vigente, se encuentra INACTIVO o no existe.'], $fila);
                            }
                        }

                        $objExisteToj = $arrExisteTipoOrgJuridica[$columnas->codigo_tipo_organizacion_juridica] ?? null;
                        $arrProveedor['toj_codigo'] = $columnas->codigo_tipo_organizacion_juridica;
                        if (!empty($objExisteToj)) {
                            $arrProveedor['toj_id'] = $objExisteToj->toj_id;

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
                                $arrErrores = $this->adicionarError($arrErrores, $arrFaltantes, $fila);
                            } else {
                                $arrProveedor['pro_razon_social'] = $this->sanitizarStrings($columnas->razon_social);
                                $arrProveedor['pro_nombre_comercial'] = $this->sanitizarStrings($columnas->nombre_comercial);
                                $arrProveedor['pro_primer_apellido'] = $this->sanitizarStrings($columnas->primer_apellido);
                                $arrProveedor['pro_segundo_apellido'] = $this->sanitizarStrings($columnas->segundo_apellido);
                                $arrProveedor['pro_primer_nombre'] = $this->sanitizarStrings($columnas->primer_nombre);
                                $arrProveedor['pro_otros_nombres'] = $this->sanitizarStrings($columnas->otros_nombres);
                            }
                        } else {
                            $arrErrores = $this->adicionarError($arrErrores, ["El Código Tipo Organización Juridica [{$columnas->codigo_tipo_organizacion_juridica}], ya no está vigente, se encuentra INACTIVO o no existe."], $fila);
                        }

                        if (!property_exists($columnas, 'codigo_postal')) {
                            $columnas->codigo_postal = '';
                        }

                        if (!property_exists($columnas, 'matricula_mercantil')) {
                            $columnas->matricula_mercantil = '';
                        }

                        if (!property_exists($columnas, 'correos_notificacion')) {
                            $columnas->correos_notificacion = '';
                        }

                        $arrProveedor['pro_direccion'] = $this->sanitizarStrings($columnas->direccion);
                        $arrProveedor['pro_direccion_domicilio_fiscal'] = (trim($columnas->direccion_domicilio_fiscal) != '') ? $this->sanitizarStrings($columnas->direccion_domicilio_fiscal) : null;
                        $arrProveedor['pro_matricula_mercantil'] = $this->sanitizarStrings($columnas->matricula_mercantil);
                        $arrProveedor['pro_correos_notificacion'] = (trim($columnas->correos_notificacion) != '') ? $this->sanitizarStrings($columnas->correos_notificacion) : null;
                        $arrProveedor['pro_telefono'] = $this->sanitizarStrings((string)$columnas->telefono, 50);
                        $arrProveedor['pro_correo'] = $this->soloEmail($columnas->correo);
                        $arrProveedor['pro_usuarios_recepcion'] = isset($columnas->usuarios_gestion_documentos_recibidos) ? $columnas->usuarios_gestion_documentos_recibidos : null;

                        if (empty($arrErrores)) {
                            $rules = $this->className::$rules;
                            $rules['pro_usuarios_recepcion'] = 'nullable';
                            $rules['ref_id']                 = 'nullable|string|max:255';
                            $objValidator = Validator::make($arrProveedor, $rules);

                            if (!empty($objValidator->errors()->all())) {
                                $arrErrores = $this->adicionarError($arrErrores, $objValidator->errors()->all(), $fila);
                            } else {
                                $arrResultado[] = $arrProveedor;
                            }
                        }
                    }
                }
                if ($fila % 500 === 0) {
                    $this->renovarConexion($this->user);
                }
            }

            if (!empty($arrErrores)) {
                $agendamiento = AdoAgendamiento::create([
                    'usu_id'                  => $this->user->usu_id,
                    'age_proceso'             => 'FINALIZADO',
                    'age_cantidad_documentos' => 0,
                    'age_prioridad'           => null,
                    'usuario_creacion'        => $this->user->usu_id,
                    'fecha_creacion'          => date('Y-m-d H:i:s'),
                    'estado'                  => 'ACTIVO',
                ]);

                EtlProcesamientoJson::create([
                    'pjj_tipo' => ProcesarCargaParametricaCommand::$TYPE_PROV,
                    'pjj_json' => json_encode([]),
                    'pjj_procesado' => 'SI',
                    'age_estado_proceso_json' => 'FINALIZADO',
                    'pjj_errores' => json_encode($arrErrores),
                    'age_id' => $agendamiento->age_id,
                    'usuario_creacion' => $this->user->usu_id,
                    'fecha_creacion' => date('Y-m-d H:i:s'),
                    'estado' => 'ACTIVO'
                ]);

                return response()->json([
                    'message' => 'Errores al guardar los ' . ucwords($titulo['title']),
                    'errors' => ['Verifique el Log de Errores'],
                ], 400);

            } else {
                $insertProveedores = [];

                foreach ($arrResultado as $proveedor) {

                    $data = [
                        'pro_id'                         => $proveedor['pro_id'],
                        'ofe_id'                         => $proveedor['ofe_id'],
                        'pro_identificacion'             => $proveedor['pro_identificacion'],
                        'pro_id_personalizado'           => $proveedor['pro_id_personalizado'],
                        'pro_razon_social'               => !empty($proveedor['pro_razon_social']) ? $this->sanitizarStrings($proveedor['pro_razon_social']) : null,
                        'pro_nombre_comercial'           => !empty($proveedor['pro_nombre_comercial']) ? $this->sanitizarStrings($proveedor['pro_nombre_comercial']) : null,
                        'pro_primer_apellido'            => !empty($proveedor['pro_primer_apellido']) ? $this->sanitizarStrings($proveedor['pro_primer_apellido']) : null,
                        'pro_segundo_apellido'           => !empty($proveedor['pro_segundo_apellido']) ? $this->sanitizarStrings($proveedor['pro_segundo_apellido']) : null,
                        'pro_primer_nombre'              => !empty($proveedor['pro_primer_nombre']) ? $this->sanitizarStrings($proveedor['pro_primer_nombre']) : null,
                        'pro_otros_nombres'              => !empty($proveedor['pro_otros_nombres']) ? $this->sanitizarStrings($proveedor['pro_otros_nombres']) : null,
                        'tdo_id'                         => $proveedor['tdo_id'],
                        'toj_id'                         => $proveedor['toj_id'],
                        'pai_id'                         => $proveedor['pai_id'],
                        'dep_id'                         => $proveedor['dep_id'],
                        'mun_id'                         => $proveedor['mun_id'],
                        'cpo_id'                         => $proveedor['cpo_id'],
                        'pai_id_domicilio_fiscal'        => $proveedor['pai_id_domicilio_fiscal'],
                        'dep_id_domicilio_fiscal'        => $proveedor['dep_id_domicilio_fiscal'],
                        'mun_id_domicilio_fiscal'        => $proveedor['mun_id_domicilio_fiscal'],
                        'pro_direccion_domicilio_fiscal' => $proveedor['pro_direccion_domicilio_fiscal'],
                        'cpo_id_domicilio_fiscal'        => $proveedor['cpo_id_domicilio_fiscal'],
                        'pro_direccion'                  => $this->sanitizarStrings($proveedor['pro_direccion']),
                        'pro_telefono'                   => $this->sanitizarStrings($proveedor['pro_telefono']),
                        'pro_correo'                     => !empty($proveedor['pro_correo']) ? $this->sanitizarStrings($proveedor['pro_correo']) : null,
                        'rfi_id'                         => $proveedor['rfi_id'],
                        'ref_id'                         => $proveedor['ref_id'],
                        'pro_matricula_mercantil'        => $this->sanitizarStrings($proveedor['pro_matricula_mercantil']),
                        'pro_correos_notificacion'       => (!empty($proveedor['pro_correos_notificacion'])) ? $this->sanitizarStrings($proveedor['pro_correos_notificacion']) : null,
                        'pro_usuarios_recepcion'         => (!empty($proveedor['pro_usuarios_recepcion'])) ? explode(',', $proveedor['pro_usuarios_recepcion']) : null
                    ];

                    array_push($insertProveedores, $data);
                }

                if (count($insertProveedores) > 0) {
                    $bloques = array_chunk($insertProveedores, 100);

                    foreach ($bloques as $bloque) {
                        $agendamiento = AdoAgendamiento::create([
                            'usu_id'                  => $this->user->usu_id,
                            'age_proceso'             => ProcesarCargaParametricaCommand::$NOMBRE_COMANDO,
                            'age_cantidad_documentos' => count($bloque),
                            'age_prioridad'           => null,
                            'usuario_creacion'        => $this->user->usu_id,
                            'fecha_creacion'          => date('Y-m-d H:i:s'),
                            'estado'                  => 'ACTIVO',
                        ]);

                        if ($agendamiento) {
                            EtlProcesamientoJson::create([
                                'pjj_tipo' => ProcesarCargaParametricaCommand::$TYPE_PROV,
                                'pjj_json' => json_encode($bloque),
                                'pjj_procesado' => 'NO',
                                'age_id' => $agendamiento->age_id,
                                'usuario_creacion' => $this->user->usu_id,
                                'fecha_creacion' => date('Y-m-d H:i:s'),
                                'estado' => 'ACTIVO'
                            ]);
                        }
                    }
                }

                if (!empty($arrErrores)) {
                    return response()->json([
                        'message' => 'Errores al guardar los ' . ucwords($titulo['title']),
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
                'message' => 'Errores al guardar los ' . ucwords($titulo['title']),
                'errors' => ['No se ha subido ningún archivo.']
            ], 400);
        }
    }

    /**
     * Permite validar parametros recibidos para los métodos store y update.
     *
     * @param Request $request
     * @return array Contiene los ids de los datos validados
     */
    public function validarDatos(Request $request) {
        /**
         * Validaciones particulares
         */
        if (!$request->pro_razon_social && !$request->pro_primer_apellido && !$request->pro_primer_nombre)
            $this->errors = $this->adicionarError($this->errors, ['No se encontró la razón social o nombres del proveedor']);

        $datos = [];

        $datos['pro_razon_social']     = $request->has('pro_razon_social') && !empty($request->pro_razon_social) ? $this->sanitizarStrings($request->pro_razon_social) : null;
        $datos['pro_nombre_comercial'] = $request->has('pro_nombre_comercial') && !empty($request->pro_nombre_comercial) ? $this->sanitizarStrings($request->pro_nombre_comercial) : null;
        $datos['pro_primer_apellido']  = $request->has('pro_primer_apellido') && !empty($request->pro_primer_apellido) ? $this->sanitizarStrings($request->pro_primer_apellido) : null;
        $datos['pro_segundo_apellido'] = $request->has('pro_segundo_apellido') && !empty($request->pro_segundo_apellido) ? $this->sanitizarStrings($request->pro_segundo_apellido) : null;
        $datos['pro_primer_nombre']    = $request->has('pro_primer_nombre') && !empty($request->pro_primer_nombre) ? $this->sanitizarStrings($request->pro_primer_nombre) : null;
        $datos['pro_otros_nombres']    = $request->has('pro_otros_nombres') && !empty($request->pro_otros_nombres) ? $this->sanitizarStrings($request->pro_otros_nombres) : null;
        $datos['pro_correos_notificacion'] = $request->has('pro_correos_notificacion') && !empty($request->pro_correos_notificacion) ? $this->sanitizarStrings($request->pro_correos_notificacion) : null;

        $datos['tdo_id'] = $this->validarTipoDocumento($request->tdo_codigo, 'DE');
        $datos['toj_id'] = $this->validarTipoOrganizacionJuridica($request, 'pro');
        if ($request->has('pai_codigo') && !empty($request->pai_codigo))
            $datos['pai_id'] = ($request->has('pai_codigo') && $request->pai_codigo != '') ? $this->validarPais($request->pai_codigo, '', true) : null;
        if ($request->has('pai_codigo') && $request->has('dep_codigo') && !empty($request->dep_codigo) && !empty($datos['pai_id']))
            $datos['dep_id'] = ($request->has('dep_codigo') && $request->dep_codigo != '') ? $this->validarDepartamento($request->pai_codigo, $request->dep_codigo, '', true) : null;
        if ($request->has('pai_codigo') && $request->has('mun_codigo') && !empty($request->mun_codigo) && !empty($datos['pai_id']))
            $datos['mun_id'] = ($request->has('mun_codigo') && $request->mun_codigo != '') ? $this->validarMunicipio($request->pai_codigo, $request->dep_codigo, $request->mun_codigo, '', true) : null;

        if ($request->has('tat_codigo') && !empty($request->tat_codigo))
            $datos['tat_id'] = $this->validarAceptacionTacita($request->tat_codigo);

        if ($request->has('cpo_codigo') && !empty($request->cpo_codigo))
            $datos['cpo_id'] = $this->validarCodigoPostal(
                $request->cpo_codigo,
                isset($request->dep_codigo) ? $request->dep_codigo : '',
                isset($request->mun_codigo) ? $request->mun_codigo : '',
                true
            );

        
        if ($request->has('origen') && $request->origen == 'procesamiento_documentos') {
            $datos['rfi_id'] = null;
        } else {
            if ($request->has('rfi_codigo') && !empty($request->rfi_codigo)) {
                $rfi_id = $this->validarRegimenFiscal($request->rfi_codigo, true);
                $datos['rfi_id'] = ($rfi_id != '' && $rfi_id != null) ? $rfi_id : null;
            }
        }

        if ($request->has('ref_codigo') && !empty($request->ref_codigo))
            $datos['responsabilidades_fiscales'] = $this->validarResponsibilidadFiscal($request->ref_codigo);

        if ($request->has('ref_codigo') && !empty($request->ref_codigo)) {
            $datos['ref_id'] = implode(';', $request->ref_codigo);
        }

        // Validaciones para dirección de correspondencia
        $this->validacionGeneralDomicilio($request, 'pro', 'correspondencia');
        // Validaciones para domicilio fiscal
        $this->validacionGeneralDomicilio($request, 'pro', 'fiscal');

        if ($request->has('pai_codigo_domicilio_fiscal') && !empty($request->pai_codigo_domicilio_fiscal)) {
            $datos['pai_id_domicilio_fiscal'] = $this->validarPais($request->pai_codigo_domicilio_fiscal, 'En domicilio fiscal', true);
        }

        if ($request->has('pai_codigo_domicilio_fiscal') && $request->has('dep_codigo_domicilio_fiscal') && !empty($request->dep_codigo_domicilio_fiscal) && !empty($datos['pai_id_domicilio_fiscal'])) {
            $datos['dep_id_domicilio_fiscal'] = $this->validarDepartamento($request->pai_codigo_domicilio_fiscal, $request->dep_codigo_domicilio_fiscal, 'En domicilio fiscal', true);
        }

        if ($request->has('pai_codigo_domicilio_fiscal') && $request->has('mun_codigo_domicilio_fiscal') && !empty($request->mun_codigo_domicilio_fiscal) && !empty($datos['pai_id_domicilio_fiscal'])) {
            $datos['mun_id_domicilio_fiscal'] = $this->validarMunicipio($request->pai_codigo_domicilio_fiscal, $request->dep_codigo_domicilio_fiscal, $request->mun_codigo_domicilio_fiscal, 'En domicilio fiscal', true);
        }

        if ($request->has('cpo_codigo_domicilio_fiscal') && !empty($request->cpo_codigo_domicilio_fiscal))
            $datos['cpo_id_domicilio_fiscal'] = $this->validarCodigoPostal(
                $request->cpo_codigo_domicilio_fiscal,
                isset($request->dep_codigo_domicilio_fiscal) ? $request->dep_codigo_domicilio_fiscal : '',
                isset($request->mun_codigo_domicilio_fiscal) ? $request->mun_codigo_domicilio_fiscal : '',
                true
            );

        return $datos;
    }

    /**
     * Obtiene la lista de errores de procesamiento de cargas masivas de proveedores.
     *
     * @return Response
     * @throws \Exception
     */
    public function getListaErroresProveedores() {
        return $this->getListaErrores([ProcesarCargaParametricaCommand::$TYPE_PROV, ProcesarCargaParametricaCommand::$TYPE_UPP]);
    }

    /**
     * Retorna una lista en Excel de errores de cargue de proveedores.
     *
     * @param  Request  $request
     * @return Response
     */
    public function descargarListaErroresProveedores() {
        return $this->getListaErrores([ProcesarCargaParametricaCommand::$TYPE_PROV, ProcesarCargaParametricaCommand::$TYPE_UPP], true, 'carga_proveedores_log_errores');
    }

    /**
     * Efectua un proceso de busqueda en la parametrica
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function busqueda(Request $request) {
        $columnas = [
            'pro_id',
            'pro_identificacion',
            'pro_id_personalizado',
            'pro_razon_social',
            'pro_nombre_comercial',
            'pro_primer_apellido',
            'pro_segundo_apellido',
            'pro_primer_nombre',
            'pro_otros_nombres',
            \DB::raw('IF(pro_razon_social IS NULL OR pro_razon_social = "", CONCAT(pro_primer_nombre, " ", pro_otros_nombres, " ", pro_primer_apellido, " ", pro_segundo_apellido), pro_razon_social) as nombre_completo'),
            'tdo_id',
            'toj_id',
            'pai_id',
            'dep_id',
            'mun_id',
            'ofe_id',
            'cpo_id',
            'pro_direccion',
            'pro_telefono',
            'pro_correo'
        ];

        $columnas = array_merge($columnas, [
            'rfi_id',
            'ref_id',
            'pro_matricula_mercantil',
            'fecha_creacion',
            'fecha_modificacion',
            'usuario_creacion',
            'estado',
        ]);

        $incluir = [
            'getUsuarioCreacion',
            'getConfiguracionObligadoFacturarElectronicamente',
            'getParametroTipoDocumento',
            'getParametroPais',
            'getParametroDepartamento',
            'getParametroMunicipio',
            'getParametroTipoOrganizacionJuridica',
            'getRegimenFiscal',
            'getResponsabilidadFiscal:ref_id,ref_codigo,ref_descripcion,fecha_vigencia_desde,fecha_vigencia_hasta,estado',
            'getTiempoAceptacionTacita',
            'getCodigoPostal'
        ];

        $oferente = ConfiguracionObligadoFacturarElectronicamente::where('estado', 'ACTIVO')
            ->where('ofe_identificacion', $request->valorOfe)
            ->validarAsociacionBaseDatos()
            ->first();

        if (!is_null($oferente)) {
            return $this->procesadorBusqueda($request, $columnas, $incluir, []);
        }
        else
            return response()->json([
                'data' => [],
            ], Response::HTTP_OK);
    }

    /**
     * Obtiene una lista de usuarios del sistema relacionados con la BD a la cual pertenece el usuario autenticacado
     *
     * @param int $ofe_identificacion Identificación del OFE asociado al Proveedor
     * @param string $text
     * @return JsonResponse
     */
    public function obtenerUsuarios($ofe_identificacion, $text) {
        // Usuario autenticado
        $user = auth()->user();

        $bdd_id_rg = null;
        $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'bdd_id_rg'])
            ->where('ofe_identificacion', $ofe_identificacion)
            ->where('estado', 'ACTIVO')
            ->first();

        if($ofe)
            $bdd_id_rg = $ofe->bdd_id_rg;

        $data = [];
        $adminRol = SistemaRol::where('rol_codigo', 'superadmin')->first();
        $usuarios = User::whereRaw("(usu_email LIKE '%$text%' OR usu_identificacion LIKE '%$text%' OR usu_nombre LIKE '%$text%')")
            ->where('usu_type', 'OPERATIVO')
            ->where('bdd_id', $user->getBaseDatos->bdd_id)
            ->where('estado', 'ACTIVO')
            ->whereRaw("usu_id IN (SELECT usu_id FROM `sys_roles_usuarios` WHERE rol_id != {$adminRol->rol_id})");

        if($bdd_id_rg)
            $usuarios->where('bdd_id_rg', $bdd_id_rg);

        $usuarios->get()
            ->map(function ($item) use (&$data) {
                $data[] = [
                    'usu_id'             => $item->usu_id,
                    'usu_nombre'         => $item->usu_nombre,
                    'usu_identificacion' => $item->usu_identificacion,
                    'usuario'            => $item->usu_identificacion . ' - ' . $item->usu_nombre
                ];
            });

        return response()->json(['usuarios' => $data], 200);
    }
}
