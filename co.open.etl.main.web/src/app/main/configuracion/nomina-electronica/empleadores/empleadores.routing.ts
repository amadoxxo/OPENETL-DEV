import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { EmpleadoresGestionarComponent } from './gestionar/empleadores-gestionar.component';
import { EmpleadoresListarComponent } from './listar/empleadores-listar.component';
import { EmpleadoresSubirComponent } from './subir/empleadores-subir.component';

const routes: Routes = [
    {
        path: '',
        component: EmpleadoresListarComponent
    },
    {
        path: 'nuevo-empleador',
        component: EmpleadoresGestionarComponent
    },
    {
        path: 'editar-empleador/:emp_identificacion',
        component: EmpleadoresGestionarComponent
    },
    {
        path: 'ver-empleador/:emp_identificacion/:emp_id',
        component: EmpleadoresGestionarComponent
    },
    {
        path: 'subir-empleadores',
        component: EmpleadoresSubirComponent
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
export class EmpleadoresRoutingModule {}
