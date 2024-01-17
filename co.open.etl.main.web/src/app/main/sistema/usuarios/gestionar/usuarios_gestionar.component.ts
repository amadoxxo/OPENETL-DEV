import {ActivatedRoute, Router} from '@angular/router';
import {BaseComponentList} from '../../../core/base_component_list';
import {Component, ElementRef, OnInit, ViewChild} from '@angular/core';
import {CommonsService} from '../../../../services/commons/commons.service';
import {UsuariosService} from '../../../../services/sistema/usuarios.service';
import {AbstractControl, FormBuilder, FormGroup, FormArray, Validators} from '@angular/forms';
import {debounceTime, finalize, switchMap, tap, distinctUntilChanged, filter} from 'rxjs/operators';
import { JwtHelperService } from '@auth0/angular-jwt';

@Component({
    selector: 'gestionar-usuarios.service',
    templateUrl: './usuarios_gestionar.component.html',
    styleUrls: ['./usuarios_gestionar.component.scss']
})
export class UsuariosGestionarComponent extends BaseComponentList implements OnInit
{
    // Usuario en línea
    public usuario           : any;
    public objMagic          = {};
    public permisoActivado   : boolean;
    public showButton        = true;
    public usu_id            : number;
    public textoSeccion      : string;
    public arrRoles          = new Array();
    public form              : FormGroup;
    public datosGenerales    : FormGroup;
    public ofesColeccion     : FormGroup;
    public usu_nombre        : AbstractControl;
    public usu_identificacion: AbstractControl;
    public usu_email         : AbstractControl;
    public usu_direccion     : AbstractControl;
    public usu_telefono      : AbstractControl;
    public usu_movil         : AbstractControl;
    public usu_type          : AbstractControl;
    public noCoincidences    : boolean;
    public panelOpenState;

    public ofes                    : FormArray;
    // public estado               : AbstractControl;
    public estado                  = "";
    public tabla                   : any;
    public tablaInicializada       = false;
    public asignados               = false;
    public textoAsignados          = 'Filtrar roles asignados';
    public marcarTodos             = false;
    public textoActDesTodos        = 'Marcar roles en pantalla';
    public filteredOfes            : any = [];
    public formErrors              : any;
    public selectedValue           : any;
    public estadoActual            : any;
    public loadingIndicator        : any;
    public ver                     : boolean = false;
    public editar                  : boolean = false;
    public editarUsuarioIntegracion: boolean = false;


    public tiposUsuario = [
        {id: 'OPERATIVO', itemName: 'Operativo'},
        {id: 'INTEGRACION', itemName: 'Integración'}
    ];

    // Ofes seleccionados para poder cargarle documentos masivos por medio de openIDE
    public _ofesIde: any = [];

    // Proveedores seleccionados para ser accesados por el usuario
    public _proveedoresAgregados: any = [];

    // Proveedores seleccionados para ser eliminados al usuario
    public _proveedoresEliminados: any = [];    

    @ViewChild('r') ref: ElementRef;

    /**
     * Constructor
     * @param fb
     * @param _usuariosService
     * @param route
     * @param router
     */
    constructor(
        private fb             : FormBuilder,
        public route           : ActivatedRoute,
        private router         : Router,
        public _commonsService : CommonsService,
        public _usuariosService: UsuariosService,
        private jwtHelperService: JwtHelperService
    ) {
        super();
        this.rows = [];
        this.buildErrorsObject();
        this.init();
        this.usuario = this.jwtHelperService.decodeToken();
    }

