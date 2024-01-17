<?php

namespace App\Http\Modulos;

use Validator;
use Ramsey\Uuid\Uuid;
use App\Traits\DiTrait;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Http\Models\AdoAgendamiento;
use Illuminate\Support\Facades\File;
use openEtl\Tenant\Traits\TenantTrait;
use Illuminate\Support\Facades\Storage;
use App\Http\Modulos\DataInputWorker\Excel\ParserExcel;
use App\Http\Modulos\DataInputWorker\ConstantsDataInput;
use App\Http\Modulos\DataInputWorker\FNC\ParserExcelFNC;
use App\Http\Modulos\Sistema\EtlProcesamientoJson\EtlProcesamientoJson;
use App\Http\Modulos\DataInputWorker\DhlAeroExpreso\ParserExcelDhlAeroExpreso;
use App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaop;
use App\Http\Modulos\ProyectosEspeciales\Emision\DHLExpress\PickupCash\PryArchivoPickupCash;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

/**
 * Gestiona las tareas relacionadas con la carga de documentos
 *
 * Class EtlDocumentoDaopController
 * @package App\Http\Modulos
 */
class EtlDocumentoDaopController extends Controller {
    use DiTrait;

    /**
     * Extensiones permitidas para el cargue de registros.
     * 
     * @var Array
     */
    public $arrExtensionesPermitidas = ['xlsx', 'xls'];

    /**
     * Constructor de la clase.
     */
    public function __construct() {
        $this->middleware(['jwt.auth', 'jwt.refresh']);

        $this->middleware(['VerificaMetodosRol:DocumentosSoporteDocumentosPorExcelSubir'])->only([
            'cargarDocumentoSoporte'
        ]);

        $this->middleware(['VerificaMetodosRol:DocumentosSoporteNotasCreditoPorExcelSubir'])->only([
            'cargarNotaCreditoDocumentoSoporte'
        ]);
    }

    public function reorganizarDocumentosJson($procesamientoMaximoEdi, $jsonDocumentos) {
        // Reorganiza los documentos recibidos de acuerdo a la máxima cantidad de registros permitidos para procesamiento EDI
        $tiposDocumentos = ['FC', 'NC', 'ND', 'DS'];
        $i               = 0;
        $contDocs        = 0;
        $indiceTipos     = 0;
        $grupos          = [];
        $indiceBloque    = 0;
        while($contDocs < $procesamientoMaximoEdi) {
            if(array_key_exists($indiceTipos, $tiposDocumentos)) {
                $tipoDoc = $tiposDocumentos[$indiceTipos];
                if(array_key_exists($tipoDoc, $jsonDocumentos) && count($jsonDocumentos[$tipoDoc]) > 0) {
                    if($i < count($jsonDocumentos[$tipoDoc])) {
                        $grupos[$indiceBloque][$tipoDoc][] = $jsonDocumentos[$tipoDoc][$i];
                        $contDocs++;
                        $i++;
                    } else {
                        $i = 0;
                        if($indiceTipos < count($tiposDocumentos)) $indiceTipos++;
                    }
                } elseif(!array_key_exists($tipoDoc, $jsonDocumentos)) {
                    if($indiceTipos < count($tiposDocumentos)) $indiceTipos++;
                }

                if($contDocs == $procesamientoMaximoEdi) {
                    $contDocs = 0;
                    $indiceBloque++;
                }
            } else  {
                // Sale del ciclo
                $contDocs = $procesamientoMaximoEdi+1;
            }
        }

        return $grupos;
    }

