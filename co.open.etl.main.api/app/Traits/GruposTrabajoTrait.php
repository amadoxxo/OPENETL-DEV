<?php

namespace App\Traits;

use Mail;
use App\Http\Models\User;
use App\Traits\MainTrait;
use Illuminate\Support\Facades\File;
use openEtl\Tenant\Traits\TenantTrait;
use Illuminate\Support\Facades\Storage;
use App\Http\Modulos\Configuracion\GruposTrabajo\GruposTrabajo\ConfiguracionGrupoTrabajo;
use App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaop;
use App\Http\Modulos\Configuracion\GruposTrabajo\GruposTrabajoUsuarios\ConfiguracionGrupoTrabajoUsuario;
use App\Http\Modulos\Configuracion\GruposTrabajo\GruposTrabajoProveedores\ConfiguracionGrupoTrabajoProveedor;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

trait GruposTrabajoTrait {
    /**
     * Ubica el Logo del OFE en el proceso de recepción.
     * 
     * El logo en el proceso de recepción es el utilizado en la notificación de eventos y en las notificaciones de asociaciones de usuarios y proveedores a grupos de trabajo
     *
     * @param array $dataEmail Array con la información a enviar en el email
     * @param string $ofe_identificacion Identificación del OFE
     * @return void
     */
    public function logoNotificacionAsociacion(array &$dataEmail, string $ofe_identificacion): void {
        if(!empty(auth()->user()->bdd_id_rg)) {
            $baseDatos = auth()->user()->getBaseDatosRg->bdd_nombre;
        } else {
            $baseDatos = auth()->user()->getBaseDatos->bdd_nombre;
        }
        
        MainTrait::setFilesystemsInfo();
        $directorio = Storage::disk(config('variables_sistema.ETL_LOGOS_STORAGE'))->getDriver()->getAdapter()->getPathPrefix();
        $logoEvento = $directorio . $baseDatos . '/' . $ofe_identificacion . '/assets/' . 'logoevento' . $ofe_identificacion . '.png';

        if(File::exists($logoEvento))
            $dataEmail['ofe_logo'] = $logoEvento;
        else
            $dataEmail['ofe_logo'] = '';
    }
    
    /**
     * Permite realizar el envío de correo que notifica la asociación de un proveedor a un grupo de trabajo.
     *
     * @param Array  $datos Información para el envío de correo
     */
    public function notificarProveedorAsociado($datos) {
        $dataEmail = [
            "ofe_razon_social"     => $datos['ofe_razon_social'],
            "pro_identificacion"   => $datos['pro_identificacion'],
            "pro_razon_social"     => $datos['pro_razon_social'],
            "grupo_trabajo"        => $datos['grupo_trabajo'],
            "nombre_grupo_trabajo" => $datos['gtr_nombre'],
            "app_url"              => config('variables_sistema.APP_URL_WEB'),
            "remite"               => config('variables_sistema.EMPRESA'),
            "direccion"            => config('variables_sistema.DIRECCION'),
            "ciudad"               => config('variables_sistema.CIUDAD'),
            "telefono"             => config('variables_sistema.TELEFONO'),
            "web"                  => config('variables_sistema.WEB'),
            "email"                => config('variables_sistema.EMAIL'),
            "facebook"             => config('variables_sistema.FACEBOOK'),
            "twitter"              => config('variables_sistema.TWITTER')
        ];

        // Valida si llega por la acción de crear proveedor para cambiar el formato del correo
        if (isset($datos['crear_proveedor']) && $datos['crear_proveedor'] == true) {
            $dataEmail['crear_proveedor'] = true;
            $subject = 'Proveedor creado en openETL';
        } else {
            $subject = 'Asociación como proveedor de ' . $datos['ofe_razon_social'] . ' (' . $datos['ofe_identificacion'] . ')';
        }
        
        $this->logoNotificacionAsociacion($dataEmail, $datos['ofe_identificacion']);

        $arrCorreosNotificacion = [];
        if ($datos['pro_correo'] != null && $datos['pro_correo'] != '') {
            $arrCorreosNotificacion = explode(',', $datos['pro_correo']);
        }

        // Establece el email del remitente del correo
        $emailRemitente = (!empty(config('variables_sistema.MAIL_FROM_ADDRESS'))) ?  config('variables_sistema.MAIL_FROM_ADDRESS') : $datos['ofe_correo'];

        if (!empty($arrCorreosNotificacion)) {
            foreach ($arrCorreosNotificacion as $correo) {
                MainTrait::setMailInfo();
                Mail::send(
                    'emails.proveedorAsociado',
                    $dataEmail,
                    function ($message) use ($datos, $subject, $correo, $emailRemitente){
                        $message->from($emailRemitente, $datos['ofe_razon_social'] . ' (' . $datos['ofe_identificacion'] . ')');
                        $message->sender($emailRemitente, $datos['ofe_razon_social']);
                        $message->subject($subject);
                        $message->to($correo, $datos['pro_razon_social']);
                    }
                );
            }
        }
    }

