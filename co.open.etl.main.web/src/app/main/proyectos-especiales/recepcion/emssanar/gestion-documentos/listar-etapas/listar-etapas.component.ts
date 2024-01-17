import swal from 'sweetalert2';
import { Auth } from '../../../../../../services/auth/auth.service';
import * as moment from 'moment';
import { BaseComponentList } from '../../../../../core/base_component_list';
import { ActivatedRoute, Router } from '@angular/router';
import { GestionDocumentosService } from '../../../../../../services/proyectos-especiales/recepcion/emssanar/gestion-documentos.service';
import { ModalAsignacionComponent } from './../modals/modal-asignacion/modal-asignacion.component';
import { ModalGestionFeDsComponent } from '../modals/modal-gestion-fe-ds/modal-gestion-fe-ds.component';
import { MatDialog, MatDialogConfig } from '@angular/material/dialog';
import { ModalDatosContabilizadoComponent } from './../modals/modal-datos-contabilizado/modal-datos-contabilizado.component';
import { FiltrosGestionDocumentosComponent } from '../../../../../commons/filtros-gestion-documentos/filtros-gestion-documentos.component';
import { GestionDocumentosTrackingComponent } from '../../../../../commons/gestion-documentos-tracking/gestion-documentos-tracking.component';
import { Component, OnInit, ViewChild, ViewEncapsulation } from '@angular/core';
import { GestionDocumentosTrackingColumnInterface, GestionDocumentosTrackingInterface } from '../../../../../commons/gestion-documentos-tracking/gestion-documentos-tracking-interface';

interface ParametrosListar {
    ofe_id              : number;
    gdo_fecha_desde     : string;
    gdo_fecha_hasta     : string;
    buscar             ?: string;
    length             ?: number;
    columnaOrden       ?: string;
    ordenDireccion     ?: string;
    etapa              ?: number;
    gdo_identificacion ?: string[];
    gdo_clasificacion  ?: string;
    rfa_prefijo        ?: string;
    gdo_consecutivo    ?: string;
    estado_gestion     ?: string[];
    centro_operacion   ?: string[];
    centro_costo       ?: string[];
    excel              ?: boolean;
}

@Component({
    selector: 'app-listar-etapas',
    templateUrl: './listar-etapas.component.html',
    styleUrls: ['./listar-etapas.component.scss'],
    encapsulation: ViewEncapsulation.None
})
export class ListarEtapasComponent extends BaseComponentList implements GestionDocumentosTrackingInterface, OnInit {
    @ViewChild('gestionDocumentosTracking', {static: true}) gestionDocumentosTracking: GestionDocumentosTrackingComponent;
    @ViewChild('filtros', {static: true}) filtros: FiltrosGestionDocumentosComponent;

    public trackingInterface: GestionDocumentosTrackingInterface;

    // Variables globales
    public usuario               : any;
    public aclsUsuario           : any;
    public loadingIndicator      : boolean = true;
    public existeConsulta        : boolean = false;
    public registros             : any[] = [];
    public accionesBloque        : object[] = [];
    public paginador             : string;
    public linkSiguiente         : string;
    public linkAnterior          : string;
    public txtBreadCrum          : string = '';
    public etapa                 : number;
    public permisoGestionarEtapa : string = '';
    public permisoSiguienteEtapa : string = '';

