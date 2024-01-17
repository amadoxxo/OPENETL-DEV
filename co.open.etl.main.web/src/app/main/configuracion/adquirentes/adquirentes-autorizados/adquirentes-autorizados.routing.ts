import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { AdquirentesAutorizadosGestionarComponent } from './gestionar/adquirentes-autorizados-gestionar.component';
import { AdquirentesAutorizadosListarComponent } from './listar/adquirentes-autorizados-listar.component';
import { AdquirentesAutorizadosSubirComponent } from './subir/adquirentes-autorizados-subir.component';

const routes: Routes = [
    {
        path: '',
        component: AdquirentesAutorizadosListarComponent
    },
    {
        path: 'nuevo-autorizado',
        component: AdquirentesAutorizadosGestionarComponent
    },
    {
        path: 'editar-autorizado/:adq_identificacion/:ofe_identificacion',
        component: AdquirentesAutorizadosGestionarComponent
    },
    {
        path: 'editar-autorizado/:adq_identificacion/:ofe_identificacion/:adq_id_personalizado',
        component: AdquirentesAutorizadosGestionarComponent
    },
    {
        path: 'ver-autorizado/:adq_identificacion/:adq_id/:ofe_identificacion',
        component: AdquirentesAutorizadosGestionarComponent
    },
    {
        path: 'ver-autorizado/:adq_identificacion/:adq_id/:ofe_identificacion/:adq_id_personalizado',
        component: AdquirentesAutorizadosGestionarComponent
    },
    {
        path: 'subir-autorizados',
        component: AdquirentesAutorizadosSubirComponent
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
export class AdquirentesAutorizadosRoutingModule {}
