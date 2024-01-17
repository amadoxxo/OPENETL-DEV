<?php
namespace App\Http\Modulos\Recepcion\Documentos;

use Ramsey\Uuid\Uuid;
use Illuminate\Http\Request;
use openEtl\Main\Traits\PackageMainTrait;
use App\Http\Modulos\Sistema\Agendamiento\AdoAgendamiento;
use App\Http\Modulos\Sistema\EtlProcesamientoJson\EtlProcesamientoJson;
use App\Http\Modulos\Recepcion\Configuracion\Proveedores\ConfiguracionProveedor;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

class DocumentosManuales {
    use PackageMainTrait;

    /**
     * Almacena los errores basados en la ausencia de campos en los requerimientos
     * @var array
     */
    public $errorsPetition = [];

    /**
     * Ejecuta un chequeo en el objeto $request basado en una lista de items para  verificar si esan ausentes
     * @param Request $request
     * @param array $fields
     */
    protected function analizarPostRequest(Request $request, array $fields) {
        if ($this->errorsPetition == null)
            $this->errorsPetition = [];
        foreach ($fields as $field)
            if (!$request->has($field))
                array_push($this->errorsPetition, "'$field' no puede ser nulo.");
    }

    /**
     * Determina si ha registrado errores en la peticion
     * @return bool
     */
    protected function postHasError(Request $request, array $fields) {
        $this->analizarPostRequest($request, $fields);
        return count($this->errorsPetition) > 0;
    }

    /**
     * Retorna los campos faltantes en el requerimiento http que se ha recibido
     * @return \Illuminate\Http\Response
     */
    protected function getErrorResponseFieldsMissing() {
        return $this->getErrorResponse($this->errorsPetition);
    }

    /**
     * @param array $errors
     * @return \Illuminate\Http\Response
     */
    protected function getErrorResponse(array $errors) {
        return response()->json(
            [
                'message' => 'Error en la petición.',
                'errors'  => $errors,
            ], 422
        );
    }

    /**
     * Crea un lote de procesamiento.
     * 
     * @return string $lote Lote de procesamiento
     */
    protected function buildLote() {
        $uuid    = Uuid::uuid4();
        $dateObj = \DateTime::createFromFormat('U.u', microtime(TRUE));
        $msg     = $dateObj->format('u');
        $msg    /= 1000;

        $dateObj->setTimeZone(new \DateTimeZone('America/Bogota'));
        $dateTime = $dateObj->format('YmdHis').intval($msg);

        $lote = $dateTime . '_' . $uuid->toString();

        return $lote;
    }

