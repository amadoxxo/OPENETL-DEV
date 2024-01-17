import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import {TiemposAceptacionTacitaComponent} from './tiempos_aceptacion_tacita.component';

const routes: Routes = [
    {
        path: '',
        component: TiemposAceptacionTacitaComponent
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
export class TiemposAceptacionTacitaRoutingModule { }
