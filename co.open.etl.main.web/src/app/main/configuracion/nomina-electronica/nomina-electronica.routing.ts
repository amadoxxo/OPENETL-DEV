import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';

const routes: Routes = [
    {
        path: 'empleadores',
        loadChildren: () => import('./empleadores/empleadores.module').then( emp => emp.EmpleadoresModule ) 
    },
    {
        path: 'trabajadores',
        loadChildren: () => import('./trabajadores/trabajadores.module').then( tra => tra.TrabajadoresModule ) 
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
export class NominaElectronicaRoutingModule {}
