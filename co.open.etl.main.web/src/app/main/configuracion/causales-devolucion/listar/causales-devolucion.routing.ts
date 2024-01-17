import { NgModule } from '@angular/core';
import { RouterModule } from '@angular/router';
import { CausalesDevolucionComponent } from './causales-devolucion.component';

const routes = [
	{
		path: '',
		component: CausalesDevolucionComponent
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
export class CausalesDevolucionRoutingModule {}
