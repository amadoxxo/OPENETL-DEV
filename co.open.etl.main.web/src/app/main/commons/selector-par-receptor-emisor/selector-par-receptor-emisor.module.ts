import {CUSTOM_ELEMENTS_SCHEMA, NgModule} from '@angular/core';
import {CommonModule} from '@angular/common';
import {SelectorParReceptorEmisorComponent} from './selector-par-receptor-emisor.component';
import {FlexLayoutModule} from '@angular/flex-layout';
import {FuseSharedModule} from '../../../../@fuse/shared.module';
import {OferentesService} from '../../../services/configuracion/oferentes.service';
import {NgSelectModule} from '@ng-select/ng-select';
import { GestionDocumentosService } from '../../../services/proyectos-especiales/recepcion/emssanar/gestion-documentos.service';


@NgModule({
    declarations: [SelectorParReceptorEmisorComponent],
    imports: [
        CommonModule,
        FlexLayoutModule,
        FuseSharedModule,
        NgSelectModule
    ],
    exports: [SelectorParReceptorEmisorComponent],
    schemas: [ CUSTOM_ELEMENTS_SCHEMA],
    providers: [OferentesService, GestionDocumentosService]
})
export class SelectorParReceptorEmisorModule {}
