import { Router } from '@angular/router';
import {Component, OnInit} from '@angular/core';
import {BaseComponentList} from 'app/main/core/base_component_list';
import {AbstractControl, FormGroup, FormBuilder} from '@angular/forms';
import {DocumentosProcesadosService} from '../../../services/reportes/documentos_procesados.service';
import {Auth} from '../../../services/auth/auth.service';
import {CommonsService} from '../../../services/commons/commons.service';
import * as moment from 'moment';
import {TrackingColumnInterface, TrackingOptionsInterface, TrackingInterface} from '../../commons/open-tracking/tracking-interface';

class Parameters {
    fecha_creacion_desde : string;
    fecha_creacion_hasta : string;
    ofe_id               : number;
    adq_id?              : number;
    cdo_clasificacion?   : string;
    proceso?             : string;
    estado?              : string;
    cdo_origen?          : string;
    cdo_consecutivo?     : string;
    cdo_lote?            : string;
    rfa_prefijo?         : string;
    tipo_reporte?        : string;
}

@Component({
    selector: 'app-documentos-procesados',
    templateUrl: './documentos-procesados.component.html',
    styleUrls: ['./documentos-procesados.component.scss']
})
export class DocumentosProcesadosComponent extends BaseComponentList implements OnInit, TrackingInterface {
    public parameters: Parameters;
    public form: FormGroup;
    public aclsUsuario: any;
    public arrOrigen: Array<Object> = [
        {id: 'MANUAL', name: 'MANUAL'},
        {id: 'INTEGRACION', name: 'INTEGRACIÓN'}
    ];
    public arrTipoDoc: Array<Object> = [];
    public arrEstadoDoc: Array<Object> = [
        {id: 'ACTIVO', name: 'ACTIVO'},
        {id: 'INACTIVO', name: 'INACTIVO'}
    ];

    public ofe_id              : AbstractControl;
    public adq_id              : AbstractControl;
    public fecha_creacion_desde: AbstractControl;
    public fecha_creacion_hasta: AbstractControl;
    public cdo_origen          : AbstractControl;
    public cdo_clasificacion   : AbstractControl;
    public estado              : AbstractControl;
    public cdo_lote            : AbstractControl;
    public cdo_consecutivo     : AbstractControl;
    public rfa_prefijo         : AbstractControl;
    public tipo_reporte        : AbstractControl;
    public tituloHeader        : string = '';
    public proceso             : string = '';
    public mostarOpcionEventos : boolean;

    public ofes: Array<any> = [];
    public archivos: any [] = [];

    public trackingInterface: TrackingInterface;

    public columns: TrackingColumnInterface[] = [
        {name: 'Fecha de Generación', prop: 'fecha', sorteable: false, width: 130},
        {name: 'Archivo', prop: 'archivo_reporte', sorteable: false, width: 600}
    ];

    public trackingOpciones: TrackingOptionsInterface = {
        showButton    : false,
        editButton    : false,
        deleteButton  : false,
        downloadButton: true
    };

    /**
     * Constructor.
     *
     * @param _auth
     * @param _fb
     * @param {Router} _router
     * @param _documentosSinEnvioService
     */
    constructor(
        public _auth: Auth,
        private fb: FormBuilder,
        private _router: Router,
        private _commonsService: CommonsService,
        private _documentosProcesadosService: DocumentosProcesadosService
    ) {
        super();
        this.rows = [];
        this._documentosProcesadosService.setSlug = "emision";
        this.parameters = new Parameters();
        this.aclsUsuario = this._auth.getAcls();
        this.trackingInterface = this;
        this.init();
    }