    // Variables del formulario
    public arrAccionesBloqueEtapas : any = {
        1: [
            { id : 'etapa1GestionarFeDs',     nombre: 'Gestionar FE/DS',    recurso: 'RecepcionGestionDocumentosEtapa1GestionarFeDs' },
            { id : 'etapa1CentroOperaciones', nombre: 'Centro Operaciones', recurso: 'RecepcionGestionDocumentosEtapa1CentroOperaciones' },
            { id : 'etapa1SiguienteEtapa',    nombre: 'Siguiente Etapa',    recurso: 'RecepcionGestionDocumentosEtapa1SiguienteEtapa' }
        ],
        2: [
            { id : 'etapa2GestionarFeDs',     nombre: 'Gestionar FE/DS',    recurso: 'RecepcionGestionDocumentosEtapa2GestionarFeDs' },
            { id : 'etapa2CentroCosto',       nombre: 'Centro Costo',       recurso: 'RecepcionGestionDocumentosEtapa2CentroCosto' },
            { id : 'etapa2SiguienteEtapa',    nombre: 'Siguiente Etapa',    recurso: 'RecepcionGestionDocumentosEtapa2SiguienteEtapa' }
        ],
        3: [
            { id : 'etapa3GestionarFeDs',     nombre: 'Gestionar FE/DS',    recurso: 'RecepcionGestionDocumentosEtapa3GestionarFeDs' },
            { id : 'etapa3SiguienteEtapa',    nombre: 'Siguiente Etapa',    recurso: 'RecepcionGestionDocumentosEtapa3SiguienteEtapa' }
        ],
        4: [
            { id : 'etapa4GestionarFeDs',     nombre: 'Gestionar FE/DS',    recurso: 'RecepcionGestionDocumentosEtapa4GestionarFeDs' },
            { id : 'etapa4DatosContabilizado',nombre: 'Datos Contabilizado',recurso: 'RecepcionGestionDocumentosEtapa4DatosContabilizado' },
            { id : 'etapa4SiguienteEtapa',    nombre: 'Siguiente Etapa',    recurso: 'RecepcionGestionDocumentosEtapa4SiguienteEtapa' }
        ],
        5: [
            { id : 'etapa5GestionarFeDs',     nombre: 'Gestionar FE/DS',    recurso: 'RecepcionGestionDocumentosEtapa5GestionarFeDs' },
            { id : 'etapa5SiguienteEtapa',    nombre: 'Siguiente Etapa',    recurso: 'RecepcionGestionDocumentosEtapa5SiguienteEtapa' }
        ],
        6: [
            { id : 'etapa6GestionarFeDs',     nombre: 'Gestionar FE/DS',    recurso: 'RecepcionGestionDocumentosEtapa6GestionarFeDs' },
            { id : 'etapa6SiguienteEtapa',    nombre: 'Siguiente Etapa',    recurso: 'RecepcionGestionDocumentosEtapa6SiguienteEtapa' }
        ],
    };

    // Columnas del tracking
    public columns: GestionDocumentosTrackingColumnInterface[] = [
        {name: 'Tipo',       prop: 'gdo_clasificacion',  sorteable: false,  width: '80'},
        {name: 'Documento',  prop: 'documento',          sorteable: false,  width: '120'},
        {name: 'Emisor',     prop: 'emisor_razon_social',sorteable: false,  width: '250'},
        {name: 'Fecha',      prop: 'gdo_fecha_hora',     sorteable: true,   width: '150'},
        {name: 'Moneda',     prop: 'mon_codigo',         sorteable: false,  width: '80'},
        {name: 'Valor',      prop: 'gdo_valor_a_pagar',  sorteable: false,  width: '150', derecha: true},
        {name: 'Estado',     prop: 'estado',             sorteable: false,  width: '100'},
    ];

    /**
     * Constructor del componente.
     * 
     * @param {Auth} _auth
     * @param {Router} _router
     * @param {ActivatedRoute} _activatedRoute
     * @param {GestionDocumentosService} _gestionDocumentosService
     * @param {MatDialog} _matDialog
     * @memberof ListarEtapasComponent
     */
    constructor(
        public _auth : Auth,
        private _router : Router,
        private _activatedRoute: ActivatedRoute,
        private _gestionDocumentosService: GestionDocumentosService,
        private _matDialog: MatDialog
    ) {
        super();
        this.txtBreadCrum      = this._activatedRoute.snapshot.data['breadcrum'];
        this.etapa             = this._activatedRoute.snapshot.data['etapa'];
        this.aclsUsuario       = this._auth.getAcls();
        this.trackingInterface = this;
    }

    /**
     * Inicializa las variables del componente.
     *
     * @private
     * @memberof ListarEtapasComponent
     */
    private init(): void {
        this.initDataSort('gdo_fecha');
        this.ordenDireccion = 'ASC';
    }

