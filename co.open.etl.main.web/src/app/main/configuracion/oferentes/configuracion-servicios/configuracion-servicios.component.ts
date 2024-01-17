import {MatAccordion} from '@angular/material/expansion';
import {ActivatedRoute, Router} from '@angular/router';
import {DomSanitizer} from '@angular/platform-browser';
import {Auth} from '../../../../services/auth/auth.service';
import {Component, OnInit, ViewChild} from '@angular/core';
import {BaseComponentList} from 'app/main/core/base_component_list';
import {AbstractControl, FormBuilder, FormArray, FormGroup, Validators} from '@angular/forms';
import {CommonsService} from '../../../../services/commons/commons.service';
import {OferentesService} from '../../../../services/configuracion/oferentes.service';
import {ConfiguracionService} from '../../../../services/configuracion/configuracion.service';
import {DatosEventoDianComponent} from '../../../commons/datos-evento-dian/datos-evento-dian.component';
import * as moment from 'moment';
import swal from 'sweetalert2';

interface EventosDianInterface {
    evento                 : string;
    generacion_automatica ?: string;
    tdo_id                ?: string;
    use_identificacion    ?: string;
    use_nombres           ?: string;
    use_apellidos         ?: string;
    use_cargo             ?: string;
    use_area              ?: string;
};

@Component({
    selector: 'app-configuracion-servicios',
    templateUrl: './configuracion-servicios.component.html',
    styleUrls: ['./configuracion-servicios.component.scss']
})
export class ConfiguracionServiciosComponent extends BaseComponentList implements OnInit {
    @ViewChild('infoEventosDian', {static: false})  infoEventosDian : DatosEventoDianComponent;
    @ViewChild('acordion',        {static: false})  acordion        : MatAccordion;

    public formulario                                  : FormGroup;
    public ofe_emision                                 : AbstractControl;
    public ofe_recepcion                               : AbstractControl;
    public ofe_documento_soporte                       : AbstractControl;
    public ofe_evento_notificacion_open                : AbstractControl;
    public ofe_evento_notificacion_click               : AbstractControl;
    public ofe_prioridad_agendamiento                  : AbstractControl;
    public emision_aceptaciont                         : AbstractControl;
    public emision_acuse_recibo                        : AbstractControl;
    public emision_recibo_bien                         : AbstractControl;
    public emision_aceptacion                          : AbstractControl;
    public emision_rechazo                             : AbstractControl;
    public emision_fecha_inicio_consulta_eventos_dian  : AbstractControl;
    public recepcion_get_status                        : AbstractControl;
    public recepcion_acuse_recibo                      : AbstractControl;
    public recepcion_recibo_bien                       : AbstractControl;
    public recepcion_aceptacion                        : AbstractControl;
    public recepcion_rechazo                           : AbstractControl;
    public recepcion_aceptaciont                       : AbstractControl;
    public recepcion_fecha_inicio_consulta_eventos_dian: AbstractControl;
    public recepcion_generacion_automatica_acuse_recibo: AbstractControl;
    public recepcion_generacion_automatica_recibo_bien : AbstractControl;
    public ofe_recepcion_correo_estandar               : AbstractControl;
    public ofe_recepcion_correo_estandar_logo          : AbstractControl;
    public ofe_envio_notificacion_amazon_ses           : AbstractControl;
    public aws_access_key_id                           : AbstractControl;
    public aws_secret_access_key                       : AbstractControl;
    public aws_from_email                              : AbstractControl;
    public aws_region                                  : AbstractControl;
    public aws_ses_configuration_set                   : AbstractControl;
    public smtp_driver                                 : AbstractControl;
    public smtp_host                                   : AbstractControl;
    public smtp_puerto                                 : AbstractControl;
    public smtp_encriptacion                           : AbstractControl;
    public smtp_usuario                                : AbstractControl;
    public smtp_password                               : AbstractControl;
    public smtp_email                                  : AbstractControl;
    public smtp_nombre                                 : AbstractControl;
    public ofe_recepcion_transmision_erp               : AbstractControl;
    public ofe_recepcion_conexion_erp                  : AbstractControl;
    public ofe_cadisoft_activo                         : AbstractControl;
    public ofe_cadisoft_api_fc                         : AbstractControl;
    public ofe_cadisoft_api_fc_usuario                 : AbstractControl;
    public ofe_cadisoft_api_fc_password                : AbstractControl;
    public ofe_cadisoft_api_notas                      : AbstractControl;
    public ofe_cadisoft_api_notas_usuario              : AbstractControl;
    public ofe_cadisoft_api_notas_password             : AbstractControl;
    public ofe_cadisoft_logo                           : AbstractControl;
    public ofe_cadisoft_frecuencia                     : AbstractControl;
    public ofe_integracion_ecm                         : AbstractControl;
    public ofe_integracion_ecm_id_bd                   : AbstractControl;
    public ofe_integracion_ecm_id_negocio              : AbstractControl;
    public ofe_integracion_ecm_url_api                 : AbstractControl;
    public id_modulo_integracion_ecm                   : AbstractControl;
    public id_servicio_integracion_ecm                 : AbstractControl;
    public id_sitio_integracion_ecm                    : AbstractControl;
    public id_grupo_integracion_ecm                    : AbstractControl;

    // Se inicializan los formGroup por cada sección del formulario
    serviciosContratados                        : FormGroup;
    emisionEventosDian                          : FormGroup;
    recepcionEventosDian                        : FormGroup;
    notificacionEventosDian                     : FormGroup;
    configuracionAmazonSes                      : FormGroup;
    recepcionTransmisionErp                     : FormGroup;
    configuracionCadisoft                       : FormGroup;
    ofeIntegracionEcm                           : FormGroup;

    public aclsUsuario                          : any;
    public ofe_id                               : number;
    public ofeIdentificacion                    : number;
    public _ofe_identificacion                  : number;
    public _razon_social                        : string;
    public imgPathCadisoft                      : any;
    public imgPath                              : any;
    public imgUrlCadisoft                       : any;
    public imgUrl                               : any;
    public logoCadisoft                         : any;
    public logoNotificacionEventosDian          : any;
    public propiedadOcultar                     : string;
    public muestraOpcionesRelacionadasEmision   : boolean = false;
    public muestraOpcionesRelacionadasRecepcion : boolean = false;
    public readonlyConexionErp                  : boolean = true;
    public aplicaGeneracionAutomaticaAcuseRecibo: boolean = false;
    public aplicaGeneracionAutomaticaReciboBien : boolean = false;
    public tiposDocumento                       : Array<any> = [];
    public mostrarIntegracionEcm                : string  = 'NO';
    public arrIntegracionOfes                   : any = [];
    public modulosItems                         : any = '';
    public itemsSelectEmision                   : Array<any> = [];
    public contModulosIntegracion               : number;
    public integracionNo                        : boolean = false;

    integracionesEcm                            : FormArray;
    integracionEcm                              : FormGroup;

    /**
     * Crea una instancia de ConfiguracionServiciosComponent.
     * 
     * @param {Auth} _auth
     * @param {Router} _router
     * @param {FormBuilder} _formBuilder
     * @param {DomSanitizer} sanitizer
     * @param {ActivatedRoute} _route
     * @param {OferentesService} _oferentesService
     * @param {ConfiguracionService} _configuracionService
     * @memberof ConfiguracionServiciosComponent
     */
    constructor(
        public _auth                 : Auth,
        private _router              : Router,
        private _formBuilder         : FormBuilder,
        private sanitizer            : DomSanitizer,
        private _route               : ActivatedRoute,
        private _oferentesService    : OferentesService,
        private _commonsService      : CommonsService,
        private _configuracionService: ConfiguracionService,
    ) {
        super();
        this._configuracionService.setSlug = 'ofe';
        this.buildFormulario();
    }

    ngOnInit() {
        this.ofeIdentificacion = this._route.snapshot.params['ofe_identificacion'];
        this.aclsUsuario = this._auth.getAcls();
        this.camposIntegracionOpenEcm('NO');
        this.loadOfe();
    }

    /**
     * Construcción del formulario principal.
     *
     * @memberof ConfiguracionServiciosComponent
     */
    buildFormulario() {
        this.formulario = this._formBuilder.group({
            ServiciosContratados    : this.buildServiciosContratados(),
            EmisionEventosDian      : this.buildFormularioEmisionEventosDian(),
            RecepcionEventosDian    : this.buildFormularioRecepcionEventosDian(),
            NotificacionEventosDian : this.buildNotificacionEventosDian(),
            ConfiguracionAmazonSes  : this.buildFormularioConfiguracionAmazonSes(),
            RecepcionTransmisionErp : this.buildFormularioRecepcionTransmisionErp(),
            ConfiguracionCadisoft   : this.buildConfiguracionCadisoft(),
            integracionEcm         : this.buildFormularioIntegracionEcm(),
            OfeIntegracionEcm      : this.buildFormularioOfeIntegracionEcm()
        });
    }

