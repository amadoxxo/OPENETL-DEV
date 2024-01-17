import {Component, OnDestroy, OnInit, Input, ViewChild, AfterViewInit} from '@angular/core';
import {BaseComponentView} from '../../../../core/base_component_view';
import {ActivatedRoute, Router} from '@angular/router';
import {AbstractControl, FormArray, FormBuilder, FormGroup, Validators} from '@angular/forms';
import {Subject} from 'rxjs';
import {CommonsService} from '../../../../../services/commons/commons.service';
import {ConfiguracionService} from '../../../../../services/configuracion/configuracion.service';
import {MatAccordion} from '@angular/material/expansion';
import swal from 'sweetalert2';
import {JwtHelperService} from '@auth0/angular-jwt';

@Component({
    selector: 'app-adquirentes-gestionar',
    templateUrl: './adquirentes-gestionar.component.html',
    styleUrls: ['./adquirentes-gestionar.component.scss']
})
export class AdquirentesGestionarComponent extends BaseComponentView implements OnInit, OnDestroy, AfterViewInit {
    @ViewChild('acordion', {static: false}) acordion: MatAccordion;

    @Input() tipoAdquirente: string = null;
    @Input() ver: boolean;
    @Input() editar: boolean;

    // Usuario en línea
    public usuario: any;
    public objMagic = {};
    // Formulario y controles
    public gestor    : AdquirentesGestionarComponent;
    public formulario: FormGroup;
    public tdo_id    : AbstractControl;
    public tat_id    : AbstractControl;
    public toj_id    : AbstractControl;
    public pai_id    : AbstractControl;
    public mun_id    : AbstractControl;
    public dep_id    : AbstractControl;
    //Domicilio Fiscal
    public pai_id_domicilio_fiscal       : AbstractControl;
    public mun_id_domicilio_fiscal       : AbstractControl;
    public dep_id_domicilio_fiscal       : AbstractControl;
    public adq_direccion_domicilio_fiscal: AbstractControl;
    public cpo_id_domicilio_fiscal       : AbstractControl;
    //Fin Domicilio Fiscal
    public DV                      : AbstractControl;
    public responsable_tributos    : AbstractControl;
    public adq_identificacion      : AbstractControl;
    public adq_id_personalizado    : AbstractControl;
    public adq_razon_social        : AbstractControl;
    public adq_nombre_comercial    : AbstractControl;
    public adq_primer_apellido     : AbstractControl;
    public adq_segundo_apellido    : AbstractControl;
    public adq_primer_nombre       : AbstractControl;
    public adq_otros_nombres       : AbstractControl;
    public adq_direccion           : AbstractControl;
    public adq_nombre_contacto     : AbstractControl;
    public adq_telefono            : AbstractControl;
    public adq_fax                 : AbstractControl;
    public adq_correo              : AbstractControl;
    public adq_notas               : AbstractControl;
    public adq_matricula_mercantil : AbstractControl;
    public cpo_id                  : AbstractControl;
    public adq_correos_notificacion: AbstractControl;
    public rfi_id                  : AbstractControl;
    public ref_id                  : AbstractControl;
    public ipv_id                  : AbstractControl;
    public ofe_id                  : AbstractControl;
    public estado                  : AbstractControl;
    public titulo                  : string;
    // public ofeObjeto: Array<any> = [];
    // public ofeObjeto = {};
    public paisObjeto                  = {};
    public departamentoObjeto          = {};
    public municipioObjeto             = {};
    public paisFiscalObjeto            = {};
    public departamentoFiscalObjeto    = {};
    public municipioFiscalObjeto       = {};
    public responsabilidadFiscalObjeto = {};
    public codigoPostalObjeto          = {};
    public codigoPostalFiscalObjeto    = {};
    public tatObjeto                   = {};

    public initOrganizacion = null;
    public initDV           = null;
    public codigoNIT        = "31";

    proveedores            : FormArray;
    camposInfoPersonalizada: Array<any> = [];
    recurso                = 'Adquirente';

    _adq_id: any;
    _adq_identificacion: any;
    _adq_id_personalizado: any;

    public ofes                           : Array<any> = [];
    public paises                         : Array<any> = [];
    public tipoDocumentos                 : Array<any> = [];
    public tipoOrganizaciones             : Array<any> = [];
    public tipoRegimen                    : Array<any> = [];
    public tipoProcedenciaVendedor        : Array<any> = [];
    public tributos                       : Array<any> = [];
    public ResFiscal                      : Array<any> = [];
    public tiemposAceptacionTacita        : Array<any> = [];
    public responsabilidadesFiscales      : any = [];
    public ubicacionFiscalvalidation      : boolean = false;
    public tipoDocumentoSelect            : any = {};
    public tipoOrganizacionSelect         : any = {};
    public regimenFiscalSelect            : any = {};
    public procedenciaVendedorSelect      : any = {};
    public responsabilidadesFiscalesSelect: any = [];
    public tributosSelect                 : any = [];

    // Steppers
    datosGenerales          : FormGroup;
    ubicacion               : FormGroup;
    ubicacionFiscal         : FormGroup;
    datosTributarios        : FormGroup;
    ofeSelector             : FormGroup;
    contactos               : FormGroup;
    reglaAceptacionTacita   : FormGroup;
    informacionAdicional    : FormGroup;
    correosNotificacion     : FormGroup;
    repGraficaYXml          : FormGroup;
    envioDirecto            : FormGroup;
    portalClientes          : FormGroup;
    informacionPersonalizada: FormGroup;

    deliveryContact  : FormGroup;
    accountingContact: FormGroup;
    buyerContact     : FormGroup;

    // Private
    private _unsubscribeAll: Subject<any> = new Subject();

    _ofe_identificacion: string;
    ultimoComprobado   : string;
    ultimoOfeComprobado: string;

    public maxlengthIdentificacion: number = 20;
    public regexIdentificacion: RegExp = /^[0-9a-zA-Z-]{1,20}$/;

    /**
     *
     * @param _router
     * @param _route
     * @param _formBuilder
     * @param _commonsService
     */
    constructor(
        private _router: Router,
        private _route: ActivatedRoute,
        private _formBuilder: FormBuilder,
        private _commonsService: CommonsService,
        private _configuracionService: ConfiguracionService,
        private jwtHelperService: JwtHelperService
    ) {
        super();
        this._configuracionService.setSlug = 'adquirentes';
        this.gestor = this;
        this.init();
        this.usuario = this.jwtHelperService.decodeToken();
    }

