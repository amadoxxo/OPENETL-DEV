<?php

namespace App\Http\Modulos\Recepcion\RPA\EtlEmailsProcesamientoManual;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\OpenBaseController;
use App\Http\Modulos\Recepcion\Configuracion\Proveedores\ConfiguracionProveedor;
use App\Http\Modulos\Recepcion\RPA\EtlEmailsProcesamientoManual\EtlEmailProcesamientoManual;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

class EtlEmailProcesamientoManualController extends OpenBaseController {
    /**
     * Nombre del modelo en singular.
     *
     * @var String
     */
    public $nombre = 'Correo Recibido';

    /**
     * Nombre del modelo en plural.
     *
     * @var String
     */
    public $nombrePlural = 'Correos Recibidos';

    /**
     * Modelo relacionado a la paramétrica.
     *
     * @var Illuminate\Database\Eloquent\Model
     */
    public $className = EtlEmailProcesamientoManual::class;

    /**
     * Mensaje de error cuando el objeto no existe.
     *
     * @var String
     */
    public $mensajeObjectNotFound = 'El Id del Correo Recibido [%s] no Existe';

    /**
     * Mensaje de error cuando el objeto no existe.
     *
     * @var String
     */
    public $mensajeObjectDisabled = 'El Id del Correo Recibido [%s] esta Inactivo';

    /**
     * Propiedad para almacenar los errores.
     *
     * @var Array
     */
    protected $errors = [];

    /**
     * Propiedad Contiene las datos del usuario autenticado.
     *
     * @var Object
     */
    protected $user;

    /**
     * Llave primaria de la tabla.
     *
     * @var String
     */
    public $nombreCampoIdentificacion = 'epm_id';

    /**
     * Base de datos permitidas.
     *
     * @var Array
     */
    public $bddPermitidas = [
        4, 2, 6, 183, 184, 185
    ];

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();
        $this->middleware(['jwt.auth', 'jwt.refresh']);

        $this->middleware([
            'VerificaMetodosRol:RecepcionCorreosRecibidos,RecepcionCorreosRecibidosVer'
        ])->except([
            'show',
            'getListaCorreosRecibidos',
        ]);

        $this->middleware([
            'VerificaMetodosRol:RecepcionCorreosRecibidosVer'
        ])->only([
            'show'
        ]);
    }

    /**
     * Muestra solo un registro.
     * 
     * @param  int  $id ID del registro a retornar
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse {
        $autorizado = $this->validarUsuarioAuth();

        if($autorizado) {
            $proId     = null;
            $proveedor = null;

            $correoRecibido    = EtlEmailProcesamientoManual::with('getUsuarioCreacion')->find($id);
            $objCorreoRecibido = $correoRecibido->toArray();
            $arrSubject        = explode(';', $correoRecibido->epm_subject);

            if(count($arrSubject) >= 5)
                $proId = ($arrSubject[0] === 'Soporte') ? $arrSubject[1] : $arrSubject[0];

            $oferente = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'ofe_identificacion', \DB::raw('IF(ofe_razon_social IS NULL OR ofe_razon_social = "", CONCAT(COALESCE(ofe_primer_nombre, ""), " ", COALESCE(ofe_otros_nombres, ""), " ", COALESCE(ofe_primer_apellido, ""), " ", COALESCE(ofe_segundo_apellido, "")), ofe_razon_social) as nombre_completo')])
                ->where('ofe_identificacion', $correoRecibido->ofe_identificacion)
                ->where('estado', 'ACTIVO')
                ->first();

            if($proId)
                $proveedor = ConfiguracionProveedor::select(['pro_id', 'pro_identificacion', \DB::raw('IF(pro_razon_social IS NULL OR pro_razon_social = "", CONCAT(COALESCE(pro_primer_nombre, ""), " ", COALESCE(pro_otros_nombres, ""), " ", COALESCE(pro_primer_apellido, ""), " ", COALESCE(pro_segundo_apellido, "")), pro_razon_social) as nombre_completo')])
                    ->where('ofe_id', $oferente->ofe_id)
                    ->where('pro_identificacion', $proId)
                    ->where('estado', 'ACTIVO')
                    ->first();

            $objCorreoRecibido['ofe_identificacion_nombre_completo'] = $oferente->ofe_identificacion . ' - ' . $oferente->nombre_completo;

            if($proveedor)
                $objCorreoRecibido['pro_identificacion_nombre_completo'] = $proveedor->pro_identificacion. ' - ' . $proveedor->nombre_completo;
            else
                $objCorreoRecibido['pro_identificacion_nombre_completo'] = null;

            return response()->json([
                'data' => $objCorreoRecibido
            ], 200);
        } else
            return response()->json([
                'message' => 'Error al procesar la información',
                'errors' => ['No tiene permisos para la acción que intenta ejecutar']
            ], 409);
    }

    /**
     * Lista los registros según la información enviada en el request.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getListaCorreosRecibidos(Request $request) {
        $autorizado = $this->validarUsuarioAuth();

        if(!$autorizado) {
            return response()->json([
                'message' => 'Error al procesar la información',
                'errors' => ['No tiene permisos para la acción que intenta ejecutar']
            ], 401);
        } else {
            $condiciones = [
                'filters' => [
                    'AND' => [
                        ['ofe_identificacion', '=', $request->ofe_identificacion],
                        ['epm_procesado', '=', $request->procesado]
                    ]
                ]
            ];
            $columnas = [
                'epm_id',
                'ofe_identificacion', 
                'epm_subject',
                'epm_procesado',
                'epm_fecha_correo'
            ];

            return $this->procesadorTracking($request, $condiciones, $columnas, [], [], false, [], 'email_procesamiento_manual');
        }
    }

    /**
     * Valida si el usuario autenticado está relacionado a las bases de datos permitidas.
     *
     * @return Boolean
     */
    public function validarUsuarioAuth() {
        $user   = auth()->user();
        if($user->bdd_id !== null && $user->bdd_id !== '')
            $bdd = $user->bdd_id;
        else
            $bdd = $user->bdd_id_rg;

        return (in_array($bdd, $this->bddPermitidas)) ? true : false;
    }
}
