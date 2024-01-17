import { BaseComponentList } from '../../../../../../core/base_component_list';
import { ListarEtapasComponent } from './../../listar-etapas/listar-etapas.component';
import { GestionDocumentosService } from './../../../../../../../services/proyectos-especiales/recepcion/emssanar/gestion-documentos.service';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { FormGroup, AbstractControl, FormBuilder, Validators } from '@angular/forms';
import { Component, Inject, AfterViewChecked, ViewEncapsulation, ViewChild, ElementRef } from '@angular/core';

@Component({
    selector: 'app-modal-gestion-fe-ds',
    templateUrl: './modal-gestion-fe-ds.component.html',
    styleUrls: ['./modal-gestion-fe-ds.component.scss'],
    encapsulation: ViewEncapsulation.None
})
export class ModalGestionFeDsComponent extends BaseComponentList implements AfterViewChecked {
    @ViewChild('inputCde') inputCausalDevolucion: ElementRef<HTMLInputElement>;

    public parent            : ListarEtapasComponent;
    public form              : FormGroup;
    public estado_gestion    : AbstractControl;
    public observacion       : AbstractControl;
    public causal_devolucion : AbstractControl;
    public etapa             : number;
    public idRechazado       : string;

    public arrDocumentos      : any[] = [];
    public arrIds             : any[] = [];
    public arrCausales        : any[] = [];
    public arrOpciones        : any = {
        1: [
            { id : 'CONFORME',    name : 'Conforme' },
            { id : 'NO_CONFORME', name : 'No Conforme' , rechazado: true }
        ],
        2: [
            { id : 'REVISION_CONFORME',    name : 'Revisión Conforme' },
            { id : 'REVISION_NO_CONFORME', name : 'Revisión No Conforme' , rechazado: true }
        ],
        3: [
            { id: 'APROBACION_CONFORME',    name: 'Aprovación Conforme' },
            { id: 'APROBACION_NO_CONFORME', name: 'Aprovación No Conforme', rechazado: true }
        ],
        4: [
            { id : 'APROBADA_POR_CONTABILIDAD',    name : 'Aprobada por Contabilidad' },
            { id : 'NO_APROBADA_POR_CONTABILIDAD', name : 'No Aprobada por Contabilidad' , rechazado: true }
        ],
        5: [
            { id : 'APROBADA_POR_IMPUESTOS',    name : 'Aprobada por Impuestos' },
            { id : 'NO_APROBADA_POR_IMPUESTOS', name : 'No Aprobada por Impuestos' , rechazado: true }
        ],
        6: [
            { id : 'APROBADA_Y_PAGADA',     name : 'Aprobada y Pagada' },
            { id : 'NO_APROBADA_PARA_PAGO', name : 'No Aprobada para Pago' , rechazado: true }
        ]
    };

    /**
     * Crea una instancia de ModalGestionFeDsComponent.
     * 
     * @param {*} data
     * @param {MatDialogRef<ModalGestionFeDsComponent>} _modalRef
     * @param {FormBuilder} _formBuilder
     * @param {GestionDocumentosService} _gestionDocumentosService
     * @memberof ModalGestionFeDsComponent
     */
    constructor(
        @Inject(MAT_DIALOG_DATA) data,
        private _modalRef: MatDialogRef<ModalGestionFeDsComponent>,
        private _formBuilder: FormBuilder,
        private _gestionDocumentosService: GestionDocumentosService
    ) {
        super();
        this.etapa  = data.etapa;
        this.parent = data.parent;
        this.obtenerDataComponent();
        this.init();

        const [ estado ] = this.arrOpciones[this.etapa].filter( (reg) => reg.rechazado);
        this.idRechazado = estado.id;

        data.documentos.forEach(({ documento, gdo_id }) => {
            this.arrDocumentos.push(documento);
            this.arrIds.push(gdo_id);
        });
    }

    /**
     * Pemite detectar los cambios después de cargado el componente.
     * 
     * @memberof ModalGestionFeDsComponent
     */
    ngAfterViewChecked(): void {
        window.dispatchEvent(new Event('resize'));
    }

    /**
     * Inicializa el formulario del componente.
     *
     * @private
     * @memberof ModalGestionFeDsComponent
     */
    private init(): void  {
        this.form = this._formBuilder.group({
            estado_gestion    : this.requerido(),
            observacion       : [''],
            causal_devolucion : [''],
        });

        this.estado_gestion    = this.form.controls['estado_gestion'];
        this.observacion       = this.form.controls['observacion'];
        this.causal_devolucion = this.form.controls['causal_devolucion'];
    }

