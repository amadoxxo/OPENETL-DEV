import { ComponentRef } from '@angular/core';
import { TabDinamicoItemComponent } from '../facturacion-web/facturacion-web/documento-electronico/tab-dinamico-item/tab-dinamico-item.component';
export class FacturacionWebItemDinamico {
    itemId: number;
    componente: ComponentRef<TabDinamicoItemComponent>;

    constructor(
        itemId: number,
        componente: ComponentRef<TabDinamicoItemComponent>, 
    ) {
        this.itemId = itemId;
        this.componente = componente;
    }
}