    ngOnInit() {
        switch (this.tipoAdquirente) {
            case 'adquirente':
                this.recurso = 'Adquirente';
                this._configuracionService.setSlug = 'adquirentes';
                break;
            case 'autorizado':
                this.recurso = 'Autorizado';
                this._configuracionService.setSlug = 'autorizados';
                break;
            case 'responsable':
                this.recurso = 'Responsable Entrega de Bienes';
                this._configuracionService.setSlug = 'responsables';
                break;
            case 'vendedor':
                this.recurso = 'Vendedor Documento Soporte';
                this._configuracionService.setSlug = 'vendedores-ds';
                break;
            default:
                break;
        }
        this._adq_id = this._route.snapshot.params['adq_id'];
        this._adq_identificacion   = this._route.snapshot.params['adq_identificacion'];
        this._adq_id_personalizado = this._route.snapshot.params['adq_id_personalizado'];
        this._ofe_identificacion   = this._route.snapshot.params['ofe_identificacion'];
        this.ver = false;

        const nombreVentana = this.recurso === 'Autorizado' ? 'Autorizado (Representación)' : this.recurso;

        if (this._adq_identificacion && !this._adq_id) {
            this.titulo = 'Editar ' + nombreVentana;
            this.editar = true;
        } else if (this._adq_identificacion && this._adq_id) {
            this.titulo = 'Ver ' + nombreVentana;
            this.ver = true;
        } else {
            this.titulo = 'Crear ' + nombreVentana;
        }
    }

    /**
     * Vista construida
     */
    ngAfterViewInit() {
        if (this.ver) {
            this.acordion.openAll();
        }
        this.initForBuild();
    }

    /**
     * On destroy.
     *
     */
    ngOnDestroy(): void {
        // Unsubscribe from all subscriptions
        this._unsubscribeAll.next(true);
        this._unsubscribeAll.complete();
    }

    /**
     * Inicializacion de los diferentes controles.
     *
     */
    init() {
        this.buildFormulario();
    }