    /**
     * Recibe un Documento Json en el request para registrar un agendamiento para procesamiento del Json.
     *
     * @param  \Illuminate\Http\Request
     * @return \Illuminate\Http\Response
     */
    public function agendarEdi(Request $request) {
        // Autenticación recibiendo un token en los encabezados del request
        $user = auth()->user();
        if (!$request->has('documentos')) {
            return response()->json([
                'message' => 'Error en la petición',
                'errors'  => ['La petición esta mal formada, debe expecificarse un tipo y objeto JSON']
            ], 422);
        }

        try {
            $grupos = $this->reorganizarDocumentosJson($user->getBaseDatos->bdd_cantidad_procesamiento_edi, $request->documentos);
            foreach($grupos as $grupo) {
                // Crea el agendamiento en el sistema
                $agendamiento = AdoAgendamiento::create([
                    'usu_id'                    => $user->usu_id,
                    'bdd_id'                    => $user->bdd_id,
                    'age_proceso'               => 'EDI',
                    'age_cantidad_documentos'   => 1,
                    'age_prioridad'             => null,
                    'usuario_creacion'          => $user->usu_id,
                    'estado'                    => 'ACTIVO'
                ]);

                // Graba la información del Json en la tabla de programacion de procesamientos de Json
                $programacion = EtlProcesamientoJson::create([
                    'pjj_tipo'                  => 'EDI',
                    'pjj_json'                  => json_encode(['documentos' => $grupo]),
                    'pjj_procesado'             => 'NO',
                    'age_id'                    => $agendamiento->age_id,
                    'age_estado_proceso_json'   => null,
                    'usuario_creacion'          => $user->usu_id,
                    'estado'                    => 'ACTIVO',
                ]);
            }

            return response()->json([
                'message' => 'El archivo fue cargado para ser procesado en background'
            ], 201);
        } catch (Exception $e) {
            $error = $e->getMessage();
            return response()->json([
                'message' => 'Error al registrar el Json',
                'errors'  => [$error]
            ], 400);
        }
    }

    /**
     * Recibe un Documento Json en el request y registra los documentos electronicos que este contiene.
     *
     * @param Request
     * @return JsonResponse
     * @throws \Exception
     */
    public function registrarDocumentos(Request $request) {
        $user = auth()->user();
        if (!$request->has('documentos')) {
            return response()->json([
                'message' => 'Error en la petición',
                'errors'  => ['La petición esta mal formada, debe especificarse un tipo y objeto JSON']
            ], 422);
        }

        if($request->has('cdo_origen') && $request->cdo_origen == 'MANUAL')
            $cdo_origen = $request->cdo_origen;
        else
            $cdo_origen = ($user->usu_type == 'INTEGRACION') ? 'INTEGRACION' : 'API';

        $json = json_decode(json_encode(['documentos' => $request->documentos]));

        return $this->registrarDocumentosEmision($request, $json, $cdo_origen);
    }

    /**
     * Consulta un documento previamente registrado para su procesamiento.
     *
     * @param  \Illuminate\Http\Request
     * @return \Illuminate\Http\Response
     */
    public function consultarDocumentoRegistrado(Request $request) {
        $user = auth()->user();

        // Reglas de validacón para los parámetros que debn llegar en el request
        $reglas = [
            'ofe_identificacion'    => 'required|string|max:20',
            'tipo_documento'        => 'required|string|max:2',
            'prefijo'               => 'nullable|string|max:5',
            'consecutivo'           => 'required|string|max:20'
        ];

        $validador = Validator::make($request->all(), $reglas);
        if($validador->fails()){
            return response()->json([
                'message' => 'Errores en su petición',
                'errors' => $validador->errors()->all()
            ], 400);
        }

        $identificacion = TenantTrait::sanitizarIdentificacion($request->ofe_identificacion);

        // Verifica que el OFE exista en la BD del usuario autenticado
        $ofe = ConfiguracionObligadoFacturarElectronicamente::where('ofe_identificacion', $identificacion)
            ->validarAsociacionBaseDatos()
            ->first();
        if(!$ofe) {
            return response()->json([
                'message' => 'Errores en su petición',
                'errors' => [
                    'Ofe no existe',
                    'Verifique la identificación, que no tenga caracteres especiales y que no incluya el dígito de verificación'
                ]
            ], 400);
        }

        // Verificamos la existencia del documento de acuerdo a los parámetros recibidos
        $documento = EtlCabeceraDocumentoDaop::where('ofe_id', $ofe->ofe_id)
            ->where('cdo_clasificacion', $request->tipo_documento)
            ->where('rfa_prefijo', trim($request->prefijo))
            ->where('cdo_consecutivo', $request->consecutivo)
            ->with([
                'getDetalleDocumentosDaop',
                'getImpuestosItemsDocumentosDaop',
                'getAnticiposDocumentosDaop',
                'getCargosDescuentosDocumentosDaop',
                'getDadDocumentosDaop',
                'getMediosPagoDocumentosDaop',
                'getConfiguracionObligadoFacturarElectronicamente',
                'getConfiguracionAdquirente'
            ])
            ->first();

        if(!$documento) {
            return response()->json([
                'message' => 'Documento no Existe',
                'errors' => [
                    'No se encontró el documento indicado'
                ]
            ], 404);
        }

        return response()->json([
            'data' => [
                'documento' => $documento
            ]
        ], 200);
    }

