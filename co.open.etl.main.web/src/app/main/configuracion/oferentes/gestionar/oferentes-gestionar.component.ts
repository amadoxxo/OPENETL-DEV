import {Component, OnDestroy, OnInit, ViewChild, AfterViewInit} from '@angular/core';
import {BaseComponentView} from '../../../core/base_component_view';
import {ActivatedRoute, Router} from '@angular/router';
import {AbstractControl, FormArray, FormBuilder, FormGroup, Validators} from '@angular/forms';
import {Subject} from 'rxjs';
import {CommonsService} from '../../../../services/commons/commons.service';
import {ConfiguracionService} from '../../../../services/configuracion/configuracion.service';
import {OferentesService} from '../../../../services/configuracion/oferentes.service';
import {DomSanitizer} from '@angular/platform-browser';
import {MatAccordion} from '@angular/material/expansion';
import {MatCheckbox} from '@angular/material/checkbox';
import {JwtHelperService} from '@auth0/angular-jwt';
import {Auth} from '../../../../services/auth/auth.service';
import {DatosGeneralesRegistroComponent} from '../../../commons/datos-generales-registro/datos-generales-registro.component';
import {UbicacionOpenComponent} from './../../../commons/ubicacion-open/ubicacion-open.component';
import swal from 'sweetalert2';

@Component({
    selector: 'app-oferentes-gestionar',
    templateUrl: './oferentes-gestionar.component.html',
    styleUrls: ['./oferentes-gestionar.component.scss']
})
export class OferentesGestionarComponent extends BaseComponentView implements OnInit, OnDestroy, AfterViewInit {
    @ViewChild('acordion', {static: false}) acordion: MatAccordion;
    @ViewChild('DatosGenerales', {static: true}) datosGeneralesControl: DatosGeneralesRegistroComponent;
    @ViewChild('domicilioCorrespondencia', {static: false}) domicilioCorrespondencia: UbicacionOpenComponent;
    @ViewChild('adqIdentificacion', {static: false}) adqIdentificacion: MatCheckbox;
    @ViewChild('adqIdPersonalizado', {static: false}) adqIdPersonalizado: MatCheckbox;

    // Usuario en línea
    public usuario                                    : any;
    public objMagic                                   = {};
    public ver                                        : boolean = false;
    public editar                                     : boolean = false;
    // Formulario y controles
    public gestor                                     : OferentesGestionarComponent;
    public formulario                                 : FormGroup;
    public tdo_id                                     : AbstractControl;
    public tat_id                                     : AbstractControl;
    public toj_id                                     : AbstractControl;
    public pai_id                                     : AbstractControl;
    public mun_id                                     : AbstractControl;
    public dep_id                                     : AbstractControl;
    public pai_id_domicilio_fiscal                    : AbstractControl;
    public mun_id_domicilio_fiscal                    : AbstractControl;
    public dep_id_domicilio_fiscal                    : AbstractControl;
    public DV                                         : AbstractControl;
    public responsable_tributos                       : AbstractControl;
    public ofe_identificacion                         : AbstractControl;
    public ofe_razon_social                           : AbstractControl;
    public ofe_nombre_comercial                       : AbstractControl;
    public ofe_primer_apellido                        : AbstractControl;
    public ofe_segundo_apellido                       : AbstractControl;
    public ofe_primer_nombre                          : AbstractControl;
    public ofe_otros_nombres                          : AbstractControl;
    public ofe_direccion                              : AbstractControl;
    public ofe_direccion_domicilio_fiscal             : AbstractControl;
    public ofe_telefono                               : AbstractControl;
    public ofe_nombre_contacto                        : AbstractControl;  
    public ofe_fax                                    : AbstractControl;
    public ofe_notas                                  : AbstractControl; 
    public ofe_correo                                 : AbstractControl;
    public ofe_matricula_mercantil                    : AbstractControl;
    public ofe_actividad_economica                    : AbstractControl;
    public cpo_id                                     : AbstractControl;
    public cpo_id_domicilio_fiscal                    : AbstractControl;
    public ofe_correos_notificacion                   : AbstractControl;
    public ofe_notificacion_un_solo_correo            : AbstractControl;
    public ofe_correos_autorespuesta                  : AbstractControl;
    // public ofe_mostrar_seccion_correos_notificacion: AbstractControl;
    public ofe_enviar_pdf_xml_notificacion            : AbstractControl;
    public ofe_asunto_correos                         : AbstractControl;
    public rfi_id                                     : AbstractControl;
    public ref_id                                     : AbstractControl;
    public sft_id                                     : AbstractControl;
    public sft_id_ds                                  : AbstractControl;
    public ofe_web                                    : AbstractControl;
    public ofe_twitter                                : AbstractControl;
    public ofe_facebook                               : AbstractControl;
    public estado                                     : AbstractControl;
    public nombre_filtro                              : AbstractControl;
    public motivo_codigo                              : AbstractControl;
    public motivo_descripcion                         : AbstractControl;
    public ogf_observacion                            : AbstractControl;
    public tojSeleccionado                            = '';
    public titulo                                     : string;
    public ofe_identificador_unico_adquirente         : AbstractControl;
    public ofe_informacion_personalizada_adquirente   : AbstractControl;

    public initOrganizacion = null;
    public initDV           = null;

    public sptObjeto                   = {};
    public sptDsObjeto                 = {};
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
    public codigoNIT                   = '31';

    proveedores         : FormArray;
    motivos             : FormArray;
    observaciones       : FormArray;
    filtros             : FormArray;

    public aclsUsuario: any;

    recurso = 'Obligado a Facturar Electrónicamente';

    _ofe_id: any;
    _ofe_identificacion: any;
    public paises                         : Array<any> = [];
    public tipoDocumentos                 : Array<any> = [];
    public tipoOrganizaciones             : Array<any> = [];
    public tipoRegimen                    : Array<any> = [];
    public tributos                       : Array<any> = [];
    public responsabilidadesFiscales      : Array<any> = [];
    public ResFiscal                      : Array<any> = [];
    public tiemposAceptacionTacita        : Array<any> = [];
    public modulosItems                   : any        = '';
    public itemsSelectEmision             : Array<any> = [];
    public selectedInfoPersonalizadaAdq   : any[] = [];
    public tipoDocumentoSelect            : any = {};
    public tipoOrganizacionSelect         : any = {};
    public regimenFiscalSelect            : any = {};
    public responsabilidadesFiscalesSelect: any = [];
    public tributosSelect                 : any = [];

