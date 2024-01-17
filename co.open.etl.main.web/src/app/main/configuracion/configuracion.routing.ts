import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { ReportesBackgroundComponent } from '../reportes/reportes-background/reportes-background.component';

const routes: Routes = [
    {
        path: 'administracion-recepcion-erp',
        loadChildren: () => import('./administracion-recepcion-erp/administracion-recepcion-erp.module').then( erp => erp.AdministracionRecepcionErpModule ) 
    },
    {
        path: 'resoluciones-facturacion',
        loadChildren: () => import('./resoluciones-facturacion/resoluciones-facturacion.module').then( res => res.ResolucionesFacturacionModule ) 
    },
    {
        path: 'oferentes',
        loadChildren: () => import('./oferentes/oferentes.module').then( ofe => ofe.OferentesModule ) 
    },
    {
        path: 'radian-actores',
        loadChildren: () => import('./radian-actores/radian-actores.module').then( act => act.RadianActoresModule) 
    },
    {
        path: 'software-proveedor-tecnologico',
        loadChildren: () => import('./software-proveedor-tecnologico/software-proveedor-tecnologico.module').then( sft => sft.SoftwareProveedorTecnologicoModule ) 
    },
    {
        path: 'proveedores',
        loadChildren: () => import('./proveedores/proveedores.module').then( pro => pro.ProveedoresModule ) 
    },
    {
        path: 'recepcion/fondos',
        loadChildren: () => import('../proyectos-especiales/recepcion/fnc/validacion/fondos/listar/fondos_listar.module').then( fondos => fondos.FondosListarModule),
    },
    {
        path: 'recepcion/centros-costo',
        loadChildren: () => import('./centros-costo/listar/centros-costo.module').then( cco => cco.CentrosCostoModule),
    },
    {
        path: 'recepcion/causales-devolucion',
        loadChildren: () => import('./causales-devolucion/listar/causales-devolucion.module').then( cde => cde.CausalesDevolucionModule),
    },
    {
        path: 'recepcion/centros-operacion',
        loadChildren: () => import('./centros-operacion/listar/centros-operacion.module').then( cop => cop.CentrosOperacionModule),
    },
    {
        path: 'grupos-trabajo',
        loadChildren: () => import('./grupos-trabajo/grupos-trabajo.module').then( gtr => gtr.GruposTrabajoModule ) 
    },
    {
        path: 'xpath-documentos-electronicos',
        loadChildren: () => import('./xpath-documentos-electronicos/xpath-documentos-electronicos.module').then( xph => xph.XPathDocumentosElectronicosModule ) 
    },
    {
        path: 'autorizaciones-eventos-dian',
        loadChildren: () => import('./autorizaciones-eventos-dian/autorizaciones-eventos-dian.module').then( aut => aut.AutorizacionesEventosDianModule ) 
    },
    {
        path: 'usuarios-ecm',
        loadChildren: () => import('./usuarios-ecm/usuarios-ecm.module').then( ecm => ecm.UsuariosEcmModule ) 
    },
    {
        path: 'nomina-electronica',
        loadChildren: () => import('./nomina-electronica/nomina-electronica.module').then( nom => nom.NominaElectronicaModule ) 
    },
    {
        path: 'reportes/background',
        component: ReportesBackgroundComponent
    },
    {
        path: '',
        loadChildren: () => import('./adquirentes/adquirentes.module').then( adq => adq.AdquirentesModule ) 
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
export class ConfiguracionRoutingModule {}