    /**
     * Se encarga de cargar los datos de un adquirente que se ha seleccionado en el tracking.
     *
     */
    public loadAdquirente(): void {
        this.loading(true);
        this._configuracionService.getAdq(this._adq_identificacion, this._ofe_identificacion, this._adq_id_personalizado).subscribe(
            res => {
                if (res) {
                    if (res.data.get_configuracion_obligado_facturar_electronicamente)
                        this.ofe_id.setValue(res.data.get_configuracion_obligado_facturar_electronicamente.ofe_identificacion);
                    if (res.data.get_parametro_tipo_documento) {
                        this.tdo_id.setValue(res.data.get_parametro_tipo_documento.tdo_codigo);
                        this.tipoDocumentoSelect = res.data.get_parametro_tipo_documento;
                    }
                    if (res.data.get_parametro_tipo_organizacion_juridica)
                        this.toj_id.setValue(res.data.get_parametro_tipo_organizacion_juridica.toj_codigo);
                        this.tipoOrganizacionSelect = res.data.get_parametro_tipo_organizacion_juridica;
                    if (res.data.get_regimen_fiscal)
                        this.rfi_id.setValue(res.data.get_regimen_fiscal.rfi_codigo);
                        this.regimenFiscalSelect = res.data.get_regimen_fiscal;

                    if (res.data.get_procedencia_vendedor) {
                        this.ipv_id.setValue(res.data.get_procedencia_vendedor.ipv_codigo);
                        this.procedenciaVendedorSelect = res.data.get_procedencia_vendedor;
                    } else {
                        this.procedenciaVendedorSelect = this.tipoProcedenciaVendedor;
                    }

                    if (res.data.get_responsabilidad_fiscal) {
                        this.responsabilidadesFiscalesSelect = res.data.get_responsabilidad_fiscal;
                    } else {
                        this.responsabilidadesFiscalesSelect = this.responsabilidadesFiscales;
                    }

                    if (res.data.get_tributos) {
                        this.tributosSelect = res.data.get_tributos;
                    } else {
                        this.tributosSelect = this.tributos;
                    }

                    if (res.data.ref_id && res.data.get_responsabilidad_fiscal) {
                        const arrRefId = res.data.ref_id ? res.data.ref_id.split(';') : [];
                        const arrResponsabilidadFiscal = [];
                        arrRefId.forEach(element => {
                            const resultado = res.data.get_responsabilidad_fiscal.find(valor => valor.ref_codigo === element && valor.estado === 'ACTIVO');

                            if (resultado !== undefined)
                                arrResponsabilidadFiscal.push(element);
                        });

                        if (!this.ver) {
                            this.ref_id.setValue(arrResponsabilidadFiscal);
                        } else {
                            this.ref_id.setValue(arrRefId);
                        }
                    }

                    this.adq_identificacion.setValue(res.data.adq_identificacion);
                    this.adq_id_personalizado.setValue(res.data.adq_id_personalizado);
                    this.adq_razon_social.setValue((res.data.adq_razon_social !== null) ? res.data.adq_razon_social : '');
                    this.adq_nombre_comercial.setValue((res.data.adq_nombre_comercial !== null) ? res.data.adq_nombre_comercial : '');
                    this.adq_primer_apellido.setValue((res.data.adq_primer_apellido !== null) ? res.data.adq_primer_apellido : '');
                    this.adq_segundo_apellido.setValue((res.data.adq_segundo_apellido !== null) ? res.data.adq_segundo_apellido : '');
                    this.adq_primer_nombre.setValue((res.data.adq_primer_nombre !== null) ? res.data.adq_primer_nombre : '');
                    this.adq_otros_nombres.setValue((res.data.adq_otros_nombres !== null) ? res.data.adq_otros_nombres : '');
                    this.adq_direccion.setValue(res.data.adq_direccion);
                    this.adq_direccion_domicilio_fiscal.setValue(res.data.adq_direccion_domicilio_fiscal);
                    this.adq_nombre_contacto.setValue(res.data.adq_nombre_contacto);
                    this.adq_telefono.setValue(res.data.adq_telefono);
                    this.adq_fax.setValue(res.data.adq_fax);
                    this.adq_correo.setValue(res.data.adq_correo);
                    this.adq_matricula_mercantil.setValue(res.data.adq_matricula_mercantil);
                    this.adq_notas.setValue(res.data.adq_notas);
                    this.adq_correos_notificacion.setValue(res.data.adq_correos_notificacion ? res.data.adq_correos_notificacion.split(',') : []);

                    let adqInformacionPersonalizada: any;
                    if(res.data.adq_informacion_personalizada !== null && res.data.adq_informacion_personalizada !== undefined && res.data.adq_informacion_personalizada !== '') {
                        adqInformacionPersonalizada = JSON.parse(res.data.adq_informacion_personalizada);
                    }

                    if(
                        res.data.get_configuracion_obligado_facturar_electronicamente.ofe_informacion_personalizada_adquirente !== undefined &&
                        res.data.get_configuracion_obligado_facturar_electronicamente.ofe_informacion_personalizada_adquirente !== null &&
                        res.data.get_configuracion_obligado_facturar_electronicamente.ofe_informacion_personalizada_adquirente.length > 0
                    ) {
                        let arrTemp = res.data.get_configuracion_obligado_facturar_electronicamente.ofe_informacion_personalizada_adquirente;
                        arrTemp.map(campo => {
                            this.camposInfoPersonalizada.push({title: campo});

                            let valorCampo = '';
                            if(adqInformacionPersonalizada)
                                valorCampo = adqInformacionPersonalizada[campo] !== undefined && adqInformacionPersonalizada[campo] != '' ? adqInformacionPersonalizada[campo] : '';
                                
                            this.arrInfoPersonalizada.push(this._formBuilder.control(valorCampo));
                        });
                    }

                    let i = 0;
                    while (i <  this.tipoOrganizaciones.length) {
                        if (this.tipoOrganizaciones[i].toj_id === res.data.toj_id) {
                            this.initOrganizacion = this.tipoOrganizaciones[i].toj_codigo;
                            break;
                        }
                        i++;
                    }

                    let tributos = [];
                    if (res.data.get_tributos){
                        res.data.get_tributos.forEach(el => {
                            tributos.push(el.get_detalle_tributo.tri_codigo);
                        });
                    }

                    const arrTributos = [];
                    tributos.forEach(element => {
                        arrTributos.push(element);
                    });

                    this.responsable_tributos.setValue(arrTributos);

                    this.estado.setValue(res.data.estado);
                    if (res.data.get_contactos){
                        res.data.get_contactos.forEach(el => {
                            this.llenarCamposGroup(el.con_tipo, el);
                        });
                    }
                    if (res.data.get_parametro_pais){
                        this.paisObjeto['pai_id'] = res.data.get_parametro_pais.pai_id;
                        this.paisObjeto['pai_codigo'] = res.data.get_parametro_pais.pai_codigo;
                        this.paisObjeto['pai_codigo_descripion'] = res.data.get_parametro_pais.pai_codigo + ' - ' + res.data.get_parametro_pais.pai_descripcion;
                    }
                    if (res.data.get_parametro_departamento){
                        this.departamentoObjeto['dep_id'] = res.data.get_parametro_departamento.dep_id;
                        this.departamentoObjeto['dep_codigo'] = res.data.get_parametro_departamento.dep_codigo;
                        this.departamentoObjeto['dep_codigo_descripion'] = res.data.get_parametro_departamento.dep_codigo + ' - ' + res.data.get_parametro_departamento.dep_descripcion;
                    }
                    if (res.data.get_parametro_municipio){
                        this.municipioObjeto['mun_id'] = res.data.get_parametro_municipio.mun_id;
                        this.municipioObjeto['mun_codigo'] = res.data.get_parametro_municipio.mun_codigo;
                        this.municipioObjeto['mun_codigo_descripion'] = res.data.get_parametro_municipio.mun_codigo + ' - ' + res.data.get_parametro_municipio.mun_descripcion;
                    }
                    // if (res.data.get_responsabilidad_fiscal){
                    //     //this.responsabilidadFiscalObjeto['ref_id'] = res.data.get_responsabilidad_fiscal.ref_id;
                    //     // this.responsabilidadFiscalObjeto['ref_codigo'] = res.data.get_responsabilidad_fiscal.ref_codigo;
                    //     // this.responsabilidadFiscalObjeto['ref_codigo_descripion'] = res.data.get_responsabilidad_fiscal.ref_codigo + ' - ' + res.data.get_responsabilidad_fiscal.ref_descripcion;
                    // }
                    if (res.data.get_parametro_domicilio_fiscal_pais) {
                        this.paisFiscalObjeto['pai_id'] = res.data.get_parametro_domicilio_fiscal_pais.pai_id;
                        this.paisFiscalObjeto['pai_codigo'] = res.data.get_parametro_domicilio_fiscal_pais.pai_codigo;
                        this.paisFiscalObjeto['pai_codigo_descripion'] = res.data.get_parametro_domicilio_fiscal_pais.pai_codigo + ' - ' + res.data.get_parametro_domicilio_fiscal_pais.pai_descripcion;
                    }
                    if (res.data.get_parametro_domicilio_fiscal_departamento) {
                        this.departamentoFiscalObjeto['dep_id'] = res.data.get_parametro_domicilio_fiscal_departamento.dep_id;
                        this.departamentoFiscalObjeto['dep_codigo'] = res.data.get_parametro_domicilio_fiscal_departamento.dep_codigo;
                        this.departamentoFiscalObjeto['dep_codigo_descripion'] = res.data.get_parametro_domicilio_fiscal_departamento.dep_codigo + ' - ' + res.data.get_parametro_domicilio_fiscal_departamento.dep_descripcion;
                    }
                    if (res.data.get_parametro_domicilio_fiscal_municipio) {
                        this.municipioFiscalObjeto['mun_id'] = res.data.get_parametro_domicilio_fiscal_municipio.mun_id;
                        this.municipioFiscalObjeto['mun_codigo'] = res.data.get_parametro_domicilio_fiscal_municipio.mun_codigo;
                        this.municipioFiscalObjeto['mun_codigo_descripion'] = res.data.get_parametro_domicilio_fiscal_municipio.mun_codigo + ' - ' + res.data.get_parametro_domicilio_fiscal_municipio.mun_descripcion;
                    }
                    if (res.data.get_codigo_postal){
                        this.codigoPostalObjeto['cpo_id'] = res.data.get_codigo_postal.cpo_id;
                        this.codigoPostalObjeto['cpo_codigo'] = res.data.get_codigo_postal.cpo_codigo;
                    }
                    if (res.data.get_codigo_postal_domicilio_fiscal) {
                        this.codigoPostalFiscalObjeto['cpo_id'] = res.data.get_codigo_postal_domicilio_fiscal.cpo_id;
                        this.codigoPostalFiscalObjeto['cpo_codigo'] = res.data.get_codigo_postal_domicilio_fiscal.cpo_codigo;
                    }

                    if (res.data.get_tiempo_aceptacion_tacita){
                        this.tatObjeto['tat_id'] = res.data.get_tiempo_aceptacion_tacita.tat_id;
                        this.tatObjeto['tat_codigo'] = res.data.get_tiempo_aceptacion_tacita.tat_codigo;
                        this.tatObjeto['tat_codigo_descripcion'] = res.data.get_tiempo_aceptacion_tacita.tat_codigo + ' - ' + res.data.get_tiempo_aceptacion_tacita.tat_descripcion;
                    }

                    if (res.data.get_parametro_tipo_documento && res.data.get_parametro_tipo_documento.tdo_codigo === this.codigoNIT){
                        if(res.data.adq_identificacion){
                            this.loading(true);
                            this._commonsService.calcularDV(res.data.adq_identificacion).subscribe(
                                result => {
                                    if(result.data || result.data === 0) {
                                        this.DV.setValue(result.data);
                                        this.loading(false);
                                    } else {
                                        this.DV.setValue(null);
                                        this.loading(false);
                                    }
                            });
                        }
                    } else{
                        this.initDV = false;
                        this.DV.setValue(null);
                        this.loading(false);
                    }
                    this.pai_id.setValue(this.paisObjeto);
                    this.dep_id.setValue(this.departamentoObjeto);
                    this.mun_id.setValue(this.municipioObjeto);
                    this.pai_id_domicilio_fiscal.setValue(this.paisFiscalObjeto);
                    this.dep_id_domicilio_fiscal.setValue(this.departamentoFiscalObjeto);
                    this.mun_id_domicilio_fiscal.setValue(this.municipioFiscalObjeto);
                    this.cpo_id_domicilio_fiscal.setValue(this.codigoPostalFiscalObjeto);
                    // this.ref_id.setValue(this.responsabilidadFiscalObjeto);
                    this.cpo_id.setValue(this.codigoPostalObjeto);
                    this.tat_id.setValue(this.tatObjeto);
                    this.objMagic['fecha_creacion'] = res.data.fecha_creacion;
                    this.objMagic['fecha_modificacion'] = res.data.fecha_modificacion;
                    this.objMagic['estado'] = res.data.estado;
                    // Obligatoriedad de campos que aplica para DHL Express
                    this.camposObligatoriosDhlExpress(this.ofe_id.value, this.tdo_id.value);
                } else
                    this.loading(false);
            },
            error => {
                // const texto_errores = this.parseError(error);
                this.loading(false);
                let ruta = this.tipoAdquirente == 'vendedor' ? 'es' : 's';
                this.mostrarErrores(error, 'Error al cargar el ' + this.capitalize(this.tipoAdquirente), `/configuracion/${this.tipoAdquirente}${ruta}`, this._router);
                // this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al cargar el Adquirente', 'Ok', 'btn btn-danger', `/configuracion/${this.tipoAdquirente}s`, this._router);
            }
        );
    }

