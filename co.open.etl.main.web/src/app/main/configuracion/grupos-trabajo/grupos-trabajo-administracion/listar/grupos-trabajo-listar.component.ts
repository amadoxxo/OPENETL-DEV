import { Router } from "@angular/router";
import { Component } from '@angular/core';
import { BaseComponentList } from '../../../../core/base_component_list';
import { Auth } from '../../../../../services/auth/auth.service';
import { ConfiguracionService } from '../../../../../services/configuracion/configuracion.service';
import { TrackingColumnInterface, TrackingInterface, TrackingOptionsInterface } from '../../../../commons/open-tracking/tracking-interface';
import { MatDialog, MatDialogConfig } from '@angular/material/dialog';
import { GruposTrabajoGestionarComponent } from '../gestionar/grupos-trabajo-gestionar.component';
import { JwtHelperService } from '@auth0/angular-jwt';
import * as capitalize from 'lodash';
import swal from 'sweetalert2';
import { ModalGruposTrabajoAsociadosComponent } from "../modal-asociados/modal-grupos-trabajo-asociados.component";
import { CommonsService  } from '../../../../../services/commons/commons.service';

@Component({
    selector: 'app-grupos-trabajo-listar',
    templateUrl: './grupos-trabajo-listar.component.html',
    styleUrls: ['./grupos-trabajo-listar.component.scss']
})
export class GruposTrabajoListarComponent extends BaseComponentList implements TrackingInterface {

    public loadingIndicator      : any;
    private modalGrupoTrabajo    : any;
    private modalTracking        : any;
    public aclsUsuario           : any;
    public registros             = [];
    public titleGrupoTrabajo     : string = '';
    public grupo_trabajo_singular: string = '';
    public trackingInterface     : TrackingInterface;

    public columns: TrackingColumnInterface[] = [
        {name: 'OFE',    prop: 'ofe_identificacion', sorteable: true, width: 120},
        {name: 'Código', prop: 'gtr_codigo',         sorteable: true, width: 100},
        {name: 'Nombre', prop: 'gtr_nombre',         sorteable: true, width: 200},
        {name: 'Estado', prop: 'estado',             sorteable: true, width: 120}
    ];

    public accionesBloque = [];

    public trackingOpciones: TrackingOptionsInterface = {
        editButton: true, 
        showButton: true,
        verUsuarioAsociadoButton: true,
        verProveedorAsociadoButton: true
    };

    /**
     * Crea una instancia de GruposTrabajoListarComponent.
     * 
     * @param {Auth} _auth
     * @param {Router} _router
     * @param {MatDialog} _modal
     * @param {JwtHelperService} _jwtHelperService
     * @param {ConfiguracionService} _configuracionService
     * @param {CommonsService} _commonsService
     * @memberof GruposTrabajoListarComponent
     */
    constructor(
        public _auth: Auth,
        private _router:Router,
        private _modal: MatDialog,
        private _jwtHelperService: JwtHelperService,
        private _configuracionService: ConfiguracionService,
        private _commonsService: CommonsService
    ) {
        super();
        this._configuracionService.setSlug = "grupos-trabajo";
        this.trackingInterface = this;
        this.rows = [];
        let usuario = this._jwtHelperService.decodeToken();
        this.titleGrupoTrabajo = capitalize.startCase(capitalize.toLower(usuario.grupos_trabajo.plural));
        this.init();
    }

    /**
     * Permite aperturar la modal para crear un grupo de trabajo
     *
     * @memberof GruposTrabajoListarComponent
     */
    nuevoGrupoTrabajo() {
        this.openModalGrupoTrabajo('new');
    }

    /**
     * Permite ir a la pantalla para subir spts.
     *
     * @memberof GruposTrabajoListarComponent
     */
    subirGrupoTrabajo() {
        this._router.navigate(['configuracion/grupos-trabajo/administracion/subir-grupos-trabajo']);
    }

