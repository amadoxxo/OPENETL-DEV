import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { GruposTrabajoProveedoresListarComponent } from './listar/grupos-trabajo-proveedores-listar.component';
import { GruposTrabajoProveedoresSubirComponent } from './subir/grupos-trabajo-proveedores-subir.component';

const routes: Routes = [
    {
        path: '',
        component: GruposTrabajoProveedoresListarComponent
    },
    {
        path: 'subir-proveedores-asociados',
        component: GruposTrabajoProveedoresSubirComponent
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
export class GruposTrabajoProveedoresRoutingModule {}
