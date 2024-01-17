import {Component, Inject, OnInit} from '@angular/core';
import {AbstractControl, FormBuilder, FormGroup, Validators} from '@angular/forms';
import {UsuariosService} from '../../../../services/sistema/usuarios.service';
import {BaseComponentView} from 'app/main/core/base_component_view';
import { MatDialogRef, MAT_DIALOG_DATA } from '@angular/material/dialog';

@Component({
  selector: 'cambiar-clave-usuario',
  templateUrl: './cambiar_clave_usuario.component.html',
  styleUrls: ['./cambiar_clave_usuario.component.scss']
})
export class CambiarClaveUsuarioComponent extends BaseComponentView implements OnInit{

    form: FormGroup;
    usuId: number;
    parent: any;
    public formErrors: any;

    public password: AbstractControl;
    public usu_nombre: '';
    public usu_email: '';

    /**
     * Constructor
     * @param formBuilder
     * @param modalRef
     * @param data
     * @param _usuariosService
     */
    constructor(
        private formBuilder: FormBuilder,
        private modalRef: MatDialogRef<CambiarClaveUsuarioComponent>,
        @Inject(MAT_DIALOG_DATA) data,
        private _usuariosService: UsuariosService) {
            super();
            this.initForm();
            this.buildErrorsObject();
            this.parent = data.parent;
            this.usuId = data.usu_id;
            this.loadUser();
    }

    ngOnInit() {  
    }

    /**
     * Inicializando el formulario.
     * 
     */
    private initForm(): void {
        this.form = this.formBuilder.group({
            'password': ['', Validators.compose(
                [
                    Validators.required,
                    Validators.minLength(6),
                    Validators.maxLength(20)
                ],
            )]
        }, {});

        this.password = this.form.controls['password'];
    }

    /**
     * Construye un objeto para gestionar los errores en el formulario.
     * 
     */
    public buildErrorsObject() {
        this.formErrors = {
            password: {
                required: 'Debe introducir una nueva clave',
                minLength: 'La nueva clave debe poseer un mínimo de 6 caracteres',
                maxLength: 'La nueva clave debe poseer un máximo de 20 caracteres'
            }
        };
    }

    /**
     * Se encarga de cargar los datos de un Tipo de Documento que se ha seleccionado en el tracking.
     * 
     */
    public loadUser(): void {
        this.loading(true);
        this._usuariosService.getUsuario(this.usuId).subscribe(
            res => {
                if (res) {
                    this.loading(false);
                    this.usu_nombre = res.data.usu_nombre;
                    this.usu_email = res.data.usu_email;
                }
            },
            error => {
                let texto_errores = this.parseError(error);
                this.loading(false);
                this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al cargar datos del Usuario', 'Ok', 'btn btn-danger');
            }
        );
    }

    /**
     * Cierra la ventana modal de Cambio de Clave de Usuario.
     * 
     */
    save(): void {
        if (this.modalRef) {
            this.modalRef.close(this.form.value);
        }
    }

    /**
     * Cierra la ventana modal de Cambio de Clave de Usuario.
     * 
     */
    public closeModal(reload): void {
        this.modalRef.close();
        this.parent.getData();
    }

    /**
     * Actualiza la clave.
     * 
     * @param values
     */
    public changePassword(values) {
        let formWithAction: any = values;
        this.loading(true);
        if (this.form.valid) {
            formWithAction.usu_id = this.usuId;
            this._usuariosService.cambiaPassword(formWithAction, this.usuId).subscribe(
                response => {
                    this.loading(false);
                    this.showTimerAlert('<strong>Clave de usuario actualizada correctamente.</strong>', 'success', 'center', 2000);
                    this.closeModal(true);
                },
                error => {
                    let texto_errores = this.parseError(error);
                    this.loading(false);
                    this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al actualizar la clave de usuario', 'Ok', 'btn btn-danger');
                });
        }
    }
}