    /**
     * Recibe un Json en el request y programa un agendamiento para que
     * en background se procese el Json mediate el método registrarDocumentos
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function programarProcesamientosJson(Request $request) {

        // Autenticación recibiendo un token en los encabezados del request
        $auth = $request->header('Authorization');
        if($auth) {
            $this->token = str_replace('Bearer ', '', $auth);
        } else {
            throw new \Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException('');
        }

        // Usuario autenticado
        $payload = JWTAuth::getPayload($this->token);
        $usu_id = $payload->get('id');

        try {
            $user = User::find($usu_id);
            $grupos = $this->reorganizarDocumentosJson($user->getBaseDatos->bdd_cantidad_procesamiento_edi, $request->json);
            foreach($grupos as $grupo) {
                // Crea el agendamiento en el sistema
                $agendamiento = AdoAgendamiento::create([
                    'usu_id'                    => $usu_id,
                    'bdd_id'                    => $user->bdd_id,
                    'age_proceso'               => 'EDI',
                    'age_cantidad_documentos'   => 1,
                    'age_prioridad'             => null,
                    'usuario_creacion'          => $usu_id,
                    'estado'                    => 'ACTIVO'
                ]);

                // Graba la información del Json en la tabla de programacion de procesamientos de Json
                $programacion = PseProcesamientoJson::create([
                    'pjj_tipo'                  => $request->tipo,
                    'pjj_json'                  => json_encode(['documentos' => $grupo]),
                    'pjj_procesado'             => 'NO',
                    'age_id'                    => $agendamiento->age_id,
                    'age_estado_proceso_json'   => null,
                    'usuario_creacion'          => $usu_id,
                    'estado'                    => 'ACTIVO',
                ]);
            }

            return response()->json([
                'message' => 'El archivo fue cargado para ser procesado en background'
            ], 201);
        } catch (Exception $e) {
            $error = $e->getMessage();
            return response()->json([
                'message' => 'Error al registrar el Json',
                'errors'  => [$error]
            ], 400);
        }
    }

    /**
     * Permite la carga manual de facturas mediante archivos Excel.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     * @throws \Exception
     */
    public function cargarFactura(Request $request) {
        if (!$request->hasFile('archivo')) {
            return response()->json([
                'error' => false,
                'message' => 'No se ha subido ningún archivo.'
            ], 400);
        }

        $this->arrExtensionesPermitidas = array_merge($this->arrExtensionesPermitidas, ['txt']);

        $archivo = explode('.', $request->file('archivo')->getClientOriginalName());
        if (
            (!empty($request->file('archivo')->extension()) && !in_array($request->file('archivo')->extension(), $this->arrExtensionesPermitidas)) ||
            !in_array($archivo[count($archivo) - 1], $this->arrExtensionesPermitidas)
        ) {
            return response()->json([
                'message' => false,
                'errors'  => 'Solo se permite la carga de archivos EXCEL y TXT.'
            ], 409);
        }

        if (!$request->has('ofe_id')) {
            return response()->json([
                'error' => false,
                'message' => 'No se ha especificado un oferente.'
            ], 422);
        }
        // Obtiene el OFE
        $ofe = ConfiguracionObligadoFacturarElectronicamente::find($request->ofe_id);

        if ($ofe === null) {
            return response()->json([
                'error' => false,
                'message' => 'El oferente indicado no existe.'
            ], 422);
        }

        $parseador = new ParserExcel();
        $response = $parseador->run($request->file('archivo')->path(), ConstantsDataInput::FC, $ofe->ofe_id, $request->file('archivo')->getClientOriginalName());
        return response()->json([
            'error' => $response['error'],
            'errores' => $response['errores'],
            'message' => $response['message']
        ], 200);
    }

