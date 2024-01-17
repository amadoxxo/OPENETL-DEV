import {Component, Inject, OnInit} from '@angular/core';
import {MAT_DIALOG_DATA, MatDialogRef} from '@angular/material/dialog';
import {DocumentosAnexosService} from '../../../services/emision/documentos_anexos.service';
import {BaseComponentView} from 'app/main/core/base_component_view';
import swal from 'sweetalert2';
import { Auth } from 'app/services/auth/auth.service';

@Component({
  selector: 'modal-documentos-anexos',
  templateUrl: './modal-documentos-anexos.component.html',
  styleUrls: ['./modal-documentos-anexos.component.scss']
})
export class ModalDocumentosAnexosComponent extends BaseComponentView implements OnInit{

    documento             : any;
    documentosAnexos      : any;
    parent                : any;
    proceso               : any;
    subproceso            : any;
    
    public totalDocumentos: number = 0;
    public documentos     : any[] = [];
    public documentosIds  : string = '';
    public aclsUsuario    : any;

    /**
     * Constructor de ModalDocumentosAnexosComponent.
     * 
     * @param {Auth} _auth
     * @param {MatDialogRef<ModalDocumentosAnexosComponent>} modalRef
     * @param {*} data
     * @param {DocumentosAnexosService} _documentosAnexosService
     * @memberof ModalDocumentosAnexosComponent
     */
    constructor(
        public _auth: Auth, 
        private modalRef: MatDialogRef<ModalDocumentosAnexosComponent>,
        @Inject(MAT_DIALOG_DATA) data,
        private _documentosAnexosService: DocumentosAnexosService
    ) {
        super();
        this.aclsUsuario       = this._auth.getAcls();
        this.parent            = data.parent;
        this.proceso           = data.proceso ? data.proceso : 'emision';
        this.subproceso        = data.subproceso ? data.subproceso : undefined;
        this.documento         = data.item;
        this.documentosAnexos  = data.item.get_documentos_anexos;
    }

    /**
     * Convierte bytes a la medida de tamaño más adecuada.
     * 
     * @param number bytes 
     * @param number decimals 
     * @returns 
     * @memberof ModalDocumentosAnexosComponent
     */
    formatBytes(bytes, decimals) {
        if (bytes === 0) return '0 Bytes';

        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));

        return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
    }

    /**
     * ngOnInit de ModalDocumentosAnexosComponent.
     *
     * @memberof ModalDocumentosAnexosComponent
     */
    ngOnInit() {
        let ids = [];
        this.totalDocumentos = 0;
        this.documentos = [];
        this.documentosIds = '';
        this.documentosAnexos.forEach(documento => {
            let tamano = this.formatBytes(parseInt(documento.dan_tamano), 2);
            documento.dan_tamano = tamano;
            ids.push(documento.dan_id);
        });
        this.totalDocumentos = this.documentosAnexos.length;
        this.documentos = this.documentosAnexos;
        this.documentosIds = ids.join(',');
    }

    /**
     * Permite la descarga de uno o varios documentos anexos.
     * 
     * @param string ids Ids de los documentos anexos a descargar 
     * @memberof ModalDocumentosAnexosComponent
     */
    descargarDocumentos(ids) {
        this.loading(true);
        this._documentosAnexosService.descargarDocumentosAnexos(ids, this.proceso, this.documento.cdo_id).subscribe(
            response => {
                this.loading(false);
            },
            (error) => {
                this.loading(false);
            }
        );
    }

    /**
     * Cierra la ventana modal.
     * 
     * @memberof ModalDocumentosAnexosComponent
     */
    save(): void {
        if (this.modalRef) {
            this.modalRef.close(this.form.value);
        }
    }

    /**
     * Cierra la ventana modal.
     * 
     * @param {*} reload
     * @memberof ModalDocumentosAnexosComponent
     */
    public closeModal(reload): void {
        this.modalRef.close();
        if(reload)
            this.parent.recargarConsulta();
    }

    /**
     * Elimina los documentos anexos asociados al documento.
     *
     * @param {*} ids Identidicador de los documentos anexos
     * @memberof ModalDocumentosAnexosComponent
     */
    eliminarDocumentoAnexo(ids) {
        let that = this;
        swal({
            html: `<strong style="color: #F00">¿Esta seguro de eliminar los documentos anexos indicados?</strong>`,
            type: 'warning',
            showCancelButton: true,
            confirmButtonClass: 'btn btn-success',
            confirmButtonText: 'Continuar',
            cancelButtonText: 'Cancelar',
            cancelButtonClass: 'btn btn-danger',
            buttonsStyling: false,
            allowOutsideClick: false
        })
        .then(function(result) {
            if(result.value) {
                that.loading(true);
                that._documentosAnexosService.eliminarDocumentosAnexos(ids, that.proceso, that.documento.cdo_id).subscribe(
                    response => {
                        that.loading(false);
                        that.closeModal(true);
                        that.showSuccess('<h3>' + response.message + '</h3>', 'success', 'Borrado Exitoso', 'Ok', 'btn btn-success');
                    },
                    (error) => {
                        that.loading(false);
                        that.mostrarErrores(error, 'Error al eliminar los documentos anexos');
                    }
                );
            }
        });
    }
}