    /**
     * Construcción del formgroup para la selección de servicios contratados.
     *
     * @return {*} 
     * @memberof ConfiguracionServiciosComponent
     */
    buildServiciosContratados() {
        this.serviciosContratados = this._formBuilder.group({
            ofe_emision                  : [''],
            ofe_recepcion                : [''],
            ofe_documento_soporte        : [''],
            ofe_evento_notificacion_open : [''],
            ofe_evento_notificacion_click: [''],
            ofe_prioridad_agendamiento   : ['', [Validators.pattern(new RegExp(/^[0-9]{1,2}$/))]]
        });

        this.ofe_emision                   = this.serviciosContratados.controls['ofe_emision'];
        this.ofe_recepcion                 = this.serviciosContratados.controls['ofe_recepcion'];
        this.ofe_documento_soporte         = this.serviciosContratados.controls['ofe_documento_soporte'];
        this.ofe_evento_notificacion_open  = this.serviciosContratados.controls['ofe_evento_notificacion_open'];
        this.ofe_evento_notificacion_click = this.serviciosContratados.controls['ofe_evento_notificacion_click'];
        this.ofe_prioridad_agendamiento    = this.serviciosContratados.controls['ofe_prioridad_agendamiento'];

        return this.serviciosContratados;
    }

    /**
     * Construcción del formgroup para la configuración emisión - eventos DIAN.
     *
     * @memberof ConfiguracionServiciosComponent
     */
    buildFormularioEmisionEventosDian() {
        this.emisionEventosDian = this._formBuilder.group({
            emision_aceptaciont : [''],
            emision_acuse_recibo: [''],
            emision_recibo_bien : [''],
            emision_aceptacion  : [''],
            emision_rechazo     : [''],
            emision_fecha_inicio_consulta_eventos_dian: ['']
        });

        this.emision_aceptaciont                        = this.emisionEventosDian.controls['emision_aceptaciont'];
        this.emision_acuse_recibo                       = this.emisionEventosDian.controls['emision_acuse_recibo'];
        this.emision_recibo_bien                        = this.emisionEventosDian.controls['emision_recibo_bien'];
        this.emision_aceptacion                         = this.emisionEventosDian.controls['emision_aceptacion'];
        this.emision_rechazo                            = this.emisionEventosDian.controls['emision_rechazo'];
        this.emision_fecha_inicio_consulta_eventos_dian = this.emisionEventosDian.controls['emision_fecha_inicio_consulta_eventos_dian'];

        return this.emisionEventosDian;
    }

    /**
     * Construcción del formgroup para la configuración de recepción - eventos DIAN.
     *
     * @return {*} 
     * @memberof ConfiguracionServiciosComponent
     */
    buildFormularioRecepcionEventosDian() {
        this.recepcionEventosDian = this._formBuilder.group({
            recepcion_get_status                        : [''],
            recepcion_acuse_recibo                      : [''],
            recepcion_recibo_bien                       : [''],
            recepcion_aceptacion                        : [''],
            recepcion_rechazo                           : [''],
            recepcion_aceptaciont                       : [''],
            recepcion_generacion_automatica_acuse_recibo: [''],
            recepcion_generacion_automatica_recibo_bien : [''],
            recepcion_fecha_inicio_consulta_eventos_dian: ['']
        });

        this.recepcion_get_status                         = this.recepcionEventosDian.controls['recepcion_get_status'];
        this.recepcion_acuse_recibo                       = this.recepcionEventosDian.controls['recepcion_acuse_recibo'];
        this.recepcion_recibo_bien                        = this.recepcionEventosDian.controls['recepcion_recibo_bien'];
        this.recepcion_aceptacion                         = this.recepcionEventosDian.controls['recepcion_aceptacion'];
        this.recepcion_rechazo                            = this.recepcionEventosDian.controls['recepcion_rechazo'];
        this.recepcion_aceptaciont                        = this.recepcionEventosDian.controls['recepcion_aceptaciont'];
        this.recepcion_generacion_automatica_acuse_recibo = this.recepcionEventosDian.controls['recepcion_generacion_automatica_acuse_recibo'];
        this.recepcion_generacion_automatica_recibo_bien  = this.recepcionEventosDian.controls['recepcion_generacion_automatica_recibo_bien'];
        this.recepcion_fecha_inicio_consulta_eventos_dian = this.recepcionEventosDian.controls['recepcion_fecha_inicio_consulta_eventos_dian'];

        return this.recepcionEventosDian;
    }

    /**
     * Construcción del formgroup para notificacón de eventos DIAN.
     *
     * @return {*} 
     * @memberof ConfiguracionServiciosComponent
     */
    buildNotificacionEventosDian() {
        this.notificacionEventosDian = this._formBuilder.group({
            ofe_recepcion_correo_estandar     : ['SI'],
            ofe_recepcion_correo_estandar_logo: ['']
        });

        this.ofe_recepcion_correo_estandar      = this.notificacionEventosDian.controls['ofe_recepcion_correo_estandar'];
        this.ofe_recepcion_correo_estandar_logo = this.notificacionEventosDian.controls['ofe_recepcion_correo_estandar_logo'];

        return this.notificacionEventosDian;
    }

    /**
     * Construcción del formgroup para la configuración de AWS SES.
     *
     * @return {*} 
     * @memberof ConfiguracionServiciosComponent
     */
    buildFormularioConfiguracionAmazonSes() {
        this.configuracionAmazonSes = this._formBuilder.group({
            ofe_envio_notificacion_amazon_ses: [''],
            aws_access_key_id                : [''],
            aws_secret_access_key            : [''],
            aws_from_email                   : [''],
            aws_region                       : [''],
            aws_ses_configuration_set        : [''],
            smtp_driver                      : [''],
            smtp_host                        : [''],
            smtp_puerto                      : ['', Validators.compose(
                [
                    Validators.pattern('^[0-9]*$')
                ],
            )],
            smtp_encriptacion                : [''],
            smtp_usuario                     : [''],
            smtp_password                    : [''],
            smtp_email                       : ['', Validators.compose(
                [
                    Validators.email,
                ],
            )],
            smtp_nombre                      : [''],
        });

        this.ofe_envio_notificacion_amazon_ses = this.configuracionAmazonSes.controls['ofe_envio_notificacion_amazon_ses'];
        this.aws_access_key_id                 = this.configuracionAmazonSes.controls['aws_access_key_id'];
        this.aws_secret_access_key             = this.configuracionAmazonSes.controls['aws_secret_access_key'];
        this.aws_from_email                    = this.configuracionAmazonSes.controls['aws_from_email'];
        this.aws_region                        = this.configuracionAmazonSes.controls['aws_region'];
        this.aws_ses_configuration_set         = this.configuracionAmazonSes.controls['aws_ses_configuration_set'];
        this.smtp_driver                       = this.configuracionAmazonSes.controls['smtp_driver'];
        this.smtp_host                         = this.configuracionAmazonSes.controls['smtp_host'];
        this.smtp_puerto                       = this.configuracionAmazonSes.controls['smtp_puerto'];
        this.smtp_encriptacion                 = this.configuracionAmazonSes.controls['smtp_encriptacion'];
        this.smtp_usuario                      = this.configuracionAmazonSes.controls['smtp_usuario'];
        this.smtp_password                     = this.configuracionAmazonSes.controls['smtp_password'];
        this.smtp_email                        = this.configuracionAmazonSes.controls['smtp_email'];
        this.smtp_nombre                       = this.configuracionAmazonSes.controls['smtp_nombre'];

        return this.configuracionAmazonSes;
    }

    /**
     * Construcción del formgroup para recepción - transmisión al ERP.
     *
     * @return {*} 
     * @memberof ConfiguracionServiciosComponent
     */
    buildFormularioRecepcionTransmisionErp() {
        this.recepcionTransmisionErp = this._formBuilder.group({
            ofe_recepcion_transmision_erp: [''],
            ofe_recepcion_conexion_erp   : ['']
        });

        this.ofe_recepcion_transmision_erp = this.recepcionTransmisionErp.controls['ofe_recepcion_transmision_erp'];
        this.ofe_recepcion_conexion_erp    = this.recepcionTransmisionErp.controls['ofe_recepcion_conexion_erp'];

        return this.recepcionTransmisionErp;
    }

    /**
     * Construcción del formgroup para configuración de OFEs de Cadisoft.
     *
     * @return {*} 
     * @memberof ConfiguracionServiciosComponent
     */
    buildConfiguracionCadisoft() {
        this.configuracionCadisoft = this._formBuilder.group({
            ofe_cadisoft_activo            : [''],
            ofe_cadisoft_api_fc            : [''],
            ofe_cadisoft_api_fc_usuario    : [''],
            ofe_cadisoft_api_fc_password   : [''],
            ofe_cadisoft_api_notas         : [''],
            ofe_cadisoft_api_notas_usuario : [''],
            ofe_cadisoft_api_notas_password: [''],
            ofe_cadisoft_logo              : [''],
            ofe_cadisoft_frecuencia        : [''],
        });
        this.ofe_cadisoft_activo             = this.configuracionCadisoft.controls['ofe_cadisoft_activo'];
        this.ofe_cadisoft_api_fc             = this.configuracionCadisoft.controls['ofe_cadisoft_api_fc'];
        this.ofe_cadisoft_api_fc_usuario     = this.configuracionCadisoft.controls['ofe_cadisoft_api_fc_usuario'];
        this.ofe_cadisoft_api_fc_password    = this.configuracionCadisoft.controls['ofe_cadisoft_api_fc_password'];
        this.ofe_cadisoft_api_notas          = this.configuracionCadisoft.controls['ofe_cadisoft_api_notas'];
        this.ofe_cadisoft_api_notas_usuario  = this.configuracionCadisoft.controls['ofe_cadisoft_api_notas_usuario'];
        this.ofe_cadisoft_api_notas_password = this.configuracionCadisoft.controls['ofe_cadisoft_api_notas_password'];
        this.ofe_cadisoft_logo               = this.configuracionCadisoft.controls['ofe_cadisoft_logo'];
        this.ofe_cadisoft_frecuencia         = this.configuracionCadisoft.controls['ofe_cadisoft_frecuencia'];

        return this.configuracionCadisoft;
    }