    /**
     * Inicializa los controles del Formulario de Nuevo Usuario.
     *
     */
    private init(): void {
        this.form = this.fb.group({
            DatosGenerales: this.buildFormularioDatosGenerales(),
            ofesColeccion: this.buildFormularioOfes(),
            // ofes_ide: this.fb.array([
            //     this.ofesIde()
            // ]),
            // proveedores: this.fb.array([
            //     this.proveedoresAcceso()
            // ]),
        });

        if (this.route.snapshot.params['usu_identificacion'] !== undefined) {
            this.disableFormControl(this.usu_nombre, this.usu_identificacion, this.usu_email, this.usu_direccion,
                    this.usu_telefono, this.usu_movil, this.usu_type);
            this.ver = true;        
        }

        // Si llega un usu_id en la URL se debe cargar la información del usuario y sus roles
        if (this.route.snapshot.params['usu_id'] !== undefined) {
            this.usu_id = this.route.snapshot.params['usu_id'];
            this.textoSeccion = 'Editar Usuario';
            this.editar = true;
            // Campo dinámico para el estado del registro
            // let controlEstado: FormControl = new FormControl('', Validators.required);
            // this.form.addControl('estado', controlEstado);
            // this.estado = this.form.controls['estado'];

            // Consulta el usuario y los roles asignados
            this.loading(true);
            this._usuariosService.getUsuario(this.usu_id).subscribe(
                response => {
                    this.loading(false);
                    this.estado = response.data.estado;
                    this.usu_nombre.setValue(response.data.usu_nombre);
                    this.usu_identificacion.setValue(response.data.usu_identificacion);
                    this.usu_email.setValue(response.data.usu_email);
                    this.usu_direccion.setValue(response.data.usu_direccion);
                    this.usu_telefono.setValue(response.data.usu_telefono);
                    this.usu_movil.setValue(response.data.usu_movil);
                    this.usu_type.setValue(response.data.usu_type);
                    this.objMagic['fecha_creacion'] = response.data.fecha_creacion;
                    this.objMagic['fecha_modificacion'] = response.data.fecha_modificacion;
                    this.objMagic['estado'] = response.data.estado;
                    // if (response.rol.estado === 'ACTIVO') {
                    //     this.form.controls['estado'].setValue('ACTIVO');
                    // } else {
                    //     this.form.controls['estado'].setValue('INACTIVO');
                    // }
                    if (response.data.get_roles_usuario.length > 0) {
                        for (let roles of response.data.get_roles_usuario) {
                            this.arrRoles.push(roles.rol_id);
                        }
                    }
                    this.rows = [];
                    this.initDataSort('rol_id');
                    this.loadingIndicator = true;
                    this.loadRoles();

                    if(this.usu_type.value === 'INTEGRACION') {
                        this.editarUsuarioIntegracion = true;
                        this.disableFormControl(this.usu_nombre, this.usu_identificacion, this.usu_email, this.usu_direccion, this.usu_telefono, this.usu_movil, this.usu_type);
                    }

                    // let ofes_ide_form = (<FormArray>this.form.controls['ofes_ide']);
                    // let proveedores_form = (<FormArray>this.form.controls['proveedores']);

                    // Cargando los ofes para cargas masivas que se han asignado
                    // this._usuariosService.getOfesIdeUsuario(this.usu_id).subscribe(
                    //     resultado => {
                    //         resultado.oferentes.forEach((item, index) => {
                    //             if (index > 0) {
                    //                 this.agregarOfesParaCargaMasiva();
                    //             }
                    //             ofes_ide_form.at(index).patchValue({
                    //                 ofe_identificacion: item.ofe_identificacion,
                    //                 ofe_razon_social: item.ofe_razon_social
                    //             });
                    //         });
                    //         this._ofesIde = resultado.oferentes;

                    //         this._usuariosService.getProveedoresGestionables(this.usu_id).subscribe(
                    //             resultadoProveedores => {
                    //                 resultadoProveedores.proveedores.forEach((item, index) => {
                    //                     if (index > 0) {
                    //                         this.agregarProvsParaCargaMasiva();
                    //                     }
                    //                     proveedores_form.at(index).patchValue({
                    //                         pro_identificacion: item.pro_identificacion,
                    //                         pro_razon_social: item.pro_razon_social
                    //                     });
                    //                 });
                    //                 this._proveedoresAgregados = resultadoProveedores.proveedores;
                    //             },
                    //             err => {
                    //                 this.loading(false);
                    //                 let message = (err.message.length > 0) ? err.message : 'Error al Cargar los Proveedores que puede gestionar el usuario';
                    //                 let errors = '';
                    //                 if (Array.isArray(err.errors) && err.errors.length > 0) {
                    //                     err.errors.forEach(strError => {
                    //                         errors += '<li>' + strError + '</li>';
                    //                     });
                    //                 }
                    //                 this.showError('<h3>' + message + '</h3>' + ((errors.length > 0) ? '<span style="text-align:left"><ul>' + errors + '</ul></span>' : ''), 'error', 'Error al cargar proveedores', 'Cerrar', 'btn btn-danger');
                    //             }
                    //         );
                    //     },
                    //     err => {
                    //         this.loading(false);
                    //         let message = (err.message.length > 0) ? err.message : 'Error al Cargar los Ofes para cargas masivas';
                    //         let errors = '';
                    //         if (Array.isArray(err.errors) && err.errors.length > 0) {
                    //             err.errors.forEach(strError => {
                    //                 errors += '<li>' + strError + '</li>';
                    //             });
                    //         }
                    //         this.showError('<h3>' + message + '</h3>' + ((errors.length > 0) ? '<span style="text-align:left"><ul>' + errors + '</ul></span>' : ''), 'error', 'Error al cargar Ofes', 'Cerrar', 'btn btn-danger');
                    //     }
                    // )

                    if (this.route.snapshot.params['usu_identificacion'] !== undefined) {
                        this.textoSeccion = 'Ver Usuario';
                        this.ver = true;
                        this.usu_type.disable();
                    }
                },
                error => {
                    this.loading(false);
                    let errores = '';
                    if (error.errors instanceof Array && error.errors.length > 0) {
                        error.errors.forEach(strError => {
                            errores += strError + '<br>';
                        });
                    } else {
                        errores = error.errors;
                    }
                    this.showError("<h3>No se pudo obtener el usuario</h3><br><strong>" + error.message + "</strong><br>" + errores, 'error', 'Error al obtener el usuario', 'Cerrar', 'btn btn-danger');
                }
            );
        } else {
            this.textoSeccion = 'Crear Usuario';
            this.usu_id = 0;
        }
    }

