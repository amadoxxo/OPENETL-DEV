import { BaseComponentList } from '../../../../../../core/base_component_list';
import { ListarEtapasComponent } from './../../listar-etapas/listar-etapas.component';
import { GestionDocumentosService } from './../../../../../../../services/proyectos-especiales/recepcion/emssanar/gestion-documentos.service';
import { Component, Inject, AfterViewChecked, ViewEncapsulation, ViewChild, ElementRef } from '@angular/core';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { MatAccordion } from '@angular/material/expansion';

@Component({
    selector: 'app-modal-ver-detalle',
    templateUrl: './modal-ver-detalle.component.html',
    styleUrls: ['./modal-ver-detalle.component.scss'],
    encapsulation: ViewEncapsulation.None
})
export class ModalVerDetalleComponent extends BaseComponentList implements AfterViewChecked {
    @ViewChild('acordion', {static: false}) acordion: MatAccordion;
    @ViewChild('inputCde') inputCausalDevolucion: ElementRef<HTMLInputElement>;

    public parent                : ListarEtapasComponent;
    public etapa                 : number;
    public informacionEtapas     : any;
    public mostrarEtapaEspecifica: boolean = false;
    public numeroEtapaEspecifica : number;
    public nombreEtapaEspecifica : string = '';

    public historicoEtapa        : Array<any> = [{}];

    /**
     * Crea una instancia de ModalVerDetalleComponent.
     * 
     * @param {*} data
     * @param {MatDialogRef<ModalVerDetalleComponent>} _modalRef
     * @param {FormBuilder} _formBuilder
     * @param {GestionDocumentosService} _gestionDocumentosService
     * @memberof ModalVerDetalleComponent
     */
    constructor(
        @Inject(MAT_DIALOG_DATA) data,
        private _modalRef: MatDialogRef<ModalVerDetalleComponent>,
        private _gestionDocumentosService: GestionDocumentosService
    ) {
        super();
        this.etapa             = data.etapa;
        this.parent            = data.parent;
        this.informacionEtapas = data.informacionEtapas;
    }

    /**
     * Pemite detectar los cambios después de cargado el componente.
     * 
     * @memberof ModalVerDetalleComponent
     */
    ngAfterViewChecked(): void {
        window.dispatchEvent(new Event('resize'));
    }

    /**
     * Cierra la ventana modal de información moneda.
     *
     * @memberof ModalVerDetalleComponent
     */
    public closeModal(): void {
        this._modalRef.close();
    }

    /**
     * Pemrite mostrar la información detallada de una etapa específica.
     *
     * @param {number} etapa Número de la etapa
     * @param {string} nombreEtapa Nombre de la etapa
     * @memberof ModalVerDetalleComponent
     */
    public mostrarDetalleEtapa(etapa: number, nombreEtapa: string): void {
        this.numeroEtapaEspecifica  = etapa;
        this.nombreEtapaEspecifica  = nombreEtapa;

        this.historicoEtapa = this.informacionEtapas.historico.filter(item => {
            if(item.etapa === etapa) {
                switch (item.accion) {
                    case 'ESTADO':
                        item.accion = 'Asignación Estado';
                        break;
                    case 'CENTRO_OPERACIONES':
                        item.accion = 'Asignación Centro Operación';
                        break;
                    case 'CENTRO_COSTO':
                        item.accion = 'Asignación Centro Costo';
                        break;
                    case 'DATOS_CONTABILIZADO':
                        item.accion = 'Datos Contabilizados';
                        break;
                    case 'DEVOLVER_ETAPA':
                        item.accion = 'Autorización Devolver Etapa';
                        break;
                    default:
                        break;
                }
                return item;
            }
        });

        if (this.historicoEtapa.length > 0)
            this.mostrarEtapaEspecifica = true;
    }

    /**
     * Retorna a la información princila de la ventana modal. 
     *
     * @memberof ModalVerDetalleComponent
     */
    public regresar(): void {
        this.mostrarEtapaEspecifica = false;
    }
}