    /**
     *  Se encarga de cargar los datos de configuración de servicios de un Ofe seleccionado en el tracking.
     *
     * @memberof ConfiguracionServiciosComponent
     */
    public loadOfe(): void {
        this.loading(true);
        this._configuracionService.get(this.ofeIdentificacion).subscribe(
            res => {
                if (res.data)
                    res = res.data;

                this.ofe_id = res.ofe_id;
                this._ofe_identificacion = res.ofe_identificacion;
                if(res.ofe_razon_social != '') {
                    this._razon_social = res.ofe_razon_social;
                } else {
                    this._razon_social = res.ofe_primer_nombre + ' ' + res.ofe_otros_nombres + ' ' + res.ofe_primer_apellido + ' ' + res.ofe_segundo_apellido;
                }

                this.ofe_emision.setValue((res.ofe_emision !== null && res.ofe_emision !== undefined && res.ofe_emision !== '') ? res.ofe_emision : 'NO');
                this.ofe_recepcion.setValue((res.ofe_recepcion !== null && res.ofe_recepcion !== undefined && res.ofe_recepcion !== '') ? res.ofe_recepcion : 'NO');
                this.ofe_documento_soporte.setValue((res.ofe_documento_soporte !== null && res.ofe_documento_soporte !== undefined && res.ofe_documento_soporte !== '') ? res.ofe_documento_soporte : 'NO');
                this.muestraOpcionesRelacionadasEmision = res.ofe_emision === 'SI' ? true : false;
                this.muestraOpcionesRelacionadasRecepcion = res.ofe_recepcion === 'SI' ? true : false;

                if(res.ofe_eventos_notificacion !== null && res.ofe_eventos_notificacion !== undefined && res.ofe_eventos_notificacion !== '') {
                    let ofeEventosNotificacion = JSON.parse(res.ofe_eventos_notificacion);
                    this.ofe_evento_notificacion_open.setValue(ofeEventosNotificacion.open === true ? 'SI' : 'NO');
                    this.ofe_evento_notificacion_click.setValue(ofeEventosNotificacion.click === true ? 'SI' : 'NO');
                } else {
                    this.ofe_evento_notificacion_open.setValue('NO');
                    this.ofe_evento_notificacion_click.setValue('NO');
                }

                this.ofe_prioridad_agendamiento.setValue((res.ofe_prioridad_agendamiento !== null && res.ofe_prioridad_agendamiento !== undefined && res.ofe_prioridad_agendamiento !== '') ? res.ofe_prioridad_agendamiento : null);

                this.emision_aceptaciont.setValue('NO');
                this.emision_acuse_recibo.setValue('NO');
                this.emision_recibo_bien.setValue('NO');
                this.emision_aceptacion.setValue('NO');
                this.emision_rechazo.setValue('NO');
                this.emision_fecha_inicio_consulta_eventos_dian.setValue('');
                if(res.ofe_emision_eventos_contratados_titulo_valor) {
                    res.ofe_emision_eventos_contratados_titulo_valor.forEach(elemento => {
                        switch (elemento.evento) {
                            case 'ACEPTACIONT':
                                this.emision_aceptaciont.setValue('SI');
                                this.emision_fecha_inicio_consulta_eventos_dian.setValue(elemento.fecha_inicio ? elemento.fecha_inicio : '');
                                this.emision_fecha_inicio_consulta_eventos_dian.setValidators([Validators.required]);
                                this.emision_fecha_inicio_consulta_eventos_dian.updateValueAndValidity();
                                break;
                            case 'ACUSERECIBO':
                                this.emision_acuse_recibo.setValue('SI');
                                break;
                            case 'RECIBOBIEN':
                                this.emision_recibo_bien.setValue('SI');
                                break;
                            case 'ACEPTACION':
                                this.emision_aceptacion.setValue('SI');
                                break;
                            case 'RECHAZO':
                                this.emision_rechazo.setValue('SI');
                                break;
                            default:
                                break;
                        }
                    });
                }

                this.recepcion_get_status.setValue('NO');
                this.recepcion_acuse_recibo.setValue('NO');
                this.recepcion_recibo_bien.setValue('NO');
                this.recepcion_aceptacion.setValue('NO');
                this.recepcion_rechazo.setValue('NO');
                this.recepcion_aceptaciont.setValue('NO');
                this.recepcion_fecha_inicio_consulta_eventos_dian.setValue('');
                if(res.ofe_recepcion_eventos_contratados_titulo_valor) {
                    res.ofe_recepcion_eventos_contratados_titulo_valor.forEach(elemento => {
                        let objEvento : EventosDianInterface = elemento;
                        switch (objEvento.evento) {
                            case 'GETSTATUS':
                                this.recepcion_get_status.setValue('SI');
                                break;
                            case 'ACUSERECIBO':
                                this.recepcion_acuse_recibo.setValue('SI');
                                if(objEvento.generacion_automatica === 'SI') {
                                    this.recepcion_generacion_automatica_acuse_recibo.setValue('SI');
                                    this.aplicaGeneracionAutomaticaAcuseRecibo = true;
                                    this.infoEventosDian.setDataFormulario(objEvento);
                                } else
                                    this.recepcion_generacion_automatica_acuse_recibo.setValue('NO');
                                break;
                            case 'RECIBOBIEN':
                                this.recepcion_recibo_bien.setValue('SI');
                                if(objEvento.generacion_automatica === 'SI') {
                                    this.recepcion_generacion_automatica_recibo_bien.setValue('SI');
                                    this.aplicaGeneracionAutomaticaReciboBien = true;
                                    this.infoEventosDian.setDataFormulario(objEvento);
                                } else
                                    this.recepcion_generacion_automatica_recibo_bien.setValue('NO');
                                break;
                            case 'ACEPTACION':
                                this.recepcion_aceptacion.setValue('SI');
                                break;
                            case 'RECHAZO':
                                this.recepcion_rechazo.setValue('SI');
                                break;
                            case 'ACEPTACIONT':
                                this.recepcion_aceptaciont.setValue('SI');
                                this.recepcion_fecha_inicio_consulta_eventos_dian.setValue(elemento.fecha_inicio ? elemento.fecha_inicio : '');
                                this.recepcion_fecha_inicio_consulta_eventos_dian.setValidators([Validators.required]);
                                this.recepcion_fecha_inicio_consulta_eventos_dian.updateValueAndValidity();
                                break;
                            default:
                                break;
                        }
                    });
                }

                this.marcarOpcionesEmisionEventosDian(this.emision_aceptaciont.value);
                this.marcarOpcionesRecepcionEventosDian(this.recepcion_get_status.value);
                this.mostrarRecepcionInicioConsultaEventosDian(this.recepcion_aceptaciont.value);
                if (this.aplicaGeneracionAutomaticaAcuseRecibo || this.aplicaGeneracionAutomaticaReciboBien)
                    this.infoEventosDian.changeCamposRequeridos('SI');
                else 
                    this.infoEventosDian.changeCamposRequeridos('NO');

                this.ofe_recepcion_correo_estandar.setValue((res.ofe_recepcion_correo_estandar !== null && res.ofe_recepcion_correo_estandar !== undefined && res.ofe_recepcion_correo_estandar !== '') ? res.ofe_recepcion_correo_estandar : 'NO');

                if (res.logoEventosDian) {
                    this.imgUrl = this.sanitizer.bypassSecurityTrustUrl(res.logoEventosDian);
                    this.logoNotificacionEventosDian = this.dataURItoFile(this.imgUrl.changingThisBreaksApplicationSecurity);
                }

                if(res.ofe_envio_notificacion_amazon_ses === 'SI') {
                    this.ofe_envio_notificacion_amazon_ses.setValue('SI');
                } else {
                    this.ofe_envio_notificacion_amazon_ses.setValue('NO');
                }

                if(res.ofe_envio_notificacion_amazon_ses === 'SI' && res.ofe_conexion_smtp !== null && res.ofe_conexion_smtp !== undefined && res.ofe_conexion_smtp !== '') {
                    this.aws_access_key_id.setValue(res.ofe_conexion_smtp.AWS_ACCESS_KEY_ID);
                    this.aws_secret_access_key.setValue(res.ofe_conexion_smtp.AWS_SECRET_ACCESS_KEY);
                    this.aws_from_email.setValue(res.ofe_conexion_smtp.AWS_FROM_EMAIL);
                    this.aws_region.setValue(res.ofe_conexion_smtp.AWS_REGION);
                    this.aws_ses_configuration_set.setValue(res.ofe_conexion_smtp.AWS_SES_CONFIGURATION_SET);
                }
                
                if(res.ofe_envio_notificacion_amazon_ses === 'NO' && res.ofe_conexion_smtp !== null && res.ofe_conexion_smtp !== undefined && res.ofe_conexion_smtp !== '') {
                    this.smtp_driver.setValue(res.ofe_conexion_smtp.driver);
                    this.smtp_host.setValue(res.ofe_conexion_smtp.host);
                    this.smtp_puerto.setValue(res.ofe_conexion_smtp.port);
                    this.smtp_encriptacion.setValue(res.ofe_conexion_smtp.encryption);
                    this.smtp_usuario.setValue(res.ofe_conexion_smtp.usuario);
                    this.smtp_password.setValue(res.ofe_conexion_smtp.password);
                    this.smtp_email.setValue(res.ofe_conexion_smtp.from_email);
                    this.smtp_nombre.setValue(res.ofe_conexion_smtp.from_nombre);
                } else if (
                    res.ofe_envio_notificacion_amazon_ses === 'NO' && res.ofe_conexion_smtp !== null && res.ofe_conexion_smtp !== undefined && res.ofe_conexion_smtp !== '' &&
                    !res.ofe_conexion_smtp.driver && !res.ofe_conexion_smtp.host && !res.ofe_conexion_smtp.port && !res.ofe_conexion_smtp.encryption && !res.ofe_conexion_smtp.usuario &&
                    !res.ofe_conexion_smtp.password && res.ofe_conexion_smtp.email && res.ofe_conexion_smtp.email !== '' && !res.ofe_conexion_smtp.nombre
                )
                {
                    this.smtp_driver.setValue('');
                    this.smtp_host.setValue('');
                    this.smtp_puerto.setValue('');
                    this.smtp_encriptacion.setValue('');
                    this.smtp_usuario.setValue('');
                    this.smtp_password.setValue('');
                    this.smtp_email.setValue(res.ofe_conexion_smtp.from_email);
                    this.smtp_nombre.setValue('');
                }

                this.camposAwsSes(res.ofe_envio_notificacion_amazon_ses);

                this.ofe_recepcion_transmision_erp.setValue(res.ofe_recepcion_transmision_erp === 'SI' ? 'SI' : 'NO');
                this.ofe_recepcion_conexion_erp.setValue(res.ofe_recepcion_conexion_erp ? JSON.stringify(res.ofe_recepcion_conexion_erp, undefined, 4) : '');
                this.readonlyConexionErp = res.ofe_recepcion_transmision_erp === 'SI' ? false : true;

                this.ofe_cadisoft_activo.setValue((res.ofe_cadisoft_activo !== null && res.ofe_cadisoft_activo !== undefined && res.ofe_cadisoft_activo !== '') ? res.ofe_cadisoft_activo : 'NO');
                if(this.ofe_cadisoft_activo.value === 'SI') {
                    this.activarDesactivarCadisoft(this.ofe_cadisoft_activo.value);

                    let ofeCadisoftConfiguracion = JSON.parse(res.ofe_cadisoft_configuracion);
                    this.ofe_cadisoft_api_fc.setValue(ofeCadisoftConfiguracion.api_facturas);
                    this.ofe_cadisoft_api_fc_usuario.setValue(ofeCadisoftConfiguracion.usuario_facturas);
                    this.ofe_cadisoft_api_fc_password.setValue(ofeCadisoftConfiguracion.password_facturas);
                    this.ofe_cadisoft_api_notas.setValue(ofeCadisoftConfiguracion.api_notas);
                    this.ofe_cadisoft_api_notas_usuario.setValue(ofeCadisoftConfiguracion.usuario_notas);
                    this.ofe_cadisoft_api_notas_password.setValue(ofeCadisoftConfiguracion.password_notas);
                    this.ofe_cadisoft_frecuencia.setValue(ofeCadisoftConfiguracion.frecuencia_ejecucion);
                    this.ofe_cadisoft_logo.setValue('sdfasdf.png');
                    
                    this.imgUrlCadisoft = this.sanitizer.bypassSecurityTrustUrl(res.logoCadisoft);
                    this.logoCadisoft   = this.dataURItoFile(this.imgUrlCadisoft.changingThisBreaksApplicationSecurity);
                }

                this._commonsService.getDataInitForBuild('tat=true').subscribe(
                    result => {
                        this.tiposDocumento = result.data.tipo_documentos;
                        this.arrIntegracionOfes = result.data.ofes;
                        this.arrIntegracionOfes.map(resArrIntegracion => {​​​
                            this.mostrarIntegracionEcm = resArrIntegracion.integracion_variable_ecm;
                            if (resArrIntegracion.variable_modulos_integracion_ecm !== null) {​​​
                                this.modulosItems = JSON.parse(resArrIntegracion.variable_modulos_integracion_ecm);
                                this.modulosItems.forEach( (alias, id) => {​​​
                                    const objIntegracionEcm = new Object();
                                    objIntegracionEcm['alias'] = alias;
                                    objIntegracionEcm['id'] = id;
                                    this.itemsSelectEmision.push(objIntegracionEcm);
                                }​​​);
                            }​​​
                            this.contModulosIntegracion = this.itemsSelectEmision.slice(0, 2).length;
                            this.itemsSelectEmision = this.itemsSelectEmision.slice(0,2);
                        }​​​);

                        let ofeIntegracionEcmConexion = (res.ofe_integracion_ecm_conexion !== null && res.ofe_integracion_ecm_conexion !== undefined && res.ofe_integracion_ecm_conexion !== '') ? JSON.parse(res.ofe_integracion_ecm_conexion) : '';

                        this.ofe_integracion_ecm.setValue((res.ofe_integracion_ecm !== null && res.ofe_integracion_ecm !== undefined && res.ofe_integracion_ecm !== '') ? res.ofe_integracion_ecm : 'NO');
                        if (this.ofe_integracion_ecm.value === 'SI' && res.ofe_integracion_ecm_conexion !== undefined && res.ofe_integracion_ecm_conexion !== null && res.ofe_integracion_ecm_conexion !== '') {
                            this.integracionNo = false;
                            this.ofe_integracion_ecm_id_bd.setValue(ofeIntegracionEcmConexion.bdd_id_ecm); 
                            this.ofe_integracion_ecm_id_negocio.setValue(ofeIntegracionEcmConexion.id_negocio_ecm); 
                            this.ofe_integracion_ecm_url_api.setValue(ofeIntegracionEcmConexion.url_api);

                        } else {
                            this.integracionNo = true;
                            this.ofe_integracion_ecm_id_bd.setValue(''); 
                            this.ofe_integracion_ecm_id_negocio.setValue(''); 
                            this.ofe_integracion_ecm_url_api.setValue(''); 
                        }

                        if (this.ofe_integracion_ecm.value === 'SI' && res.ofe_integracion_ecm_conexion !== undefined && res.ofe_integracion_ecm_conexion !== null && res.ofe_integracion_ecm_conexion !== '') {
                            if (ofeIntegracionEcmConexion.servicios) {
                                ofeIntegracionEcmConexion.servicios.forEach((item, index) => {
                                    if (index <= this.contModulosIntegracion) {
                                        this.agregarIntegracionOpenEcm();
                                    }
                                    let integracionesEcmModulos = this.formulario.get('integracionEcm.integracionesEcm') as FormArray;
                                    integracionesEcmModulos.at(index).patchValue({
                                        id_modulo_integracion_ecm   : item.modulo,
                                        id_servicio_integracion_ecm : item.id_servicio,
                                        id_sitio_integracion_ecm    : item.id_sitio,
                                        id_grupo_integracion_ecm    : item.id_grupo
                                    });
                                });
                            }
                        }
                        this.camposIntegracionOpenEcm(this.ofe_integracion_ecm.value);

                        this.loading(false);
                    }, error => {
                        const texto_errores = this.parseError(error);
                        this.loading(false);
                        this.showError(texto_errores, 'error', 'Error al cargar los parámetros', 'Ok', 'btn btn-danger');
                    }
                );
            },
            error => {
                this.loading(false);
                const texto_errores = this.parseError(error);
                this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al cargar la configuración de los servicios del Oferente', 'Ok', 'btn btn-danger', 'configuracion/oferentes', this._router);
            }
        );
    }

