import {CUSTOM_ELEMENTS_SCHEMA, NgModule} from '@angular/core';
import {CommonModule} from '@angular/common';
import {InformacionAdicionalComponent} from './informacion-adicional.component';
import {MatFormFieldModule} from '@angular/material/form-field';
import {MatIconModule} from '@angular/material/icon';
import {MatInputModule} from '@angular/material/input';
import {FlexLayoutModule} from '@angular/flex-layout';
import {FuseSharedModule} from '../../../../@fuse/shared.module';
import {TagInputModule} from 'ngx-chips';

@NgModule({
    declarations: [InformacionAdicionalComponent],
    imports: [
        CommonModule,
        FlexLayoutModule,
        MatIconModule,
        MatFormFieldModule,
        MatInputModule,
        FuseSharedModule,
        TagInputModule
    ],
    schemas: [CUSTOM_ELEMENTS_SCHEMA],
    exports: [InformacionAdicionalComponent],
    providers: []
})
export class InformacionAdicionalModule {}

