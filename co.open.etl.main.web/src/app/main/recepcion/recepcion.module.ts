import { NgModule } from '@angular/core';
import { TranslateModule } from '@ngx-translate/core';
import { FuseSharedModule } from '@fuse/shared.module';
import { NgSelectModule } from '@ng-select/ng-select';
import { LogErroresModule } from '../commons/log-errores/log-errores.module';
import { NgxFileDropModule } from 'ngx-file-drop';
import { SelectorParFechasModule } from '../commons/selector-par-fechas/selector-par-fechas.module';
import { SelectorLoteModule } from '../commons/selector-lote/selector-lote.module';
import { SelectorOfePrecargadoModule } from '../commons/selector-ofe-precargado/selector-ofe-precargado.module';
import { SelectorParReceptorEmisorModule } from '../commons/selector-par-receptor-emisor/selector-par-receptor-emisor.module';
import { DocumentosTrackingModule } from '../commons/documentos-tracking/documentos-tracking.module';
import { DocumentosRecibidosComponent } from './documentos-recibidos/documentos-recibidos.component';
import { CorreosRecibidosComponent } from './correos-recibidos/correos-recibidos.component';
import { ModalDocumentosListaModule } from '../modals/modal-documentos-lista/modal-documentos-lista.module';
import { ModalDocumentosAnexosModule } from '../modals/modal-documentos-anexos/modal-documentos-anexos.module';
import { ModalEventosDocumentosModule } from '../commons/modal-eventos-documentos/modal-eventos-documentos.module';
import { ModalAsignarGrupoTrabajoDocumentosModule } from './../commons/modal-asignar-grupo-trabajo-documentos/modal-asignar-grupo-trabajo-documentos.module';
import { DocumentosManualesComponent } from './documentos-manuales/documentos-manuales.component';
import { DocumentosRecibidosService } from '../../services/recepcion/documentos_recibidos.service';
import { DocumentosManualesService } from '../../services/recepcion/documentos_manuales.service';
import { DocumentosAnexosService } from '../../services/emision/documentos_anexos.service';
import { CommonsService } from '../../services/commons/commons.service';
import { CorreosRecibidosService } from '../../services/recepcion/correos_recibidos.service';
import { CarteraVencidaModule } from './../commons/cartera-vencida/cartera-vencida.module';
import { CargarAnexosComponent } from './documentos-anexos/cargar-anexos/cargar-anexos.component';
import { LogErroresAnexosComponent } from './documentos-anexos/log-errores-anexos/log-errores-anexos.component';
import { DocumentosNoElectronicosComponent } from './documentos-no-electronicos/documentos-no-electronicos.component';
import { DocumentosNoElectronicosService } from '../../services/recepcion/documentos_no_electronicos.service';
import { ReportesBackgroundService } from '../../services/reportes/reportes_background.service';
import { MatButtonModule } from '@angular/material/button';
import { MatCheckboxModule } from '@angular/material/checkbox';
import { MatDatepickerModule } from '@angular/material/datepicker';
import { MatDialogModule } from '@angular/material/dialog';
import { MatDividerModule } from '@angular/material/divider';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import { MatOptionModule } from '@angular/material/core';
import { MatSelectModule } from '@angular/material/select';
import { MatTooltipModule } from '@angular/material/tooltip';
import { MatAutocompleteModule } from '@angular/material/autocomplete';
import { RecepcionRoutingModule } from './recepcion.routing';
import { OpenTrackingModule } from '../commons/open-tracking/open-tracking.module';
import { NominaElectronicaService } from '../../services/nomina-electronica/nomina_electronica.service';
import { ConfiguracionService } from '../../services/configuracion/configuracion.service';
import { OpenEcmService } from '../../services/ecm/openecm.service';
import { RadianService } from '../../services/radian/radian.service';
import { DocumentosService } from '../../services/emision/documentos.service';
import { ParametrosService } from '../../services/parametros/parametros.service';
import { DocumentosProcesadosService } from '../../services/reportes/documentos_procesados.service';
import { ValidacionDocumentosComponent } from './validacion-documentos/validacion-documentos.component';
import { DocumentosProcesadosComponent } from './reportes/documentos-procesados/documentos-procesados.component';
import { LogValidacionDocumentosComponent } from './reportes/log-validacion-documentos/log-validacion-documentos.component';
import { ReporteDependeciasComponent } from './reportes/reporte-dependencias/reporte-dependencias.component';
import { ReporteDependenciasService } from './../../services/recepcion/reporte_dependencias.service';

@NgModule({
    declarations: [
        DocumentosRecibidosComponent,
        CorreosRecibidosComponent,
        DocumentosManualesComponent,
        CargarAnexosComponent,
        LogErroresAnexosComponent,
        DocumentosNoElectronicosComponent,
        ValidacionDocumentosComponent,
        DocumentosProcesadosComponent,
        LogValidacionDocumentosComponent,
        ReporteDependeciasComponent
    ],
    imports: [
        OpenTrackingModule,
        RecepcionRoutingModule,
        TranslateModule,
        FuseSharedModule,
        NgxFileDropModule,
        NgSelectModule,
        
        ModalDocumentosListaModule,
        ModalDocumentosAnexosModule,
        ModalEventosDocumentosModule,
        ModalAsignarGrupoTrabajoDocumentosModule,
        SelectorOfePrecargadoModule,
        SelectorParFechasModule,
        SelectorLoteModule,
        SelectorParReceptorEmisorModule,
        DocumentosTrackingModule,
        LogErroresModule,
        MatButtonModule,
        MatCheckboxModule,
        MatDatepickerModule,
        MatDialogModule,
        MatDividerModule,
        MatFormFieldModule,
        MatIconModule,
        MatInputModule,
        MatOptionModule,
        MatSelectModule,
        MatTooltipModule,
        MatAutocompleteModule,
        LogErroresModule,
        CarteraVencidaModule
    ],
    providers: [
        DocumentosRecibidosService,
        DocumentosManualesService,
        DocumentosAnexosService,
        DocumentosNoElectronicosService,
        CorreosRecibidosService,
        ReportesBackgroundService,
        CommonsService,
        RadianService,
        NominaElectronicaService,
        ConfiguracionService,
        OpenEcmService,
        DocumentosService,
        ParametrosService,
        DocumentosProcesadosService,
        ReporteDependenciasService
    ]
})

export class RecepcionModule {}