    /**
     * Ciclo OnInit del componente.
     *
     * @memberof ListarEtapasComponent
     */
    ngOnInit(): void {
        this.init();
        this.verificarAccionesBloque();

        // Asigna el recurso para la opción del tracking Gestionar y Siguiente Etapa
        switch (this.etapa) {
            case 1:
                this.permisoGestionarEtapa = 'RecepcionGestionDocumentosEtapa1GestionarFeDs';
                this.permisoSiguienteEtapa = 'RecepcionGestionDocumentosEtapa1SiguienteEtapa';
                break;
            case 2:
                this.permisoGestionarEtapa = 'RecepcionGestionDocumentosEtapa2GestionarFeDs';
                this.permisoSiguienteEtapa = 'RecepcionGestionDocumentosEtapa2SiguienteEtapa';
                break;
            case 3:
                this.permisoGestionarEtapa = 'RecepcionGestionDocumentosEtapa3GestionarFeDs';
                this.permisoSiguienteEtapa = 'RecepcionGestionDocumentosEtapa3SiguienteEtapa';
                break;
            case 4:
                this.permisoGestionarEtapa = 'RecepcionGestionDocumentosEtapa4GestionarFeDs';
                this.permisoSiguienteEtapa = 'RecepcionGestionDocumentosEtapa4SiguienteEtapa';
                break;
            case 5:
                this.permisoGestionarEtapa = 'RecepcionGestionDocumentosEtapa5GestionarFeDs';
                this.permisoSiguienteEtapa = 'RecepcionGestionDocumentosEtapa5SiguienteEtapa';
                break;
            case 6:
                this.permisoGestionarEtapa = 'RecepcionGestionDocumentosEtapa6GestionarFeDs';
                this.permisoSiguienteEtapa = 'RecepcionGestionDocumentosEtapa6SiguienteEtapa';
                break;
            default:
                break;
        }
    }

    /**
     * Realiza la petición para enviar a Gestión Fe/Ds con los parámetros.
     *
     * @private
     * @return {Promise<any>}
     * @memberof ListarEtapasComponent
     */
    private enviarGestionFeDs(params: object): Promise<any> {
        return new Promise((resolve, reject) => {
            this._gestionDocumentosService.gestionarEtapasFeDs(params).subscribe({
                next: () => {
                    resolve(true);
                },
                error: (error) => {
                    reject(this.parseError(error));
                }
            });
        });
    }

    /**
     * Realiza la petición para enviar a Siguiente Etapa con los parámetros.
     *
     * @private
     * @return {Promise<any>}
     * @memberof ListarEtapasComponent
     */
    private enviarSiguienteEtapa(params: object): Promise<any> {
        return new Promise((resolve, reject) => {
            this._gestionDocumentosService.enviarSiguienteEtapa(params).subscribe({
                next: () => {
                    resolve(true);
                },
                error: (error) => {
                    reject(this.parseError(error));
                }
            });
        });
    }

    /**
     * Realiza la petición para enviar la asignación del centro de costo u operación.
     *
     * @private
     * @return {Promise<any>}
     * @memberof ListarEtapasComponent
     */
    private enviarAsignarCentro(params: object, modal: string): Promise<any> {
        return new Promise((resolve, reject) => {
            let metodoEnviarAsignar = this._gestionDocumentosService.enviarAsignarCentroOperacion(params);
            // Etapas para las que aplica el combo Centro de Costo
            if(modal === 'costo')
                metodoEnviarAsignar = this._gestionDocumentosService.enviarAsignarCentroCosto(params);

            metodoEnviarAsignar.subscribe({
                next: () => {
                    resolve(true);
                },
                error: (error) => {
                    reject(this.parseError(error));
                }
            });
        });
    }

    /**
     * Realiza la petición para asignar los datos contabilizado.
     *
     * @private
     * @return {Promise<any>}
     * @memberof ListarEtapasComponent
     */
    private enviarDatosContabilizado(params: object): Promise<any> {
        return new Promise((resolve, reject) => {
            this._gestionDocumentosService.enviarDatosContabilizado(params).subscribe({
                next: () => {
                    resolve(true);
                },
                error: (error) => {
                    reject(this.parseError(error));
                }
            });
        });
    }

