import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { OferentesGestionarComponent } from './gestionar/oferentes-gestionar.component';
import { OferentesListarComponent } from './listar/oferentes-listar.component';
import { ConfiguracionDocumentoElectronicoComponent } from './configuracion-documento-electronico/configuracion-documento-electronico.component';
import { ConfiguracionDocumentoSoporteComponent } from './configuracion-documento-soporte/configuracion-documento-soporte.component';
import { ValoresPorDefectoDocumentoElectronicoComponent } from './valores-por-defecto-documento-electronico/valores-por-defecto-documento-electronico.component';
import { ConfiguracionServiciosComponent } from './configuracion-servicios/configuracion-servicios.component';
import { OferentesSubirComponent } from './subir/oferentes-subir.component';

const routes: Routes = [
    {
        path: '',
        component: OferentesListarComponent
    },
    {
        path: 'nuevo-oferente',
        component: OferentesGestionarComponent
    },
    {
        path: 'editar-oferente/:ofe_identificacion',
        component: OferentesGestionarComponent
    },
    {
        path: 'ver-oferente/:ofe_identificacion/:ofe_id',
        component: OferentesGestionarComponent
    },
    {
        path: 'configuracion-documento-electronico/:ofe_identificacion',
        component: ConfiguracionDocumentoElectronicoComponent
    },
    {
        path: 'configuracion-documento-soporte/:ofe_identificacion',
        component: ConfiguracionDocumentoSoporteComponent
    },
    {
        path: 'valores-por-defecto-documento-electronico/:ofe_identificacion',
        component: ValoresPorDefectoDocumentoElectronicoComponent
    },
    {
        path: 'configuracion-servicios/:ofe_identificacion',
        component: ConfiguracionServiciosComponent
    },
    {
        path: 'subir-oferentes',
        component: OferentesSubirComponent
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
export class OferentesRoutingModule {}