    llenarCamposGroup(form_group, campos) {
        const formGroup = (this.formulario.controls['DatosContactos'].get(form_group) as FormGroup);
        const arrFormulario = formGroup.value;
        for (const x in arrFormulario) {
            arrFormulario[x] = campos[x];
            formGroup.controls[x.trim()].setValue(campos[x]);
        }
        // this.changeContactoGroup(form_group);
    }

    /**
     * Construccion del formulario principal.
     *
     */
    buildFormulario() {
        // this.proveedores = this._formBuilder.array([]);
        this.formulario = this._formBuilder.group({
            DatosGenerales: this.getFormularioDatosGenerales(),
            Ubicacion: this.buildFormularioUbicacion(),
            UbicacionFiscal: this.buildFormularioUbicacionFiscal(),
            DatosTributarios : this.buildFormularioDatosTributarios(),
            InformacionAdicional : this.buildFormularioInformacionAdicional(),
            DatosOfe: this.buildFormularioDatosOfe(),
            DatosContactos: this.buildFormularioContactos(),
            ReglaAceptacionTacita: this.buildFormularioDatosTat(),
            CorreosNotificacion: this.buildFormularioNotificaciones(),
            InformacionPersonalizada: this.buildFormularioInformacionPersonalizada()
            // proveedores: this.proveedores
        });
    }

    get arrInfoPersonalizada() {
        return this.informacionPersonalizada.get("arrInfoPersonalizada") as FormArray;
    }

    /**
     * Construccion del formulario de información personalizada.
     *
     */
    buildFormularioInformacionPersonalizada() {
        this.informacionPersonalizada = this._formBuilder.group({
            arrInfoPersonalizada: this._formBuilder.array([])
        });

        return this.informacionPersonalizada;
    }

    /**
     * Construccion del formulario de datos personales.
     *
     */
    getFormularioDatosGenerales() {
        this.datosGenerales = this._formBuilder.group({
            tdo_id: this.requerido(),
            toj_id: this.requerido(),
            adq_identificacion: this.requeridoMaxlong(20),
            adq_id_personalizado: [''],
            adq_razon_social: this.maxlong(255),
            adq_nombre_comercial: this.maxlong(255),
            adq_primer_apellido: this.maxlong(100),
            adq_segundo_apellido: [''],
            adq_primer_nombre: this.maxlong(100),
            adq_otros_nombres: [''],
            DV: [''],
            estado: ['']
        });
        this.tdo_id = this.datosGenerales.controls['tdo_id'];
        this.toj_id = this.datosGenerales.controls['toj_id'];
        this.adq_identificacion = this.datosGenerales.controls['adq_identificacion'];
        this.adq_id_personalizado = this.datosGenerales.controls['adq_id_personalizado'];
        this.adq_razon_social = this.datosGenerales.controls['adq_razon_social'];
        this.adq_nombre_comercial = this.datosGenerales.controls['adq_nombre_comercial'];
        this.adq_primer_apellido = this.datosGenerales.controls['adq_primer_apellido'];
        this.adq_segundo_apellido = this.datosGenerales.controls['adq_segundo_apellido'];
        this.adq_primer_nombre = this.datosGenerales.controls['adq_primer_nombre'];
        this.adq_otros_nombres = this.datosGenerales.controls['adq_otros_nombres'];
        this.DV = this.datosGenerales.controls['DV'];
        this.estado = this.datosGenerales.controls['estado'];
        return this.datosGenerales;
    }

