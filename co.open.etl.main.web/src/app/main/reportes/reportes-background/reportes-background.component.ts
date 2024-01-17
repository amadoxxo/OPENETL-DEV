import { Component, OnInit } from '@angular/core';
import { Router } from '@angular/router';
import { BaseComponentList } from 'app/main/core/base_component_list';
import { Auth } from '../../../services/auth/auth.service';
import { TrackingColumnInterface, TrackingOptionsInterface, TrackingInterface } from '../../commons/open-tracking/tracking-interface';
import { ReportesBackgroundService } from '../../../services/reportes/reportes_background.service';

@Component({
    selector: 'app-reportes-background',
    templateUrl: './reportes-background.component.html',
    styleUrls: ['./reportes-background.component.scss']
})
export class ReportesBackgroundComponent extends BaseComponentList implements OnInit, TrackingInterface {
    
    public aclsUsuario      : any;
    public archivos         : any [] = [];
    public ofes             : Array<any> = [];
    public tipoReporte      : string = '';
    public proceso          : string = '';
    public moduloTitulo     : string = '';
    public trackingInterface: TrackingInterface;

    public columns: TrackingColumnInterface[] = [
        {name: 'Fecha de Generación', prop: 'fecha', sorteable: false, width: 100},
        {name: 'Archivo', prop: 'archivo_reporte', sorteable: false, width: 430},
        {name: 'Usuario', prop: 'usuario', sorteable: false, width: 100}
    ];

    public trackingOpciones: TrackingOptionsInterface = {
        showButton          : false,
        editButton          : false,
        deleteButton        : false,
        downloadButton      : true,
        unableDownloadButton: true
    };

    /**
     * Constructor de ReportesBackgroundComponent.
     * 
     * @param {Auth} _auth
     * @param {Router} router
     * @param {ReportesBackgroundService} _reportesBackgroundService
     * @memberof ReportesBackgroundComponent
     */
    constructor(
        public _auth: Auth,
        private router: Router,
        private _reportesBackgroundService : ReportesBackgroundService
    ) {
        super();
        this.definirModuloReporte();
        this.rows = [];
        this.aclsUsuario = this._auth.getAcls();
        this.trackingInterface = this;
        this.init();
    }

    /**
     * Inicializa las variables.
     *
     * @private
     * @memberof ReportesBackgroundComponent
     */
    private init() {
        this.loadingIndicator = true;
    }

    ngOnInit() {
        this.initDataSort('fecha_creacion');
        this.loadingIndicator = true;
        this.ordenDireccion = 'DESC';
        this.consultarReportesDescargar();
    }

    /**
     * Define el modulo desde donde se ingresa.
     *
     * @memberof ReportesBackgroundComponent
     * @return {void}
     */
    definirModuloReporte(): void {
        if (this.router.url.indexOf('/emision/reportes/background') !== -1) {
            this.moduloTitulo = 'Emisión';
            this.tipoReporte  = 'emision';
        } else if (this.router.url.indexOf('/documento-soporte/reportes/background') !== -1) {
            this.moduloTitulo = 'Documentos Soporte';
            this.tipoReporte  = 'emision';
            this.proceso      = 'documento_soporte';
        } else if (this.router.url.indexOf('/recepcion/reportes/background') !== -1) {
            this.moduloTitulo = 'Recepción';
            this.tipoReporte  = 'recepcion';
        } else if (this.router.url.indexOf('/nomina-electronica/reportes/background') !== -1) {
            this.moduloTitulo = 'Documento Soporte de Pago de Nómina Electrónica';
            this.tipoReporte  = 'nomina-electronica';
        } else if (this.router.url.indexOf('/radian/registro-documentos/reportes/background') !== -1) {
            this.moduloTitulo = 'Radian';
            this.tipoReporte  = 'radian';
        } else if (this.router.url.indexOf('/configuracion/reportes/background') !== -1) {
            this.moduloTitulo = 'Configuracion';
            this.tipoReporte  = 'commons';
        }
    }

    /**
     * Sobreescribe los parámetros de búsqueda inline - (Get).
     *
     * @return {*}  {string}
     * @memberof ReportesBackgroundComponent
     */
    getSearchParametersInline(): string {
        let query = 'start=' + this.start + '&' +
        'length=' + this.length + '&' +
        'buscar=' + this.buscar + '&' +
        'columnaOrden=' + this.columnaOrden + '&' +
        'ordenDireccion=' + this.ordenDireccion;
        
        if (this.proceso != '')
            query += '&proceso=' + this.proceso;

        return query;
    }