    /**
     * Retorna los parámetros de los filtros para ser enviados en la petición.
     *
     * @private
     * @param {boolean} [excel=false] Indica si es una descarga excel
     * @return {ParametrosListar}
     * @memberof ListarEtapasComponent
     */
    private getPayload(excel: boolean = false): ParametrosListar {
        const { 
            ofe_id,
            gdo_id,
            gdo_clasificacion,
            rfa_prefijo,
            gdo_consecutivo,
            estado_gestion,
            centro_operacion,
            centro_costo,
            gdo_fecha_desde,
            gdo_fecha_hasta
        } = this.filtros.form.getRawValue();
        
        const params: ParametrosListar = {
            ofe_id          : ofe_id,
            gdo_fecha_desde : String(moment(gdo_fecha_desde).format('YYYY-MM-DD')),
            gdo_fecha_hasta : String(moment(gdo_fecha_hasta).format('YYYY-MM-DD')),
            etapa           : this.etapa,
            length          : this.length,
            columnaOrden    : this.columnaOrden,
            ordenDireccion  : this.ordenDireccion,
        };

        if(gdo_id && gdo_id.length > 0)
            params.gdo_identificacion = gdo_id;

        if(excel)
            params.excel = excel;

        if(gdo_clasificacion)
            params.gdo_clasificacion = gdo_clasificacion;

        if(rfa_prefijo)
            params.rfa_prefijo = rfa_prefijo;

        if(gdo_consecutivo)
            params.gdo_consecutivo = gdo_consecutivo;

        if(estado_gestion && estado_gestion.length > 0)
            params.estado_gestion = estado_gestion;

        if(centro_operacion)
            params.centro_operacion = centro_operacion;

        if(centro_costo)
            params.centro_costo = centro_costo;

        return params;
    }

    /**
     * Agrega las acciones en bloque sobre las que tiene permiso el usuario autenticado.
     *
     * @private
     * @memberof ListarEtapasComponent
     */
    private verificarAccionesBloque(): void {
        this.arrAccionesBloqueEtapas[this.etapa]?.forEach(({ recurso, ... others }) => {
            if (this._auth.existeRol(this.aclsUsuario.roles, 'superadmin') || this._auth.existePermiso(this.aclsUsuario.permisos, recurso)) {
                this.accionesBloque.push(others);
            }
        });
    }

    /**
     * Apertura la modal de Gestionar Fe/Ds.
     *
     * @private
     * @param {object} data Documentos a inyectar en la modal
     * @memberof ListarEtapasComponent
     */
    private openModalGestionFeDs(data : object): void {
        const modalConfig = new MatDialogConfig();
        modalConfig.autoFocus = true;
        modalConfig.width = '600px';
        modalConfig.data = {
            etapa      : this.etapa,
            documentos : data,
            parent     : this,
        };
        this._matDialog.open(ModalGestionFeDsComponent, modalConfig);
    }

    /**
     * Apertura la modal de Datos Contabilizado.
     *
     * @private
     * @param {object} data Documentos a inyectar en la modal
     * @memberof ListarEtapasComponent
     */
    private openModalDatosContabilizado(data : object): void {
        const modalConfig = new MatDialogConfig();
        modalConfig.autoFocus = true;
        modalConfig.width = '600px';
        modalConfig.data = {
            documentos : data,
            parent     : this,
        };
        this._matDialog.open(ModalDatosContabilizadoComponent, modalConfig);
    }

    /**
     * Apertura la modal de Asignación para Centro de Costo o Centro de Operación.
     *
     * @private
     * @param {object} data Documentos a inyectar en la modal
     * @memberof ListarEtapasComponent
     */
    private openModalAsignacionCentro(data : object): void {
        const modalConfig = new MatDialogConfig();
        modalConfig.autoFocus = true;
        modalConfig.width = '500px';
        modalConfig.data = {
            etapa      : this.etapa,
            documentos : data,
            parent     : this,
        };
        this._matDialog.open(ModalAsignacionComponent, modalConfig);
    }

