import {Component, Inject, OnInit} from '@angular/core';
import {MAT_DIALOG_DATA, MatDialogRef} from '@angular/material/dialog';
import {AbstractControl, FormBuilder, FormControl, FormGroup, Validators} from '@angular/forms';
import {ParametrosService} from '../../../../../services/parametros/parametros.service';
import {BaseComponentView} from 'app/main/core/base_component_view';
import { JwtHelperService } from '@auth0/angular-jwt';
import * as moment from 'moment';
import { DebidaDiligenciaComponent } from '../listar/debida_diligencia.component';

interface InterfaceDebidaDiligencia {
    ddi_id               : number;
    ddi_codigo           : string;
    ddi_descripcion      : string;
    fecha_vigencia_desde?: string|null,
    fecha_vigencia_hasta?: string|null,
    estado              ?: 'ACTIVO'|'INACTIVO',
    hora_vigencia_desde ?: string|null,
    hora_vigencia_hasta ?: string|null
}

type FormsErrors = {
    ddi_codigo: {
        required: string,
        maxLength: string
    },
    ddi_descripcion: {
        required: string,
        maxLength: string
    }
}

@Component({
    selector: 'debida-diligencia-gestionar',
    templateUrl: './debida_diligencia_gestionar.component.html',
    styleUrls: ['./debida_diligencia_gestionar.component.scss']
})
export class DebidaDiligenciaGestionarComponent extends BaseComponentView implements OnInit{

    //Usuario en línea
    public usuario: JwtHelperService;
    public objMagic = {};
    public form: FormGroup;
    public IdToGet: number;
    public action: string;
    public parent: DebidaDiligenciaComponent;
    public formErrors: FormsErrors;

    public ddi_descripcion: AbstractControl;
    public ddi_codigo: AbstractControl;
    public fecha_vigencia_desde: AbstractControl;
    public fecha_vigencia_hasta: AbstractControl;
    public hora_vigencia_desde: AbstractControl;
    public hora_vigencia_hasta: AbstractControl;
    public fecha_vigencia_desde_anterior: AbstractControl;
    public fecha_vigencia_hasta_anterior: AbstractControl;
    public estado: AbstractControl;

    codigoDebidaDiligencia: string;
    fechaVigenciaDesde: string;
    fechaVigenciaHasta: string;


    /**
     * Crea una instancia de DebidaDiligenciaGestionarComponent.
     * @param {FormBuilder} formBuilder
     * @param {MatDialogRef<DebidaDiligenciaGestionarComponent>} modalRef
     * @param {MAT_DIALOG_DATA} data
     * @param {ParametrosService} parametrosService
     * @param {JwtHelperService} jwtHelperService
     * @memberof DebidaDiligenciaGestionarComponent
     */
    constructor(
        private formBuilder: FormBuilder,
        private modalRef: MatDialogRef<DebidaDiligenciaGestionarComponent>,
        @Inject(MAT_DIALOG_DATA) public data,
        private parametrosService: ParametrosService,
        private jwtHelperService: JwtHelperService) {
            super();
            this.initForm();
            this.buildErrorsObjetc();
            this.parent = data.parent;
            this.action = data.action;
            this.IdToGet = data.ddi_id;
            this.codigoDebidaDiligencia = data.ddi_codigo;
            this.fechaVigenciaDesde = data.fecha_vigencia_desde;
            this.fechaVigenciaHasta = data.fecha_vigencia_hasta;
            this.usuario = this.jwtHelperService.decodeToken();
    }

    /**
     *
     * @memberof DebidaDiligenciaGestionarComponent
     */
    ngOnInit(): void {
        if (this.action === 'edit') {
            let controlEstado: FormControl = new FormControl('', Validators.required);
            this.form.addControl('estado', controlEstado);
            this.estado = this.form.controls['estado'];
            this.loadDebidaDiligencia();    
        }
        if (this.action === 'view'){
            this.disableFormControl(
                this.ddi_codigo,
                this.ddi_descripcion,
                this.fecha_vigencia_desde, 
                this.fecha_vigencia_hasta,
                this.hora_vigencia_desde,
                this.hora_vigencia_hasta
            );
            this.loadDebidaDiligencia(); 
        }    
    }