    /**
     * Construcción del formulario de ubicacion.
     *
     */
    buildFormularioUbicacion() {
        this.ubicacion   = this._formBuilder.group({
            pai_id       : [''],
            dep_id       : [''],
            mun_id       : [''],
            adq_direccion: this.maxlong(255),            
            cpo_id       : this.maxlong(50)
        });

        this.pai_id        = this.ubicacion.controls['pai_id'];
        this.dep_id        = this.ubicacion.controls['dep_id'];
        this.mun_id        = this.ubicacion.controls['mun_id'];
        this.adq_direccion = this.ubicacion.controls['adq_direccion'];        
        this.cpo_id        = this.ubicacion.controls['cpo_id'];

        return this.ubicacion;
    }

    /**
     * Construcción del formulario de domicilio fiscal.
     *
     */
    buildFormularioUbicacionFiscal() {
        this.ubicacionFiscal = this._formBuilder.group({
            pai_id_domicilio_fiscal       : [''],
            dep_id_domicilio_fiscal       : [''],
            mun_id_domicilio_fiscal       : [''],
            cpo_id_domicilio_fiscal       : this.maxlong(50),
            adq_direccion_domicilio_fiscal: this.maxlong(255)
        });

        this.pai_id_domicilio_fiscal        = this.ubicacionFiscal.controls['pai_id_domicilio_fiscal'];
        this.dep_id_domicilio_fiscal        = this.ubicacionFiscal.controls['dep_id_domicilio_fiscal'];
        this.mun_id_domicilio_fiscal        = this.ubicacionFiscal.controls['mun_id_domicilio_fiscal'];
        this.adq_direccion_domicilio_fiscal = this.ubicacionFiscal.controls['adq_direccion_domicilio_fiscal'];
        this.cpo_id_domicilio_fiscal        = this.ubicacionFiscal.controls['cpo_id_domicilio_fiscal'];

        return this.ubicacionFiscal;
    }

    /**
     * Construcción del formulario de información adicional.
     *
     */
    buildFormularioInformacionAdicional() {
        this.informacionAdicional = this._formBuilder.group({
            adq_nombre_contacto: this.maxlong(255),
            adq_telefono: this.maxlong(50),
            adq_fax: this.maxlong(50),
            adq_correo: ['', Validators.compose(
                [
                    Validators.email,
                    Validators.maxLength(255),
                    Validators.minLength(10)
                ],
            )],
            adq_matricula_mercantil: this.maxlong(255),
            adq_notas: [],
        });
        this.adq_nombre_contacto     = this.informacionAdicional.controls['adq_nombre_contacto'];
        this.adq_telefono            = this.informacionAdicional.controls['adq_telefono'];
        this.adq_fax                 = this.informacionAdicional.controls['adq_fax'];
        this.adq_correo              = this.informacionAdicional.controls['adq_correo'];
        this.adq_matricula_mercantil = this.informacionAdicional.controls['adq_matricula_mercantil'];
        this.adq_notas               = this.informacionAdicional.controls['adq_notas'];
        return this.informacionAdicional;
    }

    /**
     * Construccion de los datos tributarios.
     *
     */
    buildFormularioDatosTributarios() {
        this.datosTributarios = this._formBuilder.group({
            rfi_id: [''],
            ref_id: [''],
            responsable_tributos: [''],
            ipv_id: ['']
        });

        this.rfi_id = this.datosTributarios.controls['rfi_id'];
        this.ref_id = this.datosTributarios.controls['ref_id'];
        this.responsable_tributos = this.datosTributarios.controls['responsable_tributos'];
        this.ipv_id = this.datosTributarios.controls['ipv_id'];
        return this.datosTributarios;
    }

    /**
     * Construccion del formgroup para el campo de notificaciones.
     *
     */
    buildFormularioNotificaciones() {
        this.correosNotificacion = this._formBuilder.group({
            adq_correos_notificacion: ['']
        });
        this.adq_correos_notificacion = this.correosNotificacion.controls['adq_correos_notificacion'];
        return this.correosNotificacion;
    }

    /**
     * Construccion de los datos del ofe.
     *
     */
    buildFormularioDatosOfe() {
        this.ofeSelector = this._formBuilder.group({
            ofe_id: this.requerido(),
        });
        this.ofe_id = this.ofeSelector.controls['ofe_id'];
        return this.ofeSelector;
    }

    /**
     * Construccion de los datos de aceptacion tacita.
     *
     */
    buildFormularioDatosTat() {
        this.reglaAceptacionTacita = this._formBuilder.group({
            tat_id: [],
        });
        this.tat_id = this.reglaAceptacionTacita.controls['tat_id'];
        return this.reglaAceptacionTacita;
    }

    /**
     * Construye el formulario de contactos (FormArray - Cuidado).
     *
     */
    buildFormularioContactos(): any {
        this.deliveryContact = this.buildFormularioContacto(this._formBuilder, 'DeliveryContact');
        this.accountingContact = this.buildFormularioContacto(this._formBuilder, 'AccountingContact');
        this.buyerContact = this.buildFormularioContacto(this._formBuilder, 'BuyerContact');
        this.contactos = this._formBuilder.group({
            DeliveryContact: this.deliveryContact,
            AccountingContact: this.accountingContact,
            BuyerContact: this.buyerContact
        });
        return this.contactos;
    }

    /**
     * Permite regresar a la lista de adquirentes.
     *
     */
    regresar() {
        switch (this.tipoAdquirente) {
            case 'adquirente':
                this._router.navigate(['configuracion/adquirentes']);
                break;
            case 'autorizado':
                this._router.navigate(['configuracion/autorizados']);
                break;
            case 'responsable':
                this._router.navigate(['configuracion/responsables']);
                break;
            case 'vendedor':
                this._router.navigate(['configuracion/vendedores']);
                break;
            default:
                break;
        }
    }

