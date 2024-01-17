import { Component, Inject, OnInit, Input, AfterViewInit } from '@angular/core';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { BaseComponentView } from 'app/main/core/base_component_view';
import { AbstractControl, FormBuilder, FormControl, FormGroup, Validators } from '@angular/forms';
import { JwtHelperService } from '@auth0/angular-jwt';
import { ConfiguracionService } from '../../../../../services/configuracion/configuracion.service';
import * as capitalize from 'lodash';

@Component({
    selector: 'app-grupos-trabajo-gestionar',
    templateUrl: './grupos-trabajo-gestionar.component.html',
    styleUrls: ['./grupos-trabajo-gestionar.component.scss'],
    providers: []
})
export class GruposTrabajoGestionarComponent extends BaseComponentView implements OnInit, AfterViewInit{

    @Input() ver: boolean;

    // Usuario en línea
    public usuario  : any;
    public objMagic = {};
    public ofes     : Array<any> = [];

    form           : FormGroup;
    gtrCodigoToGet : string;
    ofeIdeToGet    : string;
    action         : string;
    parent         : any;
    dataModal      : any;

    public formErrors               : any;
    public ofe_identificacion       : AbstractControl;
    public gtr_codigo               : AbstractControl;
    public gtr_nombre               : AbstractControl;
    public gtr_correos_notificacion : AbstractControl;
    public gtr_por_defecto          : AbstractControl;
    public estado                   : AbstractControl;

    public grupoTrabajoPlural       : string = '';
    public grupoTrabajoSingular     : string = '';
    public aplicaNotificarCorreos   : boolean = false;

    /**
     * Crea una instancia de GruposTrabajoGestionarComponent.
     * 
     * @param {FormBuilder} formBuilder
     * @param {MatDialogRef<GruposTrabajoGestionarComponent>} modalRef
     * @param {*} data
     * @param {JwtHelperService} _jwtHelperService
     * @param {ConfiguracionService} _configuracionService
     * @memberof GruposTrabajoGestionarComponent
     */
    constructor(
        private formBuilder           : FormBuilder,
        private modalRef              : MatDialogRef<GruposTrabajoGestionarComponent>,
        @Inject(MAT_DIALOG_DATA) data,
        private _jwtHelperService     : JwtHelperService,
        private _configuracionService : ConfiguracionService
    ) {
        super();
        this.initForm();
        this.buildErrorsObjetc();
        this.parent                 = data.parent;
        this.action                 = data.action;
        this.gtrCodigoToGet         = data.gtr_codigo;
        this.ofeIdeToGet            = data.ofe_identificacion;
        this.usuario                = this._jwtHelperService.decodeToken();
        this.grupoTrabajoPlural     = capitalize.startCase(capitalize.toLower(this.usuario.grupos_trabajo.plural));
        this.grupoTrabajoSingular   = capitalize.startCase(capitalize.toLower(this.usuario.grupos_trabajo.singular));
        this.aplicaNotificarCorreos = (this.usuario.notificar_grupos_trabajo == 'SI') ? true : false;
        this.ofes                   = data.ofes;
        this.dataModal              = data;
    }

    /**
     * ngOnInit de GruposTrabajoGestionarComponent.
     *
     * @memberof GruposTrabajoGestionarComponent
     */
    ngOnInit() {
        if (this.aplicaNotificarCorreos) {
            this.gtr_correos_notificacion.setValidators([Validators.required]);
            this.gtr_correos_notificacion.updateValueAndValidity();
        }

        if (this.action == "view") {
            this.ver = true;
        }

        if(this.action !== 'new') {
            if(this.action === 'edit') {
                let controlEstado: FormControl = new FormControl('', Validators.required);
                this.form.addControl('estado', controlEstado);
                this.estado = this.form.controls['estado'];
                this.loadGrupoTrabajo(this.dataModal.item);
            } else if (this.action === 'view') {
                this.disableFormControl(this.ofe_identificacion, this.gtr_codigo, this.gtr_nombre, this.gtr_correos_notificacion, this.gtr_por_defecto);
                this.loadGrupoTrabajo(this.dataModal.item);
            }
        }
    }

    /**
     * ngAfterViewInit de GruposTrabajoGestionarComponent.
     *
     * @memberof GruposTrabajoGestionarComponent
     */
    ngAfterViewInit() {
        if(this.action !== 'new')
            this.ofe_identificacion.setValue(this.ofeIdeToGet);

        if(this.ofes.length === 1)
            this.ofe_identificacion.setValue(this.ofes[0].ofe_identificacion);
    }

