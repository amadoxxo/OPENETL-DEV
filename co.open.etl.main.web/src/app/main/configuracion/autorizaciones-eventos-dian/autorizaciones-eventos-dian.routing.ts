import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { AutorizacionesEventosDianGestionarComponent } from './gestionar/autorizaciones-eventos-dian-gestionar.component';
import { AutorizacionesEventosDianListarComponent } from './listar/autorizaciones-eventos-dian-listar.component';
import { AutorizacionesEventosDianSubirComponent } from './subir/autorizaciones-eventos-dian-subir.component';

const routes: Routes = [
    {
        path: '',
        component: AutorizacionesEventosDianListarComponent
    },
    {
        path: 'nuevo-autorizaciones-eventos-dian',
        component: AutorizacionesEventosDianGestionarComponent
    },
    {
        path: 'editar-autorizaciones-eventos-dian/:use_identificador',
        component: AutorizacionesEventosDianGestionarComponent
    },
    {
        path: 'ver-autorizaciones-eventos-dian/:use_identificador/:use_id',
        component: AutorizacionesEventosDianGestionarComponent
    },
    {
        path: 'subir-autorizaciones-eventos-dian',
        component: AutorizacionesEventosDianSubirComponent
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
export class AutorizacionesEventosDianRoutingModule {}