    /**
     * Consulta los reportes del día que el usuario autenticado puede descargar
     *
     * @memberof ReportesBackgroundComponent
     */
    async consultarReportesDescargar() {
        this.loading(true);
        this.archivos = [];
        let consultaReportes = await this._reportesBackgroundService.listarReportesDescargar(this.getSearchParametersInline(), this.tipoReporte).toPromise().catch(error => {
            let texto_errores = this.parseError(error);
            this.loading(false);
            this.showError(texto_errores, 'error', 'Error al consultar los reportes que se pueden descargar', 'Ok', 'btn btn-danger');
        });

        this.loading(false);

        if(consultaReportes !== undefined) {
            consultaReportes.data.forEach(reg => {
                this.archivos.push(
                    {
                        'pjj_id'         : reg.pjj_id,
                        'fecha'          : reg.fecha,
                        'archivo_reporte': (reg.archivo_reporte != '' ? reg.archivo_reporte : (reg.errores != '' ? reg.tipo_reporte + ' - Error: ' + reg.errores : reg.tipo_reporte + ' - ' + reg.estado)),
                        'descargar'      : reg.archivo_reporte != '' ? true : false,
                        'existe_archivo' : reg.existe_archivo,
                        'usuario'        : reg.usuario.usu_identificacion + ' - ' + reg.usuario.usu_nombre
                    }
                );
            });
            this.totalElements = consultaReportes.filtrados;
            this.loadingIndicator = false;
            this.totalShow = this.length !== -1 ? this.length : this.totalElements;
        }
    }

    /**
     * Descarga el reporte en background del registro seleccionado.
     *
     * @memberof ReportesBackgroundComponent
     */
    descargarExcel(pjjId) {
        this.loading(true);
        this._reportesBackgroundService.descargarExcel({'pjj_id': pjjId}, this.tipoReporte).subscribe(
            response => {
                this.loading(false);
            },
            (error) => {
                this.loading(false);
            }
        );
    }

    /**
     * Gestiona el evento de paginación de la grid.
     * 
     * @param $evt
     * @memberof ReportesBackgroundComponent
     */
    public onPage($evt) {
        this.page = $evt.offset;
        this.start = $evt.offset * this.length;
        this.consultarReportesDescargar();
    }

    /**
     * Implementación de los métodos de TrackingInterface.
     * 
     * @memberof ReportesBackgroundComponent
     */
    onDownloadItem(item: any) {
        this.descargarExcel(item.pjj_id);
    }

    /**
     * Ejecuta una busqueda rapida en los registros.
     *
     * @param {string} buscar
     * @memberof ReportesBackgroundComponent
     */
    onSearchInline(buscar: string) {
        this.start = 0;
        this.buscar = buscar;
        this.consultarReportesDescargar();
    }

    /**
     * Cambia la cantidad de registros a visualizar
     *
     * @param {number} size
     * @memberof ReportesBackgroundComponent
     */
    onChangeSizePage(size: number) {
        this.start = 0;
        this.page = 1;
        this.length = size;
        this.consultarReportesDescargar();
    }

    /**
     * Implementación debido a TrackingInterface.
     *
     * @param {string} column
     * @param {string} $order
     * @memberof ReportesBackgroundComponent
     */
    onOrderBy(column: string, $order: string) {
        //
    }

    /**
     * Implementación debido a TrackingInterface.
     *
     * @param {*} opcion
     * @param {any[]} selected
     * @memberof ReportesBackgroundComponent
     */
    onOptionMultipleSelected(opcion: any, selected: any[]) {
        //
    }

    /**
     * Implementación debido a TrackingInterface.
     *
     * @param {*} item
     * @memberof ReportesBackgroundComponent
     */
    onViewItem(item: any) {
        //
    }

    /**
     * Implementación debido a TrackingInterface.
     *
     * @param {*} item
     * @memberof ReportesBackgroundComponent
     */
    onRequestDeleteItem(item: any) {
        //
    }

    /**
     * Implementación debido a TrackingInterface.
     *
     * @param {*} item
     * @memberof ReportesBackgroundComponent
     */
    onEditItem(item: any) {
        //
    }

    /**
     * Implementación debido a TrackingInterface.
     *
     * @param {*} item
     * @memberof ReportesBackgroundComponent
     */
    onConfigurarDocumentoElectronico(item: any) {
        //
    }
}