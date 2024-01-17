import { CUSTOM_ELEMENTS_SCHEMA, NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FlexLayoutModule } from '@angular/flex-layout';
import { FormsModule } from '@angular/forms';
import { FuseSharedModule } from '../../../../@fuse/shared.module';
import { TagInputModule } from 'ngx-chips';
import { DatosEventoDianComponent } from './datos-evento-dian.component';
import { MatExpansionModule } from '@angular/material/expansion';
import { MatInputModule } from '@angular/material/input';
import { NgSelectModule } from '@ng-select/ng-select';
import { MatIconModule } from '@angular/material/icon';
import { MatButtonModule } from '@angular/material/button';

@NgModule({
    declarations: [
        DatosEventoDianComponent
    ],
    imports: [
        CommonModule,
        FlexLayoutModule,
        FormsModule,
        FuseSharedModule,
        TagInputModule,
        MatExpansionModule,
        MatInputModule,
        NgSelectModule,
        MatIconModule,
        MatButtonModule
    ],
    exports: [DatosEventoDianComponent],
    schemas: [CUSTOM_ELEMENTS_SCHEMA],
})
export class DatosEventoDianModule {}
