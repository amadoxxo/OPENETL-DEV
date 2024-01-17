import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import {UsuariosSubirComponent} from './usuarios_subir.component';

const routes: Routes = [
    {
        path: 'subir-usuarios',
        component: UsuariosSubirComponent
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
export class UsuariosSubirRoutingModule { }
