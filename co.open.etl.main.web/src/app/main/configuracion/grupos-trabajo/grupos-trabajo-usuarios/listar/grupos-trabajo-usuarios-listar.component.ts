import swal from 'sweetalert2';
import { Component } from '@angular/core';
import { Router } from '@angular/router';
import { JwtHelperService } from '@auth0/angular-jwt';
import { MatDialog, MatDialogConfig } from '@angular/material/dialog';
import { BaseComponentList } from '../../../../core/base_component_list';
import { TrackingColumnInterface, TrackingInterface, TrackingOptionsInterface } from '../../../../commons/open-tracking/tracking-interface';

import { Auth } from '../../../../../services/auth/auth.service';
import { ConfiguracionService } from '../../../../../services/configuracion/configuracion.service';
import { GruposTrabajoUsuariosGestionarComponent } from '../gestionar/grupos-trabajo-usuarios-gestionar.component';
import * as capitalize from 'lodash';

@Component({
    selector: 'listar-grupos-trabajo-usuarios',
    templateUrl: './grupos-trabajo-usuarios-listar.component.html',
    styleUrls: ['./grupos-trabajo-usuarios-listar.component.scss']
})

export class GruposTrabajoUsuariosListarComponent extends BaseComponentList implements TrackingInterface {
    
    public loadingIndicator       : any;
    public aclsUsuario            : any;
    public trackingInterface      : TrackingInterface;
    public usuario                : any;
    public grupo_trabajo_singular : string;
    public grupo_trabajo_plural   : string;
    public registros              : any [] = [];
    private modalGrupoTrabajoUsuario: any;

    public columns: TrackingColumnInterface[] = [];

    public accionesBloque = [
        // {id: 'cambiarEstado', itemName: 'Cambiar Estado'}
    ];

    public trackingOpciones: TrackingOptionsInterface = {
        editButton: false, 
        showButton: true,
        cambiarEstadoButton: true
    };

    /**
     * Crea una instancia de GruposTrabajoUsuariosListarComponent.
     * 
     * @param {Auth} _auth
     * @param {Router} _router
     * @param {MatDialog} _modal
     * @param {JwtHelperService} _jwtHelperService
     * @param {ConfiguracionService} _configuracionService
     * @memberof GruposTrabajoUsuariosListarComponent
     */
    constructor(
        public _auth: Auth,
        private _router: Router,
        private _modal: MatDialog,
        private _jwtHelperService: JwtHelperService,
        private _configuracionService: ConfiguracionService
    ) {
        super();
        this._configuracionService.setSlug = "grupos-trabajo-usuarios";
        this.trackingInterface = this;
        this.rows = [];
        this.init();
    }

    /**
     * Se encarga de inicializar los parámetros para la búsqueda.
     *
     * @private
     * @memberof GruposTrabajoUsuariosListarComponent
     */
    private init() {
        this.initDataSort('fecha_modificacion');
        this.loadingIndicator = true;
        this.ordenDireccion   = 'DESC';
        this.aclsUsuario      = this._auth.getAcls();
        this.usuario          = this._jwtHelperService.decodeToken();
        this.grupo_trabajo_singular = capitalize.startCase(capitalize.toLower(this.usuario.grupos_trabajo.singular));
        this.grupo_trabajo_plural   = capitalize.startCase(capitalize.toLower(this.usuario.grupos_trabajo.plural));

        this.columns = [
            {name: 'OFE',                       prop: 'ofe_identificacion',     sorteable: true, width: 100},
            {name: this.grupo_trabajo_singular, prop: 'gtr_codigo_descripcion', sorteable: true, width: 200},
            {name: 'Identificación',            prop: 'usu_identificacion',     sorteable: true, width: 120},
            {name: 'Nombre',                    prop: 'usu_nombre',             sorteable: true, width: 150},
            {name: 'Email',                     prop: 'usu_email',              sorteable: true, width: 150},
            {name: 'Estado',                    prop: 'estado',                 sorteable: true, width: 100}
        ];

        this.loadGruposTrabajoUsuarios();
    }

