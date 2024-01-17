import { NgModule } from '@angular/core';
import { RouterModule } from '@angular/router';
import { ConceptosCorreccionComponent } from './conceptos_correccion.component';

const routes = [
	{
		path: '',
		component: ConceptosCorreccionComponent
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
export class ConceptosCorreccionRoutingModule {}
