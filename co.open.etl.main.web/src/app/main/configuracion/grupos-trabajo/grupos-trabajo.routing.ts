import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';

const routes: Routes = [
    {
        path: 'administracion',
        loadChildren: () => import('./grupos-trabajo-administracion/grupos-trabajo-administracion.module').then( adm => adm.GruposTrabajoAdministracionModule ) 
    },
    {
        path: 'asociar-proveedores',
        loadChildren: () => import('./grupos-trabajo-proveedores/grupos-trabajo-proveedores.module').then( pro => pro.GruposTrabajoProveedoresModule ) 
    },
    {
        path: 'asociar-usuarios',
        loadChildren: () => import('./grupos-trabajo-usuarios/grupos-trabajo-usuarios.module').then( usu => usu.GruposTrabajoUsuariosModule ) 
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
export class GruposTrabajoRoutingModule {}
