import {Component, Input, OnInit} from '@angular/core';
import {BaseComponentList} from '../../core/base_component_list';
import {CommonsService} from '../../../services/commons/commons.service';
import {LogErroresService} from '../../../services/commons/log_errores.service';
import * as moment from 'moment';

@Component({
    selector: 'app-log-errores',
    templateUrl: './log-errores.component.html',
    styleUrls: ['./log-errores.component.scss']
})
export class LogErroresComponent extends BaseComponentList implements OnInit {

    public existeConsulta: boolean;
    public loadingIndicator: any;
    public fechaCargue;
    public listaLogs: any;
    public pjjTipo: any;
    public accionLog: any;
    public identificacionOfe: string;
    public ofeSeleccionado: boolean = false;

    @Input() tipoLog: string;
    @Input() set ofeIdentificacion(identificacion: string) {
        if (identificacion == '830076778') {
            this.listaLogs = [
                {label: 'DOCUMENTOS', value: ''},
                {label: 'INTEGRACION-OPENCOMEX', value: 'INTEGRACION-OPENCOMEX'}
            ];
        } else if(identificacion == '860502609') {
            this.listaLogs = [
                {label: 'DOCUMENTOS', value: ''},
                {label: 'PICKUP-CASH', value: 'PICKUP-CASH'}
            ];
        }
        this.identificacionOfe = identificacion;
    }

    get ofeIdentificacion(): string {
        return this.identificacionOfe;
    }

    /**
     * Crea una instancia de LogErroresComponent.
     * 
     * @param {CommonsService} _commonsService
     * @param {LogErroresService} _logErroresService
     * @memberof LogErroresComponent
     */
    constructor(
        private _commonsService   : CommonsService,
        private _logErroresService: LogErroresService
    ) {
        super();
        this.rows = [];
        this.initDataSort('');
        this.loadingIndicator = true;
        this.ordenDireccion = 'ASC';
        this.pjjTipo = '';
        this.accionLog = 'registro_documentos';
    }

    /**
     * ngOnInit de LogErroresComponent.
     *
     * @memberof LogErroresComponent
     */
    ngOnInit() {
        if (this.tipoLog == 'ADQ') {
            this.consultarOfes();
        }
    }

    /**
     * Recarga el datatable con la información filtrada.
     *
     * @memberof LogErroresComponent
     */
    searchDocumentos(): void {
        this.loadLogErrores();
    }

    /**
     * Permite consultar los OFEs.
     * 
     * @memberof LogErroresComponent
     */
    private consultarOfes() {
        this.loading(true);
        this._commonsService.getDataInitForBuild('tat=false').subscribe(
            result => {
                result.data.ofes.forEach(ofe => {
                    ofe.ofe_identificacion = ofe.ofe_identificacion;
                    if (ofe.ofe_identificacion == '860502609') {
                        this.ofeSeleccionado = true;
                        this.listaLogs = [
                            {label: 'EXCEL', value: 'ADQ'},
                            {label: 'INTEGRACION', value: 'XML-ADQUIRENTES'},
                            {label: 'OPENCOMEX', value: 'ADQ-OPENCOMEX'}
                        ];
                    }
                });
                this.loading(false);
            }, error => {
                const texto_errores = this.parseError(error);
                this.loading(false);
                this.showError(texto_errores, 'error', 'Error al cargar los OFEs', 'Ok', 'btn btn-danger');
            }
        );
    }

    /**
     * Se encarga de traer la data de los Errores.
     *
     * @memberof LogErroresComponent
     */
    public loadLogErrores(): void {
        this.loading(true);
        this.selected = [];
        this.rows = [];
        let parameters = this.getSearchParametersObject();
        this._logErroresService.getListaLogErrores(parameters).subscribe(
            res => {
                this.loading(false);
                this.rows = res.data;
                this.totalElements = res.filtrados;
                this.loadingIndicator = false;
                this.totalShow = this.length !== -1 ? this.length : this.totalElements;
                if(this.rows.length > 0) {
                    this.existeConsulta = true;
                } else {
                    this.existeConsulta = false;
                }
            },
            error => {
                this.loading(false);
                this.existeConsulta = false;
                const texto_errores = this.parseError(error);
                this.loadingIndicator = false;
                this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al consultar el log de errores', 'OK', 'btn btn-warning', null, null);
            });
    }

    /**
     * Exporta la consulta en pantalla a Excel
     * El proceso tiene en cuenta si existen filtros aplicados.
     *
     * @memberof LogErroresComponent
     */
    descargarExcel() {
        this.loading(true);
        let parameters = this.getSearchParametersObject(true);
        this._logErroresService.descargarExcel(parameters).subscribe(
            response => {
                this.loading(false);
            },
            (error) => {
                this.loading(false);
                this.showError('<h3>Verifique que la consulta tenga resultados.</h3>', 'error', 'Error en la descarga', 'OK', 'btn btn-danger');
            }
        );

    }

    /**
     * Retorna un objeto con los parámetros para la consulta.
     *
     * @param {*} [descargarExcel=null]
     * @return {*} 
     * @memberof LogErroresComponent
     */
    public getSearchParametersObject(descargarExcel = null) {
        let excel = false;
        if(descargarExcel){
            excel = true;
        }
        let fecha = this.fechaCargue  && this.fechaCargue.value !== '' ? String(moment(this.fechaCargue).format("Y-MM-DD")) : '';
        let tipo = (this.tipoLog) ? this.tipoLog : '';

        return {
            'start': this.start,
            'length': this.length,
            'buscar': this.buscar,
            'columnaOrden': this.columnaOrden,
            'ordenDireccion': this.ordenDireccion,
            'fechaCargue': fecha,
            'tipoLog': tipo,
            'pjjTipo': this.accionLog == "registro_eventos" ? 'REGEVENTOS' : this.pjjTipo,
            'excel': excel
        };
    }

    /**
     * Gestiona el evento de paginación de la grid.
     * 
     * @param $evt Event
     * @memberof LogErroresComponent
     */
    public onPage($evt) {
        this.page = $evt.offset;
        this.selected = [];
        this.start = $evt.offset * this.length;
        this.getData();
    }

    /**
     * Sobreescritura del método onSort.
     * 
     * @param $evt Event
     * @memberof LogErroresComponent
     */
    public onSort($evt) {
        this.selected = [];
        const column = $evt.column.prop;
        this.columnaOrden = column;
        this.ordenDireccion = $evt.newValue.toUpperCase();
        this.getData();
    }

    /**
     * Efectua la carga de datos.
     * 
     * @memberof LogErroresComponent
     */
    public getData() {
        this.loadingIndicator = true;
        this.loadLogErrores();
    }

    /**
     * Cambia el numero de items a mostrar y refresca la grid.
     * 
     * @param evt Event
     * @memberof LogErroresComponent
     */
    paginar(evt) {
        this.length = evt;
        this.getData();
    }

    /**
     * Evento de select all del checkbox primario de la grid.
     *
     * @param {*} { selected }
     * @memberof LogErroresComponent
     */
    onSelect({ selected }) {
        this.selected.splice(0, this.selected.length);
        this.selected.push(...selected);
    }

    /**
     * Evento de paginacion en el listado principal.
     *
     * @memberof LogErroresComponent
     */
    onChangePagination() {
        this.getData();
    }

    /**
     * Evento de busqueda rapida.
     * 
     * @memberof LogErroresComponent
     */
    searchinline() {
        this.getData();
    }
}