    datosGenerales               : FormGroup;
    ubicacion                    : FormGroup;
    ubicacionFiscal              : FormGroup;
    datosTributarios             : FormGroup;
    contactos                    : FormGroup;
    reglaAceptacionTacita        : FormGroup;
    informacionAdicional         : FormGroup;
    correosNotificacion          : FormGroup;
    correosAutorespuesta         : FormGroup;
    envioDirecto                 : FormGroup;
    sftSelector                  : FormGroup;
    redesSociales                : FormGroup;
    asuntoCorreos                : FormGroup;
    proveedoresColeccion         : FormGroup;
    motivosColeccion             : FormGroup;
    observacionesColeccion       : FormGroup;
    configuracionesPersonalizadas: FormGroup;
    // filtrosPersonalizados     : FormGroup;

    despatchContact              : FormGroup;
    accountingContact            : FormGroup;
    sellerContact                : FormGroup;

    objOFE = null;
    usuarioCreador = null;
    propiedadOcultar: string;

    // Private
    private _unsubscribeAll: Subject<any> = new Subject();

    public maxlengthIdentificacion: number = 20;
    public regexIdentificacion: RegExp = /^[0-9a-zA-Z-]{1,20}$/;

    /**
     * Constructor
     * @param _auth
     * @param _router
     * @param _route
     * @param _formBuilder
     * @param _commonsService
     * @param _configuracionService
     * @param _oferentesService
     * @param iconRegistry
     * @param sanitizer
     * @param jwtHelperService
     */
    constructor(
        public _auth                 : Auth,
        private _router              : Router,
        private _route               : ActivatedRoute,
        private _formBuilder         : FormBuilder,
        private _commonsService      : CommonsService,
        private _configuracionService: ConfiguracionService,
        private _oferentesService    : OferentesService,
        private sanitizer            : DomSanitizer,
        private jwtHelperService     : JwtHelperService
    ) {
        super();
        this._configuracionService.setSlug = 'ofe';
        this.gestor = this;
        this.init();
        this.usuario = this.jwtHelperService.decodeToken();
    }

    ngOnInit() {
        this._ofe_id             = this._route.snapshot.params['ofe_id'];
        this._ofe_identificacion = this._route.snapshot.params['ofe_identificacion'];
        this.ver = false;
        this.initForBuild();
        if (this._ofe_identificacion && !this._ofe_id) {
            this.titulo = 'Editar ' + this.recurso;
            this.editar = true;
        } else if (this._ofe_identificacion && this._ofe_id) {
            this.titulo = 'Ver ' + this.recurso;
            this.ver = true;
        } else {
            this.titulo = 'Crear ' + this.recurso;
        }
    }

    /**
     * Vista construida
     */
    ngAfterViewInit() {
        if (this.ver) {
            this.acordion.openAll();
        }
        if (this.toj_id.value && (this.editar || this.ver )) {
            this.datosGeneralesControl.switchControles(this.toj_id.value);
        }
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
        this.aclsUsuario = this._auth.getAcls();
        this.buildFormulario();
    }