    /**
     * Inicializando el formulario.
     * 
     * @memberof GruposTrabajoGestionarComponent
     */
    private initForm(): void {
        this.form = this.formBuilder.group({
            'ofe_identificacion'       : this.requerido(),
            'gtr_codigo'               : this.requeridoMaxlong(10),
            'gtr_nombre'               : this.requeridoMaxlong(100),
            'gtr_por_defecto'          : ['NO'],
            'gtr_correos_notificacion' : ['']
        });
        
        this.ofe_identificacion       = this.form.controls['ofe_identificacion'];
        this.gtr_codigo               = this.form.controls['gtr_codigo'];
        this.gtr_nombre               = this.form.controls['gtr_nombre'];
        this.gtr_por_defecto          = this.form.controls['gtr_por_defecto'];
        this.gtr_correos_notificacion = this.form.controls['gtr_correos_notificacion'];
    }

    /**
     * Construye un objeto para gestionar los errores en el formulario.
     * 
     * @memberof CargosGestionarComponent
     */
    public buildErrorsObjetc() {
        this.formErrors = {
            ofe_identificacion: {
                required: 'El OFE es requerido!'
            },
            gtr_codigo: {
                required: 'El código es requerido!',
                maxLength: 'Ha introducido más de 10 caracteres'
            },
            gtr_nombre: {
                required: 'La descripción es requerida!',
                maxLength: 'Ha introducido más de 100 caracteres'
            },
            gtr_correos_notificacion: {
                invalid: 'Ha introducido un correo inválido!'
            },
        };
    }

    /**
     * Se encarga de setear los datos del registro de un Grupo de Trabajo que se ha seleccionado en el tracking.
     *
     * @param {*} data Información del registro seleccionado
     * @memberof GruposTrabajoGestionarComponent
     */
    public loadGrupoTrabajo(data) {
        if (this.action === 'edit') {
            if (data.estado === 'ACTIVO')
                this.estado.setValue('ACTIVO');
            else
                this.estado.setValue('INACTIVO');
        }
        this.gtr_codigo.setValue(data.gtr_codigo);
        this.gtr_nombre.setValue(data.gtr_nombre);
        this.gtr_por_defecto.setValue((data.gtr_por_defecto && data.gtr_por_defecto === 'SI') ? 'SI': 'NO');

        if (this.aplicaNotificarCorreos) {
            this.gtr_correos_notificacion.setValue(data.gtr_correos_notificacion ? data.gtr_correos_notificacion.split(',') : []);
        }

        this.objMagic['fecha_creacion'] = data.fecha_creacion;
        this.objMagic['fecha_modificacion'] = data.fecha_modificacion;
        this.objMagic['estado'] = data.estado;
    }

    /**
     * Cierra la ventana modal de Grupos de Trabajo.
     * 
     * @memberof GruposTrabajoGestionarComponent
     */
    public closeModal(reload): void {
        this.modalRef.close();
        if(reload)
            this.parent.getData();
    }

    /**
     * Crea o actualiza un nuevo Grupo de Trabajo.
     * 
     * @param values
     * @memberof GruposTrabajoGestionarComponent
     */
    public saveGrupoTrabajo(values) {
        let formWithAction: any = values;
        this.loading(true);
        if (this.form.valid) {
            if (formWithAction.gtr_correos_notificacion !== undefined && formWithAction.gtr_correos_notificacion !== '') {
                formWithAction.gtr_correos_notificacion = formWithAction.gtr_correos_notificacion.join(',');
            }

            if (this.action === 'edit') {
                this._configuracionService.updateGrupoTrabajo(formWithAction, this.ofeIdeToGet, this.gtrCodigoToGet).subscribe(
                    response => {
                        this.loading(false);
                        this.showTimerAlert('<strong>Registro actualizado correctamente.</strong>', 'success', 'center', 2000);
                        this.closeModal(true);
                    },
                    error => {
                        this.loading(false);
                        this.showError('<h4>' + error.errors + '</h4>', 'error', 'Error al actualizar el registro', 'Ok', 'btn btn-danger');
                    }
                );
            } else if(this.action === 'new') {
                this._configuracionService.create(formWithAction).subscribe(
                    response => {
                        this.loading(false);
                        this.showTimerAlert('<strong>Registro creado correctamente.</strong>', 'success', 'center', 2000);
                        this.closeModal(true);
                    },
                    error => {
                        this.loading(false);
                        this.showError('<h4>' + error.errors + '</h4>', 'error', 'Error al actualizar el registro', 'Ok', 'btn btn-danger');
                    }
                );
            }
        }
    }
}
