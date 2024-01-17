import {NgModule} from '@angular/core';
import {AuthGuard} from 'app/auth.guard';
import {MatOptionModule} from '@angular/material/core';
import {FuseSharedModule} from '@fuse/shared.module';
import {MatIconModule} from '@angular/material/icon';
import {NgSelectModule} from '@ng-select/ng-select';
import {MatSelectModule} from '@angular/material/select';
import {MatInputModule} from '@angular/material/input';
import {MatButtonModule} from '@angular/material/button';
import {MatDialogModule} from '@angular/material/dialog';
import {MatFormFieldModule} from '@angular/material/form-field';
import {MatAutocompleteModule} from '@angular/material/autocomplete';
import {MatDatepickerModule} from '@angular/material/datepicker'
import {LoaderModule} from 'app/shared/loader/loader.module';
import {ModalEventosDocumentosComponent} from './modal-eventos-documentos.component';
import {BaseService} from '../../../services/core/base.service';
import {DatosParametricosValidacionService} from './../../../services/proyectos-especiales/recepcion/fnc/validacion/datos-parametricos-validacion.service';
import {ValidacionDocumentosService} from "./../../../services/recepcion/validacion_documentos.service";

@NgModule({
    declarations: [
        ModalEventosDocumentosComponent
    ],
    imports: [
        FuseSharedModule,
        LoaderModule,
        MatIconModule,
        MatFormFieldModule,
        MatDialogModule, 
        MatInputModule,
        MatOptionModule,
        MatAutocompleteModule,
        MatDatepickerModule,
        MatButtonModule,
        NgSelectModule,
        MatSelectModule
    ],
    providers   : [
        AuthGuard,
        BaseService,
        DatosParametricosValidacionService,
        ValidacionDocumentosService
    ]
})

export class ModalEventosDocumentosModule {}