import { NgModule } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { CommonModule } from '@angular/common';
import { MatIconModule } from '@angular/material/icon';
import { MatButtonModule } from '@angular/material/button';
import { TranslateModule } from '@ngx-translate/core';
import { FuseSharedModule } from '@fuse/shared.module';
import { MatDividerModule } from '@angular/material/divider';
import { MatTooltipModule } from '@angular/material/tooltip';
import { RouterModule, Routes } from '@angular/router';
import { ListarEtapasComponent } from './listar-etapas/listar-etapas.component';
import { ModalAsignacionModule } from './modals/modal-asignacion/modal-asignacion.module';
import { ModalVerDetalleModule } from './modals/modal-ver-detalle/modal-ver-detalle.module';
import { ModalGestionFeDsModule } from './modals/modal-gestion-fe-ds/modal-gestion-fe-ds.module';
import { GestionDocumentosService } from '../../../../../services/proyectos-especiales/recepcion/emssanar/gestion-documentos.service';
import { ModalDatosContabilizadoModule } from './modals/modal-datos-contabilizado/modal-datos-contabilizado.module';
import { FiltrosGestionDocumentosModule } from './../../../../commons/filtros-gestion-documentos/filtros-gestion-documentos.module';
import { GestionDocumentosTrackingModule } from '../../../../commons/gestion-documentos-tracking/gestion-documentos-tracking.module';

const routes: Routes = [
    {
        path: 'fe-doc-soporte-electronico',
        component: ListarEtapasComponent,
        data: {
            breadcrum: 'Fe/Doc Soporte Electrónico',
            etapa: 1
        }
    },
    {
        path: 'pendiente-revision',
        component: ListarEtapasComponent,
        data: {
            breadcrum: 'Pendiente Revisión',
            etapa: 2
        }
    },
    {
        path: 'pendiente-aprobar-conformidad',
        component: ListarEtapasComponent,
        data: {
            breadcrum: 'Pendiente Aprobar Conformidad',
            etapa: 3
        }
    },
    {
        path: 'pendiente-reconocimiento-contable',
        component: ListarEtapasComponent,
        data: {
            breadcrum: 'Pendiente Reconocimiento Contable',
            etapa: 4
        }
    },
    {
        path: 'pendiente-revision-impuestos',
        component: ListarEtapasComponent,
        data: {
            breadcrum: 'Pendiente Revisión de Impuestos',
            etapa: 5
        }
    },
    {
        path: 'pendiente-pago',
        component: ListarEtapasComponent,
        data: {
            breadcrum: 'Pendiente de Pago',
            etapa: 6
        }
    },
    {
        path: 'fe-doc-soporte-electronico-gestionado',
        component: ListarEtapasComponent,
        data: {
            breadcrum: 'Fe/Doc Soporte Electrónico Gestionado',
            etapa: 7
        }
    },
];

@NgModule({
    declarations: [
        ListarEtapasComponent
    ],
    imports: [
        RouterModule.forChild(routes),
        TranslateModule,
        FuseSharedModule,
        MatIconModule,
        MatButtonModule,
        MatDividerModule,
        MatTooltipModule,
        CommonModule,
        FormsModule,

        FiltrosGestionDocumentosModule,
        GestionDocumentosTrackingModule,
        ModalGestionFeDsModule,
        ModalAsignacionModule,
        ModalDatosContabilizadoModule,
        ModalVerDetalleModule
    ],
    providers: [ GestionDocumentosService ],
    exports: [ RouterModule ]

})

export class GestionDocumentosModule {}