    /**
     * Si no llega un usu_id en la URL se carga la tabla de roles.
     * 
     */
    ngOnInit() {
        if (this.route.snapshot.params['usu_id'] === undefined) {
            this.rows = [];
            this.initDataSort('rol_id');
            this.loadingIndicator = true;
            this.loadRoles();
        }
    }

     /**
     * Construccion del formulario de datos personales.
     * 
     */
    buildFormularioDatosGenerales() {
        this.datosGenerales = this.fb.group({
            usu_nombre: ['', Validators.compose(
                [
                    Validators.required,
                    Validators.maxLength(255),
                    Validators.minLength(8)
                ],
            )],
            usu_identificacion: this.requeridoMaxlong(20),
            usu_email: this.emailRequerido(),
            // 'bdd_id': [''],
            usu_direccion: this.maxlong(255),
            usu_telefono: this.requeridoMaxlong(50),
            usu_movil: this.maxlong(50),
            usu_type: this.requerido()
        });

        this.usu_nombre         = this.datosGenerales.controls['usu_nombre'];
        this.usu_identificacion = this.datosGenerales.controls['usu_identificacion'];
        this.usu_email          = this.datosGenerales.controls['usu_email'];
        this.usu_direccion      = this.datosGenerales.controls['usu_direccion'];
        this.usu_telefono       = this.datosGenerales.controls['usu_telefono'];
        this.usu_movil          = this.datosGenerales.controls['usu_movil'];
        this.usu_type           = this.datosGenerales.controls['usu_type'];

        return this.datosGenerales;
    }

    /**
     * Construcción del formulario de Ofes.
     * 
     */
    buildFormularioOfes() {
        this.ofesColeccion = this.fb.group({
            ofes: this.buildFormularioOfesArray()
        });
        return this.ofesColeccion;
    }

