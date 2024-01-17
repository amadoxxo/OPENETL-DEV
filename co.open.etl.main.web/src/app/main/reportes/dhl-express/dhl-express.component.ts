import {Component, OnInit} from '@angular/core';
import {BaseComponentList} from 'app/main/core/base_component_list';
import {AbstractControl, FormGroup, FormBuilder} from '@angular/forms';
import {ConfiguracionService} from '../../../services/configuracion/configuracion.service';
import {DhlExpressService} from '../../../services/reportes/dhl_express.service';
import {Auth} from '../../../services/auth/auth.service';
import {CommonsService} from '../../../services/commons/commons.service';
import * as moment from 'moment';
import {TrackingColumnInterface, TrackingOptionsInterface, TrackingInterface} from '../../commons/open-tracking/tracking-interface';

class Parameters {
    cdo_fecha_envio_desde: string;
    cdo_fecha_envio_hasta: string;
    cdo_fecha_desde      : string;
    cdo_fecha_hasta      : string;
    tipo_reporte         : string;
    ofe_id               : number;
    adq_id?              : number;
    cdo_clasificacion?   : string;
    estado?              : string;
    estado_dian?         : string;
    cdo_origen?          : string;
    cdo_consecutivo?     : string;
    cdo_lote?            : string;
    rfa_prefijo?         : string;
    cdo_acuse_recibo?    : string;
    cdo_resultado_ws_crt?: string;
    excel?               : boolean;
    ofe_filtro?          : string;
    ofe_filtro_buscar?   : string;
}

class ParametersPickupCash {
    apc_cargue_desde: string;
    apc_cargue_hasta: string;
    tipo_reporte    : string;
    campo_buscar?   : string;
    valor_buscar?   : string;
    excel?          : boolean;
}

@Component({
    selector: 'app-dhl-express',
    templateUrl: './dhl-express.component.html',
    styleUrls: ['./dhl-express.component.scss']
})
export class DhlExpressComponent extends BaseComponentList implements OnInit, TrackingInterface {
    public parameters: Parameters;
    public parametersPickupCash: ParametersPickupCash;
    public form: FormGroup;
    public formArchivoEntradaPickupCash: FormGroup;
    public aclsUsuario: any;

    public arrTiposReporte: Array<Object> = [
        {id: 'sin_envio', name: 'Reporte Documentos Sin Envío'},
        {id: 'enviados', name: 'Reporte Documentos Enviados'},
        {id: 'facturacion_manual_pickup_cash', name: 'Reporte Facturación Manual y Pickup Cash'},
        {id: 'archivo_entrada_pickup_cash', name: 'Reporte Archivo Entrada Pickup Cash'}
    ];

    public arrOrigen: Array<Object> = [
        {id: 'MANUAL', name: 'MANUAL'},
        {id: 'INTEGRACION', name: 'INTEGRACIÓN'}
    ];

    public arrTipoDoc: Array<Object> = [
        {id: 'FC', name: 'FC'},
        {id: 'NC', name: 'NC'},
        {id: 'ND', name: 'ND'}
    ];

    public arrEstadoDoc: Array<Object> = [
        {id: 'ACTIVO', name: 'ACTIVO'},
        {id: 'INACTIVO', name: 'INACTIVO'}
    ];

    public arrEstadoDian: Array<Object> = [
        {id: 'aprobado', name: 'APROBADO'},
        {id: 'aprobado_con_notificacion', name: 'APROBADO CON NOTIFICACION'},
        {id: 'rechazado', name: 'RECHAZADO'}
    ];

    public tipoReporte          : any = undefined;
    public filtrosOfe           : Array<Object> = [];
    public accionesBloque       : Array<Object> = [];
    public ofe_id               : AbstractControl;
    public adq_id               : AbstractControl;
    public cdo_fecha_desde      : AbstractControl;
    public cdo_fecha_hasta      : AbstractControl;
    public cdo_fecha_envio_desde: AbstractControl;
    public cdo_fecha_envio_hasta: AbstractControl;
    public cdo_origen           : AbstractControl;
    public cdo_clasificacion    : AbstractControl;
    public estado               : AbstractControl;
    public estado_dian          : AbstractControl;
    public cdo_lote             : AbstractControl;
    public cdo_consecutivo      : AbstractControl;
    public rfa_prefijo          : AbstractControl;
    public cdo_acuse_recibo     : AbstractControl;
    public cdo_resultado_ws_crt : AbstractControl;
    public ofe_filtro           : AbstractControl;
    public ofe_filtro_buscar    : AbstractControl;

