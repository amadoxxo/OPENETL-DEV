import swal from 'sweetalert2';
import { Component } from '@angular/core';
import { Router } from '@angular/router';
import { JwtHelperService } from '@auth0/angular-jwt';
import { MatDialog, MatDialogConfig } from '@angular/material/dialog';
import { BaseComponentList } from '../../../../core/base_component_list';
import { GruposTrabajoProveedoresGestionarComponent } from '../gestionar/grupos-trabajo-proveedores-gestionar.component';
import { TrackingColumnInterface, TrackingInterface, TrackingOptionsInterface } from '../../../../commons/open-tracking/tracking-interface';

import { Auth } from '../../../../../services/auth/auth.service';
import { ConfiguracionService } from '../../../../../services/configuracion/configuracion.service';
import * as capitalize from 'lodash';

@Component({
    selector: 'listar-grupos-trabajo-proveedores',
    templateUrl: './grupos-trabajo-proveedores-listar.component.html',
    styleUrls: ['./grupos-trabajo-proveedores-listar.component.scss']
})

export class GruposTrabajoProveedoresListarComponent extends BaseComponentList implements TrackingInterface {
    
    public loadingIndicator       : any;
    public aclsUsuario            : any;
    public trackingInterface      : TrackingInterface;
    public usuario                : any;
    public grupo_trabajo_singular : string;
    public grupo_trabajo_plural   : string;
    public registros              : any [] = [];
    private modalGrupoTrabajoProveedor: any;

    public columns: TrackingColumnInterface[] = [];

    public accionesBloque = [
        // {id: 'cambiarEstado', itemName: 'Cambiar Estado'}
    ];

    public trackingOpciones: TrackingOptionsInterface = {
        editButton: false, 
        showButton: false,
        cambiarEstadoButton: true
    };

    /**
     * Crea una instancia de GruposTrabajoProveedoresListarComponent.
     * 
     * @param {Auth} _auth
     * @param {Router} _router
     * @param {MatDialog} _modal
     * @param {JwtHelperService} _jwtHelperService
     * @param {ConfiguracionService} _configuracionService
     * @memberof GruposTrabajoProveedoresListarComponent
     */
    constructor(
        public _auth: Auth,
        private _router: Router,
        private _modal: MatDialog,
        private _jwtHelperService: JwtHelperService,
        private _configuracionService: ConfiguracionService
    ) {
        super();
        this._configuracionService.setSlug = "grupos-trabajo-proveedores";
        this.trackingInterface = this;
        this.rows = [];
        this.init();
    }