    /**
     * Construcción del array de ofes.
     * 
     */
    buildFormularioOfesArray() {
        this.ofes = this.fb.array([
            this.ofesIde()
        ])
        return this.ofes;
    }

    /**
     * Permite la creación dinámica de las diferentes filas y campos en el apartado OFES.
     * 
     * @returns Grupo de formulario con campos código y descripción
     */
    private ofesIde(): any {
        return this.fb.group({
            ofe_identificacion: [''],
            ofe_razon_social: ['']
        });
    }

    /**
     * Construye un objeto para gestionar los errores en el formulario.
     * 
     */
    public buildErrorsObject() {
        this.formErrors = {
            usu_identificacion: {
                required: 'La identificación del Usuario es requerida!',
                maxLength: 'Ha introducido más de 20 caracteres'
            },
            usu_nombre: {
                required: 'El Nombre es requerido!',
                minLength: 'El nombre debe ser mínimo de 8 caracteres',
                maxLength: 'Ha introducido más de 255 caracteres'
            },
            usu_email: {
                required: 'El Email es requerido!',
                email: 'El email no es válido',
                minLength: 'El email debe ser mínimo de 6 caracteres',
                maxLength: 'Ha introducido más de 255 caracteres'
            },
            usu_type: {
                required: 'El tipo de usuario es requerido!'
            },
            usu_direccion: {
                maxLength: 'Ha introducido más de 255 caracteres'
            },
            usu_telefono: {
                required: 'El teléfono es requerido',
                maxLength: 'Ha introducido más de 50 caracteres'
            },
            usu_movil: {
                maxLength: 'Ha introducido más de 50 caracteres'
            }
        };
    }

    /**
     * Sobreescribe los parametros de busqueda inline - (Get).
     * 
     */
    getSearchParametersInline(): string {
        let ar = '';
        let first = true;
        if (this.arrRoles) {
            for (let t of this.arrRoles) {
                if (first) {
                    ar = 'arrRoles[]=' + t;
                } else {
                    ar = ar + '&arrRoles[]=' + t;
                }
                first = false;
            }
        }

        let query = 'start=' + this.start + '&' +
            'length=' + this.length + '&' +
            'buscar=' + this.buscar + '&' +
            'asignados=' + this.asignados + '&' +
            'columnaOrden=' + this.columnaOrden + '&' +
            'ordenDireccion=' + this.ordenDireccion;

        return (ar != '') ? query + '&' + ar : query;
    }

    /**
     * Se encarga de traer los roles para el usuario.
     * 
     */
    public loadRoles(): void {
        this.loading(true);
        this._usuariosService.listarRoles(this.getSearchParametersInline()).subscribe(
            res => {
                this.loading(false);
                this.rows = res.data;
                this.totalElements = res.filtrados;
                this.loadingIndicator = false;
                this.totalShow = this.length !== -1 ? this.length : this.totalElements;

            },
            error => {
                this.loading(false);
                const texto_errores = this.parseError(error);
                this.loadingIndicator = false;
                this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al cargar los roles', 'OK', 'btn btn-warning');
            });
    }

