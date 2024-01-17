<?php
namespace App\Console\Commands\DHLExpress;

use GuzzleHttp\Client;
use App\Http\Models\User;
use Illuminate\Console\Command;
use openEtl\Tenant\Traits\TenantTrait;
use GuzzleHttp\Exception\ClientException;
use openEtl\Tenant\Traits\TenantDatabase;
use App\Http\Modulos\Sistema\AuthBaseDatos\AuthBaseDatos;
use App\Http\Modulos\Recepcion\Documentos\RepEstadosDocumentosDaop\RepEstadoDocumentoDaop;
use App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaop;

class DhlExpressIntegracionOpencomexCausacionCxpCommand extends Command {

    /**
     * Nombre del comando en la consola.
     *
     * @var string
     */
    protected $signature = 'dhlexpress-integracion-opencomex-causacion-cxp';

    /**
     * Descripción del comando en la consola.
     *
     * @var string
     */
    protected $description = 'DHL Express - Comando de integración para causación CXP';

    /**
     * Identificacion del OFE.
     *
     * @var string
     */
    protected $ofeIdentificacion = '830076778';

    /**
     * Nombre de la base de datos en openComex.
     *
     * @var string
     */
    protected $baseDatosOpencomexCxp = 'TEDHLEXPRE';

    /**
     * Nombre de la base de datos en openETL.
     *
     * @var string
     */
    protected $baseDatosOpenETL = 'etl_dhlexcca';

    /**
     * Crea una instancia del comando.
     *
     * @return void
     */
    public function __construct() {
        parent::__construct();
    }

    /**
     * Ejecuta el comando en la consola.
     * 
     * @return mixed
     */
    public function handle() {
        $dataBase = AuthBaseDatos::where('bdd_nombre', $this->baseDatosOpenETL)->first();

        if(!$dataBase) {
            $this->error("No existe la base de datos [" . $this->baseDatosOpenETL . "]");
            exit;
        }

        // Ubica un usuario relacionado con la BD para poder autenticarlo y acceder a los modelos Tenant
        $user = User::where('bdd_id', $dataBase->bdd_id)
            ->where('estado', 'ACTIVO')
            ->where('usu_type', 'ADMINISTRADOR')
            ->first();

        if(!$user) {
            $this->error("No se encontró un usuario asociado con la base de datos [" . $this->baseDatosOpenETL . "]");
            exit;
        }

        auth()->login($user);

        TenantDatabase::setTenantConnection(
            'conexion01',
            $dataBase->bdd_host,
            $dataBase->bdd_nombre,
            $dataBase->bdd_usuario,
            $dataBase->bdd_password
        );

        TenantTrait::GetVariablesSistemaTenant();
        $variablesOpencomex = [
            'hashKey'             => config('variables_sistema_tenant.OPENCOMEX_CBO_HASH_KEY'),
            'endpointOpencomex'   => config('variables_sistema_tenant.OPENCOMEX_CBO_API'),
            'proIdentificaciones' => array_map('trim', explode(',', config('variables_sistema_tenant.OPENCOMEX_CBO_NITS_INTEGRACION')))
        ];

        if(empty($variablesOpencomex['hashKey']) ||  empty($variablesOpencomex['endpointOpencomex']) ||  empty($variablesOpencomex['proIdentificaciones']))
            $this->error('Verifique que todas las variables del sistema Tenant OPENCOMEX_CBO tengan asignado un valor');

        RepCabeceraDocumentoDaop::select(['cdo_id', 'ofe_id', 'pro_id', 'cdo_fecha', 'rfa_prefijo', 'cdo_consecutivo', 'cdo_observacion', 'cdo_valor_sin_impuestos', 'cdo_impuestos'])
            ->with([
                'getConfiguracionObligadoFacturarElectronicamente' => function($query) {
                    $query->select(['ofe_id', 'ofe_identificacion'])
                        ->where('ofe_identificacion', $this->ofeIdentificacion);
                },
                'getConfiguracionProveedor' => function($query) use ($variablesOpencomex) {
                    $query->select(['pro_id', 'pro_identificacion'])
                        ->whereIn('pro_identificacion', $variablesOpencomex['proIdentificaciones']);
                }
            ])
            ->whereHas('getConfiguracionObligadoFacturarElectronicamente', function($query) {
                $query->select(['ofe_id', 'ofe_identificacion'])
                    ->where('ofe_identificacion', $this->ofeIdentificacion);
            })
            ->whereHas('getConfiguracionProveedor', function($query) use ($variablesOpencomex) {
                $query->select(['pro_id', 'pro_identificacion'])
                    ->whereIn('pro_identificacion', $variablesOpencomex['proIdentificaciones']);
            })
            ->doesntHave('getOpencomexCxpExitoso')
            ->get()
            ->map(function($facturaCxp) use ($variablesOpencomex) {
                if($this->cxpFallidos($facturaCxp->cdo_id) < 3)
                    $this->openComexIntegracionCausacionCxp($facturaCxp, $variablesOpencomex);
            });

        $this->info("Proceso finalizado para la base de datos [" . $this->baseDatosOpenETL . "]");
    }

