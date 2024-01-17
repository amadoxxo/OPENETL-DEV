import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { SoftwareProveedorTecnologicoGestionarComponent } from './gestionar/software-proveedor-tecnologico-gestionar.component';
import { SoftwareProveedorTecnologicoListarComponent } from './listar/software-proveedor-tecnologico-listar.component';
import { SoftwareProveedorTecnologicoSubirComponent } from './subir/software-proveedor-tecnologico-subir.component';

const routes: Routes = [
    {
        path: '',
        component: SoftwareProveedorTecnologicoListarComponent
    },
    {
        path: 'nuevo-software-proveedor-tecnologico',
        component: SoftwareProveedorTecnologicoGestionarComponent
    },
    {
        path: 'editar-software-proveedor-tecnologico/:sft_identificador/:sft_id',
        component: SoftwareProveedorTecnologicoGestionarComponent
    },
    {
        path: 'ver-software-proveedor-tecnologico/:sft_identificador/:sft_id',
        component: SoftwareProveedorTecnologicoGestionarComponent
    },
    {
        path: 'subir-software-proveedor-tecnologico',
        component: SoftwareProveedorTecnologicoSubirComponent
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
export class SoftwareProveedorTecnologicoRoutingModule {}