    private init() {
        this.loadingIndicator = true;
        this.form = this.fb.group({
            ofe_id              : this.requerido(),
            fecha_creacion_desde: this.requerido(),
            fecha_creacion_hasta: this.requerido(),
            adq_id              : [''],
            cdo_origen          : [''],
            cdo_consecutivo     : [''],
            cdo_clasificacion   : [''],
            estado              : [''],
            cdo_lote            : [''],
            rfa_prefijo         : [''],
            tipo_reporte        : this.requerido()
        });
        
        this.ofe_id               = this.form.controls['ofe_id'];
        this.fecha_creacion_desde = this.form.controls['fecha_creacion_desde'];
        this.fecha_creacion_hasta = this.form.controls['fecha_creacion_hasta'];
        this.adq_id               = this.form.controls['adq_id'];
        this.cdo_origen           = this.form.controls['cdo_origen'];
        this.cdo_clasificacion    = this.form.controls['cdo_clasificacion'];
        this.estado               = this.form.controls['estado'];
        this.cdo_lote             = this.form.controls['cdo_lote'];
        this.rfa_prefijo          = this.form.controls['rfa_prefijo'];
        this.cdo_consecutivo      = this.form.controls['cdo_consecutivo'];
        this.tipo_reporte         = this.form.controls['tipo_reporte'];

        if (this._router.url == '/emision/reportes/documentos-procesados') {
            this.arrTipoDoc   = [{id: 'FC', name: 'FC'}, {id: 'NC', name: 'NC'}, {id: 'ND', name: 'ND'}];
            this.tituloHeader = 'Emisión';
            this.proceso      = 'emision';
        } else if (this._router.url == '/documento-soporte/reportes/documentos-procesados') {
            this.arrTipoDoc   = [{id: 'DS', name: 'DS'}, {id: 'DS_NC', name: 'DS_NC'}];
            this.tituloHeader = 'Documento Soporte';
        }

        this.tipo_reporte.setValue('documentos_procesados');
    }

    /**
     * Sobreescribe los parámetros de búsqueda inline - (Get).
     * 
     */
     getSearchParametersInline(): string {
        let query = 'start=' + this.start + '&' +
        'length=' + this.length + '&' +
        'buscar=' + this.buscar + '&' +
        'columnaOrden=' + this.columnaOrden + '&' +
        'ordenDireccion=' + this.ordenDireccion;

        if (this.proceso != '')
            query += '&proceso=' + this.proceso;

        if (this.tipo_reporte && this.tipo_reporte.value != '')
            query += '&tipoReporte=' + this.tipo_reporte.value;

        return query;
    }

    /**
     * Crea un JSON con los parámetros de búsqueda.
     *
     */
    public getSearchParametersObject() {
        const fecha_creacion_desde = this.fecha_creacion_desde && this.fecha_creacion_desde.value !== '' ? String(moment(this.fecha_creacion_desde.value).format('YYYY-MM-DD')) : '';
        const fecha_creacion_hasta = this.fecha_creacion_hasta && this.fecha_creacion_hasta.value !== '' ? String(moment(this.fecha_creacion_hasta.value).format('YYYY-MM-DD')) : '';

        this.parameters.fecha_creacion_desde = fecha_creacion_desde;
        this.parameters.fecha_creacion_hasta = fecha_creacion_hasta;

        if (this.adq_id && this.adq_id.value)
            this.parameters.adq_id = this.adq_id.value;
        else
            delete this.parameters.adq_id;
        if (this.ofe_id && this.ofe_id.value)
            this.parameters.ofe_id = this.ofe_id.value;
        if (this.cdo_origen && this.cdo_origen.value)
            this.parameters.cdo_origen = this.cdo_origen.value;
        else
            delete this.parameters.cdo_origen;
        if (this.cdo_consecutivo && this.cdo_consecutivo.value.trim())
            this.parameters.cdo_consecutivo = this.cdo_consecutivo.value;
        else
            delete this.parameters.cdo_consecutivo;
        if (this.cdo_clasificacion && this.cdo_clasificacion.value)
            this.parameters.cdo_clasificacion = this.cdo_clasificacion.value;
        else
            delete this.parameters.cdo_clasificacion;
        if(this.estado && this.estado.value)
            this.parameters.estado = this.estado.value;
        else 
            delete this.parameters.estado; 
        if (this.cdo_lote && this.cdo_lote.value)
            this.parameters.cdo_lote = this.cdo_lote.value;
        else
            delete this.parameters.cdo_lote;
        if (this.rfa_prefijo && this.rfa_prefijo.value.trim())
            this.parameters.rfa_prefijo = this.rfa_prefijo.value;
        else
            delete this.parameters.rfa_prefijo;

        if (this._router.url == '/emision/reportes/documentos-procesados') {
            this.parameters.proceso = 'emision';
        } else if (this._router.url == '/documento-soporte/reportes/documentos-procesados') {
            this.parameters.proceso = 'documento_soporte';
        }

        if (this.tipo_reporte && this.tipo_reporte.value)
            this.parameters.tipo_reporte = this.tipo_reporte.value;
        else
            delete this.parameters.tipo_reporte;

        return this.parameters;
    }