    /**
     * Extrae información puntual del campo cdo_observacion de la cabecera de documentos en recepción
     *
     * @param string $cdoObservacion Contenido del campo cdo_observacion
     * @param string $buscar Valor a buscar
     * @return void
     */
    private function extraerInfoArrayObservacion(string $cdoObservacion, string $buscar) {
        if(!empty($cdoObservacion)) {
            $encontrado = array_values(array_filter(json_decode($cdoObservacion, true), function($valor) use ($buscar) {
                return strstr($valor, $buscar);
            }));

            if(empty($encontrado))
                return '';
            else
                return trim(str_replace($buscar . ':' , '', $encontrado[0]));
        } else
            return '';
    }

    /**
     * Arma la estructura de información que debe ser transmitida a openComex
     *
     * @param RepCabeceraDocumentoDaop $facturaCxp Factura de recepción a transmitir
     * @param array $vriablesOpencomex Variables para conexión/transmisión a openComex
     * @return void|string $mensajeResultado Cadena con la información de respuesta/errores de openComex
     */
    public function openComexIntegracionCausacionCxp(RepCabeceraDocumentoDaop $facturaCxp, array $variablesOpencomex) {
        $arrFacturaCxp = [
            'cdo_fecha'               => $facturaCxp->cdo_fecha, 
            'cdo_vencimiento'         => $facturaCxp->cdo_vencimiento, 
            'rfa_prefijo'             => $facturaCxp->rfa_prefijo, 
            'cdo_consecutivo'         => $facturaCxp->cdo_consecutivo, 
            'nit_proveedor'           => $facturaCxp->getConfiguracionProveedor->pro_identificacion,
            'nit_importador'          => $this->extraerInfoArrayObservacion($facturaCxp->cdo_observacion, 'nit_importador'), 
            'ofe_identificacion'      => $facturaCxp->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion, 
            'observaciones'           => $this->extraerInfoArrayObservacion($facturaCxp->cdo_observacion, 'observaciones'), 
            'cdo_valor_sin_impuestos' => $facturaCxp->cdo_valor_sin_impuestos, 
            'cdo_impuestos'           => $facturaCxp->cdo_impuestos, 
            'cdo_total'               => $facturaCxp->cdo_total, 
            'numero_guia'             => $this->extraerInfoArrayObservacion($facturaCxp->cdo_observacion, 'numero_guia')
        ];
        
        $arrFacturaCxp['hash'] = hash_hmac(
            'sha512',
            json_encode($arrFacturaCxp),
            $variablesOpencomex['hashKey']
        );

        try {
            $cliente = new Client();
            $transmision = $cliente->request(
                'POST',
                $variablesOpencomex['endpointOpencomex'],
                [
                    'verify' => false,
                    'json'   => $arrFacturaCxp,
                    'headers' => [
                        'Content-Type'    => 'application/json'
                    ]
                ]
            );

            $status    = $transmision->getStatusCode();
            $respuesta = json_decode((string)$transmision->getBody()->getContents(), true);

            if (($status === 200 || $status === 201) && array_key_exists('id_openComex', $respuesta) && !empty($respuesta['id_openComex'])) {
                $mensajeResultado = 'Documento Guardado con Éxito en openComex [' . $respuesta['id_openComex'] . ']';
                $this->cearEstadoOpencomexCxp($facturaCxp, 'OPENCOMEXCXP', 'EXITOSO', $mensajeResultado, 'FINALIZADO');
            } else {
                if($this->cxpFallidos($facturaCxp->cdo_id) < 3) {
                    if(array_key_exists('errors', $respuesta) && !empty($respuesta['errors'])) {
                        $mensajeResultado = 'Error al guardar el comprobante en openComex: ' . implode(' // ', $respuesta['errors']);
                        $this->cearEstadoOpencomexCxp($facturaCxp, 'OPENCOMEXCXP', 'FALLIDO', $mensajeResultado, 'FINALIZADO', ['agendamiento' => 'OPENCOMEXCXPR']);
                    } else {
                        $mensajeResultado = 'Error al guardar el comprobante en openComex';
                        $this->cearEstadoOpencomexCxp($facturaCxp, 'OPENCOMEXCXP', 'FALLIDO', $mensajeResultado, 'FINALIZADO', ['errors' => [$respuesta], 'agendamiento' => 'OPENCOMEXCXPR']);
                    }
                }
            }
        } catch (ClientException $e) {
            if($this->cxpFallidos($facturaCxp->cdo_id) < 3 || (array_key_exists('origen', $variablesOpencomex) && $variablesOpencomex['origen'] == 'controlador')) {
                $status    = $e->getCode();
                $respuesta = $e->getResponse();
                $respuesta = $respuesta->getBody()->getContents();

                if(empty($respuesta))
                    $respuesta = $e->getMessage();

                $arrRespuesta = json_decode($respuesta, true);
                if(json_last_error() == JSON_ERROR_NONE && array_key_exists('errors', $arrRespuesta) && !empty($arrRespuesta['errors'])) {
                    $mensajeResultado = 'Error al guardar el comprobante en openComex: ' . implode(' // ', $arrRespuesta['errors']);
                    $this->cearEstadoOpencomexCxp($facturaCxp, 'OPENCOMEXCXP', 'FALLIDO', $mensajeResultado, 'FINALIZADO', ['agendamiento' => 'OPENCOMEXCXPR']);
                } else {
                    $mensajeResultado = 'Error al guardar el comprobante en openComex';
                    $this->cearEstadoOpencomexCxp($facturaCxp, 'OPENCOMEXCXP', 'FALLIDO', $mensajeResultado, 'FINALIZADO', ['errors' => [$respuesta], 'agendamiento' => 'OPENCOMEXCXPR']);
                }
            }
        }

        if(array_key_exists('origen', $variablesOpencomex) && $variablesOpencomex['origen'] == 'controlador' && isset($mensajeResultado) && !empty($mensajeResultado))
            return $mensajeResultado;
    }