    /**
     * Se encarga de cargar los datos de un ofe que se ha seleccionado en el tracking.
     *
     */
    public loadOfe(): void {
        this.loading(true);
        this._configuracionService.get(this._ofe_identificacion).subscribe(
            res => {
                if (res) {
                    if (res.data)
                        res = res.data;

                    // let observaciones_form = (<FormArray>this.formulario.get('observacionesColeccion.observaciones'));
                    // let motivos_rechazo_form = (<FormArray>this.formulario.get('motivosColeccion.motivos'));
                    if (res.get_parametro_tipo_documento) {
                        this.tdo_id.setValue(res.get_parametro_tipo_documento.tdo_codigo);
                        this.tipoDocumentoSelect = res.get_parametro_tipo_documento;
                    }

                    if (res.get_tipo_organizacion_juridica) {
                        this.toj_id.setValue(res.get_tipo_organizacion_juridica.toj_codigo);
                        this.tipoOrganizacionSelect = res.get_tipo_organizacion_juridica;
                    }
                    if (res.get_parametros_regimen_fiscal) {
                        this.rfi_id.setValue(res.get_parametros_regimen_fiscal.rfi_codigo);
                        this.regimenFiscalSelect = res.get_parametros_regimen_fiscal;
                    }

                    if (res.responsabilidades_fiscales) {
                        this.responsabilidadesFiscalesSelect = res.responsabilidades_fiscales;
                    } else {
                        this.responsabilidadesFiscalesSelect = this.responsabilidadesFiscales;
                    }

                    if (res.get_tributos){
                        this.tributosSelect = res.get_tributos;
                    } else {
                        this.tributosSelect = this.tributos;
                    }

                    this.ofe_identificacion.setValue(res.ofe_identificacion);
                    this.ofe_razon_social.setValue((res.ofe_razon_social !== null) ? res.ofe_razon_social : '');
                    this.ofe_nombre_comercial.setValue((res.ofe_nombre_comercial !== null) ? res.ofe_nombre_comercial : '');
                    this.ofe_primer_apellido.setValue((res.ofe_primer_apellido !== null) ? res.ofe_primer_apellido : '');
                    this.ofe_segundo_apellido.setValue((res.ofe_segundo_apellido !== null) ? res.ofe_segundo_apellido : '');
                    this.ofe_primer_nombre.setValue((res.ofe_primer_nombre !== null) ? res.ofe_primer_nombre : '');
                    this.ofe_otros_nombres.setValue((res.ofe_otros_nombres !== null) ? res.ofe_otros_nombres : '');
                    this.ofe_direccion.setValue((res.ofe_direccion !== null) ? res.ofe_direccion : '');
                    if(res.ofe_direcciones_adicionales !== undefined && res.ofe_direcciones_adicionales !== '' && res.ofe_direcciones_adicionales !== null && res.ofe_direcciones_adicionales.length > 0)
                        this.domicilioCorrespondencia.direccionesExistentes(res.ofe_direcciones_adicionales);
                    this.ofe_nombre_contacto.setValue((res.ofe_nombre_contacto !== null) ? res.ofe_nombre_contacto : '');
                    this.ofe_telefono.setValue((res.ofe_telefono !== null) ? res.ofe_telefono : '');
                    this.ofe_fax.setValue((res.ofe_fax !== null) ? res.ofe_fax : '');
                    this.ofe_notas.setValue((res.ofe_notas !== null) ? res.ofe_notas : '');
                    this.ofe_correo.setValue(res.ofe_correo);
                    this.ofe_direccion_domicilio_fiscal.setValue((res.ofe_direccion_domicilio_fiscal !== null) ? res.ofe_direccion_domicilio_fiscal : '');
                    this.ofe_matricula_mercantil.setValue((res.ofe_matricula_mercantil !== null) ? res.ofe_matricula_mercantil : '');
                    if(res.ofe_actividad_economica) {
                        this.ofe_actividad_economica.setValue(res.ofe_actividad_economica.split(';'));
                    }
                    this.ofe_web.setValue((res.ofe_web !== null) ? res.ofe_web : '');
                    this.ofe_twitter.setValue((res.ofe_twitter !== null) ? res.ofe_twitter : '');
                    this.ofe_facebook.setValue((res.ofe_facebook !== null) ? res.ofe_facebook : '');
                    this.ofe_asunto_correos.setValue((res.ofe_asunto_correos !== null) ? res.ofe_asunto_correos : '');

                    // RESPONSABILIDADES FISCALES cuando llega como array
                    let responsabilidadesFiscales = [];
                    if (res.responsabilidades_fiscales){
                        res.responsabilidades_fiscales.forEach(el => {
                            responsabilidadesFiscales.push(el.ref_codigo);
                        });
                    } else {
                        // RESPONSABILIDADES FISCALES cuando llega como string separado por (;)
                        if (res.ref_id)
                            responsabilidadesFiscales = res.ref_id ? res.ref_id.split(';') : [];
                    }

                    const arrResponsabilidadFiscal = [];
                    responsabilidadesFiscales.forEach(element => {
                        const resultado = res.responsabilidades_fiscales.find(valor => valor.ref_codigo === element && valor.estado === 'ACTIVO');
                        
                        if (resultado !== undefined)
                            arrResponsabilidadFiscal.push(element);
                    });

                    if (!this.ver) {
                        this.ref_id.setValue(arrResponsabilidadFiscal);
                    } else {
                        this.ref_id.setValue(responsabilidadesFiscales);
                    }
                    //FIN RESPONSABILIDADES_FISCALES
                    
                    if (res.get_tipo_organizacion_juridica)
                        this.initOrganizacion = res.get_tipo_organizacion_juridica.toj_codigo;

                    this.ofe_correos_notificacion.setValue(res.ofe_correos_notificacion ? res.ofe_correos_notificacion.split(',') : []);
                    this.ofe_correos_autorespuesta.setValue(res.ofe_correos_autorespuesta ? res.ofe_correos_autorespuesta.split(',') : []);
                    this.ofe_notificacion_un_solo_correo.setValue((res.ofe_notificacion_un_solo_correo !== null && res.ofe_notificacion_un_solo_correo !== undefined && res.ofe_notificacion_un_solo_correo !== '') ? res.ofe_notificacion_un_solo_correo : 'NO');

                    const tributos = [];
                    if (res.get_tributos){
                        res.get_tributos.forEach(el => {
                            tributos.push(el.get_detalle_tributo.tri_codigo);
                        });
                    }
                    
                    const arrTributos = [];
                    tributos.forEach(element => {
                        arrTributos.push(element);
                    });

                    this.responsable_tributos.setValue(arrTributos);

                    this.estado.setValue(res.estado);
                    if (res.get_contactos) {
                        res.get_contactos.forEach(el => {
                            this.llenarCamposGroup(el.con_tipo, el);
                        });
                    }

                    // if (res.get_configuracion_observaciones_generales_factura){
                    //     res.get_configuracion_observaciones_generales_factura.forEach((item, index) => {
                    //     if (index > 0) {
                    //         this.agregarObservacion();
                    //     }
                    //     observaciones_form.at(index).patchValue({
                    //         ogf_observacion: item.ogf_observacion,
                    //     });
                    //     });
                    // }
                    // if (res.ofe_motivo_rechazo) {
                    //     res.ofe_motivo_rechazo.forEach((item, index) => {
                    //         if (index > 0) {
                    //             this.agregarMotivoRechazo();
                    //         }
                    //         motivos_rechazo_form.at(index).patchValue({
                    //             motivo_codigo: item.motivo_codigo,
                    //             motivo_descripcion: item.motivo_descripcion
                    //         });
                    //     });
                    // }
                    if (res.get_configuracion_software_proveedor_tecnologico) {
                        this.sptObjeto['sft_id'] = res.get_configuracion_software_proveedor_tecnologico.sft_id;
                        this.sptObjeto['sft_identificador'] = res.get_configuracion_software_proveedor_tecnologico.sft_identificador;
                        this.sptObjeto['sft_identificador_nombre'] = res.get_configuracion_software_proveedor_tecnologico.sft_identificador + ' - ' + res.get_configuracion_software_proveedor_tecnologico.sft_nombre;
                    }
                    if (res.get_configuracion_software_proveedor_tecnologico_ds) {
                        this.sptDsObjeto['sft_id'] = res.get_configuracion_software_proveedor_tecnologico_ds.sft_id;
                        this.sptDsObjeto['sft_identificador'] = res.get_configuracion_software_proveedor_tecnologico_ds.sft_identificador;
                        this.sptDsObjeto['sft_identificador_nombre'] = res.get_configuracion_software_proveedor_tecnologico_ds.sft_identificador + ' - ' + res.get_configuracion_software_proveedor_tecnologico_ds.sft_nombre;
                    }
                    if (res.get_parametros_pais) {
                        this.paisObjeto['pai_id'] = res.get_parametros_pais.pai_id;
                        this.paisObjeto['pai_codigo'] = res.get_parametros_pais.pai_codigo;
                        this.paisObjeto['pai_codigo_descripion'] = res.get_parametros_pais.pai_codigo + ' - ' + res.get_parametros_pais.pai_descripcion;
                    }
                    if (res.get_parametros_departamento) {
                        this.departamentoObjeto['dep_id'] = res.get_parametros_departamento.dep_id;
                        this.departamentoObjeto['dep_codigo'] = res.get_parametros_departamento.dep_codigo;
                        this.departamentoObjeto['dep_codigo_descripion'] = res.get_parametros_departamento.dep_codigo + ' - ' + res.get_parametros_departamento.dep_descripcion;
                    }
                    if (res.get_parametros_municipio) {
                        this.municipioObjeto['mun_id'] = res.get_parametros_municipio.mun_id;
                        this.municipioObjeto['mun_codigo'] = res.get_parametros_municipio.mun_codigo;
                        this.municipioObjeto['mun_codigo_descripion'] = res.get_parametros_municipio.mun_codigo + ' - ' + res.get_parametros_municipio.mun_descripcion;
                    }
                    if (res.get_parametro_domicilio_fiscal_pais) {
                        this.paisFiscalObjeto['pai_id'] = res.get_parametro_domicilio_fiscal_pais.pai_id;
                        this.paisFiscalObjeto['pai_codigo'] = res.get_parametro_domicilio_fiscal_pais.pai_codigo;
                        this.paisFiscalObjeto['pai_codigo_descripion'] = res.get_parametro_domicilio_fiscal_pais.pai_codigo + ' - ' + res.get_parametro_domicilio_fiscal_pais.pai_descripcion;
                    }
                    if (res.get_parametro_domicilio_fiscal_departamento) {
                        this.departamentoFiscalObjeto['dep_id'] = res.get_parametro_domicilio_fiscal_departamento.dep_id;
                        this.departamentoFiscalObjeto['dep_codigo'] = res.get_parametro_domicilio_fiscal_departamento.dep_codigo;
                        this.departamentoFiscalObjeto['dep_codigo_descripion'] = res.get_parametro_domicilio_fiscal_departamento.dep_codigo + ' - ' + res.get_parametro_domicilio_fiscal_departamento.dep_descripcion;
                    }
                    if (res.get_parametro_domicilio_fiscal_municipio) {
                        this.municipioFiscalObjeto['mun_id'] = res.get_parametro_domicilio_fiscal_municipio.mun_id;
                        this.municipioFiscalObjeto['mun_codigo'] = res.get_parametro_domicilio_fiscal_municipio.mun_codigo;
                        this.municipioFiscalObjeto['mun_codigo_descripion'] = res.get_parametro_domicilio_fiscal_municipio.mun_codigo + ' - ' + res.get_parametro_domicilio_fiscal_municipio.mun_descripcion;
                    }
                    // if (res.get_responsabilidad_fiscal) {
                    //     this.responsabilidadFiscalObjeto['ref_id'] = res.get_responsabilidad_fiscal.ref_id;
                    //     this.responsabilidadFiscalObjeto['ref_codigo'] = res.get_responsabilidad_fiscal.ref_codigo;
                    //     this.responsabilidadFiscalObjeto['ref_codigo_descripion'] = res.get_responsabilidad_fiscal.ref_codigo + ' - ' + res.get_responsabilidad_fiscal.ref_descripcion;
                    // }

                    if (res.get_codigo_postal) {
                        this.codigoPostalObjeto['cpo_id'] = res.get_codigo_postal.cpo_id;
                        this.codigoPostalObjeto['cpo_codigo'] = res.get_codigo_postal.cpo_codigo;
                    }
                    if (res.get_codigo_postal_domicilio_fiscal) {
                        this.codigoPostalFiscalObjeto['cpo_id'] = res.get_codigo_postal_domicilio_fiscal.cpo_id;
                        this.codigoPostalFiscalObjeto['cpo_codigo'] = res.get_codigo_postal_domicilio_fiscal.cpo_codigo;
                    }

                    if (res.get_tiempo_aceptacion_tacita){
                        this.tatObjeto['tat_id'] = res.get_tiempo_aceptacion_tacita.tat_id;
                        this.tatObjeto['tat_codigo'] = res.get_tiempo_aceptacion_tacita.tat_codigo;
                        this.tatObjeto['tat_codigo_descripcion'] = res.get_tiempo_aceptacion_tacita.tat_codigo + ' - ' + res.get_tiempo_aceptacion_tacita.tat_descripcion;
                    }

                    if (res.get_parametro_tipo_documento && res.get_parametro_tipo_documento.tdo_codigo === this.codigoNIT) {
                        if (res.ofe_identificacion) {
                            this._commonsService.calcularDV(res.ofe_identificacion).subscribe(
                                result => {
                                    if (result.data || result.data === 0) {
                                        this.DV.setValue(result.data);
                                    }
                                    else {
                                        this.DV.setValue(null);
                                    }
                                    this.loading(false);
                                });
                        }
                    } else {
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

                    if(Object.keys(this.sptObjeto).length > 0)
                        this.sft_id.setValue(this.sptObjeto);

                    if(Object.keys(this.sptDsObjeto).length > 0)
                        this.sft_id_ds.setValue(this.sptDsObjeto);
                        
                    this.tat_id.setValue(this.tatObjeto);
                    // this.ref_id.setValue(this.responsabilidadFiscalObjeto);
                    this.cpo_id.setValue(this.codigoPostalObjeto);
                    this.cpo_id_domicilio_fiscal.setValue(this.codigoPostalFiscalObjeto);
                    this.objMagic['fecha_creacion'] = res.fecha_creacion;
                    this.objMagic['fecha_modificacion'] = res.fecha_modificacion;
                    this.objMagic['estado'] = res.estado;

                    if (res.ofe_identificador_unico_adquirente) {
                        this.ofe_identificador_unico_adquirente.setValue(res.ofe_identificador_unico_adquirente);

                        this.ofe_identificador_unico_adquirente.value.indexOf('adq_identificacion') !== -1 ? this.adqIdentificacion.toggle() : '';
                        this.ofe_identificador_unico_adquirente.value.indexOf('adq_id_personalizado') !== -1 ? this.adqIdPersonalizado.toggle() : '';
                    } else {
                        this.adqIdentificacion.toggle();
                        this.ofe_identificador_unico_adquirente.setValue(['adq_identificacion']);
                    }

                    if (res.ofe_informacion_personalizada_adquirente) {
                        this.ofe_informacion_personalizada_adquirente.setValue(res.ofe_informacion_personalizada_adquirente);
                        this.selectedInfoPersonalizadaAdq = res.ofe_informacion_personalizada_adquirente;
                    }
                }
            },
            error => {
                this.loading(false);
                const texto_errores = this.parseError(error);
                this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al cargar el OFE', 'Ok', 'btn btn-danger', 'configuracion/oferentes', this._router);
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
            DatosGenerales               : this.getFormularioDatosGenerales(),
            Ubicacion                    : this.buildFormularioUbicacion(),
            UbicacionFiscal              : this.buildFormularioUbicacionFiscal(),
            DatosTributarios             : this.buildFormularioDatosTributarios(),
            InformacionAdicional         : this.buildFormularioInformacionAdicional(),
            DatosSft                     : this.buildFormularioDatosSft(),
            DatosContactos               : this.buildFormularioContactos(),
            redesSociales                : this.buildFormularioRedesSociales(),
            ReglaAceptacionTacita        : this.buildFormularioDatosTat(),
            AsuntoCorreos                : this.buildFormularioAsuntoCorreos(),
            CorreosNotificacion          : this.buildFormularioNotificaciones(),
            CorreosAutorespuesta         : this.buildFormularioAutorespuesta(),
            configuracionesPersonalizadas: this.buildConfiguracionesPersonalizadas(),
            // filtrosPersonalizados: this.buildFormularioFiltrosPersonalizados(),
            // motivosColeccion: this.buildFormularioMotivosRechazo(),
            // observacionesColeccion: this.buildFormularioObservaciones()
            // ofe_guardar_representacion_grafica: [''],
            // proveedores: this.proveedores
        });
    }

    /**
     * Construcción del formgroup para configuraciones personalizadas.
     *
     */
    buildConfiguracionesPersonalizadas() {
        this.configuracionesPersonalizadas = this._formBuilder.group({
            ofe_identificador_unico_adquirente      : [''],
            ofe_informacion_personalizada_adquirente: ['']
        });
        this.ofe_identificador_unico_adquirente       = this.configuracionesPersonalizadas.controls['ofe_identificador_unico_adquirente'];
        this.ofe_informacion_personalizada_adquirente = this.configuracionesPersonalizadas.controls['ofe_informacion_personalizada_adquirente'];

        this.ofe_identificador_unico_adquirente.setValue([]);

        return this.configuracionesPersonalizadas;
    }

    /**
     * Construccion del formulario de datos personales.
     *
     */
    getFormularioDatosGenerales() {
        this.datosGenerales = this._formBuilder.group({
            tdo_id: this.requerido(),
            toj_id: this.requerido(),
            ofe_identificacion: this.requeridoMaxlong(20),
            ofe_razon_social: this.maxlong(255),
            ofe_nombre_comercial: this.maxlong(255),
            ofe_primer_apellido: this.maxlong(100),
            ofe_segundo_apellido: [''],
            ofe_primer_nombre: this.maxlong(100),
            ofe_otros_nombres: [''],
            DV: [''],
            estado: ['']
        });
        this.tdo_id = this.datosGenerales.controls['tdo_id'];
        this.toj_id = this.datosGenerales.controls['toj_id'];
        this.ofe_identificacion = this.datosGenerales.controls['ofe_identificacion'];
        this.ofe_razon_social = this.datosGenerales.controls['ofe_razon_social'];
        this.ofe_nombre_comercial = this.datosGenerales.controls['ofe_nombre_comercial'];
        this.ofe_primer_apellido = this.datosGenerales.controls['ofe_primer_apellido'];
        this.ofe_segundo_apellido = this.datosGenerales.controls['ofe_segundo_apellido'];
        this.ofe_primer_nombre = this.datosGenerales.controls['ofe_primer_nombre'];
        this.ofe_otros_nombres = this.datosGenerales.controls['ofe_otros_nombres'];
        this.DV = this.datosGenerales.controls['DV'];
        this.estado = this.datosGenerales.controls['estado'];
        return this.datosGenerales;
    }

    /**
     * Construcción del formulario de ubicacion.
     *
     */
    buildFormularioUbicacion() {
        this.ubicacion = this._formBuilder.group({
            pai_id: [''],
            dep_id: [''],
            mun_id: [''],
            ofe_direccion: this.maxlong(255),
            cpo_id: this.maxlong(50)
        });
        this.pai_id = this.ubicacion.controls['pai_id'];
        this.dep_id = this.ubicacion.controls['dep_id'];
        this.mun_id = this.ubicacion.controls['mun_id'];
        this.ofe_direccion = this.ubicacion.controls['ofe_direccion'];
        this.cpo_id = this.ubicacion.controls['cpo_id'];
        return this.ubicacion;
    }

    /**
     * Construcción del formulario de redes sociales.
     *
     */
    buildFormularioRedesSociales() {
        this.redesSociales = this._formBuilder.group({
            ofe_web: this.maxlong(255),
            ofe_twitter: this.maxlong(255),
            ofe_facebook: this.maxlong(255)
        });
        this.ofe_web = this.redesSociales.controls['ofe_web'];
        this.ofe_twitter = this.redesSociales.controls['ofe_twitter'];
        this.ofe_facebook = this.redesSociales.controls['ofe_facebook'];
        return this.redesSociales;
    }

    /**
     * Construcción del formulario de redes sociales.
     *
     */
    buildFormularioAsuntoCorreos() {
        this.asuntoCorreos = this._formBuilder.group({
            ofe_asunto_correos: this.maxlong(255)
        });
        this.ofe_asunto_correos = this.asuntoCorreos.controls['ofe_asunto_correos'];
        return this.asuntoCorreos;
    }

    /**
     * Construcción del formulario de motivos de rechazo.
     *
     */
    buildFormularioMotivosRechazo() {
        this.motivosColeccion = this._formBuilder.group({
            motivos: this.buildFormularioMotivosRechazoArray()
        });
        return this.motivosColeccion;
    }

    /**
     * Construcción del formulario de filtros personalizados.
     *
     */
    // buildFormularioFiltrosPersonalizados() {
    //     this.filtrosPersonalizados = this._formBuilder.group({
    //         filtros: this.buildFormularioFiltrosPersonalizadosArray()
    //     });
    //     return this.filtrosPersonalizados;
    // }

    /**
     * Construcción del array de motivos de rechazo.
     *
     */
    buildFormularioMotivosRechazoArray() {
        this.motivos = this._formBuilder.array([
            this.motivosRechazo()
        ]);
        return this.motivos;
    }

    /**
     * Construcción del array de filtros personalizados.
     *
     */
    // buildFormularioFiltrosPersonalizadosArray() {
    //     this.filtros = this._formBuilder.array([
    //         // this.filtroPersonalizado()
    //     ]);
    //     return this.filtros;
    // }

    /**
     * Construcción del formulario de observaciones.
     *
     */
    buildFormularioObservaciones() {
        this.observacionesColeccion = this._formBuilder.group({
            observaciones: this.buildFormularioObservacionesArray()
        });
        return this.observacionesColeccion;
    }

    /**
     * Construcción del array de observaciones.
     *
     */
    buildFormularioObservacionesArray() {
        this.observaciones = this._formBuilder.array([
            this.createObservaciones()
        ]);
        return this.observaciones;
    }

    /**
     * Construcción del formulario de domicilio fiscal.
     *
     */
    buildFormularioUbicacionFiscal() {
        this.ubicacionFiscal = this._formBuilder.group({
            pai_id_domicilio_fiscal: [''],
            dep_id_domicilio_fiscal: [''],
            mun_id_domicilio_fiscal: [''],
            cpo_id_domicilio_fiscal: this.maxlong(50),
            ofe_direccion_domicilio_fiscal: this.maxlong(255)
        });
        this.pai_id_domicilio_fiscal = this.ubicacionFiscal.controls['pai_id_domicilio_fiscal'];
        this.dep_id_domicilio_fiscal = this.ubicacionFiscal.controls['dep_id_domicilio_fiscal'];
        this.mun_id_domicilio_fiscal = this.ubicacionFiscal.controls['mun_id_domicilio_fiscal'];
        this.ofe_direccion_domicilio_fiscal = this.ubicacionFiscal.controls['ofe_direccion_domicilio_fiscal'];
        this.cpo_id_domicilio_fiscal = this.ubicacionFiscal.controls['cpo_id_domicilio_fiscal'];
        return this.ubicacionFiscal;
    }

    /**
     * Construcción del formulario de información adicional.
     *
     */
    buildFormularioInformacionAdicional() {
        this.informacionAdicional = this._formBuilder.group({
            ofe_correo: ['', Validators.compose(
                [
                    Validators.required,
                    Validators.email,
                    Validators.maxLength(255),
                    Validators.minLength(10)
                ],
            )],

            ofe_nombre_contacto    : this.maxlong(255),
            ofe_fax                : this.maxlong(50),
            ofe_telefono           : this.maxlong(255),
            ofe_matricula_mercantil: this.maxlong(100),
            ofe_actividad_economica: this.maxlong(255),
            ofe_notas              : ['']
        });

        this.ofe_nombre_contacto     = this.informacionAdicional.controls['ofe_nombre_contacto'];
        this.ofe_fax                 = this.informacionAdicional.controls['ofe_fax'];
        this.ofe_telefono            = this.informacionAdicional.controls['ofe_telefono'];
        this.ofe_notas               = this.informacionAdicional.controls['ofe_notas'];
        this.ofe_correo              = this.informacionAdicional.controls['ofe_correo'];
        this.ofe_matricula_mercantil = this.informacionAdicional.controls['ofe_matricula_mercantil'];
        this.ofe_actividad_economica = this.informacionAdicional.controls['ofe_actividad_economica'];

        return this.informacionAdicional;
    }

    /**
     * Construccion de los datos tributarios.
     *
     */
    buildFormularioDatosTributarios() {
        this.datosTributarios = this._formBuilder.group({
            rfi_id              : [''],
            ref_id              : [''],
            responsable_tributos: ['']
        });
        this.rfi_id = this.datosTributarios.controls['rfi_id'];
        this.ref_id = this.datosTributarios.controls['ref_id'];
        this.responsable_tributos = this.datosTributarios.controls['responsable_tributos'];
        return this.datosTributarios;
    }

    /**
     * Construccion del formgroup para el campo de notificaciones.
     *
     */
    buildFormularioNotificaciones() {
        this.correosNotificacion = this._formBuilder.group({
            ofe_correos_notificacion: [''],
            ofe_notificacion_un_solo_correo: [''],
            // ofe_mostrar_seccion_correos_notificacion: ['']
        });
        this.ofe_correos_notificacion = this.correosNotificacion.controls['ofe_correos_notificacion'];
        this.ofe_notificacion_un_solo_correo = this.correosNotificacion.controls['ofe_notificacion_un_solo_correo'];
        // this.ofe_mostrar_seccion_correos_notificacion = this.correosNotificacion.controls['ofe_mostrar_seccion_correos_notificacion'];
        return this.correosNotificacion;
    }

    /**
     * Construccion del formgroup para el campo de correos de autorespuesta.
     *
     */
    buildFormularioAutorespuesta() {
        this.correosAutorespuesta = this._formBuilder.group({
            ofe_correos_autorespuesta: ['']
        });
        this.ofe_correos_autorespuesta = this.correosAutorespuesta.controls['ofe_correos_autorespuesta'];
        return this.correosAutorespuesta;
    }

    /**
     * Construcción de los datos del proveedor de software tecnológico.
     *
     */
    buildFormularioDatosSft() {
        this.sftSelector = this._formBuilder.group({
            sft_id   : [''],
            sft_id_ds: ['']
        });
        this.sft_id    = this.sftSelector.controls['sft_id'];
        this.sft_id_ds = this.sftSelector.controls['sft_id_ds'];
        return this.sftSelector;
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
        this.despatchContact = this.buildFormularioContacto(this._formBuilder, 'DespatchContact');
        this.accountingContact = this.buildFormularioContacto(this._formBuilder, 'AccountingContact');
        this.sellerContact = this.buildFormularioContacto(this._formBuilder, 'SellerContact');
        this.contactos = this._formBuilder.group({
            DespatchContact: this.despatchContact,
            AccountingContact: this.accountingContact,
            SellerContact: this.sellerContact
        });
        return this.contactos;
    }

    /**
     * Permite regresar a la lista de oferentes.
     *
     */
    regresar() {
        this._router.navigate(['configuracion/oferentes']);
    }

    /**
     * Inicializa la data necesaria para la construcción del oferente.
     *
     */
    private initForBuild() {
        this.loading(true);
        this._commonsService.getDataInitForBuild('tat=true&aplicaPara=DE').subscribe(
            result => {
                this.paises                  = result.data.paises;
                this.tipoDocumentos          = result.data.tipo_documentos;
                this.tipoOrganizaciones      = result.data.tipo_organizaciones;
                this.tipoRegimen             = result.data.tipo_regimen;
                this.ResFiscal               = result.data.responsabilidades_fiscales;

                this.tiemposAceptacionTacita = result.data.tiempo_aceptacion_tacita;
                this.tiemposAceptacionTacita.map( el => {
                    el.tat_codigo_descripcion = el.tat_codigo + ' - ' + el.tat_descripcion;
                });
                this.tributos = result.data.tributos;
                this.responsabilidadesFiscales = result.data.responsabilidades_fiscales;
                if (this._ofe_identificacion) {
                    this.loadOfe();
                } else {
                    this.loading(false);
                    this.tipoDocumentoSelect             = result.data.tipo_documentos;
                    this.tipoOrganizacionSelect          = result.data.tipo_organizaciones;
                    this.regimenFiscalSelect             = result.data.tipo_regimen;
                    this.responsabilidadesFiscalesSelect = result.data.responsabilidades_fiscales;
                    this.tributosSelect                  = result.data.tributos;
                }
            }, error => {
                const texto_errores = this.parseError(error);
                this.loading(false);
                this.showError(texto_errores, 'error', 'Error al cargar los parámetros', 'Ok', 'btn btn-danger');
            }
        );
    }

    /**
     * Crea o actualiza un nuevo registro.
     *
     * @param values
     */
    public resourceOfe(values) {
        if (this.formulario.valid) {
            if(!this.sft_id.value && !this.sft_id_ds.value)
                this.showError('<h4>Debe indicar el Software Proveedor Tecnológico para Emisión o Documento Soporte</h4>', 'error', 'Error al procesar el OFE', 'Ok', 'btn btn-danger');
            else {
                this.loading(true);
                const payload = this.getPayload();
                if (this._ofe_identificacion) {
                    payload['estado'] = this.estado.value;
                    this._oferentesService.update(this._ofe_identificacion, payload).subscribe(
                        response => {
                            this.loading(false);
                            this.showSuccess('<h3>Actualización exitosa</h3>', 'success', 'OFE actualizado exitosamente', 'Ok', 'btn btn-success', `/configuracion/oferentes`, this._router);
                        },
                        error => {
                            this.loading(false);
                            const texto_errores = this.parseError(error);
                            this.showError('<h4 style="text-align:left">' + texto_errores + '</h4>', 'error', 'Error al actualizar el OFE', 'Ok', 'btn btn-danger');
                        });
                } else {
                    this._oferentesService.create(payload).subscribe(
                        response => {
                            this.loading(false);
                            this.showSuccess('<h3>' + '</h3>', 'success', 'OFE creado exitosamente', 'Ok', 'btn btn-success', `/configuracion/oferentes`, this._router);
                        },
                        error => {
                            this.loading(false);
                            const texto_errores = this.parseError(error);
                            this.showError('<h4 style="text-align:left">' + texto_errores + '</h4>', 'error', 'Error al guardar el OFE', 'Ok', 'btn btn-danger');
                        });
                }
            }
        }
    }

    /**
     * Crea un json para enviar los campos del formulario.
     *
     */
    getPayload() {
        const contactos = [];
        const despatch = {};
        const accounting = {};
        const seller = {};

        Object.keys(this.despatchContact.controls).forEach(key => {
            if (this.despatchContact.controls[key].value) {
                despatch[key] = this.despatchContact.controls[key].value;
            }
        });
        if (Object.keys(despatch).length > 1) {
            contactos.push(despatch);
        }
        Object.keys(this.accountingContact.controls).forEach(key => {
            if (this.accountingContact.controls[key].value) {
                accounting[key] = this.accountingContact.controls[key].value;
            }
        });
        if (Object.keys(accounting).length > 1) {
            contactos.push(accounting);
        }
        Object.keys(this.sellerContact.controls).forEach(key => {
            if (this.sellerContact.controls[key].value) {
                seller[key] = this.sellerContact.controls[key].value;
            }
        });
        if (Object.keys(seller).length > 1) {
            contactos.push(seller);
        }

        let direccionesAdicionales = [];
        let ctrlDireccionesAdicionales = this.domicilioCorrespondencia.formDireccionesAdicionales.get('direcciones_adicionales') as FormArray
        ctrlDireccionesAdicionales.controls.forEach(ctrlDireccion => {
            direccionesAdicionales.push(ctrlDireccion.value.direccion)
        });

        // let filtrosOfe = {};
        // if(this.formulario.get('filtrosPersonalizados.filtros').value){
        //     this.formulario.get('filtrosPersonalizados.filtros').value.forEach(element => {
        //         if (element.nombre_filtro && element.nombre_filtro !== ''){
        //             let clave = element.nombre_filtro.split(' ').join('_').toLowerCase();
        //             filtrosOfe[clave] = element.nombre_filtro.toUpperCase();
        //         }
        //     });
        // }
        // filtrosOfe = this.isEmpty(filtrosOfe) ? null : filtrosOfe;
        // let motivosRechazos = null;
        // if(this.formulario.get('motivosColeccion.motivos').value){
        //     motivosRechazos = this.formulario.get('motivosColeccion.motivos').value.filter(element => {
        //         return (element.motivo_descripcion && element.motivo_descripcion !== '') || (element.motivo_codigo && element.motivo_codigo !== '')
        //     });
        // }
        const payload = {
            sft_id: this.sft_id.value && this.sft_id.value.sft_id ? this.sft_id.value.sft_id : '',
            sft_id_ds: this.sft_id_ds.value && this.sft_id_ds.value.sft_id ? this.sft_id_ds.value.sft_id : '',
            sft_identificador: this.sft_id.value && this.sft_id.value.sft_identificador ? this.sft_id.value.sft_identificador : '',
            sft_identificador_ds: this.sft_id_ds.value && this.sft_id_ds.value.sft_identificador ? this.sft_id_ds.value.sft_identificador : '',
            ofe_identificacion: this.ofe_identificacion.value,
            ofe_razon_social: this.ofe_razon_social.value,
            ofe_nombre_comercial: this.ofe_nombre_comercial.value,
            ofe_primer_apellido: this.ofe_primer_apellido.value,
            ofe_segundo_apellido: this.ofe_segundo_apellido.value,
            ofe_primer_nombre: this.ofe_primer_nombre.value,
            ofe_otros_nombres: this.ofe_otros_nombres.value,
            tdo_codigo: this.tdo_id.value,
            toj_codigo: this.toj_id.value,
            pai_codigo: this.pai_id.value && this.pai_id.value.pai_codigo ? this.pai_id.value.pai_codigo : '',
            dep_codigo: this.dep_id.value && this.dep_id.value.dep_codigo ? this.dep_id.value.dep_codigo : '',
            mun_codigo: this.mun_id.value && this.mun_id.value.mun_codigo ? this.mun_id.value.mun_codigo : '',
            cpo_codigo: this.cpo_id.value && this.cpo_id.value.cpo_codigo ? this.cpo_id.value.cpo_codigo : '',
            ofe_direccion: this.ofe_direccion.value ? this.ofe_direccion.value : '',
            ofe_direcciones_adicionales: direccionesAdicionales,
            pai_codigo_domicilio_fiscal: this.pai_id_domicilio_fiscal.value && this.pai_id_domicilio_fiscal.value.pai_codigo ? this.pai_id_domicilio_fiscal.value.pai_codigo : '',
            dep_codigo_domicilio_fiscal: this.dep_id_domicilio_fiscal.value && this.dep_id_domicilio_fiscal.value.dep_codigo ? this.dep_id_domicilio_fiscal.value.dep_codigo : '',
            mun_codigo_domicilio_fiscal: this.mun_id_domicilio_fiscal.value && this.mun_id_domicilio_fiscal.value.mun_codigo ? this.mun_id_domicilio_fiscal.value.mun_codigo : '',
            cpo_codigo_domicilio_fiscal: this.cpo_id_domicilio_fiscal.value && this.cpo_id_domicilio_fiscal.value.cpo_codigo ? this.cpo_id_domicilio_fiscal.value.cpo_codigo : '',
            ofe_direccion_domicilio_fiscal: this.ofe_direccion_domicilio_fiscal.value,
            ofe_web: this.ofe_web.value,
            ofe_twitter: this.ofe_twitter.value,
            ofe_facebook: this.ofe_facebook.value,
            ofe_asunto_correos: this.ofe_asunto_correos.value ? this.ofe_asunto_correos.value : '',
            // ofe_filtros : filtrosOfe,
            // "ofe_motivo_rechazo" : motivosRechazos,
            // "ofe_mostrar_seccion_correos_notificacion" : this.ofe_mostrar_seccion_correos_notificacion.value,
            ofe_correos_notificacion: this.ofe_correos_notificacion && this.ofe_correos_notificacion.value ? this.ofe_correos_notificacion.value.join(',') : '',
            ofe_correos_autorespuesta: this.ofe_correos_autorespuesta && this.ofe_correos_autorespuesta.value ? this.ofe_correos_autorespuesta.value.join(',') : '',
            ofe_notificacion_un_solo_correo : this.ofe_notificacion_un_solo_correo.value,
            rfi_codigo: (this.rfi_id.value !== null) ? this.rfi_id.value : '',
            ref_codigo: (this.ref_id.value !== undefined && this.ref_id.value.length > 0)? this.ref_id.value.join(';') : '',
            ofe_nombre_contacto: this.ofe_nombre_contacto.value,
            ofe_telefono: this.ofe_telefono.value,
            ofe_fax: this.ofe_fax.value,
            ofe_notas: this.ofe_notas.value,
            ofe_correo: this.ofe_correo.value,
            ofe_matricula_mercantil: this.ofe_matricula_mercantil.value,
            ofe_actividad_economica: (this.ofe_actividad_economica.value.length > 0) ? this.ofe_actividad_economica.value.join(';') : '',
            tat_codigo : this.tat_id.value && this.tat_id.value.tat_codigo ? this.tat_id.value.tat_codigo : '',
            ofe_identificador_unico_adquirente: (this.ofe_identificador_unico_adquirente.value.length > 0) ? this.ofe_identificador_unico_adquirente.value : '',
            ofe_informacion_personalizada_adquirente: this.selectedInfoPersonalizadaAdq,
            responsable_tributos: this.responsable_tributos.value,
            // "observaciones": this.formulario.get('observacionesColeccion.observaciones').value,
            contactos: contactos
        };

        return payload;
    }

    /**
     * Permite la previsualización del asunto de correos personalizado
     *
     * @memberof ObligadoFacturarElectronicamenteComponent
     */
    verEjemploAsunto() {
        if (
            this.ofe_asunto_correos && this.ofe_asunto_correos.value && this.ofe_razon_social && this.ofe_razon_social.value && this.ofe_identificacion && this.ofe_identificacion.value &&
            this.ofe_asunto_correos.value.indexOf('TIPO_DOCUMENTO')        !== -1 &&
            this.ofe_asunto_correos.value.indexOf('PREFIJO')               !== -1 &&
            this.ofe_asunto_correos.value.indexOf('CONSECUTIVO')           !== -1 &&
            this.ofe_asunto_correos.value.indexOf('CODIGO_TIPO_DOCUMENTO') !== -1 &&
            this.ofe_asunto_correos.value.indexOf('RAZON_SOCIAL_OFE')      !== -1 &&
            this.ofe_asunto_correos.value.indexOf('NOMBRE_COMERCIAL_OFE')  !== -1 &&
            this.ofe_asunto_correos.value.indexOf('NIT_OFE')               !== -1
        ) {
            let asunto            = this.ofe_asunto_correos.value;
            const ofe             = this.ofe_razon_social.value;
            const nit             = this.ofe_identificacion.value;
            const nombreComercial = this.ofe_nombre_comercial.value;
            asunto                = asunto.replace('{NIT_OFE}', nit);
            asunto                = asunto.replace('{RAZON_SOCIAL_OFE}', ofe);
            if(nombreComercial != '' && nombreComercial != undefined)
                asunto = asunto.replace('{NOMBRE_COMERCIAL_OFE}', nombreComercial);
            else
                asunto = asunto.replace('{NOMBRE_COMERCIAL_OFE}', ofe);

            this.showSuccess('<h4>' + asunto + '</h4><small>', 'success', 'Previsualización del Asunto', 'Cerrar', 'btn btn-success');
        } else {
            this.showError('<h4>Debe ingresar la personalización del asunto de los correos, haciendo uso de todos los campos dinámicos disponibles</h4>', 'error', 'Previsualización del Asunto', 'Ok', 'btn btn-danger');
        }
    }

    /**
     * Permite la creación dinámica de las diferentes filas y campos en el apartado Motivos Rechazo.
     *
     */
    private motivosRechazo(): any {
        return this._formBuilder.group({
            motivo_codigo: [''],
            motivo_descripcion: [''],
        });
    }

    /**
     * Crea un nuevo item para motivo de rechazo.
     *
     */
    agregarMotivoRechazo() {
        const CTRL = this.formulario.get('motivosColeccion.motivos') as FormArray;
        CTRL.push(this.motivosRechazo());
    }

    /**
     * Elimina un elemento de motivo de rechazo.
     *
     * @param i
     */
    eliminarMotivoRechazo(i: number) {
        const CTRL = this.formulario.get('motivosColeccion.motivos') as FormArray;
        CTRL.removeAt(i);
    }

    /**
     * Permite la creación dinámica de las diferentes filas y campos en el apartado Motivos Rechazo.
     *
     */
    // private filtroPersonalizado(): any {
    //     return this._formBuilder.group({
    //         nombre_filtro: ['']
    //     });
    // }

    /**
     * Crea un nuevo item para filtro de Ofe.
     *
     */
    // agregarFiltro() {
    //     const CTRL = this.formulario.get('filtrosPersonalizados.filtros') as FormArray;
    //     CTRL.push(this.filtroPersonalizado());
    // }

    /**
     * Elimina un elemento de filtro de Ofe.
     *
     * @param i
     */
    // eliminarFiltro(i: number) {
    //     const CTRL = this.formulario.get('filtrosPersonalizados.filtros') as FormArray;
    //     CTRL.removeAt(i);
    // }

    /**
     * Permite la creación dinámica de las diferentes filas y campos en el apartado Observaciones.
     *
     */
    private createObservaciones(): any {
        return this._formBuilder.group({
            ogf_observacion: ['']
        });
    }

    /**
     * Crea un nuevo item de observación.
     *
     */
    agregarObservacion() {
        const CTRL = this.formulario.get('observacionesColeccion.observaciones') as FormArray;
        CTRL.push(this.createObservaciones());
    }

    /**
     * Elimina un item de observación.
     *
     * @param i
     */
    eliminarObservacion(i: number) {
        const CTRL = this.formulario.get('observacionesColeccion.observaciones') as FormArray;
        CTRL.removeAt(i);
    }

    /**
     * Gestiona el cambio de selección en los radio buttons de notificación.
     *
     */
    changeCorreosNotificacion(value) {
        if (value === 'SI') {
            this.ofe_correos_notificacion.setValidators([Validators.required]);
        } else {
            this.ofe_correos_notificacion.clearValidators();
            this.ofe_correos_notificacion.setValue(null);
        }
        this.ofe_correos_notificacion.updateValueAndValidity();
    }

    /**
     * Establece la configuracón del identificador único de adquirente
     *
     * @param bool checked Indica si el checkbox fue seleccionado o no
     * @param string value Valor seleccionado
     * @memberof OferentesGestionarComponent
     */
    public setIdentificadorUnicoAdquirente(checked, value) {
        let valores = this.ofe_identificador_unico_adquirente.value;

        if(checked)
            valores.push(value);
        else
            valores.splice(valores.indexOf(value), 1);

        this.ofe_identificador_unico_adquirente.setValue(valores);
    }
}
