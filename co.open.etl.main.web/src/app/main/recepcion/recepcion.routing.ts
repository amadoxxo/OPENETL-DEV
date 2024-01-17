import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { CargarAnexosComponent } from './documentos-anexos/cargar-anexos/cargar-anexos.component';
import { ReporteDependeciasComponent } from './reportes/reporte-dependencias/reporte-dependencias.component';
import { DocumentosManualesComponent } from './documentos-manuales/documentos-manuales.component';
import { DocumentosRecibidosComponent } from './documentos-recibidos/documentos-recibidos.component';
import { LogErroresAnexosComponent} from './documentos-anexos/log-errores-anexos/log-errores-anexos.component';
import { DocumentosNoElectronicosComponent } from './documentos-no-electronicos/documentos-no-electronicos.component';
import { CorreosRecibidosComponent } from './correos-recibidos/correos-recibidos.component';
import { ReportesBackgroundComponent } from '../reportes/reportes-background/reportes-background.component';
import { ValidacionDocumentosComponent } from './validacion-documentos/validacion-documentos.component';
import { DocumentosProcesadosComponent } from './reportes/documentos-procesados/documentos-procesados.component';
import { LogValidacionDocumentosComponent } from './reportes/log-validacion-documentos/log-validacion-documentos.component';

const routes: Routes = [
    {
        path: 'documentos-recibidos',
        component: DocumentosRecibidosComponent,
    },
    {
        path: 'documentos-manuales',
        component: DocumentosManualesComponent,
    },
    {
        path: 'documentos-anexos/cargar-anexos',
        component: CargarAnexosComponent,
    },
    {
        path: 'documentos-anexos/log-errores-anexos',
        component: LogErroresAnexosComponent,
    },
    {
        path: 'documentos-no-electronicos',
        component: DocumentosNoElectronicosComponent,
    },
    {
        path: 'documentos-no-electronicos/ver-documento/:ofe_id/:cdo_id',
        component: DocumentosNoElectronicosComponent,
    },
    {
        path: 'documentos-no-electronicos/editar-documento/:ofe_id/:cdo_id',
        component: DocumentosNoElectronicosComponent,
    },
    {
        path: 'correos-recibidos',
        component: CorreosRecibidosComponent,
    },
    {
        path: 'documentos-manuales/asociar/:epm_id',
        component: DocumentosManualesComponent,
    },
    {
        path: 'documentos-anexos/cargar-anexos/asociar/:epm_id',
        component: CargarAnexosComponent,
    },
    {
        path: 'validacion-documentos',
        component: ValidacionDocumentosComponent
    },
    {
        path: 'reportes/documentos-procesados',
        component: DocumentosProcesadosComponent
    },
    {
        path: 'reportes/log-validacion-documentos',
        component: LogValidacionDocumentosComponent
    },
    {
        path: 'reportes/reporte-dependencias',
        component: ReporteDependeciasComponent
    },
    {
        path: 'reportes/background',
        component: ReportesBackgroundComponent
    },
    {
        path: 'gestion-documentos',
        loadChildren: () => import('../proyectos-especiales/recepcion/emssanar/gestion-documentos/gestion-documentos.module').then( mod => mod.GestionDocumentosModule) 
    },
    {
        path: 'autorizaciones',
        loadChildren: () => import('../proyectos-especiales/recepcion/emssanar/autorizaciones/autorizaciones.module').then(m => m.AutorizacionesModule)
    }
];

@NgModule({
    imports: [
        RouterModule.forChild( routes )
    ],
    exports: [
        RouterModule
    ]
})
export class RecepcionRoutingModule { }