    /**
     * Inicializa la data necesaria para la construcción del adquirente.
     *
     */
    private initForBuild() {
        this.loading(true);
        this._commonsService.getDataInitForBuild(this.tipoAdquirente == 'vendedor' ? 'tat=true&origen=adquirentes&aplicaPara=DS' : 'tat=true&origen=adquirentes&aplicaPara=DE').subscribe(
            result =>
            {
                this.loading(false);
                this.ofes = result.data.ofes;
                this.paises = result.data.paises;
                this.tipoDocumentos = result.data.tipo_documentos;
                this.tipoOrganizaciones = result.data.tipo_organizaciones;
                this.tipoRegimen = result.data.tipo_regimen;
                this.tipoProcedenciaVendedor = result.data.procedencia_vendedor;
                // this.ResFiscal = result.data.responsabilidades_fiscales;
                this.tiemposAceptacionTacita = result.data.tiempo_aceptacion_tacita;
                this.tiemposAceptacionTacita.map(el => {
                    el.tat_codigo_descripcion = el.tat_codigo + ' - ' + el.tat_descripcion;
                });
                this.responsabilidadesFiscales = result.data.responsabilidades_fiscales;
                this.tributos = result.data.tributos;
                if (this._adq_identificacion) {
                    this.loading(true);
                    this.loadAdquirente();
                } else {
                    this.tipoDocumentoSelect                        = result.data.tipo_documentos;
                    this.tipoOrganizacionSelect                     = result.data.tipo_organizaciones;
                    this.regimenFiscalSelect                        = result.data.tipo_regimen;
                    this.procedenciaVendedorSelect                  = result.data.procedencia_vendedor;
                    this.responsabilidadesFiscalesSelect            = result.data.responsabilidades_fiscales;
                    this.tributosSelect                             = result.data.tributos;
                }
            }, error => {
                this.loading(false);
                const texto_errores = this.parseError(error);
                this.showError(texto_errores, 'error', 'Error al cargar los parámetros', 'Ok', 'btn btn-danger');
            }
        );
    }

    /**
     * Crea o actualiza un nuevo registro.
     *
     * @param values
     */
    public resourceAdquirente(values) {
        const payload = this.getPayload();
        switch (this.tipoAdquirente) {
            case 'adquirente':
                payload['adq_tipo_adquirente'] =  'SI';
                break;
            case 'autorizado':
                payload['adq_tipo_autorizado'] =  'SI';
                break;
            case 'responsable':
                payload['adq_tipo_responsable_entrega'] =  'SI';
                break;
            case 'vendedor':
                payload['adq_tipo_vendedor_ds'] =  'SI';
                break;
            default:
                break;
        }
        
        if (this.formulario.valid) {
            this.loading(true);
            if (this._adq_identificacion) {
                payload['estado'] =  this.estado.value;
                this._configuracionService.updateAdq(payload, this._adq_identificacion, this.ofe_id.value, this._adq_id_personalizado).subscribe(
                    response => {
                        let ruta = this.tipoAdquirente == 'vendedor' ? 'es' : 's';
                        this.loading(false);
                        this.showSuccess('<h3>Actualización exitosa</h3>', 'success', this.capitalize(this.tipoAdquirente) + ' actualizado exitosamente', 'Ok', 'btn btn-success', `/configuracion/${this.tipoAdquirente}${ruta}`, this._router);
                    },
                    error => {
                        this.loading(false);
                        const texto_errores = this.parseError(error);
                        this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al actualizar el ' + this.capitalize(this.tipoAdquirente), 'Ok', 'btn btn-danger');
                    });
            } else {
                this._configuracionService.create(payload).subscribe(
                    response => {
                        let ruta = this.tipoAdquirente == 'vendedor' ? 'es' : 's';
                        this.loading(false);
                        this.showSuccess('<h3>' + '</h3>', 'success', this.capitalize(this.tipoAdquirente) + ' creado exitosamente', 'Ok', 'btn btn-success', `/configuracion/${this.tipoAdquirente}${ruta}`, this._router);
                    },
                    error => {
                        this.loading(false);
                        const texto_errores = this.parseError(error);
                        this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al guardar el ' + this.capitalize(this.tipoAdquirente), 'Ok', 'btn btn-danger');
                    });
                }
        }
    }

