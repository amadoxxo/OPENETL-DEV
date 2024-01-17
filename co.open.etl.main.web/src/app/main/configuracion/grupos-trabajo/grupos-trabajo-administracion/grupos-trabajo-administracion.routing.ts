import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { GruposTrabajoListarComponent } from './listar/grupos-trabajo-listar.component';
import { GruposTrabajoSubirComponent } from './subir/grupos-trabajo-subir.component';

const routes: Routes = [
    {
        path: '',
        component: GruposTrabajoListarComponent
    },
    {
        path: 'subir-grupos-trabajo',
        component: GruposTrabajoSubirComponent
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
export class GruposTrabajoAdministracionRoutingModule {}
