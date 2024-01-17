import swal from 'sweetalert2';
import {Router} from "@angular/router";
import {Component, OnInit} from '@angular/core';
import {BaseComponentList} from '../../../core/base_component_list';
import {Auth} from '../../../../services/auth/auth.service';
import * as moment from 'moment';
import {ConfiguracionService} from '../../../../services/configuracion/configuracion.service';
import {TrackingColumnInterface, TrackingInterface, TrackingOptionsInterface} from '../../../commons/open-tracking/tracking-interface';

@Component({
    selector: 'app-software-proveedor-tecnologico-listar',
    templateUrl: './software-proveedor-tecnologico-listar.component.html',
    styleUrls: ['./software-proveedor-tecnologico-listar.component.scss']
})
export class SoftwareProveedorTecnologicoListarComponent extends BaseComponentList implements OnInit, TrackingInterface {

    public trackingInterface: TrackingInterface;
    public loadingIndicator: any;
    public registros: any [] = [];
    public aclsUsuario: any;

    public columns: TrackingColumnInterface[] = [
        {name: 'Identificador', prop: 'sft_identificador', sorteable: true, width: 120},
        {name: 'Pin', prop: 'sft_pin', sorteable: true, width: 100},
        {name: 'Nombre', prop: 'sft_nombre', sorteable: true, width: 200},
        {name: 'Aplica Para', prop: 'sft_aplica_para', sorteable: true, width: 100},
        {name: 'Nit', prop: 'sft_nit_proveedor_tecnologico', sorteable: true, width: 120},
        {name: 'Razon Social', prop: 'sft_razon_social_proveedor_tecnologico', sorteable: true, width: 165},
        {name: 'Fecha de Registro', prop: 'sft_fecha_registro', sorteable: true, width: 150},
        {name: 'Estado', prop: 'estado', sorteable: true, width: 120}
    ];

    public accionesBloque = [
        // {id: 'cambiarEstado', itemName: 'Cambiar Estado'}
    ];

    public trackingOpciones: TrackingOptionsInterface = {
        editButton: true, 
        showButton: true
    };

    constructor(
        private _router:Router,
        public _auth: Auth,
        private _configuracionService: ConfiguracionService) {
        super();
        this._configuracionService.setSlug = "spt";
        this.trackingInterface = this;
        this.rows = [];
    }

    ngOnInit() {
        this.init();
    }

    /**
     * Permite ir a la pantalla para subir spts.
     *
     */
    subirSPTs() {
        this._router.navigate(['configuracion/software-proveedor-tecnologico/subir-software-proveedor-tecnologico']);
    }


    /**
     * Se encarga de inicializar los parámetros para la búsqueda.
     * 
     */
    private init() {
        this.initDataSort('codigo');
        this.loadingIndicator = true;
        this.ordenDireccion = 'ASC';
        this.aclsUsuario = this._auth.getAcls();
        this.loadSPTs();
    }

    /**
     * Sobreescribe los parámetros de búsqueda inline - (Get).
     * 
     */
    getSearchParametersInline(excel = false, tracking = true, aplicaPara = 'DE,DS,DN'): string {
        let query = 'start=' + this.start + '&' +
        'length=' + this.length + '&' +
        'buscar=' + this.buscar + '&' +
        'columnaOrden=' + this.columnaOrden + '&' +
        'ordenDireccion=' + this.ordenDireccion;
        if (excel)
            query += '&excel=true';
        if (tracking)
            query += '&tracking=true';
        if (aplicaPara !== '')
            query += '&aplica_para='+ aplicaPara +'';

        return query;
    }

    /**
     * Permite ir a la pantalla para crear un nuevo oferente
     */
    nuevoSpt() {
        this._router.navigate(['configuracion/software-proveedor-tecnologico/nuevo-software-proveedor-tecnologico']);
    }

    /**
     * Se encarga de traer la data de los diferentes registros.
     * 
     */
    public loadSPTs(): void {
        this.loading(true);
        this._configuracionService.listar(this.getSearchParametersInline()).subscribe(
            res => {
                this.registros.length = 0;
                this.loading(false);
                this.rows = res.data;
                res.data.forEach(reg => {
                    this.registros.push(
                        {
                            'sft_id': reg.sft_id,
                            'sft_identificador': reg.sft_identificador,
                            'sft_pin': reg.sft_pin,
                            'sft_aplica_para': reg.sft_aplica_para,
                            'sft_nombre': reg.sft_nombre,
                            'sft_nit_proveedor_tecnologico': reg.sft_nit_proveedor_tecnologico,
                            'sft_razon_social_proveedor_tecnologico': reg.sft_razon_social_proveedor_tecnologico,
                            'sft_fecha_registro': moment(reg.sft_fecha_registro).format('YYYY-MM-DD'),
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
                this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al cargar los Proveedores Tecnológicos', '0k, entiendo', 'btn btn-danger', '/dashboard', this._router);
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
        this.loadSPTs();
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
                this.showError('<h3>Debe seleccionar al menos un Proveedor Tecnológico para cambiar su estado</h3>', 'warning', 'Cambio de Estado', 'Ok, entiendo',
                    'btn btn-warning');
            } else {
                let identificadores = '';
                this.selected.forEach(reg => {
                    // ids.push(reg.sft_id);
                    identificadores += reg.sft_id + ',';
                });
                identificadores = identificadores.slice(0, -1);
                swal({
                    html: '¿Está seguro de cambiar el estado de los Proveedores Tecnológicos seleccionados?',
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
                            'sft-identificadores': identificadores
                        }
                        this._configuracionService.cambiarEstado(payload).subscribe(
                            response => {
                                this.loadSPTs();
                                this.loading(false);
                                this.showSuccess('<h3>Los registros de Proveedores Tecnológicos seleccionados han cambiado de estado</h3>', 'success', 'Cambio de estado exitoso', 'OK', 'btn btn-success');
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
            case 'sft_identificador':
                this.columnaOrden = 'codigo';
                break;
            case 'sft_pin':
                this.columnaOrden = 'pin';
                break;
            case 'sft_aplica_para':
                this.columnaOrden = 'aplica_para';
                break;
            case 'sft_nombre':
                this.columnaOrden = 'nombre';
                break;
            case 'sft_nit_proveedor_tecnologico':
                this.columnaOrden = 'nit';
                break;
            case 'sft_razon_social_proveedor_tecnologico':
                this.columnaOrden = 'razon_social';
                break;
            case 'sft_fecha_registro':
                this.columnaOrden = 'fecha_registro';
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
        this._router.navigate(['configuracion/software-proveedor-tecnologico/ver-software-proveedor-tecnologico/' + item.sft_identificador + '/' + item.sft_id]);
    }

    /**
     * Gestiona la acción del botón de eliminar un registro
     * 
     */
    onRequestDeleteItem(item: any) {
        
    }

    /**
     * Gestiona la acción del botón de editar un registro
     * 
     */
    onEditItem(item: any) {
        this._router.navigate(['configuracion/software-proveedor-tecnologico/editar-software-proveedor-tecnologico/' + item.sft_identificador + '/' + item.sft_id]);
    }

    // Aplica solamente a OFEs pero debe implementarse debido a la interface
    onConfigurarDocumentoElectronico(item: any) {
        
    }
}
