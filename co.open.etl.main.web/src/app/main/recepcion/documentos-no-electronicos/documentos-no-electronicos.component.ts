import { concat, of } from 'rxjs';
import { Router, ActivatedRoute } from '@angular/router';
import { Component, OnInit, ViewChild } from '@angular/core';
import { BaseComponentView } from 'app/main/core/base_component_view';
import { AbstractControl, FormGroup, FormBuilder } from '@angular/forms';
import { catchError, debounceTime, distinctUntilChanged, filter, switchMap, tap } from 'rxjs/operators';

import { Auth } from '../../../services/auth/auth.service';
import { CommonsService } from '../../../services/commons/commons.service';
import { ProveedoresService } from '../../../services/configuracion/proveedores.service';
import { ConfiguracionService } from '../../../services/configuracion/configuracion.service';
import { DocumentosNoElectronicosService } from '../../../services/recepcion/documentos_no_electronicos.service';
import { SelectorParReceptorEmisorComponent } from '../../commons/selector-par-receptor-emisor/selector-par-receptor-emisor.component';
import * as moment from 'moment';

@Component({
    selector: 'app-documentos-no-electronicos',
    templateUrl: './documentos-no-electronicos.component.html',
    styleUrls: ['./documentos-no-electronicos.component.scss']
})
export class DocumentosNoElectronicosComponent extends BaseComponentView implements OnInit {

    @ViewChild('selectorParReceptorEmisorChild', {static: true}) selectorParReceptorEmisorChild: SelectorParReceptorEmisorComponent;

    // Inicializa las propiedades del formulario
    public form                    : FormGroup;
    public ofe_id                  : AbstractControl;
    public pro_id                  : AbstractControl;
    public cdo_clasificacion       : AbstractControl;
    public rfa_prefijo             : AbstractControl;
    public cdo_consecutivo         : AbstractControl;
    public cdo_fecha               : AbstractControl;
    public cdo_hora                : AbstractControl;
    public cdo_vencimiento         : AbstractControl;
    public mon_codigo              : AbstractControl;
    public cdo_trm                 : AbstractControl;
    public cdo_trm_fecha           : AbstractControl;
    public cdo_observacion         : AbstractControl;
    public cdo_valor_sin_impuestos : AbstractControl;
    public cdo_impuestos           : AbstractControl;
    public cdo_total               : AbstractControl;

    public formErrors                  : any;
    public ofeIdentificacion           : string;
    public proIdentificacion           : string;
    public ofes                        : Array<any> = [];
    public tiposDocumentosElectronicos : Array<any> = [];
    public monedas                     : Array<any> = [];
    public mostrarTrm                  : boolean = false;
    public ofeId                       : string = '';
    public cdoId                       : string = '';
    public tituloAccion                : string = '';
    public verDocumento                : boolean = false;
    public editarDocumento             : boolean = false;

    public arrTipoDoc: Array<Object> = [
        {id: 'FC', name: 'FC'},
        {id: 'NC', name: 'NC'},
        {id: 'ND', name: 'ND'}
    ];

    /**
     * Crea una instancia de DocumentosNoElectronicosComponent.
     * 
     * @param {Auth} _auth
     * @param {Router} _router
     * @param {FormBuilder} _formBuilder
     * @param {ActivatedRoute} _activatedRoute
     * @param {CommonsService} _commonsService
     * @param {ProveedoresService} _proveedoresService
     * @param {ConfiguracionService} _configuracionService
     * @param {DocumentosNoElectronicosService} _documentosNoElectronicosService
     * @memberof DocumentosNoElectronicosComponent
     */
    constructor(
        public _auth: Auth,
        private _router: Router,
        private _formBuilder: FormBuilder,
        private _activatedRoute: ActivatedRoute,
        private _commonsService: CommonsService,
        private _proveedoresService: ProveedoresService,
        private _configuracionService: ConfiguracionService,
        private _documentosNoElectronicosService: DocumentosNoElectronicosService
    ) {
        super();
        this.initForm();
        this.buildErrorsObjetc();
        this.ofeId = this._activatedRoute.snapshot.params['ofe_id'];
        this.cdoId = this._activatedRoute.snapshot.params['cdo_id'];
    }

