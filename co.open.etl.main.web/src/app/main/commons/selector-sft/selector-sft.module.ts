import {CUSTOM_ELEMENTS_SCHEMA, NgModule} from '@angular/core';
import {CommonModule} from '@angular/common';
import {SelectorSftComponent} from './selector-sft.component';
import {FlexLayoutModule} from '@angular/flex-layout';
import {FuseSharedModule} from '../../../../@fuse/shared.module';
import {ProveedorTecnologicoService} from '../../../services/configuracion/proveedor-tecnologico.service';
import {NgSelectModule} from '@ng-select/ng-select';


@NgModule({
    declarations: [SelectorSftComponent],
    imports: [
        CommonModule,
        FlexLayoutModule,
        FuseSharedModule,
        NgSelectModule
    ],
    exports: [SelectorSftComponent],
    schemas: [ CUSTOM_ELEMENTS_SCHEMA],
    providers: [ProveedorTecnologicoService]
})
export class SelectorSftModule {}
