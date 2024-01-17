import { Router } from "@angular/router";
import { Component, Inject, AfterViewChecked } from '@angular/core';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { BaseComponentList } from '../../../../core/base_component_list';
import { TrackingColumnInterface, TrackingInterface, TrackingOptionsInterface } from '../../../../commons/open-tracking/tracking-interface';

import { Auth } from '../../../../../services/auth/auth.service';
import { ConfiguracionService } from '../../../../../services/configuracion/configuracion.service';

@Component({
    selector: 'app-modal-grupos-trabajo-asociados',
    templateUrl: './modal-grupos-trabajo-asociados.component.html',
    styleUrls: ['./modal-grupos-trabajo-asociados.component.scss']
})
export class ModalGruposTrabajoAsociadosComponent extends BaseComponentList implements TrackingInterface, AfterViewChecked {

    // Se inicializan las propiedades de la modal
    parent                      : any;
    accion                      : any;
    public loadingIndicator     : any;
    public registros            = [];
    public trackingInterface    : TrackingInterface;
    public ofeIdentificacion    : string;
    public codigoGrupo          : string;
    public tituloModal          : string;
    public lengthTracking       : number = 5;
    public columns: TrackingColumnInterface[] = [];

    public accionesBloque = [];
    public trackingOpciones: TrackingOptionsInterface = {};

    /**
     * Crea una instancia de ModalGruposTrabajoAsociadosComponent.
     * 
     * @param {MatDialogRef<ModalGruposTrabajoAsociadosComponent>} modalRef
     * @param {*} data
     * @param {Auth} _auth
     * @param {Router} _router
     * @param {ConfiguracionService} _configuracionService
     * @memberof ModalGruposTrabajoAsociadosComponent
     */
    constructor(
        private modalRef: MatDialogRef<ModalGruposTrabajoAsociadosComponent>,
        @Inject(MAT_DIALOG_DATA) data,
        public _auth: Auth,
        private _router: Router,
        private _configuracionService: ConfiguracionService
    ) {
        super();
        this.rows = [];
        this.trackingInterface = this;
        this.parent = data.parent;
        this.accion = data.action;
        this.codigoGrupo = data.gtr_codigo;
        this.ofeIdentificacion = data.ofe_identificacion;

        this.columns = [
            {name: 'Identificación', prop: 'identificacion', sorteable: true, width: 120},
            {name: (this.accion == 'ver-usuarios-asociados' ? 'Nombres' : 'Razón Social'), prop: 'nombres', sorteable: true, width: 200},
            {name: 'Email',          prop: 'email',          sorteable: true, width: 150},
            {name: 'Estado',         prop: 'estado',         sorteable: true, width: 150}
        ];

        this.init();
    }

    /**
     * Se encarga de inicializar los parámetros para la búsqueda.
     * 
     * @memberof GruposTrabajoListarComponent
     */
    private init() {
        this.initDataSort('identificacion');
        this.loadingIndicator = true;
        this.ordenDireccion = 'ASC';
        if (this.accion == 'ver-usuarios-asociados') {
            this.tituloModal = 'Lista Usuarios Asociados';
            this.loadUsuariosAsociados();
        } else if (this.accion == 'ver-proveedores-asociados') {
            this.tituloModal = 'Lista Proveedores Asociados';
            this.loadProveedoresAsociados();
        }
    }

    /**
     * Pemite detectar los cambios después de cargado el componente.
     * 
     * @memberof GruposTrabajoListarComponent
     */
    ngAfterViewChecked():void{
        window.dispatchEvent(new Event('resize'));
    }

    /**
     * Sobreescribe los parámetros de búsqueda inline - (Get).
     *
     * @param {boolean} [excel=false] Aplica retorno en excel
     * @return {string}
     * @memberof GruposTrabajoListarComponent
     */
    getSearchParametersInline(excel = false): string {
        let query = 'nitOfe=' + this.ofeIdentificacion + '&' +
        'codigoGrupo=' + this.codigoGrupo + '&' +
        'start=' + this.start + '&' +
        'length=' + this.lengthTracking + '&' +
        'buscar=' + this.buscar + '&' +
        'columnaOrden=' + this.columnaOrden + '&' +
        'ordenDireccion=' + this.ordenDireccion;
        if (excel)
            query += '&excel=true';

        return query;
    }

    /**
     * Se encarga de traer la data de los diferentes usuarios asociados a un grupo de trabajo.
     * 
     * @memberof GruposTrabajoListarComponent
     */
    public loadUsuariosAsociados(): void {
        this.loading(true);
        this._configuracionService.listarUsuariosAsociados(this.getSearchParametersInline()).subscribe(
            res => {
                this.registros.length = 0;
                this.loading(false);
                this.rows = res.data;
                res.data.forEach(reg => {
                    let nit_usuario = '';
                    let nombres     = '';
                    let email       = '';
                    let estado      = '';
                    if (reg.get_usuario) {
                        nit_usuario   = reg.get_usuario.usu_identificacion;
                        nombres       = reg.get_usuario.usu_nombre;
                        email         = reg.get_usuario.usu_email;
                        estado        = reg.get_usuario.estado;
                    }

                    this.registros.push(
                        {
                            'identificacion' : nit_usuario,
                            'nombres'        : nombres,
                            'email'          : email,
                            'estado'         : estado
                        }
                    );
                });

                this.totalElements    = res.total;
                this.loadingIndicator = false;
                this.totalShow        = this.lengthTracking !== -1 ? this.lengthTracking : this.totalElements;
            },
            error => {
                this.loading(false);
                const texto_errores = this.parseError(error);
                this.loadingIndicator = false;
                this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al cargar los Usuarios Asociados', '0k, entiendo', 'btn btn-danger', '/dashboard', this._router);
            });
    }

