import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { RadianActoresListarComponent } from './listar/radian-actores-listar.component';
import { RadianActoresGestionarComponent } from './gestionar/radian-actores-gestionar.component';
import { RadianActoresSubirComponent } from './subir/radian-actores-subir.component';

const routes: Routes = [
    {
        path: '',
        component: RadianActoresListarComponent
    },
    {
        path: 'nuevo-actor',
        component: RadianActoresGestionarComponent
    },
    {
        path: 'editar-actor/:act_identificacion',
        component: RadianActoresGestionarComponent
    },
    {
        path: 'ver-actor/:act_identificacion/:act_id',
        component: RadianActoresGestionarComponent
    },
    {
        path: 'subir-actor',
        component: RadianActoresSubirComponent
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

export class RadianActoresRoutingModule {}
