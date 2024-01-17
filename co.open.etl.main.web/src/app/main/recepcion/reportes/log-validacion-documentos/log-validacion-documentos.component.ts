import {Component, OnInit} from '@angular/core';
import {BaseComponentList} from 'app/main/core/base_component_list';
import {AbstractControl, FormGroup, FormBuilder} from '@angular/forms';
import {Auth} from '../../../../services/auth/auth.service';
import {CommonsService} from '../../../../services/commons/commons.service';
import {TrackingColumnInterface, TrackingOptionsInterface, TrackingInterface} from '../../../commons/open-tracking/tracking-interface';
import {ValidacionDocumentosService} from '../../../../services/recepcion/validacion_documentos.service';
import {BaseService} from '../../../../services/core/base.service';
import {DatosParametricosValidacionService} from '../../../../services/proyectos-especiales/recepcion/fnc/validacion/datos-parametricos-validacion.service';
import * as moment from 'moment';

class Parameters {
    cdo_fecha_desde        : string;
    cdo_fecha_hasta        : string;
    ofe_id                 : number;
    pro_id?                : number;
    cdo_clasificacion?     : string;
    proceso?               : string;
    cdo_origen?            : string;
    cdo_consecutivo?       : string;
    rfa_prefijo?           : string;
    tipo?                  : string;
    campo_validacion?      : string;
    valor_campo_validacion?: string;
}

@Component({
    selector: 'app-log-validacion-documentos',
    templateUrl: './log-validacion-documentos.component.html',
    styleUrls: []
})
export class LogValidacionDocumentosComponent extends BaseComponentList implements OnInit, TrackingInterface {
    public parameters: Parameters;
    public form: FormGroup;
    public aclsUsuario: any;
    public arrOrigen: Array<Object> = [
        {id: 'RPA', name: 'RPA'},
        {id: 'CORREO', name: 'CORREO'},
        {id: 'MANUAL', name: 'MANUAL'},
        {id: 'NO-ELECTRONICO', name: 'NO-ELECTRONICO'}
    ];

    public arrTipoDoc: Array<Object> = [
        {id: 'FC', name: 'FC'}, 
        {id: 'NC', name: 'NC'}, 
        {id: 'ND', name: 'ND'}
    ];

    public ofe_id                : AbstractControl;
    public pro_id                : AbstractControl;
    public cdo_fecha_desde       : AbstractControl;
    public cdo_fecha_hasta       : AbstractControl;
    public cdo_origen            : AbstractControl;
    public cdo_clasificacion     : AbstractControl;
    public cdo_consecutivo       : AbstractControl;
    public rfa_prefijo           : AbstractControl;
    public tipo_reporte          : AbstractControl;
    public campo_validacion      : AbstractControl;
    public valor_campo_validacion: AbstractControl;

    public ofes: Array<any> = [];
    public archivos: any [] = [];

    public trackingInterface: TrackingInterface;

    public columns: TrackingColumnInterface[] = [
        {name: 'Fecha de Generación', prop: 'fecha', sorteable: false, width: 150},
        {name: 'Archivo', prop: 'archivo_reporte', sorteable: false, width: 600}
    ];

    public trackingOpciones: TrackingOptionsInterface = {
        showButton          : false,
        editButton          : false,
        deleteButton        : false,
        downloadButton      : true,
        unableDownloadButton: true
    };

    public tablaDatosParametricosValidacion : string = 'pry_datos_parametricos_validacion';
    public mostrarComboValorCampoValidacion : boolean = false;
    public arrCamposValidacion              : Array<Object> = [];
    public arrValoresCamposValidacion       : Array<Object> = [];

    public ofeRecepcionFncActivo : string = 'NO';
    public ofeRecepcionFncConfiguracion: any [] = [];

    /**
     * Constructor del componente LogValidacionDocumentosComponent.
     * 
     * @param {Auth} _auth
     * @param {FormBuilder} fb
     * @param {BaseService} _baseService
     * @param {CommonsService} _commonsService
     * @param {ValidacionDocumentosService} _validacionDocumentosService
     * @param {DatosParametricosValidacionService} _datosParametricosService
     * @memberof LogValidacionDocumentosComponent
     */
    constructor(
        public _auth                        : Auth,
        private fb                          : FormBuilder,
        private _baseService                : BaseService,
        private _commonsService             : CommonsService,
        private _datosParametricosService   : DatosParametricosValidacionService,
        private _validacionDocumentosService: ValidacionDocumentosService
    ) {
        super();
        this.rows              = [];
        this.parameters        = new Parameters();
        this.aclsUsuario       = this._auth.getAcls();
        this.trackingInterface = this;
        this.init();
    }