    /**
     * ngOnInit de DocumentosNoElectronicosComponent.
     *
     * @memberof DocumentosNoElectronicosComponent
     */
    ngOnInit() {
        if (this._router.url.indexOf('/recepcion/documentos-no-electronicos/ver-documento') !== -1) {
            //Acción ver documento no electrónico
            this.tituloAccion = 'Ver Documentos No Electrónicos';
            this.verDocumento = true;
            this.form.disable();
        } else if(this._router.url.indexOf('/recepcion/documentos-no-electronicos/editar-documento') !== -1) {
            //Acción editar documento no electrónico
            this.tituloAccion = 'Editar Documentos No Electrónicos';
            this.editarDocumento = true;
            this.ofe_id.disable();
            this.pro_id.disable();
            this.rfa_prefijo.disable();
            this.cdo_consecutivo.disable();
        } else {
            this.tituloAccion = 'Crear Documentos No Electrónicos';
        }
        this.cargarOfes();
    }

    /**
     * Inicializando el formulario.
     *
     * @private
     * @memberof DocumentosNoElectronicosComponent
     */
    private initForm(): void {
        this.form = this._formBuilder.group({
            'ofe_id'                  : this.requerido(),
            'pro_id'                  : this.requerido(),
            'cdo_clasificacion'       : this.requerido(),
            'rfa_prefijo'             : [''],
            'cdo_consecutivo'         : this.requerido(),
            'cdo_fecha'               : this.requerido(),
            'cdo_hora'                : [''],
            'cdo_vencimiento'         : [''],
            'mon_codigo'              : this.requerido(),
            'cdo_trm'                 : [''],
            'cdo_trm_fecha'           : [''],
            'cdo_observacion'         : [''],
            'cdo_valor_sin_impuestos' : this.requerido(),
            'cdo_impuestos'           : [''],
            'cdo_total'               : ['']
        });
        
        this.ofe_id                  = this.form.controls['ofe_id'];
        this.pro_id                  = this.form.controls['pro_id'];
        this.cdo_clasificacion       = this.form.controls['cdo_clasificacion'];
        this.rfa_prefijo             = this.form.controls['rfa_prefijo'];
        this.cdo_consecutivo         = this.form.controls['cdo_consecutivo'];
        this.cdo_fecha               = this.form.controls['cdo_fecha'];
        this.cdo_hora                = this.form.controls['cdo_hora'];
        this.cdo_vencimiento         = this.form.controls['cdo_vencimiento'];
        this.mon_codigo              = this.form.controls['mon_codigo'];
        this.cdo_trm                 = this.form.controls['cdo_trm'];
        this.cdo_trm_fecha           = this.form.controls['cdo_trm_fecha'];
        this.cdo_observacion         = this.form.controls['cdo_observacion'];
        this.cdo_valor_sin_impuestos = this.form.controls['cdo_valor_sin_impuestos'];
        this.cdo_impuestos           = this.form.controls['cdo_impuestos'];
        this.cdo_total               = this.form.controls['cdo_total'];
    }

    /**
     * Construye un objeto para gestionar los errores en el formulario.
     * 
     * @memberof CargosGestionarComponent
     */
    public buildErrorsObjetc() {
        this.formErrors = {
            ofe_id: {
                required: 'El Receptor es requerido!'
            },
            pro_id: {
                required: 'El Emisor es requerido!'
            },
            cdo_consecutivo: {
                required: 'El consecutivo es requerido!'
            },
            cdo_fecha: {
                required: 'La fecha es requerida!'
            },
            cdo_valor_sin_impuestos: {
                required: 'El valor sin impuestos es requerido!'
            }
        };
    }

    /**
     * Monitoriza cuando el valor del select del receptor (Ofe) cambia.
     *
     * @param {*} ofe Información del Ofe seleccionado
     * @memberof DocumentosNoElectronicosComponent
     */
    ofeHasChanged(ofe) {
        this.ofeIdentificacion = ofe.ofe_identificacion;
    }