    /**
     * Se encarga de inicializar los parámetros para la búsqueda.
     * 
     * @memberof GruposTrabajoListarComponent
     */
    private init() {
        this.initDataSort('fecha_modificacion');
        this.loadingIndicator       = true;
        this.ordenDireccion         = 'DESC';
        this.aclsUsuario            = this._auth.getAcls();
        let usuario                 = this._jwtHelperService.decodeToken();
        this.titleGrupoTrabajo      = capitalize.startCase(capitalize.toLower(usuario.grupos_trabajo.plural));
        this.grupo_trabajo_singular = capitalize.startCase(capitalize.toLower(usuario.grupos_trabajo.singular));

        this.columns = [
            {name: 'OFE',    prop: 'ofe_identificacion', sorteable: true, width: 120},
            {name: 'Código', prop: 'gtr_codigo',         sorteable: true, width: 100},
            {name: 'Nombre', prop: 'gtr_nombre',         sorteable: true, width: 200},
            {name: this.grupo_trabajo_singular + ' por Defecto', prop: 'gtr_por_defecto', sorteable: true, width: 150},
            {name: 'Estado', prop: 'estado',             sorteable: true, width: 120}
        ];

        this.loadGruposTrabajo();
    }

    /**
     * Sobreescribe los parámetros de búsqueda inline - (Get).
     *
     * @param {boolean} [excel=false] Aplica retorno en excel
     * @return {string}
     * @memberof GruposTrabajoListarComponent
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
     * Se encarga de traer la data de los diferentes registros.
     * 
     * @memberof GruposTrabajoListarComponent
     */
    public loadGruposTrabajo(): void {
        this.loading(true);
        this._configuracionService.listar(this.getSearchParametersInline()).subscribe(
            res => {
                this.registros.length = 0;
                this.loading(false);
                this.rows = res.data;
                res.data.forEach(reg => {
                    this.registros.push(
                        {
                            'ofe_identificacion': reg.get_configuracion_obligado_facturar_electronicamente.ofe_identificacion,
                            'gtr_codigo'        : reg.gtr_codigo,
                            'gtr_nombre'        : reg.gtr_nombre,
                            'gtr_por_defecto'   : reg.gtr_por_defecto,
                            'estado'            : reg.estado
                        }
                    );
                });
                this.totalElements    = res.filtrados;
                this.loadingIndicator = false;
                this.totalShow        = this.length !== -1 ? this.length : this.totalElements;
            },
            error => {
                this.loading(false);
                const texto_errores = this.parseError(error);
                this.loadingIndicator = false;
                this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al cargar los Grupos de Trabajo', '0k, entiendo', 'btn btn-danger', '/dashboard', this._router);
            });
    }

    /**
     * Gestiona el evento de paginación de la grid.
     * 
     * @param $evt Cantidad de registros
     * @memberof GruposTrabajoListarComponent
     */
    public onPage($evt) {
        this.selected = [];
        this.page     = $evt.offset;
        this.start    = $evt.offset * this.length;
        this.getData();
    }

    /**
     * Realiza el ordenamiento de los registros y recarga el listado.
     *
     * @param {string} column Columna por la cual se organizan los registros
     * @param {string} $order Dirección del orden de los registros [ASC - DESC]
     * @memberof GruposTrabajoListarComponent
     */
    onOrderBy(column: string, $order: string) {
        this.selected = [];
        switch (column) {
            case 'ofe_identificacion':
                this.columnaOrden = 'oferente';
                break;
            case 'gtr_codigo':
                this.columnaOrden = 'codigo';
                break;
            case 'gtr_nombre':
                this.columnaOrden = 'nombre';
                break;
            case 'estado':
                this.columnaOrden = 'estado';
                break;
            default:
                break;
        }
        this.start = 0;
        this.ordenDireccion = $order;
        this.getData();
    }
    /**
     * Efectua la carga de datos.
     * 
     * @memberof GruposTrabajoListarComponent
     */
    public getData() {
        this.loadingIndicator = true;
        this.loadGruposTrabajo();
    }

