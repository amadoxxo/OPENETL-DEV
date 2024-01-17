import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { UsuariosEcmGestionarComponent } from './gestionar/usuarios-ecm-gestionar.component';
import { UsuariosEcmListarComponent } from './listar/usuarios-ecm-listar.component';
import { UsuariosEcmSubirComponent } from './subir/usuarios-ecm-subir.component';

const routes: Routes = [
    {
        path: '',
        component: UsuariosEcmListarComponent
    },
    {
        path: 'nuevo-usuario-ecm',
        component: UsuariosEcmGestionarComponent
    },
    {
        path: 'editar-usuario-ecm/:use_identificador',
        component: UsuariosEcmGestionarComponent
    },
    {
        path: 'ver-usuario-ecm/:use_identificador/:use_id',
        component: UsuariosEcmGestionarComponent
    },
    {
        path: 'subir-usuarios-ecm',
        component: UsuariosEcmSubirComponent
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
export class UsuariosEcmRoutingModule {}
