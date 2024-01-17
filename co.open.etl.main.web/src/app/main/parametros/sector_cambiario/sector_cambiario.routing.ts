import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';

const routes: Routes = [
    {
        path: 'mandatos-profesional-cambios',
        loadChildren: () => import('./control_cambiario/listar/mandatos_profesional.module').then( module => module.MandatosProfesionalModule ) 
    },
    {
        path: 'debida-diligencia',
        loadChildren: () => import('./debida_diligencia/listar/debida_diligencia.module').then( module => module.DebidaDiligenciaModule )
    }
];

@NgModule({
    imports: [
        RouterModule.forChild(routes)
    ],
    exports: [
        RouterModule
    ]
})
export class SectorCambiarioRoutingModule {}
