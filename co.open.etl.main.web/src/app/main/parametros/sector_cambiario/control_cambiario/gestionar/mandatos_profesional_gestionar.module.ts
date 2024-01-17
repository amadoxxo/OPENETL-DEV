import {NgModule} from '@angular/core';
import {FuseSharedModule} from '@fuse/shared.module';
import {MatIconModule} from '@angular/material/icon';
import {MatRadioModule} from '@angular/material/radio';
import {MatInputModule} from '@angular/material/input';
import {MatButtonModule} from '@angular/material/button';
import {MatDialogModule} from '@angular/material/dialog';
import {MatFormFieldModule} from '@angular/material/form-field';
import {MatDatepickerModule} from '@angular/material/datepicker';
import {MagicFieldsModule} from '../../../../commons/magic-fields/magic-fields.module';
import {SelectorParFechasVigenciaModule} from '../../../../commons/selector-par-fechas-vigencia/selector-par-fechas-vigencia.module';
import {AuthGuard} from 'app/auth.guard';
import {LoaderModule} from 'app/shared/loader/loader.module';
import {MandatoProfesionalGestionarComponent} from './mandatos_profesional_gestionar.component';


@NgModule({
    declarations: [
        MandatoProfesionalGestionarComponent
    ],
    imports     : [
        FuseSharedModule,
        LoaderModule,
        MagicFieldsModule,
        MatIconModule,
        MatFormFieldModule,
        MatDialogModule, 
        MatDatepickerModule,
        MatInputModule,
        MatRadioModule,
        MatButtonModule,
        SelectorParFechasVigenciaModule
    ],
    providers   : [
        AuthGuard,
    ]
})

export class MandatoProfesionalGestionarModule {}