    /**
     * Inicializando el formulario.
     *
     * @private
     * @memberof DebidaDiligenciaGestionarComponent
     */
    private initForm(): void {
        this.form = this.formBuilder.group({
            'ddi_codigo': ['', Validators.compose(
                [
                    Validators.required,
                    Validators.maxLength(10),
                ],
            )],
            'ddi_descripcion': ['', Validators.compose(
                [
                    Validators.required,
                    Validators.maxLength(255)
                ],
            )],
            fecha_vigencia_desde: [''],
            fecha_vigencia_hasta: [''],
            hora_vigencia_desde: [''],
            hora_vigencia_hasta: [''],
            fecha_vigencia_desde_anterior: [''],
            fecha_vigencia_hasta_anterior: ['']
        }, {});

        this.ddi_codigo                    = this.form.controls['ddi_codigo'];
        this.ddi_descripcion               = this.form.controls['ddi_descripcion'];
        this.fecha_vigencia_desde          = this.form.controls['fecha_vigencia_desde'];
        this.fecha_vigencia_hasta          = this.form.controls['fecha_vigencia_hasta'];
        this.hora_vigencia_desde           = this.form.controls['hora_vigencia_desde'];
        this.hora_vigencia_hasta           = this.form.controls['hora_vigencia_hasta'];
        this.fecha_vigencia_desde_anterior = this.form.controls['fecha_vigencia_desde_anterior'];
        this.fecha_vigencia_hasta_anterior = this.form.controls['fecha_vigencia_hasta_anterior'];
    }

    /**
     * Construye un objeto para gestionar los errores en el formulario.
     *
     * @memberof DebidaDiligenciaGestionarComponent
     */
    public buildErrorsObjetc(): void {
        this.formErrors = {
            ddi_codigo: {
                required: '¡El Código es requerido!',
                maxLength: 'Ha introducido más de 10 caracteres'
            },
            ddi_descripcion: {
                required: '¡La descripción es requerida!',
                maxLength: 'Ha introducido más de 255 caracteres'
            },
        };   
    }

    /**
     * Se encarga de cargar los datos de un registro que se ha seleccionado en el tracking.
     *
     * @memberof DebidaDiligenciaGestionarComponent
     */
    public loadDebidaDiligencia(): void {
        this.loading(true);
        let data: InterfaceDebidaDiligencia = {
            ddi_id: this.IdToGet,
            ddi_codigo: this.codigoDebidaDiligencia,
            ddi_descripcion: '',
            fecha_vigencia_desde: this.fechaVigenciaDesde,
            fecha_vigencia_hasta: this.fechaVigenciaHasta
        }
        this.parametrosService.get(data.ddi_id).subscribe({
            next: res => {
                if (res) {
                    this.loading(false);
                    if (this.action === 'edit') {
                        if (res.data.estado === 'ACTIVO') {
                            this.estado.setValue('ACTIVO');
                        } else {
                            this.estado.setValue('INACTIVO');
                        }
                    }
                    this.ddi_codigo.setValue(res.data.ddi_codigo);
                    this.ddi_descripcion.setValue(res.data.ddi_descripcion);
                    if (res.data.fecha_vigencia_desde !== null && res.data.fecha_vigencia_desde !== '') {
                        const fechaVigenciaDesde = res.data.fecha_vigencia_desde.split(' ');
                        this.fecha_vigencia_desde.setValue(fechaVigenciaDesde[0]);
                        this.hora_vigencia_desde.setValue(fechaVigenciaDesde[1]);
                    }
                    if (res.data.fecha_vigencia_hasta !== null && res.data.fecha_vigencia_hasta !== '') {
                        const fechaVigenciaHasta = res.data.fecha_vigencia_hasta.split(' ');
                        this.fecha_vigencia_hasta.setValue(fechaVigenciaHasta[0]);
                        this.hora_vigencia_hasta.setValue(fechaVigenciaHasta[1]);
                    }
                    this.fecha_vigencia_desde_anterior.setValue(res.data.fecha_vigencia_desde);
                    this.fecha_vigencia_hasta_anterior.setValue(res.data.fecha_vigencia_hasta);
                    this.objMagic['fecha_creacion'] = res.data.fecha_creacion;
                    this.objMagic['fecha_modificacion'] = res.data.fecha_modificacion;
                    this.objMagic['estado'] = res.data.estado;
                }
            },
            error: error => {
                this.loading(false);
                this.mostrarErrores(error, 'Error al cargar la Debida Diligencia');
            }
        });
    }

