import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { MatIconModule } from '@angular/material/icon';
import { MatButtonModule } from '@angular/material/button';
import { TranslateModule } from '@ngx-translate/core';
import { FuseSharedModule } from '@fuse/shared.module';
import { MatDividerModule } from '@angular/material/divider';
import { MatTooltipModule } from '@angular/material/tooltip';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { RouterModule, Routes } from '@angular/router';
import { FlexLayoutModule } from '@angular/flex-layout';
import { AutorizacionesService } from '../../../../../services/proyectos-especiales/recepcion/emssanar/autorizaciones.service';
import { AutorizacionEtapasComponent } from './autorizacion-etapas/autorizacion-etapas.component';
import { FiltrosGestionDocumentosModule } from '../../../../commons/filtros-gestion-documentos/filtros-gestion-documentos.module';

const routes: Routes = [
    {
        path: 'autorizacion-etapas',
        component: AutorizacionEtapasComponent,
        data: {
            breadcrum: 'Autorizacion Etapas',
        }
    },
];

@NgModule({
    declarations: [
        AutorizacionEtapasComponent
    ],
    imports: [
        RouterModule.forChild(routes),
        TranslateModule,
        FuseSharedModule,
        MatIconModule,
        MatButtonModule,
        MatDividerModule,
        MatTooltipModule,
        MatFormFieldModule,
        FlexLayoutModule,
        MatInputModule,
        CommonModule,

        FiltrosGestionDocumentosModule

    ],
    providers: [ AutorizacionesService ],
    exports: [ RouterModule ]

})

export class AutorizacionesModule {}