    /**
     * Monitoriza cuando el valor del select del emisor (Proveedor) cambia.
     *
     * @param {*} proveedor Información del proveedor seleccionado
     * @memberof DocumentosNoElectronicosComponent
     */
    proveedorHasChanged(proveedor) {
        this.proIdentificacion = proveedor.pro_identificacion;
        this.setProveedorSelected(this.ofe_id.value, proveedor);
    }

    setProveedorSelected(ofe_id, proveedor) {
        if(proveedor) {
            let razonSocialProveedor = (proveedor.pro_razon_social != '') ? proveedor.pro_razon_social : proveedor.pro_primer_nombre + ' ' + proveedor.pro_otros_nombres + ' ' + proveedor.pro_primer_apellido + ' ' + proveedor.pro_segundo_apellido;
            const prov = [{
                pro_id              : proveedor.pro_id,
                pro_identificacion  : proveedor.pro_identificacion,
                pro_razon_social    : razonSocialProveedor,
                pro_identificacion_pro_razon_social :  '('+ proveedor.pro_identificacion + ') - ' + razonSocialProveedor
            }];

            this.selectorParReceptorEmisorChild.proveedores$ = concat(
                of(prov),
                this.selectorParReceptorEmisorChild.proveedoresInput$.pipe(
                    debounceTime(750),
                    filter((query: string) => query && query.length > 0),
                    distinctUntilChanged(),
                    tap(() => this.loading(true)),
                    switchMap(term => this._proveedoresService.searchProveedorNgSelect('', ofe_id).pipe(
                        catchError(() => of([])),
                        tap(() => this.loading(false))
                    ))
                )
            );
        }
    }

    /**
     * Monitoriza cuando el valor del select del moneda cambia.
     *
     * @param {*} moneda Información de la moneda seleccionada
     * @memberof DocumentosNoElectronicosComponent
     */
    monedaHasChanged(moneda) {
        if (moneda) {
            if (moneda.mon_codigo !== 'COP') {
                this.mostrarTrm = true;
            } else {
                this.mostrarTrm = false;
            }
        }
    }

    /**
     * Calcula el valor total del documento teniendo en cuenta la sumatoria del valor sin impuestos y los impuestos.
     *
     * @memberof DocumentosNoElectronicosComponent
     */
    calcularTotal() {
        let valorSinImpuestos = !isNaN(this.cdo_valor_sin_impuestos.value) && this.cdo_valor_sin_impuestos.value != '' ? this.cdo_valor_sin_impuestos.value : 0;
        let valorImpuestos    = !isNaN(this.cdo_impuestos.value) && this.cdo_impuestos.value != '' ? this.cdo_impuestos.value : 0;
        let valorTotal        = parseFloat(valorSinImpuestos) + parseFloat(valorImpuestos);
        this.cdo_total.setValue(valorTotal);
    }

    /**
     * Carga los OFEs en el select de receptores.
     *
     * @memberof DocumentosNoElectronicosComponent
     */
    public async cargarOfes() {
        this.loading(true);

        let ofes = await this._configuracionService.listarSelect('ofe').toPromise().catch(error => {
            let texto_errores = this.parseError(error);
            this.loading(false);
            this.showError(texto_errores, 'error', 'Error al cargar los OFEs', 'Ok', 'btn btn-danger');
        });

        this.loading(false);
        let that = this;
        this.ofes = [];
        let listadoOfes = ofes.data;
        listadoOfes.forEach(ofe => {
            if(ofe.ofe_recepcion === 'SI') {
                ofe.ofe_identificacion_ofe_razon_social = ofe.ofe_identificacion + ' - ' + ofe.ofe_razon_social;
                that.ofes.push(ofe);
            }
        });

        await this.cargarDatosParametricos();

        if (this.verDocumento || this.editarDocumento) {
            await this.loadDocumentoNoElectronico();
        }
    }

