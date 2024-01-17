import {Component, Inject, OnInit} from '@angular/core';
import {BaseComponentView} from '../../core/base_component_view';
import {MAT_DIALOG_DATA, MatDialogRef} from '@angular/material/dialog';
import {AbstractControl, FormGroup, FormBuilder, FormArray, Validators} from '@angular/forms';
import {ProveedoresService} from '../../../services/configuracion/proveedores.service';
import {AdquirentesService} from '../../../services/configuracion/adquirentes.service';

@Component({
    selector: 'app-modal-usuarios-portales',
    templateUrl: './modal-usuarios-portales.component.html',
    styleUrls: ['./modal-usuarios-portales.component.scss']
})
export class ModalUsuariosPortalesComponent extends BaseComponentView implements OnInit {

    public parent                   : any;
    public registro                 : any;
    public ofe_id                   : number;
    public adq_id                   : number;
    public pro_id                   : number;
    public formUsuariosPortales     : FormGroup;
    public usuariosExistentes       : FormArray;
    public usuariosNuevos           : FormArray;
    public emisor                   : AbstractControl;
    public receptor                 : AbstractControl;
    public origen                   : any;
    public tituloEmisor             : any;
    public tituloReceptor           : any;
    public usuariosActivos          : number = 0;
    public usuariosPortalesAdmitidos: number = 0;

    /**
     * Constructor
     *
     * @param data
     * @param modalRef
     */
    constructor(
        @Inject(MAT_DIALOG_DATA) data,
        private modalRef           : MatDialogRef<ModalUsuariosPortalesComponent>,
        private formBuilder        : FormBuilder,
        private _proveedoresService: ProveedoresService,
        private _adquirentesService: AdquirentesService
    ) {
        super();
        this.parent   = data.parent;
        this.origen   = data.trackingOrigen;
        this.registro = data.registro;
    }

    ngOnInit() {
        this.initFormUsuarios();
    }

    initFormUsuarios() {
        this.formUsuariosPortales = this.formBuilder.group({
            'emisor'            : [],
            'receptor'          : [],
            'usuariosNuevos'    : this.formBuilder.array([]),
            'usuariosExistentes': this.formBuilder.array([])
        });

        this.emisor   = this.formUsuariosPortales.controls['emisor'];
        this.receptor = this.formUsuariosPortales.controls['receptor'];

        // Cantidad máxima de usuarios activos de portales admitidos
        this.usuariosPortalesAdmitidos = this.registro.usuarios_portales_admitidos;

        // Verifica si existen usuarios de portales
        if(this.registro.usuarios_portales != null && this.registro.usuarios_portales != undefined && this.registro.usuarios_portales.length > 0) {
            this.registro.usuarios_portales.forEach(usuario => {
                this.agregarUsuarioExistente(usuario);
                if(usuario.estado === 'ACTIVO')
                    this.usuariosActivos++;
            });
        }

        if(this.origen == 'proveedores') {
            this.ofe_id         = this.registro.ofe_id;
            this.pro_id         = this.registro.pro_id;
            this.tituloEmisor   = 'Proveedor';
            this.tituloReceptor = 'Oferente';
            this.formUsuariosPortales.get('emisor').setValue(this.registro.pro_razon_social);
            this.formUsuariosPortales.get('receptor').setValue(this.registro.ofe_razon_social);
        } else if(this.origen == 'adquirentes') {
            this.ofe_id         = this.registro.ofe_id;
            this.adq_id         = this.registro.adq_id;
            this.tituloEmisor   = 'Oferente';
            this.tituloReceptor = 'Adquirente';
            this.formUsuariosPortales.get('emisor').setValue(this.registro.ofe_identificacion + ' - ' + this.registro.oferente);
            this.formUsuariosPortales.get('receptor').setValue(this.registro.adq_identificacion + ' - ' + this.registro.adq_razon_social);
        }
    }

    /**
     * Cierra la ventana modal.
     *
     */
    closeModal(reload): void {
        this.modalRef.close();
        if(reload)
            this.parent.paginar(10);
    }

    /**
     * Agrega un ForGroup de usuario al formulario.
     *
     * @param {string} [identificacion='']
     * @param {string} [nombre='']
     * @param {string} [correo='']
     * @param {string} [id='']
     * @param {string} [estado='']
     * @returns {FormGroup}
     * @memberof ModalUsuariosPortalesComponent
     */
    agregarCamposUsuario(identificacion = '', nombre = '', correo = '', id = '', estado = ''): FormGroup {
        return this.formBuilder.group({
            identificacion: [identificacion, Validators.compose(
                [
                    Validators.required,
                    Validators.minLength(2),
                    Validators.maxLength(20),
                ]
            )],
            nombre: [nombre, Validators.compose(
                [
                    Validators.required,
                    Validators.minLength(8),
                    Validators.maxLength(255)
                ]
            )],
            email: [correo, Validators.compose(
                [
                    Validators.required,
                    Validators.email,
                    Validators.minLength(8),
                    Validators.maxLength(255)
                ]
            )],
            id: [id],
            estado: [estado]
        });
    }