    /**
     * Se encarga de inicializar los parámetros para la búsqueda.
     *
     * @private
     * @memberof GruposTrabajoProveedoresListarComponent
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
            {name: 'Identificación',            prop: 'pro_identificacion',     sorteable: true, width: 120},
            {name: 'Razón Social',              prop: 'pro_razon_social',       sorteable: true, width: 200},
            {name: 'Estado',                    prop: 'estado',                 sorteable: true, width: 100}
        ];

        this.loadGruposTrabajoProveedores();
    }

    /**
     * Sobreescribe los parámetros de búsqueda inline - (Get).
     *
     * @param {boolean} [excel=false] Identifica si se debe generar el Excel
     * @return {*}  {string}
     * @memberof GruposTrabajoProveedoresListarComponent
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
     * Se encarga de traer la data de los proveedores asociados a un grupo de trabajo.
     *
     * @memberof GruposTrabajoProveedoresListarComponent
     */
    public loadGruposTrabajoProveedores(): void {
        this.loading(true);
        this._configuracionService.listar(this.getSearchParametersInline()).subscribe(
            res => {
                this.registros.length = 0;
                this.loading(false);
                this.rows = res.data;
                res.data.forEach(reg => {
                    let nit_oferente = reg.get_grupo_trabajo.get_configuracion_obligado_facturar_electronicamente.ofe_identificacion;
                    let razon_social = '';
                    if (reg.get_proveedor.pro_razon_social != '') {
                        razon_social = reg.get_proveedor.pro_razon_social;
                    } else {
                        razon_social = reg.get_proveedor.pro_primer_nombre + ' ' + reg.get_proveedor.pro_otros_nombres + ' ' +  reg.get_proveedor.pro_primer_apellido + ' ' + reg.get_proveedor.pro_segundo_apellido;
                    }

                    this.registros.push(
                        {
                            'gtp_id': reg.gtp_id,
                            'ofe_identificacion': nit_oferente,
                            'gtr_codigo': reg.get_grupo_trabajo.gtr_codigo,
                            'gtr_codigo_descripcion': reg.get_grupo_trabajo.gtr_codigo + ' - ' + reg.get_grupo_trabajo.gtr_nombre,
                            'pro_identificacion': reg.get_proveedor.pro_identificacion,
                            'pro_razon_social': razon_social,
                            'estado': reg.estado
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
                this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al cargar los Proveedores asociados a ' + this.grupo_trabajo_plural, '0k, entiendo', 'btn btn-danger', '/dashboard', this._router);
            });
    }

    /**
     * Gestiona el evento de paginación de la grid.
     *
     * @param {*} $evt Acción del evento
     * @memberof GruposTrabajoProveedoresListarComponent
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
     * @memberof GruposTrabajoProveedoresListarComponent
     */
    onCheckboxChangeFn(evt: any) {}

    /**
     * Efectua la carga de datos.
     *
     * @memberof GruposTrabajoProveedoresListarComponent
     */
    public getData() {
        this.loadingIndicator = true;
        this.loadGruposTrabajoProveedores();
    }

    /**
     * Evento de selectall del checkbox primario de la grid.
     *
     * @param {*} {selected} Registros seleccionados de la grid
     * @memberof GruposTrabajoProveedoresListarComponent
     */
    onSelect({selected}) {
        this.selected.splice(0, this.selected.length);
        this.selected.push(...selected);
    }
 
    /**
     * Recarga la lista del tracking.
     *
     * @memberof GruposTrabajoProveedoresListarComponent
     */
    recargarLista() {
        this.getData();
    }

    /**
     * Apertura una ventana modal para asociar un proveedor a un grupo de trabajo.
     *
     * @param {string} action Acción que se ejecuta
     * @memberof GruposTrabajoProveedoresListarComponent
     */
    public openModalAsociarProveedor(action: string): void {
        const modalConfig = new MatDialogConfig();
        modalConfig.autoFocus = true;
        modalConfig.width = '600px';
        modalConfig.data = {
            action: action,
            parent: this
        };
        modalConfig.disableClose = true;
        this.modalGrupoTrabajoProveedor = this._modal.open(GruposTrabajoProveedoresGestionarComponent, modalConfig);
    }

    /**
     * Se encarga de cerrar y eliminar la referencia del modal para visualizar el detalle de un registro.
     *
     * @memberof GruposTrabajoProveedoresListarComponent
     */
    public closeModalGrupoTrabajoProveedor(): void {
        if (this.modalGrupoTrabajoProveedor) {
            this.modalGrupoTrabajoProveedor.close();
            this.modalGrupoTrabajoProveedor = null;
        }
    }

    /**
     * Gestiona la acción seleccionada en el select de Acciones en Bloque de los registrados seleccionados en la grid.
     *
     * @param {*} accion Acción a ejecutar
     * @memberof GruposTrabajoProveedoresListarComponent
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
                    ObjRegistros['pro_identificacion'] = reg.pro_identificacion;
                    ObjRegistros['gtr_codigo'] = reg.gtr_codigo;

                    arrRegistros.push(ObjRegistros);
                });
                swal({
                    html: '¿Está seguro de cambiar el estado de los Proveedores asociados a ' + this.grupo_trabajo_plural + '?',
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
                                this.loadGruposTrabajoProveedores();
                                this.loading(false);
                                this.showSuccess('<h3>Los Provedores asociados a ' + this.grupo_trabajo_plural + ' han cambiado de estado</h3>', 'success', 'Cambio de estado exitoso', 'OK', 'btn btn-success');
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
     * @memberof GruposTrabajoProveedoresListarComponent
     */
    descargarExcel() {
        this.loading(true);
        this._configuracionService.descargarExcelGet(this.getSearchParametersInline(true)).subscribe(
            response => {
                this.loading(false);
            },
            (error) => {
                this.loading(false);
                this.showError('<h3>Error en descarga</h3><p>Verifique que la consulta tenga resultados.</p>', 'error', 'Error al descargar archivo excel de los Proveedores asociados a ' + this.grupo_trabajo_plural, 'OK', 'btn btn-danger');
            }
        );
    }

    /**
     * Recarga el listado en base al término de búsqueda.
     *
     * @param {string} buscar Valor a buscar
     * @memberof GruposTrabajoProveedoresListarComponent
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
     * @memberof GruposTrabajoProveedoresListarComponent
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
     * @memberof GruposTrabajoProveedoresListarComponent
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
            case 'pro_identificacion':
                this.columnaOrden = 'identificacion';
                break;
            case 'pro_razon_social':
                this.columnaOrden = 'nombres';
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
     * @memberof GruposTrabajoProveedoresListarComponent
     */
    onOptionMultipleSelected(opcion: any, selected: any[]) {
        this.selected = selected;
        this.accionesEnBloque(opcion);
    }

    /**
     * Gestiona la acción del botón de ver un registro.
     *
     * @param {*} item Registro seleccionado
     * @memberof GruposTrabajoProveedoresListarComponent
     */
    onViewItem(item: any) {}

    /**
     * Gestiona la acción del botón de eliminar un registro.
     *
     * @param {*} item Registro seleccionado
     * @memberof GruposTrabajoProveedoresListarComponent
     */
    onRequestDeleteItem(item: any) {}

    /**
     * Gestiona la acción del botón de editar un registro.
     *
     * @param {*} item Registro seleccionado
     * @memberof GruposTrabajoProveedoresListarComponent
     */
    onEditItem(item: any) {}

    /**
     * Gestiona la acción del botón de cambiar estado de un registro.
     *
     * @param {*} item Información del registro seleccionado
     * @memberof GruposTrabajoProveedoresListarComponent
     */
    onCambiarEstadoItem(item){
        this.selected.push(item);
        this.accionesEnBloque('cambiarEstado');
    }

    /**
     * Permite ir a la pantalla para subir los proveedores asociados a un grupo de trabajo.
     *
     * @memberof GruposTrabajoProveedoresListarComponent
     */
    subirAsociarProveedores() {
        this._router.navigate(['configuracion/grupos-trabajo/asociar-proveedores/subir-proveedores-asociados']);
    }
}