    /**
     * Permite la carga manual de facturas mediante archivos Excel.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     * @throws \Exception
     */
    public function cargarFacturaFNC(Request $request) {
        if (!$request->hasFile('archivo')) {
            return response()->json([
                'error' => false,
                'message' => 'No se ha subido ningún archivo.'
            ], 400);
        }

        $this->arrExtensionesPermitidas = array_merge($this->arrExtensionesPermitidas, ['pgp']);
        
        $archivo = explode('.', $request->file('archivo')->getClientOriginalName());
        if (
            (!empty($request->file('archivo')->extension()) && !in_array($request->file('archivo')->extension(), $this->arrExtensionesPermitidas)) ||
            !in_array($archivo[count($archivo) - 1], $this->arrExtensionesPermitidas)
        ) {
            return response()->json([
                'message' => false,
                'errors'  => 'Solo se permite la carga de archivos EXCEL y PGP.'
            ], 409);
        }

        if (!$request->has('ofe_id')) {
            return response()->json([
                'error' => false,
                'message' => 'No se ha especificado un oferente.'
            ], 422);
        }
        // Obtiene el OFE
        $ofe = ConfiguracionObligadoFacturarElectronicamente::find($request->ofe_id);

        if ($ofe === null) {
            return response()->json([
                'error' => false,
                'message' => 'El oferente indicado no existe.'
            ], 422);
        }

        $parseador = new ParserExcelFNC();
        $response = $parseador->run($request->file('archivo')->path(), ConstantsDataInput::FC, $ofe->ofe_id, $request->file('archivo')->getClientOriginalName());
        return response()->json([
            'error' => $response['error'],
            'errores' => $response['errores'],
            'message' => $response['message']
        ], 200);
    }

    /**
     * Permite la carga manual de notas de crédito y de débito mediante archivos Excel.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     * @throws \Exception
     */
    public function cargarNdNc(Request $request)
    {
        if (!$request->hasFile('archivo')) {
            return response()->json([
                'error' => false,
                'message' => 'No se ha subido ningún archivo.'
            ], 400);
        }

        $this->arrExtensionesPermitidas = array_merge($this->arrExtensionesPermitidas, ['txt']);

        $archivo = explode('.', $request->file('archivo')->getClientOriginalName());
        if (
            (!empty($request->file('archivo')->extension()) && !in_array($request->file('archivo')->extension(), $this->arrExtensionesPermitidas)) ||
            !in_array($archivo[count($archivo) - 1], $this->arrExtensionesPermitidas)
        ) {
            return response()->json([
                'message' => false,
                'errors'  => 'Solo se permite la carga de archivos EXCEL y TXT.'
            ], 409);
        }

        if (!$request->has('ofe_id')) {
            return response()->json([
                'error' => false,
                'message' => 'No se ha especificado un oferente.'
            ], 422);
        }
        // Obtiene el OFE
        $ofe = ConfiguracionObligadoFacturarElectronicamente::find($request->ofe_id);

        if ($ofe === null) {
            return response()->json([
                'error' => false,
                'message' => 'El oferente indicado no existe.'
            ], 422);
        }

        $parseador = new ParserExcel();
        $response = $parseador->run($request->file('archivo')->path(), ConstantsDataInput::NC_ND, $ofe->ofe_id, $request->file('archivo')->getClientOriginalName());
        return response()->json([
            'error' => $response['error'],
            'errores' => $response['errores'],
            'message' => $response['message']
        ], 200);
    }

    /**
     * Permite la carga manual de notas de crédito y de débito mediante archivos Excel de FNC.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     * @throws \Exception
     */
    public function cargarNdNcFNC(Request $request)
    {
        if (!$request->hasFile('archivo')) {
            return response()->json([
                'error' => false,
                'message' => 'No se ha subido ningún archivo.'
            ], 400);
        }

        $this->arrExtensionesPermitidas = array_merge($this->arrExtensionesPermitidas, ['pgp']);

        $archivo = explode('.', $request->file('archivo')->getClientOriginalName());
        if (
            (!empty($request->file('archivo')->extension()) && !in_array($request->file('archivo')->extension(), $this->arrExtensionesPermitidas)) ||
            !in_array($archivo[count($archivo) - 1], $this->arrExtensionesPermitidas)
        ) {
            return response()->json([
                'message' => false,
                'errors'  => 'Solo se permite la carga de archivos EXCEL y PGP.'
            ], 409);
        }

        if (!$request->has('ofe_id')) {
            return response()->json([
                'error' => false,
                'message' => 'No se ha especificado un oferente.'
            ], 422);
        }
        // Obtiene el OFE
        $ofe = ConfiguracionObligadoFacturarElectronicamente::find($request->ofe_id);

        if ($ofe === null) {
            return response()->json([
                'error' => false,
                'message' => 'El oferente indicado no existe.'
            ], 422);
        }

        $parseador = new ParserExcelFNC();
        $response = $parseador->run($request->file('archivo')->path(), ConstantsDataInput::NC_ND, $ofe->ofe_id, $request->file('archivo')->getClientOriginalName());
        return response()->json([
            'error' => $response['error'],
            'errores' => $response['errores'],
            'message' => $response['message']
        ], 200);
    }

