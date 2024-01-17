import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { AdquirentesResponsablesGestionarComponent } from './gestionar/adquirentes-responsables-gestionar.component';
import { AdquirentesResponsablesListarComponent } from './listar/adquirentes-responsables-listar.component';
import { AdquirentesResponsablesSubirComponent } from './subir/adquirentes-responsables-subir.component';

const routes: Routes = [
    {
        path: '',
        component: AdquirentesResponsablesListarComponent
    },
    {
        path: 'nuevo-responsable',
        component: AdquirentesResponsablesGestionarComponent
    },
    {
        path: 'editar-responsable/:adq_identificacion/:ofe_identificacion',
        component: AdquirentesResponsablesGestionarComponent
    },
    {
        path: 'editar-responsable/:adq_identificacion/:ofe_identificacion/:adq_id_personalizado',
        component: AdquirentesResponsablesGestionarComponent
    },
    {
        path: 'ver-responsable/:adq_identificacion/:adq_id/:ofe_identificacion',
        component: AdquirentesResponsablesGestionarComponent
    },
    {
        path: 'ver-responsable/:adq_identificacion/:adq_id/:ofe_identificacion/:adq_id_personalizado',
        component: AdquirentesResponsablesGestionarComponent
    },
    {
        path: 'subir-responsables',
        component: AdquirentesResponsablesSubirComponent
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
export class AdquirentesResponsablesRoutingModule {}
