<?php

namespace App\Http\Modulos\Recepcion\RPA\EtlEmailsProcesamientoManual;

use ZipArchive;
use Ramsey\Uuid\Uuid;
use App\Traits\DiTrait;
use Illuminate\Http\Request;
use App\Traits\RecepcionTrait;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use App\Http\Modulos\Recepcion\RPA\EtlEmailsProcesamientoManual\EtlEmailProcesamientoManual;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

class EtlEmailProcesamientoManualController extends Controller {

    use RecepcionTrait;

    /**
     * Propiedad Contiene las datos del usuario autenticado.
     *
     * @var Object
     */
    protected $user;

    /**
     * Base de datos permitidas.
     *
     * @var Array
     */
    public $bddPermitidas = [
        2, 6, 183, 184, 185
    ];

    /**
     * Arreglo para almacenar los errores.
     *
     * @var Array
     */
    public $arrErrors = [];

    /**
     * Constructor
     */
    public function __construct() {
        $this->middleware(['jwt.auth', 'jwt.refresh']);

        $this->middleware([
            'VerificaMetodosRol:RecepcionCorreosRecibidosDescargar'
        ])->only([
            'descargarAnexosCorreo'
        ]);

        $this->middleware([
            'VerificaMetodosRol:RecepcionCorreosRecibidosAsociarAnexos'
        ])->only([
            'asociarAnexosCorreoDocumento'
        ]);
    }

