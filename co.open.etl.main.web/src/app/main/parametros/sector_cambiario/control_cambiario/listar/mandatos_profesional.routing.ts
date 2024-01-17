import { NgModule } from '@angular/core';
import { RouterModule } from '@angular/router';
import { MandatosProfesionalComponent } from './mandatos_profesional.component';

const routes = [
	{
		path: '',
		component: MandatosProfesionalComponent
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
export class MandatosProfesionalRoutingModule {}
