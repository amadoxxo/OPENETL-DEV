import { NgModule } from '@angular/core';
import { SectorCambiarioRoutingModule } from './sector_cambiario.routing';
import { DebidaDiligenciaRoutingModule } from './debida_diligencia/listar/debida_diligencia.routing';

@NgModule({
    imports: [
        SectorCambiarioRoutingModule,
        DebidaDiligenciaRoutingModule
    ]
})

export class SectorCambiarioModule {}