    /**
     * Realiza el envío de correo que notifica a los usuarios que pertenecen al grupo de trabajo que fue asociado el proveedor y
     * a los correos de notificación que tenga parametrizado el grupo de trabajo.
     *
     * @param Array  $datos Información para el envío de correo
     */
    public function notificarUsuariosAsociados($datos) {
        $dataEmail = [
            "ofe_razon_social"     => $datos['ofe_razon_social'],
            "pro_identificacion"   => $datos['pro_identificacion'],
            "pro_razon_social"     => $datos['pro_razon_social'],
            "grupo_trabajo"        => $datos['grupo_trabajo'],
            "nombre_grupo_trabajo" => $datos['gtr_nombre'],
            "app_url"              => config('variables_sistema.APP_URL_WEB'),
            "remite"               => config('variables_sistema.EMPRESA'),
            "direccion"            => config('variables_sistema.DIRECCION'),
            "ciudad"               => config('variables_sistema.CIUDAD'),
            "telefono"             => config('variables_sistema.TELEFONO'),
            "web"                  => config('variables_sistema.WEB'),
            "email"                => config('variables_sistema.EMAIL'),
            "facebook"             => config('variables_sistema.FACEBOOK'),
            "twitter"              => config('variables_sistema.TWITTER'),
            "notificar_usuarios"   => true
        ];

        // Se realiza la notificación a los usuarios que pertenecen al grupo de trabajo
        if (isset($datos['grupo_por_defecto']) && $datos['grupo_por_defecto'] == false) {
            $dataEmail['grupo_por_defecto'] = false;
        }

        $this->logoNotificacionAsociacion($dataEmail, $datos['ofe_identificacion']);
        
        $arrCorreosUsuarios = [];
        ConfiguracionGrupoTrabajoUsuario::where('gtr_id', $datos['gtr_id'])
            ->where('estado', 'ACTIVO')
            ->get()
            ->map(function ($usuarioAsociado) use (&$arrCorreosUsuarios) {
                $usuario = User::select(['usu_id', 'usu_email'])
                    ->where('usu_id', $usuarioAsociado->usu_id)
                    ->where('estado', 'ACTIVO')
                    ->first();

                if ($usuario) {
                    $arrCorreosUsuarios[] = $usuario->usu_email;
                }   
            });

        // Se realiza la notificación a los correos parametrizados en el grupo de trabajo
        $grupoTrabajo = ConfiguracionGrupoTrabajo::select(['gtr_id', 'gtr_correos_notificacion'])
            ->where('gtr_id', $datos['gtr_id'])
            ->where('estado', 'ACTIVO')
            ->first();

        $arrCorreos = [];
        if ($grupoTrabajo->gtr_correos_notificacion != null && $grupoTrabajo->gtr_correos_notificacion != '') {
            $arrCorreos = explode(',', $grupoTrabajo->gtr_correos_notificacion);
        }

        $arrCorreosNotificacion = array_merge($arrCorreosUsuarios, $arrCorreos);
        $arrCorreosNotificacion = array_unique($arrCorreosNotificacion);

        // Establece el email del remitente del correo
        $emailRemitente = (!empty(config('variables_sistema.MAIL_FROM_ADDRESS'))) ?  config('variables_sistema.MAIL_FROM_ADDRESS') : $datos['ofe_correo'];

        if (!empty($arrCorreosNotificacion)) {
            foreach ($arrCorreosNotificacion as $correo) {
                MainTrait::setMailInfo();
                Mail::send(
                    'emails.proveedorAsociado',
                    $dataEmail,
                    function ($message) use ($datos, $correo, $emailRemitente){
                        $message->from($emailRemitente, $datos['ofe_razon_social'] . ' (' . $datos['ofe_identificacion'] . ')');
                        $message->sender($emailRemitente, $datos['ofe_razon_social']);
                        $message->subject('Asociación de proveedor');
                        $message->to($correo);
                    }
                );
            }
        }
    }