    /**
     * Cambia el numero de items a mostrar y refresca la grid.
     * 
     * @param evt Cantidad de registros
     * @memberof GruposTrabajoListarComponent
     */
    paginar(evt) {
        this.length = evt;
        this.getData();
    }

    /**
     * Evento de select all del checkbox primario de la grid.
     * 
     * @param selected Resgistro seleccionado
     * @memberof GruposTrabajoListarComponent
     */
    onSelect({selected}) {
        this.selected.splice(0, this.selected.length);
        this.selected.push(...selected);
    }

    /**
     * Recarga el listado en base al término de búsqueda.
     * 
     * @memberof GruposTrabajoListarComponent
     */
    onSearchInline(buscar: string) {
        this.start = 0;
        this.buscar = buscar;
        this.getData();
    }

    /**
     * Cambia la cantidad de registros del paginado y recarga el listado.
     * 
     * @memberof GruposTrabajoListarComponent
     */
    onChangeSizePage(size: number) {
        this.length = size;
        this.getData();
    }

    /**
     * Apertura una ventana modal para crear o editar un grupo de trabajo.
     *
     * @param {string} action Acción para abrir la ventana modal
     * @param {string} [gtr_codigo=null] Código del grupo de trabajo
     * @param {string} [ofe_identificacion=null] Identificación de Ofe
     * @memberof GruposTrabajoListarComponent
     */
    public openModalGrupoTrabajo(action: string, gtr_codigo: string = null, ofe_identificacion: string = null): void {
        this.loading(true);
        let ofes = [];
        let registro;
        this._commonsService.getDataInitForBuild('tat=false').subscribe(
            result => {
                ofes = result.data.ofes;
                if(action === 'new') {
                    this.loading(false);
                    const modalConfig = new MatDialogConfig();
                    modalConfig.autoFocus = true;
                    modalConfig.width = '600px';
                    modalConfig.data = {
                        action            : action,
                        parent            : this,
                        gtr_codigo        : gtr_codigo,
                        ofe_identificacion: ofe_identificacion,
                        ofes              : ofes
                    };
                    this.modalGrupoTrabajo = this._modal.open(GruposTrabajoGestionarComponent, modalConfig);
                } else {
                    this._configuracionService.getGrp(gtr_codigo, ofe_identificacion).subscribe(
                        res => {
                            if (res) {
                                registro = res.data;
                                this.loading(false);
                                const modalConfig = new MatDialogConfig();
                                modalConfig.autoFocus = true;
                                modalConfig.width = '600px';
                                modalConfig.data = {
                                    action            : action,
                                    parent            : this,
                                    gtr_codigo        : gtr_codigo,
                                    ofe_identificacion: ofe_identificacion,
                                    ofes              : ofes,
                                    item              : registro
                                };
                                this.modalGrupoTrabajo = this._modal.open(GruposTrabajoGestionarComponent, modalConfig);
                            }
                        },
                        error => {
                            this.loading(false);
                            this.showError('<h4>' + error.message + '</h4>', 'error', 'Error al cargar el registro', 'Ok', 'btn btn-danger');
                        }
                    );
                }
            }, error => {
                this.loading(false);
                this.showError('<h4>' + error.message + '</h4>', 'error', 'Error al cargar los ofes', 'Ok', 'btn btn-danger');
            }
        );
    }


    /**
     * Se encarga de cerrar y eliminar la referencia del modal para visualizar el detalle de una variable del sistema.
     * 
     * @memberof GruposTrabajoListarComponent
     */
    public closeModalGrupoTrabajo(): void {
        if (this.modalGrupoTrabajo) {
            this.modalGrupoTrabajo.close();
            this.modalGrupoTrabajo = null;
        }
    }

