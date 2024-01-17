import {CUSTOM_ELEMENTS_SCHEMA, NgModule} from '@angular/core';
import {CommonModule} from '@angular/common';
import {FlexLayoutModule} from '@angular/flex-layout';
import {FuseSharedModule} from '../../../../@fuse/shared.module';
import {AlertaVencimientosComponent} from './alerta-vencimientos.component';
import {CertificadosService} from '../../../services/certificados/certificados.service';
import {ConfiguracionService} from '../../../services/configuracion/configuracion.service';

@NgModule({
    declarations: [AlertaVencimientosComponent],
    imports: [
        CommonModule,
        FlexLayoutModule,
        FuseSharedModule
    ],
    exports: [AlertaVencimientosComponent],
    schemas: [CUSTOM_ELEMENTS_SCHEMA],
    providers: [
        CertificadosService,
        ConfiguracionService
    ]
})
export class AlertaVencimientosModule {}