    /**
     * Permite realizar la asociación del proveedor a un grupo de trabajo por defecto y realizar la notificación de los correos.
     *
     * @param array $data Información del proveedor
     * @param bool  $create Indica si el proveedor se va a crear
     */
    public function asociarProveedorGrupoTrabajo(array $data, bool $create = true) {
        // Usuario autenticado
        $this->user = auth()->user();
        // Indica si el proveedor ya esta asociado a un grupo de trabajo
        $proveedorAsociado = false;

        $ofe = ConfiguracionObligadoFacturarElectronicamente::select([
                'ofe_id',
                'ofe_identificacion',
                'ofe_correo',
                'ofe_recepcion_fnc_activo',
                \DB::raw('IF(ofe_razon_social IS NULL OR ofe_razon_social = "", CONCAT(COALESCE(ofe_primer_nombre, ""), " ", COALESCE(ofe_otros_nombres, ""), " ", COALESCE(ofe_primer_apellido, ""), " ", COALESCE(ofe_segundo_apellido, "")), ofe_razon_social) as nombre_completo')
            ])
            ->where('ofe_id', $data['ofe_id'])
            ->first();

        // Se obtiene el grupo de trabajo que tiene el OFE por defecto
        $grupoTrabajo = ConfiguracionGrupoTrabajo::select(['gtr_id', 'gtr_codigo', 'gtr_nombre', 'gtr_correos_notificacion'])
            ->where('ofe_id', $ofe->ofe_id)
            ->where('gtr_por_defecto', 'SI')
            ->where('estado', 'ACTIVO')
            ->first();

        // Si el OFE no tiene grupo de trabajo por defecto y se va crear un proveedor se busca el primer grupo ACTIVO
        if (!$grupoTrabajo && $create) {
            $datosCorreo['grupo_por_defecto'] = false;
            $grupoTrabajo = ConfiguracionGrupoTrabajo::select(['gtr_id', 'gtr_codigo', 'gtr_nombre', 'gtr_correos_notificacion'])
                ->where('ofe_id', $ofe->ofe_id)
                ->where('estado', 'ACTIVO')
                ->first();
        }

        if(!$create) {
            $consultaGtp = ConfiguracionGrupoTrabajoProveedor::select('gtp_id')
                ->where('pro_id', $data['pro_id'])
                ->where('estado', 'ACTIVO')
                ->first();

            $proveedorAsociado = ($consultaGtp) ? true : false;
        }

        if ($grupoTrabajo && !$proveedorAsociado) {
            // Si el grupo tiene correos de notificación parmétrizados o usuarios asociados, se permite realizar la asociación del proveedor
            $usuariosAsociados = ConfiguracionGrupoTrabajoUsuario::select('gtu_id')
                ->where('gtr_id', $grupoTrabajo->gtr_id)
                ->where('estado', 'ACTIVO')
                ->first();

            $dataCreate['gtr_id']           = $grupoTrabajo->gtr_id;
            $dataCreate['pro_id']           = $data['pro_id'];
            $dataCreate['estado']           = 'ACTIVO';
            $dataCreate['usuario_creacion'] = $this->user->usu_id;
            $objGrupo = ConfiguracionGrupoTrabajoProveedor::create($dataCreate);

            if ($objGrupo && (($grupoTrabajo->gtr_correos_notificacion != null && $grupoTrabajo->gtr_correos_notificacion != '') || $usuariosAsociados)) {
                TenantTrait::GetVariablesSistemaTenant();
                $arrVariableSistema = json_decode(config('variables_sistema_tenant.NOMBRE_GRUPOS_TRABAJO'), true);

                if (config('variables_sistema_tenant.NOTIFICAR_ASIGNACION_GRUPO_TRABAJO') == 'SI') {
                    $datosCorreo['ofe_identificacion'] = $ofe->ofe_identificacion;
                    $datosCorreo['ofe_correo']         = $ofe->ofe_correo;
                    $datosCorreo['ofe_razon_social']   = $ofe->nombre_completo;
                    $datosCorreo['gtr_nombre']         = $grupoTrabajo->gtr_nombre;
                    $datosCorreo['pro_identificacion'] = $data['pro_identificacion'];
                    $datosCorreo['pro_razon_social']   = $data['pro_razon_social'];
                    $datosCorreo['pro_correo']         = $data['pro_correo'];
                    $datosCorreo['grupo_trabajo']      = ucwords($arrVariableSistema['singular']);
                    $datosCorreo['gtr_id']             = $grupoTrabajo->gtr_id;

                    // Se envía el correo de notificación con la información del proveedor asociado al grupo de trabajo
                    if ($data['pro_correo'] != '' && $data['pro_correo'] != null && $ofe->ofe_recepcion_fnc_activo == 'SI') {
                        $datosCorreo['crear_proveedor'] = true;
                        $this->notificarProveedorAsociado($datosCorreo);
                    }

                    // Se envía el correo de notificación a los usuarios asociados al grupo de trabajo y los correos de notificación parametrizados al grupo de trabajo
                    if ($ofe->ofe_recepcion_fnc_activo == 'SI')
                        $this->notificarUsuariosAsociados($datosCorreo);
                }
            }
        }
    }

