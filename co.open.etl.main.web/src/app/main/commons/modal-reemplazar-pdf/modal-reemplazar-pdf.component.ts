import {Component, Inject, OnInit} from '@angular/core';
import {BaseComponentView} from '../../core/base_component_view';
import {MAT_DIALOG_DATA, MatDialogRef} from '@angular/material/dialog';
import {AbstractControl, FormGroup, FormBuilder} from '@angular/forms';
import {DocumentosEnviadosService} from './../../../services/emision/documentos_enviados.service';

@Component({
    selector: 'app-modal-reemplazar-pdf',
    templateUrl: './modal-reemplazar-pdf.component.html',
    styleUrls: ['./modal-reemplazar-pdf.component.scss']
})
export class ModalReemplazarPdfComponent extends BaseComponentView implements OnInit {

    public item              : any;
    public parent            : any;
    public proceso           : any;
    public formReemplazarPdf : FormGroup;
    public pdf               : AbstractControl;
    public archivoCargar     : any[] = [];

    /**
     * Constructor
     *
     * @param {object} data Data recibida del componente padre
     * @param {MatDialogRef} _modalRef Referencia al componente para renderizar la modal
     * @param {FormBuilder} _formBuilder Instancia para la creación del formulario
     * @param {DocumentosEnviadosService} _documentosEnviados Servicio a utilizar
     */
    constructor(
        @Inject(MAT_DIALOG_DATA) data,
        private _modalRef          : MatDialogRef<ModalReemplazarPdfComponent>,
        private _formBuilder       : FormBuilder,
        private _documentosEnviados: DocumentosEnviadosService
    ) {
        super();
        this.item    = data.item;
        this.parent  = data.parent;
        this.proceso = data.proceso;
    }

    ngOnInit() {
        this.initFormUsuarios();
    }

    /**
     * Inicializa el formulario para la carga del PDF.
     *
     * @memberof ModalReemplazarPdfComponent
     */
    initFormUsuarios() {
        this.formReemplazarPdf = this._formBuilder.group({
            'pdf': this.requerido()
        });

        this.pdf    = this.formReemplazarPdf.controls['pdf'];
    }

    /**
     * Cierra la ventana modal.
     *
     * @param {bool} reload indica si la información del padre debe ser recargada
     * @memberof ModalReemplazarPdfComponent
     */
    closeModal(reload): void {
        this._modalRef.close();
        if(reload)
            this.parent.paginar(10);
    }

    /**
     * Se ejecuta cuando se carga de manera efectiva un archivo a través de los campos del formulario.
    
     * Esto permite actualizar la propiedad en donde se almacena la información de archivos a enviar a la API
     *
     * @param {*} fileInput
     * @memberof ModalReemplazarPdfComponent
     */
    fileChangeEvent(fileInput: any) {
        if (fileInput.target.files) {
            this.archivoCargar = fileInput.target.files;
        } else {
            this.archivoCargar = [];
        }
    }

    /**
     * Limpia los campos del formulario.
     *
     * @memberof ModalReemplazarPdfComponent
     */
    limpiarCampos() {
        this.pdf.setValue('');
        this.archivoCargar = [];
    }

    /**
     * Procesa el formulario para el envío del nuevo PDF.
     *
     * @memberof ModalReemplazarPdfComponent
     */
    uploadPdf(): void {
        if(this.proceso == 'emision') {
            this.loading(true);
            this._documentosEnviados.reemplazarPdf(this.archivoCargar[0], this.item.ofe_identificacion, this.item.adq_identificacion, this.item.rfa_prefijo, this.item.cdo_consecutivo).subscribe(
                res => {
                    this.loading(false);
                    this.closeModal(false);
                    this.limpiarCampos();
                    this.showSuccess('<strong>' + res.message + '</strong>', 'success', 'Reemplazar PDF', 'Ok', 'btn btn-success');
                },
                error => {
                    this.loading(false);
                    this.limpiarCampos();
                    const texto_errores = this.parseError(error);
                    this.showError('<h4 style="text-align:left">' + texto_errores + '</h4>', 'error', 'Reemplazar PDF', '0k, entiendo', 'btn btn-danger');
                });
        }
    }
}
