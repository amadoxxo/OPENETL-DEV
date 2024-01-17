import {NgModule} from '@angular/core';
import {AuthGuard} from 'app/auth.guard';
import {MatOptionModule} from '@angular/material/core';
import {FuseSharedModule} from '@fuse/shared.module';
import {MatIconModule} from '@angular/material/icon';
import {NgSelectModule} from '@ng-select/ng-select';
import {MatInputModule} from '@angular/material/input';
import {MatButtonModule} from '@angular/material/button';
import {MatDialogModule} from '@angular/material/dialog';
import {MatFormFieldModule} from '@angular/material/form-field';
import {MatAutocompleteModule} from '@angular/material/autocomplete';
import {LoaderModule} from 'app/shared/loader/loader.module';
import {ModalConsultaResolucionDianComponent} from './modal-consulta-resolucion-dian.component';
import {BaseService} from '../../../services/core/base.service';
import {SelectorOfePrecargadoModule} from '../../commons/selector-ofe-precargado/selector-ofe-precargado.module';
import {ResolucionesService} from './../../../services/configuracion/resoluciones.service';

@NgModule({
    declarations: [
        ModalConsultaResolucionDianComponent
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
        MatButtonModule,
        NgSelectModule,
        SelectorOfePrecargadoModule
    ],
    providers   : [
        AuthGuard,
        BaseService,
        ResolucionesService
    ]
})

export class ModalConsultaResolucionDianModule {}