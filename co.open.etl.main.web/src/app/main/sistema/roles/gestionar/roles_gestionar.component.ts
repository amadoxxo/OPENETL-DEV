import {Component, ElementRef, OnInit, ViewChild} from '@angular/core';
import {BaseComponentList} from '../../../core/base_component_list';
import {ActivatedRoute, Router} from '@angular/router';
import {AbstractControl, FormBuilder, FormGroup, Validators} from '@angular/forms';
import {RolesService} from '../../../../services/sistema/roles.service';
import { JwtHelperService } from '@auth0/angular-jwt';

@Component({
    selector: 'gestionar-roles',
    templateUrl: './roles_gestionar.component.html',
    styleUrls: ['./roles_gestionar.component.scss']
})
export class RolesGestionarComponent extends BaseComponentList implements OnInit
{
    // Usuario en línea
    public usuario: any;
    public objMagic = {};
    public permisoActivado: boolean;
    public showButton = true;
    public rol_id: number;
    public textoSeccion: string;
    public arrPermisos = new Array();
    public formNuevoRol: FormGroup;
    public rol_codigo: AbstractControl;
    public rol_descripcion: AbstractControl;
    // public estado: AbstractControl;
    public estado = "";
    public tabla: any;
    public tablaInicializada = false;
    public asignados = false;
    public textoAsignados = 'Filtrar permisos asignados';
    public marcarTodos = false;
    public textoActDesTodos = 'Marcar permisos en pantalla';
    public formErrors: any;
    public selectedValue: any;
    public estadoActual: any;
    public loadingIndicator: any;
    public ver: boolean = false;
    public editar: boolean = false;

    @ViewChild('r') ref: ElementRef;

    /**
     * Constructor
     * @param fb
     * @param _rolesService
     * @param route
     * @param _openBSD
     * @param router
     */
    constructor(
        private fb: FormBuilder,
        public _rolesService: RolesService,
        public route: ActivatedRoute,
        private router: Router,
        private jwtHelperService: JwtHelperService
    ) {
        super();
        this.rows = [];
        this.buildErrorsObject();
        this.init();
        this.usuario = this.jwtHelperService.decodeToken();
    }

