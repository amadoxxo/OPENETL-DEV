import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { TrabajadoresGestionarComponent } from './gestionar/trabajadores-gestionar.component';
import { TrabajadoresListarComponent } from './listar/trabajadores-listar.component';
import { TrabajadoresSubirComponent } from './subir/trabajadores-subir.component';

const routes: Routes = [
    {
        path: '',
        component: TrabajadoresListarComponent
    },
    {
        path: 'nuevo-trabajador',
        component: TrabajadoresGestionarComponent
    },
    {
        path: 'editar-trabajador/:tra_identificacion/:emp_identificacion',
        component: TrabajadoresGestionarComponent
    },
    {
        path: 'ver-trabajador/:tra_identificacion/:tra_id/:emp_identificacion',
        component: TrabajadoresGestionarComponent
    },
    {
        path: 'subir-trabajadores',
        component: TrabajadoresSubirComponent
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
export class TrabajadoresRoutingModule {}
