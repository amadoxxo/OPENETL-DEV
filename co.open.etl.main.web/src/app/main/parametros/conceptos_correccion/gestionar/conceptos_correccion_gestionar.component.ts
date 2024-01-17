import {Component, Inject, OnInit} from '@angular/core';
import {MAT_DIALOG_DATA, MatDialogRef} from '@angular/material/dialog';
import {AbstractControl, FormBuilder, FormControl, FormGroup, Validators} from '@angular/forms';
import {ParametrosService} from '../../../../services/parametros/parametros.service';
import {BaseComponentView} from 'app/main/core/base_component_view';
import { JwtHelperService } from '@auth0/angular-jwt';
import * as moment from 'moment';

@Component({
  selector: 'conceptos-correcion-gestionar',
  templateUrl: './conceptos_correccion_gestionar.component.html',
  styleUrls: ['./conceptos_correccion_gestionar.component.scss']
})
export class ConceptosCorreccionGestionarComponent extends BaseComponentView implements OnInit{

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
    public cco_tipo: AbstractControl;
    public fecha_vigencia_desde: AbstractControl;
    public fecha_vigencia_hasta: AbstractControl;
    public hora_vigencia_desde: AbstractControl;
    public hora_vigencia_hasta: AbstractControl;
    public fecha_vigencia_desde_anterior: AbstractControl;
    public fecha_vigencia_hasta_anterior: AbstractControl;
    public estado: AbstractControl;
    public tipoSeleccionado: string;

    codigoConcepto: string;
    tipoConcepto: string;
    fechaVigenciaDesde: string;
    fechaVigenciaHasta: string;

    public tiposConceptosCorreccion = ['NC', 'ND'];

    /**
     * Constructor
     * @param formBuilder
     * @param modalRef
     * @param data
     * @param _parametrosService
     */
    constructor(
        private formBuilder: FormBuilder,
        private modalRef: MatDialogRef<ConceptosCorreccionGestionarComponent>,
        @Inject(MAT_DIALOG_DATA) data,
        private _parametrosService: ParametrosService,
        private jwtHelperService: JwtHelperService) {
            super();
            this.initForm();
            this.buildErrorsObjetc();
            this.parent = data.parent;
            this.action = data.action;
            this.IdToGet = data.cco_id;
            this.codigoConcepto = data.cco_codigo;
            this.tipoConcepto = data.cco_tipo;
            this.fechaVigenciaDesde = data.fecha_vigencia_desde;
            this.fechaVigenciaHasta = data.fecha_vigencia_hasta;
            this.usuario = this.jwtHelperService.decodeToken();
    }

    ngOnInit() {
        if (this.action === 'edit') {
            let controlEstado: FormControl = new FormControl('', Validators.required);
            this.form.addControl('estado', controlEstado);
            this.estado = this.form.controls['estado'];
            this.loadConceptoCorreccion();   
        } 
        if (this.action === 'view'){
            this.disableFormControl(
                this.cco_descripcion,
                this.cco_codigo,
                this.cco_tipo,
                this.fecha_vigencia_desde, 
                this.fecha_vigencia_hasta,
                this.hora_vigencia_desde,
                this.hora_vigencia_hasta
            );
            this.loadConceptoCorreccion(); 
        }    
    }

    /**
     * Inicializando el formulario.
     * 
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
            'cco_tipo': ['', Validators.compose(
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
        this.cco_tipo = this.form.controls['cco_tipo'];
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
     */
    public buildErrorsObjetc() {
        this.formErrors = {
            cco_descripcion: {
                required: 'La descripción es requerida!',
                maxLength: 'Ha introducido más de 100 caracteres'
            },
            cco_codigo: {
                required: 'El Código es requerido!',
                maxLength: 'Ha introducido más de 10 caracteres'
            },
            cco_tipo: {
                required: 'El Tipo es requerido!',
                maxLength: 'Ha introducido más de 10 caracteres'
            }
        };   
    }

    /**
     * Se encarga de cargar los datos de un registro que se ha seleccionado en el tracking.
     * 
     */
    public loadConceptoCorreccion(): void {
        this.loading(true);
        let data: any = {
            cco_tipo: this.tipoConcepto,
            cco_codigo: this.codigoConcepto,
            fecha_vigencia_desde: this.fechaVigenciaDesde,
            fecha_vigencia_hasta: this.fechaVigenciaHasta
        }
        this._parametrosService.consultaRegistroParametrica(data).subscribe(
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
                    this.cco_tipo.setValue(res.data.cco_tipo);
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
            error => {
                this.loading(false);
                this.mostrarErrores(error, 'Error al cargar el Concepto de Corrección');
                // let texto_errores = this.parseError(error);
                // this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al cargar el Concepto de Corrección', 'Ok', 'btn btn-danger');
            }
        );
    }

    /**
     * Cierra la ventana modal.
     * 
     */
    save(): void {
        if (this.modalRef) {
            this.modalRef.close(this.form.value);
        }
    }

    /**
     * Cierra la ventana modal.
     * 
     */
    public closeModal(reload): void {
        this.modalRef.close();
        if(reload)
            this.parent.getData();
    }

    /**
     * Crea o actualiza un nuevo registro.
     * 
     * @param values
     */
    public saveItem(values) {
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
                this._parametrosService.update(formWithAction, this.tipoConcepto + ':' + this.codigoConcepto).subscribe(
                    response => {
                        this.loading(false);
                        this.showTimerAlert('<strong>Concepto de Corrección actualizado correctamente.</strong>', 'success', 'center', 2000);
                        this.closeModal(true);
                    },
                    error => {
                        this.loading(false);
                        this.mostrarErrores(error, 'Error al actualizar el Concepto de Corrección');
                        // let texto_errores = this.parseError(error);
                        // this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al actualizar el Concepto de Corrección', 'Ok', 'btn btn-danger');
                    });
            } else {
                this._parametrosService.create(formWithAction).subscribe(
                    response => {
                        this.loading(false);
                        this.showTimerAlert('<strong>Concepto de Corrección guardado correctamente.</strong>', 'success', 'center', 2000);
                        this.closeModal(true);
                    },
                    error => {
                        this.loading(false);
                        this.mostrarErrores(error, 'Error al guardar el Concepto de Corrección');
                        // let texto_errores = this.parseError(error);
                        // this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al guardar el Concepto de Corrección', 'Ok', 'btn btn-danger');
                    });
                }
        }
    }
}
