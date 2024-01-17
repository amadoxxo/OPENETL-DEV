<?php


namespace App\Http\Modulos\Engines\Documentos;

use Illuminate\Support\Carbon;
use App\Http\Modulos\Documentos\EtlFatDocumentosDaop\EtlFatDocumentoDaop;
use App\Http\Modulos\Documentos\EtlDetalleDocumentosDaop\EtlDetalleDocumentoDaop;
use App\Http\Modulos\Documentos\EtlEstadosDocumentosDaop\EtlEstadosDocumentoDaop;
use App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaop;
use App\Http\Modulos\Documentos\EtlAnticiposDocumentosDaop\EtlAnticiposDocumentoDaop;
use App\Http\Modulos\Documentos\EtlMediosPagoDocumentosDaop\EtlMediosPagoDocumentoDaop;
use App\Http\Modulos\Documentos\EtlImpuestosItemsDocumentosDaop\EtlImpuestosItemsDocumentoDaop;
use App\Http\Modulos\Documentos\EtlCargosDescuentosDocumentosDaop\EtlCargosDescuentosDocumentoDaop;
use App\Http\Modulos\Documentos\EtlDatosAdicionalesDocumentosDaop\EtlDatosAdicionalesDocumentoDaop;

/**
 * Motor que centraliza el proceso de busqueda de documentos en DO
 *
 * Class BuscadorDocumentosEngine
 * @package App\Http\Modulos\Engines\Documentos
 */
class BuscadorDocumentosEngine
{
    /**
     * Documentos encontrados.
     *
     * @var array
     */
    private $documentos = [];

    /**
     * Obtiene los documentos asociados a un agendamiento
     *
     * @param int age_id
     * @param string $estadoRequerido
     */
    public function buscarPorAgendamiento($age_id, string $estadoRequerido): void {
        $this->documentos = [];
        EtlCabeceraDocumentoDaop::select(['cdo_id'])
            ->where('age_id', $age_id)
            ->where('estado', 'ACTIVO')
            ->get()
            ->map(function ($item) use ($estadoRequerido) {
                $estado = EtlEstadosDocumentoDaop::where('cdo_id', $item->cdo_id)
                    ->where('estado', 'ACTIVO')
                    ->where()
                    ->orderBy('esd_estado_fecha', 'desc')->first();
                if ($estado->est_estado === $estadoRequerido)
                    $this->documentos[] = [
                        'doc' => $item,
                        'estado' => $estado
                    ];
            });
    }

    public function buscarPorEstado(string $estadoRequerido): void  {
        $this->documentos = [];
        EtlCabeceraDocumentoDaop::select(['cdo_id'])
            ->where('estado', 'ACTIVO')
            ->get()
            ->map(function ($item) use ($estadoRequerido) {
                $estado = EtlEstadosDocumentoDaop::where('cdo_id', $item->cdo_id)
                    ->where('estado', 'ACTIVO')
                    ->where()
                    ->orderBy('esd_estado_fecha', 'desc')->first();
                if ($estado->est_estado === $estadoRequerido)
                    $this->documentos[] = [
                        'doc' => $item,
                        'estado' => $estado
                    ];
            });
    }

    /**
     * Obtiene un documento electronico y los datos asociados al mismo dado su cdo_id
     *
     * @param $cdo_id
     * @param array $relaciones
     * @return EtlCabeceraDocumentoDaop|null
     */
    public function getDocumento($cdo_id, array $relaciones = []) {
        $documento = EtlCabeceraDocumentoDaop::where('cdo_id', $cdo_id)
            ->where(function($query) {
                $query->where('estado', 'ACTIVO')
                    ->orWhere('estado', 'PROVISIONAL');
            })
            ->with($relaciones)
            ->first();

        if(!$documento) {
            $documento = $this->getDocumentoHistorico($cdo_id, $relaciones);
        }

        return $documento;
    }

