import {Component, Inject, OnInit} from '@angular/core';
import {MAT_DIALOG_DATA, MatDialogRef} from '@angular/material/dialog';
import {AbstractControl, FormBuilder, FormControl, FormGroup, Validators} from '@angular/forms';
import {BaseComponentView} from 'app/main/core/base_component_view';
import { JwtHelperService } from '@auth0/angular-jwt';
import { ConfiguracionService } from './../../../../services/proyectos-especiales/recepcion/emssanar/configuracion.service';
import * as moment from 'moment';

@Component({
    selector: 'causales-devolucion-gestionar',
    templateUrl: './causales-devolucion-gestionar.component.html',
    styleUrls: ['./causales-devolucion-gestionar.component.scss']
})
export class CausalesDevolucionGestionarComponent extends BaseComponentView implements OnInit{

    // Usuario en línea
    public usuario: any;
    public objMagic = {};
    form: FormGroup;
    IdToGet: number;
    action: string;
    parent: any;
    public formErrors: any;

    public cde_descripcion: AbstractControl;
    public fecha_vigencia_desde: AbstractControl;
    public fecha_vigencia_hasta: AbstractControl;
    public hora_vigencia_desde: AbstractControl;
    public hora_vigencia_hasta: AbstractControl;
    public fecha_vigencia_desde_anterior: AbstractControl;
    public fecha_vigencia_hasta_anterior: AbstractControl;
    public estado: AbstractControl;

    fechaVigenciaDesde: string;
    fechaVigenciaHasta: string;


    /**
     * Crea una instancia de CausalesDevolucionGestionarComponent.
     * @param {FormBuilder} formBuilder
     * @param {MatDialogRef<CausalesDevolucionGestionarComponent>} modalRef
     * @param {any} data
     * @param {ConfiguracionService} _configuracionService
     * @param {JwtHelperService} jwtHelperService
     * @memberof CausalesDevolucionGestionarComponent
     */
    constructor(
        private formBuilder: FormBuilder,
        private modalRef: MatDialogRef<CausalesDevolucionGestionarComponent>,
        @Inject(MAT_DIALOG_DATA) data,
        private _configuracionService: ConfiguracionService,
        private jwtHelperService: JwtHelperService
    ) {
            super();
            this.initForm();
            this.buildErrorsObjetc();
            this.parent = data.parent;
            this.action = data.action;
            this.IdToGet = data.cde_id;
            this.fechaVigenciaDesde = data.fecha_vigencia_desde;
            this.fechaVigenciaHasta = data.fecha_vigencia_hasta;
            this.usuario = this.jwtHelperService.decodeToken();
            this._configuracionService.setSlug = 'causales-devolucion';

    }

    /**
     * Se encarga de inicializar los parámetros para la búsqueda.
     *
     * @memberof CausalesDevolucionGestionarComponent
     */
    ngOnInit() {
        if (this.action === 'edit') {
            let controlEstado: FormControl = new FormControl('', Validators.required);
            this.form.addControl('estado', controlEstado);
            this.estado = this.form.controls['estado'];
            this.loadCausalDevolucion();   
        } 
        if (this.action === 'view'){
            this.disableFormControl(
                this.cde_descripcion,
                this.fecha_vigencia_desde, 
                this.fecha_vigencia_hasta,
                this.hora_vigencia_desde,
                this.hora_vigencia_hasta
            );
            this.loadCausalDevolucion(); 
        }    
    }

    /**
     * Inicializando el formulario.
     *
     * @private
     * @memberof CausalesDevolucionGestionarComponent
     */
    private initForm(): void {
        this.form = this.formBuilder.group({
            'cde_descripcion': ['', Validators.compose(
                [
                    Validators.required,
                    Validators.maxLength(100)
                ],
            )],
            fecha_vigencia_desde: [''],
            fecha_vigencia_hasta: [''],
            hora_vigencia_desde:  [''],
            hora_vigencia_hasta:  [''],
            fecha_vigencia_desde_anterior: [''],
            fecha_vigencia_hasta_anterior: ['']
        }, {});

        this.cde_descripcion = this.form.controls['cde_descripcion'];
        this.fecha_vigencia_desde = this.form.controls['fecha_vigencia_desde'];
        this.fecha_vigencia_hasta = this.form.controls['fecha_vigencia_hasta'];
        this.hora_vigencia_desde = this.form.controls['hora_vigencia_desde'];
        this.hora_vigencia_hasta = this.form.controls['hora_vigencia_hasta'];
        this.fecha_vigencia_desde_anterior = this.form.controls['fecha_vigencia_desde_anterior'];
        this.fecha_vigencia_hasta_anterior = this.form.controls['fecha_vigencia_hasta_anterior'];
    }

    /**
     * Construye un objeto para gestionar los errores en el formulario.
     *
     * @memberof CausalesDevolucionGestionarComponent
     */
    public buildErrorsObjetc() {
        this.formErrors = {
            cde_descripcion: {
                required: 'La descripción es requerida!',
                maxLength: 'Ha introducido más de 100 caracteres'
            }
        };   
    }