    /**
     * Inicializaciones del componente.
     *
     * @private
     * @memberof LogValidacionDocumentosComponent
     */
    private init() {
        this.loadingIndicator = true;
        this.form = this.fb.group({
            ofe_id                : this.requerido(),
            cdo_fecha_desde       : this.requerido(),
            cdo_fecha_hasta       : this.requerido(),
            pro_id                : [''],
            cdo_origen            : [''],
            cdo_consecutivo       : [''],
            cdo_clasificacion     : [''],
            rfa_prefijo           : [''],
            tipo_reporte          : this.requerido(),
            campo_validacion      : [''],
            valor_campo_validacion: ['']
        });
        
        this.ofe_id                 = this.form.controls['ofe_id'];
        this.cdo_fecha_desde        = this.form.controls['cdo_fecha_desde'];
        this.cdo_fecha_hasta        = this.form.controls['cdo_fecha_hasta'];
        this.pro_id                 = this.form.controls['pro_id'];
        this.cdo_origen             = this.form.controls['cdo_origen'];
        this.cdo_clasificacion      = this.form.controls['cdo_clasificacion'];
        this.rfa_prefijo            = this.form.controls['rfa_prefijo'];
        this.cdo_consecutivo        = this.form.controls['cdo_consecutivo'];
        this.tipo_reporte           = this.form.controls['tipo_reporte'];
        this.campo_validacion       = this.form.controls['campo_validacion'];
        this.valor_campo_validacion = this.form.controls['valor_campo_validacion'];

        this.tipo_reporte.setValue('recepcion-log-validacion');
    }

    /**
     * Parámetros para listar reportes generados y poder descargarlos.
     *
     * @return {*}  {string}
     * @memberof LogValidacionDocumentosComponent
     */
    getSearchParametersInline(): string {
        let query = 'start=' + this.start + '&' +
        'length=' + this.length + '&' +
        'buscar=' + this.buscar + '&' +
        'columnaOrden=' + this.columnaOrden + '&' +
        'ordenDireccion=' + this.ordenDireccion + '&' +
        'proceso=recepcion&tipo=RLOGVALIDACION';

        return query;
    }

    /**
     * Crea un objeto JSON con los parámetros necesarios para el agendamiento del reporte en background.
     *
     * @return {*} 
     * @memberof LogValidacionDocumentosComponent
     */
    public getSearchParametersObject() {
        const cdo_fecha_desde = this.cdo_fecha_desde && this.cdo_fecha_desde.value !== '' ? String(moment(this.cdo_fecha_desde.value).format('YYYY-MM-DD')) : '';
        const cdo_fecha_hasta = this.cdo_fecha_hasta && this.cdo_fecha_hasta.value !== '' ? String(moment(this.cdo_fecha_hasta.value).format('YYYY-MM-DD')) : '';

        this.parameters.cdo_fecha_desde = cdo_fecha_desde;
        this.parameters.cdo_fecha_hasta = cdo_fecha_hasta;

        if (this.pro_id && this.pro_id.value)
            this.parameters.pro_id = this.pro_id.value;
        else
            delete this.parameters.pro_id;

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

        if (this.rfa_prefijo && this.rfa_prefijo.value.trim())
            this.parameters.rfa_prefijo = this.rfa_prefijo.value;
        else
            delete this.parameters.rfa_prefijo;

        
        if(this.campo_validacion && this.campo_validacion.value)
            this.parameters.campo_validacion = this.campo_validacion.value;
        else 
            delete this.parameters.campo_validacion; 

        if(this.valor_campo_validacion && this.valor_campo_validacion.value)
            this.parameters.valor_campo_validacion = this.valor_campo_validacion.value;
        else 
            delete this.parameters.valor_campo_validacion; 

        this.parameters.proceso = 'recepcion';
        this.parameters.tipo    = this.tipo_reporte.value;

        return this.parameters;
    }

    ngOnInit() {
        this.initDataSort('fecha_creacion');
        this.loadingIndicator = true;
        this.ordenDireccion   = 'DESC';
        this.pro_id.disable();
        this.cargarOfes();
    }