    /**
     * Obtiene un documento electronico mediante el cdo_id desde la data histórica y los datos asociados al mismo.
     *
     * @param int $cdo_id ID del documento
     * @param array $relaciones Array de relaciones
     * @return EtlCabeceraDocumentoDaop|null
     */
    public function getDocumentoHistorico(int $cdo_id, array $relaciones = []) {
        $docFat = EtlFatDocumentoDaop::select(['cdo_id', 'rfa_prefijo', 'cdo_consecutivo', 'cdo_fecha_validacion_dian'])
            ->where('cdo_id', $cdo_id)
            ->first();

        if($docFat) {
            $particion = Carbon::parse($docFat->cdo_fecha_validacion_dian)->format('Ym');

            $tblCabecera = new EtlCabeceraDocumentoDaop;
            $tblCabecera->setTable('etl_cabecera_documentos_' . $particion);

            $documento = $tblCabecera->where('cdo_id', $cdo_id)
                ->where(function($query) {
                    $query->where('estado', 'ACTIVO')
                        ->orWhere('estado', 'PROVISIONAL');
                })
                ->with([
                    'getConfiguracionObligadoFacturarElectronicamente',
                    'getConfiguracionObligadoFacturarElectronicamente.getConfiguracionSoftwareProveedorTecnologico:sft_id,add_id,sft_testsetid',
                    'getConfiguracionObligadoFacturarElectronicamente.getConfiguracionSoftwareProveedorTecnologicoDs:sft_id,add_id,sft_testsetid',
                    'getConfiguracionAdquirente',
                    'getConfiguracionAutorizado',
                    'getConfiguracionResolucionesFacturacion',
                    'getTipoDocumentoElectronico',
                    'getTipoOperacion',
                    'getParametrosMoneda',
                    'getParametrosMonedaExtranjera'
                ])
                ->first();

            if(in_array('getDetalleDocumentosDaop', $relaciones)) {
                $tblDetalle = new EtlDetalleDocumentoDaop;
                $tblDetalle->setTable('etl_detalle_documentos_' . $particion);
    
                $getDetalleDocumentosDaop = $tblDetalle->where('cdo_id', $documento->cdo_id)
                    ->get();
    
                $documento->get_detalle_documentos_daop = $documento->getDetalleDocumentosDaop = $getDetalleDocumentosDaop;
            }
    
            if(in_array('getImpuestosItemsDocumentosDaop', $relaciones)) {
                $tblImpuestos = new EtlImpuestosItemsDocumentoDaop;
                $tblImpuestos->setTable('etl_impuestos_items_documentos_' . $particion);
    
                $getImpuestosItemsDocumentosDaop = $tblImpuestos->where('cdo_id', $documento->cdo_id)
                    ->get();
    
                $documento->get_impuestos_items_documentos_daop = $documento->getImpuestosItemsDocumentosDaop = $getImpuestosItemsDocumentosDaop;
            }
    
            if(in_array('getDadDocumentosDaop', $relaciones)) {
                $tblDadDocumentos = new EtlDatosAdicionalesDocumentoDaop;
                $tblDadDocumentos->setTable('etl_datos_adicionales_documentos_' . $particion);
    
                $getDadDocumentosDaop = $tblDadDocumentos->where('cdo_id', $documento->cdo_id)
                    ->get();
    
                $documento->get_dad_documentos_daop = $documento->getDadDocumentosDaop = $getDadDocumentosDaop;
            }
    
            if(in_array('getEstadosDocumentos', $relaciones)) {
                $tblEstados = new EtlEstadosDocumentoDaop;
                $tblEstados->setTable('etl_estados_documentos_' . $particion);
    
                $getEstadosDocumentos = $tblEstados->where('cdo_id', $documento->cdo_id)
                    ->get();
    
                $documento->get_estados_documentos = $documento->getEstadosDocumentos = $getEstadosDocumentos;
            }
    
            if(in_array('getAnticiposDocumentosDaop', $relaciones)) {
                $tblAnticipos = new EtlAnticiposDocumentoDaop;
                $tblAnticipos->setTable('etl_anticipos_documentos_' . $particion);
    
                $getAnticiposDocumentosDaop = $tblAnticipos->where('cdo_id', $documento->cdo_id)
                    ->get();
    
                $documento->get_anticipos_documentos_daop = $documento->getAnticiposDocumentosDaop = $getAnticiposDocumentosDaop;
            }
    
            if(in_array('getCargosDescuentosDocumentosDaop', $relaciones)) {
                $tblCargosDescuentos = new EtlCargosDescuentosDocumentoDaop;
                $tblCargosDescuentos->setTable('etl_cargos_descuentos_documentos_' . $particion);
    
                $getCargosDescuentosDocumentosDaop = $tblCargosDescuentos->where('cdo_id', $documento->cdo_id)
                    ->get();
    
                $documento->get_cargos_descuentos_documentos_daop = $documento->getCargosDescuentosDocumentosDaop = $getCargosDescuentosDocumentosDaop;
            }
    
            if(in_array('getMediosPagoDocumentosDaop', $relaciones)) {
                $tblMediosPago = new EtlMediosPagoDocumentoDaop();
                $tblMediosPago->setTable('etl_medios_pago_documentos_' . $particion);
    
                $getMediosPagoDocumentosDaop = $tblMediosPago->where('cdo_id', $documento->cdo_id)
                    ->get();
    
                $documento->get_medios_pago_documentos_daop = $documento->getMediosPagoDocumentosDaop = $getMediosPagoDocumentosDaop;
            }
        }

        return isset($documento) ? $documento : null;
    }
}