    /**
     * Convierte la Data Uri con la imagen que se obtiene en Edición a un objeto File.
     *
     * @param {*} dataURI Data Uri de la Imagen
     * @return {*} 
     * @memberof ConfiguracionServiciosComponent
     */
    dataURItoFile(dataURI) {
        if(dataURI !== '' && dataURI !== undefined) {
            // separar y decodificar el string base 64
            const byteString = atob(dataURI.split(',')[1]);

            // separar el mime type
            const mimeString = dataURI.split(',')[0].split(':')[1].split(';')[0];

            // pasar los bytes del string a un ArrayBuffer
            const ab = new ArrayBuffer(byteString.length);

            // crear una vista en el Buffer
            const ia = new Uint8Array(ab);

            // establece los bytes del Buffer a los valores correctos
            for (let i = 0; i < byteString.length; i++) {
                ia[i] = byteString.charCodeAt(i);
            }

            const file = new File([ab], 'logo.' + mimeString.split('/')[1], {
                type: mimeString,
            });

            return file;
        }
    }

    /**
     * Actualiza el valor del control abstracto ofe_recepcion_transmision_erp.
     *
     * @memberof ConfiguracionServiciosComponent
     */
    public actualizaRecepcionTransmisionErp() {
        this.ofe_recepcion_transmision_erp.setValue('NO');
    }

    /**
     * Inhabilita, habilita y marca por defecto opciones de Recepción Eventos Dian conforme al valor seleccionado para el FormControl recepcion_get_status.
     *
     * @param {*} valor Valor seleccionado
     * @memberof ConfiguracionServiciosComponent
     */
    public marcarOpcionesRecepcionEventosDian(valor) {
        if(valor === 'NO') {
            this.recepcion_acuse_recibo.setValue('NO');
            this.recepcion_acuse_recibo.disable({emitEvent: false});
            this.recepcion_recibo_bien.setValue('NO');
            this.recepcion_recibo_bien.disable({emitEvent: false});
            this.recepcion_aceptacion.setValue('NO');
            this.recepcion_aceptacion.disable({emitEvent: false});
            this.recepcion_rechazo.setValue('NO');
            this.recepcion_rechazo.disable({emitEvent: false});
            this.changeAcuseRecibo('NO');
            this.changeReciboBien('NO');
        } else {
            this.recepcion_acuse_recibo.enable({emitEvent: false});
            this.recepcion_recibo_bien.enable({emitEvent: false});
            this.recepcion_aceptacion.enable({emitEvent: false});
            this.recepcion_rechazo.enable({emitEvent: false});
        }
    }