    /**
     * Sobreescribe los parámetros de búsqueda inline - (Get).
     *
     * @param {boolean} [excel=false] Identifica si se debe generar el Excel
     * @return {*}  {string}
     * @memberof GruposTrabajoUsuariosListarComponent
     */
    getSearchParametersInline(excel = false): string {
        let query = 'start=' + this.start + '&' +
        'length=' + this.length + '&' +
        'buscar=' + this.buscar + '&' +
        'columnaOrden=' + this.columnaOrden + '&' +
        'ordenDireccion=' + this.ordenDireccion;
        if (excel)
            query += '&excel=true';
        return query;
    }

    /**
     * Se encarga de traer la data de los usuarios asociados a un grupo de trabajo.
     *
     * @memberof GruposTrabajoUsuariosListarComponent
     */
    public loadGruposTrabajoUsuarios(): void {
        this.loading(true);
        this._configuracionService.listar(this.getSearchParametersInline()).subscribe(
            res => {
                this.registros.length = 0;
                this.loading(false);
                this.rows = res.data;
                res.data.forEach(reg => {
                    let nit_oferente = reg.get_grupo_trabajo.get_configuracion_obligado_facturar_electronicamente.ofe_identificacion;
                    this.registros.push(
                        {
                            'gtp_id': reg.gtp_id,
                            'ofe_identificacion': nit_oferente,
                            'gtr_codigo': reg.get_grupo_trabajo.gtr_codigo,
                            'gtr_codigo_descripcion': reg.get_grupo_trabajo.gtr_codigo + ' - ' + reg.get_grupo_trabajo.gtr_nombre,
                            'usu_identificacion': reg.get_usuario.usu_identificacion,
                            'usu_nombre': reg.get_usuario.usu_nombre,
                            'usu_email': reg.get_usuario.usu_email,
                            'estado': reg.estado,
                            'ofe_recepcion_fnc_activo': reg.get_grupo_trabajo.get_configuracion_obligado_facturar_electronicamente.ofe_recepcion_fnc_activo
                        }
                    );
                });
                this.totalElements = res.filtrados;
                this.loadingIndicator = false;
                this.totalShow = this.length !== -1 ? this.length : this.totalElements;
            },
            error => {
                this.loading(false);
                const texto_errores = this.parseError(error);
                this.loadingIndicator = false;
                this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al cargar los Usuarios asociados a ' + this.grupo_trabajo_plural, '0k, entiendo', 'btn btn-danger', '/dashboard', this._router);
            });
    }

    /**
     * Gestiona el evento de paginación de la grid.
     *
     * @param {*} $evt Acción del evento
     * @memberof GruposTrabajoUsuariosListarComponent
     */
    public onPage($evt) {
        this.page = $evt.offset;
        this.start = $evt.offset * this.length;
        this.selected = [];
        this.getData();
    }

    /**
     * Método utilizado por los checkbox en los listados.
     *
     * @param {*} evt Acción del evento
     * @memberof GruposTrabajoUsuariosListarComponent
     */
    onCheckboxChangeFn(evt: any) {}

    /**
     * Efectua la carga de datos.
     *
     * @memberof GruposTrabajoUsuariosListarComponent
     */
    public getData() {
        this.loadingIndicator = true;
        this.loadGruposTrabajoUsuarios();
    }

    /**
     * Evento de selectall del checkbox primario de la grid.
     *
     * @param {*} {selected} Registros seleccionados de la grid
     * @memberof GruposTrabajoUsuariosListarComponent
     */
    onSelect({selected}) {
        this.selected.splice(0, this.selected.length);
        this.selected.push(...selected);
    }
 
