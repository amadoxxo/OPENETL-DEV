import { NgModule } from '@angular/core';
import { RouterModule } from '@angular/router';
import { CentrosCostoComponent } from './centros-costo.component';

const routes = [
	{
		path: '',
		component: CentrosCostoComponent
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
export class CentrosCostoRoutingModule {}