    ngOnInit() {
        this.initDataSort('fecha_creacion');
        this.loadingIndicator = true;
        this.ordenDireccion = 'DESC';
        this.adq_id.disable();
        this.cargarOfes();
    }

    /**
     * Carga los OFEs en el select de emisores.
     *
     */
    async cargarOfes() {
        this.loading(true);
        let ofes = await this._commonsService.getDataInitForBuild('tat=false').toPromise().catch(error => {
            let texto_errores = this.parseError(error);
            this.loading(false);
            this.showError(texto_errores, 'error', 'Error al cargar los OFEs', 'Ok', 'btn btn-danger');
        });

        this.loading(false);
        this.ofes = [];
        this.mostarOpcionEventos = false;
        ofes.data.ofes.map(ofe => {
            if(ofe.ofe_emision === 'SI') {
                ofe.ofe_identificacion_ofe_razon_social = ofe.ofe_identificacion + ' - ' + ofe.ofe_razon_social;
                this.ofes.push(ofe);

                if (ofe.ofe_emision_eventos_contratados_titulo_valor) {
                    ofe.ofe_emision_eventos_contratados_titulo_valor.find(e => {
                        if (e.evento === 'ACEPTACIONT') {
                            this.mostarOpcionEventos = true;   
                        }
                    });
                }
            }
        });

        this.consultarReportesDescargar();
    }

    /**
     * Consulta los reportes del día que el usuario autenticado puede descargar
     */
    async consultarReportesDescargar() {
        this.loading(true);
        this.archivos = [];

        let consultaReportes = await this._documentosProcesadosService.listarReportesDescargar(this.getSearchParametersInline()).toPromise().catch(error => {
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
                        'descargar'      : reg.archivo_reporte != '' ? true : false
                    }
                );
            });
            this.totalElements = consultaReportes.filtrados;
            this.loadingIndicator = false;
            this.totalShow = this.length !== -1 ? this.length : this.totalElements;
        }
    }

    /**
     * Programa la generación de un Excel de los documentos filtrados.
     *
     */
    generarExcel() {
        this.loading(true);
        this._documentosProcesadosService.agendarReporteExcel(this.getSearchParametersObject()).subscribe(
            response => {
                this.loading(false);
                this.showSuccess('<strong>' + response.message + '<br><br>El archivo se listará cuando el mismo sea generado por el sistema.</strong>', 'success', 'Generar Excel', 'Ok, entiendo', 'btn btn-success');
                this.consultarReportesDescargar();
            },
            (error) => {
                this.loading(false);
                this.showError('<h3>Error en procesamiento</h3>', 'error', 'Error al programar la generación del Excel', 'OK', 'btn btn-danger');
            }
        );
    }

    /**
     * Verifica los campos mínimos que deben estar diligenciados en el formulario para poder procesar la información
     *
     * @returns boolean
     * @memberof DocumentosProcesadosComponent
     */
    validarCamposMinimos() {
        if(
            this.ofe_id.value !== '' && this.ofe_id.value !== undefined &&
            this.fecha_creacion_desde.value !== '' && this.fecha_creacion_desde.value !== undefined && this.fecha_creacion_desde.value !== null &&
            this.fecha_creacion_hasta.value !== '' && this.fecha_creacion_hasta.value !== undefined && this.fecha_creacion_hasta.value !== null
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Programa la generación de un Excel de los documentos filtrados.
     *
     */
    descargarExcel(pjjId) {
        this.loading(true);
        this._documentosProcesadosService.descargarExcel({'pjj_id': pjjId}).subscribe(
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
     */
    public onPage($evt) {
        this.page = $evt.offset;
        this.start = $evt.offset * this.length;
        this.consultarReportesDescargar();
    }

    /**
     * Implementación de los métodos de TrackingInterface
     */

    onDownloadItem(item: any) {
        this.descargarExcel(item.pjj_id);
    }

    onSearchInline(buscar: string) {
        this.start = 0;
        this.buscar = buscar;
        this.consultarReportesDescargar();
    }

    onChangeSizePage(size: number) {
        this.start = 0;
        this.page = 1;
        this.length = size;
        this.consultarReportesDescargar();
    }

    onOrderBy(column: string, $order: string) {
        //
    }

    onOptionMultipleSelected(opcion: any, selected: any[]) {
        //
    }

    onViewItem(item: any) {
        //
    }

    onRequestDeleteItem(item: any) {
        //
    }

    onEditItem(item: any) {
        //
    }

    onConfigurarDocumentoElectronico(item: any) {
        //
    }
}