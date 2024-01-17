<?php
namespace App\Console\Commands\Cadisoft;

use GuzzleHttp\Client;
use App\Http\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Console\Command;
use App\Http\Models\AuthBaseDatos;
use Illuminate\Support\Facades\DB;
use App\Http\Models\AdoAgendamiento;
use openEtl\Tenant\Traits\TenantDatabase;
use App\Http\Modulos\Sistema\EtlProcesamientoJson\EtlProcesamientoJson;
use App\Http\Modulos\Documentos\EtlFatDocumentosDaop\EtlFatDocumentoDaop;
use App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaop;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

/**
 * Procesamiento de documentos para OFEs de Cadisoft.
 * 
 * Class CadisoftProcesarDocumentosCommand
 * @package App\Console\Commands\Cadisoft
 */
class CadisoftProcesarDocumentosCommand extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cadisoft-procesar-documentos';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Procesamiento en emisión de documentos de OFEs con integración de Cadisoft';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct() {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        set_time_limit(0);
        ini_set('memory_limit', '1024M');
        
        // Obtiene los usuarios de Integración de Cadisoft
        $usuariosCadisoft = User::where('usu_email', 'like', '%@cadisoft.co')
            ->where('estado', 'ACTIVO')
            ->with([
                'getBaseDatos:bdd_id,bdd_nombre,bdd_host,bdd_usuario,bdd_password'
            ])
            ->get();

        foreach($usuariosCadisoft as $user) {
            // Generación del token conforme al usuario para poder acceder a los modelos tenant
            $token = auth()->login($user);

            // Se consultan los datos de la base de datos
            $baseDatos = AuthBaseDatos::where('bdd_id', $user->bdd_id)
                ->where('estado', 'ACTIVO')
                ->first();

            if ($baseDatos) {
                // Se establece una conexión dinámica a la BD
                TenantDatabase::setTenantConnection(
                    'conexion01',
                    $baseDatos->bdd_host,
                    $baseDatos->bdd_nombre,
                    $baseDatos->bdd_usuario,
                    $baseDatos->bdd_password
                );

                $ofes = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'ofe_identificacion', 'ofe_cadisoft_configuracion', 'ofe_cadisoft_ultima_ejecucion', 'ofe_prioridad_agendamiento'])
                    ->where('ofe_cadisoft_activo', 'SI')
                    ->validarAsociacionBaseDatos()
                    ->where('estado', 'ACTIVO')
                    ->get();

                foreach($ofes as $ofe) {
                    try {
                        $documentosProcesar = $this->consumoWebService($ofe);
                        if(!empty($documentosProcesar) && is_array($documentosProcesar)) {
                            // Array de documentos procesados que pasarán por EDI
                            $payload = [
                                'documentos' => [
                                    'FC' => [],
                                    'ND' => [],
                                    'NC' => []
                                ],
                            ];

                            foreach ($documentosProcesar as $documento) {
                                if($documento->tipoDocumento == 'FE')
                                    $documento->tipoDocumento = 'FC';

                                $payload['documentos'][$documento->tipoDocumento][] = $this->mapeoColumnas($documento);
                            }

                            // Se procesó el bloque de documentos, ahora se crea el agendamieto para EDI y se registra el objeto Json
                            if(count($payload['documentos']['FC']) > 0 || count($payload['documentos']['NC']) > 0 || count($payload['documentos']['ND']) > 0) {
                                $totalDocumentos = count($payload['documentos']['FC']) + count($payload['documentos']['NC']) + count($payload['documentos']['ND']);
                                $agendamientoEdi = AdoAgendamiento::create([
                                    'usu_id'                  => $user->usu_id,
                                    'bdd_id'                  => $user->getBaseDatos->bdd_id,
                                    'age_proceso'             => 'EDI',
                                    'age_cantidad_documentos' => $totalDocumentos,
                                    'age_prioridad'           => !empty($ofe->ofe_prioridad_agendamiento) ? $ofe->ofe_prioridad_agendamiento : null,
                                    'usuario_creacion'        => $user->usu_id,
                                    'estado'                  => 'ACTIVO'
                                ]);
                                
                                $this->registraProcesamientoJson([
                                    'pjj_tipo'                => 'CADISOFT',
                                    'pjj_json'                => json_encode($payload),
                                    'pjj_procesado'           => 'NO',
                                    'pjj_errores'             => null,
                                    'age_id'                  => $agendamientoEdi->age_id,
                                    'age_estado_proceso_json' => null,
                                    'usuario_creacion'        => $user->usu_id,
                                    'estado'                  => 'ACTIVO'
                                ]);
                            }
                        }
                    } catch(\Exception $e) {
                        $arrExcepciones   = [];
                        $arrExcepciones[] = [
                            'Marcado'             => '',
                            'documento'           => '',
                            'consecutivo'         => '',
                            'prefijo'             => '',
                            'errors'              => ['Se produjo un error al consultar las facturas en el servidor de Cadisoft. OFE [' . $ofe->ofe_identificacion . '] - Código HTTP [' . $e->getCode() . '] - Razón [' . $e->getMessage() . ']'],
                            'fecha_procesamiento' => date('Y-m-d'),
                            'hora_procesamiento'  => date('H:i:s'),
                            'traza'               => $e->getFile() . ' - Línea ' . $e->getLine() . ': ' . $e->getMessage() . "\n\nTrace:\n" . $e->getTraceAsString()
                        ];

                        $this->registraProcesamientoJson([
                            'pjj_tipo'                => 'CADISOFT',
                            'pjj_json'                => json_encode([
                                'ofe_identificacion' => $ofe->ofe_identificacion,
                                'api'                => '',
                                'documentos'         => "[]"
                            ]),
                            'pjj_procesado'           => 'SI',
                            'pjj_errores'             => json_encode($arrExcepciones),
                            'age_id'                  => 0,
                            'age_estado_proceso_json' => 'FINALIZADO',
                            'usuario_creacion'        => $user->usu_id,
                            'estado'                  => 'ACTIVO'
                        ]);
                    }
                }

