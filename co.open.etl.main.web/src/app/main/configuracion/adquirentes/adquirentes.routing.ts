import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';

const routes: Routes = [
    {
        path: 'adquirentes',
        loadChildren: () => import('./adquirentes-adquirentes/adquirentes-adquirentes.module').then( adq => adq.AdquirentesAdquirentesModule ) 
    },
    {
        path: 'autorizados',
        loadChildren: () => import('./adquirentes-autorizados/adquirentes-autorizados.module').then( aut => aut.AdquirentesAutorizadosModule ) 
    },
    {
        path: 'responsables',
        loadChildren: () => import('./adquirentes-responsables/adquirentes-responsables.module').then( res => res.AdquirentesResponsablesModule ) 
    },
    {
        path: 'vendedores',
        loadChildren: () => import('./adquirentes-vendedores/adquirentes-vendedores.module').then( ven => ven.AdquirentesVendedoresModule ) 
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
export class AdquirentesRoutingModule {}