    /**
     * Cierra la ventana modal de Códigos de Descuento.
     *
     * @memberof DebidaDiligenciaGestionarComponent
     */
    public save(): void {
        if (this.modalRef) {
            this.modalRef.close(this.form.value);
        }
    }

    /**
     * Cierra la ventana modal de Códigos de Descuento.
     *
     * @param {boolean} reload
     * @memberof DebidaDiligenciaGestionarComponent
     */
    public closeModal(reload: boolean): void {
        this.modalRef.close();
        if(reload)
            this.parent.getData();
    }

    /**
     * Crea o actualiza un nuevo registro.
     *
     * @param {InterfaceDebidaDiligencia} values
     * @memberof DebidaDiligenciaGestionarComponent
     */
    public saveItem(values: InterfaceDebidaDiligencia): void {
        let formWithAction = values;
        this.loading(true);
        if (this.form.valid) {
            const fechaVigenciaDesde = (this.fecha_vigencia_desde.value !== '' && this.fecha_vigencia_desde.value !== null) ? String(moment(this.fecha_vigencia_desde.value).format('YYYY-MM-DD')) : '';
            const horaVigenciaDesde  = (this.hora_vigencia_desde.value  !== '' && this.hora_vigencia_desde.value  !== null) ? this.hora_vigencia_desde.value : '00:00:00';
            const fechaVigenciaHasta = (this.fecha_vigencia_hasta.value !== '' && this.fecha_vigencia_hasta.value !== null) ? String(moment(this.fecha_vigencia_hasta.value).format('YYYY-MM-DD')) : '';
            const horaVigenciaHasta  = (this.hora_vigencia_hasta.value  !== '' && this.hora_vigencia_hasta.value  !== null) ? this.hora_vigencia_hasta.value : '00:00:00';

            const fechaInicio = (fechaVigenciaDesde !== '') ? fechaVigenciaDesde + ' ' + horaVigenciaDesde : '';
            const fechaFin    = (fechaVigenciaHasta !== '') ? fechaVigenciaHasta + ' ' + horaVigenciaHasta : '';

            formWithAction.fecha_vigencia_desde = (fechaInicio !== '') ? String(moment(fechaInicio).format('YYYY-MM-DD HH:mm:ss')) : '';
            formWithAction.fecha_vigencia_hasta = (fechaFin !== '') ? String(moment(fechaFin).format('YYYY-MM-DD HH:mm:ss')) : '';

            delete formWithAction.hora_vigencia_desde;
            delete formWithAction.hora_vigencia_hasta;

            if (this.action === 'edit') {
                formWithAction.ddi_id = this.IdToGet;
                this.parametrosService.update(formWithAction, this.IdToGet).subscribe({
                    next: res => {
                        this.loading(false);
                        this.showTimerAlert('<strong>Debida Diligencia actualizado correctamente.</strong>', 'success', 'center', 2000);
                        this.closeModal(true);
                    },
                    error: error => {
                        this.loading(false);
                        this.mostrarErrores(error, 'Error al actualizar la Debida Diligencia');
                    }
                });
            } else {
                this.parametrosService.create(formWithAction).subscribe({
                    next: res => {
                        this.loading(false);
                        this.showTimerAlert('<strong>Debida Diligencia guardado correctamente.</strong>', 'success', 'center', 2000);
                        this.closeModal(true);
                    },
                    error: error => {
                        this.loading(false);
                        this.mostrarErrores(error, 'Error al guardar la Debida Diligencia');
                    }    
                });
            }
        }
    }
}