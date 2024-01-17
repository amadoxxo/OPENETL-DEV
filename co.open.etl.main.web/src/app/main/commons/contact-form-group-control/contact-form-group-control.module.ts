import {CUSTOM_ELEMENTS_SCHEMA, NgModule} from '@angular/core';
import {CommonModule} from '@angular/common';
import {ContactFormGroupControlComponent} from './contact-form-group-control.component';
import {FlexLayoutModule} from '@angular/flex-layout';
import { MatButtonModule } from '@angular/material/button';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import {FuseSharedModule} from '../../../../@fuse/shared.module';

@NgModule({
    declarations: [ContactFormGroupControlComponent],
    imports: [
        CommonModule,
        FlexLayoutModule,
        MatIconModule,
        MatFormFieldModule,
        MatInputModule,
        MatButtonModule,
        MatIconModule,
        FuseSharedModule,
    ],
    schemas: [ CUSTOM_ELEMENTS_SCHEMA],
    exports: [ContactFormGroupControlComponent]
})
export class ContactFormGroupControlModule {}

