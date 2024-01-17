import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { AuthGuard } from '../../../../auth.guard';
import {RolesComponent} from './roles.component';

const routes: Routes = [
    {
        path: '',
        component: RolesComponent,
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
export class RolesRoutingModule { }
