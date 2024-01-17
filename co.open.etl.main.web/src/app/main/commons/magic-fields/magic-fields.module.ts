import {CUSTOM_ELEMENTS_SCHEMA, NgModule} from '@angular/core';
import { CommonModule } from '@angular/common';
import { MagicFieldsComponent } from './magic-fields.component';
import {FlexLayoutModule} from '@angular/flex-layout';

@NgModule({
    declarations: [MagicFieldsComponent],
    exports: [MagicFieldsComponent],
    imports: [
        CommonModule,
        FlexLayoutModule
    ],
    schemas: [ CUSTOM_ELEMENTS_SCHEMA]
})
export class MagicFieldsModule { }
