import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';

const routes: Routes = [
    {
        path: 'tiempos-aceptacion-tacita',
        loadChildren: () => import('./tiempos_aceptacion_tacita/listar/tiempos_aceptacion_tacita.module').then( t => t.TiemposAceptacionTacitaModule) 
    },
    {
        path: 'festivos',
        loadChildren: () => import('./festivos/listar/festivos.module').then( fest => fest.FestivosModule),
    },
    {
        path: 'roles',
        loadChildren: () => import('./roles/listar/roles.module').then( rol => rol.RolesModule) 
    },
    {
        path: 'usuarios',
        loadChildren: () => import('./usuarios/listar/usuarios.module').then( usu => usu.UsuariosModule) 
    },
    {
        path: 'variables-sistema',
        loadChildren: () => import('./variables_sistema/listar/variables_sistema.module').then( vars => vars.VariablesSistemaModule) 
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
export class SistemaRoutingModule {}
