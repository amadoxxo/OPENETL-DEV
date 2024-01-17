import {Component, Inject, AfterViewChecked} from '@angular/core';
import {BaseComponentList} from '../../core/base_component_list';
import {MAT_DIALOG_DATA, MatDialogRef} from '@angular/material/dialog';

@Component({
    selector: 'app-modal-informacion-moneda-documento',
    templateUrl: './modal-informacion-moneda-documento.component.html',
    styleUrls: ['./modal-informacion-moneda-documento.component.scss']
})
export class ModalInformacionMonedaDocumentoComponent extends BaseComponentList implements AfterViewChecked {
    
    /**
     * Mensajes para la tabla de los listados.
     */
    public messageDT = {
        emptyMessage: 'No hay data para mostrar',
        totalMessage: 'total',
        selectedMessage: 'seleccionados'
    };
    public reorderable: boolean;
    public selected = [];

    /**
     * Crea una instancia de ModalInformacionMonedaDocumentoComponent.
     * 
     * @param {*} data
     * @param {MatDialogRef<ModalInformacionMonedaDocumentoComponent>} modalRef
     * @memberof ModalInformacionMonedaDocumentoComponent
     */
    constructor(
        @Inject(MAT_DIALOG_DATA) data,
        private modalRef: MatDialogRef<ModalInformacionMonedaDocumentoComponent>
    ) {
        super();
        this.rows = [
            {
                moneda: "COP", moneda_extranjera: "USD", enviar_dia_me: "NO", trm: "4.396,84", moneda_xml: "COP", trm_xml: "Tasa de cambio para convertir COP a USD"
            },
            {
                moneda: "COP", moneda_extranjera: "USD", enviar_dia_me: "SI", trm: "0,00023", moneda_xml: "USD", trm_xml: "Tasa de cambio para convertir USD a COP"
            },
            {
                moneda: "USD", moneda_extranjera: "COP", enviar_dia_me: "NO", trm: "0,00023", moneda_xml: "USD", trm_xml: "Tasa de cambio para convertir USD a COP"
            },
            {
                moneda: "USD", moneda_extranjera: "COP", enviar_dia_me: "SI", trm: "4.396,84", moneda_xml: "COP", trm_xml: "Tasa de cambio para convertir COP a USD"
            }
        ];
    }

    /**
     * Pemite detectar los cambios después de cargado el componente.
     * 
     * @memberof ModalDocumentosReferenciaComponent
     */
    ngAfterViewChecked():void{
        window.dispatchEvent(new Event('resize'));
    }

    /**
     * Cierra la ventana modal de información moneda.
     *
     * @memberof ModalInformacionMonedaDocumentoComponent
     */
    public closeModal(): void {
        this.modalRef.close();
    }
}