    /**
     * Crea un nuevo usuario con sus roles o edita uno existente con los datos provenientes del Formulario de Nuevo Usuario.
     * 
     * @param values
     */
    resourceUsuario(values): void {
        this.loading(true);
        if (this.form.valid) {
            // let ofes_ide = '';
            // let proveedoresAceptados = '';
            // let proveedoresEliminados = '';

            // delete values.agregarProveedores;
            // delete values.eliminarProveedores;

            // if (this._ofesIde && this._ofesIde.length > 0) {
            //     for (let i = 0; i < this._ofesIde.length; i++) {
            //         if (i === 0)
            //             ofes_ide = this._ofesIde[i].ofe_identificacion;
            //         else
            //             ofes_ide = ofes_ide + ',' + this._ofesIde[i].ofe_identificacion;
            //     }
            // }
            // values.ofes_ide = ofes_ide;

            // if (this._proveedoresAgregados && this._proveedoresAgregados.length > 0) {
            //     for (let i = 0; i < this._proveedoresAgregados.length; i++) {
            //         if (i === 0)
            //             proveedoresAceptados = this._proveedoresAgregados[i].pro_identificacion;
            //         else
            //             proveedoresAceptados = proveedoresAceptados + ',' + this._proveedoresAgregados[i].pro_identificacion;
            //     }
            // }
            // values.agregarProveedores = proveedoresAceptados;

            // if (this._proveedoresEliminados && this._proveedoresEliminados.length > 0) {
            //     for (let i = 0; i < this._proveedoresEliminados.length; i++) {
            //         if (i === 0)
            //             proveedoresEliminados = this._proveedoresEliminados[i].pro_identificacion;
            //         else
            //             proveedoresEliminados = proveedoresEliminados + ',' + this._proveedoresEliminados[i].pro_identificacion;
            //     }
            // }
            // values.eliminarProveedores = proveedoresEliminados;
            // delete values.proveedores;

            let that = this;
            if (this.route.snapshot.params['usu_id'] === undefined) { // Crea un nuevo usuario
                this._usuariosService.createUsuario(values.DatosGenerales).subscribe(
                    response => {
                        this.loading(false);
                        this.usu_id = response.usu_id;
                        this.showSuccess('<h3>' + '</h3>', 'success', 'Usuario creado exitosamente', 'Ok', 'btn btn-success', '/sistema/usuarios/editar-usuario/' + response.usu_id, this.router);
                        // that.router.navigate(['/sistema/usuarios/editar-usuario/' + this.usu_id]);
                    },
                    error => {
                        this.usu_id = error.usu_id;
                        this.loading(false);
                        this._proveedoresEliminados = [];
                        let errores = '';
                        if (error.errors instanceof Array && error.errors.length > 0) {
                            error.errors.forEach(strError => {
                                errores += strError + '<br>';
                            });
                        } else {
                            errores = error.errors;
                        }
                        this.showError(errores, 'error', error.message, 'Cerrar', 'btn btn-danger');
                        // that.router.navigate(['/sistema/usuarios/editar-usuario/' + this.usu_id]);
                    }
                );
            } else if (this.route.snapshot.params['usu_id'] !== undefined) { // Edita un usuario existente
                values.DatosGenerales.estado = this.estado;
                this._usuariosService.updateUsuario(values.DatosGenerales, this.usu_id).subscribe(
                    response => {
                        this.loading(false);
                        this.usu_id = response.usu_id;
                        this._proveedoresEliminados = [];
                        this.showSuccess('<h3>' + '</h3>', 'success', 'Usuario actualizado exitosamente', 'Ok', 'btn btn-success', '/sistema/usuarios/editar-usuario/' + response.usu_id, this.router);
                    },
                    error => {
                        this.loading(false);
                        let errores = '';
                        if (error.errors.length > 0) {
                            error.errors.forEach(strError => {
                                errores += strError + '<br>';
                            });
                        } else {
                            errores = error.errors;
                        }
                        this.showError(errores, 'error', error.message, 'Cerrar', 'btn btn-danger');
                    }
                );
            }
        }
    }