                // Finaliza la conexión a la actual base de datos para poder pasar a la siguiente
                DB::purge('conexion01');
            }
        }
    }

    /**
     * Cliente de Guzzle que permite el consulo de los endpotins de Cadisoft
     * 
     * @param string $endpoint Endpoint a consumir
     * @param string $usuario Usuario de autenticación del endpoint
     * @param string $password Clave del usuario de autenticación del endpoint
     * @return GuzzleHttp\Client 
     */
    private function consultaServerCadisoft($endpoint, $usuario, $password, $tipoDocumento) {
        $client = new Client();
        return $client
            ->request(
                'POST',
                $endpoint,
                [
                    'body' => ($tipoDocumento['tipo'] == "FC") ? '' : json_encode($tipoDocumento),
                    'headers' => [
                        'Content-Type'  => 'application/json',
                        'Accept'        => 'application/json',
                        'Authorization' => 'Basic ' . base64_encode($usuario . ':' . sha1($password))
                    ]
                ]
            );
    }

    /**
     * Cliente de Guzzle que permite marcar como procesado un documento en cadisoft
     * 
     * @param string $endpoint Endpoint a consumir
     * @param string $usuario Usuario de autenticación del endpoint
     * @param string $password Clave del usuario de autenticación del endpoint
     * @return object $documentoCadisoft Array con el documento a marcar
     */
    private function marcarDocumentoServerCadisoft($endpoint, $usuario, $password, $documentoCadisoft) {
        $client = new Client();
        return $client
            ->request(
                'POST',
                $endpoint,
                [
                    'body' => json_encode($documentoCadisoft),
                    'headers' => [
                        'Content-Type'  => 'application/json',
                        'Authorization' => 'Basic ' . base64_encode($usuario . ':' . sha1($password))
                    ]
                ]
            );
    }

    /**
     * Consumo del webservice de Cadisoft para obtener los documentos a procesar.
     *
     * @param ConfiguracionObligadoFacturarElectronicamente $ofe Colección del OFE sobre el cual se está ejecutando el comando
     * @return array $documentosCadisoft Array de objetos Json con los documentos a procesar
     */
    private function consumoWebService(ConfiguracionObligadoFacturarElectronicamente $ofe) {
        $documentosCadisoft = [];
        $cadisoftConfig     = json_decode($ofe->ofe_cadisoft_configuracion);

        // dump("OFE: ".$ofe->ofe_identificacion);
        // dump($cadisoftConfig);
        
        // Verificación de periodicidad
        $procesar = true;
        if(!empty($ofe->ofe_cadisoft_ultima_ejecucion)) {
            $fechaHoraActual = Carbon::now()->toString();
            $ultimaEjecucion = Carbon::parse($ofe->ofe_cadisoft_ultima_ejecucion);
            $diferencia      = $ultimaEjecucion->diffInSeconds($fechaHoraActual);

            if($diferencia < $cadisoftConfig->frecuencia_ejecucion)
                $procesar = false;
        }

        if(!$procesar)
            return $documentosCadisoft;
        else {
            $ofe->ofe_cadisoft_ultima_ejecucion = date('Y-m-d H:i:s');
            $ofe->save();
        }

        try {
            // Facturas
            $tipoDocumento = [
                'tipo' => "FC"
            ];

            $consultaDocumentos = $this->consultaServerCadisoft($cadisoftConfig->api_facturas, $cadisoftConfig->usuario_facturas, $cadisoftConfig->password_facturas, $tipoDocumento);
            $status             = $consultaDocumentos->getStatusCode();
            $razon              = $consultaDocumentos->getReasonPhrase();

            // dump("Response API");
            // dump($status);
            // dump(@json_decode((string)$consultaDocumentos->getBody()->getContents()));
            // dump("---------------");

            if($status != '200' && $status != '201') {
                $this->registraProcesamientoJson([
                    'pjj_tipo'                => 'CADISOFT',
                    'pjj_json'                => json_encode([
                        'ofe_identificacion' => $ofe->ofe_identificacion,
                        'api'                => $cadisoftConfig->api_facturas,
                        'documentos'         => "[]"
                    ]),
                    'pjj_procesado'           => 'SI',
                    'pjj_errores'             => json_encode([[
                        'Marcado'             => '',
                        'documento'           => '',
                        'consecutivo'         => '',
                        'prefijo'             => '',
                        'errors'              => ['Se produjo un error al consultar las facturas en el servidor de Cadisoft. OFE [' . $ofe->ofe_identificacion . '] - Endpoint [' . $cadisoftConfig->api_facturas . '] - Código HTTP [' . $status . '] - Razón [' . $razon . ']'],
                        'fecha_procesamiento' => date('Y-m-d'),
                        'hora_procesamiento'  => date('H:i:s'),
                        'traza'               => $razon
                    ]]),
                    'age_id'                  => 0,
                    'age_estado_proceso_json' => 'FINALIZADO',
                    'usuario_creacion'        => auth()->user()->usu_id,
                    'estado'                  => 'ACTIVO'
                ]);
            } else {
                $respuesta = json_decode((string)$consultaDocumentos->getBody()->getContents());
                if(!empty($respuesta)) {
                    $documentosCadisoft = array_merge($documentosCadisoft, $respuesta);

                    //Marcando las facturas en cadisoft
                    $documentosMarcados = [];
                    foreach ($respuesta as $key => $documento) {
                        //Datos Documento
                        $documentoCadisoft = [
                            'prefijo' => (string)$documento->prefijoDocumento, 
                            'desde'   => (string)$documento->numeroDocumento,
                            'hasta'   => (string)$documento->numeroDocumento,
                            'estado'  => "1"
                        ];

                        $endpointMarcarDocumento = str_replace("PostFacturas", "PostSetEstadoFactura", $cadisoftConfig->api_facturas);

                        $marcarDocumentos = $this->marcarDocumentoServerCadisoft($endpointMarcarDocumento, $cadisoftConfig->usuario_facturas, $cadisoftConfig->password_facturas, $documentoCadisoft);
                        $marcarStatus     = $marcarDocumentos->getStatusCode();
                        $marcarRazon      = $marcarDocumentos->getReasonPhrase();

                        // dump("Response API");
                        // var_dump($marcarDocumentos);
                        // dump(json_decode((string)$marcarDocumentos->getBody()));
                        // dump($marcarStatus);
                        // dump($marcarRazon);
                        // dump("---------------");

                        if ($marcarStatus != '200' && $marcarStatus != '201') {
                            $documentosMarcados[] = [
                                'Marcado'             => '',
                                'documento'           => $documentoCadisoft['prefijo'].$documentoCadisoft['desde'],
                                'consecutivo'         => $documentoCadisoft['desde'],
                                'prefijo'             => $documentoCadisoft['prefijo'],
                                'errors'              => ['Se produjo un error al marcar las facturas en el servidor de Cadisoft. OFE [' . $ofe->ofe_identificacion . '] - Endpoint [' . $endpointMarcarDocumento . '] - Código HTTP [' . $marcarStatus . '] - Razón [' . $marcarRazon . ']'],
                                'fecha_procesamiento' => date('Y-m-d'),
                                'hora_procesamiento'  => date('H:i:s'),
                                'traza'               => ''
                            ];
                        } else {
                            $documentosMarcados[] = [
                                'Marcado'             => $documentoCadisoft['prefijo'].$documentoCadisoft['desde'],
                                'documento'           => $documentoCadisoft['prefijo'].$documentoCadisoft['desde'],
                                'consecutivo'         => $documentoCadisoft['desde'],
                                'prefijo'             => $documentoCadisoft['prefijo'],
                                'errors'              => ['Documento procesado y marcado correctamente.'],
                                'fecha_procesamiento' => date('Y-m-d'),
                                'hora_procesamiento'  => date('H:i:s'),
                                'traza'               => ''
                            ];
                        }
                    }

                    $this->registraProcesamientoJson([
                        'pjj_tipo'                => 'CADISOFT',
                        'pjj_json'                => json_encode([
                            'ofe_identificacion' => $ofe->ofe_identificacion,
                            'api'                => $cadisoftConfig->api_facturas,
                            'documentos'         => (!empty($respuesta)) ? $respuesta : "[]"
                        ]),
                        'pjj_procesado'           => 'SI',
                        'pjj_errores'             => (!empty($documentosMarcados)) ? json_encode($documentosMarcados) : null,
                        'age_id'                  => 0,
                        'age_estado_proceso_json' => 'FINALIZADO',
                        'usuario_creacion'        => auth()->user()->usu_id,
                        'estado'                  => 'ACTIVO'
                    ]);
                } else {
                    $this->registraProcesamientoJson([
                        'pjj_tipo'                => 'CADISOFT',
                        'pjj_json'                => json_encode([
                            'ofe_identificacion' => $ofe->ofe_identificacion,
                            'api'                => null,
                            'documentos'         => "[]"
                        ]),
                        'pjj_procesado'           => 'SI',
                        'pjj_errores'             => json_encode([[
                            'Marcado'             => '',
                            'documento'           => '',
                            'consecutivo'         => '',
                            'prefijo'             => '',
                            'errors'              => ['No se encontraron FC para procesar.'],
                            'fecha_procesamiento' => date('Y-m-d'),
                            'hora_procesamiento'  => date('H:i:s'),
                            'traza'               => ''
                        ]]),
                        'age_id'                  => 0,
                        'age_estado_proceso_json' => 'FINALIZADO',
                        'usuario_creacion'        => auth()->user()->usu_id,
                        'estado'                  => 'ACTIVO'
                    ]);
                }
            }

            // Notas Credito
            $tipoDocumento = [
                'tipo' => "NC"
            ];
            $consultaDocumentos = $this->consultaServerCadisoft($cadisoftConfig->api_notas, $cadisoftConfig->usuario_notas, $cadisoftConfig->password_notas, $tipoDocumento);
            $status             = $consultaDocumentos->getStatusCode();
            $razon              = $consultaDocumentos->getReasonPhrase();

            if($status != '200' && $status != '201') {
                $this->registraProcesamientoJson([
                    'pjj_tipo'                => 'CADISOFT',
                    'pjj_json'                => json_encode([
                        'ofe_identificacion' => $ofe->ofe_identificacion,
                        'api'                => $cadisoftConfig->api_notas,
                        'documentos'         => "[]"
                    ]),
                    'pjj_procesado'           => 'SI',
                    'pjj_errores'             => json_encode([[
                        'Marcado'             => '',
                        'documento'           => '',
                        'consecutivo'         => '',
                        'prefijo'             => '',
                        'errors'              => ['Se produjo un error al consultar las notas crédito en el servidor de Cadisoft. OFE [' . $ofe->ofe_identificacion . '] - Endpoint [' . $cadisoftConfig->api_notas . '] - Código HTTP [' . $status . '] - Razón [' . $razon . ']'],
                        'fecha_procesamiento' => date('Y-m-d'),
                        'hora_procesamiento'  => date('H:i:s'),
                        'traza'               => $razon
                    ]]),
                    'age_id'                  => 0,
                    'age_estado_proceso_json' => 'FINALIZADO',
                    'usuario_creacion'        => auth()->user()->usu_id,
                    'estado'                  => 'ACTIVO'
                ]);
            } else {
                $respuesta = json_decode((string)$consultaDocumentos->getBody()->getContents());

                if(!empty($respuesta)) {
                    $documentosCadisoft = array_merge($documentosCadisoft, $respuesta);

                    //Marcando las facturas en cadisoft
                    $documentosMarcados = [];
                    foreach ($respuesta as $key => $documento) {
                        //Datos Documento
                        $documentoCadisoft = [
                            'tipo'    => (string)$documento->tipoDocumento, 
                            'prefijo' => (string)$documento->prefijoDocumento, 
                            'desde'   => (string)$documento->numeroDocumento,
                            'hasta'   => (string)$documento->numeroDocumento,
                            'estado'  => "1"
                        ];

                        $endpointMarcarDocumento = str_replace("PostNotas", "PostSetEstadoNota", $cadisoftConfig->api_notas);

                        $marcarDocumentos = $this->marcarDocumentoServerCadisoft($endpointMarcarDocumento, $cadisoftConfig->usuario_facturas, $cadisoftConfig->password_facturas, $documentoCadisoft);
                        $marcarStatus     = $marcarDocumentos->getStatusCode();
                        $marcarRazon      = $marcarDocumentos->getReasonPhrase();

                        // dump("Response API");
                        // var_dump($marcarDocumentos);
                        // dump(json_decode((string)$marcarDocumentos->getBody()));
                        // dump($marcarStatus);
                        // dump($marcarRazon);
                        // dump("---------------");

                        if ($marcarStatus != '200' && $marcarStatus != '201') {
                            $documentosMarcados[] = [
                                'Marcado'             => '',
                                'documento'           => $documentoCadisoft['prefijo'].$documentoCadisoft['desde'],
                                'consecutivo'         => $documentoCadisoft['desde'],
                                'prefijo'             => $documentoCadisoft['prefijo'],
                                'errors'              => ['Se produjo un error al marcar las notas crédito en el servidor de Cadisoft. OFE [' . $ofe->ofe_identificacion . '] - Endpoint [' . $endpointMarcarDocumento . '] - Código HTTP [' . $marcarStatus . '] - Razón [' . $marcarRazon . ']'],
                                'fecha_procesamiento' => date('Y-m-d'),
                                'hora_procesamiento'  => date('H:i:s'),
                                'traza'               => ''
                            ];
                        } else {
                            $documentosMarcados[] = [
                                'Marcado'             => $documentoCadisoft['prefijo'].$documentoCadisoft['desde'],
                                'documento'           => $documentoCadisoft['prefijo'].$documentoCadisoft['desde'],
                                'consecutivo'         => $documentoCadisoft['desde'],
                                'prefijo'             => $documentoCadisoft['prefijo'],
                                'errors'              => ['Documento procesado y marcado correctamente.'],
                                'fecha_procesamiento' => date('Y-m-d'),
                                'hora_procesamiento'  => date('H:i:s'),
                                'traza'               => ''
                            ];
                        }
                    }

                    $this->registraProcesamientoJson([
                        'pjj_tipo'                => 'CADISOFT',
                        'pjj_json'                => json_encode([
                            'ofe_identificacion' => $ofe->ofe_identificacion,
                            'api'                => $cadisoftConfig->api_notas,
                            'documentos'         => (!empty($respuesta)) ? $respuesta : "[]"
                        ]),
                        'pjj_procesado'           => 'SI',
                        'pjj_errores'             => (!empty($documentosMarcados)) ? json_encode($documentosMarcados) : null,
                        'age_id'                  => 0,
                        'age_estado_proceso_json' => 'FINALIZADO',
                        'usuario_creacion'        => auth()->user()->usu_id,
                        'estado'                  => 'ACTIVO'
                    ]);
                } else {
                    $this->registraProcesamientoJson([
                        'pjj_tipo'                => 'CADISOFT',
                        'pjj_json'                => json_encode([
                            'ofe_identificacion' => $ofe->ofe_identificacion,
                            'api'                => null,
                            'documentos'         => "[]"
                        ]),
                        'pjj_procesado'           => 'SI',
                        'pjj_errores'             => json_encode([[
                            'Marcado'             => '',
                            'documento'           => '',
                            'consecutivo'         => '',
                            'prefijo'             => '',
                            'errors'              => ['No se encontraron NC para procesar.'],
                            'fecha_procesamiento' => date('Y-m-d'),
                            'hora_procesamiento'  => date('H:i:s'),
                            'traza'               => ''
                        ]]),
                        'age_id'                  => 0,
                        'age_estado_proceso_json' => 'FINALIZADO',
                        'usuario_creacion'        => auth()->user()->usu_id,
                        'estado'                  => 'ACTIVO'
                    ]);
                }
            }

            // Notas Debito
            $tipoDocumento = [
                'tipo' => "ND"
            ];
            $consultaDocumentos = $this->consultaServerCadisoft($cadisoftConfig->api_notas, $cadisoftConfig->usuario_notas, $cadisoftConfig->password_notas, $tipoDocumento);
            $status             = $consultaDocumentos->getStatusCode();
            $razon              = $consultaDocumentos->getReasonPhrase();

            if($status != '200' && $status != '201') {
                $this->registraProcesamientoJson([
                    'pjj_tipo'                => 'CADISOFT',
                    'pjj_json'                => json_encode([
                        'ofe_identificacion' => $ofe->ofe_identificacion,
                        'api'                => $cadisoftConfig->api_notas,
                        'documentos'         => "[]"
                    ]),
                    'pjj_procesado'           => 'SI',
                    'pjj_errores'             => json_encode([[
                        'Marcado'             => '',
                        'documento'           => '',
                        'consecutivo'         => '',
                        'prefijo'             => '',
                        'errors'              => ['Se produjo un error al consultar las notas débito en el servidor de Cadisoft. OFE [' . $ofe->ofe_identificacion . '] - Endpoint [' . $cadisoftConfig->api_notas . '] - Código HTTP [' . $status . '] - Razón [' . $razon . ']'],
                        'fecha_procesamiento' => date('Y-m-d'),
                        'hora_procesamiento'  => date('H:i:s'),
                        'traza'               => $razon
                    ]]),
                    'age_id'                  => 0,
                    'age_estado_proceso_json' => 'FINALIZADO',
                    'usuario_creacion'        => auth()->user()->usu_id,
                    'estado'                  => 'ACTIVO'
                ]);
            } else {
                $respuesta = json_decode((string)$consultaDocumentos->getBody()->getContents());

                if(!empty($respuesta)) {
                    $documentosCadisoft = array_merge($documentosCadisoft, $respuesta);

                    //Marcando las facturas en cadisoft
                    $documentosMarcados = [];
                    foreach ($respuesta as $key => $documento) {
                        //Datos Documento
                        $documentoCadisoft = [
                            'tipo'    => (string)$documento->tipoDocumento, 
                            'prefijo' => (string)$documento->prefijoDocumento, 
                            'desde'   => (string)$documento->numeroDocumento,
                            'hasta'   => (string)$documento->numeroDocumento,
                            'estado'  => "1"
                        ];

                        $endpointMarcarDocumento = str_replace("PostNotas", "PostSetEstadoNota", $cadisoftConfig->api_notas);

                        $marcarDocumentos = $this->marcarDocumentoServerCadisoft($endpointMarcarDocumento, $cadisoftConfig->usuario_facturas, $cadisoftConfig->password_facturas, $documentoCadisoft);
                        $marcarStatus     = $marcarDocumentos->getStatusCode();
                        $marcarRazon      = $marcarDocumentos->getReasonPhrase();

                        // dump("Response API");
                        // var_dump($marcarDocumentos);
                        // dump(json_decode((string)$marcarDocumentos->getBody()));
                        // dump($marcarStatus);
                        // dump($marcarRazon);
                        // dump("---------------");

                        if ($marcarStatus != '200' && $marcarStatus != '201') {
                            $documentosMarcados[] = [
                                'Marcado'             => '',
                                'documento'           => $documentoCadisoft['prefijo'].$documentoCadisoft['desde'],
                                'consecutivo'         => $documentoCadisoft['desde'],
                                'prefijo'             => $documentoCadisoft['prefijo'],
                                'errors'              => ['Se produjo un error al marcar las notas débito en el servidor de Cadisoft. OFE [' . $ofe->ofe_identificacion . '] - Endpoint [' . $endpointMarcarDocumento . '] - Código HTTP [' . $marcarStatus . '] - Razón [' . $marcarRazon . ']'],
                                'fecha_procesamiento' => date('Y-m-d'),
                                'hora_procesamiento'  => date('H:i:s'),
                                'traza'               => ''
                            ];
                        } else {
                            $documentosMarcados[] = [
                                'Marcado'             => $documentoCadisoft['prefijo'].$documentoCadisoft['desde'],
                                'documento'           => $documentoCadisoft['prefijo'].$documentoCadisoft['desde'],
                                'consecutivo'         => $documentoCadisoft['desde'],
                                'prefijo'             => $documentoCadisoft['prefijo'],
                                'errors'              => ['Documento procesado y marcado correctamente.'],
                                'fecha_procesamiento' => date('Y-m-d'),
                                'hora_procesamiento'  => date('H:i:s'),
                                'traza'               => ''
                            ];
                        }
                    }

                    $this->registraProcesamientoJson([
                        'pjj_tipo'                => 'CADISOFT',
                        'pjj_json'                => json_encode([
                            'ofe_identificacion' => $ofe->ofe_identificacion,
                            'api'                => $cadisoftConfig->api_notas,
                            'documentos'         => (!empty($respuesta)) ? $respuesta : "[]"
                        ]),
                        'pjj_procesado'           => 'SI',
                        'pjj_errores'             => (!empty($documentosMarcados)) ? json_encode($documentosMarcados) : null,
                        'age_id'                  => 0,
                        'age_estado_proceso_json' => 'FINALIZADO',
                        'usuario_creacion'        => auth()->user()->usu_id,
                        'estado'                  => 'ACTIVO'
                    ]);
                } else {
                    $this->registraProcesamientoJson([
                        'pjj_tipo'                => 'CADISOFT',
                        'pjj_json'                => json_encode([
                            'ofe_identificacion' => $ofe->ofe_identificacion,
                            'api'                => null,
                            'documentos'         => "[]"
                        ]),
                        'pjj_procesado'           => 'SI',
                        'pjj_errores'             => json_encode([[
                            'Marcado'             => '',
                            'documento'           => '',
                            'consecutivo'         => '',
                            'prefijo'             => '',
                            'errors'              => ['No se encontraron ND para procesar.'],
                            'fecha_procesamiento' => date('Y-m-d'),
                            'hora_procesamiento'  => date('H:i:s'),
                            'traza'               => ''
                        ]]),
                        'age_id'                  => 0,
                        'age_estado_proceso_json' => 'FINALIZADO',
                        'usuario_creacion'        => auth()->user()->usu_id,
                        'estado'                  => 'ACTIVO'
                    ]);
                }
            }
        } catch(\Exception $e) {
            $this->registraProcesamientoJson([
                'pjj_tipo'                => 'CADISOFT',
                'pjj_json'                => json_encode([
                    'ofe_identificacion' => $ofe->ofe_identificacion,
                    'api'                => "",
                    'documentos'         => "[]"
                ]),
                'pjj_procesado'           => 'SI',
                'pjj_errores'             => json_encode([[
                    'Marcado'             => '',
                    'documento'           => '',
                    'consecutivo'         => '',
                    'prefijo'             => '',
                    'errors'              => ['Se produjo un error al consultar las facturas en el servidor de Cadisoft. OFE [' . $ofe->ofe_identificacion . '] - Código HTTP [' . $e->getCode() . '] - Razón [' . $e->getMessage() . ']'],
                    'fecha_procesamiento' => date('Y-m-d'),
                    'hora_procesamiento'  => date('H:i:s'),
                    'traza'               => $razon
                ]]),
                'age_id'                  => 0,
                'age_estado_proceso_json' => 'FINALIZADO',
                'usuario_creacion'        => auth()->user()->usu_id,
                'estado'                  => 'ACTIVO'
            ]);
        }

        return $documentosCadisoft;
    }

    /**
     * Mapea las columnas del documento hacia las columnas requeridas en openETL.
     *
     * @param object $documento Documento en procesamiento
     * @return array $documentoMapeado Documento mapeado
     */
    private function mapeoColumnas($documento) {
        $documentoMapeado = [
            'tde_codigo'                           => $documento->codigoTipoDocumento,
            'top_codigo'                           => $documento->tipoOperacion,
            'ofe_identificacion'                   => $documento->facturador->identificacion,
            'adq_identificacion'                   => $documento->adquiriente->identificacion,
            'rfa_resolucion'                       => ($documento->tipoDocumento == 'FC') ? $documento->resolucion->numero : null,
            'rfa_prefijo'                          => (!empty($documento->prefijoDocumento)) ? $documento->prefijoDocumento : '',
            'cdo_consecutivo'                      => (string)$documento->numeroDocumento,
            'cdo_fecha'                            => $documento->fechaEmision,
            'cdo_hora'                             => $documento->horaEmision . ((strlen($documento->horaEmision) < 6) ? ':00' : ''),
            'cdo_vencimiento'                      => $documento->pago->fechaVencimiento, // OJO - Pendiente por definir si definitivamente se deja esta columna
            'cdo_representacion_grafica_documento' => '1',
            'cdo_representacion_grafica_acuse'     => '1',
            'cdo_valor_sin_impuestos'              => (string)$this->getDBNum($documento->subtotal),
            'cdo_impuestos'                        => (string)$this->getDBNum(($documento->subtotalMasTributos - $documento->subtotal)),
            'cdo_total'                            => (string)$this->getDBNum($documento->subtotalMasTributos),
            'mon_codigo'                           => $documento->codigoMoneda,
            'items'                                => [],
            'tributos'                             => [],
            'cdo_informacion_adicional'            => [
                'correos_receptor'       => isset($documento->adquiriente->notificacion) ? str_replace("," ,";", (string)$documento->adquiriente->notificacion) : '',
                'pdf_base64'             => $documento->base64,
                'cdo_procesar_documento' => 'SI'
            ]
        ];

        //Si el tipo de documento electronico es 03 - Factura de talonario o papel con numeración de contingencia
        if ($documento->codigoTipoDocumento == '03') {
            $documentoMapeado['cdo_documento_adicional'][] = [
                'rod_codigo'    => 'R1',
                'prefijo'       => (!empty($documento->prefijoDocumento)) ? $documento->prefijoDocumento : '',
                'consecutivo'   => (string)$documento->numeroDocumento,
                'fecha_emision' => $documento->fechaEmision,
            ];
        }

        // Tasa de cambio
        if(isset($documento->tasaCambio) && !empty(trim($documento->tasaCambio))) {
            $documentoMpeado['cdo_trm'] = $documento->tasaCambio;
        }

        // Periodo de facturación
        if(isset($documento->periodoFacturacion->fechaInicio) && !empty(trim($documento->periodoFacturacion->fechaInicio)) && isset($documento->periodoFacturacion->fechaFin) && !empty(trim($documento->periodoFacturacion->fechaFin))) {
            $documentoMapeado['cdo_periodo_facturacion'] = [
                'dad_periodo_fecha_inicio' => $documento->periodoFacturacion->fechaInicio,
                'dad_periodo_hora_inicio'  => $documento->periodoFacturacion->horaInicio . ((strlen($documento->periodoFacturacion->horaInicio) < 6) ? ':00' : ''),
                'dad_periodo_fecha_fin'    => $documento->periodoFacturacion->fechaFin,
                'dad_periodo_hora_fin'     => $documento->periodoFacturacion->horaFin . ((strlen($documento->periodoFacturacion->horaFin) < 6) ? ':00' : '')
            ];
        }

        // Medios de pago
        $arrIdentificadoresPago = [];
        foreach($documento->pago->listaIdentificadoresPago as $identificadorPago) {
            $arrIdentificadoresPago[] = [
                'id' => (string)$identificadorPago
            ];
        }
        $documentoMapeado['cdo_medios_pago'][] = [
            'fpa_codigo'               => (string)$documento->pago->id,
            'atributo_fpa_codigo_id'   => (isset($documento->cdo_medios_pago[0]->fpa_codigo) && !empty($documento->cdo_medios_pago[0]->fpa_codigo)) ? (string)$documento->cdo_medios_pago[0]->fpa_codigo : null,
            'atributo_fpa_codigo_name' => (isset($documento->cdo_medios_pago[0]->fpa_name) && !empty($documento->cdo_medios_pago[0]->fpa_name)) ? (string)$documento->cdo_medios_pago[0]->fpa_name : null,
            'mpa_codigo'               => $documento->pago->codigoMedioPago,
            'men_fecha_vencimiento'    => $documento->pago->fechaVencimiento,
            'men_identificador_pago'   => $arrIdentificadoresPago
        ];

        // Adquirente - Se debe crear o actualizar conforme a la data recibida
        $arrResponsableTributos = [];
        foreach($documento->adquiriente->listaResponsabilidadesTributarias as $responsabilidadTributaria) {
            $arrResponsableTributos[] = $responsabilidadTributaria->codigo;
        }

        $documentoMapeado['adquirente'] = [
            'adq_identificacion'             => $documento->adquiriente->identificacion,
            'ofe_identificacion'             => $documento->facturador->identificacion,
            'adq_razon_social'               => ($documento->adquiriente->naturaleza == '1') ? $documento->adquiriente->nombreRegistrado : null,
            'adq_nombre_comercial'           => ($documento->adquiriente->naturaleza == '1') ? $documento->adquiriente->razonSocial : null,
            'adq_primer_apellido'            => ($documento->adquiriente->naturaleza == '2') ? $documento->adquiriente->apellidos : null,
            'adq_segundo_apellido'           => null,
            'adq_primer_nombre'              => ($documento->adquiriente->naturaleza == '2') ? $documento->adquiriente->nombres : null,
            'adq_otros_nombres'              => null,
            'tdo_codigo'                     => (string)$documento->adquiriente->tipoIdentificacion,
            'toj_codigo'                     => (string)$documento->adquiriente->naturaleza,
            'pai_codigo'                     => (string)$documento->adquiriente->direccion->codigoPais,
            'dep_codigo'                     => (string)$documento->adquiriente->direccion->codigoDepartamento,
            'mun_codigo'                     => (string)substr($documento->adquiriente->direccion->codigoCiudad, 2),
            'cpo_codigo'                     => !empty($documento->adquiriente->direccion->codigoPostal) ? (string)$documento->adquiriente->direccion->codigoPostal : (string)$documento->adquiriente->direccion->codigoCiudad,
            'adq_direccion'                  => (string)$documento->adquiriente->direccion->direccionFisica,
            'pai_codigo_domicilio_fiscal'    => (string)$documento->adquiriente->direccionFiscal->codigoPais,
            'dep_codigo_domicilio_fiscal'    => (string)$documento->adquiriente->direccionFiscal->codigoDepartamento,
            'mun_codigo_domicilio_fiscal'    => (string)substr($documento->adquiriente->direccionFiscal->codigoCiudad, 2),
            'cpo_codigo_domicilio_fiscal'    => !empty($documento->adquiriente->direccionFiscal->codigoPostal) ? (string)$documento->adquiriente->direccionFiscal->codigoPostal : (string)$documento->adquiriente->direccionFiscal->codigoCiudad,
            'adq_direccion_domicilio_fiscal' => (string)$documento->adquiriente->direccionFiscal->direccionFisica,
            'adq_nombre_contacto'            => !empty($documento->adquiriente->contacto->nombre) ? $documento->adquiriente->contacto->nombre : null,
            'adq_telefono'                   => !empty($documento->adquiriente->contacto->telefono) ? $documento->adquiriente->contacto->telefono : (!empty($documento->adquiriente->telefono) ? $documento->adquiriente->telefono : null),
            'adq_fax'                        => !empty($documento->adquiriente->contacto->fax) ? $documento->adquiriente->contacto->fax : null,
            'adq_correo'                     => !empty($documento->adquiriente->contacto->email) ? $documento->adquiriente->contacto->email : (!empty($documento->adquiriente->email) ? $documento->adquiriente->email : null),
            'adq_notas'                      => !empty($documento->adquiriente->contacto->observaciones) ? $documento->adquiriente->contacto->observaciones : null,
            'adq_correos_notificacion'       => !empty($documento->adquiriente->contacto->email) ? $documento->adquiriente->contacto->email : (!empty($documento->adquiriente->email) ? $documento->adquiriente->email : null),
            'rfi_codigo'                     => $documento->adquiriente->codigoRegimen,
            'ref_codigo'                     => [$documento->adquiriente->responsabilidadFiscal],
            'responsable_tributos'           => $arrResponsableTributos,
        ];

        // Aplica a NC y ND
        if($documento->tipoDocumento != 'FC') {
            foreach ($documento->listaDocumentosReferenciados as $documentoReferencia) {
                $prefijoConsecutivoCufe = $this->obtenerPrefijoConsecutivoCufe($documentoReferencia->id);
                
                $relacionado = [
                    'clasificacion' => ($documentoReferencia->tipo == 'FE') ? 'FC' : $documentoReferencia->tipo,
                    'consecutivo'   => (array_key_exists('consecutivo', $prefijoConsecutivoCufe)) ? $prefijoConsecutivoCufe['consecutivo'] : $documentoReferencia->id,
                    'cufe'          => (array_key_exists('cufe', $prefijoConsecutivoCufe)) ? $prefijoConsecutivoCufe['cufe'] : $documentoReferencia->id,
                    'fecha_emision' => $documentoReferencia->fecha
                ];

                if(array_key_exists('prefijo', $prefijoConsecutivoCufe) && !empty($prefijoConsecutivoCufe['prefijo'])) {
                    $relacionado['prefijo'] = $prefijoConsecutivoCufe['prefijo'];
                }
                $documentoMapeado['cdo_documento_referencia'][] = $relacionado;
            }

            foreach($documento->listaCorrecciones as $correccion) {
                $documentoMapeado['cdo_conceptos_correccion'][] = [
                    'cco_codigo' => (string)$correccion->codigo,
                    'cdo_observacion_correccion' => $correccion->descripcion
                ];
            }
        }

        // Items y tributos del documento
        foreach($documento->listaProductos as $item) {
            $infoItem = [];
            $infoItem = [
                'ddo_secuencia'                         => (string)$item->numeroLinea,
                'ddo_tipo_item'                         => 'IP',
                'ddo_descripcion_uno'                   => $item->informacion,
                'ddo_cantidad'                          => (string)$item->cantidad,
                'ddo_total'                             => (string)$this->getDBNum($item->valorTotal),
                'ddo_codigo'                            => $item->idProducto,
                'ddo_valor_unitario'                    => (string)$this->getDBNum($item->valorUnitario),
                'und_codigo'                            => $item->codigoUnidad,
                'ddo_marca'                             => $item->item->marca,
                'ddo_modelo'                            => $item->item->modelo,
                'ddo_codigo_vendedor'                   => $item->item->codigoArticuloVendedor,
                'ddo_codigo_vendedor_subespecificacion' => $item->item->codigoExtendidoVendedor,
                'cpr_codigo'                            => $item->item->codigoEstandar                
            ];
            
            $infoPropiedadesAdicionales = [];
            if (isset($item->item->listaCaracteristicas[0]) && !empty($item->item->listaCaracteristicas[0])) {
                $infoPropiedadesAdicionales =  [
                    'ddo_propiedades_adicionales'           => $item->item->listaCaracteristicas[0]
                ];
            }

            $infoCompradorCliente = [];
            if (isset($documento->CompradorCliente) && !empty($documento->CompradorCliente)) {
                $infoCompradorCliente =  [
                    'ddo_identificacion_comprador'          => [[
                        'id' => $documento->CompradorCliente,
                        'atributo_consecutivo_id' => 'AutorizaID-ERP/EPS'
                    ]]
                ];
            }

            $documentoMapeado['items'][] = array_merge($infoItem, $infoPropiedadesAdicionales, $infoCompradorCliente);

            if(!empty($item->listaImpuestos)) {
                foreach($item->listaImpuestos as $impuesto) {
                    if ($impuesto->codigo != '' || $impuesto->codigo != null) {
                        $documentoMapeado['tributos'][] = [
                            'ddo_secuencia'  => (string)$item->numeroLinea,
                            'tri_codigo'     => $impuesto->codigo,
                            'iid_valor'      => (string)$this->getDBNum($impuesto->valor),
                            'iid_porcentaje' => [
                                'iid_base'       => (string)$this->getDBNum($impuesto->baseGravable),
                                'iid_porcentaje' => (string)$this->getDBNum($impuesto->porcentaje)
                            ]
                        ];
                    }                    
                }
            }
        }

        // Sector Salud
        $documentoMapeado = $this->mapeoSectorSalud($documento, $documentoMapeado);

        return $documentoMapeado;
    }

    /**
     * Obtiene el prefijo, consecutivo y cufe para un documento
     *
     * @param string $identificador Identificador del documento compuesto por la concatenacion del prefijo y el consecutivo
     * @return array Array conteniendo índices para el prefijo, consecutivo y cufe, array vacio si no se encuentra el documento
     */
    private function obtenerPrefijoConsecutivoCufe($identificador) {
        $existe = EtlCabeceraDocumentoDaop::select(['cdo_id', 'rfa_prefijo', 'cdo_consecutivo', 'cdo_cufe', 'cdo_fecha'])
            ->where(DB::raw('CONCAT(rfa_prefijo, "", cdo_consecutivo)'), '=', trim($identificador))
            ->first();

        if(!$existe)
            $existe = EtlFatDocumentoDaop::select(['cdo_id', 'rfa_prefijo', 'cdo_consecutivo', 'cdo_cufe', 'cdo_fecha'])
                ->where(DB::raw('CONCAT(rfa_prefijo, "", cdo_consecutivo)'), '=', trim($identificador))
                ->first();

        if($existe) {
            return [
                'prefijo'       => $existe->rfa_prefijo,
                'consecutivo'   => $existe->cdo_consecutivo,
                'cufe'          => $existe->cdo_cufe,
                'fecha_emision' => $existe->cdo_fecha
            ];
        } else {
            return [];
        }
    }

    /**
     * Realiza el registro de un procesamiento json en el modelo correspondiente
     *
     * @param array $valores Array que contiene la información de los campos para la creación dle registro
     * @return void
     */
    private function registraProcesamientoJson($valores){
        EtlProcesamientoJson::create([
            'pjj_tipo'                => $valores['pjj_tipo'],
            'pjj_json'                => $valores['pjj_json'],
            'pjj_procesado'           => $valores['pjj_procesado'],
            'pjj_errores'             => $valores['pjj_errores'],
            'age_id'                  => $valores['age_id'],
            'age_estado_proceso_json' => $valores['age_estado_proceso_json'],
            'usuario_creacion'        => $valores['usuario_creacion'],
            'estado'                  => $valores['estado']
        ]);
    }

    /**
     * Elimina el caracter coma de un string.
     * 
     * @param string $num String a normalizar
     * @return string $num String con el remplazo hecho 
     */
    private function normalizeNum($num) {
        return str_replace(',', '', $num);
    }

    /**
     * Da formato a un número.
     * 
     * @param float $value Valor flotante a formatear
     * @return float $value Valor flotante con formato
     */
    private function getDBNum($value) {
        if($value !== '') {
            $value = $this->normalizeNum($value);
            $value = number_format($value, 2, '.', '');
        }
        return $value;
    }

    /**
     * Intenta codificar a utf-8 las diferentes lineas que componen el archivo de entrada
     *
     * @param $line
     * @return bool|false|string|string[]|null
     */
    private function fixEncoding($line) {
        if (($codificacion = mb_detect_encoding($line)) !== false)
            return mb_convert_encoding($line, "UTF-8", $codificacion);
        return mb_convert_encoding($line, "ISO-8859-1");
    }

    /**
     * Mapeo de información correspondiente al Sector Salud
     *
     * @param object $documento Documento que esta siendo mapeado
     * @param array $documentoMapeado Documento mapeado
     * @return array $documentoMapeado Documento mapeado
     */
    private function mapeoSectorSalud($documento, $documentoMapeado) {
        if(isset($documento->cdo_documento_referencia) && !empty($documento->cdo_documento_referencia)) {
            if(!array_key_exists('cdo_documento_referencia', $documentoMapeado))
                $documentoMapeado['cdo_documento_referencia'] = [];

            foreach ($documento->cdo_documento_referencia as $documentoReferencia) {
                $prefijoConsecutivoCufe = $this->obtenerPrefijoConsecutivoCufe(trim($documentoReferencia->prefijo) . trim($documentoReferencia->consecutivo));

                $documentoMapeado['cdo_documento_referencia'][] = [
                    'clasificacion'                           => $documentoReferencia->clasificacion == 'FE' ? 'FC' : $documentoReferencia->clasificacion,
                    'prefijo'                                 => $documentoReferencia->prefijo,
                    'consecutivo'                             => $documentoReferencia->consecutivo,
                    'atributo_consecutivo_id'                 => $documentoReferencia->atributo_consecutivo_id,
                    'atributo_consecutivo_name'               => $documentoReferencia->atributo_consecutivo_name,
                    'atributo_consecutivo_agency_id'          => $documentoReferencia->atributo_consecutivo_agency_id,
                    'atributo_consecutivo_version_id'         => $documentoReferencia->atributo_consecutivo_version_id,
                    'cufe'                                    => '',
                    'uuid'                                    => (array_key_exists('cufe', $prefijoConsecutivoCufe)) ? $prefijoConsecutivoCufe['cufe'] : '',
                    'atributo_uuid_name'                      => $documentoReferencia->atributo_uuid_name,
                    'fecha_emision'                           => (array_key_exists('fecha_emision', $prefijoConsecutivoCufe)) ? $prefijoConsecutivoCufe['fecha_emision'] : '',
                    'codigo_tipo_documento'                   => $documentoReferencia->codigo_tipo_documento,
                    'atributo_codigo_tipo_documento_list_uri' => $documentoReferencia->atributo_codigo_tipo_documento_list_uri,
                    'tipo_documento'                          => $documentoReferencia->tipo_documento
                ];
            }
        }

        if(isset($documento->cdo_documento_adicional) && !empty($documento->cdo_documento_adicional)) {
            if(!array_key_exists('cdo_documento_adicional', $documentoMapeado))
                $documentoMapeado['cdo_documento_adicional'] = [];

            foreach ($documento->cdo_documento_adicional as $documentoAdicional) {
                $prefijoConsecutivoCufe = $this->obtenerPrefijoConsecutivoCufe(trim($documentoAdicional->prefijo) . trim($documentoAdicional->consecutivo));

                $documentoMapeado['cdo_documento_adicional'][] = [
                    'seccion'                         => $documentoAdicional->seccion,
                    'rod_codigo'                      => $documentoAdicional->rod_codigo,
                    'atributo_rod_codigo_list_uri'    => $documentoAdicional->atributo_rod_codigo_list_uri,
                    'prefijo'                         => $documentoAdicional->prefijo,
                    'consecutivo'                     => $documentoAdicional->consecutivo,
                    'atributo_consecutivo_id'         => $documentoAdicional->atributo_consecutivo_id,
                    'atributo_consecutivo_name'       => $documentoAdicional->atributo_consecutivo_name,
                    'atributo_consecutivo_agency_id'  => $documentoAdicional->atributo_consecutivo_agency_id,
                    'atributo_consecutivo_version_id' => $documentoAdicional->atributo_consecutivo_version_id,
                    'cufe'                            => '',
                    'uuid'                            => (array_key_exists('cufe', $prefijoConsecutivoCufe)) ? $prefijoConsecutivoCufe['cufe'] : '',
                    'atributo_uuid_name'              => $documentoAdicional->atributo_uuid_name,
                    'fecha_emision'                   => (array_key_exists('fecha_emision', $prefijoConsecutivoCufe)) ? $prefijoConsecutivoCufe['fecha_emision'] : '',
                    'tipo_documento'                  => $documentoAdicional->tipo_documento
                ];
            }
        }

        if(isset($documento->cdo_documento_referencia_linea) && !empty($documento->cdo_documento_referencia_linea)) {
            $documentoMapeado['cdo_documento_referencia_linea'] = [];

            foreach ($documento->cdo_documento_referencia_linea as $documentoReferenciaLinea) {
                $documentoMapeado['cdo_documento_referencia_linea'][] = [
                    'prefijo'                         => $documentoReferenciaLinea->prefijo,
                    'consecutivo'                     => $documentoReferenciaLinea->consecutivo,
                    'atributo_consecutivo_id'         => $documentoReferenciaLinea->atributo_consecutivo_id,
                    'atributo_consecutivo_name'       => $documentoReferenciaLinea->atributo_consecutivo_name,
                    'atributo_consecutivo_agency_id'  => $documentoReferenciaLinea->atributo_consecutivo_agency_id,
                    'atributo_consecutivo_version_id' => $documentoReferenciaLinea->atributo_consecutivo_version_id,
                    'valor'                           => $documentoReferenciaLinea->valor,
                    'atributo_valor_moneda'           => $documentoReferenciaLinea->atributo_valor_moneda,
                    'atributo_valor_concepto'         => $documentoReferenciaLinea->atributo_valor_concepto
                ];
            }
        }

        if(isset($documento->cdo_referencia_adquirente) && !empty($documento->cdo_referencia_adquirente)) {
            $documentoMapeado['cdo_referencia_adquirente'] = [];

            foreach ($documento->cdo_referencia_adquirente as $referenciaAdquirente) {
                $documentoMapeado['cdo_referencia_adquirente'][] = [
                    'id'                                 => $referenciaAdquirente->id,
                    'atributo_id_name'                   => $referenciaAdquirente->atributo_id_name,
                    'nombre'                             => $referenciaAdquirente->nombre,
                    'postal_address_codigo_pais'         => $referenciaAdquirente->postal_address_codigo_pais,
                    'postal_address_descripcion_pais'    => $referenciaAdquirente->postal_address_descripcion_pais,
                    'residence_address_id'               => $referenciaAdquirente->residence_address_id,
                    'residence_address_atributo_id_name' => $referenciaAdquirente->residence_address_atributo_id_name,
                    'residence_address_nombre_ciudad'    => $referenciaAdquirente->residence_address_nombre_ciudad,
                    'residence_address_direccion'        => $referenciaAdquirente->residence_address_direccion,
                    'codigo_pais'                        => $referenciaAdquirente->codigo_pais,
                    'descripcion_pais'                   => $referenciaAdquirente->descripcion_pais
                ];
            }
        }

        if(isset($documento->interoperabilidad) && !empty($documento->interoperabilidad)) {
            $documentoMapeado['cdo_interoperabilidad'] = [];
            $informacionAdicional = [];

            $indice = 0;
            foreach ($documento->interoperabilidad->informacionAdicional as $infoAdicional) {
                if(isset($infoAdicional->valor) && $infoAdicional->valor != '') {
                    $informacionAdicional[$indice]['valor'] = $infoAdicional->valor;

                    if(isset($infoAdicional->llave) && $infoAdicional->llave != '') {
                        $arrReemplazar = array('NUMERO_AUTORIZACIÓN', 'NUMERO_AUTORIZACI\u00d3N');
                        $informacionAdicional[$indice]['nombre'] = str_replace($arrReemplazar, 'NUMERO_AUTORIZACION', $infoAdicional->llave);
                    }

                    if(isset($infoAdicional->schemeName) && $infoAdicional->schemeName != '')
                        $informacionAdicional[$indice]['atributo_name'] = $infoAdicional->schemeName;

                    if(isset($infoAdicional->schemeId) && $infoAdicional->schemeId != '')
                        $informacionAdicional[$indice]['atributo_id'] = $infoAdicional->schemeId;

                    $indice++;
                }
            }

            if(!empty($informacionAdicional)) {
                $documentoMapeado['cdo_interoperabilidad'][] = [
                    'informacion_general' => $this->arrCdoInteroperabilidadInformacionGeneral($documento->interoperabilidad->general),
                    'interoperabilidad' => [
                        'grupo' => $documento->interoperabilidad->nombreSector,
                        'collection' => [
                            [
                                'nombre' => $documento->interoperabilidad->unidadSector,
                                'informacion_adicional' => $informacionAdicional
                            ]
                        ]
                    ]
                ];
            } else {
                $documentoMapeado['cdo_interoperabilidad'][] = [
                    'informacion_general' => $this->arrCdoInteroperabilidadInformacionGeneral($documento->interoperabilidad->general)
                ];
            }
        }

        return $documentoMapeado;
    }

    /**
     * Arma el array de información correspondiente a Información General de la propiedad cdo_interoperabilidad del Sector Salud
     *
     * @param string $actoAdministrativo Acto administrativo
     * @return array $infoGeneral Array de información general
     */
    private function arrCdoInteroperabilidadInformacionGeneral($informacionGeneral) {
        $infoGeneral = [];
        foreach($informacionGeneral as $infoGral) {
            $infoGeneral[] = [
                'nombre' => $infoGral->llave,
                'valor'  => $infoGral->valor
            ];
        }

        return $infoGeneral;
    }
}
