import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import {RolesGestionarComponent} from './roles_gestionar.component';

const routes: Routes = [
    {
        path: 'nuevo-rol',
        component: RolesGestionarComponent,
    },
    {
        path: 'nuevo-rol',
        component: RolesGestionarComponent,
    },
    {
        path: 'editar-rol/:rol_id',
        component: RolesGestionarComponent,
    },
    {
        path: 'ver-rol/:rol_id/:rol_codigo',
        component: RolesGestionarComponent,
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
export class RolesGestionarRoutingModule { }
