<?php
namespace App\Http\Modulos\NominaElectronica;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Modulos\NominaElectronica\DataBuilder;
use App\Http\Modulos\NominaElectronica\ConstantsDataInput;
use App\Http\Modulos\NominaElectronica\ParserExcel\ParserExcel;
use App\Http\Modulos\Configuracion\NominaElectronica\Empleadores\ConfiguracionEmpleador;

class EtlNominaElectronicaController extends Controller {

    /**
     * Extensiones permitidas para el cargue de registros.
     * 
     * @var Array
     */
    public $arrExtensionesPermitidas = ['xlsx', 'xls'];

    public function __construct() {
        $this->middleware(['jwt.auth', 'jwt.refresh']);

        $this->middleware(['VerificaMetodosRol:DnDocumentosPorExcelSubirNomina'])->only([
            'generarInterfaceNomina'
        ]);

        $this->middleware(['VerificaMetodosRol:DnDocumentosPorExcelSubirEliminar'])->only([
            'generarInterfaceEliminar'
        ]);
    }

    /**
     * Recibe un Documento Json en el request y registra los documentos de nómina electrónica que este contiene.
     *
     * @param \Illuminate\Http\Request
     * @return \Illuminate\Http\Response
     */
    public function registrarDocumentosNominaElectronica(Request $request) {
        if (!$request->has('documentos')) {
            return response()->json([
                'message' => 'Error en la petición',
                'errors'  => ['La petición esta mal formada, debe especificarse un tipo y objeto JSON']
            ], 422);
        }

        try {
            if(!$request->has('cdn_origen'))
                $cdn_origen = ConstantsDataInput::ORIGEN_API;

            $json     = json_decode(json_encode(['documentos' => $request->documentos]));
            $builder  = new DataBuilder(auth()->user()->usu_id, $json, $cdn_origen);
            $procesar = $builder->procesar();

            return response()->json($procesar['resultado'], $procesar['codigo_http']);
        } catch (\Exception $e) {
            $error = $e->getMessage();
            return response()->json([
                'message' => 'Error al registrar los documentos de nómina electrónica',
                'errors'  => [$error]
            ], 422);
        }
    }

    /**
     * Carga un archivo de Excel de Nomina / Novedad / Ajuste / Eliminar.
     *
     * @param Request $request
     * @return Response
     */
    public function cargarExcel(Request $request) {
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

        if (!$request->has('emp_id')) {
            return response()->json([
                'error' => false,
                'message' => 'No se ha especificado un empleador.'
            ], 422);
        }

        $empleador = ConfiguracionEmpleador::find($request->emp_id);

        if ($empleador === null) {
            return response()->json([
                'error' => false,
                'message' => 'El empleador indicado no existe.'
            ], 422);
        }

        $parseador = new ParserExcel();
        $response  = $parseador->procesarArchivo($request->file('archivo')->path(), ($request->tipo_proceso == 'nomina' ? ConstantsDataInput::EXCEL_NOMINA : ConstantsDataInput::EXCEL_ELIMINAR), $empleador->emp_id, $request->file('archivo')->getClientOriginalName());
        
        return response()->json([
            'error'   => $response['error'],
            'errores' => $response['errores'],
            'message' => $response['message']
        ], 200);
    }
}
