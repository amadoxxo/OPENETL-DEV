import {CUSTOM_ELEMENTS_SCHEMA, NgModule} from '@angular/core';
import {CommonModule} from '@angular/common';
import {SelectorUsuarioComponent} from './selector-usuario.component';
import {FlexLayoutModule} from '@angular/flex-layout';
import {FuseSharedModule} from '../../../../@fuse/shared.module';
import {NgSelectModule} from '@ng-select/ng-select';
import {UsuariosService} from '../../../services/sistema/usuarios.service';

@NgModule({
    declarations: [SelectorUsuarioComponent],
    imports: [
        CommonModule,
        FlexLayoutModule,
        FuseSharedModule,
        NgSelectModule
    ],
    exports: [SelectorUsuarioComponent],
    schemas: [ CUSTOM_ELEMENTS_SCHEMA],
    providers: [UsuariosService]
})
export class SelectorUsuarioModule {}
