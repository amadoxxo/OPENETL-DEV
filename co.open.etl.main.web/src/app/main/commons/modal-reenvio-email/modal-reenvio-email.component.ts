import {Component, Inject} from '@angular/core';
import {MAT_DIALOG_DATA, MatDialogRef} from '@angular/material/dialog';
import {AbstractControl, FormBuilder, FormGroup} from '@angular/forms';
import {DocumentosEnviadosService} from '../../../services/emision/documentos_enviados.service';
import {BaseComponentView} from 'app/main/core/base_component_view';

@Component({
    selector: 'app-modal-reenvio-email',
    templateUrl: './modal-reenvio-email.component.html',
    styleUrls: ['./modal-reenvio-email.component.scss']
})
export class ModalReenvioEmailComponent extends BaseComponentView {
    public parent         : any;
    public cdo_ids        : any;
    public documentos     : any;
    public reenvio_emails : AbstractControl;

    public form            : FormGroup;
    public listaDocumentos = '';

    /**
     * Constructor de ModalReenvioEmailComponent.
     * 
     * @param {*} data
     * @param {FormBuilder} formBuilder
     * @param {MatDialogRef<ModalReenvioEmailComponent>} modalRef
     * @memberof ModalReenvioEmailComponent
     */
    constructor(
        @Inject(MAT_DIALOG_DATA) data,
        private formBuilder                : FormBuilder,
        private modalRef                   : MatDialogRef<ModalReenvioEmailComponent>,
        private _documentosEnviadosService : DocumentosEnviadosService,
    ) {
        super();
        this.parent     = data.parent;
        this.documentos = data.documentos.emails_procesar;
        this.cdo_ids    = data.documentos.cdo_ids;
        this.initForm();
    }

    /**
     * Inicializando el formulario.
     * 
     * @memberof ModalReenvioEmailComponent
     */
    private initForm(): void {
        this.form = this.formBuilder.group({
            'reenvio_emails': ['']
        });

        this.reenvio_emails = this.form.controls['reenvio_emails'];
    }

    /**
     * Cierra la ventana modal.
     *
     * @param {boolean} reload Indica si debe cerrar la modal
     * @memberof ModalReenvioEmailComponent
     */
    public closeModal() {
        this.modalRef.close();
    }

    /**
     * Procesa el reenvio de los correos incluyendo correos adicionales si fueron agregados por el usuario.
     * 
     * @memberof ModalReenvioEmailComponent
     */
    public procesamientoReenvioEmails() {
        if (this.form.valid) {
            this.loading(true);
            let correos_adicionales = (this.form.controls['reenvio_emails'].value !== '') ? this.form.controls['reenvio_emails'].value.join(",") : null;
            this._documentosEnviadosService.enviarDocumentosPorCorreo(this.cdo_ids, correos_adicionales).subscribe(
                response => {
                    this.loading(false);
                    this.showTimerAlert('<p>' + response.message + '</p>', 'success', 'center', 1000);
                    this.closeModal();
                },
                (error) => {
                    this.loading(false);
                    this.mostrarErrores(error, 'Error al enviar los correos');
                }
            );
        }
    }
}
