import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import {UsuariosGestionarComponent} from './usuarios_gestionar.component';

const routes: Routes = [
    {
        path: 'nuevo-usuario',
        component: UsuariosGestionarComponent,
    },
    {
        path: 'editar-usuario/:usu_id',
        component: UsuariosGestionarComponent,
    },
    {
        path: 'ver-usuario/:usu_id/:usu_identificacion',
        component: UsuariosGestionarComponent,
    }
];

@NgModule({
    imports: [
        RouterModule.forChild( routes )
    ],
    exports: [
        RouterModule
    ]
})
export class UsuariosGestionarRoutingModule { }
