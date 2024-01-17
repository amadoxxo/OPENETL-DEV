import swal from 'sweetalert2';
import {Router} from "@angular/router";
import {Component, OnInit} from '@angular/core';
import {BaseComponentList} from '../../../core/base_component_list';
import {Auth} from '../../../../services/auth/auth.service';
import {ConfiguracionService} from '../../../../services/configuracion/configuracion.service';
import {OferentesService} from '../../../../services/configuracion/oferentes.service';
import {TrackingColumnInterface, TrackingInterface, TrackingOptionsInterface} from '../../../commons/open-tracking/tracking-interface';

@Component({
    selector: 'app-oferentes-listar',
    templateUrl: './oferentes-listar.component.html',
    styleUrls: ['./oferentes-listar.component.scss']
})
export class OferentesListarComponent extends BaseComponentList implements OnInit, TrackingInterface {

    public trackingInterface: TrackingInterface;
    public loadingIndicator: any;
    public registros: any [] = [];
    public aclsUsuario: any;

    public columns: TrackingColumnInterface[] = [
        {name: 'Identificación', prop: 'ofe_identificacion', sorteable: true, width: 120},
        {name: 'Razón Social', prop: 'ofe_razon_social', sorteable: true, width: 200},
        {name: 'Nombre Comercial', prop: 'ofe_nombre_comercial', sorteable: true, width: 200},
        {name: 'Nombre Completo', prop: 'nombre_completo', sorteable: true, width: 200},
        {name: 'Estado', prop: 'estado', sorteable: true, width: 100}
    ];

    public accionesBloque = [
        {id: 'crearAdquirenteConsumidorFinal', itemName: 'Crear Adquirente Consumidor Final'}
    ];

    public trackingOpciones: TrackingOptionsInterface = {
        editButton: true, 
        showButton: true,
        configuracionServicioButton: true,
        configuracionDocumentoElectronicoButton: true,
        configuracionDocumentoSoporteButton: true,
        valoresPorDefectoEnDocumentoButton: true
    };

    constructor(
        private _router:Router,
        public _auth: Auth,
        private _configuracionService: ConfiguracionService,
        private _oferentesService: OferentesService
    ) {
        super();
        this._configuracionService.setSlug = "ofe";
        this.trackingInterface = this;
        this.rows = [];
    }

    ngOnInit() {
        this.init();
    }

    /**
     * Se encarga de inicializar los parámetros para la búsqueda.
     * 
     */
    private init() {
        this.initDataSort('modificado');
        this.loadingIndicator = true;
        this.ordenDireccion = 'DESC';
        this.aclsUsuario = this._auth.getAcls();
        this.loadOfes();
    }

    /**
     * Sobreescribe los parámetros de búsqueda inline - (Get).
     * 
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
     * Permite ir a la pantalla para crear un nuevo oferente
     */
    nuevoOfe() {
        this._router.navigate(['configuracion/oferentes/nuevo-oferente']);
    }

    /**
     * Permite ir a la pantalla para subir oferentes
     */
    subirOfes() {
        this._router.navigate(['configuracion/oferentes/subir-oferentes']);
    }