    /**
     * Activa o desactiva los roles correspondientes para un usuario.
     * 
     * @param value
     * @param rol_id
     */
    rolesUsuario(value, rol_id) {
        this.loading(true);
        let that = this;
        let accion = value.checked ? 'set' : 'unset';
        if (accion === 'set') {
            this._usuariosService.setRolUsuario(this.usu_id, rol_id).subscribe(
                response => {
                    this.loading(false);
                    if (this.arrRoles.indexOf(rol_id) === -1) {
                        this.arrRoles.push(rol_id);
                    }
                    this.showTimerAlert('<strong>' + response.message + '</strong>', 'success', 'center', 1000);
                },
                error => {
                    this.loading(false);
                    let errores = '';
                    if (error.errors instanceof Array && error.errors.length > 0) {
                        error.errors.forEach(strError => {
                            errores += strError + '<br>';
                        });
                    } else {
                        errores = error.errors;
                    }
                    this.showError('<h3>No se pudo asignar el rol</h3><br><strong>' + error.message + '</strong><br>' + errores, 'error', 'Error al asignar el rol', 'Cerrar', 'btn btn-danger');
                }
            );
        } else if (accion === 'unset') {
            this._usuariosService.unsetRolUsuario(this.usu_id, rol_id).subscribe(
                response => {
                    this.loading(false);
                    if (this.arrRoles.indexOf(rol_id) !== -1) {
                        this.arrRoles.splice(this.arrRoles.indexOf(rol_id), 1);
                    }
                    this.showTimerAlert('<strong>' + response.message + '</strong>', 'warning', 'center', 1000);
                },
                error => {
                    this.loading(false);
                    let errores = '';
                    if (error.errors instanceof Array && error.errors.length > 0) {
                        error.errors.forEach(strError => {
                            errores += strError + '<br>';
                        });
                    } else {
                        errores = error.errors;
                    }
                    this.showError('<h3>No se pudo eliminar el rol</h3><br><strong>' + error.message + '</strong><br>' + errores, 'error', 'Error al eliminar el rol', 'Cerrar', 'btn btn-danger');
                }
            );
        }
    }

    /**
     * Activa o desactiva en lote todos los roles existentes para un usuario.
     *
     */
    actDesTodos() {
        if (this.usu_id === 0) {
            this.noUser();
        } else {
            this.marcarTodos = !this.marcarTodos;
            let arrRolesId = new Array;
            let accion = '';
            if (this.rows) {
                for (let r of this.rows) {
                    arrRolesId.push(r.rol_id);
                }
            }

            if (!this.marcarTodos) {
                this.textoActDesTodos = 'Marcar roles en pantalla';
                accion = 'inactivar';

                // Los roles se eliminarán, por lo que deben eliminarse del arrRoles
                arrRolesId.forEach(oldRol => {
                    if (this.arrRoles.indexOf(oldRol) !== -1) {
                        this.arrRoles.splice(this.arrRoles.indexOf(oldRol), 1);
                    }
                });
            } else {
                this.textoActDesTodos = 'Desmarcar roles en pantalla';
                accion = 'activar';

                // Los roles se agregarán, por lo que se deben agregar también a arrRoles
                arrRolesId.forEach(newRol => {
                    if (this.arrRoles.indexOf(newRol) === -1) {
                        this.arrRoles.push(newRol);
                    }
                });
            }

            this.loading(true);
            this._usuariosService.actDesBloqueRoles(accion, this.usu_id, arrRolesId).subscribe(
                response => {
                    this.loading(false);
                    this.showSuccess('<strong>' + response.message + '</strong>', 'success', 'Roles asignados correctamente', 'Ok', 'btn btn-success');
                },
                error => {
                    this.loading(false);
                    let errores = '';
                    if (error.errors instanceof Array && error.errors.length > 0) {
                        error.errors.forEach(strError => {
                            errores += strError + '<br>';
                        });
                    } else {
                        errores = error.errors;
                    }
                    this.showError('<h3>No se pudo asignar los roles</h3><br><strong>' + error.message + '</strong><br>' + errores, 'error', 'Error al asignar el permiso', 'Cerrar', 'btn btn-danger');
                }
            );
        }
    }

    /**
     * Dispara un cuadro de alerta indicando que no se encontro ningún usuario al cual asignar roles.
     *
     */
    noUser() {
        this.showTimerAlert('<strong>Ningún usuario en memoria</strong><br>Debe crear un nuevo usuario o editar uno existente para poder activar/desactivar roles', 'error', 'center', 3000);
    }

