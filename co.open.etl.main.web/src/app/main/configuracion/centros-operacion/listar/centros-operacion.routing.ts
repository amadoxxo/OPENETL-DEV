import { NgModule } from '@angular/core';
import { RouterModule } from '@angular/router';
import { CentrosOperacionComponent } from './centros-operacion.component';

const routes = [
	{
		path: '',
		component: CentrosOperacionComponent
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
export class CentrosOperacionRoutingModule {}
