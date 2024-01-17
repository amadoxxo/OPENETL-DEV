import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { XPathDocumentosElectronicosListarComponent } from './listar/xpath-documentos-electronicos-listar.component';

const routes: Routes = [
    {
        path: 'estandar',
        component: XPathDocumentosElectronicosListarComponent
    },{
        path: 'personalizados',
        component: XPathDocumentosElectronicosListarComponent
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
export class XPathDocumentosElectronicosRoutingModule {}
