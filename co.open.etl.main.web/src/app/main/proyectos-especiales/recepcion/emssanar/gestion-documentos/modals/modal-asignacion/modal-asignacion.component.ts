import { BaseComponentList } from '../../../../../../core/base_component_list';
import { ListarEtapasComponent } from './../../listar-etapas/listar-etapas.component';
import { GestionDocumentosService } from './../../../../../../../services/proyectos-especiales/recepcion/emssanar/gestion-documentos.service';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { FormGroup, AbstractControl, FormBuilder } from '@angular/forms';
import { Component, Inject, AfterViewChecked, ViewEncapsulation, ViewChild, ElementRef } from '@angular/core';

@Component({
    selector: 'app-modal-asignacion',
    templateUrl: './modal-asignacion.component.html',
    styleUrls: ['./modal-asignacion.component.scss'],
    encapsulation: ViewEncapsulation.None
})
export class ModalAsignacionComponent extends BaseComponentList implements AfterViewChecked {
    @ViewChild('inputCombo') inputCombo: ElementRef<HTMLInputElement>;

    public parent                : ListarEtapasComponent;
    public form                  : FormGroup;
    public combo                 : AbstractControl;
    public etapa                 : number;
    public nombreCombo           : string;
    public arrDocumentos         : any[] = [];
    public arrIds                : any[] = [];
    public arrOpcionesCombo      : any[] = [];
    private arrEtapasCentroCosto : number[] = [2];

    /**
     * Crea una instancia de ModalAsignacionComponent.
     * 
     * @param {*} data
     * @param {MatDialogRef<ModalAsignacionComponent>} _modalRef
     * @param {FormBuilder} _formBuilder
     * @param {GestionDocumentosService} _gestionDocumentosService
     * @memberof ModalAsignacionComponent
     */
    constructor(
        @Inject(MAT_DIALOG_DATA) data,
        private _modalRef: MatDialogRef<ModalAsignacionComponent>,
        private _formBuilder: FormBuilder,
        private _gestionDocumentosService: GestionDocumentosService
    ) {
        super();
        this.etapa  = data.etapa;
        this.parent = data.parent;
        this.obtenerDataComponent();
        this.init();

        data.documentos.forEach(({ documento, gdo_id }) => {
            this.arrDocumentos.push(documento);
            this.arrIds.push(gdo_id);
        });
    }

    /**
     * Pemite detectar los cambios después de cargado el componente.
     * 
     * @memberof ModalAsignacionComponent
     */
    ngAfterViewChecked(): void {
        window.dispatchEvent(new Event('resize'));
    }

    /**
     * Inicializa el formulario del componente.
     *
     * @private
     * @memberof ModalAsignacionComponent
     */
    private init(): void {
        this.form  = this._formBuilder.group({ combo : this.requerido() });
        this.combo = this.form.controls['combo'];

        if(this.arrEtapasCentroCosto.includes(this.etapa)) {
            this.nombreCombo = 'Centro de Costo';
        } else {
            this.nombreCombo = 'Centro de Operación';
        }
    }

    /**
     * Carga la información necesaria del componente.
     *
     * @private
     * @return {Promise<any>}
     * @memberof ModalAsignacionComponent
     */
    private async obtenerDataComponent(): Promise<void> {
        this.loading(true);
        await this.getDataCombo().then( (data) => {
            data.forEach(registro => {
                if(this.arrEtapasCentroCosto.includes(this.etapa)) {
                    let { cco_id, cco_codigo, cco_descripcion } = registro;
                    this.arrOpcionesCombo.push({
                        id   : cco_id,
                        name : `${ cco_codigo } - ${ cco_descripcion }`
                    });
                } else {
                    let { cop_id, cop_descripcion } = registro;
                    this.arrOpcionesCombo.push({
                        id   : cop_id,
                        name : cop_descripcion
                    });
                }
            });
            if(data.length > 0) {
                this.arrOpcionesCombo = [...this.arrOpcionesCombo];
            }
        }).catch( (error) => {
            this.showError(error, 'error', 'Error al cargar los registros de la lista', 'Ok', 'btn btn-danger');
        });
    }

    /**
     * Realiza la petición para obtener los registros de la lista.
     *
     * @private
     * @return {Promise<any>}
     * @memberof ModalAsignacionComponent
     */
    private getDataCombo(): Promise<any> {
        return new Promise((resolve, reject) => {
            let metodoGet = this._gestionDocumentosService.getCentrosOperacion();
            // Etapas para las que aplica el combo Centro de Costo
            if(this.arrEtapasCentroCosto.includes(this.etapa))
                metodoGet = this._gestionDocumentosService.getCentrosCosto();

            metodoGet.subscribe({
                next: ({ data }) => {
                    this.loading(false);
                    resolve(data);
                },
                error: () => {
                    this.loading(false);
                    reject('No se obtuvieron los registros del '+ this.nombreCombo);
                }
            });
        });
    }

    /**
     * Retorna los parametros a enviar en la petición.
     *
     * @private
     * @return {object}
     * @memberof ModalAsignacionComponent
     */
    private getPayload(): object {
        const payload: any = {
            gdo_ids : this.arrIds.join()
        };
        if(this.arrEtapasCentroCosto.includes(this.etapa))
            payload.cco_id = this.combo.value;
        else
            payload.cop_id = this.combo.value;

        return payload;
    }

    /**
     * Cierra la ventana modal de información moneda.
     *
     * @memberof ModalAsignacionComponent
     */
    public closeModal(): void {
        this._modalRef.close();
    }

    /**
     * Realiza el guardado de la información diligenciada del componente.
     *
     * @memberof ModalAsignacionComponent
     */
    public async saveModal(): Promise<void> {
        this.loading(true);
        let metodoSave = this._gestionDocumentosService.enviarAsignarCentroOperacion(this.getPayload());
        // Etapas para las que aplica el combo Centro de Costo
        if(this.arrEtapasCentroCosto.includes(this.etapa))
            metodoSave = this._gestionDocumentosService.enviarAsignarCentroCosto(this.getPayload());

        metodoSave.subscribe({
            next: () => {
                this.loading(false);
                this.showSuccess(`Se asignó el ${ this.nombreCombo } correctamente.`, 'success', 'Asignar '+this.nombreCombo, 'Ok', 'btn btn-success');
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

    /**
     * Maneja el evento de la tecla espacio para evitar auto seleccionar la opción.
     *
     * @param {KeyboardEvent} event Evento ejecutado
     * @memberof ModalAsignacionComponent
     */
    public keyDownEvent(event: KeyboardEvent): void {
        if (event.key === ' ' || event.code === 'Space') {
            event.preventDefault();
            this.inputCombo.nativeElement.value += ' ';
            this.filtrarLista(this.inputCombo.nativeElement.value);
        }
    }

    /**
     * Realiza el filtrado de los registros del combo según el texto predictivo.
     *
     * @param {string} texto Texto predictivo
     * @return {any[]}
     * @memberof ModalAsignacionComponent
     */
    public filtrarLista(texto: string): any[] {
        return this.arrOpcionesCombo.filter(reg => reg.name.toLowerCase().includes(texto.toLowerCase()));
    }

    /**
     * Acción a realizar cuando se selecciona una opción del combo.
     *
     * @memberof ModalAsignacionComponent
     */
    public setValorLista(): void {
        this.inputCombo.nativeElement.value = '';
        this.inputCombo.nativeElement.focus();
    }

    /**
     * Limpia la opción seleccionada en la lista del combo.
     *
     * @memberof ModalAsignacionComponent
     */
    public clearComboValue(): void {
        this.combo.setValue('');
    }
}