    /**
     * Carga de data inicial requerida para el funcionamiento del componente, como OFEs.
     *
     * @memberof LogValidacionDocumentosComponent
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
        ofes.data.ofes.map(ofe => {
            if(ofe.ofe_recepcion === 'SI') {
                ofe.ofe_identificacion_ofe_razon_social = ofe.ofe_identificacion + ' - ' + ofe.ofe_razon_social;
                this.ofes.push(ofe);
            }
        });

        this.consultarReportesDescargar();
    }

    /**
     * Realiza cambios a nivel del componente dependiendo del valor de OFE seleccionado.
     * 
     * @param {object} ofe Objeto con la información del OFE seleccionado
     * @memberof LogValidacionDocumentosComponent
     */
    ofeHasChanged(ofe) {
        this.arrCamposValidacion   = [];
        this.campo_validacion.setValue('');
        this.valor_campo_validacion.setValue('');
        this.mostrarComboValorCampoValidacion = false;

        if(ofe.ofe_recepcion_fnc_activo == 'SI' && ofe.ofe_recepcion_fnc_configuracion) {
            this.ofeRecepcionFncActivo        = ofe.ofe_recepcion_fnc_activo;
            this.ofeRecepcionFncConfiguracion = ofe.ofe_recepcion_fnc_configuracion;

            if(ofe.ofe_recepcion_fnc_configuracion.evento_recibo_bien) {
                let objCampoSeleccionar;
                let seleccionarFondos = false;

                ofe.ofe_recepcion_fnc_configuracion.evento_recibo_bien.forEach(configCampo => {
                    if(this._baseService.sanitizarString(configCampo.campo) === 'fondos') {
                        seleccionarFondos   = true;
                        objCampoSeleccionar = {
                            'campo'      : this._baseService.sanitizarString(configCampo.campo),
                            'nombreCampo': configCampo.campo,
                            'tipo'       : configCampo.tipo ? configCampo.tipo : '',
                            'tabla'      : configCampo.tabla ? configCampo.tabla : ''
                        }
                    }
                    
                    this.arrCamposValidacion.push(
                        {
                            'campo'      : this._baseService.sanitizarString(configCampo.campo),
                            'nombreCampo': configCampo.campo,
                            'tipo'       : configCampo.tipo ? configCampo.tipo : '',
                            'tabla'      : configCampo.tabla ? configCampo.tabla : ''
                        }
                    )
                });

                if(seleccionarFondos) {
                    this.form.get('campo_validacion').setValue(objCampoSeleccionar.campo);
                    this.cambioFiltroCamposValidacion(objCampoSeleccionar);
                }
            }
        }
    }

    /**
     * Realiza la consulta de los datos paramétricos de validación y asigna los valores encontrados al control correspondiente en los filtros del formulario.
     *
     * Aplica OFEs de FNC
     *
     * @private
     * @param {string} string Nombre del control del formulario que recibirá los datos
     * @param {string} clasificacion Clasificación de los datos que debe ser filtrada en la consulta
     * @memberof ModalEventosDocumentosComponent
     */
    private obtenerListaDatosParametricosValidacion(campo: string, clasificacion: string) {
        this.loading(true);
        this._datosParametricosService.listarDatosParametricosValidacion(campo, clasificacion).subscribe({
            next: ( res => {
                this.loading(false);
                this.arrValoresCamposValidacion = res.data.datos_parametricos_clasificacion;
            }),
            error: ( error => {
                this.loading(false);
                this.mostrarErrores(error, 'Error al cargar los Datos Parametricos de Validación');
            })
        });
    }

    /**
     * Realiza modificaciones a valores y visibilidad de campos relacionados con los filtros de validación de documentos.
     *
     * @param {*} valorSeleccionado
     * @memberof ValidacionDocumentosComponent
     */
    public cambioFiltroCamposValidacion(valorSeleccionado) {
        this.mostrarComboValorCampoValidacion = false;
        this.valor_campo_validacion.setValue('');

        if(valorSeleccionado && valorSeleccionado.tipo && valorSeleccionado.tipo === 'parametrico' && valorSeleccionado.tabla && valorSeleccionado.tabla === this.tablaDatosParametricosValidacion) {
            this.mostrarComboValorCampoValidacion = true;

            this.obtenerListaDatosParametricosValidacion('Valor a Buscar', this._baseService.sanitizarString(valorSeleccionado.campo));
        }
    }