    /**
     * Carga los datos parámetricos necesarios para la creación de un documento no electrónico.
     *
     * @private
     * @memberof DocumentosNoElectronicosComponent
     */
    private async cargarDatosParametricos() {
        this.loading(true);
        let datosParametricos = await this._commonsService.getParametrosDocumentosElectronicos().toPromise().catch(error => {
            let texto_errores = this.parseError(error);
            this.loading(false);
            this.showError(texto_errores, 'error', 'Error al cargar los datos paramétricos', 'Ok', 'btn btn-danger');
        });
        this.loading(false);

        this.monedas = [];
        datosParametricos.data.moneda.forEach(moneda => {
            moneda.mon_codigo_descripcion = moneda.mon_codigo + ' - ' + moneda.mon_descripcion;
            this.monedas.push(moneda);
        });
    }

    /**
     * Carga la información de un documento no electrónico en el formulario.
     *
     * @memberof DocumentosNoElectronicosComponent
     */
    loadDocumentoNoElectronico() {
        this.loading(true);
        const payload = {
            tde_id          : '',
            ofe_id          : this.ofeId,
            cdo_id          : this.cdoId,
            rfa_prefijo     : '',
            cdo_consecutivo : '',
            editar_documento: true
        }

        this._documentosNoElectronicosService.obtenerDocumentoNoElectronicoSeleccionado(payload).subscribe({
            next: res => {
                if (res) {
                    this.loading(false);
                    this.ofe_id.setValue(res.data.ofe_id);
                    if (res.data.get_configuracion_obligado_facturar_electronicamente) {
                        this.ofeHasChanged(res.data.get_configuracion_obligado_facturar_electronicamente);
                    }

                    this.pro_id.setValue(res.data.pro_id);

                    if (res.data.get_configuracion_proveedor) {
                        let resProveedor = res.data.get_configuracion_proveedor;
                        this.selectorParReceptorEmisorChild.onProSeleccionado(resProveedor);
                        this.setProveedorSelected(res.data.ofe_id, resProveedor);
                    }

                    this.cdo_clasificacion.setValue(res.data.cdo_clasificacion);
                    this.rfa_prefijo.setValue(res.data.rfa_prefijo);
                    this.cdo_consecutivo.setValue(res.data.cdo_consecutivo);
                    this.cdo_fecha.setValue(res.data.cdo_fecha);
                    this.cdo_hora.setValue(res.data.cdo_hora);
                    this.mon_codigo.setValue(res.data.get_parametros_moneda.mon_codigo);
                    if (res.data.get_parametros_moneda.mon_codigo != 'COP') {
                        this.mostrarTrm = true;
                        this.cdo_trm.setValue(res.data.cdo_trm);
                        this.cdo_trm_fecha.setValue(res.data.cdo_trm_fecha);
                    }
                    this.cdo_vencimiento.setValue(res.data.cdo_vencimiento);

                    if (res.data.cdo_observacion != null) {
                        let arrObservacion = JSON.parse(res.data.cdo_observacion);
                        if (arrObservacion.length > 0) { 
                            this.cdo_observacion.setValue(arrObservacion[0]);
                        }
                    }

                    this.cdo_valor_sin_impuestos.setValue(res.data.cdo_valor_sin_impuestos);
                    this.cdo_impuestos.setValue(res.data.cdo_impuestos);
                    this.cdo_total.setValue(res.data.cdo_total);

                    // Para FNC verifica los estados de VALIDACION DEL DOCUMENTO para saber si puede o no editar el OFE y el Proveedor
                    if(
                        res.data.get_configuracion_obligado_facturar_electronicamente.ofe_recepcion_fnc_activo == 'SI' &&
                        (
                            res.data.get_estados_validacion.length == 0 ||
                            (res.data.get_estados_validacion.length > 0 && this.validacionRechazado(res.data.get_estados_validacion))
                        )
                    ) {
                        this.pro_id.enable();
                        this.rfa_prefijo.enable();
                        this.cdo_consecutivo.enable();
                    }
                }
            },
            error: error => {
                this.loading(false);
                let texto_errores = this.parseError(error);
                this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al cargar el Empleador', 'Ok', 'btn btn-danger', 'recepcion/documentos-recibidos', this._router);
            }
        });
    }