    /**
     * Agrega los nuevos campos de usuario al formulario.
     *
     * @param object usuario Usuario existente
     * @memberof ModalUsuariosPortalesComponent
     */
     agregarUsuarioExistente(usuario): void {
        this.usuariosExistentes = this.formUsuariosPortales.get('usuariosExistentes') as FormArray;
        if(this.origen == 'proveedores')
            this.usuariosExistentes.push(this.agregarCamposUsuario(usuario.upp_identificacion, usuario.upp_nombre, usuario.upp_correo, usuario.upp_id, usuario.estado));
        else if(this.origen == 'adquirentes')
            this.usuariosExistentes.push(this.agregarCamposUsuario(usuario.upc_identificacion, usuario.upc_nombre, usuario.upc_correo, usuario.upc_id, usuario.estado));

        for (let grupo of this.usuariosExistentes.controls) {
            this.disableFormControl(grupo.get('identificacion'), grupo.get('nombre'), grupo.get('email'));
        }
    }

    /**
     * Agrega los nuevos campos de usuario al formulario.
     *
     * @memberof ModalUsuariosPortalesComponent
     */
    agregarUsuario(): void {
        if(this.usuariosActivos < this.usuariosPortalesAdmitidos) {
            this.usuariosNuevos = this.formUsuariosPortales.get('usuariosNuevos') as FormArray;
            this.usuariosNuevos.push(this.agregarCamposUsuario());
            this.usuariosActivos++;
        } else {
            this.showError('<h4>La cantidad máxima de usuarios activos de portales es ' + this.usuariosPortalesAdmitidos + '. Debe inactivar usuarios existentes para poder agregar nuevos usuarios</h4>', 'error', 'Usuarios Portales', '0k, entiendo', 'btn btn-danger');
        }
    }

    /**
     * ELimina un usuario de la grilla
     * @param i
     */
    eliminarUsuario(i: number) {
        const CTRL = <FormArray>this.formUsuariosPortales.controls['usuariosNuevos'];
        CTRL.removeAt(i);
        this.usuariosActivos > 0 ? this.usuariosActivos-- : this.usuariosActivos = 0;
    }

    /**
     * Procesa el formulario para crear los nuevos usuarios.
     *
     * @param object values Valores del formulario
     * @memberof ModalUsuariosPortalesComponent
     */
    guardarUsuarios(formValues): void {
        if(this.origen == 'proveedores') {
            let values = {
                'ofe_id': this.ofe_id,
                'pro_id': this.pro_id,
                'usuariosNuevos': formValues.usuariosNuevos
            }

            this.loading(true);
            this._proveedoresService.actualizarUsuariosPortales(values).subscribe(
                res => {
                    this.loading(false);
                    this.closeModal(true);
                    this.showSuccess('<strong>' + res.message + '</strong>', 'success', 'Usuarios Portales', 'Ok', 'btn btn-success');
                },
                error => {
                    this.loading(false);
                    const texto_errores = this.parseError(error);
                    this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Usuarios Portales', '0k, entiendo', 'btn btn-danger');
                });
        } else if(this.origen == 'adquirentes') {
            let values = {
                'ofe_id': this.ofe_id,
                'adq_id': this.adq_id,
                'usuariosNuevos': formValues.usuariosNuevos
            }

            this.loading(true);
            this._adquirentesService.actualizarUsuariosPortales(values).subscribe(
                res => {
                    this.loading(false);
                    this.closeModal(true);
                    this.showSuccess('<strong>' + res.message + '</strong>', 'success', 'Usuarios Portales', 'Ok', 'btn btn-success');
                },
                error => {
                    this.loading(false);
                    const texto_errores = this.parseError(error);
                    this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Usuarios Portales', '0k, entiendo', 'btn btn-danger');
                });
        }
    }

    cambiarEstadoUsuario(ctrlUsuario) {
        if(this.origen == 'proveedores') {
            let values = {
                'ofe_id': this.ofe_id,
                'pro_id': this.pro_id,
                'upp_id': ctrlUsuario.get('id').value
            }

            this.loading(true);
            this._proveedoresService.actualizarEstadoUsuarioPortales(values).subscribe(
                res => {
                    this.loading(false);
                    let estado = ctrlUsuario.get('estado').value;
                    if(estado === 'ACTIVO') {
                        this.usuariosActivos > 0 ? this.usuariosActivos-- : this.usuariosActivos = 0;
                    } else {
                        this.usuariosActivos++;
                    }
                    ctrlUsuario.get('estado').setValue(estado === 'ACTIVO' ? 'INACTIVO' : 'ACTIVO');
                    this.showSuccess('<strong>' + res.message + '</strong>', 'success', 'Usuarios Portales', 'Ok', 'btn btn-success');
                },
                error => {
                    this.loading(false);
                    const texto_errores = this.parseError(error);
                    this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Usuarios Portales', '0k, entiendo', 'btn btn-danger');
                });
        } else if(this.origen == 'adquirentes') {
            let values = {
                'ofe_id': this.ofe_id,
                'adq_id': this.adq_id,
                'upc_id': ctrlUsuario.get('id').value
            }

            this.loading(true);
            this._adquirentesService.actualizarEstadoUsuarioPortales(values).subscribe(
                res => {
                    this.loading(false);
                    let estado = ctrlUsuario.get('estado').value;
                    if(estado === 'ACTIVO') {
                        this.usuariosActivos > 0 ? this.usuariosActivos-- : this.usuariosActivos = 0;
                    } else {
                        this.usuariosActivos++;
                    }
                    ctrlUsuario.get('estado').setValue(estado === 'ACTIVO' ? 'INACTIVO' : 'ACTIVO');
                    this.showSuccess('<strong>' + res.message + '</strong>', 'success', 'Usuarios Portales', 'Ok', 'btn btn-success');
                },
                error => {
                    this.loading(false);
                    const texto_errores = this.parseError(error);
                    this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Usuarios Portales', '0k, entiendo', 'btn btn-danger');
                });
        }
    }
}
