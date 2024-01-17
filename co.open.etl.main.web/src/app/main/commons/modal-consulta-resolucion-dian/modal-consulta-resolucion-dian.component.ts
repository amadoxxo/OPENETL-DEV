import {Component, Inject} from '@angular/core';
import {MAT_DIALOG_DATA, MatDialogRef} from '@angular/material/dialog';
import {AbstractControl, FormBuilder, FormGroup} from '@angular/forms';
import {BaseComponentView} from 'app/main/core/base_component_view';
import {ResolucionesService} from './../../../services/configuracion/resoluciones.service';

@Component({
    selector: 'app-modal-consulta-resolucion-dian',
    templateUrl: './modal-consulta-resolucion-dian.component.html',
    styleUrls: ['./modal-consulta-resolucion-dian.component.scss']
})
export class ModalConsultaResolucionDianComponent extends BaseComponentView {
    public ofes              : Array<any> = [];
    public form              : FormGroup;
    public parent            : any;
    public formErrors        : any;
    public ofe_identificacion: AbstractControl;

    /**
     * Constructor de ModalConsultaResolucionDianComponent.
     * 
     * @param {*} data
     * @param {FormBuilder} formBuilder
     * @param {MatDialogRef<ModalConsultaResolucionDianComponent>} modalRef
     * @memberof ModalConsultaResolucionDianComponent
     */
    constructor(
        @Inject(MAT_DIALOG_DATA) data,
        private formBuilder         : FormBuilder,
        private modalRef            : MatDialogRef<ModalConsultaResolucionDianComponent>,
        private _resolucionesService: ResolucionesService,
    ) {
        super();
        this.initForm();
        this.ofes = data.ofes;
    }

    /**
     * Inicializando el formulario.
     * 
     * @memberof ModalConsultaResolucionDianComponent
     */
    private initForm(): void {
        this.form = this.formBuilder.group({
            'ofe_identificacion': this.requerido()
        });
        
        this.ofe_identificacion = this.form.controls['ofe_identificacion'];
    }

    /**
     * Cierra la ventana modal.
     *
     * @param {boolean} reload Indica si debe cerrar la modal
     * @memberof ModalConsultaResolucionDianComponent
     */
    closeModal() {
        this.modalRef.close();
    }

    /**
     * Procesa el reenvio de los correos incluyendo correos adicionales si fueron agregados por el usuario.
     * 
     * @memberof ModalConsultaResolucionDianComponent
     */
    consultarResolucionDian() {
        if (this.form.valid) {
            this.loading(true);

            let parametros = {
                'ofe_identificacion': this.ofe_identificacion.value
            }

            this._resolucionesService.consultarResolucionDian(parametros).subscribe(
                response => {
                    this.loading(false);
                    this.descargarExcelResolucionesDian(response.data);
                },
                (error) => {
                    this.loading(false);
                    this.mostrarErrores(error, 'Error al enviar los correos');
                }
            );
        }
    }

    /**
     * Realiza la petición para la generación del Excel de resoluciones con la data de la consulta a la DIAN.
     *
     * @param {string} data Resultado de la consulta a la DIAN codificado en base64
     * @memberof ModalConsultaResolucionDianComponent
     */
    descargarExcelResolucionesDian(data: string) {
        this.loading(true);

        let parametros = {
            'data': data,
            'ofe_identificacion': this.ofe_identificacion.value
        }

        this._resolucionesService.descargarExcelResolucionesDian(parametros).subscribe(
            response => {
                this.loading(false);
                this.closeModal();
            },
            (error) => {
                this.loading(false);
            }
        );
    }
}