    /**
     * Se encarga de traer la data de los diferentes proveedores asociados a un grupo de trabajo.
     * 
     * @memberof GruposTrabajoListarComponent
     */
    public loadProveedoresAsociados(): void {
        this.loading(true);
        this._configuracionService.listarProveedoresAsociados(this.getSearchParametersInline()).subscribe(
            res => {
                this.registros.length = 0;
                this.loading(false);
                this.rows = res.data;
                res.data.forEach(reg => {
                    let nit_proveedor = '';
                    let razon_social  = '';
                    let email         = '';
                    let estado        = '';
                    if (reg.get_proveedor) {
                        if (reg.get_proveedor.pro_razon_social != '') {
                            razon_social = reg.get_proveedor.pro_razon_social;
                        } else {
                            razon_social = reg.get_proveedor.pro_primer_nombre + ' ' + reg.get_proveedor.pro_otros_nombres + ' ' +  reg.get_proveedor.pro_primer_apellido + ' ' + reg.get_proveedor.pro_segundo_apellido;
                        }
                        nit_proveedor = reg.get_proveedor.pro_identificacion;
                        email         = reg.get_proveedor.pro_correo;
                        estado        = reg.get_proveedor.estado;
                    }

                    this.registros.push(
                        {
                            'identificacion' : nit_proveedor,
                            'nombres'        : razon_social,
                            'email'          : email,
                            'estado'         : estado
                        }
                    );
                });

                this.totalElements    = res.total;
                this.loadingIndicator = false;
                this.totalShow        = this.lengthTracking !== -1 ? this.lengthTracking : this.totalElements;
            },
            error => {
                this.loading(false);
                const texto_errores = this.parseError(error);
                this.loadingIndicator = false;
                this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al cargar los Proveedores Asociados', '0k, entiendo', 'btn btn-danger', '/dashboard', this._router);
            });
    }

    /**
     * Cierra la ventana modal de Proveedores Asociados.
     * 
     * @memberof ModalGruposTrabajoAsociadosComponent
     */
    public closeModal(reload): void {
        this.modalRef.close();
        if(reload)
            this.parent.getData();
    }

    /**
     * Recarga el listado en base al término de búsqueda.
     *
     * @param {string} buscar Valor a buscar
     * @memberof ModalGruposTrabajoAsociadosComponent
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
     * @memberof ModalGruposTrabajoAsociadosComponent
     */
    onChangeSizePage(size: number) {
        this.lengthTracking = size;
        this.recargarLista();
    }

    /**
     * Realiza el ordenamiento de los registros y recarga el listado.
     *
     * @param {string} column Columna por la cual se organizan los registros
     * @param {string} $order Dirección del orden de los registros [ASC - DESC]
     * @memberof ModalGruposTrabajoAsociadosComponent
     */
    onOrderBy(column: string, $order: string) {
        this.selected       = [];
        this.start = 0;
        this.columnaOrden   = column;
        this.ordenDireccion = $order;
        this.recargarLista();
    }

    /**
     * Recarga la lista del tracking.
     *
     * @memberof ModalGruposTrabajoAsociadosComponent
     */
    recargarLista() {
        this.loadingIndicator = true;
        if (this.accion == 'ver-usuarios-asociados') {
            this.loadUsuariosAsociados();
        } else if (this.accion == 'ver-proveedores-asociados') {
            this.loadProveedoresAsociados();
        }
    }

    /**
     * Gestiona el evento de paginación de la grid.
     * 
     * @param $evt Cantidad de registros
     * @memberof ModalGruposTrabajoAsociadosComponent
     */
    public onPage($evt) {
        this.selected = [];
        this.page     = $evt.offset;
        this.start    = $evt.offset * this.lengthTracking;
        this.recargarLista();
    }

    /**
     * Cambia el numero de items a mostrar y refresca la grid.
     * 
     * @param evt Cantidad de registros
     * @memberof ModalGruposTrabajoAsociadosComponent
     */
    paginar(evt) {
        this.lengthTracking = evt;
        this.recargarLista();
    }

    /**
     * Gestiona la acción seleccionada en el select de Acciones en Bloque.
     * 
     * @memberof ModalGruposTrabajoAsociadosComponent
     */
    onOptionMultipleSelected(opcion: any, selected: any[]) {
        this.selected = selected;
    }

    /**
     * Gestiona la acción del botón de ver un registro.
     *
     * @param {*} item Registro seleccionado
     * @memberof ModalGruposTrabajoAsociadosComponent
     */
    onViewItem(item: any) {}

    /**
     * Gestiona la acción del botón de eliminar un registro.
     *
     * @param {*} item Registro seleccionado
     * @memberof ModalGruposTrabajoAsociadosComponent
     */
    onRequestDeleteItem(item: any) {}

    /**
     * Gestiona la acción del botón de editar un registro.
     *
     * @param {*} item Registro seleccionado
     * @memberof ModalGruposTrabajoAsociadosComponent
     */
    onEditItem(item: any) {}
}