    /**
     * Crea un estado para la factura.
     *
     * @param RepCabeceraDocumentoDaop $facturaCxp factura para la cual se creará el estado
     * @param string $estado Estado a crear
     * @param string $resultado Resultado del procesamiento del estado
     * @param string $mensajeResultado Mensaje del procesamiento
     * @param string $ejecucion Ejecución del estado
     * @return void
     */
    private function cearEstadoOpencomexCxp(RepCabeceraDocumentoDaop $facturaCxp, string $estado, string $resultado, string $mensajeResultado, string $ejecucion, array $informacionAdicional = null) {
        RepEstadoDocumentoDaop::create([
            'cdo_id'                    => $facturaCxp->cdo_id,
            'est_estado'                => $estado,
            'est_resultado'             => $resultado,
            'est_mensaje_resultado'     => $mensajeResultado,
            'est_informacion_adicional' => !empty($informacionAdicional) ? json_encode($informacionAdicional) : null,
            'est_ejecucion'             => $ejecucion,
            'usuario_creacion'          => auth()->user()->usu_id,
            'estado'                    => 'ACTIVO'
        ]);
    }

    /**
     * Retorna la cantidad total de estados fallidos OPENCOMEXCXP para un documento.
     *
     * @param int $cdo_id ID del documento
     * @return int Cantidad total de estados fallidos OPENCOMEXCXP
     */
    private function cxpFallidos($cdo_id) {
        return RepEstadoDocumentoDaop::select(['est_id'])
            ->where('cdo_id', $cdo_id)
            ->where('est_estado', 'OPENCOMEXCXP')
            ->where('est_resultado', 'FALLIDO')
            ->count();
    }
}
