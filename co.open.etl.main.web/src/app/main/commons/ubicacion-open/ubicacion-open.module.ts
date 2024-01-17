import {CUSTOM_ELEMENTS_SCHEMA, NgModule} from '@angular/core';
import {CommonModule} from '@angular/common';
import {UbicacionOpenComponent} from './ubicacion-open.component';
import {NgSelectModule} from '@ng-select/ng-select';
import {MatFormFieldModule} from '@angular/material/form-field';
import {MatIconModule} from '@angular/material/icon';
import {MatInputModule} from '@angular/material/input';
import {MatTooltipModule} from '@angular/material/tooltip';
import {MatButtonModule} from '@angular/material/button';
import {FlexLayoutModule} from '@angular/flex-layout';
import {FuseSharedModule} from '../../../../@fuse/shared.module';
import {PaisesService} from '../../../services/parametros/paises.service';
import {MunicipiosService} from '../../../services/parametros/municipios.service';
import {DepartamentosService} from '../../../services/parametros/departamentos.service';

@NgModule({
    declarations: [UbicacionOpenComponent],
    imports: [
        CommonModule,
        FlexLayoutModule,
        NgSelectModule,
        MatIconModule,
        MatFormFieldModule,
        MatInputModule,
        MatTooltipModule,
        MatButtonModule,
        FuseSharedModule,
    ],
    schemas: [CUSTOM_ELEMENTS_SCHEMA],
    exports: [UbicacionOpenComponent],
    providers: [PaisesService, MunicipiosService, DepartamentosService]
})
export class UbicacionOpenModule {}