    /**
     * Inhabilita, habilita y marca por defecto opciones de Emisión Eventos Dian conforme al valor seleccionado para el FormControl de aceptación tácita.
     *
     * @param {string} valor Valor seleccionado
     * @memberof ConfiguracionServiciosComponent
     */
    public marcarOpcionesEmisionEventosDian(valor: string) {
        if(valor === 'NO') {
            this.emision_acuse_recibo.setValue('NO');
            this.emision_acuse_recibo.disable({emitEvent: false});
            this.emision_recibo_bien.setValue('NO');
            this.emision_recibo_bien.disable({emitEvent: false});
            this.emision_aceptacion.setValue('NO');
            this.emision_aceptacion.disable({emitEvent: false});
            this.emision_rechazo.setValue('NO');
            this.emision_rechazo.disable({emitEvent: false});
            this.emision_fecha_inicio_consulta_eventos_dian.setValue('');
            this.emision_fecha_inicio_consulta_eventos_dian.disable({emitEvent: false});
        } else {
            this.emision_acuse_recibo.enable({emitEvent: false});
            this.emision_recibo_bien.enable({emitEvent: false});
            this.emision_aceptacion.enable({emitEvent: false});
            this.emision_rechazo.enable({emitEvent: false});
            this.emision_fecha_inicio_consulta_eventos_dian.enable({emitEvent: false});
        }
    }

    /**
     * Muestra u oculta el campo para seleccionar la fecha de inicio de consulta de eventos DIAN en Recepción.
     *
     * @param {string} valor Valor seleccionado
     * @memberof ConfiguracionServiciosComponent
     */
    public mostrarRecepcionInicioConsultaEventosDian(valor: string) {
        if(valor === 'NO') {
            this.recepcion_fecha_inicio_consulta_eventos_dian.setValue('');
            this.recepcion_fecha_inicio_consulta_eventos_dian.disable({emitEvent: false});
            this.recepcion_fecha_inicio_consulta_eventos_dian.setValidators([]);
        } else {
            this.recepcion_fecha_inicio_consulta_eventos_dian.enable({emitEvent: false});
            this.recepcion_fecha_inicio_consulta_eventos_dian.setValidators([Validators.required]);
        }

        this.recepcion_fecha_inicio_consulta_eventos_dian.updateValueAndValidity();
    }

    /**
     * Permite previsualizar una imagen dentro del componente.
     *
     * @param {File} files Archivo(s) cargados en el navegador dle usuario
     * @param {string} opcion Opción que indica en donde se debe previsualizar la imagen
     * @return {void} 
     * @memberof ConfiguracionServiciosComponent
     */
    previsualizarImagen(files, opcion: string) {
        if (files.length === 0) {
            return;
        }
        const mimeType = files[0].type;
        if (mimeType.match(/image\/png/) == null) {
            this.showError('<h4>Debe seleccionar una imagen en formato PNG</h4>', 'error', 'Error en selección del logo', 'Ok', 'btn btn-danger');
            return;
        }

        if(opcion === 'cadisoft')
            this.imgPathCadisoft = files;
        else
            this.imgPath = files;

        const reader = new FileReader();
        reader.readAsDataURL(files[0]);
        reader.onload = (_event) => {
            const img = new Image();
            img.src = window.URL.createObjectURL(files[0]);
            img.onload = () => {
                const width  = img.naturalWidth;
                const height = img.naturalHeight;
                window.URL.revokeObjectURL(img.src);
                if (width > 200) {
                    this.showError('<h4>El logo debe tener un máximo de 200 píxeles de ancho</h4>', 'error', 'Error en selección del logo', 'Ok', 'btn btn-danger');
                    this.quitarLogo(opcion);
                    return;
                } else if (height > 150) {
                    this.showError('<h4>El logo debe tener un máximo de 150 píxeles de alto</h4>', 'error', 'Error en selección del logo', 'Ok', 'btn btn-danger');
                    this.quitarLogo(opcion);
                    return;
                }

                if(opcion === 'cadisoft') {
                    this.imgUrlCadisoft = reader.result;
                    this.configuracionCadisoft.controls['ofe_cadisoft_logo'].setValue(files[0] ? files[0].name : '');
                } else {
                    this.imgUrl = reader.result;
                    this.notificacionEventosDian.controls['ofe_recepcion_correo_estandar_logo'].setValue(files[0] ? files[0].name : '');
                }
            };
        };

        if(opcion === 'cadisoft')
            this.logoCadisoft = files[0];
        else
            this.logoNotificacionEventosDian = files[0];
    }

    /**
     * Manejador para el click del botón de Eliminar Imagen del Logo en sección Cadisoft.
     *
     * @param {string} opcion Opción que indica en donde se debe previsualizar la imagen
     * @memberof ConfiguracionServiciosComponent
     */
    quitarLogo(opcion:string){
        if(opcion === 'cadisoft') {
            if (this.configuracionCadisoft.value === 'SI') {
                this.ofe_cadisoft_logo.setValidators([Validators.required]);
            }
            this.imgUrlCadisoft = null;
            this.logoCadisoft   = null;
            this.ofe_cadisoft_logo.setValue(null);
        } else {
            this.imgUrl = null;
            this.logoNotificacionEventosDian   = null;
            this.ofe_recepcion_correo_estandar_logo.setValue(null);
        }
    }

    /**
     * Reestablece valores de controles abstractos de AWS SES.
     *
     * @param {string} valor Indica si está activo o no el envió de notificaciones a través del servicio AWS SES
     * @memberof ConfiguracionServiciosComponent
     */
    public camposAwsSes(valor: string) {
        if(valor === 'SI') {
            this.propiedadOcultar = 'SI';
            this.aws_access_key_id.enable({emitEvent: false});
            this.aws_secret_access_key.enable({emitEvent: false});
            this.aws_from_email.enable({emitEvent: false});
            this.aws_region.enable({emitEvent: false});
            this.aws_ses_configuration_set.enable({emitEvent: false});

            this.smtp_driver.setValue('');
            this.smtp_host.setValue('');
            this.smtp_puerto.setValue('');
            this.smtp_encriptacion.setValue('');
            this.smtp_usuario.setValue('');
            this.smtp_password.setValue('');
            this.smtp_email.setValue('');
            this.smtp_nombre.setValue('');

            this.smtp_driver.disable({emitEvent: false});
            this.smtp_host.disable({emitEvent: false});
            this.smtp_puerto.disable({emitEvent: false});
            this.smtp_encriptacion.disable({emitEvent: false});
            this.smtp_usuario.disable({emitEvent: false});
            this.smtp_password.disable({emitEvent: false});
            this.smtp_email.disable({emitEvent: false});
            this.smtp_nombre.disable({emitEvent: false});
        }

        if (valor === 'NO') {
            this.propiedadOcultar = 'NO';
            this.smtp_driver.enable({emitEvent: false});
            this.smtp_host.enable({emitEvent: false});
            this.smtp_puerto.enable({emitEvent: false});
            this.smtp_encriptacion.enable({emitEvent: false});
            this.smtp_usuario.enable({emitEvent: false});
            this.smtp_password.enable({emitEvent: false});
            this.smtp_email.enable({emitEvent: false});
            this.smtp_nombre.enable({emitEvent: false});

            this.aws_access_key_id.setValue('');
            this.aws_secret_access_key.setValue('');
            this.aws_from_email.setValue('');
            this.aws_region.setValue('');
            this.aws_ses_configuration_set.setValue('');

            this.aws_access_key_id.disable({emitEvent: false});
            this.aws_secret_access_key.disable({emitEvent: false});
            this.aws_from_email.disable({emitEvent: false});
            this.aws_region.disable({emitEvent: false});
            this.aws_ses_configuration_set.disable({emitEvent: false});
        }
    }

    /**
     * Activa / Desactiva campos conforme si se activa o no la integración con CADISOFT.
     *
     * @param {string} value Valor de activación
     * @memberof ConfiguracionServiciosComponent
     */
    activarDesactivarCadisoft(value: string) {
        if(value === 'SI') {
            this.ofe_cadisoft_api_fc.setValidators([Validators.required]);
            this.ofe_cadisoft_api_fc_usuario.setValidators([Validators.required]);
            this.ofe_cadisoft_api_fc_password.setValidators([Validators.required]);
            this.ofe_cadisoft_api_notas.setValidators([Validators.required]);
            this.ofe_cadisoft_api_notas_usuario.setValidators([Validators.required]);
            this.ofe_cadisoft_api_notas_password.setValidators([Validators.required]);
            this.ofe_cadisoft_logo.setValidators([Validators.required]);
            this.ofe_cadisoft_frecuencia.setValidators(Validators.compose([Validators.required, Validators.min(1)]));
        } else {
            this.ofe_cadisoft_api_fc.clearValidators();
            this.ofe_cadisoft_api_fc.setValue(null);
            this.ofe_cadisoft_api_fc_usuario.clearValidators();
            this.ofe_cadisoft_api_fc_usuario.setValue(null);
            this.ofe_cadisoft_api_fc_password.clearValidators();
            this.ofe_cadisoft_api_fc_password.setValue(null);
            this.ofe_cadisoft_api_notas.clearValidators();
            this.ofe_cadisoft_api_notas.setValue(null);
            this.ofe_cadisoft_api_notas_usuario.clearValidators();
            this.ofe_cadisoft_api_notas_usuario.setValue(null);
            this.ofe_cadisoft_api_notas_password.clearValidators();
            this.ofe_cadisoft_api_notas_password.setValue(null);
            this.ofe_cadisoft_frecuencia.clearValidators();
            this.ofe_cadisoft_frecuencia.setValue(null);
            this.quitarLogo('cadisoft');
            this.ofe_cadisoft_logo.clearValidators();
        }
        this.ofe_cadisoft_logo.updateValueAndValidity();
    }

