import {CUSTOM_ELEMENTS_SCHEMA, NgModule} from '@angular/core';
import {CommonModule} from '@angular/common';
import {FlexLayoutModule} from '@angular/flex-layout';
import {FuseSharedModule} from '../../../../@fuse/shared.module';
import {NotificacionesComponent} from './notificaciones.component';
import {TagInputModule} from 'ngx-chips';

@NgModule({
    declarations: [
        NotificacionesComponent
    ],
    imports: [
        CommonModule,
        FlexLayoutModule,
        FuseSharedModule,
        TagInputModule
    ],
    exports: [NotificacionesComponent],
    schemas: [CUSTOM_ELEMENTS_SCHEMA],
})
export class NotificacionesModule {
}
