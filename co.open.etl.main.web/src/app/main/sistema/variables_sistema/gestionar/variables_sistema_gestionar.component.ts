import {Component, Inject, OnInit} from '@angular/core';
import {AbstractControl, FormBuilder, FormControl, FormGroup, Validators} from '@angular/forms';
import {SistemaService} from '../../../../services/sistema/sistema.service';
import {BaseComponentView} from 'app/main/core/base_component_view';
import {MomentDateAdapter} from '@angular/material-moment-adapter';
import {DateAdapter, MAT_DATE_FORMATS, MAT_DATE_LOCALE} from '@angular/material/core';
import {JwtHelperService} from '@auth0/angular-jwt';
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
    selector: 'variables-sistema-gestionar',
    templateUrl: './variables_sistema_gestionar.component.html',
    styleUrls: ['./variables_sistema_gestionar.component.scss'],
    providers: [
        { provide: MAT_DATE_LOCALE, useValue: 'es-ES' },
        { provide: DateAdapter, useClass: MomentDateAdapter, deps: [MAT_DATE_LOCALE] },
        { provide: MAT_DATE_FORMATS, useValue: MY_FORMATS },
    ]
})
export class VariablesSistemaGestionarComponent extends BaseComponentView implements OnInit{

    // Usuario en línea
    public usuario: any;
    public objMagic = {};

    form      : FormGroup;
    vsiIdToGet: number;
    action    : string;
    parent    : any;

    public formErrors     : any;
    public vsi_nombre     : AbstractControl;
    public vsi_valor      : AbstractControl;
    public vsi_descripcion: AbstractControl;
    public vsi_ejemplo    : AbstractControl;
    public estado         : AbstractControl;

    /**
     * Crea una instancia de VariablesSistemaGestionarComponent.
     * 
     * @param {FormBuilder} formBuilder
     * @param {MatDialogRef<VariablesSistemaGestionarComponent>} modalRef
     * @param {*} data
     * @param {SistemaService} sistemaService
     * @param {JwtHelperService} jwtHelperService
     * @memberof VariablesSistemaGestionarComponent
     */
    constructor(
        private formBuilder: FormBuilder,
        private modalRef: MatDialogRef<VariablesSistemaGestionarComponent>,
        @Inject(MAT_DIALOG_DATA) data,
        private sistemaService: SistemaService,
        private jwtHelperService: JwtHelperService) {
            super();
            this.initForm();
            this.parent     = data.parent;
            this.action     = data.action;
            this.vsiIdToGet = data.vsi_id;
            this.usuario    = this.jwtHelperService.decodeToken();
    }

    /**
     * ngOnInit de VariablesSistemaGestionarComponent.
     *
     * @memberof VariablesSistemaGestionarComponent
     */
    ngOnInit() {
        if (this.action === 'edit') {
            let controlEstado: FormControl = new FormControl('', Validators.required);
            this.form.addControl('estado', controlEstado);
            this.estado = this.form.controls['estado'];
            this.loadVariableSistema();  
        } 
        if (this.action === 'view'){
            this.disableFormControl(this.vsi_nombre, this.vsi_valor, this.vsi_descripcion, this.vsi_ejemplo);
            this.loadVariableSistema();
        } 
    }

    /**
     * Inicializando el formulario.
     * 
     * @memberof VariablesSistemaGestionarComponent
     */
    private initForm(): void {
        this.form = this.formBuilder.group({
            'vsi_nombre'     : [''],
            'vsi_valor'      : this.requerido(),
            'vsi_descripcion': [''],
            'vsi_ejemplo'    : ['']
        });

        this.vsi_nombre       = this.form.controls['vsi_nombre'];
        this.vsi_valor        = this.form.controls['vsi_valor'];
        this.vsi_descripcion  = this.form.controls['vsi_descripcion'];
        this.vsi_ejemplo      = this.form.controls['vsi_ejemplo'];
    }

    /**
     * Se encarga de cargar los datos de una variable del sistema que se ha seleccionado en el tracking.
     * 
     * @memberof VariablesSistemaGestionarComponent
     */
    public loadVariableSistema(): void {
        this.loading(true);

        this.sistemaService.get(this.vsiIdToGet).subscribe(
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
                    this.vsi_nombre.setValue(res.data.vsi_nombre);
                    this.vsi_valor.setValue(res.data.vsi_valor);
                    this.vsi_descripcion.setValue(res.data.vsi_descripcion);
                    this.vsi_ejemplo.setValue(res.data.vsi_ejemplo);
                    this.objMagic['fecha_creacion'] = res.data.fecha_creacion;
                    this.objMagic['fecha_modificacion'] = res.data.fecha_modificacion;
                    this.objMagic['estado'] = res.data.estado;
                }
            },
            error => {
                let texto_errores = this.parseError(error);
                this.loading(false);
                this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al cargar el VariableSistema', 'Ok', 'btn btn-danger');
            }
        );
    }

    /**
     * Cierra la ventana modal de VariableSistemas.
     * 
     * @memberof VariablesSistemaGestionarComponent
     */
    save(): void {
        if (this.modalRef) {
            this.modalRef.close(this.form.value);
        }
    }

    /**
     * Cierra la ventana modal de VariableSistemas.
     * 
     * @memberof VariablesSistemaGestionarComponent
     */
    public closeModal(reload): void {
        this.modalRef.close();
        if(reload)
            this.parent.getData();
    }

    /**
     * Crea o actualiza una nueva Variable Sistema.
     * 
     * @param values
     * @memberof VariablesSistemaGestionarComponent
     */
    public saveVariableSistema(values) {
        let formWithAction: any = values;
        this.loading(true);
        if (this.form.valid) {
            if (this.action === 'edit') {
                formWithAction.vsi_id = this.vsiIdToGet;
                this.sistemaService.update(formWithAction, this.vsiIdToGet).subscribe(
                    response => {
                        this.loading(false);
                        this.showTimerAlert('<strong>Variable del sistema actualizada correctamente.</strong>', 'success', 'center', 2000);
                        this.closeModal(true);
                    },
                    error => {
                        this.loading(false);
                        this.showError('<h4>' + error.errors + '</h4>', 'error', 'Error al actualizar la variable del sistema', 'Ok', 'btn btn-danger');
                    }
                );
            }
        }
    }
}