    /**
     * Realiza la petición para obtener los documentos.
     *
     * @memberof ListarEtapasComponent
     */
    public loadDocumentos(): void {
        this.loading(true);
        this.loadingIndicator = true;
        const parameters = this.getPayload();
        this._gestionDocumentosService.listarDocumentos(parameters, this.paginador, this.linkAnterior, this.linkSiguiente).subscribe({
            next: res => {
                this.registros    = [];
                this.linkAnterior  = res.pag_anterior ? res.pag_anterior : '';
                this.linkSiguiente = res.pag_siguiente ? res.pag_siguiente : '';

                res.data.forEach(reg => {
                    this.registros.push({
                        'gdo_id'              : reg.gdo_id,
                        'ofe_id'              : reg.ofe_id,
                        'adq_id'              : reg.adq_id,
                        'pro_id'              : reg.pro_id,
                        'gdo_clasificacion'   : reg.gdo_clasificacion,
                        'rfa_prefijo'         : reg.rfa_prefijo,
                        'gdo_consecutivo'     : reg.gdo_consecutivo,
                        'gdo_identificacion'  : reg.gdo_identificacion,
                        'documento'           : `${ (reg.rfa_prefijo + ' ') || '' }${ reg.gdo_consecutivo }`,
                        'emisor_razon_social' : `${ reg.gdo_identificacion } - ${ reg.emisor_razon_social }`,
                        'gdo_fecha_hora'      : `${ reg.gdo_fecha } ${ reg.gdo_hora || '' }`,
                        'mon_codigo'          : reg.get_parametros_moneda?.mon_codigo || '',
                        'gdo_valor_a_pagar'   : reg.gdo_valor_a_pagar,
                        'estado_gestion'      : reg.estado_gestion,
                        'estado'              : reg.estado,
                    });
                });
                this.loading(false);
                this.loadingIndicator = false;
                this.totalElements    = res.data.length;
                this.totalShow        = this.length;
                this.existeConsulta   = (res.data.length > 0) ? true : false;
            },
            error: error => {
                this.loading(false);
                this.loadingIndicator = false;
                const texto_errores = this.parseError(error);
                this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al cargar los Documentos', '0k, entiendo', 'btn btn-danger', '/dashboard', this._router);
            }
        });
    }

    /**
     * Realiza la búsqueda de los documentos.
     *
     * @memberof ListarEtapasComponent
     */
    public searchDocumentos(): void {
        if(this.filtros.form.valid) {
            this.onPage();
            this.gestionDocumentosTracking.tracking.offset = 0;
        }
    }

    /**
     * Efectua la carga de datos.
     *
     * @memberof ListarEtapasComponent
     */
    public getData(): void {
        this.loadDocumentos();
    }

    /**
     * Gestiona el evento de paginacion de la grid.
     *
     * @param {string} page Información del evento
     * @memberof ListarEtapasComponent
     */
    public onPage(page: string = ''): void {
        this.paginador = page;
        this.selected = [];
        this.getData();
    }

    /**
     * Cambia la cantidad de registros del paginado y recarga el listado.
     *
     * @param {number} size Cantidad de registros
     * @memberof ListarEtapasComponent
     */
    public onChangeSizePage(size: number): void {
        this.length = size;
        this.onPage();
        this.gestionDocumentosTracking.tracking.offset = 0;
    }

    /**
     * Realiza el ordenamiento de los registros y recarga el listado.
     *
     * @param {string} column Columna por la cual se organizan los registros
     * @param {string} $order Dirección del orden de los registros [ASC - DESC]
     * @memberof ListarEtapasComponent
     */
    public onOrderBy(column: string, $order: string): void {
        this.selected = [];
        switch (column) {
            case 'fecha':
            default:
                this.columnaOrden = 'gdo_fecha';
                break;
        }

        this.paginador = '';
        this.ordenDireccion = $order;
        this.getData();
    }

    /**
     * Gestiona la acción seleccionada en el select de Acciones en Bloque.
     *
     * @param {*} opcion Acción en bloque a ejecutar
     * @param {never[]} selected Registros seleccionados
     * @memberof ListarEtapasComponent
     */
    public onOptionMultipleSelected(opcion: any, selected: never[]): void {
        this.selected = selected;
        this.accionesEnBloque(opcion);
    }

