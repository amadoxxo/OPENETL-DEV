import {NgModule} from '@angular/core';
import {TranslateModule} from '@ngx-translate/core';
import {FuseSharedModule} from '@fuse/shared.module';
import {RadianModule} from './radian/radian.module';

// Services
import {PaisesService} from '../../services/parametros/paises.service';
import {DepartamentosService} from '../../services/parametros/departamentos.service';
import {MunicipiosService} from '../../services/parametros/municipios.service';
import {ParametrosService} from '../../services/parametros/parametros.service';
import {ParametrosRoutingModule} from './parametros.routing';

@NgModule({
    declarations: [],
    imports: [
        TranslateModule,
        FuseSharedModule,
        RadianModule,
        ParametrosRoutingModule
    ],
    providers: [
        PaisesService, 
        DepartamentosService, 
        MunicipiosService, 
        ParametrosService
    ],
    exports: []
})

export class ParametrosModule {}