    /**
     * Crea un json para enviar los campos del formulario.
     *
     */
    getPayload(){
        const contactos = [];
        const delivery = {};
        const accounting = {};
        const buyer = {};
        Object.keys(this.deliveryContact.controls).forEach(key => {
            if (this.deliveryContact.controls[key].value) {
                delivery[key] = this.deliveryContact.controls[key].value;
            }
        });
        if (Object.keys(delivery).length > 1) {
            contactos.push(delivery);
        }
        Object.keys(this.accountingContact.controls).forEach(key => {
            if (this.accountingContact.controls[key].value) {
                accounting[key] = this.accountingContact.controls[key].value;
            }
        });
        if (Object.keys(accounting).length > 1) {
            contactos.push(accounting);
        }
        Object.keys(this.buyerContact.controls).forEach(key => {
            if (this.buyerContact.controls[key].value) {
                buyer[key] = this.buyerContact.controls[key].value;
            }
        });
        if (Object.keys(buyer).length > 1) {
            contactos.push(buyer);
        }

        let informacionPersonalizada: any;
        if(this.camposInfoPersonalizada.length > 0) {
            informacionPersonalizada = {};
            let ctrlInformacionAdicional = this.informacionPersonalizada.get("arrInfoPersonalizada") as FormArray
            ctrlInformacionAdicional.controls.forEach((ctrlInfoAdicional, index) => {
                if(ctrlInfoAdicional.value !== undefined && ctrlInfoAdicional.value !== null && ctrlInfoAdicional.value !== '') {
                    let nombreColumna = this.camposInfoPersonalizada[index].title;
                    informacionPersonalizada[nombreColumna] = ctrlInfoAdicional.value;
                }
            });

            if(Object.keys(informacionPersonalizada).length === 0)
                informacionPersonalizada = null;
        }

        let payload = {
            ofe_identificacion: this.ofe_id.value,
            adq_identificacion : this.adq_identificacion.value,
            adq_id_personalizado : this.adq_id_personalizado.value,
            adq_razon_social : this.adq_razon_social.value,
            adq_nombre_comercial : this.adq_nombre_comercial.value,
            adq_primer_apellido : this.adq_primer_apellido.value,
            adq_segundo_apellido : this.adq_segundo_apellido.value,
            adq_primer_nombre : this.adq_primer_nombre.value,
            adq_otros_nombres : this.adq_otros_nombres.value,
            tdo_codigo : this.tdo_id.value,
            toj_codigo : this.toj_id.value,
            pai_codigo : this.pai_id.value && this.pai_id.value.pai_codigo ? this.pai_id.value.pai_codigo : null,
            dep_codigo : this.dep_id.value && this.dep_id.value.dep_codigo ? this.dep_id.value.dep_codigo : null,
            mun_codigo : this.mun_id.value && this.mun_id.value.mun_codigo ? this.mun_id.value.mun_codigo : null,
            cpo_codigo : this.cpo_id.value && this.cpo_id.value.cpo_codigo ? this.cpo_id.value.cpo_codigo : null,
            pai_codigo_domicilio_fiscal: this.pai_id_domicilio_fiscal.value && this.pai_id_domicilio_fiscal.value.pai_codigo ? this.pai_id_domicilio_fiscal.value.pai_codigo : null,
            dep_codigo_domicilio_fiscal: this.dep_id_domicilio_fiscal.value && this.dep_id_domicilio_fiscal.value.dep_codigo ? this.dep_id_domicilio_fiscal.value.dep_codigo : null,
            mun_codigo_domicilio_fiscal: this.mun_id_domicilio_fiscal.value && this.mun_id_domicilio_fiscal.value.mun_codigo ? this.mun_id_domicilio_fiscal.value.mun_codigo : null,
            cpo_codigo_domicilio_fiscal: this.cpo_id_domicilio_fiscal.value && this.cpo_id_domicilio_fiscal.value.cpo_codigo ? this.cpo_id_domicilio_fiscal.value.cpo_codigo : null,
            adq_direccion_domicilio_fiscal: this.adq_direccion_domicilio_fiscal.value,
            adq_direccion : this.adq_direccion.value,
            adq_correos_notificacion : this.adq_correos_notificacion && this.adq_correos_notificacion.value ? this.adq_correos_notificacion.value.join(',') : '',
            rfi_codigo : this.rfi_id.value,
            ref_codigo : this.ref_id.value,
            ipv_codigo : this.ipv_id.value,
            adq_nombre_contacto : this.adq_nombre_contacto.value,
            adq_telefono : this.adq_telefono.value,
            adq_fax : this.adq_fax.value,
            adq_correo : this.adq_correo.value,
            adq_matricula_mercantil : this.adq_matricula_mercantil.value,
            adq_notas : this.adq_notas.value,
            tat_codigo : this.tat_id.value && this.tat_id.value.tat_codigo ? this.tat_id.value.tat_codigo : null,
            responsable_tributos: this.responsable_tributos.value,
            contactos: contactos,
            adq_informacion_personalizada: informacionPersonalizada !== undefined && informacionPersonalizada !== null && informacionPersonalizada !== '' ? informacionPersonalizada : null
        };

        if (!this.tat_id || !this.tat_id.value) {
            delete payload.tat_codigo;
        }
        if (this.tipoAdquirente === 'autorizado' || this.tipoAdquirente === 'responsable'){
            delete payload.contactos;
            delete payload.adq_correos_notificacion;
        } else if (this.tipoAdquirente === 'vendedor') {
            delete payload.contactos;
        } else if (this.tipoAdquirente !== 'vendedor') {
            delete payload.ipv_codigo;
        }

        return payload;
    }

