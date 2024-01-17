<?php

namespace App\Console\Commands\ProcesosDataBase\p2023;

use App\Traits\comandosTrait;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class C2023_11_17_103115_CreaRecursosGestionDocumentosCommand extends Command {
    use comandosTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crea-recursos-gestion-documentos-2023-11-17';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crea los recursos del proyecto especial Gestion de Documentos';

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
        $inserts = [
            // Recursos para Configuracion > Recepcion > Centro de Costos
            [ 
                'rec_alias'              => 'ConfiguracionCentrosCosto',
                'rec_modulo'             => 'Configuracion', 
                'rec_controlador'        => 'CentroCostoController', 
                'rec_accion'             => 'lista', 
                'rec_modulo_descripcion' => 'Configuracion',
                'rec_descripcion'        => 'Configuracion Centro de Costos',
                'usuario_creacion'       => '1',
                'estado'                 => 'ACTIVO'
            ],
            [ 
                'rec_alias'              => 'ConfiguracionCentrosCostoVer',
                'rec_modulo'             => 'Configuracion', 
                'rec_controlador'        => 'CentroCostoController', 
                'rec_accion'             => 'ver', 
                'rec_modulo_descripcion' => 'Configuracion',
                'rec_descripcion'        => 'Ver Centro de Costos',
                'usuario_creacion'       => '1',
                'estado'                 => 'ACTIVO'
            ],
            [ 
                'rec_alias'              => 'ConfiguracionCentrosCostoNuevo',
                'rec_modulo'             => 'Configuracion', 
                'rec_controlador'        => 'CentroCostoController', 
                'rec_accion'             => 'nuevo', 
                'rec_modulo_descripcion' => 'Configuracion',
                'rec_descripcion'        => 'Nuevo Centro de Costos',
                'usuario_creacion'       => '1',
                'estado'                 => 'ACTIVO'
            ],
            [ 
                'rec_alias'              => 'ConfiguracionCentrosCostoEditar',
                'rec_modulo'             => 'Configuracion', 
                'rec_controlador'        => 'CentroCostoController', 
                'rec_accion'             => 'editar', 
                'rec_modulo_descripcion' => 'Configuracion',
                'rec_descripcion'        => 'Editar Centro de Costos',
                'usuario_creacion'       => '1',
                'estado'                 => 'ACTIVO'
            ],
            [ 
                'rec_alias'              => 'ConfiguracionCentrosCostoCambiarEstado',
                'rec_modulo'             => 'Configuracion', 
                'rec_controlador'        => 'CentroCostoController', 
                'rec_accion'             => 'cambiarEstado', 
                'rec_modulo_descripcion' => 'Configuracion',
                'rec_descripcion'        => 'Cambiar Estado Centro de Costos',
                'usuario_creacion'       => '1',
                'estado'                 => 'ACTIVO'
            ],
            // Recursos para Configuracion > Recepcion > Causales Devolución
            [ 
                'rec_alias'              => 'ConfiguracionCausalesDevolucion',
                'rec_modulo'             => 'Configuracion', 
                'rec_controlador'        => 'CausalDevolucionController', 
                'rec_accion'             => 'lista', 
                'rec_modulo_descripcion' => 'Configuracion',
                'rec_descripcion'        => 'Configuracion Causales Devolución',
                'usuario_creacion'       => '1',
                'estado'                 => 'ACTIVO'
            ],
            [ 
                'rec_alias'              => 'ConfiguracionCausalesDevolucionVer',
                'rec_modulo'             => 'Configuracion', 
                'rec_controlador'        => 'CausalDevolucionController', 
                'rec_accion'             => 'ver', 
                'rec_modulo_descripcion' => 'Configuracion',
                'rec_descripcion'        => 'Ver Causales Devolución',
                'usuario_creacion'       => '1',
                'estado'                 => 'ACTIVO'
            ],
            [ 
                'rec_alias'              => 'ConfiguracionCausalesDevolucionNuevo',
                'rec_modulo'             => 'Configuracion', 
                'rec_controlador'        => 'CausalDevolucionController', 
                'rec_accion'             => 'nuevo', 
                'rec_modulo_descripcion' => 'Configuracion',
                'rec_descripcion'        => 'Nuevo Causales Devolución',
                'usuario_creacion'       => '1',
                'estado'                 => 'ACTIVO'
            ],
            [ 
                'rec_alias'              => 'ConfiguracionCausalesDevolucionEditar',
                'rec_modulo'             => 'Configuracion', 
                'rec_controlador'        => 'CausalDevolucionController', 
                'rec_accion'             => 'editar', 
                'rec_modulo_descripcion' => 'Configuracion',
                'rec_descripcion'        => 'Editar Causales Devolución',
                'usuario_creacion'       => '1',
                'estado'                 => 'ACTIVO'
            ],
            [ 
                'rec_alias'              => 'ConfiguracionCausalesDevolucionCambiarEstado',
                'rec_modulo'             => 'Configuracion', 
                'rec_controlador'        => 'CausalDevolucionController', 
                'rec_accion'             => 'cambiarEstado', 
                'rec_modulo_descripcion' => 'Configuracion',
                'rec_descripcion'        => 'Cambiar Estado Causales Devolución',
                'usuario_creacion'       => '1',
                'estado'                 => 'ACTIVO'
            ],
            // Recursos para Configuracion > Recepcion > Centros Operación
            [ 
                'rec_alias'              => 'ConfiguracionCentrosOperacion',
                'rec_modulo'             => 'Configuracion', 
                'rec_controlador'        => 'CentrosOperacionController', 
                'rec_accion'             => 'lista', 
                'rec_modulo_descripcion' => 'Configuracion',
                'rec_descripcion'        => 'Configuracion Centros Operación',
                'usuario_creacion'       => '1',
                'estado'                 => 'ACTIVO'
            ],
            [ 
                'rec_alias'              => 'ConfiguracionCentrosOperacionVer',
                'rec_modulo'             => 'Configuracion', 
                'rec_controlador'        => 'CentrosOperacionController', 
                'rec_accion'             => 'ver', 
                'rec_modulo_descripcion' => 'Configuracion',
                'rec_descripcion'        => 'Ver Centros Operación',
                'usuario_creacion'       => '1',
                'estado'                 => 'ACTIVO'
            ],
            [ 
                'rec_alias'              => 'ConfiguracionCentrosOperacionNuevo',
                'rec_modulo'             => 'Configuracion', 
                'rec_controlador'        => 'CentrosOperacionController', 
                'rec_accion'             => 'nuevo', 
                'rec_modulo_descripcion' => 'Configuracion',
                'rec_descripcion'        => 'Nuevo Centros Operación',
                'usuario_creacion'       => '1',
                'estado'                 => 'ACTIVO'
            ],
            [ 
                'rec_alias'              => 'ConfiguracionCentrosOperacionEditar',
                'rec_modulo'             => 'Configuracion', 
                'rec_controlador'        => 'CentrosOperacionController', 
                'rec_accion'             => 'editar', 
                'rec_modulo_descripcion' => 'Configuracion',
                'rec_descripcion'        => 'Editar Centros Operación',
                'usuario_creacion'       => '1',
                'estado'                 => 'ACTIVO'
            ],
            [ 
                'rec_alias'              => 'ConfiguracionCentrosOperacionCambiarEstado',
                'rec_modulo'             => 'Configuracion', 
                'rec_controlador'        => 'CentrosOperacionController', 
                'rec_accion'             => 'cambiarEstado', 
                'rec_modulo_descripcion' => 'Configuracion',
                'rec_descripcion'        => 'Cambiar Estado Centros Operación',
                'usuario_creacion'       => '1',
                'estado'                 => 'ACTIVO'
            ],
            // Recurso para Documento Soporte > Documentos Enviados
            [ 
                'rec_alias'              => 'DocumentosSoporteDocumentosEnviadosEnviarGestionDocumentos',
                'rec_modulo'             => 'DocumentosSoporte', 
                'rec_controlador'        => 'DocumentosSoporteDocumentosEnviados', 
                'rec_accion'             => 'enviarGestionDocumentos', 
                'rec_modulo_descripcion' => 'Documentos Soporte Documentos Enviados',
                'rec_descripcion'        => 'Documentos Soporte Documentos Enviados Enviar a Gestion Documentos',
                'usuario_creacion'       => '1',
                'estado'                 => 'ACTIVO'
            ],
            // Recurso para Recepción > Documentos Recibidos
            [ 
                'rec_alias'              => 'RecepcionDocumentosRecibidosEnviarGestionDocumentos',
                'rec_modulo'             => 'Recepcion', 
                'rec_controlador'        => 'RecepcionDocumentosRecibidos', 
                'rec_accion'             => 'enviarGestionDocumentos', 
                'rec_modulo_descripcion' => 'Recepcion Documentos Recibidos',
                'rec_descripcion'        => 'Recepcion Documentos Recibidos Enviar a Gestion Documentos',
                'usuario_creacion'       => '1',
                'estado'                 => 'ACTIVO'
            ],
            // Recursos para Recepción > Gestión Documentos > Fe/Doc Soporte Electrónico (Etapa 1)
            [ 
                'rec_alias'              => 'RecepcionGestionDocumentosEtapa1',
                'rec_modulo'             => 'GestionDocumentos', 
                'rec_controlador'        => 'RepGestionDocumento', 
                'rec_accion'             => 'etapa1Listar', 
                'rec_modulo_descripcion' => 'Gestion Documentos',
                'rec_descripcion'        => 'Gestion Documentos Etapa 1 Listar',
                'usuario_creacion'       => '1',
                'estado'                 => 'ACTIVO'
            ],
            [ 
                'rec_alias'              => 'RecepcionGestionDocumentosEtapa1DescargarExcel',
                'rec_modulo'             => 'GestionDocumentos', 
                'rec_controlador'        => 'RepGestionDocumento', 
                'rec_accion'             => 'etapa1DescargarExcel', 
                'rec_modulo_descripcion' => 'Gestion Documentos',
                'rec_descripcion'        => 'Gestion Documentos Etapa 1 Descargar Excel',
                'usuario_creacion'       => '1',
                'estado'                 => 'ACTIVO'
            ],
            [ 
                'rec_alias'              => 'RecepcionGestionDocumentosEtapa1GestionarFeDs',
                'rec_modulo'             => 'GestionDocumentos', 
                'rec_controlador'        => 'RepGestionDocumento', 
                'rec_accion'             => 'etapa1Gestionar', 
                'rec_modulo_descripcion' => 'Gestion Documentos',
                'rec_descripcion'        => 'Gestion Documentos Etapa 1 Gestionar FE/DE',
                'usuario_creacion'       => '1',
                'estado'                 => 'ACTIVO'
            ],
            [ 
                'rec_alias'              => 'RecepcionGestionDocumentosEtapa1CentroOperaciones',
                'rec_modulo'             => 'GestionDocumentos', 
                'rec_controlador'        => 'RepGestionDocumento', 
                'rec_accion'             => 'etapa1AsignarCentroOperaciones', 
                'rec_modulo_descripcion' => 'Gestion Documentos',
                'rec_descripcion'        => 'Gestion Documentos Etapa 1 Asignar Centro Operaciones',
                'usuario_creacion'       => '1',
                'estado'                 => 'ACTIVO'
            ],
            [ 
                'rec_alias'              => 'RecepcionGestionDocumentosEtapa1SiguienteEtapa',
                'rec_modulo'             => 'GestionDocumentos', 
                'rec_controlador'        => 'RepGestionDocumento', 
                'rec_accion'             => 'etapa1SiguienteEtapa', 
                'rec_modulo_descripcion' => 'Gestion Documentos',
                'rec_descripcion'        => 'Gestion Documentos Etapa 1 Siguiente Etapa',
                'usuario_creacion'       => '1',
                'estado'                 => 'ACTIVO'
            ],
            // Recursos para Recepción > Gestión Documentos > Pendiente Revisión (Etapa 2)
            [ 
                'rec_alias'              => 'RecepcionGestionDocumentosEtapa2',
                'rec_modulo'             => 'GestionDocumentos', 
                'rec_controlador'        => 'RepGestionDocumento', 
                'rec_accion'             => 'etapa2Listar', 
                'rec_modulo_descripcion' => 'Gestion Documentos',
                'rec_descripcion'        => 'Gestion Documentos Etapa 2 Listar',
                'usuario_creacion'       => '1',
                'estado'                 => 'ACTIVO'
            ],
            [ 
                'rec_alias'              => 'RecepcionGestionDocumentosEtapa2DescargarExcel',
                'rec_modulo'             => 'GestionDocumentos', 
                'rec_controlador'        => 'RepGestionDocumento', 
                'rec_accion'             => 'etapa2DescargarExcel', 
                'rec_modulo_descripcion' => 'Gestion Documentos',
                'rec_descripcion'        => 'Gestion Documentos Etapa 2 Descargar Excel',
                'usuario_creacion'       => '1',
                'estado'                 => 'ACTIVO'
            ],
            [ 
                'rec_alias'              => 'RecepcionGestionDocumentosEtapa2GestionarFeDs',
                'rec_modulo'             => 'GestionDocumentos', 
                'rec_controlador'        => 'RepGestionDocumento', 
                'rec_accion'             => 'etapa2Gestionar', 
                'rec_modulo_descripcion' => 'Gestion Documentos',
                'rec_descripcion'        => 'Gestion Documentos Etapa 2 Gestionar Fe/Ds',
                'usuario_creacion'       => '1',
                'estado'                 => 'ACTIVO'
            ],
            [ 
                'rec_alias'              => 'RecepcionGestionDocumentosEtapa2CentroCosto',
                'rec_modulo'             => 'GestionDocumentos', 
                'rec_controlador'        => 'RepGestionDocumento', 
                'rec_accion'             => 'etapa2AsignarCentroCosto', 
                'rec_modulo_descripcion' => 'Gestion Documentos',
                'rec_descripcion'        => 'Gestion Documentos Etapa 2 Asignar Centro Costo',
                'usuario_creacion'       => '1',
                'estado'                 => 'ACTIVO'
            ],
            [ 
                'rec_alias'              => 'RecepcionGestionDocumentosEtapa2SiguienteEtapa',
                'rec_modulo'             => 'GestionDocumentos', 
                'rec_controlador'        => 'RepGestionDocumento', 
                'rec_accion'             => 'etapa2SiguienteEtapa', 
                'rec_modulo_descripcion' => 'Gestion Documentos',
                'rec_descripcion'        => 'Gestion Documentos Etapa 2 Siguiente Etapa',
                'usuario_creacion'       => '1',
                'estado'                 => 'ACTIVO'
            ],
            // Recursos para Recepción > Gestión Documentos > Pendiente Aprobar Conformidad (Etapa 3)
            [ 
                'rec_alias'              => 'RecepcionGestionDocumentosEtapa3',
                'rec_modulo'             => 'GestionDocumentos', 
                'rec_controlador'        => 'RepGestionDocumento', 
                'rec_accion'             => 'etapa3Listar', 
                'rec_modulo_descripcion' => 'Gestion Documentos',
                'rec_descripcion'        => 'Gestion Documentos Etapa 3 Listar',
                'usuario_creacion'       => '1',
                'estado'                 => 'ACTIVO'
            ],
            [ 
                'rec_alias'              => 'RecepcionGestionDocumentosEtapa3DescargarExcel',
                'rec_modulo'             => 'GestionDocumentos', 
                'rec_controlador'        => 'RepGestionDocumento', 
                'rec_accion'             => 'etapa3DescargarExcel', 
                'rec_modulo_descripcion' => 'Gestion Documentos',
                'rec_descripcion'        => 'Gestion Documentos Etapa 3 Descargar Excel',
                'usuario_creacion'       => '1',
                'estado'                 => 'ACTIVO'
            ],
            [ 
                'rec_alias'              => 'RecepcionGestionDocumentosEtapa3GestionarFeDs',
                'rec_modulo'             => 'GestionDocumentos', 
                'rec_controlador'        => 'RepGestionDocumento', 
                'rec_accion'             => 'etapa3Gestionar', 
                'rec_modulo_descripcion' => 'Gestion Documentos',
                'rec_descripcion'        => 'Gestion Documentos Etapa 3 Gestionar Fe/Ds',
                'usuario_creacion'       => '1',
                'estado'                 => 'ACTIVO'
            ],
            [ 
                'rec_alias'              => 'RecepcionGestionDocumentosEtapa3SiguienteEtapa',
                'rec_modulo'             => 'GestionDocumentos', 
                'rec_controlador'        => 'RepGestionDocumento', 
                'rec_accion'             => 'etapa3SiguienteEtapa', 
                'rec_modulo_descripcion' => 'Gestion Documentos',
                'rec_descripcion'        => 'Gestion Documentos Etapa 3 Siguiente Etapa',
                'usuario_creacion'       => '1',
                'estado'                 => 'ACTIVO'
            ],
            // Recursos para Recepción > Gestión Documentos > Pendiente Reconocimiento Contable (Etapa 4)
            [ 
                'rec_alias'              => 'RecepcionGestionDocumentosEtapa4',
                'rec_modulo'             => 'GestionDocumentos', 
                'rec_controlador'        => 'RepGestionDocumento', 
                'rec_accion'             => 'etapa4Listar', 
                'rec_modulo_descripcion' => 'Gestion Documentos',
                'rec_descripcion'        => 'Gestion Documentos Etapa 4 Listar',
                'usuario_creacion'       => '1',
                'estado'                 => 'ACTIVO'
            ],
            [ 
                'rec_alias'              => 'RecepcionGestionDocumentosEtapa4DescargarExcel',
                'rec_modulo'             => 'GestionDocumentos', 
                'rec_controlador'        => 'RepGestionDocumento', 
                'rec_accion'             => 'etapa4DescargarExcel', 
                'rec_modulo_descripcion' => 'Gestion Documentos',
                'rec_descripcion'        => 'Gestion Documentos Etapa 4 Descargar Excel',
                'usuario_creacion'       => '1',
                'estado'                 => 'ACTIVO'
            ],
            [ 
                'rec_alias'              => 'RecepcionGestionDocumentosEtapa4GestionarFeDs',
                'rec_modulo'             => 'GestionDocumentos', 
                'rec_controlador'        => 'RepGestionDocumento', 
                'rec_accion'             => 'etapa4Gestionar', 
                'rec_modulo_descripcion' => 'Gestion Documentos',
                'rec_descripcion'        => 'Gestion Documentos Etapa 4 Gestionar Fe/Ds',
                'usuario_creacion'       => '1',
                'estado'                 => 'ACTIVO'
            ],
            [ 
                'rec_alias'              => 'RecepcionGestionDocumentosEtapa4DatosContabilizado',
                'rec_modulo'             => 'GestionDocumentos', 
                'rec_controlador'        => 'RepGestionDocumento', 
                'rec_accion'             => 'etapa4DatosContabilizado', 
                'rec_modulo_descripcion' => 'Gestion Documentos',
                'rec_descripcion'        => 'Gestion Documentos Etapa 4 Datos Contabilizado',
                'usuario_creacion'       => '1',
                'estado'                 => 'ACTIVO'
            ],
            [ 
                'rec_alias'              => 'RecepcionGestionDocumentosEtapa4SiguienteEtapa',
                'rec_modulo'             => 'GestionDocumentos', 
                'rec_controlador'        => 'RepGestionDocumento', 
                'rec_accion'             => 'etapa4SiguienteEtapa', 
                'rec_modulo_descripcion' => 'Gestion Documentos',
                'rec_descripcion'        => 'Gestion Documentos Etapa 4 Siguiente Etapa',
                'usuario_creacion'       => '1',
                'estado'                 => 'ACTIVO'
            ],
            // Recursos para Recepción > Gestión Documentos > Pendiente Revisión de Impuestos (Etapa 5)
            [ 
                'rec_alias'              => 'RecepcionGestionDocumentosEtapa5',
                'rec_modulo'             => 'GestionDocumentos', 
                'rec_controlador'        => 'RepGestionDocumento', 
                'rec_accion'             => 'etapa5Listar', 
                'rec_modulo_descripcion' => 'Gestion Documentos',
                'rec_descripcion'        => 'Gestion Documentos Etapa 5 Listar',
                'usuario_creacion'       => '1',
                'estado'                 => 'ACTIVO'
            ],
            [ 
                'rec_alias'              => 'RecepcionGestionDocumentosEtapa5DescargarExcel',
                'rec_modulo'             => 'GestionDocumentos', 
                'rec_controlador'        => 'RepGestionDocumento', 
                'rec_accion'             => 'etapa5DescargarExcel', 
                'rec_modulo_descripcion' => 'Gestion Documentos',
                'rec_descripcion'        => 'Gestion Documentos Etapa 5 Descargar Excel',
                'usuario_creacion'       => '1',
                'estado'                 => 'ACTIVO'
            ],
            [ 
                'rec_alias'              => 'RecepcionGestionDocumentosEtapa5GestionarFeDs',
                'rec_modulo'             => 'GestionDocumentos', 
                'rec_controlador'        => 'RepGestionDocumento', 
                'rec_accion'             => 'etapa5Gestionar', 
                'rec_modulo_descripcion' => 'Gestion Documentos',
                'rec_descripcion'        => 'Gestion Documentos Etapa 5 Gestionar FE/DE',
                'usuario_creacion'       => '1',
                'estado'                 => 'ACTIVO'
            ],
            [ 
                'rec_alias'              => 'RecepcionGestionDocumentosEtapa5SiguienteEtapa',
                'rec_modulo'             => 'GestionDocumentos', 
                'rec_controlador'        => 'RepGestionDocumento', 
                'rec_accion'             => 'etapa5SiguienteEtapa', 
                'rec_modulo_descripcion' => 'Gestion Documentos',
                'rec_descripcion'        => 'Gestion Documentos Etapa 5 Siguiente Etapa',
                'usuario_creacion'       => '1',
                'estado'                 => 'ACTIVO'
            ],
            // Recursos para Recepción > Gestión Documentos > Pendiente de Pago (Etapa 6)
            [ 
                'rec_alias'              => 'RecepcionGestionDocumentosEtapa6',
                'rec_modulo'             => 'GestionDocumentos', 
                'rec_controlador'        => 'RepGestionDocumento', 
                'rec_accion'             => 'etapa6Listar', 
                'rec_modulo_descripcion' => 'Gestion Documentos',
                'rec_descripcion'        => 'Gestion Documentos Etapa 6 Listar',
                'usuario_creacion'       => '1',
                'estado'                 => 'ACTIVO'
            ],
            [ 
                'rec_alias'              => 'RecepcionGestionDocumentosEtapa6DescargarExcel',
                'rec_modulo'             => 'GestionDocumentos', 
                'rec_controlador'        => 'RepGestionDocumento', 
                'rec_accion'             => 'etapa6DescargarExcel', 
                'rec_modulo_descripcion' => 'Gestion Documentos',
                'rec_descripcion'        => 'Gestion Documentos Etapa 6 Descargar Excel',
                'usuario_creacion'       => '1',
                'estado'                 => 'ACTIVO'
            ],
            [ 
                'rec_alias'              => 'RecepcionGestionDocumentosEtapa6GestionarFeDs',
                'rec_modulo'             => 'GestionDocumentos', 
                'rec_controlador'        => 'RepGestionDocumento', 
                'rec_accion'             => 'etapa6Gestionar', 
                'rec_modulo_descripcion' => 'Gestion Documentos',
                'rec_descripcion'        => 'Gestion Documentos Etapa 6 Gestionar FE/DE',
                'usuario_creacion'       => '1',
                'estado'                 => 'ACTIVO'
            ],
            [ 
                'rec_alias'              => 'RecepcionGestionDocumentosEtapa6SiguienteEtapa',
                'rec_modulo'             => 'GestionDocumentos', 
                'rec_controlador'        => 'RepGestionDocumento', 
                'rec_accion'             => 'etapa6SiguienteEtapa', 
                'rec_modulo_descripcion' => 'Gestion Documentos',
                'rec_descripcion'        => 'Gestion Documentos Etapa 6 Siguiente Etapa',
                'usuario_creacion'       => '1',
                'estado'                 => 'ACTIVO'
            ],
            // Recursos para Recepción > Gestión Documentos > Fe/Doc Soporte Electrónico Gestionado (Etapa 7)
            [ 
                'rec_alias'              => 'RecepcionGestionDocumentosEtapa7',
                'rec_modulo'             => 'GestionDocumentos', 
                'rec_controlador'        => 'RepGestionDocumento', 
                'rec_accion'             => 'etapa7Listar', 
                'rec_modulo_descripcion' => 'Gestion Documentos',
                'rec_descripcion'        => 'Gestion Documentos Etapa 7 Listar',
                'usuario_creacion'       => '1',
                'estado'                 => 'ACTIVO'
            ],
            [ 
                'rec_alias'              => 'RecepcionGestionDocumentosEtapa7DescargarExcel',
                'rec_modulo'             => 'GestionDocumentos', 
                'rec_controlador'        => 'RepGestionDocumento', 
                'rec_accion'             => 'etapa7DescargarExcel', 
                'rec_modulo_descripcion' => 'Gestion Documentos',
                'rec_descripcion'        => 'Gestion Documentos Etapa 7 Descargar Excel',
                'usuario_creacion'       => '1',
                'estado'                 => 'ACTIVO'
            ],
            // Recursos para Recepción > Autorizaciones > Autorización Etapas
            [ 
                'rec_alias'              => 'RecepcionAutorizacionEtapas',
                'rec_modulo'             => 'Autorizaciones', 
                'rec_controlador'        => 'RepAutorizacionEtapa', 
                'rec_accion'             => 'autorizacionEtapa', 
                'rec_modulo_descripcion' => 'Autorizaciones',
                'rec_descripcion'        => 'Autorizacion Etapa',
                'usuario_creacion'       => '1',
                'estado'                 => 'ACTIVO'
            ],
            // Recursos para Recepción > Reportes > Reporte Gestión Documentos
            [ 
                'rec_alias'              => 'RecepcionReporteGestionDocumentos',
                'rec_modulo'             => 'Recepcion', 
                'rec_controlador'        => 'RecepcionReporteGestionDocumentos', 
                'rec_accion'             => 'gestionDocumentos', 
                'rec_modulo_descripcion' => 'Recepcion',
                'rec_descripcion'        => 'Recepcion Reporte Gestion Documentos',
                'usuario_creacion'       => '1',
                'estado'                 => 'ACTIVO'
            ],
        ];

        // Obtiene los roles 'superadmin' y 'usuarioma' del sistema para poder asignarle los nuevos permisos
        $roles = $this->consultarRoles(['superadmin', 'usuarioma']);

        // Itera sobre los recursos para poder crearlos
        foreach ($inserts as $recurso) {
            DB::beginTransaction();
            try {
                $this->info("Insertando permiso para el recurso: [{$recurso['rec_alias']}-{$recurso['rec_modulo']}-{$recurso['rec_descripcion']}].");

                $recursoCreado = $this->crearRecurso($recurso);

                foreach ($roles as $rol)
                    $this->crearPermiso($rol->rol_id, $recursoCreado->rec_id);

                DB::commit();

                $this->info("[{$recurso['rec_alias']}]: Recurso creado con los permisos correctamente.");
            } catch (\Exception $e){
                DB::rollBack();

                $this->error("[{$recurso['rec_alias']}]: error al intentar crear el recurso [" . $e->getFile() . " - Línea " . $e->getLine() . ": " . $e->getMessage() . "].");
            }
        }
    }
}
