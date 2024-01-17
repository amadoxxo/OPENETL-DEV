import {NgModule} from '@angular/core';
import {TranslateModule} from '@ngx-translate/core';
import {MatOptionModule} from '@angular/material/core';
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

import {AuthGuard} from 'app/auth.guard';
import {RolesGestionarComponent} from './roles_gestionar.component';
import { RolesGestionarRoutingModule } from './roles-gestionar.routing';

@NgModule({
    declarations: [
        RolesGestionarComponent
    ],
    imports: [
        RolesGestionarRoutingModule,
        FuseSharedModule,
        MagicFieldsModule,
        TranslateModule,
        LoaderModule,
        MatIconModule,
        MatButtonModule,
        MatFormFieldModule,
        MatInputModule,
        MatSelectModule,
        MatOptionModule,
        MatTooltipModule,
        MatSlideToggleModule,
        NgxDatatableModule
    ],
    providers: [
        AuthGuard
    ]
})

export class RolesGestionarModule {
}