    /**
     * Comprueba si el Adquirente ya está registrado en el sistema.
     * 
     */
    checkIfAdqExists() {
        if(!this.ofe_id.value || this.ofe_id.value === ''){
            this.loading(false);
            // this.showTimerAlert('<h3>Por favor debe seleccionar el OFE primero</h3>', 'warning', 'center', 2000);
            return ;
        }
        this.loading(true);
        if (this.adq_identificacion.value.trim() !== '' && !this.editar && (this.adq_identificacion.value !== this.ultimoComprobado || this.ofe_id.value !== this.ultimoOfeComprobado) && !this.ver) {
            this._configuracionService.checkIfAdqExists(this.ofe_id.value, this.adq_identificacion.value, this.adq_id_personalizado.value).subscribe(
            res => {
                this.loading(false);
                this.ultimoComprobado = this.adq_identificacion.value;
                this.ultimoOfeComprobado = this.ofe_id.value;
                const tipo = [];
                let activarComo = '';
                let editar = false;
                let casoEspecial = false;
                if (res.data){
                    if (res.data.adq_tipo_adquirente && res.data.adq_tipo_adquirente === 'SI') {
                        tipo.push('ADQUIRENTE');
                    }
                    if (res.data.adq_tipo_autorizado && res.data.adq_tipo_autorizado === 'SI') {
                        tipo.push('AUTORIZADO');
                    }
                    if (res.data.adq_tipo_responsable_entrega && res.data.adq_tipo_responsable_entrega === 'SI') {
                        tipo.push('RESPONSABLE ENTREGA DE BIENES');
                    }
                    if (res.data.adq_tipo_vendedor_ds && res.data.adq_tipo_vendedor_ds === 'SI') {
                        tipo.push('VENDEDOR DOCUMENTO SOPORTE');
                    }
                }
                switch (this.tipoAdquirente) {
                    case 'adquirente':
                        activarComo = 'Adquirente';
                        if (tipo.includes('ADQUIRENTE')) {
                            casoEspecial = true;
                        }
                        break;
                    case 'autorizado':
                        activarComo = 'Autorizado';
                        if (tipo.includes('AUTORIZADO')) {
                            casoEspecial = true;
                        }
                        break;
                    case 'responsable':
                        activarComo = 'Responsable Entrega de Bienes';
                        if (tipo.includes('RESPONSABLE ENTREGA DE BIENES')) {
                            casoEspecial = true;
                        }
                        break;
                    case 'vendedor':
                        activarComo = 'Vendedor Documento Soporte';
                        if (tipo.includes('VENDEDOR DOCUMENTO SOPORTE')) {
                            casoEspecial = true;
                        }
                        break;
                    default:
                        break;
                }
                let mensaje = '';
                if (tipo.length > 1) {
                    mensaje += 'La identificación digitada ya se encuentra registrada en el sistema como ' + tipo.join(' y ') + '. ¿Desea activarla como Nit ' + activarComo + '?';
                }
                if (tipo.length === 1) {
                    mensaje += 'La identificación digitada ya se encuentra registrada en el sistema como ' + tipo[0] + '. ¿Desea activarla como Nit ' + activarComo + '?';
                }
                if (tipo.length === 3) {
                    mensaje = 'La identificación digitada ya se encuentra registrada en el sistema como ADQUIRENTE, AUTORIZADO, RESPONSABLE ENTREGA DE BIENES y VENDEDOR DOCUMENTO SOPORTE.';
                }
                if (casoEspecial && tipo.length !== 3) {
                    mensaje = 'La identificación digitada ya se encuentra registrada en el sistema como ' + activarComo.toLocaleUpperCase() + '. ¿Desea editarla?';
                    editar = true;
                }
                if (tipo.length > 0 && tipo.length < 3){
                    swal({
                        html: mensaje,
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
                            let ruta = this.tipoAdquirente == 'vendedor' ? 'es' : 's';
                            this.loading(true);
                            if (editar) {
                                if(this.adq_id_personalizado.value !== '' && this.adq_id_personalizado.value !== null && this.adq_id_personalizado.value !== undefined)
                                    this._router.navigate([`configuracion/${this.tipoAdquirente}${ruta}/editar-${this.tipoAdquirente}/${this.adq_identificacion.value}/${this.ofe_id.value}/${this.adq_id_personalizado.value}`]);
                                else
                                    this._router.navigate([`configuracion/${this.tipoAdquirente}${ruta}/editar-${this.tipoAdquirente}/${this.adq_identificacion.value}/${this.ofe_id.value}`]);
                            }
                            else {
                                this.loading(true);
                                this._configuracionService.editarTipoAdquirente(this.tipoAdquirente, res.data.adq_id).subscribe(
                                    response => {
                                        this.loading(false);
                                        if(this.adq_id_personalizado.value !== '' && this.adq_id_personalizado.value !== null && this.adq_id_personalizado.value !== undefined)
                                            this._router.navigate([`configuracion/${this.tipoAdquirente}${ruta}/editar-${this.tipoAdquirente}/${this.adq_identificacion.value}/${this.ofe_id.value}/${this.adq_id_personalizado.value}`]);
                                        else
                                            this._router.navigate([`configuracion/${this.tipoAdquirente}${ruta}/editar-${this.tipoAdquirente}/${this.adq_identificacion.value}/${this.ofe_id.value}`]);
                                    },
                                    error => {
                                        this.loading(false);
                                        const errorString: string = error.message !== undefined ? error.message : 'Error al intentar activar el ' + activarComo;
                                        this.showError(errorString, 'error', 'Error', 'Ok, entiendo', 'btn btn-danger');
                                    }
                                );
                            }
                        }
                    }).catch(swal.noop);
                }
                if (tipo.length === 3) {
                    let ruta = this.tipoAdquirente == 'vendedor' ? 'es' : 's';
                    this.showError(mensaje, 'error', 'Identificación ya se encuentra registrada', 'Ok, entiendo', 'btn btn-danger', `/configuracion/${this.tipoAdquirente}${ruta}`, this._router);
                }
            });
        } else
            this.loading(false);
    }

    /**
     * Monitoriza cuando el valor del select de OFEs cambia.
     * 
     */
    ofeHasChange(ofe){
        this.camposInfoPersonalizada = [];

        this.ofes.forEach(oferente => {
            if(
                oferente.ofe_identificacion === ofe &&
                oferente.ofe_informacion_personalizada_adquirente !== undefined &&
                oferente.ofe_informacion_personalizada_adquirente !== null &&
                oferente.ofe_informacion_personalizada_adquirente.length > 0
            ) {
                let arrTemp = oferente.ofe_informacion_personalizada_adquirente;
                arrTemp.map(campo => {
                    this.camposInfoPersonalizada.push({title: campo});
                    this.arrInfoPersonalizada.push(this._formBuilder.control(''));
                });
            }
        });

        this.checkIfAdqExists();

        // Obligatoriedad de campos que aplica para DHL Express
        this.camposObligatoriosDhlExpress(ofe, this.tdo_id.value);
    }

    /**
     * Verificación sobre cambios en el campo tdo_id
     *
     * @param {string} tdo Tipo de documento
     * @memberof AdquirentesGestionarComponent
     */
    tdoHasChange(tdo) {
        // Obligatoriedad de campos que aplica para DHL Express
        this.camposObligatoriosDhlExpress(this.ofe_id.value, tdo);
    }

    /**
     * Campos que deben ser obligatorios dependiendo del tipo de documento y si el OFE es DHL Express.
     *
     * @param {string} ofe Identificacion del OFE
     * @param {string} tdo Tipo de documento
     * @memberof AdquirentesGestionarComponent
     */
    camposObligatoriosDhlExpress(ofe, tdo) {
        if((ofe === '860502609' || ofe === '830076778') && this.tipoAdquirente == 'adquirente') {
            this.pai_id.setValidators([Validators.required]);
            this.dep_id.setValidators([Validators.required]);
            this.mun_id.setValidators([Validators.required]);
            this.adq_direccion.setValidators([Validators.required]);

            if(tdo == '31') {
                this.maxlengthIdentificacion = 9;
                this.regexIdentificacion = new RegExp("^[0-9]{9}$");

                this.pai_id_domicilio_fiscal.setValidators([Validators.required]);
                this.dep_id_domicilio_fiscal.setValidators([Validators.required]);
                this.mun_id_domicilio_fiscal.setValidators([Validators.required]);
                this.adq_direccion_domicilio_fiscal.setValidators([Validators.required]);
            } else {
                this.maxlengthIdentificacion = 20;
                this.regexIdentificacion = new RegExp("^[0-9a-zA-Z-]{1,20}$")

                this.pai_id_domicilio_fiscal.clearValidators();
                this.dep_id_domicilio_fiscal.clearValidators();
                this.mun_id_domicilio_fiscal.clearValidators();
                this.adq_direccion_domicilio_fiscal.clearValidators();
            }

            this.adq_identificacion.markAsTouched();
        } else {
            this.pai_id.clearValidators();
            this.dep_id.clearValidators();
            this.mun_id.clearValidators();
            this.adq_direccion.clearValidators();

            this.pai_id_domicilio_fiscal.clearValidators();
            this.dep_id_domicilio_fiscal.clearValidators();
            this.mun_id_domicilio_fiscal.clearValidators();
            this.adq_direccion_domicilio_fiscal.clearValidators();
        }

        this.pai_id.updateValueAndValidity();
        this.dep_id.updateValueAndValidity();
        this.mun_id.updateValueAndValidity();
        this.adq_direccion.updateValueAndValidity();

        this.pai_id_domicilio_fiscal.updateValueAndValidity();
        this.dep_id_domicilio_fiscal.updateValueAndValidity();
        this.mun_id_domicilio_fiscal.updateValueAndValidity();
        this.adq_direccion_domicilio_fiscal.updateValueAndValidity();
    }
}