    /**
     * Recarga la lista del tracking.
     *
     * @memberof GruposTrabajoUsuariosListarComponent
     */
    recargarLista() {
        this.getData();
    }

    /**
     * Apertura una ventana modal para asociar un usuario a un grupo de trabajo.
     *
     * @param {string} action Acción que se ejecuta
     * @param {object} item   Data que se selecciona
     * @memberof GruposTrabajoUsuariosListarComponent
     */
    public openModalAsociarUsuario(action: string, item: any = null): void {
        const modalConfig = new MatDialogConfig();
        modalConfig.autoFocus = true;
        modalConfig.width = '600px';
        modalConfig.data = {
            action: action,
            parent: this,
            item  : item
        };
        modalConfig.disableClose = true;
        this.modalGrupoTrabajoUsuario = this._modal.open(GruposTrabajoUsuariosGestionarComponent, modalConfig);
    }

    /**
     * Se encarga de cerrar y eliminar la referencia del modal para visualizar el detalle de un registro.
     *
     * @memberof GruposTrabajoUsuariosListarComponent
     */
    public closeModalGrupoTrabajoUsuario(): void {
        if (this.modalGrupoTrabajoUsuario) {
            this.modalGrupoTrabajoUsuario.close();
            this.modalGrupoTrabajoUsuario = null;
        }
    }

    /**
     * Gestiona la acción seleccionada en el select de Acciones en Bloque de los registrados seleccionados en la grid.
     *
     * @param {*} accion Acción a ejecutar
     * @memberof GruposTrabajoUsuariosListarComponent
     */
    public accionesEnBloque(accion) {
        if (accion === 'cambiarEstado') {
            if (this.selected.length == 0){
                this.showError('<h3>Debe seleccionar al menos un registro</h3>', 'warning', 'Cambio de Estado', 'Ok, entiendo',
                    'btn btn-warning');
            } else {
                let arrRegistros = [];
                this.selected.forEach(reg => {
                    let ObjRegistros = new Object;
                    ObjRegistros['ofe_identificacion'] = reg.ofe_identificacion;
                    ObjRegistros['usu_identificacion'] = reg.usu_identificacion;
                    ObjRegistros['usu_email']          = reg.usu_email;
                    ObjRegistros['gtr_codigo']         = reg.gtr_codigo;
                    arrRegistros.push(ObjRegistros);
                });
                swal({
                    html: '¿Está seguro de cambiar el estado de los Usuarios asociados a ' + this.grupo_trabajo_plural + '?',
                    type: 'warning',
                    showCancelButton: true,
                    confirmButtonClass: 'btn btn-success',
                    confirmButtonText: 'Aceptar',
                    cancelButtonText: 'Cancelar',
                    cancelButtonClass: 'btn btn-danger',
                    buttonsStyling: false,
                    allowOutsideClick: false
                })
                .then((result) => {
                    if (result.value) {
                        this.loading(true);
                        this._configuracionService.cambiarEstado(arrRegistros).subscribe(
                            response => {
                                this.loadGruposTrabajoUsuarios();
                                this.loading(false);
                                this.showSuccess('<h3>Los Usuarios asociados a ' + this.grupo_trabajo_plural + ' han cambiado de estado</h3>', 'success', 'Cambio de estado exitoso', 'OK', 'btn btn-success');
                            },
                            error => {
                                this.loading(false);
                                const texto_errores = this.parseError(error);
                                this.loadingIndicator = false;
                                this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al cambiar de estado', 'OK', 'btn btn-danger');
                            }
                        );
                    }
                }).catch(swal.noop);
            }
        } 
        this.selected = [];
    }

    /**
     * Descarga los registros filtrados en un archivos de excel.
     *
     * @memberof GruposTrabajoUsuariosListarComponent
     */
    descargarExcel() {
        this.loading(true);
        this._configuracionService.descargarExcelGet(this.getSearchParametersInline(true)).subscribe(
            response => {
                this.loading(false);
            },
            (error) => {
                this.loading(false);
                this.showError('<h3>Error en descarga</h3><p>Verifique que la consulta tenga resultados.</p>', 'error', 'Error al descargar archivo excel de los Usuarios asociados a ' + this.grupo_trabajo_plural, 'OK', 'btn btn-danger');
            }
        );
    }

