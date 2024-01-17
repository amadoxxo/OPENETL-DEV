import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { GruposTrabajoUsuariosListarComponent } from './listar/grupos-trabajo-usuarios-listar.component';
import { GruposTrabajoUsuariosSubirComponent } from './subir/grupos-trabajo-usuarios-subir.component';

const routes: Routes = [
    {
        path: '',
        component: GruposTrabajoUsuariosListarComponent
    },
    {
        path: 'subir-usuarios-asociados',
        component: GruposTrabajoUsuariosSubirComponent
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
export class GruposTrabajoUsuariosRoutingModule {}