    /**
     * Procesamiento de documentos manuales.
     * 
     * Los archivos se reciben por parejas (XML y PDF) para su procesamiento
     *
     * @param  \Illuminate\Http\Request
     * @return \Illuminate\Http\Response
     */
    public function documentosManuales(Request $request) {
        $user = auth()->user();

        if($request->has('oferente') && !empty($request->oferente)) { //cuando la solicitud se hace desde Web
            $oferente = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'ofe_identificacion', 'ofe_recepcion_fnc_activo'])
                ->where('ofe_identificacion', $request->oferente)
                ->validarAsociacionBaseDatos()
                ->where('estado', 'ACTIVO')
                ->first();
        } else { //cuando se hace la peticion desde la API endpoint expuestos para el cliente
            $oferente = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'ofe_identificacion', 'ofe_recepcion_fnc_activo'])
                ->where('ofe_identificacion', $request->ofe_identificacion)
                ->validarAsociacionBaseDatos()
                ->where('estado', 'ACTIVO')
                ->first();
        }

        if ($oferente == null)
            return response()->json(
                [
                    'message' => 'Error en la petición.',
                    'errors'  => ['El OFE seleccionado no existe en el sistema o no está activo'],
                ], 404
            );

        //Si el request se enviaron los archivos en base64, se deben validar los archivos de otra manera
        //y construir el objeto apartir de la informacion enviada
        if ($request->has('json') && $request->json == true) {
            //Errores generados al procesar los documentos
            $errors = [];

            //documentos a procesar
            $documentos =[];

            foreach($request->documentos as $documento) {
                if (
                    isset($documento['nombre']) && !empty($documento['nombre']) && $documento['nombre'] != null &&
                    isset($documento['pdf']) && !empty($documento['pdf']) && $documento['pdf'] != null &&
                    isset($documento['xml']) && !empty($documento['xml']) && $documento['xml'] != null
                ) {
                    $documentos[] = $documento;
                } else {
                    $errors[] = 'Los campos name, pdf y xml son requeridos.';
                }
            }
            
            if (empty($errors)) {
                $lotes = [];
                foreach(array_chunk($documentos, $user->getBaseDatos->bdd_cantidad_procesamiento_rdi) as $grupos) {
                    $loteProcesamiento = $this->buildLote();
                    $lotes[] = $loteProcesamiento;
    
                    // Crea el objeto que almacenará la data para el agendamiento
                    $objeto = [
                        'ofe_identificacion' => $oferente->ofe_identificacion,
                        'pro_identificacion' => '',
                        'usu_identificacion' => $user->usu_identificacion,
                        'lote_procesamiento' => $loteProcesamiento,
                        'origen'             => 'MANUAL',
                        'documentos'         => []
                    ];
                    
                    foreach($grupos as $documento) {
                        $objeto['documentos'][] = [
                            'nombre' => $this->sanear_string($documento['nombre']),
                            'pdf'    => $documento['pdf'],
                            'xml'    => $documento['xml']
                        ];
                    }
    
                    // Se crea el agendamiento y registro correspondiente en tabla de procesamiento json
                    $agendamiento = AdoAgendamiento::create([
                        'usu_id'                  => $user->usu_id,
                        'bdd_id'                  => $user->bdd_id,
                        'age_proceso'             => 'RDI',
                        'age_cantidad_documentos' => count($objeto['documentos']),
                        'age_prioridad'           => null,
                        'usuario_creacion'        => $user->usu_id,
                        'estado'                  => 'ACTIVO'
                    ]);
                    
                    EtlProcesamientoJson::create([
                        'pjj_tipo'         => 'RDI',
                        'pjj_json'         => json_encode($objeto),
                        'pjj_procesado'    => 'NO',
                        'age_id'           => $agendamiento->age_id,
                        'usuario_creacion' => $user->usu_id,
                        'estado'           => 'ACTIVO'
                    ]);
                }
            } else {
                return response()->json(
                    [
                        'message' => 'Faltan archivos en la petición',
                        'errors'  => $errors
                    ], 409
                );
            }
        } else {
            //El request es enviado desde web o desde el endpoint expuesto al cliente
            if(!$request->has('portal_proveedores') || ($request->has('portal_proveedores') && $request->portal_proveedores != true)) {
                $mandatoryFields = ['documentos'];
                if (strpos($request->headers->get('Content-Type'), 'multipart/form-data') === 0) {
                    if ($this->postHasError($request, $mandatoryFields))
                        return $this->getErrorResponseFieldsMissing();
                }
            } else {
                //El request es enviado desde portal proveedores
                $mandatoryFields = ['documentos', 'ofe_identificacion', 'pro_identificacion', 'usu_identificacion'];
                if (strpos($request->headers->get('Content-Type'), 'multipart/form-data') === 0) {
                    if ($this->postHasError($request, $mandatoryFields))
                        return $this->getErrorResponseFieldsMissing();
                }

                $proveedor = ConfiguracionProveedor::select(['pro_id', 'pro_identificacion'])
                    ->where('ofe_id', $oferente->ofe_id)
                    ->where('pro_identificacion', $request->pro_identificacion)
                    ->where('estado', 'ACTIVO')
                    ->first();

                if ($proveedor == null)
                    return response()->json(
                        [
                            'message' => 'Error en la petición.',
                            'errors'  => ['El Proveedor seleccionado no existe en el sistema, no está activo o no está relacionado con el OFE'],
                        ], 404
                    );
            }

            //Los archivos los envian como file en el request
            // Valida que los archivos incluidos en 'documentos' lleguen completos
            $archivosFaltan = [];
            $documentos = explode(';', $request->documentos);
            foreach($documentos as $documento) {
                $pdf = str_replace(' ', '_', $documento) . '_pdf';
                $xml = str_replace(' ', '_', $documento) . '_xml';
                
                if(!$request->hasFile($pdf))
                    $archivosFaltan[] = $pdf;

                if(!$request->hasFile($xml))
                    $archivosFaltan[] = $xml;
            }

            if(!empty($archivosFaltan))
                return response()->json(
                    [
                        'message' => 'Faltan archivos en la petición',
                        'errors'  => ['Faltan los siguientes achivos en la petición: ' . implode(', ', $archivosFaltan)]
                    ], 409
                );

            $lotes = [];
            foreach(array_chunk($documentos, $user->getBaseDatos->bdd_cantidad_procesamiento_rdi) as $grupos) {
                $loteProcesamiento = $this->buildLote();
                $lotes[] = $loteProcesamiento;

                // Crea el objeto que almacenará la data para el agendamiento
                $objeto = [
                    'ofe_identificacion' => $oferente->ofe_identificacion,
                    'pro_identificacion' => ($request->has('portal_proveedores') && $request->portal_proveedores == true) ? $proveedor->pro_identificacion : '',
                    'usu_identificacion' => ($request->has('portal_proveedores') && $request->portal_proveedores == true) ? $request->usu_identificacion : $user->usu_identificacion,
                    'lote_procesamiento' => $loteProcesamiento,
                    'origen'             => 'MANUAL',
                    'documentos'         => []
                ];

                if($request->filled('epm_id') && $oferente->ofe_recepcion_fnc_activo == 'SI')
                    $objeto['epm_id'] = $request->epm_id;

                foreach($grupos as $documento) {
                    $pdf = str_replace(' ', '_', $documento) . '_pdf';
                    $xml = str_replace(' ', '_', $documento) . '_xml';
                    $objeto['documentos'][] = [
                        'nombre' => $this->sanear_string($documento),
                        'pdf'    => base64_encode($request->file($pdf)->get()),
                        'xml'    => base64_encode($request->file($xml)->get())
                    ];
                }

                // Se crea el agendamiento y registro correspondiente en tabla de procesamiento json
                $agendamiento = AdoAgendamiento::create([
                    'usu_id'                  => $user->usu_id,
                    'bdd_id'                  => $user->bdd_id,
                    'age_proceso'             => 'RDI',
                    'age_cantidad_documentos' => count($objeto['documentos']),
                    'age_prioridad'           => null,
                    'usuario_creacion'        => $user->usu_id,
                    'estado'                  => 'ACTIVO'
                ]);
                
                EtlProcesamientoJson::create([
                    'pjj_tipo'         => 'RDI',
                    'pjj_json'         => json_encode($objeto),
                    'pjj_procesado'    => 'NO',
                    'age_id'           => $agendamiento->age_id,
                    'usuario_creacion' => $user->usu_id,
                    'estado'           => 'ACTIVO'
                ]);
            }
        }
        
        return response()->json([
            'message' => 'Documentos procesados y agendados en el sistema para su procesamiento',
            'lotes_procesamiento' => $lotes
        ], 201);
    }
}