    /**
     * Filtra y muestra solo los roles activados para ese usuario, ocultando los restantes.
     *
     */
    filtrarAsignados() {
        if (this.usu_id === 0) {
            this.noUser();
        } else {
            this.asignados = !this.asignados;
            if (!this.asignados) {
                this.textoAsignados = 'Filtrar roles asignados';
            } else {
                this.textoAsignados = 'Mostrar todos';
            }
            this.getData();
        }
    }

    /**
     * Método utilizado por los checkbox en los listados.
     * 
     * @param evt
     */
    onCheckboxChangeFn(evt: any) {

    }

    /**
     * Gestiona el evento de paginación de la grid.
     * 
     * @param $evt
     */
    public onPage($evt) {
        this.start = $evt.offset * this.length;
        this.getData();
    }

    /**
     * Sobreescritura del método onSort.
     * 
     * @param $evt
     */
    public onSort($evt) {
        let column = $evt.column.prop;
        column = column.replace('.date', '');
        this.columnaOrden = column;
        this.ordenDireccion = $evt.newValue.toUpperCase();
        this.getData();
    }

    /**
     * Efectúa la carga de datos.
     * 
     */
    public getData() {
        this.loadingIndicator = true;
        this.loadRoles();
    }

    /**
     * Cambia el número de items a mostrar y refresca la grid.
     * 
     * @param evt
     */
    paginar(evt) {
        this.length = evt;
        this.getData();
    }

    /**
     * Evento de selectall del checkbox primario de la grid.
     * 
     * @param selected
     */
    onSelect({selected}) {
        this.selected.splice(0, this.selected.length);
        this.selected.push(...selected);
    }

    /**
     * Evento de búsqueda rápida.
     * 
     */
    searchinline() {
        this.getData();
    }

    /**
     * Indica si tiene acceso a un recurso en la grid principal.
     * 
     * @param row
     */
    estaAsignado(row) {
        return this.arrRoles.indexOf(row.rol_id) !== -1;
    }

    public hasError = (controlName: string, errorName: string) =>{
        return this.form.controls[controlName].hasError(errorName);
    }

    /**
     * Permite la creación dinámica de las diferentes filas y campos en el apartado PROVEEDORES A LOS QUE EL USUARIO LE PODRÁ GESTIONAR DOCUMENTOS
     * @private
     * @returns Grupo de formulario con campos código y descripción
     */
    // private proveedoresAcceso(): any {
    //     return this.fb.group({
    //         pro_razon_social: ['']
    //     });
    // }  

    /**
     * Crea un componete para agregar un ofes para carga masivas
     */
    agregarOfesParaCargaMasiva() {
        const CTRL = <FormArray>this.form.get('ofesColeccion.ofes');
        CTRL.push(this.ofesIde());
    }

    /**
     * Crea un componete de un usuario de cargas masivas
     */
    eliminarOfesParaCargaMasiva(i: number) {
        const CTRL = <FormArray>this.form.get('ofesColeccion.ofes');
        CTRL.removeAt(i);
        this._ofesIde.splice(i, 1);

        if (CTRL.length === 0)
            this.agregarOfesParaCargaMasiva();
    }

    /**
     * Crea un componete para agregar un ofe para carga masivas
     */
    // agregarProvsParaCargaMasiva() {
    //     const CTRL = <FormArray>this.form.controls['proveedores'];
    //     CTRL.push(this.proveedoresAcceso());
    // }

    /**
     * Crea un componete de un usuario de cargas masivas
     */
    // eliminarProvsParaCargaMasiva(i: number) {
    //     const CTRL = <FormArray>this.form.controls['proveedores'];
    //     CTRL.removeAt(i);

    //     this._proveedoresEliminados.push(this._proveedoresAgregados[i]);
    //     this._proveedoresAgregados.splice(i, 1);

    //     if (CTRL.length === 0)
    //         this.agregarProvsParaCargaMasiva();
    // }