    public mostrarOfeFiltros: boolean = false;
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

    public apc_cargue_desde: AbstractControl;
    public apc_cargue_hasta: AbstractControl;
    public filtro_adicional: AbstractControl;
    public valor_buscar    : AbstractControl;

    public filtrosAdicionalesPickupCash: Array<Object> = [
        {id: 'guia', name: 'GUIA'},
        {id: 'cuenta', name: 'CUENTA'}
    ];

    /**
     * Constructor.
     *
     * @param _auth
     * @param _fb
     * @param _configuracionService
     * @param _documentosSinEnvioService
     */
    constructor(
        public _auth: Auth,
        private fb: FormBuilder,
        private _commonsService: CommonsService,
        private _configuracionService: ConfiguracionService,
        private _dhlExpressService: DhlExpressService
    ) {
        super();
        this.rows                 = [];
        this.parameters           = new Parameters();
        this.parametersPickupCash = new ParametersPickupCash();
        this.aclsUsuario          = this._auth.getAcls();
        this.trackingInterface    = this;
        this.init();
    }

    private init() {
        this.loadingIndicator = true;
        this.form = this.fb.group({
            ofe_id: this.requerido(),
            cdo_fecha_envio_desde: this.requerido(),
            cdo_fecha_envio_hasta: this.requerido(),
            cdo_fecha_desde: [''],
            cdo_fecha_hasta: [''],
            adq_id: [''],
            cdo_origen: [''],
            cdo_consecutivo: [''],
            cdo_clasificacion: [''],
            estado: [''],
            estado_dian: [''],
            cdo_lote: [''],
            rfa_prefijo: [''],
            cdo_acuse_recibo: [''],
            cdo_resultado_ws_crt: [''],
            ofe_filtro: [''],
            ofe_filtro_buscar: ['']
        });

        this.ofe_id                = this.form.controls['ofe_id'];
        this.cdo_fecha_envio_desde = this.form.controls['cdo_fecha_envio_desde'];
        this.cdo_fecha_envio_hasta = this.form.controls['cdo_fecha_envio_hasta'];
        this.cdo_fecha_desde       = this.form.controls['cdo_fecha_desde'];
        this.cdo_fecha_hasta       = this.form.controls['cdo_fecha_hasta'];
        this.adq_id                = this.form.controls['adq_id'];
        this.cdo_origen            = this.form.controls['cdo_origen'];
        this.cdo_clasificacion     = this.form.controls['cdo_clasificacion'];
        this.estado                = this.form.controls['estado'];
        this.estado_dian           = this.form.controls['estado_dian'];
        this.cdo_lote              = this.form.controls['cdo_lote'];
        this.rfa_prefijo           = this.form.controls['rfa_prefijo'];
        this.cdo_consecutivo       = this.form.controls['cdo_consecutivo'];
        this.cdo_acuse_recibo      = this.form.controls['cdo_acuse_recibo'];
        this.cdo_resultado_ws_crt  = this.form.controls['cdo_resultado_ws_crt'];
        this.ofe_filtro            = this.form.controls['ofe_filtro'];
        this.ofe_filtro_buscar     = this.form.controls['ofe_filtro_buscar'];
        this.form.controls['estado'].setValue('ACTIVO');

        this.formArchivoEntradaPickupCash = this.fb.group({
            apc_cargue_desde: this.requerido(),
            apc_cargue_hasta: this.requerido(),
            filtro_adicional: [''],
            valor_buscar    : ['']
        });

        this.apc_cargue_desde = this.formArchivoEntradaPickupCash.controls['apc_cargue_desde'];
        this.apc_cargue_hasta = this.formArchivoEntradaPickupCash.controls['apc_cargue_hasta'];
        this.filtro_adicional = this.formArchivoEntradaPickupCash.controls['filtro_adicional'];
        this.valor_buscar     = this.formArchivoEntradaPickupCash.controls['valor_buscar'];
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

        return query;
    }