    /**
     * Actualiza la información de configuración de servicios del Ofe.
     *
     * @memberof ConfiguracionServiciosComponent
     */
    public guardarConfiguracionServicios() {
        let procesar = true;
        if(
            this.ofe_envio_notificacion_amazon_ses.value === 'SI' &&
            (
                (this.aws_access_key_id.value && (!this.aws_secret_access_key.value || !this.aws_from_email.value || !this.aws_region.value || !this.aws_ses_configuration_set.value)) ||
                (this.aws_secret_access_key.value && (!this.aws_access_key_id.value || !this.aws_from_email.value || !this.aws_region.value || !this.aws_ses_configuration_set.value)) ||
                (this.aws_from_email.value && (!this.aws_access_key_id.value || !this.aws_secret_access_key.value || !this.aws_region.value || !this.aws_ses_configuration_set.value)) ||
                (this.aws_region.value && (!this.aws_access_key_id.value || !this.aws_secret_access_key.value || !this.aws_from_email.value || !this.aws_ses_configuration_set.value)) ||
                (this.aws_ses_configuration_set.value && (!this.aws_access_key_id.value || !this.aws_secret_access_key.value || !this.aws_from_email.value || !this.aws_region.value))
            )
        ) {
            procesar = false;
            this.showError('<h4>Debe ingresar toda la información de configuración del servicio de correo por plataforma de emails</h4>', 'error', 'Error en Configuración de correo por plataforma de emails', 'Ok', 'btn btn-danger');
        }

        if (
            this.ofe_envio_notificacion_amazon_ses.value === 'NO' &&
            (
                (this.smtp_driver.value && (!this.smtp_host.value || !this.smtp_puerto.value || !this.smtp_encriptacion.value || !this.smtp_usuario.value || !this.smtp_password.value || !this.smtp_email.value || !this.smtp_nombre.value)) ||
                (this.smtp_host.value && (!this.smtp_driver.value || !this.smtp_puerto.value || !this.smtp_encriptacion.value || !this.smtp_usuario.value || !this.smtp_password.value || !this.smtp_email.value || !this.smtp_nombre.value)) ||
                (this.smtp_puerto.value && (!this.smtp_driver.value || !this.smtp_host.value || !this.smtp_encriptacion.value || !this.smtp_usuario.value || !this.smtp_password.value || !this.smtp_email.value || !this.smtp_nombre.value)) ||
                (this.smtp_encriptacion.value && (!this.smtp_driver.value || !this.smtp_host.value || !this.smtp_puerto.value || !this.smtp_usuario.value || !this.smtp_password.value || !this.smtp_email.value || !this.smtp_nombre.value)) ||
                (this.smtp_usuario.value && (!this.smtp_driver.value || !this.smtp_host.value || !this.smtp_puerto.value || !this.smtp_encriptacion.value || !this.smtp_password.value || !this.smtp_email.value || !this.smtp_nombre.value)) ||
                (this.smtp_password.value && (!this.smtp_driver.value || !this.smtp_host.value || !this.smtp_puerto.value || !this.smtp_encriptacion.value || !this.smtp_usuario.value || !this.smtp_email.value || !this.smtp_nombre.value)) ||
                (this.smtp_nombre.value && (!this.smtp_driver.value || !this.smtp_host.value || !this.smtp_puerto.value || !this.smtp_encriptacion.value || !this.smtp_usuario.value  || !this.smtp_password.value || !this.smtp_email.value))
            )
        ) {
            procesar = false;
            this.showError('<h4>Debe ingresar toda la información de configuración del servicio de correo por plataforma de emails</h4>', 'error', 'Error en Configuración de correo por plataforma de emails', 'Ok', 'btn btn-danger');
        }

        let json: any;
        if(this.ofe_recepcion_transmision_erp.value === 'SI' && this.ofe_recepcion_conexion_erp) {
            try {
                json = JSON.parse(this.ofe_recepcion_conexion_erp.value);
            } catch(e) {
                procesar = false;
                this.showError('<h4>Indicó que se van a transmitir los datos de recepción a un ERP pero el JSON de la Conexión ERP esta mal formado o no fue ingresado, verifique</h4>', 'error', 'Recepción - Transmisión ERP', 'Ok', 'btn btn-danger');
            }
        }

        // Debe verificar que en el Json de ofe_recepcion_conexion_erp se incluyan las llaves frecuencia_dias, frecuencia_dias_semana, frecuencia_horas y frecuencia_minutos
        if(
            (procesar && json) &&
            (
                (json.frecuencia_dias === undefined || json.frecuencia_dias === null) ||
                (
                    (json.frecuencia_dias_semana === undefined || json.frecuencia_dias_semana === null) ||
                    (
                        (json.frecuencia_dias_semana !== undefined && json.frecuencia_dias_semana !== null && json.frecuencia_dias_semana !== '') &&
                        (
                            json.frecuencia_dias_semana.search('L') === -1 && // Lunes
                            json.frecuencia_dias_semana.search('M') === -1 && // Martes
                            json.frecuencia_dias_semana.search('X') === -1 && // Miércoles
                            json.frecuencia_dias_semana.search('J') === -1 && // Jueves
                            json.frecuencia_dias_semana.search('V') === -1 && // Viernes
                            json.frecuencia_dias_semana.search('S') === -1 && // Sábado
                            json.frecuencia_dias_semana.search('D') === -1    // Domingo
                        )
                    )
                ) ||
                (json.frecuencia_horas === undefined || json.frecuencia_horas === null) ||
                (!json.frecuencia_minutos || json.frecuencia_minutos === undefined || json.frecuencia_minutos === null)
            )
        ) {
            procesar = false;
            this.showError('<h4>Verifique que dentro de la información del JSON de la Conexión ERP incluya los parámetros:<br><br><strong>frecuencia_dias, frecuencia_dias_semana, frecuencia_horas y frecuencia_minutos</strong></h4><br> Teniendo en cuenta que la única propiedad que no puede estar vacia es frecuencia_minutos, y frecuencia_dias_semana cuando no es vacio puede tener los valores L M X J V S (uno o varios de ellos separados por comas)', 'error', 'Recepción - Transmisión ERP', 'Ok', 'btn btn-danger');
        }
        
        if (this.validarEstadoFormulario() && procesar) {
            this.loading(true);
            const payload = this.getPayload();
            this._oferentesService.actualizarConfiguracionServicios(this.logoNotificacionEventosDian, this.logoCadisoft, payload).subscribe(
                response => {
                    this.loading(false);
                    this.showSuccess('<h3>Actualización exitosa</h3>', 'success', 'Configuración de servicios actualizado exitosamente', 'Ok', 'btn btn-success', `/configuracion/oferentes`, this._router);
                },
                error => {
                    this.loading(false);
                    const texto_errores = this.parseError(error);
                    this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al actualizar la configuración de servicios', 'Ok', 'btn btn-danger');
                });
        }
    }