    /**
     * Consulta los reportes del día, que el usuario autenticado puede descargar.
     *
     * @memberof LogValidacionDocumentosComponent
     */
    async consultarReportesDescargar() {
        this.loading(true);
        this.archivos = [];

        let consultaReportes = await this._validacionDocumentosService.listarReportesDescargar(this.getSearchParametersInline()).toPromise().catch(error => {
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
     * Procesa la petición para la generación del reporte conforme a los filtros seleccionados en el componente.
     *
     * @memberof LogValidacionDocumentosComponent
     */
    generarExcel() {
        this.loading(true);
        this._validacionDocumentosService.agendarReporteExcel(this.getSearchParametersObject()).subscribe({
            next: (response => {
                this.loading(false);
                this.showSuccess('<strong>' + response.message + '<br><br>El archivo se listará cuando el mismo sea generado por el sistema.</strong>', 'success', 'Generar Excel', 'Ok, entiendo', 'btn btn-success');
                this.consultarReportesDescargar();
            }),
            error: (error => {
                this.loading(false);
                const texto_errores = this.parseError(error);
                this.loadingIndicator = false;
                this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al programar la generación del Excel', 'OK', 'btn btn-danger');
            })
        });
    }

    /**
     * Verifica los campos mínimos que deben estar diligenciados en el formulario para poder procesar la información.
     *
     * @return {boolean} 
     * @memberof LogValidacionDocumentosComponent
     */
    validarCamposMinimos() {
        if(
            this.ofe_id.value !== '' && this.ofe_id.value !== undefined &&
            this.cdo_fecha_desde.value !== '' && this.cdo_fecha_desde.value !== undefined && this.cdo_fecha_desde.value !== null &&
            this.cdo_fecha_hasta.value !== '' && this.cdo_fecha_hasta.value !== undefined && this.cdo_fecha_hasta.value !== null
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Permite la descarga de un reporte previamente generado en backend.
     *
     * @param {*} pjjId Id del procesamiento
     * @memberof LogValidacionDocumentosComponent
     */
    descargarExcel(pjjId) {
        this.loading(true);
        this._validacionDocumentosService.descargarExcel({'pjj_id': pjjId}).subscribe({
            next: (response => {
                this.loading(false);
                this.consultarReportesDescargar();
            }),
            error: (error => {
                this.loading(false);
            })
        });
    }

    /**
     * Gestiona el evento de paginación de la grid.
     *
     * @param {*} $evt Información del evento
     * @memberof LogValidacionDocumentosComponent
     */
    public onPage($evt) {
        this.page = $evt.offset;
        this.start = $evt.offset * this.length;
        this.consultarReportesDescargar();
    }

    /**
     * Evento de descarga de un Ítem.
     *
     * @param {*} item Registro seleccionado
     * @memberof LogValidacionDocumentosComponent
     */
    onDownloadItem(item: any) {
        this.descargarExcel(item.pjj_id);
    }

    /**
     * Gestiona el proceso de búsqueda rápida.
     *
     * @param {string} buscar Valor a buscar
     * @memberof LogValidacionDocumentosComponent
     */
    onSearchInline(buscar: string) {
        this.start = 0;
        this.buscar = buscar;
        this.consultarReportesDescargar();
    }

    /**
     * Modifica el total de elementos a mostrar en la grid.
     *
     * @param {number} size Cantidad de registros
     * @memberof LogValidacionDocumentosComponent
     */
    onChangeSizePage(size: number) {
        this.start = 0;
        this.page = 1;
        this.length = size;
        this.consultarReportesDescargar();
    }

    /**
     * Evento para ordenar por una columna.
     *
     * @param {string} column Columna a ordenar
     * @param {string} $order Tipo de orientación
     * @memberof LogValidacionDocumentosComponent
     */
    onOrderBy(column: string, $order: string) {}

    /**
     * Evento para las acciones en bloque.
     *
     * @param {*} opcion Acción en bloque
     * @param {any[]} selected Registros seleccionados
     * @memberof LogValidacionDocumentosComponent
     */
    onOptionMultipleSelected(opcion: any, selected: any[]) {}

    /**
     * Evento de para solicitar la eliminación de un registro.
     *
     * @param {*} item Registro seleccionado
     * @memberof LogValidacionDocumentosComponent
     */
    onRequestDeleteItem(item: any) {}

    /**
     * Evento para ver de un registro.
     *
     * @param {*} item Registro seleccionado
     * @memberof LogValidacionDocumentosComponent
     */
    onViewItem(item: any) {}

    /**
     * Evento para edición de un registro.
     *
     * @param {*} item Registro seleccionado
     * @memberof LogValidacionDocumentosComponent
     */
    onEditItem(item: any) {}
}