    /**
     * Verifica si existe estado VALIDACION-RECHAZADO como estado de validación más reciente
     *
     * @param {Array<any>} estadosValidacion Estados validación del documento
     * @return {*}  {boolean}
     * @memberof DocumentosNoElectronicosComponent
     */
    validacionRechazado(estadosValidacion: Array<any>): boolean {
        let validacion = estadosValidacion[estadosValidacion.length - 1];

        if(validacion.est_resultado === 'RECHAZADO')
            return true;

        return false;
    }

    /**
     * Permite guardar un documento no electrónico.
     *
     * @memberof DocumentosNoElectronicosComponent
     */
    guardarDocumento() {
        this.loading(true);
        let json = this.getPayload();
        this._documentosNoElectronicosService.enviarDocumentoNoElectronico(json).subscribe(
            response => {
                this.loading(false);
                if (response.documentos_fallidos.length > 0) {
                    let texto_errores = response.documentos_fallidos[0];
                    this.mostrarErrores(texto_errores, 'Error al guardar el documento no electrónico');
                } else if(response.documentos_procesados.length > 0) {
                    this.showTimerAlert('<strong>Documento no electrónico guardado con éxito.!</strong>', 'success', 'center', 2000);
                    this.regresar();
                }
            },
            (error) => {
                this.loading(false);
            }
        );
    }

    /**
     * Construye el json para enviar a la api y guardar el documento no electrónico.
     *
     * @return {*} Json para enviar el petición
     * @memberof DocumentosNoElectronicosComponent
     */
    getPayload() {
        let json: any;
        let jsonDocumento: any;

        switch (this.cdo_clasificacion.value) {
            case 'FC':
                json = {
                    documentos: {
                        FC: [{}]
                    }
                };
                jsonDocumento = json.documentos.FC[0];
                break;
            case 'NC':
                json = {
                    documentos: {
                        NC: [{}]
                    }
                };
                jsonDocumento = json.documentos.NC[0];
            break;
            case 'ND':
                json = {
                    documentos: {
                        ND: [{}]
                    }
                };
                jsonDocumento = json.documentos.ND[0];
            break;
            default:
            break;
        }

        jsonDocumento.ofe_identificacion      = this.ofeIdentificacion;
        jsonDocumento.pro_identificacion      = this.proIdentificacion;
        jsonDocumento.rfa_prefijo             = this.rfa_prefijo.value;
        jsonDocumento.cdo_consecutivo         = this.cdo_consecutivo.value;
        jsonDocumento.cdo_fecha               = (this.cdo_fecha.value != '' && this.cdo_fecha.value != undefined) ? String(moment(this.cdo_fecha.value).format('YYYY-MM-DD')) : '';
        jsonDocumento.cdo_hora                = this.cdo_hora.value;
        jsonDocumento.cdo_vencimiento         = (this.cdo_vencimiento.value != '' && this.cdo_vencimiento.value != undefined) ? String(moment(this.cdo_vencimiento.value).format('YYYY-MM-DD')) : '';
        jsonDocumento.cdo_observacion         = this.cdo_observacion.value != '' ? [this.cdo_observacion.value] : null;
        jsonDocumento.mon_codigo              = this.mon_codigo.value;
        jsonDocumento.cdo_trm                 = this.cdo_trm.value;
        jsonDocumento.cdo_trm_fecha           = (this.cdo_trm_fecha.value != '' && this.cdo_trm_fecha.value != undefined) ? String(moment(this.cdo_trm_fecha.value).format('YYYY-MM-DD')) : '';
        jsonDocumento.cdo_valor_sin_impuestos = this.cdo_valor_sin_impuestos.value;
        jsonDocumento.cdo_impuestos           = this.cdo_impuestos.value;
        jsonDocumento.cdo_total               = this.cdo_total.value;
        jsonDocumento.cdo_valor_a_pagar       = this.cdo_total.value;

        if (this.editarDocumento) {
            jsonDocumento.update = 'SI';
            jsonDocumento.cdo_id = this.cdoId;
        }

        return json;
    }

    /**
     * Permite regresar al formulario de tracking de documentos recibidos.
     *
     * @memberof DocumentosNoElectronicosComponent
     */
    regresar() {
        this._router.navigate(['recepcion/documentos-recibidos']);
    }
}