    /**
     * Actualiza la información de configuración de servicios para el Ofe seleccionado.
     *
     * @memberof ConfiguracionServiciosComponent
     */
    public getPayload() {
        let ofe_eventos_notificacion: any = '';
        if(this.ofe_evento_notificacion_open.value === 'SI' || this.ofe_evento_notificacion_click.value === 'SI') {
            ofe_eventos_notificacion = {
                open : this.ofe_evento_notificacion_open.value === 'SI' ? true : false,
                click: this.ofe_evento_notificacion_click.value === 'SI' ? true : false
            }
        } else {
            ofe_eventos_notificacion = {
                open : this.ofe_evento_notificacion_open.value === 'NO' ? false : true,
                click: this.ofe_evento_notificacion_click.value === 'NO' ? false: true
            }
        }

        const emisionEventosDian: object[] = [];
        if(this.emision_aceptaciont.value === 'SI') {
            emisionEventosDian.push({'evento': 'ACEPTACIONT', 'fecha_inicio': moment(this.emision_fecha_inicio_consulta_eventos_dian.value).format("Y-MM-DD")});
        }
        if(this.emision_acuse_recibo.value === 'SI') emisionEventosDian.push({'evento': 'ACUSERECIBO'});
        if(this.emision_recibo_bien.value === 'SI') emisionEventosDian.push({'evento': 'RECIBOBIEN'});
        if(this.emision_aceptacion.value === 'SI') emisionEventosDian.push({'evento': 'ACEPTACION'});
        if(this.emision_rechazo.value === 'SI') emisionEventosDian.push({'evento': 'RECHAZO'});

        let eventoContratado: EventosDianInterface;
        const recepcionEventosDian: object[] = [];
        if(this.recepcion_get_status.value === 'SI') recepcionEventosDian.push({'evento':'GETSTATUS'});
        if(this.recepcion_acuse_recibo.value === 'SI') {
            eventoContratado = {
                evento                : 'ACUSERECIBO',
                generacion_automatica : (this.recepcion_generacion_automatica_acuse_recibo.value === 'SI') ? 'SI' : 'NO',
                tdo_id                : (this.recepcion_generacion_automatica_acuse_recibo.value === 'SI') ? this.infoEventosDian.infoEventosDian.value.tdo_id             : '',
                use_identificacion    : (this.recepcion_generacion_automatica_acuse_recibo.value === 'SI') ? this.infoEventosDian.infoEventosDian.value.use_identificacion : '',
                use_nombres           : (this.recepcion_generacion_automatica_acuse_recibo.value === 'SI') ? this.infoEventosDian.infoEventosDian.value.use_nombres        : '',
                use_apellidos         : (this.recepcion_generacion_automatica_acuse_recibo.value === 'SI') ? this.infoEventosDian.infoEventosDian.value.use_apellidos      : '',
                use_cargo             : (this.recepcion_generacion_automatica_acuse_recibo.value === 'SI') ? this.infoEventosDian.infoEventosDian.value.use_cargo          : '',
                use_area              : (this.recepcion_generacion_automatica_acuse_recibo.value === 'SI') ? this.infoEventosDian.infoEventosDian.value.use_area           : ''
            };
            recepcionEventosDian.push(eventoContratado);
        }

        if(this.recepcion_recibo_bien.value === 'SI') {
            eventoContratado = {
                evento                : 'RECIBOBIEN',
                generacion_automatica : (this.recepcion_generacion_automatica_recibo_bien.value === 'SI') ? 'SI' : 'NO',
                tdo_id                : (this.recepcion_generacion_automatica_recibo_bien.value === 'SI') ? this.infoEventosDian.infoEventosDian.value.tdo_id             : '',
                use_identificacion    : (this.recepcion_generacion_automatica_recibo_bien.value === 'SI') ? this.infoEventosDian.infoEventosDian.value.use_identificacion : '',
                use_nombres           : (this.recepcion_generacion_automatica_recibo_bien.value === 'SI') ? this.infoEventosDian.infoEventosDian.value.use_nombres        : '',
                use_apellidos         : (this.recepcion_generacion_automatica_recibo_bien.value === 'SI') ? this.infoEventosDian.infoEventosDian.value.use_apellidos      : '',
                use_cargo             : (this.recepcion_generacion_automatica_recibo_bien.value === 'SI') ? this.infoEventosDian.infoEventosDian.value.use_cargo          : '',
                use_area              : (this.recepcion_generacion_automatica_recibo_bien.value === 'SI') ? this.infoEventosDian.infoEventosDian.value.use_area           : ''
            };
            recepcionEventosDian.push(eventoContratado);
        }

        if(this.recepcion_aceptacion.value === 'SI') recepcionEventosDian.push({'evento': 'ACEPTACION'});
        if(this.recepcion_rechazo.value === 'SI') recepcionEventosDian.push({'evento': 'RECHAZO'});
        if(this.recepcion_aceptaciont.value === 'SI') {
            recepcionEventosDian.push({'evento': 'ACEPTACIONT', 'fecha_inicio': moment(this.recepcion_fecha_inicio_consulta_eventos_dian.value).format("Y-MM-DD")});
        }

        let ofe_conexion_smtp: any = '';
        if(
            this.ofe_envio_notificacion_amazon_ses.value === 'SI' &&
            this.aws_access_key_id.value !== undefined && this.aws_access_key_id.value !== null && this.aws_access_key_id.value !== '' &&
            this.aws_secret_access_key.value !== undefined && this.aws_secret_access_key.value !== null && this.aws_secret_access_key.value !== '' &&
            this.aws_from_email.value !== undefined && this.aws_from_email.value !== null && this.aws_from_email.value !== '' &&
            this.aws_region.value !== undefined && this.aws_region.value !== null && this.aws_region.value !== '' &&
            this.aws_ses_configuration_set.value !== undefined && this.aws_ses_configuration_set.value !== null && this.aws_ses_configuration_set.value !== ''
        ) {
            ofe_conexion_smtp = {
                AWS_ACCESS_KEY_ID        : this.aws_access_key_id.value,
                AWS_SECRET_ACCESS_KEY    : this.aws_secret_access_key.value,
                AWS_FROM_EMAIL           : this.aws_from_email.value,
                AWS_REGION               : this.aws_region.value,
                AWS_SES_CONFIGURATION_SET: this.aws_ses_configuration_set.value
            }
        }

        if(
            this.ofe_envio_notificacion_amazon_ses.value === 'NO' &&
            this.smtp_driver.value !== undefined && this.smtp_driver.value !== null && this.smtp_driver.value !== '' &&
            this.smtp_host.value !== undefined && this.smtp_host.value !== null && this.smtp_host.value !== '' &&
            this.smtp_puerto.value !== undefined && this.smtp_puerto.value !== null && this.smtp_puerto.value !== '' &&
            this.smtp_encriptacion.value !== undefined && this.smtp_encriptacion.value !== null && this.smtp_encriptacion.value !== '' &&
            this.smtp_usuario.value !== undefined && this.smtp_usuario.value !== null && this.smtp_usuario.value !== '' &&
            this.smtp_password.value !== undefined && this.smtp_password.value !== null && this.smtp_password.value !== '' &&
            this.smtp_email.value !== undefined && this.smtp_email.value !== null && this.smtp_email.value !== '' &&
            this.smtp_nombre.value !== undefined && this.smtp_nombre.value !== null && this.smtp_nombre.value !== ''
        ) {
            ofe_conexion_smtp = {
                driver                 : this.smtp_driver.value,
                host                   : this.smtp_host.value,
                port                   : this.smtp_puerto.value,
                from_email             : this.smtp_email.value,
                from_nombre            : this.smtp_nombre.value,
                encryption             : this.smtp_encriptacion.value,
                usuario                : this.smtp_usuario.value,
                password               : this.smtp_password.value,
            }
        }

        if(
            this.ofe_envio_notificacion_amazon_ses.value === 'NO' &&
            this.smtp_driver.value == '' &&
            this.smtp_host.value == '' &&
            this.smtp_puerto.value == '' &&
            this.smtp_encriptacion.value == '' &&
            this.smtp_usuario.value == '' &&
            this.smtp_password.value == '' &&
            this.smtp_email.value !== undefined && this.smtp_email.value !== null && this.smtp_email.value !== '' &&
            this.smtp_nombre.value == ''
        ) {
            ofe_conexion_smtp = {
                from_email             : this.smtp_email.value,
            }
        }

        let ofe_cadisoft_configuracion: any = '';
        if(this.ofe_cadisoft_activo.value === 'SI') {
            ofe_cadisoft_configuracion = {
                api_facturas        : this.ofe_cadisoft_api_fc.value,
                usuario_facturas    : this.ofe_cadisoft_api_fc_usuario.value,
                password_facturas   : this.ofe_cadisoft_api_fc_password.value,
                api_notas           : this.ofe_cadisoft_api_notas.value,
                usuario_notas       : this.ofe_cadisoft_api_notas_usuario.value,
                password_notas      : this.ofe_cadisoft_api_notas_password.value,
                frecuencia_ejecucion: this.ofe_cadisoft_frecuencia.value
            }
        }

        let arrIntegracionEcm = [];
        this.formulario.controls.integracionEcm.value.integracionesEcm.forEach( reg => {
            const objIntegracionEcm = new Object();
            objIntegracionEcm['modulo']      = reg.id_modulo_integracion_ecm;
            objIntegracionEcm['id_servicio'] = reg.id_servicio_integracion_ecm;
            objIntegracionEcm['id_sitio']    = reg.id_sitio_integracion_ecm;
            objIntegracionEcm['id_grupo']    = reg.id_grupo_integracion_ecm;
            arrIntegracionEcm.push(objIntegracionEcm);
        });

        let ofe_integracion_ecm_conexion: any = '';
        let dataIntegracionEcm: any;
        if (this.ofe_integracion_ecm.value === 'SI') {
            ofe_integracion_ecm_conexion = {
                bdd_id_ecm    : this.ofe_integracion_ecm_id_bd.value,
                id_negocio_ecm: this.ofe_integracion_ecm_id_negocio.value,
                url_api       : this.ofe_integracion_ecm_url_api.value,
                servicios     : arrIntegracionEcm
            };
        }
        dataIntegracionEcm = JSON.stringify(ofe_integracion_ecm_conexion);

        const payload = {
            ofe_identificacion                            : this._ofe_identificacion,
            ofe_emision                                   : this.ofe_emision.value,
            ofe_recepcion                                 : this.ofe_recepcion.value,
            ofe_documento_soporte                         : this.ofe_documento_soporte.value,
            ofe_eventos_notificacion,
            ofe_prioridad_agendamiento                    : this.ofe_prioridad_agendamiento.value !== undefined && this.ofe_prioridad_agendamiento.value !== null ? this.ofe_prioridad_agendamiento.value : '',
            ofe_emision_eventos_contratados_titulo_valor  : emisionEventosDian,
            ofe_recepcion_eventos_contratados_titulo_valor: recepcionEventosDian,
            ofe_recepcion_correo_estandar                 : this.ofe_recepcion_correo_estandar.value,
            ofe_envio_notificacion_amazon_ses             : this.ofe_envio_notificacion_amazon_ses.value,
            ofe_conexion_smtp,
            ofe_recepcion_transmision_erp                 : this.ofe_recepcion_transmision_erp.value,
            ofe_recepcion_conexion_erp                    : this.ofe_recepcion_conexion_erp ? this.ofe_recepcion_conexion_erp.value : '',
            ofe_cadisoft_activo                           : this.ofe_cadisoft_activo.value,
            ofe_cadisoft_configuracion,
            ofe_integracion_ecm                           : this.ofe_integracion_ecm.value,
            ofe_integracion_ecm_conexion                  : dataIntegracionEcm
        }

        return payload;
    }

