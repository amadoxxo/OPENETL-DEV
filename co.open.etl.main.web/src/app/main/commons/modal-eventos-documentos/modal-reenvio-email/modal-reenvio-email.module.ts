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
import {ModalReenvioEmailComponent} from './modal-reenvio-email.component';
import {BaseService} from '../../../services/core/base.service';
import {NotificacionesModule} from '../notificaciones/notificaciones.module';

@NgModule({
    declarations: [
        ModalReenvioEmailComponent
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
        NotificacionesModule
    ],
    providers   : [
        AuthGuard,
        BaseService
    ]
})

export class ModalReenvioEmailModule {}