    setInputOfe(ofe, indice){
        let formularioOfes = <FormArray>this.form.controls['ofes_ide'];
        let renglon = formularioOfes.at(indice);
        
        for (let i = 0; i < formularioOfes.length; i++) {
            if (i !== indice) {
                let row = formularioOfes.at(i);
                // Se intenta asignar un usuario ya elegido
                if (row.get('ofe_razon_social').value === ofe.ofe_razon_social) {
                    this.showError('<h3>Este OFE ya ha sido seleccionado</h3>', 'error', 'Error al asignar el Ofe', 'Ok, entiendo', 'btn btn-danger');
                    renglon.get('ofe_razon_social').setValue(this._ofesIde[indice].ofe_razon_social);
                    return;
                }
            }
        }
        renglon.get('ofe_razon_social').setValue(ofe.ofe_razon_social);

        this._ofesIde = [];
        for (let i = 0; i < formularioOfes.length; i++) {
            let row = formularioOfes.at(i);
            this._ofesIde.push({
                ofe_razon_social: row.get('ofe_razon_social').value
            });
        }

        // this.form['controls'].ofes_ide['controls'][indice]['controls'].ofe_razon_social.patchValue(ofe.ofe_razon_social, {emitEvent:false});
    }

    valueChangesOfes(){
        this.form
        .get('pai_descripcion')
        .valueChanges
        .pipe(
            filter(value => value.length >= 1),
            debounceTime(1000),
            distinctUntilChanged(),
            tap(() => {
                this.loading(true);
                this.form.get('pai_descripcion').disable();
            }),
            switchMap(value =>
                this._usuariosService.searchOfes('ofe_razon_social', value, 'basico')
                    .pipe(
                        finalize(() => {
                            this.loading(false);
                            this.form.get('pai_descripcion').enable();
                        })
                    )
            )
        )
        .subscribe(res => {
            this.filteredOfes = res.data;
            if (this.filteredOfes.length <= 0) {
                this.noCoincidences = true;
            } else {
                this.noCoincidences = false;
            }    
        });    
    }

    /**
     * Activa o desactiva los permisos correspondientes para un rol.
     * 
     * @param accion
     * @param rec_id
     */
    permisosRol(value, rol_id) {
        this.loading(true);
        let that = this;
        let accion = value.checked ? 'set' : 'unset';
        if (accion === 'set') {
            this._usuariosService.setRolUsuario(this.usu_id, rol_id).subscribe(
                response => {
                    this.loading(false);
                    if (this.arrRoles.indexOf(rol_id) === -1) {
                        this.arrRoles.push(rol_id);
                    }
                    this.showTimerAlert('<strong>' + response.message + '</strong>', 'success', 'center', 1000);
                },
                error => {
                    this.loading(false);
                    let errores = '';
                    if (error.errors instanceof Array && error.errors.length > 0) {
                        error.errors.forEach(strError => {
                            errores += strError + '<br>';
                        });
                    } else {
                        errores = error.errors;
                    }
                    this.showError('<h3>No se pudo asignar el permiso</h3><br><strong>' + error.message + '</strong><br>' + errores, 'error', 'Error al asignar el rol', 'Cerrar', 'btn btn-danger', null, null);
                }
            );
        } else if (accion === 'unset') {
            this._usuariosService.unsetRolUsuario(this.usu_id, rol_id).subscribe(
                response => {
                    this.loading(false);
                    if (this.arrRoles.indexOf(rol_id) !== -1) {
                        this.arrRoles.splice(this.arrRoles.indexOf(rol_id), 1);
                    }
                    this.showTimerAlert('<strong>' + response.message + '</strong>', 'warning', 'center', 1000);
                },
                error => {
                    this.loading(false);
                    let errores = '';
                    if (error.errors instanceof Array && error.errors.length > 0) {
                        error.errors.forEach(strError => {
                            errores += strError + '<br>';
                        });
                    } else {
                        errores = error.errors;
                    }
                    this.showError('<h3>No se pudo eliminar el rol</h3><br><strong>' + error.message + '</strong><br>' + errores, 'error', 'Error al eliminar el rol', 'Cerrar', 'btn btn-danger', null, null);
                }
            );
        }
    }

}
