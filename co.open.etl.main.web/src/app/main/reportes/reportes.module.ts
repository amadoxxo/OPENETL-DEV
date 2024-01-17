import {NgModule} from '@angular/core';
import {TranslateModule} from '@ngx-translate/core';
import {FuseSharedModule} from '@fuse/shared.module';
import {MatIconModule} from '@angular/material/icon';
import {MatButtonModule} from '@angular/material/button';
import {NgSelectModule} from '@ng-select/ng-select';
import {MatCheckboxModule} from '@angular/material/checkbox';
import {MatDialogModule} from '@angular/material/dialog';
import {MatDatepickerModule} from '@angular/material/datepicker';
import {MatDividerModule} from '@angular/material/divider';
import {NgxFileDropModule} from 'ngx-file-drop';
import {SelectorParFechasModule} from '../commons/selector-par-fechas/selector-par-fechas.module';
import {SelectorLoteModule} from '../commons/selector-lote/selector-lote.module';
import {SelectorOfePrecargadoModule} from '../commons/selector-ofe-precargado/selector-ofe-precargado.module';
import {SelectorParEmisorReceptorModule} from '../commons/selector-par-emisor-receptor/selector-par-emisor-receptor.module';
import {DhlExpressComponent} from './dhl-express/dhl-express.component';
import {DocumentosProcesadosComponent} from './documentos-procesados/documentos-procesados.component';
import {NotificacionDocumentosComponent} from './notificacion-documentos/notificacion-documentos.component';
import {ReportesBackgroundComponent} from './reportes-background/reportes-background.component';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';

@NgModule({
    declarations: [],
    imports: [
        TranslateModule,
        FuseSharedModule,
        NgxFileDropModule,
        NgSelectModule,
        MatIconModule,
        MatButtonModule,
        MatCheckboxModule,
        MatFormFieldModule,
        MatInputModule,
        MatSelectModule,
        MatDatepickerModule,
        MatDividerModule,
        MatDialogModule,
        SelectorOfePrecargadoModule,
        SelectorParFechasModule,
        SelectorLoteModule,
        SelectorParEmisorReceptorModule
    ],
    providers: [],
    exports: [
        DhlExpressComponent,
        DocumentosProcesadosComponent,
        NotificacionDocumentosComponent,
        ReportesBackgroundComponent
    ]
})

export class ReportesModule {}
