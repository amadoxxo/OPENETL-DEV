<?php

namespace App\Http\Modulos\Configuracion\Adquirentes;

use Validator;
use App\Http\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Traits\insertTributos;
use App\Traits\insertContactos;
use Illuminate\Validation\Rule;
use App\Traits\OfeAdqValidations;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;
use openEtl\Tenant\Traits\TenantTrait;
use openEtl\Main\Traits\PackageMainTrait;
use App\Http\Controllers\OpenTenantController;
use App\Http\Modulos\Parametros\Paises\ParametrosPais;
use App\Console\Commands\ProcesarCargaParametricaCommand;
use App\Http\Modulos\Sistema\Agendamiento\AdoAgendamiento;
use App\Http\Modulos\Parametros\Tributos\ParametrosTributo;
use App\Http\Modulos\Parametros\Municipios\ParametrosMunicipio;
use App\Http\Modulos\Parametros\Departamentos\ParametrosDepartamento;
use App\Http\Modulos\Parametros\RegimenFiscal\ParametrosRegimenFiscal;
use App\Http\Modulos\Configuracion\Adquirentes\ConfiguracionAdquirente;
use App\Http\Modulos\Parametros\CodigosPostales\ParametrosCodigoPostal;
use App\Http\Modulos\Sistema\EtlProcesamientoJson\EtlProcesamientoJson;
use App\Http\Modulos\Parametros\TiposDocumentos\ParametrosTipoDocumento;
use App\Http\Modulos\Parametros\ProcedenciaVendedor\ParametrosProcedenciaVendedor;
use App\Http\Modulos\Configuracion\TributosOfesAdquirentes\TributosOfesAdquirentes;
use App\Http\Modulos\Sistema\TiemposAceptacionTacita\SistemaTiempoAceptacionTacita;
use App\Http\Modulos\Parametros\ResponsabilidadesFiscales\ParametrosResponsabilidadFiscal;
use App\Http\Modulos\Parametros\TipoOrganizacionJuridica\ParametrosTipoOrganizacionJuridica;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

class ConfiguracionAdquirenteController extends OpenTenantController {
    use OfeAdqValidations, insertTributos, insertContactos, PackageMainTrait;

    /**
     * Nombre del modelo en singular.
     *
     * @var String
     */
    public $nombre = 'Adquirente';

    /**
     * Nombre del modelo en plural.
     *
     * @var String
     */
    public $nombrePlural = 'Adquirentes';

    /**
     * Modelo relacionado a la paramétrica.
     *
     * @var Model
     */
    public $className = ConfiguracionAdquirente::class;

    /**
     * Mensaje de error para status code 422 al crear.
     *
     * @var String
     */
    public $mensajeErrorCreacion422 = 'No Fue Posible Crear el Adquirente';

    /**
     * Mensaje de errores al momento de crear.
     *
     * @var String
     */
    public $mensajeErroresCreacion = 'Errores al Crear el Adquirente';

    /**
     * Mensaje de error para status code 422 al actualizar.
     *
     * @var String
     */
    public $mensajeErrorModificacion422 = 'Errores al Actualizar el Adquirente';

    /**
     * Mensaje de errores al momento de crear.
     *
     * @var String
     */
    public $mensajeErroresModificacion = 'Errores al Actualizar el Adquirente';

    /**
     * Mensaje de error cuando el objeto no existe.
     *
     * @var String
     */
    public $mensajeObjectNotFound = 'El Id del Adquirente [%s] no Existe';

    /**
     * Mensaje de error cuando el objeto no existe.
     *
     * @var String
     */
    public $mensajeObjectDisabled = 'El Id del Adquirente [%s] Esta Inactivo';

    /**
     * constante para almacenar el tipo adquirente.
     *
     * @var String
     */
    public const ADQUIRENTES = 'adquirentes';

    /**
     * constante para almacenar el tipo autorizados.
     *
     * @var String
     */
    public const AUTORIZADOS = 'autorizados';

    /**
     * constante para almacenar el tipo responsables.
     *
     * @var String
     */
    public const RESPONSABLES = 'responsables';

    /**
     * Constante para almacenar el tipo vendedores.
     *
     * @var String
     */
    public const VENDEDORES = 'vendedores';

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
     * Propiedad para saber si se valido correctamente la procedencia vendedor.
     *
     * @var Bool
     */
    private $procedenciaVendedor = false;

    /**
     * Propiedad para saber si se valido correctamente la responsabilidad fiscal.
     *
     * @var Bool
     */
    private $responsabilidadFiscal = false;

    /**
     * Propiedad para saber si se valido correctamente el código postal.
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
     * Nombre de la propiedad que contiene los ids para cambiar los estados.
     *
     * @var String
     */
    public $nombreDatoCambiarEstado = 'identificaciones';

    /**
     * Nombre del campo de identificación.
     *
     * @var String
     */
    public $nombreCampoIdentificacion = 'adq_identificacion';

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
    public $columnasExcel = [];