    /**
     * Permite la carga manual de documentos electrónicos mediante archivos Excel para DHL Aero Expreso.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     * @throws \Exception
     */
    public function cargarDocumentoElectronicoDhlAeroExpreso(Request $request) {
        try {
            if (!$request->hasFile('archivo')) {
                return response()->json([
                    'error' => false,
                    'message' => 'No se ha subido ningún archivo.'
                ], 400);
            }

            $archivo = explode('.', $request->file('archivo')->getClientOriginalName());
            if (
                (!empty($request->file('archivo')->extension()) && !in_array($request->file('archivo')->extension(), $this->arrExtensionesPermitidas)) ||
                !in_array($archivo[count($archivo) - 1], $this->arrExtensionesPermitidas)
            ) {
                return response()->json([
                    'message' => false,
                    'errors'  => 'Solo se permite la carga de archivos EXCEL.'
                ], 409);
            }

            if (!$request->has('tipo_documento_electronico')) {
                return response()->json([
                    'error' => false,
                    'message' => 'No se ha indicado el tipo de documento electrónico.'
                ], 400);
            }

            if (!$request->has('ofe_id')) {
                return response()->json([
                    'error' => false,
                    'message' => 'No se ha especificado un oferente.'
                ], 422);
            }

            // Obtiene el OFE
            $ofe = ConfiguracionObligadoFacturarElectronicamente::find($request->ofe_id);
            if ($ofe === null) {
                return response()->json([
                    'error' => false,
                    'message' => 'El oferente indicado no existe.'
                ], 422);
            }

            // Verifica que el OFE este configurado para hacer uso de la llave adq_id_personalizado en la columna ofe_identificador_unico_adquirente que es obligatoria para su integración
            if(
                empty($ofe->ofe_identificador_unico_adquirente) ||
                (
                    !empty($ofe->ofe_identificador_unico_adquirente) && !in_array('adq_id_personalizado', $ofe->ofe_identificador_unico_adquirente)
                )
            ) {
                return response()->json([
                    'message' => 'Error al procesar el Archivo',
                    'errors'  => ['El OFE no está configurado para hacer uso de la llave adq_id_personalizado la cual es necesaria para su integración.'],
                ], 422);
            }

            $parseador = new ParserExcelDhlAeroExpreso();
            $proceso   = $parseador->procesarExcel($request, $ofe);

            if($proceso['error']) {
                $this->registrarLogErrores($proceso['errores']);
            }

            return response()->json($proceso, 201);
        } catch (\Exception $e) {
            $this->registrarLogErrores([ $e->getMessage() ], $e->getFile() . ' - Línea ' . $e->getLine() . ': ' . $e->getMessage() . "\n\nTrace:\n" . $e->getTraceAsString());

            return response()->json([
                'message' => 'Error al procesar el archivo',
                'errors'  => [$e->getMessage()]
            ], 400);
        }
    }

