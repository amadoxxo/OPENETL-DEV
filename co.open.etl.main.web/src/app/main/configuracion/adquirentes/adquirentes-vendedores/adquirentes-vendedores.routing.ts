import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { AdquirentesVendedoresGestionarComponent } from './gestionar/adquirentes-vendedores-gestionar.component';
import { AdquirentesVendedoresListarComponent } from './listar/adquirentes-vendedores-listar.component';
import { AdquirentesVendedoresSubirComponent } from './subir/adquirentes-vendedores-subir.component';

const routes: Routes = [
    {
        path: '',
        component: AdquirentesVendedoresListarComponent
    },
    {
        path: 'nuevo-vendedor',
        component: AdquirentesVendedoresGestionarComponent
    },
    {
        path: 'editar-vendedor/:adq_identificacion/:ofe_identificacion',
        component: AdquirentesVendedoresGestionarComponent
    },
    {
        path: 'editar-vendedor/:adq_identificacion/:ofe_identificacion/:adq_id_personalizado',
        component: AdquirentesVendedoresGestionarComponent
    },
    {
        path: 'ver-vendedor/:adq_identificacion/:adq_id/:ofe_identificacion',
        component: AdquirentesVendedoresGestionarComponent
    },
    {
        path: 'ver-vendedor/:adq_identificacion/:adq_id/:ofe_identificacion/:adq_id_personalizado',
        component: AdquirentesVendedoresGestionarComponent
    },
    {
        path: 'subir-vendedores',
        component: AdquirentesVendedoresSubirComponent
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
export class AdquirentesVendedoresRoutingModule {}
