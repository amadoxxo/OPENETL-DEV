import {Component, Inject, OnInit} from '@angular/core';
import {AbstractControl, FormBuilder, FormControl, FormGroup, Validators} from '@angular/forms';
import {SistemaService} from '../../../../services/sistema/sistema.service';
import {BaseComponentView} from 'app/main/core/base_component_view';
import * as moment from 'moment';
import {MomentDateAdapter} from '@angular/material-moment-adapter';
import {DateAdapter, MAT_DATE_FORMATS, MAT_DATE_LOCALE} from '@angular/material/core';
import { JwtHelperService } from '@auth0/angular-jwt';
import { MatDialogRef, MAT_DIALOG_DATA } from '@angular/material/dialog';

export const MY_FORMATS = {
    parse: {
        dateInput: 'YYYY-MM-DD',
    },
    display: {
        dateInput: 'YYYY-MM-DD',
        monthYearLabel: 'YYYY MMM ',
        dateA11yLabel: 'LL',
        monthYearA11yLabel: 'YYYY MMMM',
    },
};

@Component({
  selector: 'festivos-gestionar',
  templateUrl: './festivos_gestionar.component.html',
  styleUrls: ['./festivos_gestionar.component.scss'],
  providers: [
    {provide: MAT_DATE_LOCALE, useValue: 'es-ES'},
    {provide: DateAdapter, useClass: MomentDateAdapter, deps: [MAT_DATE_LOCALE]},
    {provide: MAT_DATE_FORMATS, useValue: MY_FORMATS},
]
})
export class FestivosGestionarComponent extends BaseComponentView implements OnInit{

    // Usuario en línea
    public usuario: any;
    public objMagic = {};

    form: FormGroup;
    fesIdToGet: number;
    action: string;
    parent: any;
    public formErrors: any;

    public fes_descripcion: AbstractControl;
    public fes_fecha: AbstractControl;
    public estado: AbstractControl;

    /**
     * Constructor
     * @param formBuilder
     * @param modalRef
     * @param data
     * @param sistemaService
     */
    constructor(
        private formBuilder: FormBuilder,
        private modalRef: MatDialogRef<FestivosGestionarComponent>,
        @Inject(MAT_DIALOG_DATA) data,
        private sistemaService: SistemaService,
        private jwtHelperService: JwtHelperService) {
            super();
            this.initForm();
            this.buildErrorsObject();
            this.parent = data.parent;
            this.action = data.action;
            this.fesIdToGet = data.fes_id;
            this.usuario = this.jwtHelperService.decodeToken();
    }

    ngOnInit() {
        if (this.action === 'edit') {
            let controlEstado: FormControl = new FormControl('', Validators.required);
            this.form.addControl('estado', controlEstado);
            this.estado = this.form.controls['estado'];
            this.loadFestivo();  
        } 
        if (this.action === 'view'){
            this.disableFormControl(this.fes_descripcion, this.fes_fecha);
            this.loadFestivo();
        } 
    }

    /**
     * Inicializando el formulario.
     * 
     */
    private initForm(): void {
        this.form = this.formBuilder.group({
            'fes_descripcion': ['', Validators.compose(
                [
                    Validators.required,
                    Validators.maxLength(100),
                ],
            )],
            'fes_fecha': ['', Validators.compose(
                [
                    Validators.required
                ],
            )]
        }, {});

        this.fes_descripcion = this.form.controls['fes_descripcion'];
        this.fes_fecha = this.form.controls['fes_fecha'];
    }

    /**
     * Construye un objeto para gestionar los errores en el formulario.
     * 
     */
    public buildErrorsObject() {
        this.formErrors = {
            fes_descripcion: {
                required: 'La descripción es requerida!',
                maxLength: 'Ha introducido más de 100 caracteres'
            },
            fes_fecha: {
                required: 'La Fecha es requerida!'
            }
        };   
    }

    /**
     * Se encarga de cargar los datos de un Festivo que se ha seleccionado en el tracking.
     * 
     */
    public loadFestivo(): void {
        this.loading(true);

        this.sistemaService.get(this.fesIdToGet).subscribe(
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
                    this.fes_descripcion.setValue(res.data.fes_descripcion);
                    let fecha = moment(res.data.fes_fecha).format();
                    this.fes_fecha.setValue(fecha);
                    this.objMagic['fecha_creacion'] = res.data.fecha_creacion;
                    this.objMagic['fecha_modificacion'] = res.data.fecha_modificacion;
                    this.objMagic['estado'] = res.data.estado;
                }
            },
            error => {
                let texto_errores = this.parseError(error);
                this.loading(false);
                this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al cargar el Festivo', 'Ok', 'btn btn-danger');
            }
        );
    }

    /**
     * Cierra la ventana modal de Festivos.
     * 
     */
    save(): void {
        if (this.modalRef) {
            this.modalRef.close(this.form.value);
        }
    }

    /**
     * Cierra la ventana modal de Festivos.
     * 
     */
    public closeModal(reload): void {
        this.modalRef.close();
        if(reload)
            this.parent.getData();
    }

    /**
     * Crea o actualiza un nuevo festivo.
     * 
     * @param values
     */
    public saveFestivo(values) {
        let formWithAction: any = values;
        formWithAction.fes_fecha = moment(formWithAction.fes_fecha).format("Y-MM-DD");
        this.loading(true);
        if (this.form.valid) {
            if (this.action === 'edit') {
                formWithAction.fes_id = this.fesIdToGet;
                this.sistemaService.update(formWithAction, this.fesIdToGet).subscribe(
                    response => {
                        this.loading(false);
                        this.showTimerAlert('<strong>Festivo actualizado correctamente.</strong>', 'success', 'center', 2000);
                        this.closeModal(true);
                    },
                    error => {
                        let texto_errores = this.parseError(error);
                        this.loading(false);
                        this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al actualizar el Festivo', 'Ok', 'btn btn-danger');
                    });
            } else {
                this.sistemaService.create(formWithAction).subscribe(
                    response => {
                        this.loading(false);
                        this.showTimerAlert('<strong>Festivo guardado correctamente.</strong>', 'success', 'center', 2000);
                        this.closeModal(true);
                    },
                    error => {
                        let texto_errores = this.parseError(error);
                        this.loading(false);
                        this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al guardar el Festivo', 'Ok', 'btn btn-danger');
                    });
                }
        }
    }
}