    /**
     * Acción cuando se selecciona la opción Gestionar Fe/Ds de un registro del tracking o en acción en bloque.
     *
     * @param {any[]} rows Registros seleccionados
     * @return {Promise<void>}
     * @memberof ListarEtapasComponent
     */
    public async onValidarGestionarFeDs(rows: any[]): Promise<void> {
        const arrIds = rows.map( ({ gdo_id }) => gdo_id);
        const params = {
            gdo_ids : arrIds.join(),
            etapa   : this.etapa,
            validar : true
        };

        if (arrIds.length == 0) {
            this.showError('<h3>Debe seleccionar al menos un Documento</h3>', 'warning', 'Acciones en Bloque', 'Ok, entiendo', 'btn btn-warning');
        } else {
            this.loading(true);
            await this.enviarGestionFeDs(params)
                .then(() => {
                    // Aperturar la modal
                    const arrData = rows.map( ({ gdo_id, documento }) => {
                        return {
                            documento,
                            gdo_id
                        };
                    });
                    this.openModalGestionFeDs(arrData);
                })
                .catch((error) => {
                    this.loading(false);
                    this.showError(error, 'error', 'Error en la Validación de Documentos', 'Ok', 'btn btn-danger');
                });
        }
    }

    /**
     * Acción cuando se selecciona la opción Siguiente Etapa de un registro del tracking o en acción en bloque.
     *
     * @param {any[]} rows Registros seleccionados
     * @return {Promise<void>}
     * @memberof ListarEtapasComponent
     */
    public async onValidarSiguienteEtapa(rows: any[]): Promise<void> {
        const arrIds = rows.map( ({ gdo_id }) => gdo_id);
        const params: any = {
            gdo_ids : arrIds.join(),
            etapa   : this.etapa,
            validar : true
        };

        if (arrIds.length == 0) {
            this.showError('<h3>Debe seleccionar al menos un Documento</h3>', 'warning', 'Acciones en Bloque', 'Ok, entiendo', 'btn btn-warning');
        } else {
            this.loading(true);
            await this.enviarSiguienteEtapa(params)
                .then(() => {
                    this.loading(false);
                    // Mensaje de confirmación para enviar la petición
                    swal({
                        html: `¿Está seguro de enviar a la siguiente etapa los documentos seleccionados?<br><br><strong>${ rows.map( ({ documento }) => documento).join(", ") }</strong><br><br>`,
                        type: 'warning',
                        showCancelButton: true,
                        confirmButtonClass: 'btn btn-success',
                        cancelButtonClass: 'btn btn-danger',
                        confirmButtonText: 'Aceptar',
                        cancelButtonText: 'Cancelar',
                        buttonsStyling: false,
                        allowOutsideClick: false
                    })
                    .then((result) => {
                        if (result.value) {
                            this.loading(true);
                            delete params.validar;
                            this.enviarSiguienteEtapa(params).then( () => {
                                this.showSuccess('Se enviaron a la siguiente etapa los documentos seleccionados', 'success', 'Siguiente Etapa', 'Ok', 'btn btn-success');
                                this.getData();
                            })
                            .catch( (error) => {
                                this.loading(false);
                                this.showError(error, 'error', 'Error en la Validación de Documentos', 'Ok', 'btn btn-danger');
                            })
                        }
                    }).catch(swal.noop);
                })
                .catch((error) => {
                    this.loading(false);
                    this.showError(error, 'error', 'Error en la Validación de Documentos', 'Ok', 'btn btn-danger');
                });
        }
    }

    /**
     * Validación para la asignación del centro de costo.
     *
     * @param {any[]} rows Registros seleccionados
     * @return {Promise<void>}
     * @memberof ListarEtapasComponent
     */
    public async onValidarAsignarCentroCosto(rows: any[]): Promise<void> {
        const arrIds = rows.map( ({ gdo_id }) => gdo_id);
        const params = {
            gdo_ids : arrIds.join(),
            validar : true
        };

        if (arrIds.length == 0) {
            this.showError('<h3>Debe seleccionar al menos un Documento</h3>', 'warning', 'Acciones en Bloque', 'Ok, entiendo', 'btn btn-warning');
        } else {
            this.loading(true);
            await this.enviarAsignarCentro(params, 'costo')
            .then(() => {
                // Aperturar la modal
                const arrData = rows.map( ({ gdo_id, documento }) => {
                    return {
                        documento,
                        gdo_id
                    };
                });
                this.openModalAsignacionCentro(arrData);
            })
            .catch((error) => {
                this.loading(false);
                this.showError(error, 'error', 'Error en la Validación de Documentos', 'Ok', 'btn btn-danger');
            });
        }
    }