    /**
     * Crea un JSON con los parámetros de búsqueda cuando el tipo de reporte es diferente de archivo_entrada_pickup_cash.
     *
     */
    public getSearchParametersObject(excel: boolean = false) {
        const fecha_envio_desde = this.cdo_fecha_envio_desde && this.cdo_fecha_envio_desde.value !== '' ? String(moment(this.cdo_fecha_envio_desde.value).format('YYYY-MM-DD')) : '';
        const fecha_envio_hasta = this.cdo_fecha_envio_hasta && this.cdo_fecha_envio_hasta.value !== '' ? String(moment(this.cdo_fecha_envio_hasta.value).format('YYYY-MM-DD')) : '';
        const fecha_desde = this.cdo_fecha_desde && this.cdo_fecha_desde.value !== '' && this.cdo_fecha_desde.value != undefined ? String(moment(this.cdo_fecha_desde.value).format('YYYY-MM-DD')) : '';
        const fecha_hasta = this.cdo_fecha_hasta && this.cdo_fecha_hasta.value !== '' && this.cdo_fecha_desde.value != undefined ? String(moment(this.cdo_fecha_hasta.value).format('YYYY-MM-DD')) : '';

        this.parameters.cdo_fecha_envio_desde = fecha_envio_desde;
        this.parameters.cdo_fecha_envio_hasta = fecha_envio_hasta;

        if (this.tipoReporte)
            this.parameters.tipo_reporte = this.tipoReporte;
        else
            delete this.parameters.tipo_reporte;
        if (fecha_desde)
            this.parameters.cdo_fecha_desde = fecha_desde;
        else
            delete this.parameters.cdo_fecha_desde;
        if (fecha_hasta)
            this.parameters.cdo_fecha_hasta = fecha_hasta;
        else
            delete this.parameters.cdo_fecha_hasta;
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
        if(this.estado_dian && this.estado_dian.value)
            this.parameters.estado_dian = this.estado_dian.value;
        else 
            delete this.parameters.estado_dian; 
        if (this.cdo_lote && this.cdo_lote.value)
            this.parameters.cdo_lote = this.cdo_lote.value;
        else
            delete this.parameters.cdo_lote;
        if (this.rfa_prefijo && this.rfa_prefijo.value.trim())
            this.parameters.rfa_prefijo = this.rfa_prefijo.value;
        else
            delete this.parameters.rfa_prefijo;
        if (this.cdo_acuse_recibo && this.cdo_acuse_recibo.value)
            this.parameters.cdo_acuse_recibo = this.cdo_acuse_recibo.value;
        else
            delete this.parameters.cdo_acuse_recibo;
        if (this.cdo_resultado_ws_crt && this.cdo_resultado_ws_crt.value)
            this.parameters.cdo_resultado_ws_crt = this.cdo_resultado_ws_crt.value;
        else
            delete this.parameters.cdo_resultado_ws_crt;
        if (excel)
            this.parameters.excel = true;
        else
            delete this.parameters.excel;
        if (this.ofe_filtro && this.ofe_filtro.value && this.ofe_filtro_buscar && this.ofe_filtro_buscar.value) {
            this.parameters.ofe_filtro = this.ofe_filtro.value;
            this.parameters.ofe_filtro_buscar = this.ofe_filtro_buscar.value;
        } else {
            delete this.parameters.ofe_filtro;
            delete this.parameters.ofe_filtro_buscar;
        }

        return this.parameters;
    }

    /**
     * Crea un JSON con los parámetros de búsqueda cuando el tipo de reporte es igual de archivo_entrada_pickup_cash.
     *
     */
    public getSearchParametersPickupCash(excel: boolean = false) {
        const fecha_desde = this.apc_cargue_desde && this.apc_cargue_desde.value !== '' && this.apc_cargue_desde.value != undefined ? String(moment(this.apc_cargue_desde.value).format('YYYY-MM-DD')) : '';
        const fecha_hasta = this.apc_cargue_hasta && this.apc_cargue_hasta.value !== '' && this.apc_cargue_desde.value != undefined ? String(moment(this.apc_cargue_hasta.value).format('YYYY-MM-DD')) : '';

        this.parametersPickupCash.apc_cargue_desde = fecha_desde;
        this.parametersPickupCash.apc_cargue_hasta = fecha_hasta;

        if (this.tipoReporte)
            this.parametersPickupCash.tipo_reporte = this.tipoReporte;
        else
            delete this.parametersPickupCash.tipo_reporte;
        
        if (this.filtro_adicional && this.filtro_adicional.value && this.valor_buscar && this.valor_buscar.value) {
            this.parametersPickupCash.campo_buscar = this.filtro_adicional.value;
            this.parametersPickupCash.valor_buscar = this.valor_buscar.value;
        } else {
            delete this.parametersPickupCash.campo_buscar;
            delete this.parametersPickupCash.valor_buscar;
        }

        if (excel)
            this.parametersPickupCash.excel = true;
        else
            delete this.parametersPickupCash.excel;

        return this.parametersPickupCash;
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
        this.ofes = ofes.data.ofes;

        this.consultarReportesDescargar();
    }

