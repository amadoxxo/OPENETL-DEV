import {Component, Inject, OnInit} from '@angular/core';
import {AbstractControl, FormBuilder, FormControl, FormGroup, Validators} from '@angular/forms';
import {SistemaService} from '../../../../services/sistema/sistema.service';
import {BaseComponentView} from 'app/main/core/base_component_view';
import { JwtHelperService } from '@auth0/angular-jwt';
import { MatDialogRef, MAT_DIALOG_DATA } from '@angular/material/dialog';

@Component({
  selector: 'tiempos-aceptacion-tacita-gestionar',
  templateUrl: './tiempos_aceptacion_tacita_gestionar.component.html',
  styleUrls: ['./tiempos_aceptacion_tacita_gestionar.component.scss']
})
export class TiemposAceptacionTacitaGestionarComponent extends BaseComponentView implements OnInit{

    // Usuario en línea
    public usuario: any;
    public objMagic = {};

    form: FormGroup;
    tatIdToGet: number;
    action: string;
    parent: any;
    public formErrors: any;

    public tat_codigo: AbstractControl;
    public tat_descripcion: AbstractControl;
    public tat_segundos: AbstractControl;
    public tat_default: AbstractControl;
    public estado: AbstractControl;

    /**
     * Constructor
     * @param formBuilder
     * @param modalRef
     * @param data
     * @param _sistemaService
     */
    constructor(
        private formBuilder: FormBuilder,
        private modalRef: MatDialogRef<TiemposAceptacionTacitaGestionarComponent>,
        @Inject(MAT_DIALOG_DATA) data,
        private _sistemaService: SistemaService,
        private jwtHelperService: JwtHelperService) {
            super();
            this.initForm();
            this.buildErrorsObject();
            this.parent = data.parent;
            this.action = data.action;
            this.tatIdToGet = data.tat_id;
            this.usuario = this.jwtHelperService.decodeToken();
    }

    ngOnInit() {
        if (this.action === 'edit') {
            let controlEstado: FormControl = new FormControl('', Validators.required);
            this.form.addControl('estado', controlEstado);
            this.estado = this.form.controls['estado'];
            this.loadTiempoAceptacionTacita();   
        } 
        if (this.action === 'view'){
            this.disableFormControl(this.tat_codigo, this.tat_descripcion, this.tat_segundos, this.tat_default);
            this.loadTiempoAceptacionTacita(); 
        }   
        if (this.action === 'new')
            this.tat_default.setValue('NO');    
    }

    /**
     * Inicializando el formulario.
     * 
     */
    private initForm(): void {
        this.form = this.formBuilder.group({
            'tat_descripcion': ['', Validators.compose(
                [
                    Validators.required,
                    Validators.maxLength(50),
                ],
            )],
            'tat_codigo': ['', Validators.compose(
                [
                    Validators.required,
                    Validators.maxLength(5),
                ],
            )],
            'tat_segundos': ['', Validators.compose(
                [
                    Validators.required,
                    Validators.max(999999999)
                ],
            )],
            'tat_default': ['']
        }, {});

        this.tat_codigo = this.form.controls['tat_codigo'];
        this.tat_descripcion = this.form.controls['tat_descripcion'];
        this.tat_segundos = this.form.controls['tat_segundos'];
        this.tat_default = this.form.controls['tat_default'];
    }

   /**
     * Construye un objeto para gestionar los errores en el formulario.
     * 
     */
    public buildErrorsObject() {
        this.formErrors = {
            tat_codigo: {
                required: 'El Código es requerido!',
                maxLength: 'Ha introducido más de 5 caracteres'
            },
            tat_descripcion: {
                required: 'La descripción del Tiempo de Aceptación Tácita es requerida!',
                maxLength: 'Ha introducido más de 50 caracteres'
            },
            tat_segundos: {
                required: 'Los segundos son requeridos!',
                max: 'Ha introducido más de 9 dígitos'
            }
        };
    }

    /**
     * Se encarga de cargar los datos de un Tiempo de Aceptación Tácita que se ha seleccionado en el tracking.
     * 
     */
    public loadTiempoAceptacionTacita(): void {
        this.loading(true);
        this._sistemaService.get(this.tatIdToGet).subscribe(
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
                    this.tat_codigo.setValue(res.data.tat_codigo);
                    this.tat_descripcion.setValue(res.data.tat_descripcion);
                    this.tat_segundos.setValue(res.data.tat_segundos);
                    this.objMagic['fecha_creacion'] = res.data.fecha_creacion;
                    this.objMagic['fecha_modificacion'] = res.data.fecha_modificacion;
                    this.objMagic['estado'] = res.data.estado;

                    if (res.data.tat_default === 'SI') {
                        this.form.controls['tat_default'].setValue('SI');
                    } else {
                        this.form.controls['tat_default'].setValue('NO');
                    }
                }
            },
            error => {
                let texto_errores = this.parseError(error);
                this.loading(false);
                this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al cargar el Tiempo de aceptación tácita', 'Ok', 'btn btn-danger');
            }
        );
    }

    /**
     * Cierra la ventana modal de Tiempos de Aceptación Tácita.
     * 
     */
    save(): void {
        if (this.modalRef) {
            this.modalRef.close(this.form.value);
        }
    }

    /**
     * Cierra la ventana modal de Tiempos de Aceptación Tácita.
     * 
     */
    public closeModal(reload): void {
        this.modalRef.close();
        if(reload)
            this.parent.getData();
    }

    /**
     * Crea o actualiza un nuevo tiempo de aceptación tácita.
     * 
     * @param values
     */
    public saveItem(values) {
        let formWithAction: any = values;
        this.loading(true);
        if (this.form.valid) {
            formWithAction.tat_segundos = Math.abs(formWithAction.tat_segundos);
            if (this.action === 'edit') {
                formWithAction.tat_id = this.tatIdToGet;
                this._sistemaService.update(formWithAction, this.tatIdToGet).subscribe(
                    response => {
                        this.loading(false);
                        this.showTimerAlert('<strong>Tiempo de Aceptación Tácita actualizado correctamente.</strong>', 'success', 'center', 2000);
                        this.closeModal(true);
                    },
                    error => {
                        let texto_errores = this.parseError(error);
                        this.loading(false);
                        this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al actualizar el Tiempo de aceptación tácita', 'Ok', 'btn btn-danger');
                    });
            } else {
                this._sistemaService.create(formWithAction).subscribe(
                    response => {
                        this.loading(false);
                        this.showTimerAlert('<strong>Tiempo de Aceptación Tácita guardado correctamente.</strong>', 'success', 'center', 2000);
                        this.closeModal(true);
                    },
                    error => {
                        let texto_errores = this.parseError(error);
                        this.loading(false);
                        this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al guardar el Tiempo de aceptación tácita', 'Ok', 'btn btn-danger');
                    });
                }
        }
    }
}
