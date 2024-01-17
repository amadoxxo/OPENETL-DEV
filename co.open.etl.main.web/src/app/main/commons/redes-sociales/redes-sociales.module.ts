import {CommonModule} from '@angular/common';
import {FlexLayoutModule} from '@angular/flex-layout';
import {CUSTOM_ELEMENTS_SCHEMA, NgModule} from '@angular/core';
import {RedesSocialesComponent} from './redes-sociales.component';
import {FuseSharedModule} from '../../../../@fuse/shared.module';
import {MatFormFieldModule} from '@angular/material/form-field';
import {MatIconModule} from '@angular/material/icon';
import {MatInputModule} from '@angular/material/input';

@NgModule({
    declarations: [RedesSocialesComponent],
    imports: [
        CommonModule,
        FlexLayoutModule,
        FuseSharedModule,
        MatIconModule,
        MatFormFieldModule,
        MatInputModule
    ],
    exports: [RedesSocialesComponent],
    schemas: [ CUSTOM_ELEMENTS_SCHEMA],
    providers: []
})

export class RedesSocialesModule {}