    /**
     * Constructor
     */
    public function __construct() {
        if(auth()->user()) {
            // Solamente se ejecuta este código del constructor si existe un usuario autenticado
            // Porque el método obtenerAdquirenteDI no hace uso de la autenticación tradicional sino que su seguridad es a nivel de hash
            $columnasPersonalizadas = $this->ofeColumnasPersonalizadasAdquirentes();

            $this->columnasExcel = [
                'NIT OFE',
                $this->getTituloIdentificacion(),
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
                'CORREOS NOTIFICACION',
                'CODIGO REGIMEN FISCAL',
                'REGIMEN FISCAL',
                'CODIGO RESPONSABILIDADES FISCALES',
                'RESPONSABLE TRIBUTOS',
                'CODIGO PROCEDENCIA VENDEDOR',
                'PROCEDENCIA VENDEDOR',
                'MATRICULA MERCANTIL'
            ];

            if ($this->getLabelType() != 'Vendedor') {
                $indiceCodigoProcedenciaVendedor = array_search('CODIGO PROCEDENCIA VENDEDOR', $this->columnasExcel);
                $indiceProcedenciaVendedor       = array_search('PROCEDENCIA VENDEDOR', $this->columnasExcel);

                unset(
                    $this->columnasExcel[$indiceCodigoProcedenciaVendedor],
                    $this->columnasExcel[$indiceProcedenciaVendedor]
                );
                
                $this->columnasExcel = array_values($this->columnasExcel);
            }
            $this->columnasExcel = array_merge($this->columnasExcel, $columnasPersonalizadas);
        }

        $this->middleware(['jwt.auth', 'jwt.refresh'])
            ->except(['obtenerAdquirenteDI']);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionAdquirentes,ConfiguracionAdquirentesCambiarEstado,ConfiguracionAdquirentesDescargarExcel,ConfiguracionAdquirentesEditar,ConfiguracionAdquirentesNuevo,ConfiguracionAdquirentesSubir,ConfiguracionAdquirentesVer,ConfiguracionResponsables,ConfiguracionResponsablesCambiarEstado,ConfiguracionResponsablesDescargarExcel,ConfiguracionResponsablesEditar,ConfiguracionResponsablesNuevo,ConfiguracionResponsablesSubir,ConfiguracionResponsablesVer,ConfiguracionAutorizados,ConfiguracionAutorizadosCambiarEstado,ConfiguracionAutorizadosDescargarExcel,ConfiguracionAutorizadosEditar,ConfiguracionAutorizadosNuevo,ConfiguracionAutorizadosSubir,ConfiguracionAutorizadosVer,ConfiguracionVendedorDS,ConfiguracionVendedorDSCambiarEstado,ConfiguracionVendedorDSDescargarExcel,ConfiguracionVendedorDSEditar,ConfiguracionVendedorDSNuevo,ConfiguracionVendedorDSSubir,ConfiguracionVendedorDSVer'
        ])->except([
            'show',
            'store',
            'update',
            'cambiarEstado',
            'searchAdquirentes',
            'createFromDI',
            'showCompuesto',
            'showCompuestoVendedor',
            'obtenerAdquirenteDI',
            'generarInterfaceAdquirentes',
            'cargarAdquirentes',
            'getListaErroresAdquirentes',
            'descargarListaErroresAdquirentes',
            'buscarAdquirenteOfe',
            'getTipoAdquirente',
            'buscarVendedorOfe',
            'editarTipoAdquirente',
            'busqueda'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionAdquirentesNuevo,ConfiguracionResponsablesNuevo,ConfiguracionAutorizadosNuevo,ConfiguracionVendedorDSNuevo'
        ])->only([
            'store'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionAdquirentesEditar,ConfiguracionResponsablesEditar,ConfiguracionAutorizadosEditar,ConfiguracionVendedorDSEditar'
        ])->only([
            'update'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionAdquirentesVer,ConfiguracionResponsablesVer,ConfiguracionAutorizadosVer,ConfiguracionVendedorDSVer,ConfiguracionAdquirentesEditar,ConfiguracionResponsablesEditar,ConfiguracionAutorizadosEditar,ConfiguracionVendedorDSEditar'
        ])->only([
            'show',
            'showCompuesto',
            'showCompuestoVendedor',
            'busqueda'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionAdquirentesCambiarEstado,ConfiguracionResponsablesCambiarEstado,ConfiguracionAutorizadosCambiarEstado,ConfiguracionVendedorDSCambiarEstado'
        ])->only([
            'cambiarEstado'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionAdquirentesSubir,ConfiguracionResponsablesSubir,ConfiguracionAutorizadosSubir,ConfiguracionVendedorDSSubir'
        ])->only([
            'generarInterfaceAdquirentes',
            'cargarAdquirentes',
            'getListaErroresAdquirentes',
            'descargarListaErroresAdquirentes'
        ]);
    }

    /**
     * Construye el mensaje de titulo de mensaje de error al intentar crear/actualizar un Adquirente|Autorizado|Responsable|Vendedor.
     *
     * @param string $accion Acción que se estaba intentando ejecutar sobre un registro
     * @param bool $di Indica si una petición proviene del microservicio DI
     * @param string $adqTipo Cuando una petición viene del microservicio DI podría incluir el tipo de adquirente
     * @return string
     */
    private function getMensajeErrrorActualizarCrear(string $accion = 'crear', bool $di = false, string $adqTipo = null) {
        if(array_key_exists('REQUEST_URI', $_SERVER) && !empty($_SERVER['REQUEST_URI'])) {
            if (strpos($_SERVER['REQUEST_URI'], 'adquirentes') !== false || ($di && is_null($adqTipo)))
                return "Errores al $accion el Adquirente";
            elseif (strpos($_SERVER['REQUEST_URI'], 'vendedores') !== false || ($di && !is_null($adqTipo) && $adqTipo == 'adq_tipo_vendedor_ds'))
                return "Errores al $accion el Vendedor Documento Soporte";
            elseif (strpos($_SERVER['REQUEST_URI'], 'autorizados') !== false)
                return "Errores al $accion el Autorizado";
            else
                return "Errores al $accion el Responsable";
        } else {
            return "Errores al $accion el Adquirente";
        }
    }

    /**
     * Retorna Adquirente|Autorizado|Responsable|Vendedor según sea el caso.
     *
     * @param string $accion
     * @return string
     */
    private function getLabelType() {
        if(array_key_exists('REQUEST_URI', $_SERVER) && !empty($_SERVER['REQUEST_URI'])) {
            if (strpos($_SERVER['REQUEST_URI'], 'adquirentes') !== false)
                return "Adquirente";
            elseif (strpos($_SERVER['REQUEST_URI'], 'vendedores') !== false)
                return "Vendedor";
            elseif (strpos($_SERVER['REQUEST_URI'], 'autorizados') !== false)
                return "Autorizado";
            else
                return "Responsable";
        } else {
            return "Adquirente";
        }
    }

    /**
     * Configura las reglas para poder actualizar o crear los datos de los adqs en función de los códigos proporcionados.
     *
     * @param Request $request Parámetros de la petición
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
            $rules['ipv_id'],
            $rules['tat_id'],
            $rules['adq_tipo_adquirente'],
            $rules['adq_tipo_autorizado'],
            $rules['adq_tipo_vendedor_ds'],
            $rules['adq_tipo_responsable_entrega'],
            $rules['pai_id_domicilio_fiscal'],
            $rules['dep_id_domicilio_fiscal'],
            $rules['mun_id_domicilio_fiscal'],
            $rules['cpo_id_domicilio_fiscal']
        );

        if ($request['tdo_codigo'] == '13' || $request['tdo_codigo'] == '31') {
            $rules['adq_identificacion'] = 'required|string|regex:/^[1-9]/';
        }

        $rules['ofe_identificacion'] = 'required|string|max:20';
        $rules['tdo_codigo'] = 'required|string|max:2';
        $rules['toj_codigo'] = 'required|string|max:2';
        $rules['rfi_codigo'] = 'nullable|string|max:2';
        $rules['ref_codigo'] = 'nullable|array|max:255';
        if ($this->getLabelType() == 'Vendedor') {
            $rules['ipv_codigo'] = 'required|string|max:10';
        } else {
            $rules['ipv_codigo'] = 'nullable|string|max:10';
        }
        $rules['tat_codigo'] = 'nullable|string|max:5';
        
        $rules['pai_codigo']    = 'nullable|string|max:10';
        $rules['dep_codigo']    = 'nullable|string|max:10';
        $rules['mun_codigo']    = 'nullable|string|max:10';
        $rules['cpo_codigo']    = 'nullable|string|max:10';
        $rules['adq_direccion'] = 'nullable|string|max:255';
        $rules['adq_telefono']  = 'nullable|string|max:50';

        $rules['pai_codigo_domicilio_fiscal']    = 'nullable|string|max:10';
        $rules['dep_codigo_domicilio_fiscal']    = 'nullable|string|max:10';
        $rules['mun_codigo_domicilio_fiscal']    = 'nullable|string|max:10';
        $rules['cpo_codigo_domicilio_fiscal']    = 'nullable|string|max:10';
        $rules['adq_direccion_domicilio_fiscal'] = 'nullable|string|max:255';
        $rules['adq_correos_notificacion']       = 'nullable|string';

        // Condicionales para direcciones de domicilio
        $rules['pai_codigo'] = Rule::requiredIf(function() use ($request) {
            return !empty($request->dep_codigo) || !empty($request->mun_codigo) || !empty($request->adq_direccion) || !empty($request->cpo_codigo);
        });

        $rules['adq_direccion'] = Rule::requiredIf(function() use ($request) {
            return $request->pai_codigo == 'CO';
        });

        // Condicionales para direcciones fiscales
        $rules['pai_codigo_domicilio_fiscal'] = Rule::requiredIf(function() use ($request) {
            return !empty($request->dep_codigo_domicilio_fiscal) || !empty($request->mun_codigo_domicilio_fiscal) || !empty($request->adq_direccion_domicilio_fiscal) || !empty($request->cpo_codigo_domicilio_fiscal);
        });

        $rules['adq_direccion_domicilio_fiscal'] = Rule::requiredIf(function() use ($request) {
            return $request->pai_codigo_domicilio_fiscal == 'CO';
        });

        return $rules;
    }

    /**
     * Reemplaza los códigos e identificaciones proporcionadas en los datos de entrada por los correspodientes IDs de
     * las parametricas que pueden componer a un Adquirente|Autorizado|Responsable|Vendedor.
     *
     * @param array $origin Array donde se almacena la información de las parametricas
     * @param array $datosParseados Array que contiene los datos parseados
     * @param ConfiguracionObligadoFacturarElectronicamente $ofe Ofe seleccionado
     */
    private function buildData(array &$origin, array $datosParseados, ConfiguracionObligadoFacturarElectronicamente $ofe) {
        unset($origin['ofe_identificacion'], $origin['tdo_codigo'], $origin['toj_codigo'], $origin['pai_codigo'], $origin['dep_codigo'],
            $origin['mun_codigo'], $origin['cpo_codigo'], $origin['rfi_codigo'], $origin['ref_codigo'], $origin['ipv_codigo'], $origin['tat_codigo']);

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
        $origin['ref_id'] = array_key_exists('responsabilidades_fiscales', $datosParseados) ? implode(';', $datosParseados['responsabilidades_fiscales']) : null;
        $origin['ipv_id'] = array_key_exists('ipv_id', $datosParseados) ? $datosParseados['ipv_id'] : null;
        $origin['tat_id'] = array_key_exists('tat_id', $datosParseados) ? $datosParseados['tat_id'] : null;
    }

    /**
     * Almacena un recurso recién creado.
     *
     * @param Request $request Parámetros de la petición
     * @param bool $di Indica que la petición viene del microservicio DI
     * @param string $adqTipo Cuando una petición viene del microservicio DI podría incluir el tipo de adquirente
     * @return Response
     */
    public function store(Request $request, bool $di = false, string $adqTipo = null) {
        $this->errors = [];
        // Obtencion del Token
        $this->user = auth()->user();
        // Validando los datos enviados
        if($request->has('adq_informacion_personalizada') && !empty($request->adq_informacion_personalizada) && is_array($request->adq_informacion_personalizada)) {
            $request->merge([
                'adq_informacion_personalizada' => json_encode($request->adq_informacion_personalizada)
            ]);
        }
        $camposCorreo = ['adq_correo','adq_correos_notificacion'];
        foreach ($camposCorreo as $campo) {
            $validatorEmail = $this->validationEmailRule($request->{$campo});
            if (!empty($validatorEmail['errors']))
                $this->errors = array_merge($this->errors, $validatorEmail['errors']);
        }
        if (!empty($this->errors))
            return response()->json([
                'message' => $this->mensajeErroresCreacion,
                'errors'  => $this->errors
            ], 404);

        $validador = Validator::make($request->all(), $this->getRules($request));
        if (!$validador->fails()) {
            // Valida que las llaves foraneas existan
            $datos = $this->validarDatos($request);

            if (empty($this->errors)) {
                $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'ofe_identificacion', 'bdd_id_rg', 'ofe_identificador_unico_adquirente'])
                    ->where('ofe_identificacion', $request->ofe_identificacion)
                    ->validarAsociacionBaseDatos()
                    ->where('estado', 'ACTIVO')
                    ->first();

                if ($ofe === null) {
                    return response()->json([
                        'message' => $this->getMensajeErrrorActualizarCrear('crear', $di, $adqTipo),
                        'errors'  => [sprintf("El Oferente [%s] no existe o se encuentra en estado INACTIVO.", $request->ofe_identificacion)]
                    ], Response::HTTP_NOT_FOUND);
                }

                $existeAdquirente = $this->className::select('adq_id', 'adq_id_personalizado', 'adq_tipo_adquirente', 'adq_tipo_autorizado', 'adq_tipo_vendedor_ds', 'adq_tipo_responsable_entrega', 'estado')
                    ->where('adq_identificacion', $request->adq_identificacion)
                    ->where('ofe_id', $ofe->ofe_id);

                // Verifica la configuración de la llave de adquirentes que tiene el OFE
                $fraseComplementa = '';
                if(
                    !empty($ofe->ofe_identificador_unico_adquirente) &&
                    in_array('adq_id_personalizado', $ofe->ofe_identificador_unico_adquirente) &&
                    $request->has('adq_id_personalizado') &&
                    !empty($request->adq_id_personalizado) &&
                    !$this->isConsumidorFinal($request->adq_identificacion)
                ) {
                    $existeAdquirente = $existeAdquirente->where('adq_id_personalizado', $request->adq_id_personalizado);
                    $fraseComplementa = 'ID Personalizado [' . $request->adq_id_personalizado . ']';
                }

                $existeAdquirente = $existeAdquirente->first();

                if ($existeAdquirente) {
                    if ($existeAdquirente->{$this->getKeyAdq($di, $adqTipo)} != 'SI') {
                        $request->merge([
                            $this->getKeyAdq($di, $adqTipo) => 'SI',
                            'ofe_id' => $ofe->ofe_id,
                            'toj_id' => $datos['toj_id'],
                            'tdo_id' => $datos['tdo_id'],
                            'estado' => $existeAdquirente->estado,
                            'adq_id_personalizado' => $existeAdquirente->adq_id_personalizado
                        ]);

                        return $this->updateCompuesto($request, $ofe->ofe_identificacion, $request->adq_identificacion, $existeAdquirente->adq_id_personalizado, $di);
                    }

                    return response()->json([
                        'message' => $this->getMensajeErrrorActualizarCrear('crear', $di, $adqTipo),
                        'errors' =>['Ya existe un ' . $this->getLabelType() . ' con el numero de identificacion [' . $request->adq_identificacion . '] ' . $fraseComplementa . ' para el OFE [' . $request->ofe_identificacion . '].']
                    ], Response::HTTP_CONFLICT);
                } else {
                    if(array_key_exists('consumidor_final', $datos) && !empty($datos['consumidor_final'])) {
                        $arrAdquirente = (array)$datos['consumidor_final'];
                        $arrAdquirente['adq_correos_notificacion'] = ($request->has('adq_correos_notificacion') && !empty($request->adq_correos_notificacion)) ? $request->adq_correos_notificacion : null;
                        $this->crearAdquirenteConsumidorFinal($ofe->ofe_id, $arrAdquirente, $this->user);
                        return response()->json([
                            'success' => true
                        ], Response::HTTP_CREATED);
                    }
                }

                $input = $request->all();
                $this->buildData($input, $datos, $ofe);
                $this->prepararDatos($input, $request);
                $input[$this->getKeyAdq($di, $adqTipo)] = 'SI';
                $input['estado'] = 'ACTIVO';

                $objAdquirente = $this->className::create($input);
                if ($objAdquirente) {
                    $contactos = $request->has('contactos') ? (array)$request->contactos : [];
                    $this->insertContactos('adq_id', $contactos, $objAdquirente, $this->user);
                }

                //Verificamos que no tengamos error para guardar los tributos
                if (empty($this->errors)) {
                    if (!empty($request->get('responsable_tributos')) && is_array($request->get('responsable_tributos'))) {
                        $this->insertTributos('adq_id', $request->get('responsable_tributos'), $objAdquirente, $this->user);
                    }
                }

                if (empty($this->errors)) {
                    return response()->json([
                        'success' => true,
                        'adq_id' => $objAdquirente->adq_id
                    ], 201);
                } else {
                    return response()->json([
                        'message' => $this->getMensajeErrrorActualizarCrear('crear', $di, $adqTipo),
                        'errors' => $this->errors
                    ], 400);
                }
            } else {
                return response()->json([
                    'message' => $this->getMensajeErrrorActualizarCrear('crear', $di, $adqTipo),
                    'errors' => $this->errors
                ], 400);
            }
        } else {
            return response()->json([
                'message' => $this->getMensajeErrrorActualizarCrear('crear', $di, $adqTipo),
                'errors' => $validador->errors()->all()
            ], 400);
        }
    }

    /**
     * Muestra el recurso especificado.
     *
     * @param int $id Identificador único tomado desde la URL
     * @return Response
     * @throws FileNotFoundException
     */
    public function show($id) {
        $arrRelaciones = [
            'getUsuarioCreacion',
            'getConfiguracionObligadoFacturarElectronicamente:ofe_id,ofe_identificacion,ofe_nombre_comercial,ofe_otros_nombres,ofe_primer_apellido,ofe_primer_nombre,ofe_razon_social,ofe_segundo_apellido,sft_id,tdo_id,toj_id,ofe_identificador_unico_adquirente,ofe_informacion_personalizada_adquirente',
            'getParametroTipoDocumento',
            'getParametroPais',
            'getParametroDepartamento',
            'getParametroMunicipio',
            'getContactos',
            'getParametroTipoOrganizacionJuridica',
            'getRegimenFiscal',
            'getProcedenciaVendedor',
            'getResponsabilidadFiscal:ref_id,ref_codigo,ref_descripcion,fecha_vigencia_desde,fecha_vigencia_hasta,estado',
            'getTiempoAceptacionTacita',
            'getTributos:tri_id,adq_id',
            'getTributos.getDetalleTributo:tri_id,tri_codigo,tri_nombre,tri_descripcion',
            'getCodigoPostal',
            'getParametroDomicilioFiscalPais',
            'getParametroDomicilioFiscalDepartamento',
            'getParametroDomicilioFiscalMunicipio',
            'getCodigoPostalDomicilioFiscal',
        ];
        return $this->procesadorShow($id, $arrRelaciones);
    }

    /**
     * Muestra el adquirente|autorizado|responsable|vendedor especificado por la identificación del ofe al que esta asociado y
     * la identifación del individuo u organización.
     *
     * @param string $ofe_identificacion Identificación del OFE
     * @param string $adq_identificacion Identificación del Adquirente
     * @param string $adq_id_personalizado ID Personalizadio del Adquirente, no obligatorio
     * @return Response
     */
    public function showCompuestoVendedor(string $ofe_identificacion, string $adq_identificacion, string $adq_id_personalizado = null) {
        return $this->showCompuesto($ofe_identificacion, $adq_identificacion, $adq_id_personalizado, true);
    }

    /**
     * Muestra el adquirente|autorizado|responsable|vendedor especificado por la identificación del ofe al que esta asociado y
     * la identifación del individuo u organización.
     *
     * @param string $ofe_identificacion Identificación del OFE
     * @param string $adq_identificacion Identificación del Adquirente
     * @param string $adq_id_personalizado ID Personalizadio del Adquirente, no obligatorio
     * @param bool   $adq_tipo_vendedor_ds Tipo de Adquirente Vendedor
     * @return Response
     */
    public function showCompuesto(string $ofe_identificacion, string $adq_identificacion, string $adq_id_personalizado = null, bool $adq_tipo_vendedor_ds = false) {
        $arrRelaciones = [
            'getUsuarioCreacion',
            'getConfiguracionObligadoFacturarElectronicamente:ofe_id,ofe_identificacion,ofe_nombre_comercial,ofe_otros_nombres,ofe_primer_apellido,ofe_primer_nombre,ofe_razon_social,ofe_segundo_apellido,sft_id,tdo_id,toj_id,ofe_identificador_unico_adquirente,ofe_informacion_personalizada_adquirente',
            'getParametroTipoDocumento',
            'getParametroPais',
            'getParametroDepartamento',
            'getParametroMunicipio',
            'getCodigoPostal',
            'getParametroDomicilioFiscalPais',
            'getParametroDomicilioFiscalDepartamento',
            'getParametroDomicilioFiscalMunicipio',
            'getCodigoPostalDomicilioFiscal',
            'getParametroTipoOrganizacionJuridica',
            'getRegimenFiscal',
            'getProcedenciaVendedor',
            'getTributos:tri_id,adq_id',
            'getTributos.getDetalleTributo:tri_id,tri_codigo,tri_nombre,tri_descripcion,tri_aplica_persona,fecha_vigencia_hasta,fecha_vigencia_desde',
            'getResponsabilidadFiscal:ref_id,ref_codigo,ref_descripcion,fecha_vigencia_desde,fecha_vigencia_hasta,estado',
            'getTiempoAceptacionTacita',
            'getContactos',
            'getUsuariosPortales:upc_id,ofe_id,ofe_identificacion,adq_id,adq_identificacion,upc_identificacion,upc_nombre,upc_correo,estado'
        ];

        if ($this->getLabelType() != 'Responsable' && $this->getLabelType() != 'Autorizado' && $this->getLabelType() != 'Vendedor') {
            $arrRelaciones[] = 'getContactos';
        }

        $objMaster = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'ofe_identificador_unico_adquirente', 'bdd_id_rg'])
            ->where('ofe_identificacion', $ofe_identificacion)
            ->validarAsociacionBaseDatos()
            ->where('estado', 'ACTIVO')
            ->first();

        if ($objMaster === null) {
            return response()->json([
                'message' => sprintf("El Oferente [%s] no existe o se encuentra en estado INACTIVO.", $ofe_identificacion),
                'errors'  => []
            ], Response::HTTP_NOT_FOUND);
        }

        $objetoModel = $this->className::where('adq_identificacion', $adq_identificacion)
            ->where('ofe_id', $objMaster->ofe_id);

        if($adq_tipo_vendedor_ds === true)
            $objetoModel = $objetoModel->where('adq_tipo_vendedor_ds', 'SI');

        if(!empty($adq_id_personalizado) && !$this->isConsumidorFinal($adq_identificacion))
            $objetoModel = $objetoModel->where('adq_id_personalizado', $adq_id_personalizado);
        else
            $objetoModel = $objetoModel->whereNull('adq_id_personalizado');

        if (!empty($arrRelaciones))
            $objetoModel = $objetoModel->with($arrRelaciones);

        $objetoModel = $objetoModel->first();

        $responsabilidades_fiscales = [];
        if (!empty($objetoModel->ref_id)) {
            $responsabilidades_fiscales = $this->listarResponsabilidadesFiscales($objetoModel->ref_id);
        }

        if ($objetoModel){
            $arrAdquirente = $objetoModel->toArray();
            $arrAdquirente['get_responsabilidad_fiscal'] = $responsabilidades_fiscales;

            return response()->json([
                'data' => $arrAdquirente
            ], Response::HTTP_OK);
        } else {
            if(!empty($adq_id_personalizado) && !$this->isConsumidorFinal($adq_identificacion))
                $msgComplemento = ' con ID personalizado [' . $adq_id_personalizado . ']';
            else
                $msgComplemento = '';

            return response()->json([
                'message' => sprintf("El " . $this->getLabelType() . " [%s]%s, para el Oferente [%s] no existe o se encuentra en estado INACTIVO.", $adq_identificacion, $msgComplemento, $ofe_identificacion),
                'errors'  => []
            ], Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * Actualiza el recurso especificado.
     *
     * @param Request $request Parámetros de la petición
     * @param string $ofe_identificacion Identificación del oferente tomada desde la URL
     * @param string $adq_identificacion Identificación del adquirente tomada desde la URL
     * @return Response
     */
    public function update(Request $request, string $ofe_identificacion, string $adq_identificacion) {
        $this->errors = [];
        $this->user = auth()->user();

        if($request->has('adq_informacion_personalizada') && !empty($request->adq_informacion_personalizada) && is_array($request->adq_informacion_personalizada)) {
            $request->merge([
                'adq_informacion_personalizada' => json_encode($request->adq_informacion_personalizada)
            ]);
        }

        $validador = Validator::make($request->all(), $this->className::$rulesUpdate);
        // Validando los datos enviados
        if ($validador->fails()) {
            return response()->json([
                'message' => 'Errores al actualizar el Adquirente',
                'errors' => $validador->errors()->all()
            ], 400);
        }

        $oferente = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'bdd_id_rg', 'ofe_identificador_unico_adquirente'])
            ->where('ofe_identificacion', $ofe_identificacion)
            ->validarAsociacionBaseDatos()
            ->first();
        if (!$oferente) {
            $this->errors = $this->adicionarError($this->errors, ["El Oferente con Idenficación [{$ofe_identificacion}] no existe"]);
        }

        $objAdquirente = $this->className::select('adq_id', 'usuario_creacion', 'estado', 'ofe_id')
            ->where('adq_identificacion', $adq_identificacion)
            ->where('ofe_id', $oferente->ofe_id);

        // Verifica la configuración de la llave de adquirentes que tiene el OFE
        if(
            !empty($oferente->ofe_identificador_unico_adquirente) &&
            in_array('adq_id_personalizado', $oferente->ofe_identificador_unico_adquirente) && 
            $request->has('adq_id_personalizado') && 
            !empty($request->adq_id_personalizado) && 
            !$this->isConsumidorFinal($adq_identificacion)
        ) {
            $objAdquirente = $objAdquirente->where('adq_id_personalizado', $request->adq_id_personalizado);
        }

        $objAdquirente = $objAdquirente->first();

        // Verifica que NO exista otro adquirente con la misma llave ofe_id, adq_identificación, adq_id_personalizado (optional)
        $existeOtro = $this->className::select(['adq_id'])
            ->where('adq_identificacion', $adq_identificacion)
            ->where('ofe_id', $oferente->ofe_id)
            ->where('adq_id', '!=', $objAdquirente->adq_id);

        $fraseComplementa = '';
        if(
            !empty($oferente->ofe_identificador_unico_adquirente) && 
            in_array('adq_id_personalizado', $oferente->ofe_identificador_unico_adquirente) && 
            $request->has('adq_id_personalizado') && 
            !empty($request->adq_id_personalizado) && 
            !$this->isConsumidorFinal($adq_identificacion)
        ) {
            $existeOtro = $existeOtro->where('adq_id_personalizado', $request->adq_id_personalizado);
            $fraseComplementa = 'ID Personalizado [' . $request->adq_id_personalizado . ']';
        }

        $existeOtro = $existeOtro->first();

        if($existeOtro) {
            return response()->json([
                'message' => 'Error al actualizar el Adquirente',
                'errors' => [sprintf("El Adquirente [%s] %s ya existe con otro ID en openETL.", $adq_identificacion, $fraseComplementa)]
            ], 409);
        }

        if (!empty($objAdquirente)) {
            if ($adq_identificacion !== $request->adq_identificacion) {
                $existeAdquirente = $this->className::select('adq_id')->where('adq_identificacion', $request->adq_identificacion)
                    ->where('ofe_id', $request->ofe_id)
                    ->first();

                if (!empty($existeAdquirente)) {
                    //Validando que la identificación no exista con otro autoincremental
                    $this->errors = $this->adicionarError($this->errors, ['Ya exisete un Adquirente con el numero de identificacion [' . $request->adq_identificacion . '] para el OFE [' . $request->ofe_id . '].']);
                }
            }

            // Valida que las llaves foraneas existan
            $datos = $this->validarDatos($request);

            if (empty($this->errors)) {
                $input = $request->all();
                $this->buildData($input, $datos, $oferente);
                $this->prepararDatos($input, $request);
                $input['estado'] = $request->has('estado') ? $request->estado : $objAdquirente->estado;

                if(array_key_exists('consumidor_final', $datos) && !empty($datos['consumidor_final'])) {
                    $arrAdquirente = (array)$datos['consumidor_final'];
                    $arrAdquirente['adq_correos_notificacion'] = ($request->has('adq_correos_notificacion') && !empty($request->adq_correos_notificacion)) ? $request->adq_correos_notificacion : null;
                    $this->actualizarAdquirenteConsumidorFinal($ofe->ofe_id, $arrAdquirente);
                    return response()->json([
                        'success' => true
                    ], 200);
                }

                //actualizando data
                $objAdquirente->update($input);

                if ($objAdquirente) {
                    $contactos = $request->has('contactos') ? (array)$request->contactos : [];
                    $this->insertContactos('adq_id', $contactos, $objAdquirente, $this->user);
                }

                //Verificamos que no tengamos error para guardar los tributos
                if (empty($this->errors)) {
                    if (isset($request->responsable_tributos) && is_array($request->get('responsable_tributos'))) {
                        $this->insertTributos('adq_id', $request->get('responsable_tributos'), $objAdquirente, $this->user);
                    }
                }

                if (empty($this->errors)) {
                    return response()->json([
                        'success' => true
                    ], 200);
                } else {
                    return response()->json([
                        'message' => $this->getMensajeErrrorActualizarCrear('actualizar'),
                        'errors' => $this->errors
                    ], 400);
                }
            } else {
                return response()->json([
                    'message' => $this->getMensajeErrrorActualizarCrear('actualizar'),
                    'errors' => $this->errors
                ], 400);
            }
        } else {
            return response()->json([
                'message' => $this->getMensajeErrrorActualizarCrear('actualizar'),
                'errors' => ['El adquirente de identificacion [' . $adq_identificacion . '] ' . $fraseComplementa . ' no existe.']
            ], 400);
        }
    }

    /**
     * Permite editar un adquirente|responsable|autorizado|vendedor en función de un oferente.
     *
     * @param Request $request Parámetros de la petición
     * @param string $ofe_identificacion Identificación del oferente tomada desde la URL
     * @param string $adq_identificacion Identificación del adquirente tomada desde la URL
     * @param bool $di Indica que la petición viene del microservicio DI
     * @param string $adqTipo Cuando una petición viene del microservicio DI podría incluir el tipo de adquirente
     * @return JsonResponse
     */
    public function updateCompuesto(Request $request, string $ofe_identificacion, string $adq_identificacion, string $adq_id_personalizado = null, bool $di = true, string $adqTipo = null) {
        $this->errors = [];
        $this->user = auth()->user();

        $objMaster = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'bdd_id_rg', 'ofe_identificador_unico_adquirente'])
            ->where('ofe_identificacion', $ofe_identificacion)
            ->validarAsociacionBaseDatos()
            ->where('estado', 'ACTIVO')
            ->first();

        if ($objMaster === null) {
            return response()->json([
                'message' => $this->getMensajeErrrorActualizarCrear('actualizar', $di, $adqTipo),
                'errors' => [sprintf("El Oferente [%s] no existe o se encuentra en estado INACTIVO.", $ofe_identificacion)]
            ], Response::HTTP_NOT_FOUND);
        }
        
        //Si el adquirente que se esta actualizando es consumidor final el id personalizado debe ser null
        $adq_id_personalizado = !$this->isConsumidorFinal($adq_identificacion) ? $adq_id_personalizado : null;
        if (isset($request->adq_id_personalizado)) {
            $request->merge([
                'adq_id_personalizado' => isset($request->adq_identificacion) && !$this->isConsumidorFinal($request->adq_identificacion) ? $request->adq_id_personalizado : null
                ]);
        }

        $objAdquirente = $this->className::select('adq_id', 'usuario_creacion', 'estado', 'ofe_id', 'adq_identificacion', 'adq_id_personalizado')
            ->where('ofe_id', $objMaster->ofe_id)
            ->where('adq_identificacion', $adq_identificacion);

        $fraseComplementa = '';
        $requestAdqIdPersonalizado = isset($request->adq_id_personalizado) && !empty($request->adq_id_personalizado) ? $request->adq_id_personalizado : null;
        if($adq_id_personalizado == $requestAdqIdPersonalizado) {
            if(
                !empty($objMaster->ofe_identificador_unico_adquirente) &&
                in_array('adq_id_personalizado', $objMaster->ofe_identificador_unico_adquirente) &&
                !empty($requestAdqIdPersonalizado)
            ) {
                $initObjAdquirente = clone $objAdquirente;
                $objAdquirente     = $objAdquirente->where('adq_id_personalizado', $requestAdqIdPersonalizado);
                $fraseComplementa  = 'ID Personalizado [' . $requestAdqIdPersonalizado . ']';
            } elseif(
                !empty($objMaster->ofe_identificador_unico_adquirente) &&
                in_array('adq_id_personalizado', $objMaster->ofe_identificador_unico_adquirente) &&
                empty($requestAdqIdPersonalizado)
            ) {
                $objAdquirente = $objAdquirente->whereNull('adq_id_personalizado');
            }
            $objAdquirente = $objAdquirente->first();

            if(!$objAdquirente && isset($initObjAdquirente)) {
                $objAdquirente = $initObjAdquirente->whereNull('adq_id_personalizado');
                $objAdquirente = $objAdquirente->first();
            }
        } else {
            // El adq_id_personalizado cambió por lo que se debe ubicar el registro con el adq_id_personalizado del parámetro de la url y no del request
            // Y validar que NO exista otro adquirente con esa misma configuración de identificacion y id_personalizado del request
            if(
                !empty($objMaster->ofe_identificador_unico_adquirente) &&
                in_array('adq_id_personalizado', $objMaster->ofe_identificador_unico_adquirente)
            ) {
                $fraseComplementa = (isset($adq_id_personalizado) && !empty($adq_id_personalizado)) ? 'ID Personalizado [' . $adq_id_personalizado . ']' : '';
                if (!empty($adq_id_personalizado))
                    $objAdquirente = $objAdquirente->where('adq_id_personalizado', $adq_id_personalizado);
                else
                    $objAdquirente = $objAdquirente->whereNull('adq_id_personalizado');
            }
            $objAdquirente = $objAdquirente->first();   

            if(!$objAdquirente) {
                return response()->json([
                    'message' => $this->getMensajeErrrorActualizarCrear('actualizar', $di, $adqTipo),
                    'errors' => ['El ' . $this->getLabelType() .' de identificacion [' . $adq_identificacion . '] ' . $fraseComplementa . ' no existe para el OFE [' . $ofe_identificacion . '].']
                ], 409);
            }
            
            $adqExistente = $this->className::select('adq_id')
                ->where('ofe_id', $objMaster->ofe_id)
                ->where('adq_identificacion', $objAdquirente->adq_identificacion)
                ->where('adq_id_personalizado', $requestAdqIdPersonalizado)
                ->where('adq_id', '!=', $objAdquirente->adq_id)
                ->first();

            if($adqExistente) {
                return response()->json([
                    'message' => $this->getMensajeErrrorActualizarCrear('actualizar', $di, $adqTipo),
                    'errors' => ['El ' . $this->getLabelType() .' de identificacion [' . $objAdquirente->adq_identificacion . '] ' . $fraseComplementa . ' existe con otro ID para el OFE [' . $ofe_identificacion . '].']
                ], 409);
            }
        }

        if (!empty($objAdquirente)) {
            if($request->has('adq_informacion_personalizada') && !empty($request->adq_informacion_personalizada) && is_array($request->adq_informacion_personalizada)) {
                $request->merge([
                    'adq_informacion_personalizada' => json_encode($request->adq_informacion_personalizada)
                ]);
            }
            $camposCorreo = ['adq_correo','adq_correos_notificacion'];
            foreach ($camposCorreo as $campo) {
                $validatorEmail = $this->validationEmailRule($request->{$campo});
                if (!empty($validatorEmail['errors']))
                    $this->errors = array_merge($this->errors, $validatorEmail['errors']);
            }
            if (!empty($this->errors))
                return response()->json([
                    'message' => $this->mensajeErroresModificacion,
                    'errors'  => $this->errors
                ], 404);

            // Validando los datos enviados
            $validador = Validator::make($request->all(), $this->getRules($request));
            if ($validador->fails()) {
                return response()->json([
                    'message' => $this->getMensajeErrrorActualizarCrear('actualizar', $di, $adqTipo),
                    'errors' => $validador->errors()->all()
                ], 400);
            }

            if ($adq_identificacion != $request->adq_identificacion) {
                $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'ofe_identificador_unico_adquirente'])
                    ->where('ofe_identificacion', $request->ofe_identificacion)
                    ->validarAsociacionBaseDatos()
                    ->where('estado', 'ACTIVO')
                    ->first();

                if ($ofe === null) {
                    return response()->json([
                        'message' => $this->getMensajeErrrorActualizarCrear('actualizar', $di, $adqTipo),
                        'errors' => [sprintf("El Oferente [%s] no existe o se encuentra en estado INACTIVO.", $request->ofe_identificacion)]
                    ], Response::HTTP_NOT_FOUND);
                }

                $existeAdquirente = $this->className::select('adq_id')
                    ->where('ofe_id', $ofe->ofe_id)
                    ->where('adq_id', '!=', $objAdquirente->adq_id)
                    ->where('adq_identificacion', $request->adq_identificacion);

                if(
                    !empty($ofe->ofe_identificador_unico_adquirente) &&
                    in_array('adq_id_personalizado', $ofe->ofe_identificador_unico_adquirente)
                ) {
                    if (!empty($request->adq_id_personalizado)) {
                        $fraseComplementa = 'ID Personalizado [' . $request->adq_id_personalizado . ']';
                        $existeAdquirente = $existeAdquirente->where('adq_id_personalizado', $request->adq_id_personalizado);
                    } else {
                        $fraseComplementa = '';
                        $existeAdquirente = $existeAdquirente->whereNull('adq_id_personalizado');
                    }
                }
                $existeAdquirente = $existeAdquirente->first();

                if (!empty($existeAdquirente)) {
                    return response()->json([
                        'message' => $this->getMensajeErrrorActualizarCrear('actualizar', $di, $adqTipo),
                        'errors' =>['Ya existe un ' . $this->getLabelType() . ' con el numero de identificación [' . $request->adq_identificacion . '] ' . ($fraseComplementa ?? '') . ' para el OFE [' . $request->ofe_identificacion . '].']
                    ], Response::HTTP_CONFLICT);
                }
            }
            else
                $ofe = $objMaster;

            // Valida que las llaves foraneas existan
            $datos = $this->validarDatos($request);
            if (empty($this->errors)) {
                $input = $request->all();
                $this->buildData($input, $datos, $ofe);
                $this->prepararDatos($input, $request);
                $input[$this->getKeyAdq($di, $adqTipo)] = 'SI';
                $input['estado'] = $request->has('estado') ? $request->estado : $objAdquirente->estado;

                if(array_key_exists('consumidor_final', $datos) && !empty($datos['consumidor_final'])) {
                    $arrAdquirente = (array)$datos['consumidor_final'];
                    $arrAdquirente['adq_correos_notificacion'] = ($request->has('adq_correos_notificacion') && !empty($request->adq_correos_notificacion)) ? $request->adq_correos_notificacion : null;
                    $this->actualizarAdquirenteConsumidorFinal($ofe->ofe_id, $arrAdquirente);
                    return response()->json([
                        'success' => true
                    ], 200);
                }

                //actualizando data
                $objAdquirente->update($input);

                if ($objAdquirente) {
                    $contactos = $request->has('contactos') ? (array)$request->contactos : [];
                    $this->insertContactos('adq_id', $contactos, $objAdquirente, $this->user);
                }

                //Verificamos que no tengamos error para guardar los tributos
                if (empty($this->errors)) {
                    if (isset($request->responsable_tributos) && is_array($request->get('responsable_tributos'))) {
                        $this->insertTributos('adq_id', $request->get('responsable_tributos'), $objAdquirente, $this->user);
                    }
                }

                if (empty($this->errors)) {
                    return response()->json([
                        'success' => true
                    ], 200);
                } else {
                    return response()->json([
                        'message' => $this->getMensajeErrrorActualizarCrear('actualizar', $di, $adqTipo),
                        'errors' => $this->errors
                    ], 400);
                }
            } else {
                return response()->json([
                    'message' => $this->getMensajeErrrorActualizarCrear('actualizar', $di, $adqTipo),
                    'errors' => $this->errors
                ], 400);
            }
        } else {
            return response()->json([
                'message' => $this->getMensajeErrrorActualizarCrear('actualizar', $di, $adqTipo),
                'errors' => ['El ' . $this->getLabelType() .' de identificacion [' . $adq_identificacion . '] ' . $fraseComplementa . ' no existe.']
            ], 400);
        }
    }

    /**
     * Prepara la data que va ser insertada o actualizada.
     *
     * @param array $data Array que contiene la información
     * @param Request $request Parámetros de la petición
     * @return void
     */
    private function prepararDatos(array &$data, Request $request) {
        $data['usuario_creacion'] = $this->user->usu_id;
        /*$data['tat_id'] = $this->tiempoAceptacionTacita ? $request->tat_id : null;
        $data['rfi_id'] = $this->regimenFiscal ? $request->rfi_id : null;
        $data['ref_id'] = $this->responsabilidadFiscal ? $request->ref_id : null;
        $data['cpo_id'] = $this->codigoPostal ? $request->cpo_id : null;*/
        $data['adq_campos_representacion_grafica'] = (json_decode($request->adq_campos_representacion_grafica)) ? $request->adq_campos_representacion_grafica : null;

        switch ($request->toj_codigo) {
            //Este case solo se cumple cuando es organizacion JURIDICA.
            case '1':
                $data['adq_razon_social'] = $data['adq_razon_social'];
                $data['adq_nombre_comercial'] = ($data['adq_nombre_comercial'] == "" ? $data['adq_razon_social'] : $data['adq_nombre_comercial']);
                $data['adq_primer_nombre']    = NULL;
                $data['adq_otros_nombres']    = NULL;
                $data['adq_primer_apellido']  = NULL;
                $data['adq_segundo_apellido'] = NULL;
                break;
            default:
                $data['adq_razon_social']     = NULL;
                $data['adq_nombre_comercial'] = NULL;
                $data['adq_primer_nombre']    = $data['adq_primer_nombre'];
                $data['adq_otros_nombres']    = (array_key_exists('adq_otros_nombres',$data)) ? $data['adq_otros_nombres'] : NULL;
                $data['adq_primer_apellido']  = $data['adq_primer_apellido'];
                $data['adq_segundo_apellido'] = (array_key_exists('adq_segundo_apellido',$data)) ? $data['adq_segundo_apellido'] : NULL;
                break;
        }
    }

    /**
     * Obtiene el titulo para la Identificación del Adquirente.
     *
     * @return String
     */
    private function getTituloIdentificacion() {
        $tipo = $this->getTituloCorrecto();
        return 'NIT '.substr(strtoupper($tipo['title']), 0, strlen($tipo['title']) - 1);
    }

    /**
     * Devuelve una lista paginada de adquirentes|autorizados|responsables|vendedores registrados.
     *
     * @param Request $request Parámetros de la petición
     * @return Response
     * @throws \Exception
     */
    public function getListaAdquirentes(Request $request) {

        set_time_limit(0);
        ini_set('memory_limit', '1024M');

        $user = auth()->user();

        if ($request->filled('pjj_tipo') && $request->pjj_tipo == 'REPADQ')
            $tipo = [
                'conditions' => [['adq_tipo_adquirente', '=', 'SI']],
                'title' => self::ADQUIRENTES
            ];
        else
            $tipo = $this->getTituloCorrecto();

        $filtros = [
            'AND' => $tipo['conditions']
        ];

        $condiciones = [
            'filters' => $filtros,
        ];

        $columnas = [
            'adq_id',
            'ofe_id',
            'adq_identificacion',
            'adq_id_personalizado',
            'adq_informacion_personalizada',
            'adq_razon_social',
            'adq_nombre_comercial',
            'adq_primer_apellido',
            'adq_segundo_apellido',
            'adq_primer_nombre',
            'adq_otros_nombres',
            \DB::raw('IF(adq_razon_social IS NULL OR adq_razon_social = "", CONCAT(COALESCE(adq_primer_nombre, ""), " ", COALESCE(adq_otros_nombres, ""), " ", COALESCE(adq_primer_apellido, ""), " ", COALESCE(adq_segundo_apellido, "")), adq_razon_social) as nombre_completo'),
            'adq_tipo_adquirente',
            'adq_tipo_autorizado',
            'adq_tipo_vendedor_ds',
            'adq_tipo_responsable_entrega',
            'tdo_id',
            'toj_id',
            'pai_id',
            'dep_id',
            'mun_id',
            'cpo_id',
            'adq_direccion',
            'adq_telefono',
            'pai_id_domicilio_fiscal',
            'dep_id_domicilio_fiscal',
            'mun_id_domicilio_fiscal',
            'cpo_id_domicilio_fiscal',
            'adq_direccion_domicilio_fiscal',
            'adq_nombre_contacto',
            'adq_fax',
            'adq_notas',
            'adq_correo',
            'adq_correos_notificacion',
            'rfi_id',
            'ref_id',
            'ipv_id',
            'adq_matricula_mercantil',
            'adq_campos_representacion_grafica',
            'tat_id',
            'adq_reenvio_notificacion_contingencia',
            'usuario_creacion',
            'fecha_creacion',
            'fecha_modificacion',
            'estado',
            'fecha_actualizacion'
        ];

        $relaciones = [
            'getUsuarioCreacion',
            'getConfiguracionObligadoFacturarElectronicamente:ofe_id,ofe_identificacion,ofe_nombre_comercial,ofe_otros_nombres,ofe_primer_apellido,ofe_primer_nombre,ofe_razon_social,ofe_segundo_apellido,sft_id,tdo_id,toj_id,ofe_identificador_unico_adquirente,bdd_id_rg',
            'getParametroTipoDocumento',
            'getParametroPais',
            'getParametroDepartamento',
            'getParametroMunicipio',
            'getCodigoPostal',
            'getParametroDomicilioFiscalPais',
            'getParametroDomicilioFiscalDepartamento',
            'getParametroDomicilioFiscalMunicipio',
            'getCodigoPostalDomicilioFiscal',
            'getParametroTipoOrganizacionJuridica',
            'getRegimenFiscal',
            'getProcedenciaVendedor',
            'getTributos',
            'getTributos.getDetalleTributo:tri_id,tri_codigo,tri_nombre,tri_descripcion',
            'getResponsabilidadFiscal:ref_id,ref_codigo,ref_descripcion,fecha_vigencia_desde,fecha_vigencia_hasta,estado',
            'getTiempoAceptacionTacita',
            'getContactos',
            'getUsuariosPortales:upc_id,ofe_id,ofe_identificacion,adq_id,adq_identificacion,upc_identificacion,upc_nombre,upc_correo,estado'
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
                'function' => function($query) {
                    $query->whereNull('bdd_id_rg');
                }
            ];
        }

        if ($this->getLabelType() != 'Responsable' && $this->getLabelType() != 'Autorizado' && $this->getLabelType() != 'Vendedor') {
            $relaciones[] = 'getContactos';
        }

        $columnasPersonalizadas = $this->ofeColumnasPersonalizadasAdquirentes();

        $exportacion = [
            'columnas' => [
                'ofe_id' => [
                    'label' => 'NIT OFE',
                    'relation' => ['name' => 'getConfiguracionObligadoFacturarElectronicamente', 'field' => 'ofe_identificacion']
                ],
                'adq_identificacion' => $this->getTituloIdentificacion(),
                'adq_id_personalizado' => 'ID PERSONALIZADO',
                'adq_razon_social' => 'RAZON SOCIAL',
                'adq_nombre_comercial' => 'NOMBRE COMERCIAL',
                'adq_primer_apellido' => 'PRIMER APELLIDO',
                'adq_segundo_apellido' => 'SEGUNDO APELLIDO',
                'adq_primer_nombre' => 'PRIMER NOMBRE',
                'adq_otros_nombres' => 'OTROS NOMBRES',
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
                // 'adq_tipo_adquirente' => 'Tipo Adquirente',
                // 'adq_tipo_autorizado' => 'Tipo Autorizado',
                // 'adq_tipo_responsable_entrega' => 'Responsable Entrega',
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
                'adq_direccion' => 'DIRECCION',
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
                'adq_direccion_domicilio_fiscal' => 'DIRECCION DOMICILIO FISCAL',
                'adq_nombre_contacto' => 'NOMBRE CONTACTO',
                'adq_telefono' => 'TELEFONO',
                'adq_fax' => 'FAX',
                'adq_correo' => 'CORREO',
                'adq_notas' => 'NOTAS',
                'adq_correos_notificacion' => 'CORREOS NOTIFICACION',
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
              //  'ref_id' => [
              //      'multiple' => true,
              //      'relation' => 'getResponsabilidadFiscal',
              //      'fields' => [
              //          [
              //              'label' => 'CODIGO RESPONSABILIDADES FISCALES',
              //              'field' => 'ref_codigo'
              //          ],
              //          [
              //              'label' => 'RESPONSABILIDADES FISCALES',
              //              'field' => 'ref_descripcion'
              //          ]
              //      ]
              //  ],
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
                'ipv_id' => [],
                'adq_matricula_mercantil' => 'MATRICULA MERCANTIL',
                'columnas_personalizadas' => [
                    'columnas_personalizadas' => true,
                    'fields' => $columnasPersonalizadas,
                ],
                // 'tat_id' => [
                //     'label' => 'Tiempo Aceptación Tacita',
                //     'relation' => ['name' => 'getTiempoAceptacionTacita', 'field' => 'tat_codigo']
                // ],
                'pro_usuarios_portales' => [],
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
            'titulo' => $tipo['title']
        ];

        if (
            (array_key_exists('REQUEST_URI', $_SERVER) && strpos($_SERVER['REQUEST_URI'], 'adquirentes') !== false) ||
            ($request->filled('pjj_tipo') && $request->pjj_tipo == 'REPADQ')
        ) {
            $exportacion['columnas']['pro_usuarios_portales'] = [
                'callback' => true,
                'relation' => 'getUsuariosPortales',
                'label' => 'PORTAL CLIENTES',
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
            ];
        } else {
            unset($exportacion['columnas']['pro_usuarios_portales']);
        }

        if ($this->getLabelType() == 'Vendedor') {
            $exportacion['columnas']['ipv_id'] = [
                'multiple' => true,
                'relation' => 'getProcedenciaVendedor',
                'fields' => [
                    [
                        'label' => 'CODIGO PROCEDENCIA VENDEDOR',
                        'field' => 'ipv_codigo'
                    ],
                    [
                        'label' => 'PROCEDENCIA VENDEDOR',
                        'field' => 'ipv_descripcion'
                    ]
                ]
            ];
        } else {
            unset($exportacion['columnas']['ipv_id']);
        }

        if ($request->has('excel') && ($request->excel || $request->excel === 'true')) {
            $procesoBackground = ($request->filled('pjj_tipo') && $request->pjj_tipo == 'REPADQ') ? true : false;
            return $this->procesadorTracking($request, $condiciones, $columnas, $relaciones, $exportacion, false, $whereHasConditions, 'configuracion', $procesoBackground);
        } else {
            $data = $this->procesadorTracking($request, $condiciones, $columnas, $relaciones, $exportacion, true, $whereHasConditions, 'configuracion');
            
            // Cantidad de usuarios de portal clientes admitidos
            TenantTrait::GetVariablesSistemaTenant();
            $data['cantidad_usuarios_portal_clientes'] = config('variables_sistema_tenant.CANTIDAD_USUARIOS_PORTAL_CLIENTES');
            return response()->json($data, Response::HTTP_OK);
        }
    }

    /**
     * Devuelve todos los tipo de la identificacion de adquirente.
     *
     * @param Request $request Parámetros de la petición
     * @param string $ofe_identificacion Identificación del Ofe
     * @return Response
     */
    public function getTipoAdquirente(Request $request, string $ofe_identificacion) {
        if ($request->has('identificacion')) {
            $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'ofe_identificador_unico_adquirente', 'bdd_id_rg'])
                ->where('ofe_identificacion', $ofe_identificacion)
                ->validarAsociacionBaseDatos()
                ->where('estado', 'ACTIVO')
                ->first();

            if ($ofe === null) {
                return response()->json([
                    'message' => "El OFE [$ofe_identificacion] no existe!",
                    'errors' => []
                ], 404);
            }

            $adquirente = $this->className::select('adq_tipo_autorizado', 'adq_tipo_adquirente', 'adq_tipo_responsable_entrega', 'adq_tipo_vendedor_ds', 'adq_id')
                ->where('adq_identificacion', $request->identificacion)
                ->where('ofe_id', $ofe->ofe_id);

            if(
                !empty($ofe->ofe_identificador_unico_adquirente) &&
                in_array('adq_id_personalizado', $ofe->ofe_identificador_unico_adquirente)
            ) {
                if ($request->has('adq_id_personalizado') && !empty($request->adq_id_personalizado))
                    $adquirente = $adquirente->where('adq_id_personalizado', $request->adq_id_personalizado);
                else
                    $adquirente = $adquirente->whereNull('adq_id_personalizado');
            }

            $adquirente = $adquirente->first();

            if (!empty($adquirente)) {
                $tipoAdquirente = ['adq_id' => $adquirente->adq_id];

                if ($adquirente->adq_tipo_autorizado == 'SI') {
                    $tipoAdquirente['adq_tipo_autorizado'] = 'SI';
                }
                if ($adquirente->adq_tipo_adquirente == 'SI') {
                    $tipoAdquirente['adq_tipo_adquirente'] = 'SI';
                }
                if ($adquirente->adq_tipo_responsable_entrega == 'SI') {
                    $tipoAdquirente['adq_tipo_responsable_entrega'] = 'SI';
                }
                if ($adquirente->adq_tipo_vendedor_ds == 'SI') {
                    $tipoAdquirente['adq_tipo_vendedor_ds'] = 'SI';
                }

                return response()->json([
                    'success' => true,
                    'data' => $tipoAdquirente
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'data' => []
                ], 200);
            }
        } else {
            return response()->json([
                'message' => 'No se proporciono el parametro identificación',
                'errors' => []
            ], 400);
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
        $arrTitulo = $this->getTituloCorrecto();
        $this->nombrePlural = ucwords($arrTitulo['title']);

        return $this->procesadorCambiarEstadoCompuesto(
            ConfiguracionObligadoFacturarElectronicamente::class,
            'ofe_id',
            'ofe_identificacion',
            'adq_identificacion',
            'adq_identificacion',
            $request->all(),
            'El OFE [%s] no existe o se encuentra INACTIVO.'
        );
    }

    /**
     * Obtiene los adquirentes en búsqueda predictiva dado un Ofe_Id y el término a buscar.
     *
     * @param Request $request Parámetros de la petición
     * @return Response
     */
    public function searchAdquirentes(Request $request) {
        $adquirentes = $this->className::where('estado', 'ACTIVO');
        $adquirentes->select(
            [
                'adq_id',
                'adq_identificacion',
                'adq_id_personalizado',
                'adq_tipo_adquirente',
                'adq_tipo_vendedor_ds',
                'adq_razon_social',
                'adq_nombre_comercial',
                'adq_primer_apellido',
                'adq_segundo_apellido',
                'adq_primer_nombre',
                'adq_otros_nombres',
                \DB::raw('IF(adq_razon_social IS NULL OR adq_razon_social = "", CONCAT(COALESCE(adq_primer_nombre, ""), " ", COALESCE(adq_otros_nombres, ""), " ", COALESCE(adq_primer_apellido, ""), " ", COALESCE(adq_segundo_apellido, "")), adq_razon_social) as nombre_completo')
            ]);

        if ($request->has('ofe_id') && !empty($request->ofe_id)) {
            $adquirentes->where('ofe_id', $request->ofe_id);
        }

        if (!$request->has('autorizados') || ($request->has('autorizados') && $request->autorizados != "true")) {
            if($request->has('vendedor_ds') && $request->vendedor_ds == "true") {
                $adquirentes->where('adq_tipo_vendedor_ds', 'SI');
            } else {
                $adquirentes->where('adq_tipo_adquirente', 'SI');
            }
        } else {
            $adquirentes->where('adq_tipo_autorizado', 'SI');
        }

        if ($request->has('proceso_pickup_cash') && $request->proceso_pickup_cash == "true") {
            $adquirentes->whereNull('adq_id_personalizado');
        }

        $adquirentes->where(function ($query) use ($request) {
            $query->where('adq_razon_social', 'like', '%' . $request->buscar . '%')
                ->orWhere('adq_nombre_comercial', 'like', '%' . $request->buscar . '%')
                ->orWhere('adq_primer_apellido', 'like', '%' . $request->buscar . '%')
                ->orWhere('adq_segundo_apellido', 'like', '%' . $request->buscar . '%')
                ->orWhere('adq_primer_nombre', 'like', '%' . $request->buscar . '%')
                ->orWhere('adq_otros_nombres', 'like', '%' . $request->buscar . '%')
                ->orWhere('adq_identificacion', 'like', '%' . $request->buscar . '%')
                ->orWhere('adq_id_personalizado', 'like', '%' . $request->buscar . '%')
                ->orWhere('adq_tipo_adquirente', 'like', '%' . $request->buscar . '%')
                ->orWhere('adq_tipo_vendedor_ds', 'like', '%' . $request->buscar . '%')
                ->orWhereRaw("REPLACE(CONCAT(COALESCE(adq_primer_nombre, ''), ' ', COALESCE(adq_otros_nombres, ''), ' ', COALESCE(adq_primer_apellido, ''), ' ', COALESCE(adq_segundo_apellido, '')), '  ', ' ') like ?", ['%' . $request->buscar . '%']);
        });

        return response()->json([
            'data' => $adquirentes->get()
        ], 200);
    }

    /**
     * Permite encontrar uno o varios adquirentes y sus posibles Ofes mediante la identificación del Vendedor y la identificación del OFE (no obligatorio).
     *
     * @param string $adq_identificacion Identificación del Adquirente a ubicar
     * @param string $ofe_identificacion Identificación del OFE relacionado (No Obligatorio)
     * @return Response
     */
    public function buscarVendedorOfe(string $adq_identificacion, string $ofe_identificacion = null) {
        return $this->buscarAdquirenteOfe($adq_identificacion, $ofe_identificacion, true);
    }

    /**
     * Permite encontrar uno o varios adquirentes y sus posibles Ofes mediante la identificacion del Adquirente y la identificación del OFE (no obligatorio).
     *
     * @param string $adq_identificacion Identificación del Adquirente a ubicar
     * @param string $ofe_identificacion Identificación del OFE relacionado (No Obligatorio)
     * @param bool   $adq_tipo_vendedor_ds Tipo de Adquiente Vendedor
     * @return Response
     */
    public function buscarAdquirenteOfe(string $adq_identificacion, string $ofe_identificacion = null, bool $adq_tipo_vendedor_ds = false) {
        if ($ofe_identificacion != null) {
            // Ubica el OFE
            $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'bdd_id_rg'])
                ->where('ofe_identificacion', $ofe_identificacion)
                ->validarAsociacionBaseDatos()
                ->first();

            if (!$ofe)
                return response()->json([
                    'message' => 'Ofe no existe',
                    'errors' => ['El Ofe con identificación [' . $ofe_identificacion . '] no existe en el sistema']
                ], 404);

            $adquirente = ConfiguracionAdquirente::where('adq_identificacion', $adq_identificacion)
                ->where('ofe_id', $ofe->ofe_id)
                ->with('getConfiguracionObligadoFacturarElectronicamente:ofe_id,tdo_id,ofe_identificacion,ofe_razon_social,ofe_nombre_comercial,ofe_primer_apellido,ofe_segundo_apellido,ofe_primer_nombre,ofe_otros_nombres,pai_id,dep_id,mun_id,ofe_direccion,ofe_telefono,ofe_web,ofe_correo,ofe_twitter,ofe_facebook,sft_id,ofe_identificador_unico_adquirente,ofe_informacion_personalizada_adquirente');
            if($adq_tipo_vendedor_ds === true) {
                $adquirente = $adquirente->where('adq_tipo_vendedor_ds', 'SI');
            }
            $adquirente = $adquirente->get()->toArray();
        } else {
            $adquirente = ConfiguracionAdquirente::where('adq_identificacion', $adq_identificacion)
                ->with('getConfiguracionObligadoFacturarElectronicamente:ofe_id,tdo_id,ofe_identificacion,ofe_razon_social,ofe_nombre_comercial,ofe_primer_apellido,ofe_segundo_apellido,ofe_primer_nombre,ofe_otros_nombres,pai_id,dep_id,mun_id,ofe_direccion,ofe_telefono,ofe_web,ofe_correo,ofe_twitter,ofe_facebook,sft_id,ofe_identificador_unico_adquirente,ofe_informacion_personalizada_adquirente');
            if($adq_tipo_vendedor_ds === true) {
                $adquirente = $adquirente->where('adq_tipo_vendedor_ds', 'SI');
            }
            $adquirente = $adquirente->get()->toArray();
        }

        if (!$adquirente) {
            return response()->json([
                'message' => 'Adquirente no existe',
                'errors' => ['El adquirente con identificación [' . $adq_identificacion . '] no existe en el sistema']
            ], 404);
        } else {
            return response()->json([
                'data' => [
                    'adquirente' => $adquirente
                ]
            ], 200);
        }
    }

    /**
     * Responde con el título correcto según el menú desde cuál se realiza la petición.
     *
     * @return array
     */
    private function getTituloCorrecto() {
        if(array_key_exists('REQUEST_URI', $_SERVER) && !empty($_SERVER['REQUEST_URI'])) {
            if (strpos($_SERVER['REQUEST_URI'], 'adquirentes') !== false)
                return  [
                    'conditions' => [['adq_tipo_adquirente', '=', 'SI']],
                    'title' => ConfiguracionAdquirenteController::ADQUIRENTES
                ];
            elseif (strpos($_SERVER['REQUEST_URI'], 'autorizados') !== false)
                return  [
                    'conditions' => [['adq_tipo_autorizado', '=', 'SI']],
                    'title' => ConfiguracionAdquirenteController::AUTORIZADOS
                ];
            elseif (strpos($_SERVER['REQUEST_URI'], 'vendedores') !== false)
                return  [
                    'conditions' => [['adq_tipo_vendedor_ds', '=', 'SI']],
                    'title' => ConfiguracionAdquirenteController::VENDEDORES
                ];
            else
                return  [
                    'conditions' => [['adq_tipo_responsable_entrega', '=', 'SI']],
                    'title' => ConfiguracionAdquirenteController::RESPONSABLES
                ];
        } else {
            return  [
                'conditions' => [['adq_tipo_adquirente', '=', 'SI']],
                'title' => ConfiguracionAdquirenteController::ADQUIRENTES
            ];
        }
    }

    /**
     * Genera una Interfaz de Adquirentes para guardar en Excel.
     *
     * @return ExcelExport
     */
    public function generarInterfaceAdquirentes(Request $request) {
        $titulo = $this->getTituloCorrecto();
        return $this->generarInterfaceToTenant($this->columnasExcel, $titulo['title']);
    }

    /**
     * Toma una interfaz en Excel y la almacena en la tabla de Adquirentes.
     *
     * @param Request $request Parámetros de la petición
     * @return Response
     * @throws \Exception
     */
    function cargarAdquirentes(Request $request) {
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        $this->user = auth()->user();

        $titulo = $this->getTituloCorrecto();

        if (!$request->hasFile('archivo')) {
            return response()->json([
                'message' => 'Errores al guardar los ' . ucwords($titulo['title']),
                'errors' => ['No se ha subido ningún archivo.']
            ], 400);
        }

        $archivo = explode('.', $request->file('archivo')->getClientOriginalName());
        if (
            (!empty($request->file('archivo')->extension()) && !in_array($request->file('archivo')->extension(), $this->arrExtensionesPermitidas)) ||
            !in_array($archivo[count($archivo) - 1], $this->arrExtensionesPermitidas)
        )
            return response()->json([
                'message' => 'Errores al guardar los ' . ucwords($titulo['title']),
                'errors'  => ['Solo se permite la carga de archivos EXCEL.']
            ], 409);

        $nombreArchivo = $request->file('archivo')->getClientOriginalName();
        $data = $this->parserExcel($request, 'archivo', false, '', true);

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
                    $columna['usuario_creacion'],
                    $columna['fecha_creacion'],
                    $columna['fecha_modificacion'],
                    $columna['estado'],
                    $columna['portal_clientes']
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
                'message' => 'Errores al guardar los ' . ucwords($titulo['title']),
                'errors'  => ['El archivo subido no tiene datos.']
            ], 400);
        }

        $arrErrores = [];
        $arrResultado = [];
        $arrExisteOfe = [];
        $arrExistePais = [];
        $arrExistePaisDepto = [];
        $arrExisteMunDepto = [];
        $arrExisteTipoDocumento = [];
        $arrExisteRegimenFiscal = [];
        $arrExisteProcedenciaVendedor = [];
        $arrExisteTipoOrgJuridica = [];
        $arrAdquirenteInterOperabilidad = [];
        $arrAdqIds = [];
        $arrAdquirenteProceso = [];
        $arrExisteTiempoAceptacionTacita = [];
        $arrExisteResponsabilidadesFiscales = [];
        $arrExisteResponsablesTributos = [];
        $arrExisteCodigoPostal = [];
        $arrAdquirenteConsumidorFinal = [];
        $adquirenteConsumidorFinal = json_decode(config('variables_sistema.ADQUIRENTE_CONSUMIDOR_FINAL'));
        foreach($adquirenteConsumidorFinal as $consumidorFinal) {
            $arrAdquirenteConsumidorFinal[$consumidorFinal->adq_identificacion] = $consumidorFinal;
        }

        SistemaTiempoAceptacionTacita::where('estado', 'ACTIVO')->select('tat_id', 'tat_codigo')->get()->map(function ($tat) use (&$arrExisteTiempoAceptacionTacita) {
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

        $aplicaPara = ($this->getLabelType() == 'Vendedor') ? 'DS' : 'DE';
        ParametrosTipoDocumento::where('tdo_aplica_para', 'LIKE', '%'.$aplicaPara.'%')
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

        ParametrosProcedenciaVendedor::where('estado', 'ACTIVO')->get()
            ->groupBy('ipv_codigo')
            ->map(function ($ipo) use (&$arrExisteProcedenciaVendedor) {
                $vigente = $this->validarVigenciaRegistroParametrica($ipo);
                if ($vigente['vigente']) {
                    $arrExisteProcedenciaVendedor[$vigente['registro']->ipv_codigo] = $vigente['registro'];
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
            $errores = [];
            $columnas = (object)$columnas;
            // Verifica que ninguno de los adquirentes en el archivo se encuentre dentro de la variable ADQUIRENTE_CONSUMIDOR_FINAL
            $adq_identificacion = $this->sanitizarStrings($columnas->{$campo_identificacion});
            if(array_key_exists($adq_identificacion, $arrAdquirenteConsumidorFinal)) {
                $errores = $this->adicionarError($errores, ['EL Adquirente [' . $adq_identificacion . '] no se puede procesar porque es un Adquirente Consumidor Final.'], $fila);
            } else {
                // Se verifica si el adquirente corresponde a un Consumidor Final o no independiente de la identificacion
                $nombreCompleto = $columnas->primer_nombre . ' ' . $columnas->otros_nombres . ' ' . $columnas->primer_apellido . ' ' . $columnas->segundo_apellido;
                if(
                    (stristr($columnas->razon_social, 'consumidor') && stristr($columnas->razon_social, 'final')) ||
                    (stristr($nombreCompleto, 'consumidor') && stristr($nombreCompleto, 'final'))
                ) {
                    if(!array_key_exists($adq_identificacion, $arrAdquirenteConsumidorFinal)) {
                        $errores = $this->adicionarError($errores, ['El NIT de Consumidor Final enviado no corresponde al NIT de Consumidor Final autorizado por la DIAN'], $fila);
                    }
                }
            }

            if(!empty($errores)) {
                $arrErrores[] = [
                    'errors'              => $errores,
                    'documento'           => $nombreArchivo,
                    'adquirente'          => $columnas->$campo_identificacion . ((isset($columnas->id_personalizado) && !empty($columnas->id_personalizado)) ? '~' . $columnas->id_personalizado : ''),
                    'fecha_procesamiento' => date('Y-m-d'),
                    'hora_procesamiento'  => date('H:i:s')
                ];
            }
        }

        if(empty($arrErrores)) {
            $columnasPersonalizadas = [];
            $identificadoresUnicos  = [];
            $ofesColumnas           = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_identificacion', 'ofe_identificador_unico_adquirente', 'ofe_informacion_personalizada_adquirente'])
                ->where('estado', 'ACTIVO')
                ->get()
                ->map(function($ofeColumnas) use (&$columnasPersonalizadas, &$identificadoresUnicos) {
                    if(!empty($ofeColumnas->ofe_informacion_personalizada_adquirente))
                        $columnasPersonalizadas[$ofeColumnas->ofe_identificacion] = $ofeColumnas->ofe_informacion_personalizada_adquirente;

                    if(!empty($ofeColumnas->ofe_identificador_unico_adquirente))
                        $identificadoresUnicos[$ofeColumnas->ofe_identificacion] = $ofeColumnas->ofe_identificador_unico_adquirente;
                });
            
            foreach ($data as $fila => $columnas) {
                $errores = [];
                
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
                    $arrAdquirente = [];

                    $campos = [
                        'nit_ofe',
                        $campo_identificacion,
                        'codigo_tipo_organizacion_juridica',
                        'codigo_tipo_documento'
                    ];


                    if (empty($columnas->codigo_departamento)){
                        $columnas->codigo_departamento == '';
                    }

                    if (!empty($columnas->correos_notificacion)) {
                        if ($campo_identificacion == 'nit_adquirente'){
                            array_push($campos, 'correos_notificacion');
                        }
                    }

                    $arrFaltantes = $this->checkFields($Acolumnas, $campos, $fila);

                    if (!empty($arrFaltantes)) {
                        $vacio = $this->revisarArregloVacio($columnas);

                        if ($vacio) {
                            $errores = $this->adicionarError($errores, $arrFaltantes, $fila);
                        } else {
                            unset($data[$fila]);
                        }
                    } else {
                        $arrAdquirente['ofe_identificacion'] = $columnas->nit_ofe;
                        $adq_identificacion = $this->sanitizarStrings($columnas->{$campo_identificacion});

                        $llaveAdquirente = (array_key_exists($columnas->nit_ofe, $identificadoresUnicos) && in_array('adq_id_personalizado', $identificadoresUnicos[$columnas->nit_ofe])) ? 
                            $columnas->nit_ofe . '|' . $adq_identificacion . '|' . $columnas->id_personalizado :
                            $columnas->nit_ofe . '|' . $adq_identificacion;

                        if (array_key_exists($llaveAdquirente, $arrAdquirenteProceso)) {
                            $errores = $this->adicionarError($errores, ['EL Nit del ' . ucwords($titulo['title']) . ' ' . $adq_identificacion . ' ya existe en otras filas.'], $fila);
                        } else {
                            $arrAdquirenteProceso[$llaveAdquirente] = true;
                        }

                        //nit_ofe
                        if (array_key_exists($columnas->nit_ofe, $arrExisteOfe)) {
                            $objExisteOfe = $arrExisteOfe[$columnas->nit_ofe];
                        } else {
                            $objExisteOfe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'ofe_identificacion', 'bdd_id_rg', 'ofe_identificador_unico_adquirente', 'ofe_informacion_personalizada_adquirente'])
                                ->where('ofe_identificacion', $columnas->nit_ofe)
                                ->validarAsociacionBaseDatos()
                                ->where('estado', 'ACTIVO')
                                ->first();
                            $arrExisteOfe[$columnas->nit_ofe] = $objExisteOfe;
                        }

                        if ($objExisteOfe) {
                            $arrAdquirente['ofe_id'] = $objExisteOfe->ofe_id;

                            $objExisteAdquirente = $this->className::select('adq_id', 'estado')
                                ->where('adq_identificacion', $adq_identificacion)
                                ->where('ofe_id', $objExisteOfe->ofe_id);
                    
                            // Verifica la configuración de la llave de adquirentes que tiene el OFE
                            if(
                                !empty($objExisteOfe->ofe_identificador_unico_adquirente) &&
                                in_array('adq_id_personalizado', $objExisteOfe->ofe_identificador_unico_adquirente)
                            ) {
                                if(isset($columnas->id_personalizado) && !empty($columnas->id_personalizado))
                                    $objExisteAdquirente = $objExisteAdquirente->where('adq_id_personalizado', $columnas->id_personalizado);
                                else
                                    $objExisteAdquirente = $objExisteAdquirente->whereNull('adq_id_personalizado');
                            }
                    
                            $objExisteAdquirente = $objExisteAdquirente->first();

                            if (!empty($objExisteAdquirente)) {
                                if ($objExisteAdquirente->estado == 'INACTIVO') {
                                    $errores = $this->adicionarError($errores, ['No se permiten actualizar registros en estado INACTIVO.'], $fila);
                                } else {
                                    $arrAdquirente['adq_id'] = $objExisteAdquirente->adq_id;
                                }
                            } else {
                                $arrAdquirente['adq_id'] = 0;
                            }

                            $arrAdquirente['adq_identificacion']   = (string)$this->sanitizarStrings($columnas->{$campo_identificacion});
                            $arrAdquirente['adq_id_personalizado'] = (isset($columnas->id_personalizado) && !empty($columnas->id_personalizado)) ? (string)$columnas->id_personalizado : null;

                            if (!preg_match("/^[1-9]/", $arrAdquirente['adq_identificacion']) && 
                                ($columnas->codigo_tipo_documento == '13' || $columnas->codigo_tipo_documento == '31')
                            ) {
                                $errores = $this->adicionarError($errores, ['El formato del campo Identificación del Adquirente es inválido.'], $fila);
                            }

                            $arrAdquirente['adq_informacion_personalizada'] = [];
                            if(array_key_exists($objExisteOfe->ofe_identificacion, $columnasPersonalizadas)) {
                                foreach($columnasPersonalizadas[$objExisteOfe->ofe_identificacion] as $columnaPersonalizada) {
                                    $columnaPersonalizadaSanitizada = $this->sanear_string(str_replace(' ', '_', mb_strtolower($columnaPersonalizada, 'UTF-8')));

                                    if(isset($columnas->$columnaPersonalizadaSanitizada) && !empty($columnas->$columnaPersonalizadaSanitizada)) {
                                        $arrAdquirente['adq_informacion_personalizada'][$columnaPersonalizada] = $columnas->$columnaPersonalizadaSanitizada;
                                    }
                                }
                            }

                            if(!empty($arrAdquirente['adq_informacion_personalizada']))
                                $arrAdquirente['adq_informacion_personalizada'] = json_encode($arrAdquirente['adq_informacion_personalizada']);
                            else
                                $arrAdquirente['adq_informacion_personalizada'] = null;
                        } else {
                            $errores = $this->adicionarError($errores, ['El Id del OFE [' . $columnas->nit_ofe . '] no existe.'], $fila);
                        }

                        if (count($errores) > 0)
                            continue;

                        $arrAdquirente['ref_id'] = '';
                        if (!empty($columnas->codigo_responsabilidades_fiscales)) {
                            $codigos_responsabilidades_fiscales = explode(';', $columnas->codigo_responsabilidades_fiscales);
                            $responsabilidades_fiscales = ParametrosResponsabilidadFiscal::select(['ref_codigo', 'fecha_vigencia_desde', 'fecha_vigencia_hasta'])
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
                                    $errores = $this->adicionarError($errores, ['El Código de la responsabilidad fiscal ['.$codigo.'], ya no está vigente, se encuentra INACTIVO o no existe.'], $fila);
                                }
                                if ($codigo != 'R-99-PN' && $this->isConsumidorFinal($arrAdquirente['adq_identificacion'])) {
                                    $errores = $this->adicionarError($errores, ['Para el Consumidor Final solo aplica el tipo de Responsabilidad Fiscal R-99-PN.'], $fila);
                                }
                            }
                            if (!empty($codigos)){
                                $arrAdquirente['ref_id'] = implode(';',$codigos);
                            }
                        }

                        $arrAdquirente['tat_id'] = null;
                        if (property_exists($columnas, 'tiempo_aceptacion_tacita') && !empty($columnas->codigo_tiempo_aceptacion_tacita)) {
                            if (array_key_exists($columnas->codigo_tiempo_aceptacion_tacita, $arrExisteTiempoAceptacionTacita)) {
                                $objExisteTiempoAceptacionTacita = $arrExisteTiempoAceptacionTacita[$columnas->codigo_tiempo_aceptacion_tacita];
                                $arrAdquirente['tat_id'] = $objExisteTiempoAceptacionTacita->tat_id;
                            } else {
                                $errores = $this->adicionarError($errores, ['la Descripción del Tiempo Aceptación Tacita [' . $columnas->codigo_tiempo_aceptacion_tacita . '] no existe'], $fila);
                            }
                        }

                        $arrAdquirente['cpo_id'] = null;
                        if (!empty($columnas->codigo_postal)) {
                            if (array_key_exists($columnas->codigo_postal, $arrExisteCodigoPostal)) {
                                $objExisteCodigoPostal = $arrExisteCodigoPostal[$columnas->codigo_postal];
                                $arrAdquirente['cpo_id'] = $objExisteCodigoPostal->cpo_id;
                            } elseif ($columnas->codigo_postal == $columnas->codigo_departamento . $columnas->codigo_municipio) {
                                $arrAdquirente['cpo_id'] = null;
                            } else {
                                $errores = $this->adicionarError($errores, ['El Código Postal [' . $columnas->codigo_postal . '], ya no está vigente, se encuentra INACTIVO o no existe.'], $fila);
                            }
                        }

                        $arrAdquirente['cpo_id_domicilio_fiscal'] = null;
                        if (!empty($columnas->codigo_postal_domicilio_fiscal)) {
                            if (array_key_exists($columnas->codigo_postal_domicilio_fiscal, $arrExisteCodigoPostal)) {
                                $objExisteCodigoPostal = $arrExisteCodigoPostal[$columnas->codigo_postal_domicilio_fiscal];
                                $arrAdquirente['cpo_id_domicilio_fiscal'] = $objExisteCodigoPostal->cpo_id;
                            } elseif ($columnas->codigo_postal_domicilio_fiscal == $columnas->codigo_departamento_domicilio_fiscal . $columnas->codigo_municipio_domicilio_fiscal) {
                                $arrAdquirente['cpo_id_domicilio_fiscal'] = null;
                            } else {
                                $errores = $this->adicionarError($errores, ['El Código Postal del Domicilio Fiscal [' . $columnas->codigo_postal_domicilio_fiscal . '], ya no está vigente, se encuentra INACTIVO o no existe.'], $fila);
                            }
                        }

                        $arrAdquirente['tributos'] = [];
                        if (property_exists($columnas, 'responsable_tributos') && !empty($columnas->responsable_tributos)) {
                            $tributos = explode(',', $columnas->responsable_tributos);

                            if (count($tributos) > 0) {
                                foreach ($tributos as $tributo) {
                                    if (array_key_exists($tributo, $arrExisteResponsablesTributos)) {
                                        $objExisteTributos = $arrExisteResponsablesTributos[$tributo];
                                        array_push($arrAdquirente['tributos'], $objExisteTributos->tri_id);
                                    } else {
                                        $errores = $this->adicionarError($errores, ['El Código del Tributo [' . $tributo . '], ya no está vigente, se encuentra INACTIVO, no existe o el campo Aplica Persona es diferente de SI'], $fila);
                                    }
                                }
                            }
                        }

                        $arrAdquirente['adq_tipo_adquirente'] = '';
                        if (property_exists($columnas, 'tipo_adquirente') && isset($columnas->tipo_adquirente) && !empty($columnas->tipo_adquirente)) {
                            if ($columnas->tipo_adquirente == 'SI' || $columnas->tipo_adquirente == 'NO') {
                                $arrAdquirente['adq_tipo_adquirente'] = $columnas->tipo_adquirente;
                            } else {
                                $errores = $this->adicionarError($errores, ['El campo tipo adquirente debe contener SI o NO como valor.'], $fila);
                            }
                        }

                        $arrAdquirente['adq_tipo_autorizado'] = '';
                        if (property_exists($columnas, 'tipo_autorizado') && isset($columnas->tipo_autorizado) && !empty($columnas->tipo_autorizado)) {
                            if ($columnas->tipo_autorizado == 'SI' || $columnas->tipo_autorizado == 'NO') {
                                $arrAdquirente['adq_tipo_autorizado'] = $columnas->tipo_autorizado;
                            } else {
                                $errores = $this->adicionarError($errores, ['El campo tipo autorizado debe contener SI o NO como valor.'], $fila);
                            }
                        }

                        $arrAdquirente['adq_tipo_responsable_entrega'] = '';
                        if (property_exists($columnas, 'tipo_responsable_entrega') && isset($columnas->tipo_responsable_entrega) && !empty($columnas->tipo_responsable_entrega)) {
                            if ($columnas->tipo_responsable_entrega == 'SI' || $columnas->tipo_responsable_entrega == 'NO') {
                                $arrAdquirente['adq_tipo_responsable_entrega'] = $columnas->tipo_responsable_entrega;
                            } else {
                                $errores = $this->adicionarError($errores, ['El campo tipo responsable entrega debe contener SI o NO como valor.'], $fila);
                            }
                        }

                        $arrAdquirente['adq_tipo_vendedor_ds'] = '';
                        if (property_exists($columnas, 'tipo_vendedor_ds') && isset($columnas->tipo_vendedor_ds) && !empty($columnas->tipo_vendedor_ds)) {
                            if ($columnas->tipo_vendedor_ds == 'SI' || $columnas->tipo_vendedor_ds == 'NO') {
                                $arrAdquirente['adq_tipo_vendedor_ds'] = $columnas->tipo_vendedor_ds;
                            } else {
                                $errores = $this->adicionarError($errores, ['El campo tipo vendedor documento soporte debe contener SI o NO como valor.'], $fila);
                            }
                        }

                        if ($this->getLabelType() == 'Vendedor') {
                            if (!empty($columnas->codigo_responsabilidades_fiscales)) {
                                $codFiscales = explode(';', $columnas->codigo_responsabilidades_fiscales);
                                $errores = $this->adicionarError($errores, $this->validarResponsibilidadFiscalDocumentos($codFiscales, 'DS'), $fila);
                            }

                            if (!empty($columnas->responsable_tributos)) {
                                $codTributos = explode(',', $columnas->responsable_tributos);
                                $errores = $this->adicionarError($errores, $this->validarTributoDocumentos($codTributos, 'DS'), $fila);
                            }
                        } else {
                            if (!empty($columnas->codigo_responsabilidades_fiscales)) {
                                $codFiscales = explode(';', $columnas->codigo_responsabilidades_fiscales);
                                $errores = $this->adicionarError($errores, $this->validarResponsibilidadFiscalDocumentos($codFiscales, 'DE'), $fila);
                            }

                            if (!empty($columnas->responsable_tributos)) {
                                $codTributos = explode(',', $columnas->responsable_tributos);
                                $errores = $this->adicionarError($errores, $this->validarTributoDocumentos($codTributos, 'DE'), $fila);
                            }
                        }

                        // DIRECCIÓN CORRESPONDENCIA
                        $arrAdquirente['pai_id'] = null;
                        $arrAdquirente['dep_id'] = null;
                        $arrAdquirente['mun_id'] = null;
                        $seccionCompleta         = true;

                        if($columnas->nit_ofe != '860502609' && $columnas->nit_ofe != '830076778') {
                            // Si llega el pais, departamento, ciudad o direccion, el país y dirección del apartado de domicilio son obligatorios
                            if(
                                (isset($columnas->codigo_pais) && $columnas->codigo_pais != '') ||
                                (isset($columnas->codigo_departamento) && $columnas->codigo_departamento != '') ||
                                (isset($columnas->codigo_municipio) && $columnas->codigo_municipio != '') ||
                                (isset($columnas->direccion) && $columnas->direccion != '')
                            ) {
                                if(!isset($columnas->codigo_pais) || (isset($columnas->codigo_pais) && $columnas->codigo_pais == ''))
                                    $seccionCompleta = false;

                                if(!isset($columnas->direccion) || (isset($columnas->direccion) && $columnas->direccion == ''))
                                    $seccionCompleta = false;

                                if(!$seccionCompleta && $columnas->codigo_pais == 'CO')
                                    $errores = $this->adicionarError($errores, ['Los campos País y Dirección son obligatorios para el Domicilio de Correspondencia'], $fila);
                            }
                        // Aplica para adquirentes de DHL Express
                        } else {
                            //Es obligatorio para todos los tipos de documento
                            if(
                                (!isset($columnas->codigo_pais) || (isset($columnas->codigo_pais) && $columnas->codigo_pais == '')) ||
                                (!isset($columnas->codigo_departamento) || (isset($columnas->codigo_departamento) && $columnas->codigo_departamento == '')) ||
                                (!isset($columnas->codigo_municipio) || (isset($columnas->codigo_municipio) && $columnas->codigo_municipio == '')) ||
                                (!isset($columnas->direccion) || (isset($columnas->direccion) && $columnas->direccion == ''))
                            ) {
                                $seccionCompleta = false;
                                $errores = $this->adicionarError($errores, ['Los campos País, Departamento, Municipio y Dirección son obligatorios para el Domicilio de Correspondencia'], $fila);
                            }
                        }

                        if($seccionCompleta) {
                            $arrAdquirente['pai_codigo'] = $columnas->codigo_pais ?? 0;
                            if (isset($columnas->codigo_pais) && !empty($columnas->codigo_pais)) {
                                if (array_key_exists($columnas->codigo_pais, $arrExistePais))
                                    $objExistePais = $arrExistePais[$columnas->codigo_pais];
                                else {
                                    $objExistePais = ParametrosPais::where('estado', 'ACTIVO')->where('pai_codigo', $columnas->codigo_pais)
                                        ->first();
                                    if ($this->getLabelType() == 'Vendedor') {
                                        if (!empty($columnas->codigo_procedencia_vendedor) && $columnas->codigo_pais != 'CO' && $columnas->codigo_procedencia_vendedor == '10') {
                                            $errores = $this->adicionarError($errores, ['En Domicilio de Correspondencia el País ['.$columnas->codigo_pais. ' - ' .$objExistePais->pai_descripcion.'] seleccionado no es válido para la Procedencia del Vendedor seleccionada'], $fila);
                                        }
                                    }
                                    $arrExistePais[$columnas->codigo_pais] = $objExistePais;
                                }

                                $arrAdquirente['cpo_codigo'] = $columnas->codigo_postal;
                                $arrAdquirente['dep_codigo'] = $columnas->codigo_departamento;
                                $arrAdquirente['mun_codigo'] = $columnas->codigo_municipio;
                                if (isset($objExistePais) && !empty($objExistePais)) {
                                    $arrAdquirente['pai_id'] = $objExistePais->pai_id;

                                    if (isset($columnas->codigo_departamento) && !empty($columnas->codigo_departamento) && $columnas->codigo_pais == 'CO') {
                                        if (array_key_exists($columnas->codigo_pais . '-' . $columnas->codigo_departamento, $arrExistePaisDepto)) {
                                            $objExistePaisDepto = $arrExistePaisDepto[$columnas->codigo_pais . '-' . $columnas->codigo_departamento];
                                        } else {
                                            $objExistePaisDepto = ParametrosDepartamento::where('estado', 'ACTIVO')->where('pai_id', $objExistePais->pai_id)
                                                ->where('dep_codigo', $columnas->codigo_departamento)->first();
                                            $arrExistePaisDepto[$columnas->codigo_pais . '-' . $columnas->codigo_departamento] = $objExistePaisDepto;
                                        }

                                        if ((!isset($objExistePaisDepto) || !$objExistePaisDepto) && $columnas->codigo_pais == 'CO') {
                                            $errores = $this->adicionarError($errores, ['El Id del Departamento [' . $columnas->codigo_departamento . '] del Domicilio de Correspondencia no existe para el País [' . $columnas->codigo_pais . '] del Domicilio de Correspondencia.'], $fila);
                                        }
                                    }

                                    if ((isset($columnas->codigo_municipio) && !empty($columnas->codigo_municipio)) && (isset($columnas->codigo_departamento) && empty($columnas->codigo_departamento)) && $columnas->codigo_pais == 'CO') {
                                        $errores = $this->adicionarError($errores, ['El Departamento del Domicilio de Correspondencia es obligatorio si se ha seleccionado un Municipio en el Domicilio de Correspondencia'], $fila);
                                    } else {
                                        if ((isset($objExistePaisDepto) || $columnas->codigo_pais != 'CO') && isset($columnas->codigo_municipio) && !empty($columnas->codigo_municipio)) {
                                            $objExisteMunDepto = null;
                                            if (!empty($objExistePaisDepto)) {
                                                $arrAdquirente['dep_id'] = $objExistePaisDepto->dep_id;
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
                                                $arrAdquirente['mun_id'] = $objExisteMunDepto->mun_id;
                                            } else {
                                                if($columnas->codigo_pais != 'CO')
                                                    $errores = $this->adicionarError($errores, ['El Id del Municipio [' . $columnas->codigo_municipio . '] del Domicilio de Correspondencia no existe para el País [' . $columnas->codigo_pais . '] del Domicilio de Correspondencia.'], $fila);
                                                else
                                                    $errores = $this->adicionarError($errores, ['El Id del Municipio [' . $columnas->codigo_municipio . '] del Domicilio de Correspondencia no existe para el Departamento [' . $columnas->codigo_departamento . '] del Domicilio de Correspondencia.'], $fila);
                                            }
                                        }
                                    }
                                } else {
                                    $errores = $this->adicionarError($errores, ['El Id del País del Domicilio de Correspondencia [' . $columnas->codigo_pais . '] no existe'], $fila);
                                }
                            }
                        }

                        //DOMICILIO FISCAL
                        $arrAdquirente['pai_id_domicilio_fiscal'] = null;
                        $arrAdquirente['dep_id_domicilio_fiscal'] = null;
                        $arrAdquirente['mun_id_domicilio_fiscal'] = null;
                        $seccionCompleta                          = true;

                        if($columnas->nit_ofe != '860502609' && $columnas->nit_ofe != '830076778') {
                            // Si llega el pais, departamento, ciudad o direccion fiscal, el país y dirección del apartado de domicilio fiscal son obligatorios
                            if(
                                (isset($columnas->codigo_pais_domicilio_fiscal) && $columnas->codigo_pais_domicilio_fiscal != '') ||
                                (isset($columnas->codigo_departamento_domicilio_fiscal) && $columnas->codigo_departamento_domicilio_fiscal != '') ||
                                (isset($columnas->codigo_municipio_domicilio_fiscal) && $columnas->codigo_municipio_domicilio_fiscal != '') ||
                                (isset($columnas->direccion_domicilio_fiscal) && $columnas->direccion_domicilio_fiscal != '')
                            ) {
                                if(!isset($columnas->codigo_pais_domicilio_fiscal) || (isset($columnas->codigo_pais_domicilio_fiscal) && $columnas->codigo_pais_domicilio_fiscal == ''))
                                    $seccionCompleta = false;

                                if(!isset($columnas->direccion_domicilio_fiscal) || (isset($columnas->direccion_domicilio_fiscal) && $columnas->direccion_domicilio_fiscal == ''))
                                    $seccionCompleta = false;

                                if(!$seccionCompleta && $columnas->codigo_pais_domicilio_fiscal == 'CO')
                                    $errores = $this->adicionarError($errores, ['Los campos País y Dirección son obligatorios para el Domicilio Fiscal '], $fila);
                            }
                        // Aplica para adquirentes de DHL Express
                        } else {
                            //En el Domicilio Fiscal los campos de País, Departamento ,Municipio y Dirección son obligatorios cuando el tipo de documento es 31 ​(NIT)
                            if($columnas->codigo_tipo_documento == '31') {
                                if(
                                    (!isset($columnas->codigo_pais_domicilio_fiscal) || (isset($columnas->codigo_pais_domicilio_fiscal) && $columnas->codigo_pais_domicilio_fiscal == '')) ||
                                    (!isset($columnas->codigo_departamento_domicilio_fiscal) || (isset($columnas->codigo_departamento_domicilio_fiscal) && $columnas->codigo_departamento_domicilio_fiscal == '')) ||
                                    (!isset($columnas->codigo_municipio_domicilio_fiscal) || (isset($columnas->codigo_municipio_domicilio_fiscal) && $columnas->codigo_municipio_domicilio_fiscal == '')) ||
                                    (!isset($columnas->direccion_domicilio_fiscal) || (isset($columnas->direccion_domicilio_fiscal) && $columnas->direccion_domicilio_fiscal == ''))
                                ) {
                                    $seccionCompleta = false;
                                    $errores = $this->adicionarError($errores, ['Los campos País, Departamento, Municipio y Dirección son obligatorios para el Domicilio Fiscal '], $fila);
                                }
                            }
                        }

                        if($seccionCompleta) {
                            if (isset($columnas->codigo_pais_domicilio_fiscal) && !empty($columnas->codigo_pais_domicilio_fiscal)) {
                                if (array_key_exists($columnas->codigo_pais_domicilio_fiscal, $arrExistePais))
                                    $objExistePaisDomFiscal = $arrExistePais[$columnas->codigo_pais_domicilio_fiscal];
                                else {
                                    $objExistePaisDomFiscal = ParametrosPais::where('estado', 'ACTIVO')->where('pai_codigo', $columnas->codigo_pais_domicilio_fiscal)
                                        ->first();
                                    if ($this->getLabelType() == 'Vendedor') {
                                        if (!empty($columnas->codigo_procedencia_vendedor) && $columnas->codigo_pais_domicilio_fiscal != 'CO' && $columnas->codigo_procedencia_vendedor == '10') {
                                            $errores = $this->adicionarError($errores, ['En Domicilio Fiscal el País ['.$columnas->codigo_pais_domicilio_fiscal. ' - ' .$objExistePaisDomFiscal->pai_descripcion.'] seleccionado no es válido para la Procedencia del Vendedor seleccionada'], $fila);
                                        }
                                    }
                                    $arrExistePais[$columnas->codigo_pais_domicilio_fiscal] = $objExistePaisDomFiscal;
                                }

                                if (isset($objExistePaisDomFiscal) && !empty($objExistePaisDomFiscal)) {
                                    $arrAdquirente['pai_id_domicilio_fiscal'] = $objExistePaisDomFiscal->pai_id;

                                    if (isset($columnas->codigo_departamento_domicilio_fiscal) && $columnas->codigo_departamento_domicilio_fiscal != '' && $columnas->codigo_pais_domicilio_fiscal == 'CO') {
                                        if (array_key_exists($columnas->codigo_pais_domicilio_fiscal . '-' . $columnas->codigo_departamento_domicilio_fiscal, $arrExistePaisDepto)) {
                                            $objExistePaisDeptoDomFiscal = $arrExistePaisDepto[$columnas->codigo_pais_domicilio_fiscal . '-' . $columnas->codigo_departamento_domicilio_fiscal];
                                        } else {
                                            $objExistePaisDeptoDomFiscal = ParametrosDepartamento::where('estado', 'ACTIVO')->where('pai_id', $objExistePaisDomFiscal->pai_id)
                                                ->where('dep_codigo', $columnas->codigo_departamento_domicilio_fiscal)->first();
                                            $arrExistePaisDepto[$columnas->codigo_pais_domicilio_fiscal . '-' . $columnas->codigo_departamento_domicilio_fiscal] = $objExistePaisDeptoDomFiscal;
                                        }

                                        if ((!isset($objExistePaisDeptoDomFiscal) || !$objExistePaisDeptoDomFiscal) && $columnas->codigo_pais_domicilio_fiscal == 'CO') {
                                            $errores = $this->adicionarError($errores, ['El Id del Departamento [' . $columnas->codigo_departamento_domicilio_fiscal . '] del Domicilio Fiscal no existe para el País [' . $columnas->codigo_pais_domicilio_fiscal . '] del Domicilio Fiscal.'], $fila);
                                        }
                                    }

                                    if ((isset($columnas->codigo_municipio_domicilio_fiscal) && !empty($columnas->codigo_municipio_domicilio_fiscal)) && (isset($columnas->codigo_departamento_domicilio_fiscal) && empty($columnas->codigo_departamento_domicilio_fiscal)) && $columnas->codigo_pais_domicilio_fiscal == 'CO') {                                    
                                        $errores = $this->adicionarError($errores, ['El Departamento del Domicilio Fiscal es obligatorio si se ha seleccionado un Municipio en el Domicilio Fiscal'], $fila);
                                    } else {
                                        if ((isset($objExistePaisDeptoDomFiscal) || $columnas->codigo_pais_domicilio_fiscal != 'CO') && isset($columnas->codigo_municipio_domicilio_fiscal) && !empty($columnas->codigo_municipio_domicilio_fiscal)) {
                                            $objExisteMunDeptoDomFiscal = null;
                                            if (!empty($objExistePaisDeptoDomFiscal)) {
                                                $arrAdquirente['dep_id_domicilio_fiscal'] = $objExistePaisDeptoDomFiscal->dep_id ?? '';
                                            }
                                            $indice = $columnas->codigo_pais_domicilio_fiscal . '-' . ($columnas->codigo_departamento_domicilio_fiscal ?? '') . '-' . $columnas->codigo_municipio_domicilio_fiscal;

                                            if (array_key_exists($indice, $arrExisteMunDepto)) {
                                                $objExisteMunDeptoDomFiscal = $arrExisteMunDepto[$indice];
                                            } else {
                                                $objExisteMunDeptoDomFiscal = ParametrosMunicipio::where('estado', 'ACTIVO')
                                                    ->where('mun_codigo', $columnas->codigo_municipio_domicilio_fiscal)
                                                    ->where('pai_id', $objExistePaisDomFiscal->pai_id);

                                                if (!empty($objExistePaisDeptoDomFiscal)) {
                                                    $objExisteMunDeptoDomFiscal->where('dep_id', ($objExistePaisDeptoDomFiscal->dep_id ?? '') );
                                                }
                                                $objExisteMunDeptoDomFiscal = $objExisteMunDeptoDomFiscal->first();

                                                $arrExisteMunDepto[$indice] = $objExisteMunDeptoDomFiscal;
                                            }
                                            
                                            if (isset($objExisteMunDeptoDomFiscal)) {
                                                $arrAdquirente['mun_id_domicilio_fiscal'] = $objExisteMunDeptoDomFiscal->mun_id;
                                            } else {
                                                if($columnas->codigo_pais_domicilio_fiscal != 'CO')
                                                    $errores = $this->adicionarError($errores, ['El Id del Municipio [' . $columnas->codigo_municipio_domicilio_fiscal . '] del Domicilio Fiscal no existe para el Pais [' . $columnas->codigo_pais_domicilio_fiscal . '] del Domicilio Fiscal.'], $fila);
                                                else
                                                    $errores = $this->adicionarError($errores, ['El Id del Municipio [' . $columnas->codigo_municipio_domicilio_fiscal . '] del Domicilio Fiscal no existe para el Departamento [' . $columnas->codigo_departamento_domicilio_fiscal . '] del Domicilio Fiscal.'], $fila);
                                            }
                                        }
                                    }
                                } else {
                                    $errores = $this->adicionarError($errores, ['El Id del País del Domicilio Fiscal [' . $columnas->codigo_pais_domicilio_fiscal . '] no existe'], $fila);
                                }
                            }
                        }
                        //FIN DE DOMICILIO FISCAL

                        $arrAdquirente['tdo_codigo'] = $columnas->codigo_tipo_documento;
                        if (array_key_exists($columnas->codigo_tipo_documento, $arrExisteTipoDocumento)) {
                            $objExisteTipoDocumento = $arrExisteTipoDocumento[$columnas->codigo_tipo_documento];
                            $arrAdquirente['tdo_id'] = $objExisteTipoDocumento->tdo_id;
                            if ($this->getLabelType() == 'Vendedor') {
                                if (!empty($columnas->codigo_procedencia_vendedor) && $columnas->codigo_tipo_documento != '31' && $columnas->codigo_procedencia_vendedor == '10') {
                                    $errores = $this->adicionarError($errores, ['El Tipo de Documento ['.$columnas->codigo_tipo_documento. ' - ' .$objExisteTipoDocumento->tdo_descripcion.'] no es válido para la Procedencia del Vendedor seleccionada'], $fila);
                                }
                            }
                        } else {
                            $mensajeError = ($this->getLabelType() == 'Vendedor') ? 'Documento Soporte' : 'Documento Electrónico';
                            $errores = $this->adicionarError($errores, ['El Código del Tipo de Documento [' . $columnas->codigo_tipo_documento . '], ya no está vigente, no aplica para ' . $mensajeError . ', se encuentra INACTIVO o no existe.'], $fila);
                        }

                        $arrAdquirente['rfi_id'] = null;
                        if (!empty($columnas->codigo_regimen_fiscal)) {
                            if (array_key_exists($columnas->codigo_regimen_fiscal, $arrExisteRegimenFiscal)) {
                                $objExisteRegimenFiscal = $arrExisteRegimenFiscal[$columnas->codigo_regimen_fiscal];
                                $arrAdquirente['rfi_id'] = $objExisteRegimenFiscal->rfi_id;
                            } else {
                                $errores = $this->adicionarError($errores, ['El Código del Régimen Fiscal [' . $columnas->codigo_regimen_fiscal . '], ya no está vigente, se encuentra INACTIVO o no existe.'], $fila);
                            }
                        }

                        if ($this->getLabelType() == 'Vendedor') {
                            $arrAdquirente['ipv_id'] = null;
                            if (!empty($columnas->codigo_procedencia_vendedor)) {
                                if (array_key_exists($columnas->codigo_procedencia_vendedor, $arrExisteProcedenciaVendedor)) {
                                    $objExisteProcedenciaVendedor = $arrExisteProcedenciaVendedor[$columnas->codigo_procedencia_vendedor];
                                    $arrAdquirente['ipv_id'] = $objExisteProcedenciaVendedor->ipv_id;
                                } else {
                                    $errores = $this->adicionarError($errores, ['El Código de Procedencia Vendedor [' . $columnas->codigo_procedencia_vendedor . '], ya no está vigente, se encuentra INACTIVO o no existe.'], $fila);
                                }
                            } else {
                                $errores = $this->adicionarError($errores, ['La Columna [codigo_procedencia_vendedor] es requerida para continuar.'], $fila);
                            }
                        }

                        $objExisteToj = $arrExisteTipoOrgJuridica[$columnas->codigo_tipo_organizacion_juridica] ?? null;
                        $arrAdquirente['toj_codigo'] = $columnas->codigo_tipo_organizacion_juridica;
                        if (!empty($objExisteToj)) {
                            $arrAdquirente['toj_id'] = $objExisteToj->toj_id;

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
                                $errores = $this->adicionarError($errores, $arrFaltantes);
                            } else {
                                $arrAdquirente['adq_razon_social']     = $this->sanitizarStrings($columnas->razon_social);
                                $arrAdquirente['adq_nombre_comercial'] = $this->sanitizarStrings($columnas->nombre_comercial);
                                $arrAdquirente['adq_primer_apellido']  = $this->sanitizarStrings($columnas->primer_apellido);
                                $arrAdquirente['adq_segundo_apellido'] = $this->sanitizarStrings($columnas->segundo_apellido);
                                $arrAdquirente['adq_primer_nombre']    = $this->sanitizarStrings($columnas->primer_nombre);
                                $arrAdquirente['adq_otros_nombres']    = $this->sanitizarStrings($columnas->otros_nombres);
                            }
                        } else {
                            $errores = $this->adicionarError($errores, ["El Codigo Tipo Organización Juridica [{$columnas->codigo_tipo_organizacion_juridica}], ya no está vigente, se encuentra INACTIVO o no existe."], $fila);
                        }

                        if (!property_exists($columnas, 'codigo_postal')) {
                            $columnas->codigo_postal = '';
                        }

                        if (!property_exists($columnas, 'matricula_mercantil')) {
                            $columnas->matricula_mercantil = '';
                        }

                        $arrAdquirente['adq_direccion']                  = $this->sanitizarStrings($columnas->direccion);
                        $arrAdquirente['adq_direccion_domicilio_fiscal'] = $this->sanitizarStrings($columnas->direccion_domicilio_fiscal);
                        $arrAdquirente['adq_matricula_mercantil']        = $this->sanitizarStrings($columnas->matricula_mercantil);
                        $arrAdquirente['adq_nombre_contacto']            = $this->sanitizarStrings((string)$columnas->nombre_contacto, 255);
                        $arrAdquirente['adq_telefono']                   = $this->sanitizarStrings((string)$columnas->telefono, 50);
                        $arrAdquirente['adq_fax']                        = $this->sanitizarStrings((string)$columnas->fax, 50);
                        $arrAdquirente['adq_correo']                     = $this->soloEmail($columnas->correo);
                        $arrAdquirente['adq_notas']                      = $this->sanitizarStrings((string)$columnas->notas);
                        $arrAdquirente['adq_correos_notificacion']       = $this->soloEmails($columnas->correos_notificacion ?? null);

                        if (empty($errores)) {
                            $objValidator = Validator::make($arrAdquirente, $this->className::$rules);

                            if (!empty($objValidator->errors()->all())) {
                                $errores = $this->adicionarError($errores, $objValidator->errors()->all(), $fila);
                            } else {
                                $arrResultado[] = $arrAdquirente;
                            }
                        }
                    }

                    if(!empty($errores)) {
                        $arrErrores[] = [
                            'errors'              => $errores,
                            'documento'           => $nombreArchivo,
                            'adquirente'          => $columnas->$campo_identificacion . ((isset($columnas->id_personalizado) && !empty($columnas->id_personalizado)) ? '~' . $columnas->id_personalizado : ''),
                            'fecha_procesamiento' => date('Y-m-d'),
                            'hora_procesamiento'  => date('H:i:s')
                        ];
                    }
                }
                if ($fila % 500 === 0) {
                    $this->renovarConexion($this->user);
                }
            }
        }

        if (!empty($arrErrores)) {
            EtlProcesamientoJson::create([
                'pjj_tipo'                => ProcesarCargaParametricaCommand::$TYPE_ADQ,
                'pjj_json'                => json_encode([]),
                'pjj_procesado'           => 'SI',
                'age_estado_proceso_json' => 'FINALIZADO',
                'pjj_errores'             => json_encode($arrErrores),
                'age_id'                  => 0,
                'usuario_creacion'        => $this->user->usu_id,
                'fecha_creacion'          => date('Y-m-d H:i:s'),
                'estado'                  => 'ACTIVO'
            ]);

            return response()->json([
                'message' => 'Errores al guardar los ' . ucwords($titulo['title']),
                'errors' => ['Verifique el Log de Errores'],
            ], 400);
        } else {
            $insertAdqs = [];

            foreach ($arrResultado as $adquirente) {
                $data = [
                    'adq_id'                         => $adquirente['adq_id'],
                    'ofe_id'                         => $adquirente['ofe_id'],
                    'adq_identificacion'             => $adquirente['adq_identificacion'],
                    'adq_id_personalizado'           => $adquirente['adq_id_personalizado'],
                    'adq_informacion_personalizada'  => !empty($adquirente['adq_informacion_personalizada']) ? $adquirente['adq_informacion_personalizada'] : null,
                    'adq_razon_social'               => !empty($adquirente['adq_razon_social']) ? $this->sanitizarStrings($adquirente['adq_razon_social']) : null,
                    'adq_nombre_comercial'           => !empty($adquirente['adq_nombre_comercial']) ? $this->sanitizarStrings($adquirente['adq_nombre_comercial']) : null,
                    'adq_primer_apellido'            => !empty($adquirente['adq_primer_apellido']) ? $this->sanitizarStrings($adquirente['adq_primer_apellido']) : null,
                    'adq_segundo_apellido'           => !empty($adquirente['adq_segundo_apellido']) ? $this->sanitizarStrings($adquirente['adq_segundo_apellido']) : null,
                    'adq_primer_nombre'              => !empty($adquirente['adq_primer_nombre']) ? $this->sanitizarStrings($adquirente['adq_primer_nombre']) : null,
                    'adq_otros_nombres'              => !empty($adquirente['adq_otros_nombres']) ? $this->sanitizarStrings($adquirente['adq_otros_nombres']) : null,
                    $this->getKeyAdq()               => 'SI',
                    'tdo_id'                         => $adquirente['tdo_id'],
                    'toj_id'                         => $adquirente['toj_id'],
                    'pai_id'                         => $adquirente['pai_id'],
                    'dep_id'                         => !empty($adquirente['dep_id']) ? $adquirente['dep_id']: null,
                    'mun_id'                         => !empty($adquirente['mun_id']) ? $adquirente['mun_id']: null,
                    'cpo_id'                         => !empty($adquirente['cpo_id']) ? $adquirente['cpo_id']: null,
                    'adq_direccion'                  => !empty($adquirente['adq_direccion']) ? $this->sanitizarStrings($adquirente['adq_direccion']) : null,
                    'pai_id_domicilio_fiscal'        => !empty($adquirente['pai_id_domicilio_fiscal']) ? $adquirente['pai_id_domicilio_fiscal']: null,
                    'dep_id_domicilio_fiscal'        => !empty($adquirente['dep_id_domicilio_fiscal']) ? $adquirente['dep_id_domicilio_fiscal']: null,
                    'mun_id_domicilio_fiscal'        => !empty($adquirente['mun_id_domicilio_fiscal']) ? $adquirente['mun_id_domicilio_fiscal']: null,
                    'cpo_id_domicilio_fiscal'        => !empty($adquirente['cpo_id_domicilio_fiscal']) ? $adquirente['cpo_id_domicilio_fiscal'] : null,
                    'adq_direccion_domicilio_fiscal' => !empty($adquirente['adq_direccion_domicilio_fiscal']) ? $this->sanitizarStrings($adquirente['adq_direccion_domicilio_fiscal']) : null,
                    'adq_nombre_contacto'            => !empty($adquirente['adq_nombre_contacto']) ? $this->sanitizarStrings($adquirente['adq_nombre_contacto']) : null,
                    'adq_telefono'                   => !empty($adquirente['adq_telefono']) ? $this->sanitizarStrings($adquirente['adq_telefono']) : null,
                    'adq_fax'                        => !empty($adquirente['adq_fax']) ? $this->sanitizarStrings($adquirente['adq_fax']) : null,
                    'adq_correo'                     => $this->sanitizarStrings($adquirente['adq_correo']),
                    'adq_notas'                      => !empty($adquirente['adq_notas']) ? $this->sanitizarStrings($adquirente['adq_notas']) : null,
                    'adq_correos_notificacion'       => !empty($adquirente['adq_correos_notificacion']) ? $this->sanitizarStrings($adquirente['adq_correos_notificacion']) : null,
                    'rfi_id'                         => $adquirente['rfi_id'],
                    'ref_id'                         => $adquirente['ref_id'],
                    'ipv_id'                         => !empty($adquirente['ipv_id']) ? $adquirente['ipv_id']: null,
                    'adq_matricula_mercantil'        => $this->sanitizarStrings($adquirente['adq_matricula_mercantil']),
                    'tributos'                       => $adquirente['tributos'],
                    'tat_id'                         => $adquirente['tat_id']
                ];
                array_push($insertAdqs, $data);
            }

            if (count($insertAdqs) > 0) {
                $bloques = array_chunk($insertAdqs, 20000);

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
                            'pjj_tipo'         => ProcesarCargaParametricaCommand::$TYPE_ADQ,
                            'pjj_json'         => json_encode($bloque),
                            'pjj_procesado'    => 'NO',
                            'age_id'           => $agendamiento->age_id,
                            'usuario_creacion' => $this->user->usu_id,
                            'fecha_creacion'   => date('Y-m-d H:i:s'),
                            'estado'           => 'ACTIVO'
                        ]);
                    }
                }
            }

            if (!empty($arrErrores)) {
                return response()->json([
                    'message' => 'Errores al guardar los ' . ucwords($titulo['title']),
                    'errors'  => $arrErrores
                ], 422);
            } else {
                return response()->json([
                    'success' => true
                ], 200);
            }
        }
    }

    /**
     * Permite validar que el adquirente contenga al menos un tipo seteado.
     *
     * @param Object $object objeto que contiene las propiedades del adquirente
     * @param Array $arrErrores Array de errores
     * @param Int $fila Parámetro que se usa en la carga por excel para saber en cuál fila se produjo el error
     * @return void
     */
    private function validarTipoAdquirente($object, array &$arrErrores, int $fila = -1) {
        if ($object->adq_tipo_autorizado !== 'SI' && $object->adq_tipo_adquirente !== 'SI' && $object->adq_tipo_responsable_entrega !== 'SI' && $object->adq_tipo_vendedor_ds !== 'SI') {
            $arrErrores = $this->adicionarError($arrErrores, ["El Adquirente [$object->nit_adquirente] Debe tener al menos un tipo"], $fila);
        }
    }

    /**
     * Retorna la clave para definir que tipo de registro es Adquirente, Autorizado, Responsable y Vendedor.
     *
     * @param bool $di Indica si la petición proviene del microservicio DI
     * @param string $adqTipo Cuando una petición viene del microservicio DI podría incluir el tipo de adquirente
     * @return string
     */
    private function getKeyAdq(bool $di = false, string $adqTipo = null) {
        if(array_key_exists('REQUEST_URI', $_SERVER) && !empty($_SERVER['REQUEST_URI'])) {
            if (strpos($_SERVER['REQUEST_URI'], 'adquirentes') !== false || ($di && is_null($adqTipo)))
                return 'adq_tipo_adquirente';
            elseif (strpos($_SERVER['REQUEST_URI'], 'vendedores') !== false || ($di && !is_null($adqTipo) && $adqTipo == 'adq_tipo_vendedor_ds'))
                return 'adq_tipo_vendedor_ds';
            elseif (strpos($_SERVER['REQUEST_URI'], 'autorizados') !== false)
                return 'adq_tipo_autorizado';
            else
                return 'adq_tipo_responsable_entrega';
        } else {
            return 'adq_tipo_adquirente';
        }
    }

    /**
     * Permite validar parametros recibidos para los métodos store y update.
     *
     * @param Request $request Parámetros de la petición
     * @return array Contiene los ids de los datos validados
     */
    public function validarDatos(Request $request) {
        // Validaciones particulares
        if (!$request->adq_razon_social && !$request->adq_primer_apellido && !$request->adq_primer_nombre)
            $this->errors = $this->adicionarError($this->errors, ['No se encontró la razón social o nombres del adquirente']);

        $datos = [];
        // Extrayendo nits consumidor final
        $arrAdquirenteConsumidorFinal = [];
        $adquirenteConsumidorFinal = json_decode(config('variables_sistema.ADQUIRENTE_CONSUMIDOR_FINAL'), true);
        foreach($adquirenteConsumidorFinal as $consumidorFinal) {
            $arrAdquirenteConsumidorFinal[$consumidorFinal['adq_identificacion']] = $consumidorFinal;
        }
        // Se verifica si el adquirente corresponde a un Consumidor Final o no
        $nombreCompleto = $request->adq_primer_nombre . ' ' . $request->adq_otros_nombres . ' ' . $request->adq_primer_apellido . ' ' . $request->adq_segundo_apellido;
        if( array_key_exists($request->adq_identificacion, $arrAdquirenteConsumidorFinal) ||
            (stristr($request->adq_razon_social, 'consumidor') && stristr($request->adq_razon_social, 'final')) ||
            (stristr($nombreCompleto, 'consumidor') && stristr($nombreCompleto, 'final'))
        ) {
            if(!array_key_exists($request->adq_identificacion, $arrAdquirenteConsumidorFinal)) {
                $this->errors = $this->adicionarError($this->errors, ['El NIT ['.$request->adq_identificacion.'] de Consumidor Final enviado no corresponde al NIT de Consumidor Final autorizado por la DIAN']);
            } elseif(array_key_exists($request->adq_identificacion, $arrAdquirenteConsumidorFinal)) {
                $datos['consumidor_final'] = $arrAdquirenteConsumidorFinal[$request->adq_identificacion];
            }
        }

        if ($this->getLabelType() == 'Vendedor') {
            $datos['tdo_id'] = $this->validarTipoDocumento($request->tdo_codigo, 'DS', $request->ipv_codigo);

            if ($request->has('pai_codigo') && !empty($request->pai_codigo))
                $datos['pai_id'] = $this->validarPais($request->pai_codigo, 'En domicilio de correspondencia', false, false, $request->ipv_codigo);
        } else {
            $datos['tdo_id'] = $this->validarTipoDocumento($request->tdo_codigo, 'DE');

            if ($request->has('pai_codigo') && !empty($request->pai_codigo))
                $datos['pai_id'] = $this->validarPais($request->pai_codigo);
        }

        if ($request->has('pai_codigo') && $request->has('dep_codigo') && !empty($request->dep_codigo) && !empty($datos['pai_id']))
            $datos['dep_id'] = $this->validarDepartamento($request->pai_codigo, $request->dep_codigo);
        if ($request->has('pai_codigo') && $request->has('mun_codigo') && !empty($request->mun_codigo) && !empty($datos['pai_id']))
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
            $datos['responsabilidades_fiscales'] = $this->validarResponsibilidadFiscal($request->ref_codigo, true, !$this->isConsumidorFinal($request->adq_identificacion));

        if ($request->has('responsable_tributos') && !empty($request->responsable_tributos)) {
            if (!is_array($request->responsable_tributos)) {
                $tributos = explode(',', $request->responsable_tributos);
                $datos['responsable_tributos'] = $this->validarTributo($tributos);
            } else {
                $datos['responsable_tributos'] = $this->validarTributo($request->responsable_tributos);
            }
        }

        if ($request->has('rfi_codigo') && !empty($request->rfi_codigo))
            $datos['rfi_id'] = $this->validarRegimenFiscal($request->rfi_codigo);

        if ($this->getLabelType() == 'Vendedor') {
            if ($request->has('ref_codigo') && !empty($request->ref_codigo))
                $this->validarResponsibilidadFiscalDocumentos($request->ref_codigo, 'DS');

            if ($request->has('responsable_tributos') && !empty($request->responsable_tributos))
                $this->validarTributoDocumentos($request->responsable_tributos, 'DS');

        } else {
            if ($request->has('ref_codigo') && !empty($request->ref_codigo))
                $this->validarResponsibilidadFiscalDocumentos($request->ref_codigo, 'DE');

            if ($request->has('responsable_tributos') && !empty($request->responsable_tributos))
                $this->validarTributoDocumentos($request->responsable_tributos, 'DE');
        }

        if ($request->has('ipv_codigo') && !empty($request->ipv_codigo))
            $datos['ipv_id'] = $this->validarProcedenciaVendedor($request->ipv_codigo);

        //Validamos el tipo de organizacion juridica.
        $datos['toj_id'] = $this->validarTipoOrganizacionJuridica($request);

        // Correos de notificacion
        if (!empty($request->adq_correos_notificacion)) {
            $correosNotificacion = explode(',', $request->adq_correos_notificacion);
            $this->validarCorreosNotificacion($correosNotificacion);
        }

        // Validaciones para dirección de correspondencia
        $this->validacionGeneralDomicilio($request, 'adq', 'correspondencia');
        // Validaciones para domicilio fiscal
        $this->validacionGeneralDomicilio($request, 'adq', 'fiscal');

        if ($this->getLabelType() == 'Vendedor') {
            if ($request->has('pai_codigo_domicilio_fiscal') && !empty($request->pai_codigo_domicilio_fiscal))
                $datos['pai_id_domicilio_fiscal'] = $this->validarPais($request->pai_codigo_domicilio_fiscal, 'En domicilio fiscal', false, false, $request->ipv_codigo);
        } else {
            if ($request->has('pai_codigo_domicilio_fiscal') && !empty($request->pai_codigo_domicilio_fiscal))
                $datos['pai_id_domicilio_fiscal'] = $this->validarPais($request->pai_codigo_domicilio_fiscal, 'En domicilio fiscal');
        }

        if ($request->has('pai_codigo_domicilio_fiscal') && $request->has('dep_codigo_domicilio_fiscal') && !empty($request->dep_codigo_domicilio_fiscal) && !empty($datos['pai_id_domicilio_fiscal'])) {
            $datos['dep_id_domicilio_fiscal'] = $this->validarDepartamento($request->pai_codigo_domicilio_fiscal, $request->dep_codigo_domicilio_fiscal, 'En domicilio fiscal');
        }

        if ($request->has('pai_codigo_domicilio_fiscal') && $request->has('mun_codigo_domicilio_fiscal') && !empty($request->mun_codigo_domicilio_fiscal) && !empty($datos['pai_id_domicilio_fiscal'])) {
            $datos['mun_id_domicilio_fiscal'] = $this->validarMunicipio($request->pai_codigo_domicilio_fiscal, $request->dep_codigo_domicilio_fiscal, $request->mun_codigo_domicilio_fiscal, 'En domicilio fiscal');
        }

        if ($request->has('cpo_codigo_domicilio_fiscal') && !empty($request->cpo_codigo_domicilio_fiscal)) 
            $datos['cpo_id_domicilio_fiscal'] = $this->validarCodigoPostal(
                $request->cpo_codigo_domicilio_fiscal,
                isset($request->dep_codigo_domicilio_fiscal) ? $request->dep_codigo_domicilio_fiscal : '',
                isset($request->mun_codigo_domicilio_fiscal) ? $request->mun_codigo_domicilio_fiscal : ''
            );

        return $datos;
    }

    /**
     * Obtiene la lista de errores de procesamiento de cargas masivas de adquirentes.
     *
     * @return Response
     * @throws \Exception
     */
    public function getListaErroresAdquirentes() {
        $request = request();
        $arrTipoLog = [];

        if($request->has('pjjTipo') && $request->pjjTipo == 'XML-ADQUIRENTES') {
            $arrTipoLog = ['XML-ADQUIRENTES'];
        } elseif ($request->has('pjjTipo') && $request->pjjTipo == 'ADQ-OPENCOMEX') {
            $arrTipoLog = ['ADQ-OPENCOMEX'];
        } else {
            $arrTipoLog = [ProcesarCargaParametricaCommand::$TYPE_ADQ, ProcesarCargaParametricaCommand::$TYPE_UPC];
        }

        return $this->getListaErrores($arrTipoLog);
    }

    /**
     * Retorna una lista en Excel de errores de cargue de adquirentes.
     *
     * @return Response
     * @throws \Exception
     */
    public function descargarListaErroresAdquirentes() {
        $request = request();
        $arrTipoLog = [];

        if($request->has('pjjTipo') && $request->pjjTipo == 'XML-ADQUIRENTES') {
            $arrTipoLog = ['XML-ADQUIRENTES'];
        } elseif ($request->has('pjjTipo') && $request->pjjTipo == 'ADQ-OPENCOMEX') {
            $arrTipoLog = ['ADQ-OPENCOMEX'];
        } else {
            $arrTipoLog = [ProcesarCargaParametricaCommand::$TYPE_ADQ, ProcesarCargaParametricaCommand::$TYPE_UPC];
        }

        return $this->getListaErrores($arrTipoLog, true, 'carga_adquirentes_log_errores');
    }

    /**
     * Configura el Tipo de Adquirente enviado en 'SI'.
     *
     * @param Request $request Parámetros de la petición
     * @return Response
     */
    public function editarTipoAdquirente(Request $request) {
        switch ($request->tipo) {
            case 'adquirente':
                $campo = 'adq_tipo_adquirente';
                break;
            case 'autorizado':
                $campo = 'adq_tipo_autorizado';
                break;
            case 'responsable':
                $campo = 'adq_tipo_responsable_entrega';
                break;
            case 'vendedor':
                $campo = 'adq_tipo_vendedor_ds';
                break;
            default:
                break;
        }

        $adquirente = $this->className::find($request->adq_id);
        $adquirente->$campo = 'SI';
        $adquirente->save();

        if ($adquirente) {
            return response()->json([
                'message' => 'success'
            ], 200);
        } else {
            return response()->json([
                'message' => 'No se logró actualizar el ' . $request->tipo
            ], 422);
        }
    }

    /**
     * Efectúa un proceso de búsqueda en la paramétrica.
     *
     * @param Request $request Parámetros de la petición
     * @return Response
     */
    public function busqueda(Request $request) {
        $columnas = [
            'adq_id',
            'ofe_id',
            'adq_identificacion',
            'adq_id_personalizado',
            'adq_informacion_personalizada',
            'adq_razon_social',
            'adq_nombre_comercial',
            'adq_primer_apellido',
            'adq_segundo_apellido',
            'adq_primer_nombre',
            'adq_otros_nombres',
            \DB::raw('IF(adq_razon_social IS NULL OR adq_razon_social = "", CONCAT(COALESCE(adq_primer_nombre, ""), " ", COALESCE(adq_otros_nombres, ""), " ", COALESCE(adq_primer_apellido, ""), " ", COALESCE(adq_segundo_apellido, "")), adq_razon_social) as nombre_completo'),
            'adq_tipo_adquirente',
            'adq_tipo_autorizado',
            'adq_tipo_responsable_entrega',
            'adq_tipo_vendedor_ds',
            'tdo_id',
            'toj_id',
            'pai_id',
            'dep_id',
            'mun_id',
            'cpo_id',
            'adq_direccion',
            'adq_telefono',
            'pai_id_domicilio_fiscal',
            'dep_id_domicilio_fiscal',
            'mun_id_domicilio_fiscal',
            'cpo_id_domicilio_fiscal',
            'adq_direccion_domicilio_fiscal',
            'adq_nombre_contacto',
            'adq_notas',
            'adq_correo',
            'adq_correos_notificacion',
            'rfi_id',
            'ref_id',
            'ipv_id',
            'adq_matricula_mercantil',
            'adq_campos_representacion_grafica',
            'tat_id',
            'adq_reenvio_notificacion_contingencia',
            'usuario_creacion',
            'fecha_creacion',
            'fecha_modificacion',
            'estado',
            'fecha_actualizacion',
        ];

        if ($this->getLabelType() != 'Responsable' && $this->getLabelType() != 'Autorizado')
            $columnas[] = 'adq_correos_notificacion';

        $columnas = array_merge($columnas, [
            'rfi_id',
            'ref_id',
            'ipv_id',
            'adq_matricula_mercantil',
            'tat_id',
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
            'getCodigoPostal',
            'getParametroDomicilioFiscalPais',
            'getParametroDomicilioFiscalDepartamento',
            'getParametroDomicilioFiscalMunicipio',
            'getCodigoPostalDomicilioFiscal',
            'getParametroTipoOrganizacionJuridica',
            'getRegimenFiscal',
            'getProcedenciaVendedor',
            'getTributos:tri_id,adq_id',
            'getTributos.getDetalleTributo:tri_id,tri_codigo,tri_nombre,tri_descripcion',
            'getResponsabilidadFiscal:ref_id,ref_codigo,ref_descripcion,fecha_vigencia_desde,fecha_vigencia_hasta,estado',
            'getTiempoAceptacionTacita',
            'getContactos',
            'getUsuariosPortales:upc_id,ofe_id,ofe_identificacion,adq_id,adq_identificacion,upc_identificacion,upc_nombre,upc_correo,estado'
        ];

        if ($this->getLabelType() != 'Responsable' && $this->getLabelType() != 'Autorizado' && $this->getLabelType() != 'Vendedor') {
            $incluir[] = 'getContactos';
        }

        $oferente = ConfiguracionObligadoFacturarElectronicamente::where('estado', 'ACTIVO')
            ->where('ofe_identificacion', $request->valorOfe)
            ->validarAsociacionBaseDatos()
            ->first();

        if (!is_null($oferente)) {
            $precondiciones = [
                [$this->getKeyAdq(), '=', 'SI'],
                ['ofe_id', '=', $oferente->ofe_id]
            ];

            if ($this->getLabelType() === "Adquirente")
                $incluir[] = "getContactos";
            return $this->procesadorBusqueda($request, $columnas, $incluir, $precondiciones);
        }
        else
            return response()->json([
                'data' => [],
            ], Response::HTTP_OK);
    }

    /**
     * Recibe una petición desde el microservicio DI para creación/actualización de adquirentes.
     * 
     * En el request debe llegar un array de objetos llamado adquirente con los datos del o los adquirentes a procesar
     *
     * @param Request $request Parámetros de la petición
     * @return Response
     */
    public function createFromDI(Request $request) {
        $errorsAll = [];
        if($request->has('adquirente') && is_array($request->adquirente)) {
            foreach($request->adquirente as $adqProcesar) {
                $errors = [];

                $adqTipo = null;
                $adqTipoMsj = 'Adquiriente';
                if(array_key_exists('adq_tipo_vendedor_ds', $adqProcesar) && $adqProcesar['adq_tipo_vendedor_ds'] == 'SI') {
                    $adqTipo = 'adq_tipo_vendedor_ds';
                    $adqTipoMsj = 'Vendedor Documento Soporte';
                }

                if(!array_key_exists('ofe_identificacion', $adqProcesar) || (array_key_exists('ofe_identificacion', $adqProcesar) && empty($adqProcesar['ofe_identificacion'])))
                    $errors[] = $adqTipoMsj.' [' . $adqProcesar['adq_identificacion'] . ']: No se ha proporcionado el OFE para Crear el '. ($adqTipo == null) ? 'Adquiriente' : 'Vendedor';

                if(!array_key_exists('adq_identificacion', $adqProcesar) || (array_key_exists('adq_identificacion', $adqProcesar) && empty($adqProcesar['adq_identificacion'])))
                    $errors[] = $adqTipoMsj.' [' . $adqProcesar['adq_identificacion'] . ']: No se ha proporcionado el número de identificación del '. ($adqTipo == null) ? 'Adquiriente' : 'Vendedor';

                if (empty($errors)) {
                    $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'ofe_identificador_unico_adquirente'])
                        ->where('ofe_identificacion', $adqProcesar['ofe_identificacion'])
                        ->first();

                    if (!$ofe) {
                        $errors[] = $adqTipoMsj.' [' . $adqProcesar['adq_identificacion'] . ']: ' . "EL OFE {$adqProcesar['ofe_identificacion']} no existe.";
                    } else {
                        $adq = ConfiguracionAdquirente::select(['adq_id'])
                            ->where('ofe_id', $ofe->ofe_id)
                            ->where('adq_identificacion', $adqProcesar['adq_identificacion']);

                        $buscarAdqIdPersonalizado = false;
                        if(
                            !empty($ofe->ofe_identificador_unico_adquirente) &&
                            in_array('adq_id_personalizado', $ofe->ofe_identificador_unico_adquirente)
                        ) {
                            if (array_key_exists('adq_id_personalizado', $adqProcesar) && !empty($adqProcesar['adq_id_personalizado']))
                                $adq = $adq->where('adq_id_personalizado', $adqProcesar['adq_id_personalizado']);
                            else 
                                $adq = $adq->whereNull('adq_id_personalizado');

                            $buscarAdqIdPersonalizado = true;
                        }

                        $adq = $adq->first();

                        $newRequest = new Request($adqProcesar);
                        if (!$adq && $buscarAdqIdPersonalizado) {
                            $adq = ConfiguracionAdquirente::select(['adq_id'])
                                ->where('ofe_id', $ofe->ofe_id)
                                ->where('adq_identificacion', $adqProcesar['adq_identificacion'])
                                ->whereNull('adq_id_personalizado')
                                ->first();

                            if(!$adq) {
                                $procesamiento = $this->store($newRequest, true, $adqTipo);
                            } else {
                                $procesamiento = $this->updateCompuesto(
                                    $newRequest,
                                    $adqProcesar['ofe_identificacion'],
                                    $adqProcesar['adq_identificacion'],
                                    (array_key_exists('adq_id_personalizado', $adqProcesar) ? $adqProcesar['adq_id_personalizado'] : null),
                                    true,
                                    $adqTipo
                                );
                            }
                        } elseif (!$adq && !$buscarAdqIdPersonalizado) {
                            $procesamiento = $this->store($newRequest, true, $adqTipo);
                        } else {
                            $procesamiento = $this->updateCompuesto(
                                $newRequest,
                                $adqProcesar['ofe_identificacion'],
                                $adqProcesar['adq_identificacion'],
                                (array_key_exists('adq_id_personalizado', $adqProcesar) ? $adqProcesar['adq_id_personalizado'] : null),
                                true,
                                $adqTipo
                            );
                        }

                        $resultado = json_decode($procesamiento->getContent(), true);
                        if(array_key_exists('errors', $resultado) && !empty($resultado['errors'])) {
                            foreach($resultado['errors'] as $rptaError) {
                                $errors[] = $adqTipoMsj.' [' . $adqProcesar['adq_identificacion'] . ']: ' . $rptaError;
                            }
                        }
                    }
                }

                if (!empty($errors)) {
                    $errorsAll = array_merge($errorsAll, $errors);
                }
            }
        } else {
            $errorsAll[] = 'Se espera un array de objetos para poder procesar el (los) adquirente(s)';
        }

        if(empty($errorsAll)) {
            return response()->json([
                'message' => 'Información de adquirentes procesada de manera correcta'
            ], 201);
        } else {
            return response()->json([
                'message' => 'Errores al procesar el (los) adquirente(s)',
                'errors' => $errorsAll
            ], 400);
        }
    }

    /**
     * Consulta si un adquirente existe y retorna la data correspondiente o error en caso de no existir.
     * 
     * Este método aplica para consultas desde el microservicio DI ubicado en otro servidor ETL
     * 
     * @param Request $request Parámetros de la petición
     * @return Response
     */
    public function obtenerAdquirenteDI(Request $request) {
        try {
            // Verifica el Hash recibido en la petición
            $hash = $this->calcularHash(
                $request->ofe_identificacion .
                $request->adq_identificacion .
                $request->adq_id_personalizado .
                $request->etl_server_principal .
                $request->usuario_autenticado
            );

            if($hash === $request->hash) {
                // Consulta el usuario que hace la petición
                $user = User::where('usu_email', $request->usuario_autenticado)
                    ->where('estado', 'ACTIVO')
                    ->first();
                
                $errors = [];
                if (is_null($user))
                    $errors[] = 'El usuario autenticado no existe o esta inactivo en el servidor ' . $request->etl_server_principal;

                if (!isset($request->ofe_identificacion))
                    $errors[] = 'No se ha proporcionado el OFE para consultar el Adquirente en el servidor ' . $request->etl_server_principal;

                if(!isset($request->adq_identificacion))
                    $errors[] = 'No se ha proporcionado el número de identificación del Adquirente en el servidor ' . $request->etl_server_principal;

                if(!isset($request->adq_id_personalizado) && !$this->isConsumidorFinal($request->adq_identificacion))
                    $errors[] = 'No se ha proporcionado el ID personalizado del Adquirente en el servidor ' . $request->etl_server_principal;

                if (empty($errors)) {
                    // Genera un token de autenticación dentro del sistema para poder acceder a los modelos tenant correspondientes
                    $token = auth()->login($user);

                    $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'ofe_identificacion', 'bdd_id_rg', 'ofe_identificador_unico_adquirente'])
                        ->where('ofe_identificacion', $request->ofe_identificacion)
                        ->first();
                    if (is_null($ofe)) {
                        return response()->json([
                            'message' => $this->getMensajeErrrorActualizarCrear('consultar', true),
                            'errors' => ["EL OFE {$request->ofe_identificacion} no existe en el servidor " . $request->etl_server_principal]
                        ], Response::HTTP_BAD_REQUEST);
                    }

                    $adq = ConfiguracionAdquirente::select([
                        'adq_id',
                        'pai_id', // traer valor codigo
                        'dep_id', // traer valor codigo
                        'mun_id', // traer valor codigo
                        'cpo_id', // traer valor codigo
                        'ref_id', // hacer explode por ;
                        'rfi_id', // traer valor codigo
                        'tdo_id', // traer valor codigo
                        'ipv_id',
                        'toj_id', // traer valor codigo
                        'adq_correo',
                        'adq_telefono',
                        'adq_direccion',
                        'adq_razon_social',
                        'adq_nombre_comercial',
                        'adq_primer_nombre',
                        'adq_otros_nombres',
                        'adq_primer_apellido',
                        'adq_segundo_apellido',
                        'adq_id_personalizado',
                        'adq_informacion_personalizada',
                        'adq_matricula_mercantil',
                        'adq_correos_notificacion',
                        'cpo_id_domicilio_fiscal', // traer valor codigo
                        'dep_id_domicilio_fiscal', // traer valor codigo
                        'mun_id_domicilio_fiscal', // traer valor codigo
                        'pai_id_domicilio_fiscal', // traer valor codigo
                        'adq_direccion_domicilio_fiscal',
                    ])
                        ->with([
                            'getParametroPais:pai_id,pai_codigo',
                            'getParametroDepartamento:dep_id,dep_codigo',
                            'getParametroMunicipio:mun_id,mun_codigo',
                            'getCodigoPostal:cpo_id,cpo_codigo',
                            'getRegimenFiscal:rfi_id,rfi_codigo',
                            'getProcedenciaVendedor:ipv_id,ipv_codigo',
                            'getParametroTipoDocumento:tdo_id,tdo_codigo',
                            'getParametroTipoOrganizacionJuridica:toj_id,toj_codigo',
                            'getParametroDomicilioFiscalPais:pai_id,pai_codigo',
                            'getParametroDomicilioFiscalDepartamento:dep_id,dep_codigo',
                            'getParametroDomicilioFiscalMunicipio:mun_id,mun_codigo',
                            'getCodigoPostalDomicilioFiscal:cpo_id,cpo_codigo',
                            'getTributos'
                        ])
                        ->where('ofe_id', $ofe->ofe_id)
                        ->where('adq_identificacion', $request->adq_identificacion);

                    // Verifica la configuración de la llave de adquirentes que tiene el OFE
                    $fraseComplementa = '';
                    if(
                        !empty($ofe->ofe_identificador_unico_adquirente) &&
                        in_array('adq_id_personalizado', $ofe->ofe_identificador_unico_adquirente)
                    ) {
                        if (!empty($request->adq_id_personalizado) && !$this->isConsumidorFinal($request->adq_identificacion)) {
                            $fraseComplementa = 'ID Personalizado [' . $request->adq_id_personalizado . ']';
                            $adq = $adq->where('adq_id_personalizado', $request->adq_id_personalizado);
                        } else {
                            $adq = $adq->whereNull('adq_id_personalizado');
                        }
                    }
                    $adq = $adq->first();

                    if (is_null($adq)) {
                        return response()->json([
                            'message' => $this->getMensajeErrrorActualizarCrear('consultar', true),
                            'errors' => ["EL Adquirente [{$request->adq_identificacion}] " . $fraseComplementa . "no existe en el servidor " . $request->etl_server_principal]
                        ], Response::HTTP_BAD_REQUEST);
                    } else {
                        $tributosAdq = [];
                        if(isset($adq->getTributos) && $adq->getTributos != null) {
                            foreach($adq->getTributos as $tributo) {
                                $infoTributo = ParametrosTributo::select(['tri_codigo'])
                                    ->where('tri_id', $tributo->tri_id)
                                    ->first();

                                $tributosAdq[] = $infoTributo->tri_codigo;
                            }
                        }
                        // Transforma la data de la colección resultante
                        $adquirente = [
                            'adq_correo'                     => $adq->adq_correo,
                            'pai_codigo'                     => (isset($adq->getParametroPais) && $adq->getParametroPais != null) ? $adq->getParametroPais->pai_codigo : '',
                            'dep_codigo'                     => (isset($adq->getParametroDepartamento) && $adq->getParametroDepartamento != null) ? $adq->getParametroDepartamento->dep_codigo : '',
                            'mun_codigo'                     => (isset($adq->getParametroMunicipio) && $adq->getParametroMunicipio != null) ? $adq->getParametroMunicipio->mun_codigo : '',
                            'cpo_codigo'                     => (isset($adq->getCodigoPostal) && $adq->getCodigoPostal != null) ? $adq->getCodigoPostal->cpo_codigo : '',
                            'rfi_codigo'                     => (isset($adq->getRegimenFiscal) && $adq->getRegimenFiscal != null) ? $adq->getRegimenFiscal->rfi_codigo : '',
                            'ipv_codigo'                     => (isset($adq->getProcedenciaVendedor) && $adq->getProcedenciaVendedor != null) ? $adq->getProcedenciaVendedor->ipv_codigo : '',
                            'tdo_codigo'                     => (isset($adq->getParametroTipoDocumento) && $adq->getParametroTipoDocumento != null) ? $adq->getParametroTipoDocumento->tdo_codigo : '',
                            'toj_codigo'                     => (isset($adq->getParametroTipoOrganizacionJuridica) && $adq->getParametroTipoOrganizacionJuridica != null) ? $adq->getParametroTipoOrganizacionJuridica->toj_codigo : '',
                            'ofe_identificacion'             => $request->ofe_identificacion,
                            'adq_identificacion'             => $request->adq_identificacion,
                            'adq_id_personalizado'           => !empty($adq->adq_id_personalizado) ? $adq->adq_id_personalizado : '',
                            'adq_informacion_personalizada'  => !empty($adq->adq_informacion_personalizada) ? $adq->adq_informacion_personalizada : '',                            
                            'adq_telefono'                   => $adq->adq_telefono,
                            'adq_direccion'                  => $adq->adq_direccion,
                            'adq_razon_social'               => $adq->adq_razon_social,
                            'adq_nombre_comercial'           => $adq->adq_nombre_comercial,
                            'adq_primer_nombre'              => $adq->adq_primer_nombre,
                            'adq_otros_nombres'              => $adq->adq_otros_nombres,
                            'adq_primer_apellido'            => $adq->adq_primer_apellido,
                            'adq_seguncdo_apellido'          => $adq->adq_seguncdo_apellido,
                            'adq_matricula_mercantil'        => $adq->adq_matricula_mercantil,
                            'pai_codigo_domicilio_fiscal'    => (isset($adq->getParametroDomicilioFiscalPais) && $adq->getParametroDomicilioFiscalPais != null) ? $adq->getParametroDomicilioFiscalPais->pai_codigo : '',
                            'dep_codigo_domicilio_fiscal'    => (isset($adq->getParametroDomicilioFiscalDepartamento) && $adq->getParametroDomicilioFiscalDepartamento != null) ? $adq->getParametroDomicilioFiscalDepartamento->dep_codigo : '',
                            'mun_codigo_domicilio_fiscal'    => (isset($adq->getParametroDomicilioFiscalMunicipio) && $adq->getParametroDomicilioFiscalMunicipio != null) ? $adq->getParametroDomicilioFiscalMunicipio->mun_codigo : '',
                            'cpo_codigo_domicilio_fiscal'    => (isset($adq->getCodigoPostalDomicilioFiscal) && $adq->getCodigoPostalDomicilioFiscal != null) ? $adq->getCodigoPostalDomicilioFiscal->cpo_codigo : '',
                            'adq_direccion_domicilio_fiscal' => $adq->adq_direccion_domicilio_fiscal,
                            'adq_correos_notificacion'       => $adq->adq_correos_notificacion,
                            'ref_codigo'                     => ($adq->ref_id != '') ? explode(';', $adq->ref_id) : [],
                            'responsable_tributos'           => $tributosAdq
                        ];

                        return response()->json([
                            'data' => $adquirente
                        ], Response::HTTP_OK);
                    }
                }

                return response()->json([
                    'message' => $this->getMensajeErrrorActualizarCrear('consultar', true) . ' en el servidor ' . $request->etl_server_principal,
                    'errors' => $errors
                ], Response::HTTP_BAD_REQUEST);
            } else {
                return response()->json([
                    'message' => $this->getMensajeErrrorActualizarCrear('consultar', true) . ' en el servidor ' . $request->etl_server_principal,
                    'errors' => ['Hash de la consulta no corresponde']
                ], Response::HTTP_BAD_REQUEST);
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => ($this->getMensajeErrrorActualizarCrear('consultar', true)) . ' en el servidor ' . $request->etl_server_principal,
                'errors' => [$e->getMessage()]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Calcula un HASH seguro para una cadena basado en algoritmo de encripción sha256.
     *
     * @param string $cadena Cadena sobre la cual se efectúa el cálculo
     * @return string Hash calculado
     */
    public function calcularHash(string $cadena) {
        return hash_hmac(
            'sha256',
            $cadena,
            env('HASH_KEY_PETICIONES')
        );
    }

    /**
     * Crea un adquirente consumidor final para el ofe indicado.
     * 
     * @param string $ofe_id ID del OFE
     * @param array $adquirente Información del adquirente consumidor final a crear
     * @param User $user Objeto del usuario autenticado
     * @return void
     */
    public function crearAdquirenteConsumidorFinal(string $ofe_id, array $adquirente, User $user) {
        $tipoDocumento = ParametrosTipoDocumento::select(['tdo_id'])
            ->where('tdo_codigo', $adquirente['tdo_codigo'])
            ->first();

        $tipoOrganizacionJuridica = ParametrosTipoOrganizacionJuridica::select(['toj_id'])
            ->where('toj_codigo', $adquirente['toj_codigo'])
            ->first();

        $regimenFiscal = ParametrosRegimenFiscal::select(['rfi_id'])
            ->where('rfi_codigo', $adquirente['rfi_codigo'])
            ->first();

        $nuevoAdquirente = ConfiguracionAdquirente::create([
            'ofe_id'                   => $ofe_id,
            'adq_identificacion'       => $adquirente['adq_identificacion'],
            'adq_primer_apellido'      => $adquirente['adq_primer_apellido'],
            'adq_primer_nombre'        => $adquirente['adq_primer_nombre'],
            'adq_tipo_adquirente'      => 'SI',
            'adq_correos_notificacion' => (array_key_exists('adq_correos_notificacion', $adquirente) && !empty($adquirente['adq_correos_notificacion'])) ? $adquirente['adq_correos_notificacion'] : null,
            'tdo_id'                   => $tipoDocumento->tdo_id,
            'toj_id'                   => $tipoOrganizacionJuridica->toj_id,
            'rfi_id'                   => $regimenFiscal->rfi_id,
            'ref_id'                   => $adquirente['ref_codigo'],
            'usuario_creacion'         => $user->usu_id,
            'estado'                   => 'ACTIVO'
        ]);

        // Responsable Tributos
        foreach($adquirente['responsable_tributos'] as $responsableTributo) {
            $tributo = ParametrosTributo::select(['tri_id'])
                ->where('tri_codigo', $responsableTributo)
                ->where('estado', 'ACTIVO')
                ->first();

            $tributoAdquirente = TributosOfesAdquirentes::select(['toa_id'])
                ->where('adq_id', $nuevoAdquirente->adq_id)
                ->where('tri_id', $tributo->tri_id)
                ->where('estado', 'ACTIVO')
                ->first();

            if(!$tributoAdquirente) {
                TributosOfesAdquirentes::create([
                    'adq_id'           => $nuevoAdquirente->adq_id,
                    'tri_id'           => $tributo->tri_id,
                    'usuario_creacion' => $user->usu_id,
                    'estado'           => 'ACTIVO'
                ]);
            }
        }
    }

    /**
     * Actualiza un adquirente consumidor final para el ofe indicado.
     * 
     * El único campo que se puede actualizar es adq_correos_notificacion
     * 
     * @param string $ofe_id ID del OFE
     * @param array $adquirente Información del adquirente consumidor final a crear
     * @return void
     */
    public function actualizarAdquirenteConsumidorFinal(string $ofe_id, array $adquirente) {
        $existe = ConfiguracionAdquirente::select(['adq_id', 'adq_correos_notificacion'])
            ->where('ofe_id', $ofe_id)
            ->where('adq_identificacion', $adquirente['adq_identificacion'])
            ->where('estado', 'ACTIVO')
            ->first();

        if($existe) {
            $existe->update([
                'adq_correos_notificacion' => (array_key_exists('adq_correos_notificacion', $adquirente) && !empty($adquirente['adq_correos_notificacion'])) ? $adquirente['adq_correos_notificacion'] : $existe->adq_correos_notificacion
            ]);
        }
    }

    /**
     * Columnas personalizadas de adquirentes definidas por cada OFE en la base de datos.
     * 
     * @return array $columnasPersonalizadas Array de columnas personalizadas
     */
    private function ofeColumnasPersonalizadasAdquirentes() {
        $user = auth()->user();
        $columnasPersonalizadas     = [];
        $ofesColumnasPersonalizadas = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_informacion_personalizada_adquirente'])
            ->validarAsociacionBaseDatos()
            ->get()
            ->map(function($columna) use (&$columnasPersonalizadas) {
                if(!empty($columna->ofe_informacion_personalizada_adquirente))
                    $columnasPersonalizadas = array_merge($columna->ofe_informacion_personalizada_adquirente, $columnasPersonalizadas);
            });

        return array_values(array_unique($columnasPersonalizadas));
    }

    /**
     * Verifica si la identificacion del adquirente corresponde a un consumidor final.
     *
     * @param string $adq_identificacion
     * @return bool Indica si el nit enviado es un nit de consumidor final
     */
    private function isConsumidorFinal(string $adq_identificacion) {
        $adquirenteConsumidorFinal = json_decode(config('variables_sistema.ADQUIRENTE_CONSUMIDOR_FINAL'));
        $checkConsumidorFinal = false;
        foreach($adquirenteConsumidorFinal as $consumidorFinal) {
            if ($consumidorFinal->adq_identificacion == $adq_identificacion && !$checkConsumidorFinal) {
                $checkConsumidorFinal = true;
            }
        }
        return $checkConsumidorFinal;
    }

    /**
     * Crea el agendamiento en background en el sistema para poder procesar y generar el archivo de Excel.
     *
     * @param  Request $request Parámetros de la petición
     * @return JsonResponse
     */
    function agendarReporte(Request $request): JsonResponse {
        $user = auth()->user();

        if($request->filled('tipo')) {
            $agendamiento = AdoAgendamiento::create([
                'usu_id'                  => $user->usu_id,
                'age_proceso'             => 'REPORTE',
                'bdd_id'                  => $user->getBaseDatos->bdd_id,
                'age_cantidad_documentos' => 1,
                'age_prioridad'           => null,
                'usuario_creacion'        => $user->usu_id,
                'estado'                  => 'ACTIVO',
            ]);

            EtlProcesamientoJson::create([
                'pjj_tipo'         => 'REPADQ',
                'pjj_json'         => json_encode(json_decode($request->json, TRUE)),
                'pjj_procesado'    => 'NO',
                'age_id'           => $agendamiento->age_id,
                'usuario_creacion' => $user->usu_id,
                'estado'           => 'ACTIVO'
            ]);

            return response()->json([
                'message' => 'Reporte agendado para generarse en background'
            ], 201);
        } else {
            return response()->json([
                'error'   => 'Error al procesar la petición',
                'message' => 'Faltan parámetros o están vacios'
            ], 400);
        }
    }

    /**
     * Procesa el reporte para los adquirentes según los filtros.
     *
     * @param  Request $request Parámetros de la petición
     * @return array
     */
    public function procesarAgendamientoReporte(Request $request): array {
        try {
            $request->merge([
                'pjj_tipo' => 'REPADQ'
            ]);
            $this->request = $request;
            $arrExcel = $this->getListaAdquirentes($request);

            // Renombra el archivo y lo mueve al disco de descargas ya que se crea sobre el disco local
            File::move($arrExcel['ruta'], storage_path('etl/descargas/' . $arrExcel['nombre']));
            File::delete($arrExcel['ruta']);

            return [
                'errors'  => [],
                'archivo' => $arrExcel['nombre']
            ];
        } catch (\Exception $e) {
            return [
                'errors' => [ $e->getMessage() ],
                'traza'  => [ $e->getFile() . ' - Línea ' . $e->getLine() . ': ' . $e->getMessage() . "\n\nTrace:\n" . $e->getTraceAsString() ]
            ];
        }
    }
}
