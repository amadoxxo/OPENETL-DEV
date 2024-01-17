import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { VariablesSistemaComponent } from './variables_sistema.component';

const routes: Routes = [
    {
        path: '',
        component: VariablesSistemaComponent
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
export class VariablesSistemaRoutingModule { }
