import {CUSTOM_ELEMENTS_SCHEMA, NgModule} from '@angular/core';
import {CommonModule} from '@angular/common';
import {FlexLayoutModule} from '@angular/flex-layout';
import {MatOptionModule} from '@angular/material/core';
import {FuseSharedModule} from '../../../../@fuse/shared.module';
import {MatInputModule} from '@angular/material/input';
import {MatButtonModule} from '@angular/material/button';
import {MatSelectModule} from '@angular/material/select';
import {MatFormFieldModule} from '@angular/material/form-field';
import {CargasMasivasComponent} from './cargas-masivas.component';
import {CargasMasivasService} from '../../../services/commons/cargas_masivas.service';

@NgModule({
    declarations: [
        CargasMasivasComponent
    ],
    imports: [
        CommonModule,
        FlexLayoutModule,
        MatFormFieldModule,
        MatButtonModule,
        MatInputModule,
        MatOptionModule,
        MatSelectModule,
        FuseSharedModule,
    ],
    exports: [CargasMasivasComponent],
    schemas: [ CUSTOM_ELEMENTS_SCHEMA],
    providers: [CargasMasivasService]
})
export class CargasMasivasModule {}