    /**
     * Permite regresar a la lista de oferentes.
     *
     * @memberof ConfiguracionServiciosComponent
     */
    regresar() {
        this._router.navigate(['configuracion/oferentes']);
    }

    /**
     * Evento que se ejecuta cuando cambia la selección del acuse de recibo en los eventos DIAN.
     *
     * @param {string} value Opción seleccionada SI|NO
     * @memberof ConfiguracionServiciosComponent
     */
    changeAcuseRecibo(value:string) {
        this.recepcion_generacion_automatica_acuse_recibo.setValue('NO');
        if(value === 'NO') {
            this.changeGeneracionAutomaticaAcuseRecibo('NO');
        }
    }

    /**
     * Evento que se ejecuta cuando cambia la selección de la generación automatica del acuse de recibo.
     *
     * @param {string} value Opción seleccionada SI|NO
     * @memberof ConfiguracionServiciosComponent
     */
    changeGeneracionAutomaticaAcuseRecibo(value:string) {
        this.aplicaGeneracionAutomaticaAcuseRecibo = false;
        if(value === 'SI') {
            this.aplicaGeneracionAutomaticaAcuseRecibo = true;
            this.infoEventosDian.changeCamposRequeridos(value);
        }
    }

    /**
     * Evento que se ejecuta cuando cambia la selección del recibo bien en los eventos DIAN.
     *
     * @param {string} value Opción seleccionada SI|NO
     * @memberof ConfiguracionServiciosComponent
     */
    changeReciboBien(value:string) {
        this.recepcion_generacion_automatica_recibo_bien.setValue('NO');
        if(value === 'NO') {
            this.changeGeneracionAutomaticaReciboBien('NO');
        }
    }

    /**
     * Evento que se ejecuta cuando cambia la selección de la generación automatica del recibo bien.
     *
     * @param {string} value Opción seleccionada SI|NO
     * @memberof ConfiguracionServiciosComponent
     */
    changeGeneracionAutomaticaReciboBien(value:string) {
        this.aplicaGeneracionAutomaticaReciboBien = false;
        if(value === 'SI') {
            this.aplicaGeneracionAutomaticaReciboBien = true;
            this.infoEventosDian.changeCamposRequeridos(value);
        }
    }

    /**
     * Válida el estado del formulario para saber si es Valid o Invalid.
     *
     * @return {*} 
     * @memberof ConfiguracionServiciosComponent
     */
    validarEstadoFormulario() {
        let formInfoEventosDian = true;
        if (this.infoEventosDian != undefined)
            formInfoEventosDian = this.infoEventosDian.infoEventosDian.valid;

        if (this.formulario.valid && formInfoEventosDian)
            return true;
        
        return false;
    }

    /**
     * Construccion del formulario de integracionesEcm.
     *
     * @memberof ConfiguracionServiciosComponent
     */
    buildFormularioIntegracionEcm() {
        this.integracionEcm = this._formBuilder.group({
            integracionesEcm: this._formBuilder.array([], Validators.required),
        });

        return this.integracionEcm;
    }

    /**
     * Construcción del formgroup para la configuración de Integracion con ECM.
     *
     * @memberof ConfiguracionServiciosComponent
     */
    buildFormularioOfeIntegracionEcm() {
        this.ofeIntegracionEcm = this._formBuilder.group({
            ofe_integracion_ecm              : ['NO'],
            ofe_integracion_ecm_id_bd        : ['', Validators.compose(
                [
                    Validators.required,
                    Validators.min(1),
                    Validators.pattern('^[0-9]*$')
                ]
            )],
            ofe_integracion_ecm_id_negocio   : ['', Validators.compose(
                [
                    Validators.required,
                    Validators.min(1),
                    Validators.pattern('^[0-9]*$')
                ]
            )],
            ofe_integracion_ecm_url_api      : ['', Validators.compose(
                [
                    Validators.required,
                    Validators.min(1)
                ]
            )],
        });

        this.ofe_integracion_ecm                = this.ofeIntegracionEcm.controls['ofe_integracion_ecm'];
        this.ofe_integracion_ecm_id_bd          = this.ofeIntegracionEcm.controls['ofe_integracion_ecm_id_bd'];
        this.ofe_integracion_ecm_id_negocio     = this.ofeIntegracionEcm.controls['ofe_integracion_ecm_id_negocio'];
        this.ofe_integracion_ecm_url_api        = this.ofeIntegracionEcm.controls['ofe_integracion_ecm_url_api'];

        return this.ofeIntegracionEcm;
    }


    /**
     * Agrega nuevos campos de integracionEcm al formulario.
     *
     * @memberof ConfiguracionServiciosComponent
     */
    agregarIntegracionOpenEcm(): void {
        this.integracionesEcm = this.integracionEcm.get('integracionesEcm') as FormArray;
        if(this.integracionesEcm.length < this.contModulosIntegracion) {
            this.integracionesEcm.push(this.agregarCamposIntegracionEcm());
        } else {
            swal({
                html: '<h2>No puede agregar más Módulos.</h2>',
                type: 'error',
                confirmButtonClass: 'btn btn-danger',
                confirmButtonText: 'Aceptar',
                buttonsStyling: false,
                allowOutsideClick: false
            }).catch(swal.noop);
        }
    }

    /**
     * ELimina un integracionEcm de la grilla.
     *
     * @param {number} i
     * @memberof ConfiguracionServiciosComponent
     */
    eliminarCamposIntegracionOpenEcm(i: number) {
        const CTRL = this.integracionEcm.controls['integracionesEcm'] as FormArray;
        CTRL.removeAt(i);

        let integracionesEcmModulos = this.formulario.get('integracionEcm.integracionesEcm') as FormArray;
        if (integracionesEcmModulos.controls.length == 0) {
            this.agregarIntegracionOpenEcm();
            integracionesEcmModulos.markAsTouched();

            if (this.ofe_integracion_ecm.value === 'NO') {
                integracionesEcmModulos.disable({emitEvent: false});
                integracionesEcmModulos.setErrors(null);
                integracionesEcmModulos.setValidators(null);
            }
        }
    }

    /**
     * Agrega un FormGroup de modulos al formulario de integracionEcm.
     *
     * @return {FormGroup} 
     * @memberof ConfiguracionServiciosComponent
     */
    agregarCamposIntegracionEcm(): FormGroup {
        return this._formBuilder.group({
            id_modulo_integracion_ecm: ['', Validators.compose(
                [
                    Validators.required,
                ]
            )],
            id_servicio_integracion_ecm: ['', Validators.compose(
                [
                    Validators.required,
                    Validators.min(1),
                    Validators.pattern('^[0-9]*$')
                ]
            )],
            id_sitio_integracion_ecm: ['', Validators.compose(
                [
                    Validators.required,
                    Validators.min(1),
                    Validators.pattern('^[0-9]*$')
                ]
            )],
            id_grupo_integracion_ecm: ['', Validators.compose(
                [
                    Validators.required,
                    Validators.min(1),
                    Validators.pattern('^[0-9]*$')
                ]   
            )]
        });
    }

    /**
     * Reestablece valores de controles abstractos de Integracion
     *
     * @param {string} valor Indica si esta activo o no el envio de notificaciones a través del servicio Integracion Ecm
     * @memberof ConfiguracionServiciosComponent
     */
    public camposIntegracionOpenEcm(valor) {
        let integracionesEcmModulos = this.formulario.get('integracionEcm.integracionesEcm') as FormArray;
        if(valor === 'SI') {
            this.integracionNo = false;

            this.ofe_integracion_ecm_id_bd.setValidators([Validators.required, Validators.min(1), Validators.pattern('^[0-9]*$')]);
            this.ofe_integracion_ecm_id_negocio.setValidators([Validators.required, Validators.min(1), Validators.pattern('^[0-9]*$')]);
            this.ofe_integracion_ecm_url_api.setValidators([Validators.required, Validators.min(1)]);

            this.ofe_integracion_ecm_id_bd.enable({emitEvent: false});
            this.ofe_integracion_ecm_id_negocio.enable({emitEvent: false});
            this.ofe_integracion_ecm_url_api.enable({emitEvent: false});

            this.ofe_integracion_ecm_id_bd.updateValueAndValidity();
            this.ofe_integracion_ecm_id_negocio.updateValueAndValidity();
            this.ofe_integracion_ecm_url_api.updateValueAndValidity();

            integracionesEcmModulos.enable({emitEvent: false});

            if (integracionesEcmModulos.controls.length == 0) {
                this.agregarIntegracionOpenEcm();
                integracionesEcmModulos.markAsTouched();
            }

        } else {
            this.integracionNo = true;

            this.ofe_integracion_ecm_id_bd.disable({emitEvent: false});
            this.ofe_integracion_ecm_id_negocio.disable({emitEvent: false});
            this.ofe_integracion_ecm_url_api.disable({emitEvent: false});

            this.ofe_integracion_ecm_id_bd.clearValidators();
            this.ofe_integracion_ecm_id_negocio.clearValidators();
            this.ofe_integracion_ecm_url_api.clearValidators();

            integracionesEcmModulos.disable({emitEvent: false});
            integracionesEcmModulos.setErrors(null);
            integracionesEcmModulos.setValidators(null);
        }
    }
}
