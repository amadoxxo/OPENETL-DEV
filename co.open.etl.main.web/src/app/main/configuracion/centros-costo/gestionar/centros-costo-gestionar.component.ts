import {Component, Inject, OnInit} from '@angular/core';
import {MAT_DIALOG_DATA, MatDialogRef} from '@angular/material/dialog';
import {AbstractControl, FormBuilder, FormControl, FormGroup, Validators} from '@angular/forms';
import {BaseComponentView} from 'app/main/core/base_component_view';
import { JwtHelperService } from '@auth0/angular-jwt';
import * as moment from 'moment';
import {ConfiguracionService} from './../../../../services/proyectos-especiales/recepcion/emssanar/configuracion.service';

@Component({
    selector: 'centros-costo-gestionar',
    templateUrl: './centros-costo-gestionar.component.html',
    styleUrls: ['./centros-costo-gestionar.component.scss']
})
export class CentrosCostoGestionarComponent extends BaseComponentView implements OnInit{

    // Usuario en línea
    public usuario: any;
    public objMagic = {};
    form: FormGroup;
    IdToGet: number;
    action: string;
    parent: any;
    public formErrors: any;

    public cco_descripcion: AbstractControl;
    public cco_codigo: AbstractControl;
    public fecha_vigencia_desde: AbstractControl;
    public fecha_vigencia_hasta: AbstractControl;
    public hora_vigencia_desde: AbstractControl;
    public hora_vigencia_hasta: AbstractControl;
    public fecha_vigencia_desde_anterior: AbstractControl;
    public fecha_vigencia_hasta_anterior: AbstractControl;
    public estado: AbstractControl;

    codigoCentro: string;
    fechaVigenciaDesde: string;
    fechaVigenciaHasta: string;

    /**
     * Crea una instancia de CentrosCostoGestionarComponent.
     * @param {FormBuilder} formBuilder
     * @param {MatDialogRef<CentrosCostoGestionarComponent>} modalRef
     * @param {any} data
     * @param {ConfiguracionService} _configuracionService
     * @param {JwtHelperService} jwtHelperService
     * @memberof CentrosCostoGestionarComponent
     */
    constructor(
        private formBuilder: FormBuilder,
        private modalRef: MatDialogRef<CentrosCostoGestionarComponent>,
        @Inject(MAT_DIALOG_DATA) data,
        private jwtHelperService: JwtHelperService,
        private _configuracionService: ConfiguracionService
    ) {
            super();
            this.initForm();
            this.buildErrorsObject();
            this.parent = data.parent;
            this.action = data.action;
            this.IdToGet = data.cco_id;
            this.codigoCentro = data.cco_codigo;
            this.fechaVigenciaDesde = data.fecha_vigencia_desde;
            this.fechaVigenciaHasta = data.fecha_vigencia_hasta;
            this.usuario = this.jwtHelperService.decodeToken();
            this._configuracionService.setSlug = 'centros-costo';
    }

    /**
     * Se encarga de inicializar los parámetros para la búsqueda.
     *
     * @memberof CentrosCostoGestionarComponent
     */
    ngOnInit() {
        if (this.action === 'edit') {
            let controlEstado: FormControl = new FormControl('', Validators.required);
            this.form.addControl('estado', controlEstado);
            this.estado = this.form.controls['estado'];
            this.loadCentroCosto();   
        } 
        if (this.action === 'view'){
            this.disableFormControl(
                this.cco_descripcion,
                this.cco_codigo,
                this.fecha_vigencia_desde, 
                this.fecha_vigencia_hasta,
                this.hora_vigencia_desde,
                this.hora_vigencia_hasta
            );
            this.loadCentroCosto(); 
        }    
    }

    /**
     * Inicializando el formulario.
     *
     * @private
     * @memberof CentrosCostoGestionarComponent
     */
    private initForm(): void {
        this.form = this.formBuilder.group({
            'cco_descripcion': ['', Validators.compose(
                [
                    Validators.required,
                    Validators.maxLength(100)
                ],
            )],
            'cco_codigo': ['', Validators.compose(
                [
                    Validators.required,
                    Validators.maxLength(10),
                ],
            )],
            fecha_vigencia_desde: [''],
            fecha_vigencia_hasta: [''],
            hora_vigencia_desde:  [''],
            hora_vigencia_hasta:  [''],
            fecha_vigencia_desde_anterior: [''],
            fecha_vigencia_hasta_anterior: ['']
        }, {});

        this.cco_descripcion = this.form.controls['cco_descripcion'];
        this.cco_codigo = this.form.controls['cco_codigo'];
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
     * @memberof CentrosCostoGestionarComponent
     */
    public buildErrorsObject(): void {
        this.formErrors = {
            cco_descripcion: {
                required: 'La descripción es requerida!',
                maxLength: 'Ha introducido más de 100 caracteres'
            },
            cco_codigo: {
                required: 'El Código es requerido!',
                maxLength: 'Ha introducido más de 10 caracteres'
            }
        };   
    }

    /**
     * Se encarga de cargar los datos de un Centro Costo que se ha seleccionado en el tracking.
     *
     * @memberof CentrosCostoGestionarComponent
     */
    public loadCentroCosto(): void {
        this.loading(true);
        let data: any = {
            cco_codigo: this.codigoCentro,
            cco_descripcion: this.cco_descripcion,
            fecha_vigencia_desde: this.fechaVigenciaDesde,
            fecha_vigencia_hasta: this.fechaVigenciaHasta
        }
        this._configuracionService.get(this.codigoCentro).subscribe(
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
                    this.cco_descripcion.setValue(res.data.cco_descripcion);
                    this.cco_codigo.setValue(res.data.cco_codigo);
                    this.fecha_vigencia_desde_anterior.setValue(res.data.fecha_vigencia_desde);
                    this.fecha_vigencia_hasta_anterior.setValue(res.data.fecha_vigencia_hasta);
                    this.objMagic['fecha_creacion'] = res.data.fecha_creacion;
                    this.objMagic['fecha_modificacion'] = res.data.fecha_modificacion;
                    this.objMagic['estado'] = res.data.estado;
                }
            },
            error => {
                this.loading(false);
                this.mostrarErrores(error, 'Error al cargar el Centro de Costo');
            }
        );
    }

    /**
     * Guarda la ventana modal.
     *
     * @memberof CentrosCostoGestionarComponent
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
     * @memberof CentrosCostoGestionarComponent
     */
    public closeModal(reload: any): void {
        this.modalRef.close();
        if(reload)
            this.parent.getData();
    }

    /**
     * Crea o actualiza un nuevo Centro Costo.
     *
     * @param {any} values
     * @memberof CentrosCostoGestionarComponent
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
                formWithAction.cco_id = this.IdToGet;
                this._configuracionService.update(formWithAction, formWithAction.cco_id).subscribe(
                    response => {
                        this.loading(false);
                        this.showTimerAlert('<strong>Centro de Costo actualizado correctamente.</strong>', 'success', 'center', 2000);
                        this.closeModal(true);
                    },
                    error => {
                        this.loading(false);
                        this.mostrarErrores(error, 'Error al actualizar el Centro de Costo');
                    });
            } else {
                this._configuracionService.create(formWithAction).subscribe(
                    response => {
                        this.loading(false);
                        this.showTimerAlert('<strong>Centro de Costo guardado correctamente.</strong>', 'success', 'center', 2000);
                        this.closeModal(true);
                    },
                    error => {
                        this.loading(false);
                        this.mostrarErrores(error, 'Error al guardar el Centro de Costo');
                    });
                }
        }
    }
}
