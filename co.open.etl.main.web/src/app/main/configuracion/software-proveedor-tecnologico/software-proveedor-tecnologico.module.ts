import {CUSTOM_ELEMENTS_SCHEMA, NgModule} from '@angular/core';
import {MatButtonModule} from '@angular/material/button';
import {MatIconModule} from '@angular/material/icon';
import {TranslateModule} from '@ngx-translate/core';
import {FuseSharedModule} from '../../../../@fuse/shared.module';
import {NgSelectModule} from '@ng-select/ng-select';
import {MatRadioModule} from '@angular/material/radio';
import {MatDatepickerModule} from '@angular/material/datepicker';
import {MatFormFieldModule} from '@angular/material/form-field';
import {MatInputModule} from '@angular/material/input';
import {OpenTrackingModule} from '../../commons/open-tracking/open-tracking.module';
import {LogErroresModule} from '../../commons/log-errores/log-errores.module';
import {CargasMasivasModule} from '../../commons/cargas-masivas/cargas-masivas.module';

import {CommonsService} from '../../../services/commons/commons.service';
import {ParametrosService} from '../../../services/parametros/parametros.service';

import {SoftwareProveedorTecnologicoGestionarComponent} from './gestionar/software-proveedor-tecnologico-gestionar.component';
import {SoftwareProveedorTecnologicoListarComponent} from './listar/software-proveedor-tecnologico-listar.component';
import {SoftwareProveedorTecnologicoSubirComponent} from './subir/software-proveedor-tecnologico-subir.component';
import {SoftwareProveedorTecnologicoRoutingModule} from './software-proveedor-tecnologico.routing';

@NgModule({
    declarations: [
        SoftwareProveedorTecnologicoGestionarComponent,
        SoftwareProveedorTecnologicoListarComponent,
        SoftwareProveedorTecnologicoSubirComponent
    ],
    imports: [
        SoftwareProveedorTecnologicoRoutingModule,
        TranslateModule,
        FuseSharedModule,
        MatButtonModule,
        MatIconModule,
        MatFormFieldModule,
        MatInputModule,
        OpenTrackingModule,
        LogErroresModule,
        CargasMasivasModule,
        MatRadioModule,
        MatDatepickerModule,
        NgSelectModule
    ],
    providers: [
        CommonsService,
        ParametrosService
    ],
    exports: [],
    schemas: [ CUSTOM_ELEMENTS_SCHEMA]
})

export class SoftwareProveedorTecnologicoModule {
}