    /**
     * Registra los errores generados durante el procesamiento del archivo en el log de errores de carga manual.
     *
     * @param array $errores Array conteniendo los errores generados
     * @param string $traza Traza del error generado
     * @return void
     */
    private function registrarLogErrores($errores, $traza = '') {
        $arrExcepciones = [];
        $arrExcepciones[] = [
            'documento'           => '',
            'consecutivo'         => '',
            'prefijo'             => '',
            'errors'              => $errores,
            'fecha_procesamiento' => date('Y-m-d'),
            'hora_procesamiento'  => date('H:i:s'),
            'traza'               => $traza
        ];

        $parseador = new ParserExcelDhlAeroExpreso();
        $parseador->registraProcesamientoJson([
            'pjj_tipo'                => 'EDI',
            'pjj_json'                => json_encode([]),
            'pjj_procesado'           => 'SI',
            'pjj_errores'             => json_encode($arrExcepciones),
            'age_id'                  => 0,
            'age_estado_proceso_json' => 'FINALIZADO',
            'usuario_creacion'        => auth()->user()->usu_id,
            'estado'                  => 'ACTIVO'
        ]);
    }

    /**
     * Permite la carga manual de archivos Pickup Cash de DHL Express.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function cargarArchivoPickupCashDhlExpress(Request $request) {
        if (!$request->hasFile('archivo'))
            return response()->json([
                'error' => false,
                'message' => 'No se ha subido ningún archivo.'
            ], 400);

        if (!$request->has('ofe_id'))
            return response()->json([
                'error' => false,
                'message' => 'No se ha especificado un oferente.'
            ], 422);

        $extension = $request->file('archivo')->getClientOriginalExtension();
        $nombre    = $request->file('archivo')->getClientOriginalName();

        $errores = [
            'documento'           => $nombre,
            'consecutivo'         => '',
            'prefijo'             => '',
            'fecha_procesamiento' => date('Y-m-d'),
            'hora_procesamiento'  => date('H:i:s'),
            'archivo'             => $nombre,
            'traza'               => '',
            'errors'              => []
        ];
        
        try {
            $ofe = ConfiguracionObligadoFacturarElectronicamente::find($request->ofe_id);
            if (!$ofe)
                $errores['errors'][] = 'El oferente indicado no existe.';

            if($extension != 'txt')
                $errores['errors'][] = 'La extensión de archivo debe ser txt';

            if(strlen(str_replace('.' . $extension, '', $nombre)) > 255)
                $errores['errors'][] = 'El nombre del archivo no puede superar los 255 caracteres';

            $linea = fgets(fopen($request->file('archivo')->path(), 'r'));
            $arrayValores = explode('|', $linea);
            if(count($arrayValores) != 35)
                $errores['errors'][] = 'La cantidad de posiciones de información por guía debe ser igual a 35';

            if(!empty($errores['errors'])) {
                $this->registrarErrorPickupCash(json_encode([]), json_encode([$errores]), auth()->user()->usu_id);

                return response()->json([
                    'error' => false,
                    'message' => $errores['errors']
                ], 422);
            }

            $ruta        = $ofe->ofe_conexion_ftp['entrada_pickup_cash_new'];
            $nuevoNombre = 'gbi_CO_exp_CTC_' . date('Ymd') . Uuid::uuid4()->toString() . '.txt';

            $nombreDiscoSftp = 'SftpDhlExpress';
            $this->configurarDiscoSftpDhlExpress($ofe, $nombreDiscoSftp);

            $dataArchivo = File::get($request->file('archivo')->getRealPath());
            $encoding    = mb_detect_encoding($dataArchivo, 'auto');
            if ($encoding != 'UTF-8')
                $dataArchivo = iconv($encoding, "UTF-8", $dataArchivo);

            Storage::disk($nombreDiscoSftp)->put($ruta . $nuevoNombre, $dataArchivo);

            PryArchivoPickupCash::create([
                'apc_nombre_archivo_original' => $nombre,
                'apc_nombre_archivo_carpeta'  => $nuevoNombre,
                'usuario_creacion'            => auth()->user()->usu_id,
                'estado'                      => 'ACTIVO'
            ]);

            return response()->json([
                'message' => 'Archivo Pickup Cash cargado para su procesamiento en background'
            ], 200);
        } catch (\Exception $e) {
            $errores['traza']    = $e->getTraceAsString();
            $errores['errors'][] = 'Se generó un error al procesar el archivo';

            $this->registrarErrorPickupCash(json_encode([]), json_encode([$errores]), auth()->user()->usu_id);

            return response()->json([
                'error' => false,
                'message' => $errores['errors']
            ], 422);
        }
    }

    /**
     * Configura un disco en tiempo de ejecución para el SFTP de DHL Express
     *
     * @param ConfiguracionObligadoFacturarElectronicamente $ofe Modelo OFE
     * @return void
     */
    private function configurarDiscoSftpDhlExpress(ConfiguracionObligadoFacturarElectronicamente $ofe, $nombreDisco) {
        config([
            'filesystems.disks.' . $nombreDisco => [
                'driver'   => 'sftp',
                'host'     => $ofe->ofe_conexion_ftp['host'],
                'username' => $ofe->ofe_conexion_ftp['username'],
                'password' => $ofe->ofe_conexion_ftp['password'],
            ]
        ]);
    }

