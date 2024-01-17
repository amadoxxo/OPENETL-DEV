import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { AuthGuard } from '../../../../auth.guard';
import {FestivosComponent} from './festivos.component';

const routes: Routes = [
    {
        path: '',
        component: FestivosComponent
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
export class FestivosRoutingModule { }