    /**
     * Consulta los reportes del día que el usuario autenticado puede descargar
     */
    async consultarReportesDescargar() {
        this.loading(true);
        this.archivos = [];

        let consultaReportes = await this._dhlExpressService.listarReportesDescargar(this.getSearchParametersInline()).toPromise().catch(error => {
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
        let parametros;
        if(this.tipoReporte === 'archivo_entrada_pickup_cash') {
            parametros = this.getSearchParametersPickupCash(true);
        } else {
            parametros = this.getSearchParametersObject(true);
        }

        this._dhlExpressService.agendarReporteExcel(parametros).subscribe(
            response => {
                this.loading(false);
                this.showSuccess('<strong>' + response.message + '<br><br>El archivo se listará cuando el mismo sea generado por el sistema.</strong>', 'success', 'Generar Excel', 'Ok, entiendo', 'btn btn-success');
                this.consultarReportesDescargar();
            },
            (error) => {
                this.loading(false);
                this.showError('<h3>Error en descarga</h3>', 'error', 'Error al generar el Excel', 'OK', 'btn btn-danger');
            }
        );
    }

    /**
     * Monitoriza cuando el valor del select de OFEs cambia.
     *
     */
    ofeHasChanged(ofe) {
        this.ofe_filtro.setValue('');
        this.ofe_filtro_buscar.setValue('');
        this.mostrarOfeFiltros = false;
        // Si un OFE tiene filtros configurados se deben cargar
        // y mostar los campos correspondientes en el formulario
        if (!this.isEmpty(ofe.ofe_filtros) && ofe.ofe_filtros && ofe.ofe_filtros !== 'null') {
            this.filtrosOfe = Object.keys(ofe.ofe_filtros)
                .map(key => ({id: key, name: ofe.ofe_filtros[key]}));
            this.mostrarOfeFiltros = true;
        }
    }

    /**
     * De acuerdo al valor seleccionado en el combo ofe_fitros
     * limpia el valor del campo ofe_filtro_buscar
     *
     * @param {string} value
     * @memberof DocumentosEnviadosComponent
     */
    actualizaFiltroOfe(value) {
        if (!value || value == '')
            this.ofe_filtro_buscar.setValue('');
    }

    /**
     * Verifica los campos mínimos que deben estar diligenciados en el formulario para poder procesar la información
     *
     * @returns boolean
     * @memberof DhlExpressComponent
     */
    validarCamposMinimos() {
        if(
            this.tipoReporte === 'sin_envio' &&
            this.ofe_id.value !== '' && this.ofe_id.value !== undefined &&
            this.cdo_fecha_desde.value !== '' && this.cdo_fecha_desde.value !== undefined && this.cdo_fecha_desde.value !== null &&
            this.cdo_fecha_hasta.value !== '' && this.cdo_fecha_hasta.value !== undefined && this.cdo_fecha_hasta.value !== null
        ) {
            return true;
        } else if(
            (
                this.tipoReporte === 'enviados' ||
                this.tipoReporte === 'facturacion_manual_pickup_cash'
            ) &&
            this.ofe_id.value !== '' && this.ofe_id.value !== undefined &&
            this.cdo_fecha_envio_desde.value !== '' && this.cdo_fecha_envio_desde.value !== undefined && this.cdo_fecha_envio_desde.value !== null &&
            this.cdo_fecha_envio_hasta.value !== '' && this.cdo_fecha_envio_hasta.value !== undefined && this.cdo_fecha_envio_hasta.value !== null
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
        this._dhlExpressService.descargarExcel({'pjj_id': pjjId}).subscribe(
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