    /**
     * Notifica mediante email a los integrates de un grupo de trabajo, cuando un documento electrónico es asociado al grupo.
     *
     * @param RepCabeceraDocumentoDaop $documentoRecepcion Instancia del documento electrónico creado en recepción
     * @param int $gtr_id ID del grupo de trabajo para el cual se notificará a los usuarios
     * @return void
     */
    public function notificarDocumentoAsociadoGrupoTrabajo(RepCabeceraDocumentoDaop $documentoRecepcion, int $gtr_id): void {
        $dataEmail = [
            "ofe_razon_social"       => $documentoRecepcion->getConfiguracionObligadoFacturarElectronicamente->ofe_razon_social,
            "pro_identificacion"     => $documentoRecepcion->getConfiguracionProveedor->pro_identificacion,
            "pro_razon_social"       => $documentoRecepcion->getConfiguracionProveedor->pro_razon_social,
            "tde_codigo_descripcion" => ($documentoRecepcion->cdo_origen != "NO-ELECTRONICO") ? $documentoRecepcion->getTipoDocumentoElectronico->tde_codigo . ' ' . $documentoRecepcion->getTipoDocumentoElectronico->tde_descripcion : $documentoRecepcion->cdo_origen,
            "rfa_prefijo"            => $documentoRecepcion->rfa_prefijo,
            "cdo_consecutivo"        => $documentoRecepcion->cdo_consecutivo,
            "cdo_fecha"              => $documentoRecepcion->cdo_fecha,
            "app_url"                => config('variables_sistema.APP_URL_WEB'),
            "remite"                 => config('variables_sistema.EMPRESA'),
            "direccion"              => config('variables_sistema.DIRECCION'),
            "ciudad"                 => config('variables_sistema.CIUDAD'),
            "telefono"               => config('variables_sistema.TELEFONO'),
            "web"                    => config('variables_sistema.WEB'),
            "email"                  => config('variables_sistema.EMAIL'),
            "facebook"               => config('variables_sistema.FACEBOOK'),
            "twitter"                => config('variables_sistema.TWITTER')
        ];

        $this->logoNotificacionAsociacion($dataEmail, $documentoRecepcion->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion);

        TenantTrait::GetVariablesSistemaTenant();
        $arrVariableSistema  = json_decode(config('variables_sistema_tenant.NOMBRE_GRUPOS_TRABAJO'), true);
        $nombreGruposTrabajo = ucwords($arrVariableSistema['singular']);

        $grupoTrabajo = ConfiguracionGrupoTrabajo::find($gtr_id);

        $dataEmail['grupoTrabajo']        = $grupoTrabajo->gtr_nombre;
        $dataEmail['nombreGruposTrabajo'] = $nombreGruposTrabajo;
        
        $destinatarios = [];
        ConfiguracionGrupoTrabajoUsuario::select('usu_id')
            ->where('gtr_id', $gtr_id)
            ->where('estado', 'ACTIVO')
            ->get()
            ->map(function ($usuarioAsociado) use (&$destinatarios) {
                $usuario = User::select(['usu_email'])
                    ->where('usu_id', $usuarioAsociado->usu_id)
                    ->where('estado', 'ACTIVO')
                    ->first();

                if ($usuario) {
                    $destinatarios[] = $usuario->usu_email;
                }
            });

        // Establece el email del remitente del correo
        $emailRemitente = (!empty(config('variables_sistema.MAIL_FROM_ADDRESS'))) ?  config('variables_sistema.MAIL_FROM_ADDRESS') : $documentoRecepcion->getConfiguracionObligadoFacturarElectronicamente->ofe_correo;

        $destinatarios = array_unique($destinatarios);
        MainTrait::setMailInfo();
        foreach ($destinatarios as $correo) {
            Mail::send(
                'emails.documentoElectronicoAsociado',
                $dataEmail,
                function ($message) use ($documentoRecepcion, $correo, $nombreGruposTrabajo, $grupoTrabajo, $emailRemitente){
                    $message->from(
                        $emailRemitente,
                        $documentoRecepcion->getConfiguracionObligadoFacturarElectronicamente->ofe_razon_social . ' (' . $documentoRecepcion->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion . ')'
                    );
                    $message->sender($emailRemitente, $documentoRecepcion->getConfiguracionObligadoFacturarElectronicamente->ofe_razon_social);
                    $message->subject('Documento electrónico ' . $documentoRecepcion->rfa_prefijo . $documentoRecepcion->cdo_consecutivo . ' asociado a el (la) ' . $nombreGruposTrabajo  . ' ' . $grupoTrabajo->gtr_nombre);
                    $message->to($correo);
                }
            );
        }
    }

    /**
     * Obiene los grupos de usuario de usuario del usuario autenticado.
     *
     * @param ConfiguracionObligadoFacturarElectronicamente $ofe Instancia del OFE
     * @param bool $usuarioGestor Indica cuando se debe tener en cuenta que se trate de un usuario gestor
     * @param bool $usuarioValidador Indica cuando se debe tener en cuenta que se trate de un usuario validador
     * @return array
     */
    public function getGruposTrabajoUsuarioAutenticado(ConfiguracionObligadoFacturarElectronicamente $ofe, bool $usuarioGestor = false, bool $usuarioValidador = false): array {
        return ConfiguracionGrupoTrabajoUsuario::select(['gtr_id'])
            ->where('usu_id', auth()->user()->usu_id)
            ->when($usuarioGestor && $ofe->ofe_recepcion_fnc_activo == 'SI', function($query) {
                return $query->where('gtu_usuario_gestor', 'SI');
            })
            ->when($usuarioValidador && $ofe->ofe_recepcion_fnc_activo == 'SI', function($query) {
                return $query->where('gtu_usuario_validador', 'SI');
            })
            ->where('estado', 'ACTIVO')
            ->get()
            ->pluck('gtr_id')
            ->toArray();
    }
}
