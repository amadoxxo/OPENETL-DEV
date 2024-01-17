import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { ResolucionesFacturacionGestionarComponent } from './gestionar/resoluciones-facturacion-gestionar.component';
import { ResolucionesFacturacionListarComponent } from './listar/resoluciones-facturacion-listar.component';
import { ResolucionesFacturacionSubirComponent } from './subir/resoluciones-facturacion-subir.component';

const routes: Routes = [
    {
        path: '',
        component: ResolucionesFacturacionListarComponent
    },
    {
        path: 'nueva-resolucion-facturacion',
        component: ResolucionesFacturacionGestionarComponent
    },
    {
        path: 'editar-resolucion-facturacion/:rfa_prefijo_resolucion',
        component: ResolucionesFacturacionGestionarComponent
    },
    {
        path: 'ver-resolucion-facturacion/:rfa_prefijo_resolucion/:rfa_id',
        component: ResolucionesFacturacionGestionarComponent
    },
    {
        path: 'subir-resoluciones-facturacion',
        component: ResolucionesFacturacionSubirComponent
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
export class ResolucionesFacturacionRoutingModule {}