    /**
     * Registra errores de procesamiento de la carga de archivo Pikcup Cash en el modelo EtlProcesamientoJson.
     *
     * @param json $json Objeto conteniendo la información de la guía procesada, puede ser null en caso de errores del código
     * @param json $errores Objeto conteniendo los errores correspondientes
     * @param int $usu_id ID del usuario relacionado con el procesamiento
     * @return void
     */
    private function registrarErrorPickupCash($json, $errores, $usu_id) {
        EtlProcesamientoJson::create([
            'pjj_tipo'                => 'PICKUP-CASH',
            'pjj_procesado'           => 'SI',
            'pjj_json'                => $json,
            'pjj_errores'             => $errores,
            'age_id'                  => 0,
            'age_estado_proceso_json' => 'FINALIZADO',
            'usuario_creacion'        => $usu_id,
            'estado'                  => 'ACTIVO'
        ]);
    }

    /**
     * Recibe un Documento Json en el request y registra los documentos electronicos que este contiene.
     * 
     * Todo el proceso se realiza a través del método registrarDocumentos del mismo controlador, pero la diferencia en la información en la respuesta final, ya que en este método se puede incluir o no la información del Json con el que se registró un documento en el sistema
     *
     * @param \Illuminate\Http\Request
     * @return \Illuminate\Http\Response
     * @throws \Exception
     */
    public function emisionRegistrarDocumentos(Request $request) {
        $request->merge([
            'retornar_id_lote' => true
        ]);
        $rptaRegistro = $this->registrarDocumentos($request);
        $status       = $rptaRegistro->getStatusCode();
        $respuesta    = json_decode((string)$rptaRegistro->getContent(), true);

        if(
            $status != 201 ||
            (
                $status == 201 &&
                (!array_key_exists('documentos_fallidos', $respuesta) || (array_key_exists('documentos_fallidos', $respuesta) && empty($respuesta['documentos_fallidos'])))
            )
        ) // Se capturó una excepción de acuerdo al código en registrarDocumentos() o se procesó correctamente pero no hay documentos fallidos
            return $rptaRegistro;
        else { // Se procesó correctamente pero se debe verificar si hay documentos fallidos y si dentro de los fallido hay errores de documentos ya registrados
            $documentosFallidos = $respuesta['documentos_fallidos'];
            $respuesta['documentos_fallidos'] = [];
            $respuesta['documentos']['FC']    = [];

            $totalFC = $request->filled('documentos') && array_key_exists('FC', $request->documentos) ? count($request->documentos['FC']) : 0;
            $totalNC = $request->filled('documentos') && array_key_exists('NC', $request->documentos) ? count($request->documentos['NC']) : 0;
            $totalND = $request->filled('documentos') && array_key_exists('ND', $request->documentos) ? count($request->documentos['ND']) : 0;

            $totalDocumentos = $totalFC + $totalNC + $totalND;

            foreach($documentosFallidos as $fallido) {
                $errors  = [];
                $cdoId   = '';
                $cdoLote = '';

                foreach($fallido['errors'] as $error) {
                    if(strstr($error, 'ya esta registrado') !== false) {
                        $errorTmp  = explode('~', $error);
                        $cdoIdLote = explode('|', $errorTmp[1]);
                        $cdoId     = $cdoIdLote[0];
                        $cdoLote   = $cdoIdLote[1];
                    }

                    $errors[] = isset($errorTmp) && array_key_exists('0', $errorTmp) ? $errorTmp[0] : $error;
                }

                $fallido['errors'] = $errors;

                $documento = EtlCabeceraDocumentoDaop::select(['cdo_id', 'ofe_id', 'cdo_clasificacion', 'rfa_prefijo', 'cdo_consecutivo', 'fecha_creacion'])
                    ->with([
                        'getConfiguracionObligadoFacturarElectronicamente:ofe_id,ofe_identificacion',
                        'getEstadosDocumentoDaop' => function($query) use ($cdoId) {
                            $query->select(['est_id', 'cdo_id', 'est_informacion_adicional'])
                                ->where('cdo_id', $cdoId)
                                ->where('est_estado', 'EDI')
                                ->where('est_resultado', 'EXITOSO')
                                ->where('est_ejecucion', 'FINALIZADO')
                                ->orderBy('est_id', 'desc')
                                ->first();
                        }
                    ])
                    ->find($cdoId);

                if(isset($documento->cdo_clasificacion) && $documento->cdo_clasificacion == 'FC') {
                    $informacionAdicional = !empty($documento->getEstadosDocumentoDaop[0]->est_informacion_adicional) ? json_decode($documento->getEstadosDocumentoDaop[0]->est_informacion_adicional, true) : [];

                    $jsonDocumento = $this->obtenerArchivoDeDisco(
                        'emision',
                        $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                        $documento,
                        array_key_exists('est_json', $informacionAdicional) ? $informacionAdicional['est_json'] : ''
                    );

                    if(empty($jsonDocumento))
                        $jsonDocumento = json_encode(['Error' => 'Archivo Json no encontrado']);

                    $respuesta['documentos']['FC'][] = json_decode($jsonDocumento);
                }
                $respuesta['documentos_fallidos'][] = $fallido;
                
                if($totalDocumentos == 1) {
                    $respuesta['message'] = str_replace($respuesta['lote'], $cdoLote, $respuesta['message']);
                    $respuesta['lote']    = $cdoLote;
                }
            }
        }

        return response()->json([$respuesta], 201);
    }