    /**
     * Validación para la asignación del centro de operación.
     *
     * @param {any[]} rows Registros seleccionados
     * @return {Promise<void>}
     * @memberof ListarEtapasComponent
     */
    public async onValidarAsignarCentroOperacion(rows: any[]): Promise<void> {
        const arrIds = rows.map( ({ gdo_id }) => gdo_id);
        const params = {
            gdo_ids : arrIds.join(),
            validar : true
        };

        if (arrIds.length == 0) {
            this.showError('<h3>Debe seleccionar al menos un Documento</h3>', 'warning', 'Acciones en Bloque', 'Ok, entiendo', 'btn btn-warning');
        } else {
            this.loading(true);
            await this.enviarAsignarCentro(params, 'operacion')
            .then(() => {
                // Aperturar la modal
                const arrData = rows.map( ({ gdo_id, documento }) => {
                    return {
                        documento,
                        gdo_id
                    };
                });
                this.openModalAsignacionCentro(arrData);
            })
            .catch((error) => {
                this.loading(false);
                this.showError(error, 'error', 'Error en la Validación de Documentos', 'Ok', 'btn btn-danger');
            });
        }
    }

    /**
     * Validación para la gestión de los datos contabilizado.
     *
     * @param {any[]} rows Registros seleccionados
     * @return {Promise<void>}
     * @memberof ListarEtapasComponent
     */
    public async onValidarDatosContabilizado(rows: any[]): Promise<void> {
        const arrIds = rows.map( ({ gdo_id }) => gdo_id);
        const params = {
            gdo_ids : arrIds.join(),
            validar : true
        };
        this.loading(true);
        await this.enviarDatosContabilizado(params)
        .then(() => {
            // Aperturar la modal
            const arrData = rows.map( ({ gdo_id, documento }) => {
                return {
                    documento,
                    gdo_id
                };
            });
            this.openModalDatosContabilizado(arrData);
        })
        .catch((error) => {
            this.loading(false);
            this.showError(error, 'error', 'Error en la Validación de Documentos', 'Ok', 'btn btn-danger');
        });
    }

    /**
     * Responde a las diferentes acciones del combo de Acciones en Bloque.
     *
     * @param {string} selectedOption Acción en bloque seleccionada
     * @memberof ListarEtapasComponent
     */
    public accionesEnBloque(selectedOption: string): void {
        switch (selectedOption) {
            case 'etapa1GestionarFeDs':
            case 'etapa2GestionarFeDs':
            case 'etapa3GestionarFeDs':
            case 'etapa4GestionarFeDs':
            case 'etapa5GestionarFeDs':
            case 'etapa6GestionarFeDs':
                this.onValidarGestionarFeDs(this.selected);
                break;

            case 'etapa1CentroOperaciones':
                this.onValidarAsignarCentroOperacion(this.selected);
                break;

            case 'etapa2CentroCosto':
                this.onValidarAsignarCentroCosto(this.selected);
                break;

            case 'etapa1SiguienteEtapa':
            case 'etapa2SiguienteEtapa':
            case 'etapa3SiguienteEtapa':
            case 'etapa4SiguienteEtapa':
            case 'etapa5SiguienteEtapa':
            case 'etapa6SiguienteEtapa':
                this.onValidarSiguienteEtapa(this.selected);
                break;

            case 'etapa4DatosContabilizado':
                this.onValidarDatosContabilizado(this.selected);
                break;

            default:
                break;
        }
        this.selected = [];
    }

    /**
     * Gestiona la acción del botón de descargar Excel.
     *
     * @memberof ListarEtapasComponent
     */
    public onDescargarExcel(): void {}
}