    /**
     * Inicializa los controles del Formulario de Nuevo Rol.
     * 
     *
     */
    private init(): void {

        this.formNuevoRol = this.fb.group({
            'rol_codigo': ['', Validators.compose(
                [
                    Validators.required,
                    Validators.maxLength(20),
                ],
            )],
            'rol_descripcion': ['', Validators.compose(
                [
                    Validators.required,
                    Validators.maxLength(255)
                ],
            )]
        });

        this.rol_codigo = this.formNuevoRol.controls['rol_codigo'];
        this.rol_descripcion = this.formNuevoRol.controls['rol_descripcion'];

        // Si llega un rol_id en la URL se debe cargar la información del rol y sus permisos
        if (this.route.snapshot.params['rol_id'] !== undefined) {
            this.rol_id = this.route.snapshot.params['rol_id'];
            this.textoSeccion = 'Editar Rol';
            this.editar = true;
            // Campo dinámico para el estado del registro
            // let controlEstado: FormControl = new FormControl('', Validators.required);
            // this.formNuevoRol.addControl('estado', controlEstado);
            // this.estado = this.formNuevoRol.controls['estado'];

            // Consulta el rol y los permisos asignados
            this.loading(true);
            this._rolesService.getRol(this.rol_id).subscribe(
                response => {
                    this.loading(false);
                    this.estado = response.data.estado;
                    this.formNuevoRol.controls['rol_codigo'].setValue(response.data.rol_codigo);
                    this.formNuevoRol.controls['rol_descripcion'].setValue(response.data.rol_descripcion);
                    this.objMagic['fecha_creacion'] = response.data.fecha_creacion;
                    this.objMagic['fecha_modificacion'] = response.data.fecha_modificacion;
                    this.objMagic['estado'] = response.data.estado;
                    // if (response.rol.estado === 'ACTIVO') {
                    //     this.formNuevoRol.controls['estado'].setValue('ACTIVO');
                    // } else {
                    //     this.formNuevoRol.controls['estado'].setValue('INACTIVO');
                    // }
                    if (response.data.get_rol_permisos.length > 0) {
                        for (let permiso of response.data.get_rol_permisos) {
                            this.arrPermisos.push(permiso.rec_id);
                        }
                    }
                    this.rows = [];
                    this.initDataSort('alias');
                    this.ordenDireccion = 'ASC';
                    this.loadingIndicator = true;
                    this.loadPermisos();

                    if (this.route.snapshot.params['rol_codigo'] !== undefined) {
                        this.textoSeccion = 'Ver Rol';
                        this.ver = true;
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
                    this.showError("<h3>No se pudo obtener el rol</h3><br><strong>" + error.message + "</strong><br>" + errores, 'error', 'Error al obtener el rol', 'Cerrar', 'btn btn-danger');
                }
            );
        } else {
            this.textoSeccion = 'Crear Nuevo Rol';
            this.rol_id = 0;
        }
    }

    /**
     * Si no llega un rol_id en la URL se carga la tabla de permisos.
     * 
     */
    ngOnInit() {
        if (this.route.snapshot.params['rol_id'] === undefined) {
            this.rows = [];
            this.initDataSort('alias');
            this.ordenDireccion = 'ASC';
            this.loadingIndicator = true;
            this.loadPermisos();
        }
    }

    /**
     * Construye un objeto para gestionar los errores en el formulario.
     * 
     */
    public buildErrorsObject() {
        this.formErrors = {
            rol_descripcion: {
                required: 'La descripción del Rol es requerida!',
                maxLength: 'Ha introducido más de 255 caracteres'
            },
            rol_codigo: {
                required: 'El Código es requerido!',
                maxLength: 'Ha introducido más de 20 caracteres'
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
        if (this.arrPermisos) {
            for (let t of this.arrPermisos) {
                if (first) {
                    ar = 'arrPermisos[]=' + t;
                } else {
                    ar = ar + '&arrPermisos[]=' + t;
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
     * Se encarga de traer los permisos para el rol.
     * 
     */
    public loadPermisos(): void {
        this.loading(true);
        this._rolesService.listarPermisos(this.getSearchParametersInline()).subscribe(
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
                this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al cargar los permisos', 'OK', 'btn btn-warning');
            });
    }

    /**
     * Crea un nuevo rol con sus permisos o edita uno existente con los datos provenientes del Formulario de Nuevo Rol.
     * 
     * @param values
     */
    resourceRol(values): void {
        this.loading(true);
        if (this.formNuevoRol.valid) {
            let that = this;
            if (this.route.snapshot.params['rol_id'] === undefined) { // Crea un nuevo rol
                this._rolesService.createRol(values).subscribe(
                    response => {
                        this.loading(false);
                        this.rol_id = response.rol_id;
                        this.showSuccess('', 'success', 'Rol creado exitosamente', 'Ok', 'btn btn-success', '/sistema/roles/editar-rol/' + response.rol_id, this.router);
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
                        this.showError('<h3>No se pudo crear el rol</h3><br><strong>' + error.message + '</strong><br>' + errores, 'error', 'Error al crear el rol', 'Cerrar', 'btn btn-danger');
                    }
                );
            } else if (this.route.snapshot.params['rol_id'] !== undefined) { // Edita un rol existente
                values.estado = this.estado;
                this._rolesService.updateRol(values, this.rol_id).subscribe(
                    response => {
                        this.loading(false);
                        this.rol_id = response.rol_id;
                        this.showSuccess('', 'success', 'Rol actualizado exitosamente', 'Ok', 'btn btn-success', '/sistema/roles/editar-rol/' + response.rol_id, this.router);
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
                        this.showError('<h3>No se pudo modificar el rol</h3><br><strong>' + error.message + '</strong><br>' + errores, 'error', 'Error al modificar el rol', 'Cerrar', 'btn btn-danger');
                    }
                );
            }
        }
    }

    /**
     * Activa o desactiva los permisos correspondientes para un rol.
     * 
     * @param accion
     * @param rec_id
     */
    permisosRol(value, rec_id) {
        this.loading(true);
        let that = this;
        let accion = value.checked ? 'set' : 'unset';
        if (accion === 'set') {
            this._rolesService.setPermisoRol(this.rol_id, rec_id).subscribe(
                response => {
                    this.loading(false);
                    if (this.arrPermisos.indexOf(rec_id) === -1) {
                        this.arrPermisos.push(rec_id);
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
                    this.showError('<h3>No se pudo asignar el permiso</h3><br><strong>' + error.message + '</strong><br>' + errores, 'error', 'Error al asignar el permiso', 'Cerrar', 'btn btn-danger', null, null);
                }
            );
        } else if (accion === 'unset') {
            this._rolesService.unsetPermisoRol(this.rol_id, rec_id).subscribe(
                response => {
                    this.loading(false);
                    if (this.arrPermisos.indexOf(rec_id) !== -1) {
                        this.arrPermisos.splice(this.arrPermisos.indexOf(rec_id), 1);
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
                    this.showError('<h3>No se pudo eliminar el permiso</h3><br><strong>' + error.message + '</strong><br>' + errores, 'error', 'Error al eliminar el permiso', 'Cerrar', 'btn btn-danger', null, null);
                }
            );
        }
    }

    /**
     * Activa o desactiva en lote todos los permisos existentes para un rol.
     *
     */
    actDesTodos() {
        if (this.rol_id === 0) {
            this.noRole();
        } else {
            this.marcarTodos = !this.marcarTodos;
            let arrRecursosId = new Array;
            let accion = '';
            if (this.rows) {
                for (let r of this.rows) {
                    arrRecursosId.push(r.rec_id);
                }
            }

            if (!this.marcarTodos) {
                this.textoActDesTodos = 'Marcar permisos en pantalla';
                // $('.btn-act-des').prop('checked', false);
                accion = 'inactivar';

                // Los recursos se eliminarán, por lo que deben eliminarse del arrPermisos
                arrRecursosId.forEach(oldPermiso => {
                    if (this.arrPermisos.indexOf(oldPermiso) !== -1) {
                        this.arrPermisos.splice(this.arrPermisos.indexOf(oldPermiso), 1);
                    }
                });
            } else {
                this.textoActDesTodos = 'Desmarcar permisos en pantalla';
                // $('.btn-act-des').prop('checked', true);
                accion = 'activar';

                // Los recursos se agregarán, por lo que se deben agregar también a arrPermisos
                arrRecursosId.forEach(newPermiso => {
                    if (this.arrPermisos.indexOf(newPermiso) === -1) {
                        this.arrPermisos.push(newPermiso);
                    }
                });
            }

            this.loading(true);
            this._rolesService.actDesBloquePermisos(accion, this.rol_id, arrRecursosId).subscribe(
                response => {
                    this.loading(false);
                    this.showSuccess('<strong>' + response.message + '</strong>', 'success', 'Permisos asignados correctamente', 'Ok', 'btn btn-success', null, null);
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
                    this.showError('<h3>No se pudo asignar los permisos</h3><br><strong>' + error.message + '</strong><br>' + errores, 'error', 'Error al asignar el permiso', 'Cerrar', 'btn btn-danger', null, null);
                }
            );
        }
    }

    /**
     * Dispara un cuadro de alerta indicando que no se encontro ningún rol al cual asignar permisos.
     *
     */
    noRole() {
        this.showTimerAlert('<strong>Ningún rol en memoria</strong><br>Debe crear un nuevo rol o editar uno existente para poder activar/desactivar permisos', 'error', 'center', 3000);
    }

    /**
     * Filtra y muestra solo los permisos activados para ese rol, ocultando los restantes.
     *
     */
    filtrarAsignados() {
        if (this.rol_id === 0) {
            this.noRole();
        } else {
            this.asignados = !this.asignados;
            if (!this.asignados) {
                this.textoAsignados = 'Filtrar permisos asignados';
            } else {
                this.textoAsignados = 'Mostrar todos';
            }
            this.getData();
        }
    }

    /**
     * Metodo utilizado por los checkbox en los listados.
     * 
     * @param evt
     */
    onCheckboxChangeFn(evt: any) {

    }

    /**
     * Gestiona el evento de paginacion de la grid.
     * 
     * @param $evt
     */
    public onPage($evt) {
        this.start = $evt.offset * this.length;
        this.getData();
    }

    /**
     * Sobreescritura del metodo onSort.
     * 
     * @param $evt
     */
    public onSort($evt) {

        let column = $evt.column.prop;
        this.selected = [];
        switch (column) {
            case 'rec_alias':
                this.columnaOrden = 'alias';
                break;
            case 'rec_modulo_descripcion':
                this.columnaOrden = 'modulo';
                break;
            case 'rec_descripcion':
                this.columnaOrden = 'descripcion';
                break;
            case 'estado':
                this.columnaOrden = 'estado';
                break;
            default:
                break;
        }
        this.start = 0;
        this.ordenDireccion = $evt.newValue.toUpperCase();
        this.getData();
    }

    /**
     * Efectua la carga de datos.
     * 
     */
    public getData() {
        this.loadingIndicator = true;
        this.loadPermisos();
    }

    /**
     * Cambia el numero de items a mostrar y refresca la grid.
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
        this.start = 0;
        this.getData();
    }

    /**
     * Indica si tiene acceso a un recurso en la grid principal.
     * 
     * @param row
     */
    estaAsignado(row) {
        return this.arrPermisos.indexOf(row.rec_id) !== -1;
    }

    public hasError = (controlName: string, errorName: string) =>{
        return this.formNuevoRol.controls[controlName].hasError(errorName);
      }

}
