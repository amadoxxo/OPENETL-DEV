import { BaseComponentList } from '../../../../../../core/base_component_list';
import { ListarEtapasComponent } from './../../listar-etapas/listar-etapas.component';
import { GestionDocumentosService } from './../../../../../../../services/proyectos-especiales/recepcion/emssanar/gestion-documentos.service';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { FormGroup, AbstractControl, FormBuilder } from '@angular/forms';
import { Component, Inject, AfterViewChecked, ViewEncapsulation } from '@angular/core';

@Component({
    selector: 'app-modal-datos-contabilizado',
    templateUrl: './modal-datos-contabilizado.component.html',
    styleUrls: ['./modal-datos-contabilizado.component.scss'],
    encapsulation: ViewEncapsulation.None
})
export class ModalDatosContabilizadoComponent extends BaseComponentList implements AfterViewChecked {

    public parent           : ListarEtapasComponent;
    public form             : FormGroup;
    public tipo_documento   : AbstractControl;
    public numero_documento : AbstractControl;
    public arrDocumentos    : any[] = [];
    public arrIds           : any[] = [];

    /**
     * Crea una instancia de ModalDatosContabilizadoComponent.
     * 
     * @param {*} data
     * @param {MatDialogRef<ModalDatosContabilizadoComponent>} _modalRef
     * @param {FormBuilder} _formBuilder
     * @param {GestionDocumentosService} _gestionDocumentosService
     * @memberof ModalDatosContabilizadoComponent
     */
    constructor(
        @Inject(MAT_DIALOG_DATA) data,
        private _modalRef: MatDialogRef<ModalDatosContabilizadoComponent>,
        private _formBuilder: FormBuilder,
        private _gestionDocumentosService: GestionDocumentosService
    ) {
        super();
        this.parent = data.parent;
        this.init();

        data.documentos.forEach(({ documento, gdo_id }) => {
            this.arrDocumentos.push(documento);
            this.arrIds.push(gdo_id);
        });
    }

    /**
     * Pemite detectar los cambios después de cargado el componente.
     * 
     * @memberof ModalDatosContabilizadoComponent
     */
    ngAfterViewChecked(): void {
        window.dispatchEvent(new Event('resize'));
    }

    /**
     * Inicializa el formulario del componente.
     *
     * @private
     * @memberof ModalDatosContabilizadoComponent
     */
    private init(): void {
        this.form  = this._formBuilder.group({
            tipo_documento : this.requerido(),
            numero_documento : this.requerido(),
        });

        this.tipo_documento = this.form.controls['tipo_documento'];
        this.numero_documento = this.form.controls['numero_documento'];
    }

    /**
     * Retorna los parametros a enviar en la petición.
     *
     * @private
     * @return {object}
     * @memberof ModalDatosContabilizadoComponent
     */
    private getPayload(): object {
        const payload: any = {
            gdo_ids          : this.arrIds.join(),
            tipo_documento   : this.tipo_documento.value,
            numero_documento : this.numero_documento.value,
        };

        return payload;
    }

    /**
     * Cierra la ventana modal de información moneda.
     *
     * @memberof ModalDatosContabilizadoComponent
     */
    public closeModal(): void {
        this._modalRef.close();
    }

    /**
     * Realiza el guardado de la información diligenciada del componente.
     *
     * @memberof ModalDatosContabilizadoComponent
     */
    public async saveModal(): Promise<void> {
        this.loading(true);
        this._gestionDocumentosService.enviarDatosContabilizado(this.getPayload()).subscribe({
            next: () => {
                this.loading(false);
                this.showSuccess(`Se asignaron correctamente los datos contabilizado.`, 'success', 'Datos Contabilizado', 'Ok', 'btn btn-success');
                this.closeModal();
                this.parent.getData();
            },
            error: (error) => {
                this.loading(false);
                let texto_errores = this.parseError(error);
                this.showError(texto_errores, 'error', 'Error al Guardar la Información', 'Ok', 'btn btn-danger');
            }
        });
    }
}
