import {Component, Inject, OnInit} from '@angular/core';
import {MAT_DIALOG_DATA, MatDialogRef} from '@angular/material/dialog';
import {BaseComponentView} from 'app/main/core/base_component_view';

@Component({
  selector: 'modal-documentos-lista',
  templateUrl: './modal-documentos-lista.component.html',
  styleUrls: ['./modal-documentos-lista.component.scss']
})
export class ModalDocumentosListaComponent extends BaseComponentView implements OnInit{

    documento: any;
    parent: any;
    
    totalRegistros: number = 0;
    columnas: Array<string> = [];
    datos: any;
    inicializado: boolean = false;
    verificacionFuncional: any[] = [];
    showRawObject: boolean = false;
    cdo_resultado_ws_crt_object: string = '';

    /**
     * Constructor
     * @param formBuilder
     * @param modalRef
     * @param data
     * @param _parametrosService
     */
    constructor(private modalRef: MatDialogRef<ModalDocumentosListaComponent>,
        @Inject(MAT_DIALOG_DATA) data) {
            super();
            this.parent = data.parent;
            this.documento = data.item;
    }

    ngOnInit() {  
        this.verificacionFuncional = [];
        if(this.documento.get_estado_documento && this.documento.get_estado_documento.est_object && typeof(this.documento.get_estado_documento.est_object.DocumentoRecibido) !== "undefined") {
            if(this.documento.get_estado_documento.est_resultado === 'FALLIDO') {
                if(this.documento.get_estado_documento.est_object.DocumentoRecibido instanceof Object) {
                    this.verificacionFuncional = this.documento.get_estado_documento.est_object.DocumentoRecibido.VerificacionFuncional.VerificacionDocumento;
                } else if(this.documento.get_estado_documento.est_object.DocumentoRecibido instanceof Array) {
                    let totalTransmisiones = this.documento.get_estado_documento.est_object.DocumentoRecibido.length;
                    this.verificacionFuncional = this.documento.get_estado_documento.est_object.DocumentoRecibido[totalTransmisiones-1].VerificacionFuncional.VerificacionDocumento;
                }
            } else {
                if (this.documento.get_estado_documento.est_object.DocumentoRecibido instanceof Array) {
                    this.documento.get_estado_documento.est_object.DocumentoRecibido = this.documento.get_estado_documento.est_object.DocumentoRecibido[0];
                }

                this.verificacionFuncional = this.documento.get_estado_documento.est_object;
            }
        }
        else {
            this.showRawObject = true;
            this.cdo_resultado_ws_crt_object = this.documento.get_estado_documento ? this.documento.get_estado_documento.est_object : '';
        }
    }

    /**
     * Cierra la ventana modal.
     * 
     */
    save(): void {
        if (this.modalRef) {
            this.modalRef.close(this.form.value);
        }
    }

    /**
     * Cierra la ventana modal.
     * 
     */
    public closeModal(reload): void {
        this.modalRef.close();
    }
}