    /**
     * Permite la carga manual de documentos soporte mediante archivos Excel.
     *
     * @param Request $request
     * @return Response
     * @throws \Exception
     */
    public function cargarDocumentoSoporte(Request $request) {
        return $this->cargarDsDsNc($request, 'DS');
    }
    
    /**
     * Permite la carga manual de notas crédito documento soporte mediante archivos Excel.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     * @throws \Exception
     */
    public function cargarNotaCreditoDocumentoSoporte(Request $request) {
        return $this->cargarDsDsNc($request, 'DS_NC');
    }

    /**
     * Permite la carga manual de Documentos Soporte y Notas Crédito Documento soporte mediante archivos Excel.
     *
     * @param Request $request
     * @param string  $tipoDocumento Indica el tipo de documento a cargar
     * @return \Illuminate\Http\Response
     * @throws \Exception
     */
    private function cargarDsDsNc(Request $request, $tipoDocumento) {
        if (!$request->hasFile('archivo')) {
            return response()->json([
                'error' => false,
                'message' => 'No se ha subido ningún archivo.'
            ], 400);
        }

        $archivo = explode('.', $request->file('archivo')->getClientOriginalName());
        if (
            (!empty($request->file('archivo')->extension()) && !in_array($request->file('archivo')->extension(), $this->arrExtensionesPermitidas)) ||
            !in_array($archivo[count($archivo) - 1], $this->arrExtensionesPermitidas)
        ) {
            return response()->json([
                'message' => false,
                'errors'  => 'Solo se permite la carga de archivos EXCEL.'
            ], 409);
        }

        if (!$request->has('ofe_id')) {
            return response()->json([
                'error' => false,
                'message' => 'No se ha especificado un oferente.'
            ], 422);
        }
        // Obtiene el OFE
        $ofe = ConfiguracionObligadoFacturarElectronicamente::find($request->ofe_id);

        if ($ofe === null) {
            return response()->json([
                'error' => false,
                'message' => 'El oferente indicado no existe.'
            ], 422);
        }

        $clasificacion = ($tipoDocumento == ConstantsDataInput::DS) ? ConstantsDataInput::DS : ConstantsDataInput::DS_NC;
        $parseador = new ParserExcel();
        $response = $parseador->run($request->file('archivo')->path(), $clasificacion, $ofe->ofe_id, $request->file('archivo')->getClientOriginalName());
        return response()->json([
            'error' => $response['error'],
            'errores' => $response['errores'],
            'message' => $response['message']
        ], 200);
    }
}
