<?php
namespace App\Http\Modulos\Recepcion\Documentos\RepProcesarDocumentosDaop;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use openEtl\Tenant\Models\Recepcion\Documentos\RepProcesarDocumentosDaop\TenantRepProcesarDocumentoDaop;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

class RepProcesarDocumentoDaop extends TenantRepProcesarDocumentoDaop {
    /**
     * @var array
     */
    protected $visible= [
        'pdo_id',
        'ofe_id',
        'cdo_cufe',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion',
        'getConfiguracionObligadoFacturarElectronicamente'
    ];

    /**
     * Relación con el modelo de OFEs.
     *
     * @return BelongsTo
     */
    public function getConfiguracionObligadoFacturarElectronicamente(): BelongsTo {
        return $this->belongsTo(ConfiguracionObligadoFacturarElectronicamente::class, 'ofe_id') ;
    }
}
