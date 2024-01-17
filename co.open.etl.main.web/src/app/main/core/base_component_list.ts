import {BaseComponent} from './base_component';
import {BsdConstants} from './bsd_constants';
import {GridInterface} from './grid_Interface';

export class BaseComponentList extends BaseComponent implements GridInterface {

    public rows: any[];
    public selected = [];
    public allRowsSelected: any[];
    public draw: number;
    public start: number;
    public length = BsdConstants.INIT_SIZE_SEARCH;
    public buscar: any;
    public filtroCompanias: any = [];
    public columnaOrden: string;
    public ordenDireccion: string;
    public loadingIndicator: boolean;
    public totalElements: number;
    public reorderable: boolean;
    public accionesLote: any;
    public paginationSize: any;
    public maxDate = new Date();
    public page = 0;
    public totalShow = BsdConstants.INIT_SIZE_SEARCH;
    public blockAll: boolean;

    // Rangos de busqueda
    public docStartDate: Date;
    public docEndDate: Date;

    /**
     * Mueve el limite de la fecha minima en caso que se fije primero la fecha máxima
     */
    public minimuDate() {
        if (this.docEndDate)
            return this.docEndDate;
        return this.maxDate;
    }

    /**
     * Mensajes para la tabla principal de los listados
     */
    public messageDT = {
        emptyMessage: 'No hay data para mostrar',
        totalMessage: 'total',
        selectedMessage: 'seleccionados'
    };

    /**
     * Constructor
     */
    constructor() {
        super();
        this.accionesLote = [
            {
                action: 'ACTIVO',
                label: 'ACTIVAR'
            },
            {
                action: 'INACTIVO',
                label: 'DESACTIVAR'
            }
        ];
        this.paginationSize = [
            {label: '10',    value: 10},
            {label: '25',    value: 25},
            {label: '50',    value: 50},
            {label: '100',   value: 100},
            {label: 'TODOS', value: -1}
        ];
    }

    /**
     * Inicializa los parametros de busqueda
     * @param column Indica la columna de referencia para inicar los ordenamientos
     */
    public initDataSort(column: string): void {
        this.draw = Math.floor((Math.random() * 10000));
        this.start = 0;
        this.length = BsdConstants.INIT_SIZE_SEARCH;
        this.buscar = '';
        this.filtroCompanias = [];
        this.columnaOrden = column;
        this.ordenDireccion = BsdConstants.DEFAULT_SORT;
    }

    /**
     * Retorna los parametros de busqueda con el esquema
     * start: registro de inicio
     * length: cantidad de registros a retornar
     * buscar: cadena de busqueda para filtrar resultados
     * filtroCompanias:_objeto que contiene campos especificos para buscar coincidencias contra el parametro "buscar"
     * columnaOrden: Especifica la columna para efectuar el ordenamiento de los resultado
     * ordenDireccion: Define el orden en que se obtendra la data ASC ó DESC
     */
    public getSearchParameters(): any {
        return {
            draw: this.draw,
            start: this.start,
            length: this.length,
            buscar: this.buscar,
            filtroCompanias: this.filtroCompanias.join(),
            columnaOrden: this.columnaOrden,
            ordenDireccion: this.ordenDireccion
        };
    }

    /**
     * Permite controlar la paginación
     * @param $event
     */
    public onPage($evt) {
    }

    /**
     * Permite controlar el ordenamiento por una columna
     * @param $event
     */
    public onSort($evt) {
        this.ordenDireccion = $evt.newValue;
    }

    /**
     * Evento base para la seleccion de todos los items de una grid
     * @param $evt
     */
    public onSelect($evt) {
    }

    /**
     * Evento base para la gestion del evento activate de cada row de una grid
     * @param $evt
     */
    public onActivate($evt) {
    }

    /**
     * Retorna la lista de parametros codificada
     */
    public getSearchParametersInline() {
        if (this.filtroCompanias === null)
            this.filtroCompanias = [];
        return 'start=' + this.start + '&' +
            'length=' + this.length + '&' +
            'buscar=' + this.buscar + '&' +
            'columnaOrden=' + this.columnaOrden + '&' +
            'ordenDireccion=' + this.ordenDireccion + '&' +
            'filtroCompanias=' + this.filtroCompanias.join();
    }
}