    /**
     * Descarga los anexos de un correo recibido.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function descargarAnexosCorreo(Request $request) {
        $autorizado = $this->validarUsuarioAuth();
        if(!$autorizado) {
            return response()->json([
                'message' => 'Error al procesar la información',
                'errors' => ['No tiene permisos para la acción que intenta ejecutar']
            ], 401);
        } else {
            $consulta = EtlEmailProcesamientoManual::select(['epm_id', 'ofe_identificacion', 'epm_id_carpeta', 'epm_subject'])
                ->where('epm_id', $request->epm_id)
                ->first();

            if(!$consulta)
                return response()->json([
                    'message' => 'Error al procesar la información',
                    'errors' => ['El correo recibido que intenta procesar no existe']
                ], 404);

            $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'ofe_identificacion', 'ofe_recepcion_fnc_configuracion'])
                ->where('ofe_identificacion', $consulta->ofe_identificacion)
                ->where('ofe_recepcion_fnc_activo', 'SI')
                ->validarAsociacionBaseDatos()
                ->where('estado', 'ACTIVO')
                ->first();

            if(!$ofe)
                return response()->json([
                    'message' => 'Error al procesar la información',
                    'errors' => ['El OFE [' . $consulta->ofe_identificacion . '] asociado al correo no existe, se encuentra inactivo o no tiene activado el proceso de recepción mediante correos']
                ], 404);

            if (empty($ofe->ofe_recepcion_fnc_configuracion))
                return response()->json([
                    'message' => 'Error al procesar la información',
                    'errors' => ['El OFE [' . $consulta->ofe_identificacion . '] asociado al correo no tiene configurada la información para el proceso de recepción mediante correos']
                ], 400);

            $rutas = json_decode($ofe->ofe_recepcion_fnc_configuracion);
            
            // Verificando ruta principal donde se cargan los anexos
            if (!File::exists($rutas->directorio)) {
                return response()->json([
                    'message' => 'Error al procesar la información',
                    'errors' => ['Verifique la configuración de la información para el proceso de recepción mediante correos del OFE [' . $consulta->ofe_identificacion . ']']
                ], 400);
            }

            // Archivos que serán descartados en el proceso
            $arrArchivosDescartar = [
                'correo_completo.txt',
                'cuerpo_correo.txt',
                'fecha.txt',
                'subject.txt',
                'undefined'
            ];

            // Extensiones válidas para descargar
            $arrExtensionValida = [
                'xml',
                'jpg',
                'jpeg',
                'pdf',
                'tiff',
                'doc',
                'docx',
                'xls',
                'xlsx'
            ];

            $arrArchivosZipEliminar  = [];
            $arrErroresPorDirectorio = [];
            $zipDescomprimidoTotal   = true;
            $discoCorreos            = $this->user->getBaseDatos->bdd_nombre;
            $idCarpeta               = $consulta->epm_id_carpeta;
            $pathDirPrincipal        = $rutas->directorio . '/' . $this->user->getBaseDatos->bdd_nombre . '/' . $consulta->ofe_identificacion . '/' . $rutas->fallidos;
            $arrZip                  = glob($pathDirPrincipal . '/' . $idCarpeta . '/*.zip');
            $arrPrefijoConsecutivo   = explode(";", $consulta->epm_subject);

            if(count($arrPrefijoConsecutivo) >= 5)
                $prefijoConsecutivo = $arrPrefijoConsecutivo[2];
            else
                $prefijoConsecutivo = $consulta->epm_id_carpeta;

            // Inicia el proceso de descompresión recursiva
            foreach($arrZip as $zip) {
                $arrNombreZip = explode('/', $zip);
                if(!in_array($arrNombreZip[count($arrNombreZip) - 1], $arrArchivosDescartar)) {
                    $arrArchivosDescartar[] = $arrNombreZip[count($arrNombreZip) - 1];
                    $unzip = $this->unzip(
                        $prefijoConsecutivo,
                        $consulta->epm_subject,
                        $zip,
                        $pathDirPrincipal,
                        $idCarpeta,
                        $zipDescomprimidoTotal,
                        $arrArchivosZipEliminar,
                        $rutas,
                        $arrArchivosDescartar,
                        $arrErroresPorDirectorio
                    );
                    $zipDescomprimidoTotal   = $unzip['zipDescomprimidoTotal'];
                    $arrArchivosZipEliminar  = $unzip['arrArchivosZipEliminar'];
                    $arrArchivosDescartar    = $unzip['arrArchivosDescartar'];
                    $arrErroresPorDirectorio = $unzip['arrErroresPorDirectorio'];
                }
            }

            DiTrait::crearDiscoDinamico($discoCorreos, $pathDirPrincipal);
            $arrFilesDescarga = Storage::disk($discoCorreos)->files($idCarpeta);
            $filesZip = [];
            foreach ($arrFilesDescarga as $file) {
                $fileBaseName = explode('/', $file);
                if(!in_array($fileBaseName[count($fileBaseName) - 1], $arrArchivosDescartar)) {
                    $fileExtension = explode(".", $fileBaseName[count($fileBaseName) - 1]);
                    if(in_array($fileExtension[1], $arrExtensionValida))
                        $filesZip[] = $file;
                }
            }
            $filesZipDelete = [];
            foreach ($arrArchivosZipEliminar as $file) {
                $arrFile = explode('/', $file);
                foreach ($arrFile as $key => $value) {
                    if($value === $idCarpeta) {
                        $filesZip[]       = implode('/', array_slice($arrFile, $key));
                        $filesZipDelete[] = implode('/', array_slice($arrFile, $key));
                    }
                }
            }

            if(!empty($filesZip)) {
                $nombreZip = Uuid::uuid4()->toString() . '.zip';

                // Inicia la creación del archivo .zip
                $oZip = new \ZipArchive;
                $oZip->open(storage_path('etl/anexos_correo/' . $nombreZip), ZipArchive::OVERWRITE | ZipArchive::CREATE);
                foreach($filesZip as $file) {
                    $arrRuta = explode('/', $file);
                    if(file_exists($pathDirPrincipal . '/' . $file) && is_readable($pathDirPrincipal . '/' . $file)) {
                        $oZip->addFile($pathDirPrincipal . '/' . $file, $arrRuta[count($arrRuta) - 1]);
                    }
                }
                $result = $oZip->close();
                // Elimina los archivos descomprimidos
                $execute = Storage::disk($discoCorreos)->delete($filesZipDelete);

                if(!$result)
                    return response()->json([
                        'message' => 'Error al generar la descarga',
                        'errors' => ['El archivo no logra generarse correctamente']
                    ], 404);

                $headers = [
                    header('Access-Control-Expose-Headers: Content-Disposition')
                ];

                return response()->download(storage_path('etl/anexos_correo/' . $nombreZip), Uuid::uuid4()->toString().'.zip', $headers)
                    ->deleteFileAfterSend(true);
            } else {
                // Al obtenerse la respuesta en el frontend en un Observable de tipo Blob se deben agregar 
                // headers en la respuesta del error para poder mostrar explicitamente al usuario el error que ha ocurrido
                $headers = [
                    header('Access-Control-Expose-Headers: X-Error-Status, X-Error-Message'),
                    header('X-Error-Status: 422'),
                    header('X-Error-Message: El correo no tiene arhivos para descargar')
                ];
                return response()->json([
                    'message' => 'Error al finalizar el proceso',
                    'errors' => ['El correo no tiene arhivos para descargar']
                ], 404);
            }
        }
    }

    /**
     * Asocia los anexos de un correo con un documento.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function asociarAnexosCorreoDocumento(Request $request) {
        $autorizado = $this->validarUsuarioAuth();
        $this->arrErrors = [];
        if(!$autorizado) {
            return response()->json([
                'message' => 'Error al procesar la información',
                'errors' => ['No tiene permisos para la acción que intenta ejecutar']
            ], 401);
        } else {
            if(!$request->filled('epm_id'))
                $this->arrErrors[] = 'Debe enviar el ID del correo al que intenta asociar los anexos';

            if(!$request->filled('cdo_id'))
                $this->arrErrors[] = 'Debe enviar el ID del documento al que intenta asociar los anexos';

            if(!empty($this->arrErrors)) {
                return response()->json([
                    'message' => 'Error al procesar la información',
                    'errors'  => $this->arrErrors
                ], 404);
            }

            return $this->asociarAnexosCorreo($request->epm_id, $request->cdo_id, $this->user);
        }
    }

    /**
     * Valida si el usuario autenticado está relacionado a las bases de datos permitidas.
     *
     * @return bool
     */
    public function validarUsuarioAuth() {
        $this->user = auth()->user();
        if($this->user->bdd_id_rg !== null && $this->user->bdd_id_rg !== '')
            $bdd = $this->user->bdd_id_rg;
        else
            $bdd = $this->user->bdd_id;

        return in_array($bdd, $this->bddPermitidas);
    }
}
