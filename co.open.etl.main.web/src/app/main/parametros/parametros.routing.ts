import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';

const routes: Routes = [
    {
        path: 'ambiente-destino-documentos',
        loadChildren: () => import('./ambiente_destino_documentos/listar/ambiente_destino_documentos.module').then( module => module.AmbienteDestinoDocumentosModule ) 
    },
    {
        path: 'clasificacion-productos',
        loadChildren: () => import('./clasificacion_productos/listar/clasificacion_productos.module').then( module => module.ClasificacionProductosModule ) 
    },
    {
        path: 'codigos-descuentos',
        loadChildren: () => import('./codigos_descuentos/listar/codigos_descuentos.module').then( module => module.CodigosDescuentosModule ) 
    },
    {
        path: 'codigos-postales',
        loadChildren: () => import('./codigos_postales/listar/codigos_postales.module').then( module => module.CodigosPostalesModule ) 
    },
    {
        path: 'colombia-compra-eficiente',
        loadChildren: () => import('./colombia_compra_eficiente/listar/colombia_compra_eficiente.module').then( module => module.ColombiaCompraEficienteModule ) 
    },
    {
        path: 'conceptos-correccion',
        loadChildren: () => import('./conceptos_correccion/listar/conceptos_correccion.module').then( module => module.ConceptosCorreccionModule ) 
    },
    {
        path: 'conceptos-rechazo',
        loadChildren: () => import('./conceptos_rechazo/listar/conceptos_rechazo.module').then( module => module.ConceptosRechazoModule ) 
    },
    {
        path: 'condiciones-entrega',
        loadChildren: () => import('./condiciones_entrega/listar/condiciones_entrega.module').then( module => module.CondicionesEntregaModule ) 
    },
    {
        path: 'departamentos',
        loadChildren: () => import('./departamentos/listar/departamentos.module').then( module => module.DepartamentosModule ) 
    },
    {
        path: 'formas-generacion-transmision',
        loadChildren: () => import('./forma_generacion_transmision/listar/forma_generacion_transmision.module').then( module => module.FormaGeneracionTransmisionModule ) 
    },
    {
        path: 'formas-pago',
        loadChildren: () => import('./formas_pago/listar/formas_pago.module').then( module => module.FormasPagoModule ) 
    },
    {
        path: 'mandatos',
        loadChildren: () => import('./mandatos/listar/mandatos.module').then( module => module.MandatosModule ) 
    },
    {
        path: 'medios-pago',
        loadChildren: () => import('./medios_pago/listar/medios_pago.module').then( module => module.MediosPagoModule ) 
    },
    {
        path: 'monedas',
        loadChildren: () => import('./monedas/listar/monedas.module').then( module => module.MonedasModule ) 
    },
    {
        path: 'municipios',
        loadChildren: () => import('./municipios/listar/municipios.module').then( module => module.MunicipiosModule ) 
    },
    {
        path: 'nomina-electronica',
        loadChildren: () => import('./nomina_electronica/nomina_electronica.module').then( module => module.NominaElectronicaModule ) 
    },
    {
        path: 'paises',
        loadChildren: () => import('./paises/listar/paises.module').then( module => module.PaisesModule ) 
    },
    {
        path: 'partidas-arancelarias',
        loadChildren: () => import('./partidas_arancelarias/listar/partidas_arancelarias.module').then( module => module.PartidasArancelariasModule ) 
    },
    {
        path: 'precios-referencia',
        loadChildren: () => import('./precios_referencia/listar/precios_referencia.module').then( module => module.PreciosReferenciaModule ) 
    },
    {
        path: 'procedencia-vendedor',
        loadChildren: () => import('./procedencia_vendedor/listar/procedencia_vendedor.module').then( module => module.ProcedenciaVendedorModule ) 
    },
    {
        path: 'radian',
        loadChildren: () => import('./radian/radian.module').then( module => module.RadianModule )
    },
    {
        path: 'referencia-otros-documentos',
        loadChildren: () => import('./referencia_otros_documentos/listar/referencia_otros_documentos.module').then( module => module.ReferenciaOtrosDocumentosModule ) 
    },
    {
        path: 'regimen-fiscal',
        loadChildren: () => import('./regimen_fiscal/listar/regimen_fiscal.module').then( module => module.RegimenFiscalModule ) 
    },
    {
        path: 'responsabilidades-fiscales',
        loadChildren: () => import('./responsabilidades_fiscales/listar/responsabilidades_fiscales.module').then( module => module.ResponsabilidadesFiscalesModule ) 
    },
    {
        path: 'sector-cambiario',
        loadChildren: () => import('./sector_cambiario/sector_cambiario.module').then( module => module.SectorCambiarioModule ) 
    },
    {
        path: 'sector-salud',
        loadChildren: () => import('./sector_salud/sector_salud.module').then( module => module.SectorSaludModule ) 
    },
    {
        path: 'sector-transporte',
        loadChildren: () => import('./sector_transporte/sector_transporte.module').then( module => module.SectorTransporteModule ) 
    },
    {
        path: 'tarifas-impuesto',
        loadChildren: () => import('./tarifas_impuesto/listar/tarifas_impuesto.module').then( module => module.TarifasImpuestoModule ) 
    },
    {
        path: 'tipos-documentos',
        loadChildren: () => import('./tipos_documentos/listar/tipos_documentos.module').then( module => module.TiposDocumentosModule ) 
    },
    {
        path: 'tipos-documentos-electronicos',
        loadChildren: () => import('./tipos_documentos_electronicos/listar/tipos_documentos_electronicos.module').then( module => module.TiposDocumentosElectronicosModule ) 
    },
    {
        path: 'tipos-operacion',
        loadChildren: () => import('./tipos_operacion/listar/tipos_operacion.module').then( module => module.TiposOperacionModule ) 
    },
    {
        path: 'tipos-organizacion-juridica',
        loadChildren: () => import('./tipos_organizacion_juridica/listar/tipos_organizacion_juridica.module').then( module => module.TiposOrganizacionJuridicaModule ) 
    },
    {
        path: 'tributos',
        loadChildren: () => import('./tributos/listar/tributos.module').then( module => module.TributosModule ) 
    },
    {
        path: 'unidades',
        loadChildren: () => import('./unidades/listar/unidades.module').then( module => module.UnidadesModule ) 
    }
];

@NgModule({
    imports: [
        RouterModule.forChild(routes)
    ],
    exports: [
        RouterModule
    ]
})
export class ParametrosRoutingModule {}