    /**
     * Recarga el listado en base al término de búsqueda.
     *
     * @param {string} buscar Valor a buscar
     * @memberof GruposTrabajoUsuariosListarComponent
     */
    onSearchInline(buscar: string) {
        this.start = 0;
        this.buscar = buscar;
        this.recargarLista();
    }

    /**
     * Cambia la cantidad de registros del paginado y recarga el listado.
     *
     * @param {number} size Cantidad de registros
     * @memberof GruposTrabajoUsuariosListarComponent
     */
    onChangeSizePage(size: number) {
        this.length = size;
        this.recargarLista();
    }

    /**
     * Realiza el ordenamiento de los registros y recarga el listado.
     *
     * @param {string} column Columna por la cual se organizan los registros
     * @param {string} $order Dirección del orden de los registros [ASC - DESC]
     * @memberof GruposTrabajoUsuariosListarComponent
     */
    onOrderBy(column: string, $order: string) {
        this.selected = [];
        switch (column) {
            case 'ofe_identificacion':
                this.columnaOrden = 'nit_oferente';
                break;
            case 'gtr_codigo_descripcion':
                this.columnaOrden = 'grupo_trabajo';
                break;
            case 'usu_identificacion':
                this.columnaOrden = 'identificacion';
                break;
            case 'usu_nombre':
                this.columnaOrden = 'nombres';
                break;
            case 'usu_email':
                this.columnaOrden = 'email';
                break;
            case 'estado':
                this.columnaOrden = 'estado';
                break;
            default:
                break;
        }
        this.start = 0;
        this.ordenDireccion = $order;
        this.recargarLista();
    }

    /**
     * Gestiona la acción seleccionada en el select de Acciones en Bloque.
     *
     * @param {*} opcion Opción seleccionada
     * @param {any[]} selected Registros seleccionados
     * @memberof GruposTrabajoUsuariosListarComponent
     */
    onOptionMultipleSelected(opcion: any, selected: any[]) {
        this.selected = selected;
        this.accionesEnBloque(opcion);
    }

    /**
     * Gestiona la acción del botón de ver un registro.
     *
     * @param {*} item Registro seleccionado
     * @memberof GruposTrabajoUsuariosListarComponent
     */
    onViewItem(item: any) {
        this.openModalAsociarUsuario('view', item);
    }

    /**
     * Gestiona la acción del botón de eliminar un registro.
     *
     * @param {*} item Registro seleccionado
     * @memberof GruposTrabajoUsuariosListarComponent
     */
    onRequestDeleteItem(item: any) {}

    /**
     * Gestiona la acción del botón de editar un registro.
     *
     * @param {*} item Registro seleccionado
     * @memberof GruposTrabajoUsuariosListarComponent
     */
    onEditItem(item: any) {
        if (item.ofe_recepcion_fnc_activo == 'SI')
            this.openModalAsociarUsuario('edit', item);
    }

    /**
     * Gestiona la acción del botón de cambiar estado de un registro.
     *
     * @param {*} item Información del registro seleccionado
     * @memberof GruposTrabajoUsuariosListarComponent
     */
    onCambiarEstadoItem(item){
        this.selected.push(item);
        this.accionesEnBloque('cambiarEstado');
    }

    /**
     * Permite ir a la pantalla para subir los usuarios asociados a un grupo de trabajo.
     *
     * @memberof GruposTrabajoUsuariosListarComponent
     */
    subirAsociarUsuarios() {
        this._router.navigate(['configuracion/grupos-trabajo/asociar-usuarios/subir-usuarios-asociados']);
    }
}