    /**
     * Gestiona la acción seleccionada en el select de Acciones en Bloque de los registrados seleccionados en la grid.
     *
     * @param {*} accion Opción de la acción en bloque
     * @memberof GruposTrabajoListarComponent
     */
    public accionesEnBloque(accion) {
        if (accion === 'cambiarEstado') {
            if (this.selected.length == 0){
                this.showError('<h3>Debe seleccionar al menos un registro para cambiar su estado</h3>', 'warning', 'Cambio de Estado', 'Ok, entiendo',
                    'btn btn-warning');
            } else {
                let identificadores = [];
                this.selected.forEach(reg => {
                    let obj = {
                        'gtr_codigo' : reg.gtr_codigo,
                        'ofe_identificacion' : reg.ofe_identificacion
                    }
                    identificadores.push(obj);
                });
                swal({
                    html: '¿Está seguro de cambiar el estado de registros seleccionados?',
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
                        this._configuracionService.cambiarEstado(identificadores).subscribe(
                            response => {
                                this.loadGruposTrabajo();
                                this.loading(false);
                                this.showSuccess('<h3>Los registros seleccionados han cambiado de estado correctamente</h3>', 'success', 'Cambio de estado exitoso', 'OK', 'btn btn-success');
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
     * @memberof GruposTrabajoListarComponent
     */
    descargarExcel() {
        this.loading(true);
        this._configuracionService.descargarExcelGet(this.getSearchParametersInline(true)).subscribe(
            response => {
                this.loading(false);
            },
            (error) => {
                this.loading(false);
                this.showError('<h3>Error en descarga</h3><p>Verifique que la consulta tenga resultados.</p>', 'error', 'Error al descargar archivo excel de registros de Proveedores Tecnológicos', 'OK', 'btn btn-danger');
            }
        );
    }

    /**
     * Gestiona la acción seleccionada en el select de Acciones en Bloque.
     * 
     * @memberof GruposTrabajoListarComponent
     */
    onOptionMultipleSelected(opcion: any, selected: any[]) {
        this.selected = selected;
        this.accionesEnBloque(opcion);
    }

    /**
     * Gestiona la acción del botón de ver un registro
     * 
     * @memberof GruposTrabajoListarComponent
     */
    onViewItem(item: any) {
        this.openModalGrupoTrabajo('view', item.gtr_codigo, item.ofe_identificacion);
    }

    /**
     * Apertura una ventana modal para crear o editar un grupo de trabajo.
     *
     * @param {string} action Acción de para aperturar la ventana modal
     * @param {string} gtr_codigo Código del grupo de trabajo
     * @param {string} ofe_identificacion Identificación del OFE
     * @memberof GruposTrabajoListarComponent
     */
    public openModalAsociados(action: string, gtr_codigo: string, ofe_identificacion: string): void {
        const modalConfig = new MatDialogConfig();
        modalConfig.autoFocus = true;
        modalConfig.width = '1000px';
        modalConfig.data = {
            action     : action,
            parent     : this,
            gtr_codigo: gtr_codigo,
            ofe_identificacion: ofe_identificacion
        };
        this.modalTracking = this._modal.open(ModalGruposTrabajoAsociadosComponent, modalConfig);
    }

    /**
     * Apertura la modal para listar los usuarios asociados.
     *
     * @param {*} item Registro seleccionado
     * @memberof GruposTrabajoListarComponent
     */
    onViewUsuariosAsociados(item: any){
        this.openModalAsociados('ver-usuarios-asociados', item.gtr_codigo, item.ofe_identificacion);
    }

    /**
     * Apertura la modal para listar los proveedores asociados.
     *
     * @param {*} item Registro seleccionado
     * @memberof GruposTrabajoListarComponent
     */
    onViewProveedoresAsociados(item: any){
        this.openModalAsociados('ver-proveedores-asociados', item.gtr_codigo, item.ofe_identificacion);
    }

    /**
     * Gestiona la acción del botón de editar un registro
     * 
     * @param {*} item Registro seleccionado
     * @memberof GruposTrabajoListarComponent
     */
    onEditItem(item: any) {
        this.openModalGrupoTrabajo('edit', item.gtr_codigo, item.ofe_identificacion);
    }

    /**
     * Gestiona la acción del botón de eliminar un registro
     * 
     */
    onRequestDeleteItem(item: any) {}
}
