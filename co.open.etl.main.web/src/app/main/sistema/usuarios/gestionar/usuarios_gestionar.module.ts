import {NgModule} from '@angular/core';
import {TranslateModule} from '@ngx-translate/core';
import {MatOptionModule} from '@angular/material/core';
import {NgSelectModule} from '@ng-select/ng-select';
import {FuseSharedModule} from '@fuse/shared.module';
import {MatIconModule} from '@angular/material/icon';
import {MatInputModule} from '@angular/material/input';
import {MatButtonModule} from '@angular/material/button';
import {MatSelectModule} from '@angular/material/select';
import {NgxDatatableModule} from '@swimlane/ngx-datatable';
import {MatTooltipModule} from '@angular/material/tooltip';
import {LoaderModule} from 'app/shared/loader/loader.module';
import {MatFormFieldModule} from '@angular/material/form-field';
import {MatSlideToggleModule} from '@angular/material/slide-toggle';
import {MagicFieldsModule} from '../../../commons/magic-fields/magic-fields.module';
import {MatStepperModule} from '@angular/material/stepper';

import {AuthGuard} from 'app/auth.guard';
import {UsuariosGestionarComponent} from './usuarios_gestionar.component';
import { UsuariosGestionarRoutingModule } from './usuarios_gestionar.routing';

@NgModule({
    declarations: [
        UsuariosGestionarComponent
    ],
    imports: [
        UsuariosGestionarRoutingModule,
        FuseSharedModule,
        MagicFieldsModule,
        TranslateModule,
        LoaderModule,
        MatIconModule,
        MatButtonModule,
        MatFormFieldModule,
        MatInputModule,
        MatStepperModule,
        MatSelectModule,
        MatOptionModule,
        MatTooltipModule,
        NgSelectModule,
        MatSlideToggleModule,
        NgxDatatableModule
    ],
    providers: [
        AuthGuard
    ]
})

export class UsuariosGestionarModule {
}
