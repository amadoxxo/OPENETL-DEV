import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { ProveedoresGestionarComponent } from './gestionar/proveedores-gestionar.component';
import { ProveedoresListarComponent } from './listar/proveedores-listar.component';
import { ProveedoresSubirComponent } from './subir/proveedores-subir.component';

const routes: Routes = [
    {
        path: '',
        component: ProveedoresListarComponent
    },
    {
        path: 'nuevo-proveedor',
        component: ProveedoresGestionarComponent
    },
    {
        path: 'editar-proveedor/:pro_identificacion/:ofe_identificacion',
        component: ProveedoresGestionarComponent
    },
    {
        path: 'ver-proveedor/:pro_identificacion/:pro_id/:ofe_identificacion',
        component: ProveedoresGestionarComponent
    },
    {
        path: 'subir-proveedores',
        component: ProveedoresSubirComponent
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
export class ProveedoresRoutingModule {}
