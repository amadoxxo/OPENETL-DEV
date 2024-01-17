import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { AdquirentesAdquirentesGestionarComponent } from './gestionar/adquirentes-adquirentes-gestionar.component';
import { AdquirentesAdquirentesListarComponent } from './listar/adquirentes-adquirentes-listar.component';
import { AdquirentesAdquirentesSubirComponent } from './subir/adquirentes-adquirentes-subir.component';

const routes: Routes = [
    {
        path: '',
        component: AdquirentesAdquirentesListarComponent
    },
    {
        path: 'nuevo-adquirente',
        component: AdquirentesAdquirentesGestionarComponent
    },
    {
        path: 'editar-adquirente/:adq_identificacion/:ofe_identificacion',
        component: AdquirentesAdquirentesGestionarComponent
    },
    {
        path: 'editar-adquirente/:adq_identificacion/:ofe_identificacion/:adq_id_personalizado',
        component: AdquirentesAdquirentesGestionarComponent
    },
    {
        path: 'ver-adquirente/:adq_identificacion/:adq_id/:ofe_identificacion',
        component: AdquirentesAdquirentesGestionarComponent
    },
    {
        path: 'ver-adquirente/:adq_identificacion/:adq_id/:ofe_identificacion/:adq_id_personalizado',
        component: AdquirentesAdquirentesGestionarComponent
    },
    {
        path: 'subir-adquirentes',
        component: AdquirentesAdquirentesSubirComponent
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
export class AdquirentesAdquirentesRoutingModule {}