    /**
     * Carga la información necesaria del componente.
     *
     * @private
     * @memberof ModalGestionFeDsComponent
     */
    private async obtenerDataComponent(): Promise<void>  {
        this.loading(true);
        await this.getCausalesDevolucion().then( (data) => {
            data.forEach(registro => {
                let { cde_id, cde_descripcion } = registro;
                this.arrCausales.push({
                    id   : cde_id,
                    name : cde_descripcion
                });
            });
            if(data.length > 0) {
                this.arrCausales = [...this.arrCausales];
            }
        }).catch( (error) => {
            this.showError(error, 'error', 'Error al cargar las Causales de Devolución', 'Ok', 'btn btn-danger');
        });
    }

    /**
     * Realiza la petición para obtener las causales de devolución.
     *
     * @private
     * @return {Promise<any>}
     * @memberof ModalGestionFeDsComponent
     */
    private getCausalesDevolucion(): Promise<any> {
        return new Promise((resolve, reject) => {
            this._gestionDocumentosService.getCausalesDevolucion().subscribe({
                next: ({ data }) => {
                    this.loading(false);
                    resolve(data);
                },
                error: () => {
                    this.loading(false);
                    reject('No se obtuvieron las causales de devolución');
                }
            });
        });
    }

    /**
     * Retorna los parametros a enviar en la petición.
     *
     * @private
     * @return {object}
     * @memberof ModalGestionFeDsComponent
     */
    private getPayload(): object {
        const payload: any = {
            gdo_ids     : this.arrIds.join(),
            etapa       : this.etapa,
            observacion : this.observacion.value,
            estado      : this.estado_gestion.value
        };

        if(this.estado_gestion.value === this.idRechazado)
            payload.cde_id = this.causal_devolucion.value;

        return payload;
    }

    /**
     * Cierra la ventana modal de información moneda.
     *
     * @memberof ModalGestionFeDsComponent
     */
    public closeModal(): void {
        this._modalRef.close();
    }

    /**
     * Realiza el guardado de la información diligenciada del componente.
     *
     * @memberof ModalGestionFeDsComponent
     */
    public saveModal(): void {
        this.loading(true);
        this._gestionDocumentosService.gestionarEtapasFeDs(this.getPayload()).subscribe({
            next: () => {
                this.loading(false);
                this.closeModal();
                this.showSuccess('Se ha guardado la información correctamente.', 'success', 'Gestionar Fe/Ds', 'Ok', 'btn btn-success');
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
     * @memberof ModalGestionFeDsComponent
     */
    public keyDownEvent(event: KeyboardEvent): void  {
        if (event.key === ' ' || event.code === 'Space') {
            event.preventDefault();
            this.inputCausalDevolucion.nativeElement.value += ' ';
            this.filtrarCausalDevolucion(this.inputCausalDevolucion.nativeElement.value);
        }
    }

    /**
     * Realiza el filtrado de los registros del combo según el texto predictivo.
     *
     * @param {string} texto Texto predictivo
     * @return {any[]}
     * @memberof ModalGestionFeDsComponent
     */
    public filtrarCausalDevolucion(texto: string): any[] {
        return this.arrCausales.filter(cde => cde.name.toLowerCase().includes(texto.toLowerCase()));
    }

    /**
     * Acción a realizar cuando se selecciona una opción del combo.
     *
     * @memberof ModalGestionFeDsComponent
     */
    public setCausalDevolucion(): void  {
        this.inputCausalDevolucion.nativeElement.value = '';
        this.inputCausalDevolucion.nativeElement.focus();
    }

    /**
     * Limpia la opción seleccionada en la lista del combo.
     *
     * @memberof ModalGestionFeDsComponent
     */
    public clearComboValue(): void {
        this.causal_devolucion.setValue('');
    }

    /**
     * Evento de cambio de selección de checkbox.
     *
     * @param {*} event Evento de la opción seleccionada
     * @memberof ModalGestionFeDsComponent
     */
    public changeEstado(event): void  {
        if(event.value === this.idRechazado) {
            this.causal_devolucion.setValidators([Validators.required]);
        } else {
            this.clearComboValue();
            this.causal_devolucion.clearValidators();
        }
        this.causal_devolucion.updateValueAndValidity();
    }
}