    /**
     * Se encarga de cargar los datos de un Causal Devolucion que se ha seleccionado en el tracking.
     *
     * @memberof CausalesDevolucionGestionarComponent
     */
    public loadCausalDevolucion(): void {
        this.loading(true);
        let data: any = {
            cde_descripcion: this.cde_descripcion,
            fecha_vigencia_desde: this.fechaVigenciaDesde,
            fecha_vigencia_hasta: this.fechaVigenciaHasta
        }
        this._configuracionService.get(this.IdToGet).subscribe(
            res => {
                if (res) {
                    this.loading(false);
                    if (this.action === 'edit') {
                        if (res.data.estado === 'ACTIVO') {
                            this.estado.setValue('ACTIVO');
                        } else {
                            this.estado.setValue('INACTIVO');
                        }
                    }
                    this.cde_descripcion.setValue(res.data.cde_descripcion);
                    this.fecha_vigencia_desde_anterior.setValue(res.data.fecha_vigencia_desde);
                    this.fecha_vigencia_hasta_anterior.setValue(res.data.fecha_vigencia_hasta);
                    this.objMagic['fecha_creacion'] = res.data.fecha_creacion;
                    this.objMagic['fecha_modificacion'] = res.data.fecha_modificacion;
                    this.objMagic['estado'] = res.data.estado;
                }
            },
            error => {
                this.loading(false);
                this.mostrarErrores(error, 'Error al cargar el Causal Devolución');
            }
        );
    }

    /**
     * Guarda la ventana modal.
     *
     * @memberof CausalesDevolucionGestionarComponent
     */
    public save(): void {
        if (this.modalRef) {
            this.modalRef.close(this.form.value);
        }
    }

    /**
     * Cierra la ventana modal.
     *
     * @param {any} reload
     * @memberof CausalesDevolucionGestionarComponent
     */
    public closeModal(reload: any): void {
        this.modalRef.close();
        if(reload)
            this.parent.getData();
    }

    /**
     * Crea o actualiza un nuevo Causal Devolucion.
     *
     * @param {any} values
     * @memberof CausalesDevolucionGestionarComponent
     */
    public saveItem(values: any): void {
        let formWithAction: any = values;
        this.loading(true);
        if (this.form.valid) {
            const fechaVigenciaDesde = (this.fecha_vigencia_desde.value !== '' && this.fecha_vigencia_desde.value !== null) ? String(moment(this.fecha_vigencia_desde.value).format('YYYY-MM-DD')) : '';
            const horaVigenciaDesde  = (this.hora_vigencia_desde.value !== '' && this.hora_vigencia_desde.value !== null) ? this.hora_vigencia_desde.value : '00:00:00';
            const fechaVigenciaHasta = (this.fecha_vigencia_hasta.value !== '' && this.fecha_vigencia_hasta.value !== null) ? String(moment(this.fecha_vigencia_hasta.value).format('YYYY-MM-DD')) : '';
            const horaVigenciaHasta  = (this.hora_vigencia_hasta.value !== '' && this.hora_vigencia_hasta.value !== null) ? this.hora_vigencia_hasta.value : '00:00:00';

            const fechaInicio = (fechaVigenciaDesde !== '') ? fechaVigenciaDesde + ' ' + horaVigenciaDesde : '';
            const fechaFin    = (fechaVigenciaHasta !== '') ? fechaVigenciaHasta + ' ' + horaVigenciaHasta : '';

            formWithAction.fecha_vigencia_desde = (fechaInicio !== '') ? String(moment(fechaInicio).format('YYYY-MM-DD HH:mm:ss')) : '';
            formWithAction.fecha_vigencia_hasta = (fechaFin !== '') ? String(moment(fechaFin).format('YYYY-MM-DD HH:mm:ss')) : '';

            delete formWithAction.hora_vigencia_desde;
            delete formWithAction.hora_vigencia_hasta;

            if (this.action === 'edit') {
                formWithAction.cde_id = this.IdToGet;
                this._configuracionService.update(formWithAction, formWithAction.cde_id).subscribe(
                    response => {
                        this.loading(false);
                        this.showTimerAlert('<strong>Causal Devolución actualizado correctamente.</strong>', 'success', 'center', 2000);
                        this.closeModal(true);
                    },
                    error => {
                        this.loading(false);
                        this.mostrarErrores(error, 'Error al actualizar el Causal Devolución');
                    });
            } else {
                this._configuracionService.create(formWithAction).subscribe(
                    response => {
                        this.loading(false);
                        this.showTimerAlert('<strong>Causal Devolución guardado correctamente.</strong>', 'success', 'center', 2000);
                        this.closeModal(true);
                    },
                    error => {
                        this.loading(false);
                        this.mostrarErrores(error, 'Error al guardar el Causal Devolución');
                    });
                }
        }
    }
}