    /**
     * Se encarga de traer la data de los diferentes registros.
     * 
     */
    public loadOfes(): void {
        this.loading(true);
        this._configuracionService.listar(this.getSearchParametersInline()).subscribe(
            res => {
                this.registros.length = 0;
                this.loading(false);
                this.rows = res.data;
                res.data.forEach(reg => {
                    this.registros.push(
                        {
                            'ofe_id': reg.ofe_id,
                            'ofe_identificacion': reg.ofe_identificacion,
                            'ofe_razon_social': reg.ofe_razon_social,
                            'ofe_nombre_comercial': reg.ofe_nombre_comercial,
                            'Nombre Completo': reg.nombre_completo,
                            'ofe_tiene_representacion_grafica_personalizada': reg.ofe_tiene_representacion_grafica_personalizada,
                            'ofe_tiene_representacion_grafica_personalizada_ds': reg.ofe_tiene_representacion_grafica_personalizada_ds,
                            'ofe_cadisoft_activo': reg.ofe_cadisoft_activo,
                            'ofe_emision': reg.ofe_emision,
                            'ofe_documento_soporte': reg.ofe_documento_soporte,
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
                this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al cargar los Oferentes', '0k, entiendo', 'btn btn-danger', '/dashboard', this._router);
            });
    }

    /**
     * Gestiona el evento de paginación de la grid.
     * 
     * @param $evt
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
     * @param evt
     */
    onCheckboxChangeFn(evt: any) {

    }

    /**
     * Efectua la carga de datos.
     * 
     */
    public getData() {
        this.loadingIndicator = true;
        this.loadOfes();
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

    recargarLista() {
        this.getData();
    }

     /**
     * Gestiona la acción seleccionada en el select de Acciones en Bloque de los registrados seleccionados en la grid.
     * 
     */
    public accionesEnBloque(accion) {
        if (accion === 'cambiarEstado') {
            if (this.selected.length == 0){
                this.showError('<h3>Debe seleccionar al menos un OFE para cambiar su estado</h3>', 'warning', 'Cambio de Estado', 'Ok, entiendo',
                    'btn btn-warning');
            } else {
                let identificaciones = '';
                this.selected.forEach(reg => {
                    identificaciones += reg.ofe_identificacion + ',';
                });
                identificaciones = identificaciones.slice(0, -1);
                swal({
                    html: '¿Está seguro de cambiar el estado de los OFEs seleccionados?',
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
                        let payload = {
                            'identificaciones': identificaciones
                        }
                        this._configuracionService.cambiarEstado(payload).subscribe(
                            response => {
                                this.loadOfes();
                                this.loading(false);
                                this.showSuccess('<h3>Los registros seleccionados de OFEs han cambiado de estado</h3>', 'success', 'Cambio de estado exitoso', 'OK', 'btn btn-success');
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
        } else if (accion === 'crearAdquirenteConsumidorFinal') {
            if (this.selected.length == 0){
                this.showError('<h3>Debe seleccionar al menos un OFE para crear el adquirente</h3>', 'warning', 'Crear Adquirente Consumidor Final', 'Ok, entiendo',
                    'btn btn-warning');
            } else {
                let identificaciones = '';
                this.selected.forEach(reg => {
                    identificaciones += reg.ofe_identificacion + ',';
                });
                identificaciones = identificaciones.slice(0, -1);
                this.loading(true);
                let payload = {
                    'identificaciones': identificaciones
                }
                this._oferentesService.crearAdquirenteConsumidorFinal(payload).subscribe(
                    response => {
                        this.loadOfes();
                        this.loading(false);
                        this.showSuccess('<h3>' + response.message + '</h3>', 'success', 'Adquirente Consumidor Final', 'OK', 'btn btn-success');
                    },
                    error => {
                        this.loading(false);
                        const texto_errores = this.parseError(error);
                        this.loadingIndicator = false;
                        this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error', 'OK', 'btn btn-danger');
                    }
                );
            }
        } 
        this.selected = [];
    }

    /**
     * Descarga los registros filtrados en un archivos de excel.
     * 
     */
    descargarExcel() {
        this.loading(true);
        this._configuracionService.descargarExcelGet(this.getSearchParametersInline(true)).subscribe(
            response => {
                this.loading(false);
            },
            (error) => {
                this.loading(false);
                this.showError('<h3>Error en descarga</h3><p>Verifique que la consulta tenga resultados.</p>', 'error', 'Error al descargar archivo excel de registros de OFEs', 'OK', 'btn btn-danger');
            }
        );
    }

    /**
     * Recarga el listado en base al término de búsqueda.
     * 
     */
    onSearchInline(buscar: string) {
        this.start = 0;
        this.buscar = buscar;
        this.recargarLista();
    }

    /**
     * Cambia la cantidad de registros del paginado y recarga el listado.
     * 
     */
    onChangeSizePage(size: number) {
        this.length = size;
        this.recargarLista();
    }

    /**
     * Realiza el ordenamiento de los registros y recarga el listado.
     * 
     */
    onOrderBy(column: string, $order: string) {
        this.selected = [];
        switch (column) {
            case 'ofe_identificacion':
                this.columnaOrden = 'identificacion';
                break;
            case 'ofe_razon_social':
                this.columnaOrden = 'razon';
                break;
            case 'ofe_nombre_comercial':
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
        this.recargarLista();
    }

    /**
     * Gestiona la acción seleccionada en el select de Acciones en Bloque.
     * 
     */
    onOptionMultipleSelected(opcion: any, selected: any[]) {
        this.selected = selected;
        this.accionesEnBloque(opcion);
    }

    /**
     * Gestiona la acción del botón de ver un registro
     * 
     */
    onViewItem(item: any) {
        this._router.navigate(['configuracion/oferentes/ver-oferente/' + item.ofe_identificacion + '/' + item.ofe_id]);
    }

    /**
     * Gestiona la acción del botón de eliminar un registro
     * 
     */
    onRequestDeleteItem(item: any) {
        
    }

    /**
     * Gestiona la acción del botón de editar un registro.
     *
     * @param {*} item Información del registro
     * @memberof OferentesListarComponent
     */
    onEditItem(item: any) {
        this._router.navigate(['configuracion/oferentes/editar-oferente/' + item.ofe_identificacion]);
    }

    /**
     * Gestiona la acción del botón de administrar la representación gráfica estándar de un OFE
     *
     * @param {*} item Información del registro
     * @memberof OferentesListarComponent
     */
    onConfigurarDocumentoElectronico(item: any) {
        this._router.navigate(['configuracion/oferentes/configuracion-documento-electronico/' + item.ofe_identificacion]);
    }

    /**
     * Gestiona la acción del botón de administrar la representación gráfica estándar de un OFE
     * 
     * @param {*} item Información del registro
     * @memberof OferentesListarComponent
     */
    onConfigurarDocumentoSoporte(item: any) {
        this._router.navigate(['configuracion/oferentes/configuracion-documento-soporte/' + item.ofe_identificacion]);
    }

    /**
     * Gestiona la acción del botón de administrar los valores por defecto del documento electrónico.
     *
     * @param {*} item Información del registro
     * @memberof OferentesListarComponent
     */
    onValoresPorDefectoEnDocumento(item: any) {
        this._router.navigate(['configuracion/oferentes/valores-por-defecto-documento-electronico/' + item.ofe_identificacion]);
    }

    /**
     * Gestiona la acción del botón de configuración de servicios para el OFE.
     *
     * @param {*} item Información del registro
     * @memberof OferentesListarComponent
     */
    onConfigurarServicios(item: any) {
        this._router.navigate(['configuracion/oferentes/configuracion-servicios/' + item.ofe_identificacion]);
    }
}
