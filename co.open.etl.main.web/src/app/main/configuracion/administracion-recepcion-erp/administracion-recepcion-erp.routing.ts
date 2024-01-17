import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { AdministracionRecepcionErpGestionarComponent } from './gestionar/administracion-recepcion-erp-gestionar.component';
import { AdministracionRecepcionErpListarComponent } from './listar/administracion-recepcion-erp-listar.component';

const routes: Routes = [
    {
        path: '',
        component: AdministracionRecepcionErpListarComponent
    },
    {
        path: 'nuevo-administracion-recepcion-erp',
        component: AdministracionRecepcionErpGestionarComponent
    },
    {
        path: 'editar-administracion-recepcion-erp/:ate_grupo',
        component: AdministracionRecepcionErpGestionarComponent
    },
    {
        path: 'ver-administracion-recepcion-erp/:ate_grupo',
        component: AdministracionRecepcionErpGestionarComponent
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
export class AdministracionRecepcionErpRoutingModule {}
