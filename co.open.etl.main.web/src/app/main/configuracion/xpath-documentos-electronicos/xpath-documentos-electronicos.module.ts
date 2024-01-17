import {TranslateModule} from '@ngx-translate/core';
import {NgSelectModule} from '@ng-select/ng-select';
import {MatIconModule} from '@angular/material/icon';
import {MatButtonModule} from '@angular/material/button';
import {MatDialogModule} from '@angular/material/dialog';
import {CUSTOM_ELEMENTS_SCHEMA, NgModule} from '@angular/core';
import {FuseSharedModule} from '../../../../@fuse/shared.module';
import {CommonsService} from '../../../services/commons/commons.service';
import {MagicFieldsModule} from '../../commons/magic-fields/magic-fields.module';
import {MatFormFieldModule} from '@angular/material/form-field';
import {MatInputModule} from '@angular/material/input';
import {OpenTrackingModule} from '../../commons/open-tracking/open-tracking.module';
import {SelectorOfePrecargadoModule} from '../../commons/selector-ofe-precargado/selector-ofe-precargado.module';
import {XPathDocumentosElectronicosRoutingModule} from './xpath-documentos-electronicos.routing';

import {XPathDocumentosElectronicosListarComponent} from './listar/xpath-documentos-electronicos-listar.component';
import {XPathDocumentosElectronicosGestionarComponent} from './gestionar/xpath-documentos-electronicos-gestionar.component';

@NgModule({
    declarations: [
        XPathDocumentosElectronicosListarComponent,
        XPathDocumentosElectronicosGestionarComponent
    ],
    imports: [
        XPathDocumentosElectronicosRoutingModule,
        TranslateModule,
        FuseSharedModule,
        MatIconModule,
        MatButtonModule,
        MatFormFieldModule,
        MatInputModule,
        OpenTrackingModule,
        MagicFieldsModule,
        NgSelectModule, 
        MatDialogModule,
        SelectorOfePrecargadoModule
    ],
    providers: [
        CommonsService
    ],
    exports: [],
    schemas: [CUSTOM_ELEMENTS_SCHEMA]
})

export class XPathDocumentosElectronicosModule {}
