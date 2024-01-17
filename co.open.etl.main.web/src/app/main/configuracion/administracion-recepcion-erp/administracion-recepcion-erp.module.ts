import {CUSTOM_ELEMENTS_SCHEMA, NgModule} from '@angular/core';
import {MatButtonModule} from '@angular/material/button';
import {MatIconModule} from '@angular/material/icon';
import {TranslateModule} from '@ngx-translate/core';
import {FuseSharedModule} from '../../../../@fuse/shared.module';
import {MagicFieldsModule} from '../../commons/magic-fields/magic-fields.module';
import {NgSelectModule} from '@ng-select/ng-select';
import {MatFormFieldModule} from '@angular/material/form-field';
import {MatInputModule} from '@angular/material/input';
import {MatExpansionModule} from '@angular/material/expansion';
import {MatAutocompleteModule} from '@angular/material/autocomplete';
import {CommonsService} from '../../../services/commons/commons.service';
import {OpenTrackingModule} from '../../commons/open-tracking/open-tracking.module';
import {AdministracionRecepcionErpListarComponent} from './listar/administracion-recepcion-erp-listar.component';
import {AdministracionRecepcionErpGestionarComponent} from './gestionar/administracion-recepcion-erp-gestionar.component';
import {SelectorOfePrecargadoModule} from '../../commons/selector-ofe-precargado/selector-ofe-precargado.module';
import {AdministracionRecepcionErpRoutingModule} from './administracion-recepcion-erp.routing';

@NgModule({
    declarations: [
        AdministracionRecepcionErpGestionarComponent,
        AdministracionRecepcionErpListarComponent
    ],
    imports: [
        AdministracionRecepcionErpRoutingModule,
        TranslateModule,
        FuseSharedModule,
        MagicFieldsModule,
        MatButtonModule,
        MatIconModule,
        MatFormFieldModule,
        MatInputModule,
        OpenTrackingModule,
        MatExpansionModule,
        MatAutocompleteModule,
        NgSelectModule,
        SelectorOfePrecargadoModule
    ],
    providers: [
        CommonsService
    ],
    exports: [],
    schemas: [CUSTOM_ELEMENTS_SCHEMA]
})

export class AdministracionRecepcionErpModule {}
