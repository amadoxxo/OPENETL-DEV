import { NgModule } from '@angular/core';
import { RouterModule } from '@angular/router';
import { DebidaDiligenciaComponent } from './debida_diligencia.component';

const routes = [
	{
		path: '',
		component: DebidaDiligenciaComponent
	}
];

@NgModule({
	imports: [
		RouterModule.forChild( routes )
	],
	exports: [
		RouterModule
	]
})
export class DebidaDiligenciaRoutingModule {}
