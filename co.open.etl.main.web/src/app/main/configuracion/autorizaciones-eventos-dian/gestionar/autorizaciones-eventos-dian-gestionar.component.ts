import { AfterViewInit, Component, OnDestroy, OnInit, ViewChild, EventEmitter } from '@angular/core';
import { BaseComponentView } from '../../../core/base_component_view';
import { ActivatedRoute, Router } from '@angular/router';
import { AbstractControl, FormControl, FormBuilder, FormGroup, Validators } from '@angular/forms';
import { concat, of, Subject } from 'rxjs';
import { catchError, debounceTime, distinctUntilChanged, filter, finalize, switchMap, tap } from 'rxjs/operators';
import { CommonsService } from '../../../../services/commons/commons.service';
import { ConfiguracionService } from '../../../../services/configuracion/configuracion.service';
import { JwtHelperService } from '@auth0/angular-jwt';
import { SelectorParReceptorEmisorComponent } from '../../../commons/selector-par-receptor-emisor/selector-par-receptor-emisor.component';
import { Proveedor } from 'app/main/models/proveedor.model';
import { ProveedoresService } from 'app/services/configuracion/proveedores.service';
import { MatAccordion } from '@angular/material/expansion';
import { DatosEventoDianComponent } from '../../../commons/datos-evento-dian/datos-evento-dian.component';

@Component({
    selector: 'app-autorizaciones-eventos-dian-gestionar',
    templateUrl: './autorizaciones-eventos-dian-gestionar.component.html',
    styleUrls: ['./autorizaciones-eventos-dian-gestionar.component.scss']
})
export class AutorizacionesEventosDianGestionarComponent extends BaseComponentView implements OnInit, OnDestroy, AfterViewInit {
    @ViewChild('acordion') acordion: MatAccordion;
    @ViewChild('selectorParReceptorEmisorChild', {static: true})  selectorParReceptorEmisorChild : SelectorParReceptorEmisorComponent;
    @ViewChild('infoEventosDian',                {static: true})  infoEventosDian                : DatosEventoDianComponent;

    public tdoSeleccionado = new EventEmitter();

    // Usuario en línea
    public usuario          : any;
    public objMagic         = {};
    public registroOriginal = {};
    public ver              : boolean = false;
    public editar           : boolean = false;
    public titulo           : string;

    public filteredUsuarios: any = [];
    public isLoading       = false;
    public noCoincidences  : boolean;
    public disabledControls: boolean = false;

    // Formulario y controles
    public usu_id               : AbstractControl;
    public usu_email            : AbstractControl;
    public ofe_id               : AbstractControl;
    public pro_id               : AbstractControl;
    public pro_identificacion   : AbstractControl;
    public gtr_id               : AbstractControl;
    public acuseRecibo          : AbstractControl;
    public reclamo              : AbstractControl;
    public reciboBien           : AbstractControl;
    public aceptacionExpresa    : AbstractControl;

    // Steppers
    public form                   : FormGroup;
    public configuracionAsignacion: FormGroup; 
    public eventosAutorizados     : FormGroup;

    public estado        : AbstractControl;
    public ofes          : Array<any> = [];
    public grupos_trabajo: Array<any> = [];
    public tiposDocumento: Array<any> = [];

    public recurso           : string = 'Autorizaciones Eventos DIAN';
    public _use_id           : any;
    public _use_identificador: any;
    public _grupo_trabajo    : any;

    // Private
    private _unsubscribeAll: Subject<any> = new Subject();

    /**
     * @param {Router} _router
     * @param {ActivatedRoute} _route
     * @param {FormBuilder} _formBuilder
     * @param {CommonsService} _commonsService
     * @param {ConfiguracionService} _configuracionService
     * @param {JwtHelperService} jwtHelperService
     * @param {ProveedoresService} _proveedoresServices
     * @memberof AutorizacionesEventosDianGestionarComponent
     */
    constructor(
        private _router: Router,
        private _route: ActivatedRoute,
        private _formBuilder: FormBuilder,
        private _commonsService: CommonsService,
        private _configuracionService: ConfiguracionService,
        private jwtHelperService: JwtHelperService,
        private _proveedoresServices: ProveedoresService
    ) {
        super();
        this._configuracionService.setSlug = "autorizaciones-eventos-dian";
        this.init();
        this.usuario        = this.jwtHelperService.decodeToken();
        this._grupo_trabajo = this.usuario.grupos_trabajo.singular;
    }

    /**
     * Vista construida.
     *
     * @memberof AutorizacionesEventosDianGestionarComponent
     */
    ngAfterViewInit() {
        if (this.ver)
            this.acordion.openAll();
    }

    /**
     * Se inicializa el componente.
     *
     * @memberof AutorizacionesEventosDianGestionarComponent
     */
    ngOnInit() {
        this.pro_id.disable();
        this.gtr_id.disable();
        this.usu_email.disable();
        this._use_id = this._route.snapshot.params['use_id'];
        this._use_identificador = this._route.snapshot.params['use_identificador'];

        this.ver = false;
        if (this._router.url.indexOf('editar-autorizaciones-eventos-dian') !== -1) {
            this.titulo = 'Editar ' + this.recurso;
            this.editar = true;
            this.valueChangesUsuarios();
        } else if (this._router.url.indexOf('ver-autorizaciones-eventos-dian') !== -1) {
            this.titulo = 'Ver ' + this.recurso;
            this.ver = true;
        } else {
            this.titulo = 'Crear ' + this.recurso;
            this.valueChangesUsuarios();
        }

        this.initForBuild();
        if(this.ver){
            this.disabledControls = true;
            this.ofe_id.disable();
            this.pro_id.disable();
            this.gtr_id.disable();
            this.reclamo.disable();
            this.reciboBien.disable();
            this.acuseRecibo.disable();
            this.aceptacionExpresa.disable();
            this.usu_email.disable();
        }
    }

    /**
     * On destroy.
     *
     * @memberof AutorizacionesEventosDianGestionarComponent
     */
    ngOnDestroy(): void {
        // Unsubscribe from all subscriptions
        this._unsubscribeAll.next(true);
        this._unsubscribeAll.complete();
    }

    /**
     * Inicializacion de los diferentes controles.
     *
     * @memberof AutorizacionesEventosDianGestionarComponent
     */
    init() {
        this.buildFormulario();
    }

    /**
     * Se encarga de cargar los datos de un usuario autorizado evento que se ha seleccionado en el tracking.
     *
     * @memberof AutorizacionesEventosDianGestionarComponent
     */
    public loadAutorizacionesEventosDian(): void {
        this.loading(true);
        this._configuracionService.get(this._use_identificador).subscribe(
            res => {
                if (res) {
                    this.loading(false);
                    let controlEstado: FormControl = new FormControl('', Validators.required);
                    this.form.addControl('estado', controlEstado);
                    this.estado = this.form.controls['estado'];

                    if (res.data.get_configuracion_obligado_facturar_electronicamente) {
                        this.ofe_id.setValue(res.data.get_configuracion_obligado_facturar_electronicamente.ofe_id);
                        this.ofeHasChanged(res.data.get_configuracion_obligado_facturar_electronicamente);

                        this.registroOriginal['ofe_identificacion'] = res.data.get_configuracion_obligado_facturar_electronicamente.ofe_identificacion;
                    }

                    if (res.data.get_configuracion_proveedor) {
                        this.pro_id.enable();
                        this.pro_id.setValue(res.data.get_configuracion_proveedor.pro_id);
                        this.pro_identificacion.setValue(res.data.get_configuracion_proveedor.pro_identificacion);
                        this.selectorParReceptorEmisorChild.onProSeleccionado(res.data.get_configuracion_proveedor);

                        let proIdentificacionRazonSocial = '';
                        if( res.data.get_configuracion_proveedor.pro_id_personalizado != null) 
                            proIdentificacionRazonSocial = '('+  res.data.get_configuracion_proveedor.pro_identificacion +' / '+  res.data.get_configuracion_proveedor.pro_id_personalizado + ') - ' +  res.data.get_configuracion_proveedor.pro_razon_social;
                        else
                            proIdentificacionRazonSocial = '('+ res.data.get_configuracion_proveedor.pro_identificacion + ') - ' +  res.data.get_configuracion_proveedor.pro_razon_social;

                        const proveedor: Proveedor[] = [{
                            pro_id              : res.data.get_configuracion_proveedor.pro_id,
                            pro_identificacion  : res.data.get_configuracion_proveedor.pro_identificacion,
                            pro_razon_social    : res.data.get_configuracion_proveedor.pro_razon_social,
                            pro_identificacion_pro_razon_social: proIdentificacionRazonSocial
                        }]
                        this.selectorParReceptorEmisorChild.proveedores$ = concat(
                            of(proveedor),
                            this.selectorParReceptorEmisorChild.proveedoresInput$.pipe(
                                debounceTime(750),
                                filter((query: string) =>  query && query.length > 0),
                                distinctUntilChanged(),
                                tap(() => this.loading(true)),
                                switchMap(term => this._proveedoresServices.searchProveedorNgSelect(term, res.data.ofe_id).pipe(
                                    catchError(() => of([])),
                                    tap(() => this.loading(false))
                                ))
                            )
                        );

                        this.registroOriginal['pro_identificacion'] = res.data.get_configuracion_proveedor.pro_identificacion;
                    } else {
                        this.registroOriginal['pro_identificacion'] = '';
                    }

                    if (res.data.get_usuario_autorizacion_evento_dian) {
                        this.usu_id.setValue(res.data.get_usuario_autorizacion_evento_dian.usu_email);
                        let usuarioSeleccionado = res.data.get_usuario_autorizacion_evento_dian.usu_identificacion + ' - ' + res.data.get_usuario_autorizacion_evento_dian.usu_nombre + ' - ' + res.data.get_usuario_autorizacion_evento_dian.usu_email;
                        this.usu_email.setValue(usuarioSeleccionado, {emitEvent: false});

                        this.registroOriginal['usu_email'] = res.data.get_usuario_autorizacion_evento_dian.usu_email;
                    } else {
                        this.registroOriginal['usu_email'] = '';
                    }

                    if (res.data.get_configuracion_grupo_trabajo) {
                        this.gtr_id.setValue(res.data.get_configuracion_grupo_trabajo.gtr_codigo, {emitEvent:false});

                        this.registroOriginal['gtr_codigo'] = res.data.get_configuracion_grupo_trabajo.gtr_codigo;
                    } else {
                        this.registroOriginal['gtr_codigo'] = '';
                    }

                    // Setea la información del evento dian
                    this.infoEventosDian.setDataFormulario(res.data);

                    const acuseRecibo = (res.data.use_acuse_recibo && res.data.use_acuse_recibo == 'SI') ? true : false;
                    this.acuseRecibo.setValue(acuseRecibo)

                    const reciboBien = (res.data.use_recibo_bien && res.data.use_recibo_bien == 'SI') ? true : false;
                    this.reciboBien.setValue(reciboBien)

                    const aceptacionExpresa = (res.data.use_aceptacion_expresa && res.data.use_aceptacion_expresa == 'SI') ? true : false;
                    this.aceptacionExpresa.setValue(aceptacionExpresa)

                    const reclamo = (res.data.use_reclamo && res.data.use_reclamo == 'SI') ? true : false;
                    this.reclamo.setValue(reclamo)

                    if (res.data.estado === 'ACTIVO') {
                        this.estado.setValue('ACTIVO');
                    } else {
                        this.estado.setValue('INACTIVO');
                    }
                    this.objMagic['fecha_creacion'] = res.data.fecha_creacion;
                    this.objMagic['fecha_modificacion'] = res.data.fecha_modificacion;
                    this.objMagic['estado'] = res.data.estado;
                }
            },
            error => {
                let texto_errores = this.parseError(error);
                this.loading(false);
                this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al cargar la Autorización Eventos DIAN', 'Ok', 'btn btn-danger', 'configuracion/autorizaciones-eventos-dian', this._router);
            }
        );
    }

    /**
     * Construcción del formulario principal.
     *
     * @memberof AutorizacionesEventosDianGestionarComponent
     */
    buildFormulario() {
        this.form = this._formBuilder.group({
            DatosGenerales     : this.buildFormularioDatosGenerales(),
            EventosAutorizados : this.buildFormularioEventosAutotizados()
        });
    }

    /**
     * Construcción de los datos del selector de datos generales.
     *
     * @return {*} 
     * @memberof AutorizacionesEventosDianGestionarComponent
     */
    buildFormularioDatosGenerales() {
        this.configuracionAsignacion = this._formBuilder.group({
            ofe_id            : this.requerido(),
            pro_id            : [''],
            pro_identificacion: [''],
            gtr_id            : [''],
            usu_id            : [''],
            usu_email         : ['']
        });

        this.ofe_id             = this.configuracionAsignacion.controls['ofe_id'];
        this.pro_id             = this.configuracionAsignacion.controls['pro_id'];
        this.pro_identificacion = this.configuracionAsignacion.controls['pro_identificacion'];
        this.gtr_id             = this.configuracionAsignacion.controls['gtr_id'];
        this.usu_id             = this.configuracionAsignacion.controls['usu_id'];
        this.usu_email          = this.configuracionAsignacion.controls['usu_email'];

        return this.configuracionAsignacion;
    }

    /**
     * Construcción de los datos del selector de eventos autorizados para el usuario.
     *
     * @return {*} 
     * @memberof AutorizacionesEventosDianGestionarComponent
     */
    buildFormularioEventosAutotizados() {
        this.eventosAutorizados = this._formBuilder.group({
            acuseRecibo      : [''],
            reclamo          : [''],
            reciboBien       : [''],
            aceptacionExpresa: ['']
        });

        this.acuseRecibo       = this.eventosAutorizados.controls['acuseRecibo'];
        this.reclamo           = this.eventosAutorizados.controls['reclamo'];
        this.reciboBien        = this.eventosAutorizados.controls['reciboBien'];
        this.aceptacionExpresa = this.eventosAutorizados.controls['aceptacionExpresa'];

        return this.eventosAutorizados;
    }

    /**
     * Permite regresar a la lista de usuarios autorizados eventos.
     *
     * @memberof AutorizacionesEventosDianGestionarComponent
     */
    regresar() {
        this._router.navigate(['configuracion/autorizaciones-eventos-dian']);
    }

    /**
     * Inicializa la data necesaria para la construcción del usuario evento.
     *
     * @private
     * @memberof AutorizacionesEventosDianGestionarComponent
     */
    private initForBuild() {
        this.loading(true);
        this._commonsService.getDataInitForBuild('tat=false').subscribe(
            result => {
                this.ofes = [];
                result.data.ofes.forEach(ofe => {
                    ofe.ofe_identificacion_ofe_razon_social = ofe.ofe_identificacion + ' - ' + ofe.ofe_razon_social;
                    this.ofes.push(ofe);
                });
                this.tiposDocumento = result.data.tipo_documentos;

                if (this._use_identificador)
                    this.loadAutorizacionesEventosDian();
                else
                    this.loading(false);
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
     * @param {*} values
     * @memberof AutorizacionesEventosDianGestionarComponent
     */
    public resourceAutorizacionesEventosDian(values) {
        let payload = this.getPayload();
        if (this.validarEstadoFormulario()) {
            let continuar = true;
            if(!payload.pro_identificacion && !payload.gtr_codigo && !payload.usu_email) {
                continuar = false;
                this.showError('<h4>Debe seleccionar un Emisor o un Usuario o un(a) ' + this.usuario.grupos_trabajo.singular + '</h4>', 'error', 'Error al guardar el Autorización para eventos de la DIAN', 'Ok', 'btn btn-danger');
            }

            if(this.acuseRecibo.value !== true && this.reciboBien.value !== true && this.aceptacionExpresa.value !== true && this.reclamo.value !== true) {
                continuar = false;
                this.showError('<h4>Debe seleccionar por lo menos uno de los eventos DIAN</h4>', 'error', 'Error al guardar el Autorización para eventos de la DIAN', 'Ok', 'btn btn-danger');
            }

            if (this.editar && continuar) {
                this.loading(true);
                payload['estado'] =  this.estado.value;
                this._configuracionService.update(payload, this._use_identificador).subscribe(
                    response => {
                        this.loading(false);
                        this.showSuccess('<h3>Actualización exitosa</h3>', 'success', 'Autorización para eventos de la DIAN actualizado exitosamente', 'Ok', 'btn btn-success', `/configuracion/autorizaciones-eventos-dian`, this._router);
                    },
                    error => {
                        let texto_errores = this.parseError(error);
                        this.loading(false);
                        this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al actualizar el Autorización para eventos de la DIAN', 'Ok', 'btn btn-danger');
                    }
                );
            } else if (continuar && !this.editar && !this.ver) {
                this.loading(true);
                this._configuracionService.create(payload).subscribe(
                    response => {
                        this.loading(false);
                        this.showSuccess('<h3>' + '</h3>', 'success', 'Autorización para eventos de la DIAN creado exitosamente', 'Ok', 'btn btn-success', `/configuracion/autorizaciones-eventos-dian`, this._router);
                    },
                    error => {
                        let texto_errores = this.parseError(error);
                        this.loading(false);
                        this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al guardar el Autorización para eventos de la DIAN', 'Ok', 'btn btn-danger');
                    }
                );
            }
        }
    }

    /**
     * Realiza una búsqueda de los usuarios que pertenecen a la base de datos del usuario autenticacado.
     * 
     * Muestra una lista de usuarios según la coincidencia del valor diligenciado en el input text de usu_email.
     * La lista se muestra con la siguiente estructura: Identificación - Nombre,
     * 
     * @memberof AutorizacionesEventosDianGestionarComponent
     */
    valueChangesUsuarios(): void {
        this.configuracionAsignacion
        .get('usu_email')
        .valueChanges
        .pipe(
            filter(value => value.length >= 1),
            debounceTime(1000),
            distinctUntilChanged(),
            tap(() => {
                this.loading(true);
                this.configuracionAsignacion.get('usu_email').disable();
            }),
            switchMap(value =>
                this._configuracionService.searchUsuarios(value, btoa(JSON.stringify({'grupos_trabajo': 'gtr_id|' + this.gtr_id.value})))
                    .pipe(
                        finalize(() => {
                            this.loading(false);
                            this.configuracionAsignacion.get('usu_email').enable();
                        })
                    )
            )
        )
        .subscribe(res => {
            this.filteredUsuarios = res.data;
            if (this.filteredUsuarios.length <= 0) {
                this.filteredUsuarios = [];
                this.noCoincidences = true;
            } else {
                this.noCoincidences = false;
            }    
        });
    }

    /**
     * Asigna los valores del usuario seleccionado en el autocompletar.
     *
     * @param {*} usuario Información el registro
     * @memberof AutorizacionesEventosDianGestionarComponent
     */
    setUsuId(usuario: any): void {
        this.usu_id.setValue(usuario.usu_email, {emitEvent: false});
        let usuarioSeleccionado = usuario.usu_identificacion_nombre  + ' - ' + usuario.usu_email
        this.usu_email.setValue(usuarioSeleccionado, {emitEvent: false});
    }

    /**
     * Limpia la lista de los usuarios obtenidos en el autocompletar del campo usu_email.
     *
     * @memberof AutorizacionesEventosDianGestionarComponent
     */
    clearUsuario(): void {
        if (this.usu_email.value === ''){
            this.usu_id.setValue('', {emitEvent:false});
            this.usu_email.setValue('', {emitEvent:false});
            this.filteredUsuarios = [];
        }
    }

    /**
     * Crea un json para enviar los campos del formulario.
     *
     * @return {*} 
     * @memberof AutorizacionesEventosDianGestionarComponent
     */
    getPayload(){
        let ofeSeleccionado = this.ofes.filter(ofe => ofe.ofe_id === this.ofe_id.value);
        let payload = {
            "usu_email"             : this.usu_id.value,
            "ofe_identificacion"    : ofeSeleccionado[0].ofe_identificacion,
            "pro_identificacion"    : this.pro_identificacion.value,
            "gtr_codigo"            : this.gtr_id.value,
            "tdo_codigo"            : this.infoEventosDian.infoEventosDian.value.tdo_id,
            "use_identificacion"    : this.infoEventosDian.infoEventosDian.value.use_identificacion,
            "use_nombres"           : this.infoEventosDian.infoEventosDian.value.use_nombres,
            "use_apellidos"         : this.infoEventosDian.infoEventosDian.value.use_apellidos,
            "use_cargo"             : this.infoEventosDian.infoEventosDian.value.use_cargo,
            "use_area"              : this.infoEventosDian.infoEventosDian.value.use_area,
            "use_acuse_recibo"      : this.acuseRecibo.value === true ? 'SI' : null,
            "use_recibo_bien"       : this.reciboBien.value === true ? 'SI' : null,
            "use_aceptacion_expresa": this.aceptacionExpresa.value === true ? 'SI' : null,
            "use_reclamo"           : this.reclamo.value === true ? 'SI' : null,
            "registro_original"     : JSON.stringify(this.registroOriginal)
        };
        return payload;
    }

    /**
     * Permite hacer una búsqueda del registro en grupos de trabajo
     *
     * @param {string} term
     * @param {*} item
     * @return {*} 
     * @memberof AutorizacionesEventosDianGestionarComponent
     */
    customSearchFnGtr(term: string, item) {
        term = term.toLocaleLowerCase();
        return item.gtr_codigo.toLocaleLowerCase().indexOf(term) > -1 || item.gtr_descripcion.toLocaleLowerCase().indexOf(term) > -1;
    }

    /**
     * Monitoriza cuando el valor del select de OFEs cambia para realizar acciones determinadas de acuerdo al OFE.
     * 
     * @param {object} ofe Objeto con la información del OFE seleccionado
     * @memberof AutorizacionesEventosDianGestionarComponent
     */
    ofeHasChanged(ofe) {
        this.usu_id.setValue('', {emitEvent:false});
        this.usu_email.setValue('', {emitEvent:false});

        this.filteredUsuarios = [];
        this.noCoincidences = true;

        this.pro_id.setValue('');
        this.pro_identificacion.setValue('');

        if(ofe) {
            this.pro_id.enable();
            this.gtr_id.setValue('', {emitEvent:false});
            this.usu_email.enable();

            if(ofe.get_grupos_trabajo) {
                this.gtr_id.enable();

                this.grupos_trabajo = ofe.get_grupos_trabajo.filter((grupoTrabajo) => {
                    grupoTrabajo.gtr_codigo_nombre = grupoTrabajo.gtr_codigo + ' - ' + grupoTrabajo.gtr_nombre;
                    return grupoTrabajo;
                });
            } else {
                this.gtr_id.disable();
            }
        } else {
            this.pro_id.disable();
            this.gtr_id.setValue('', {emitEvent:false});
            this.gtr_id.disable();
            this.usu_email.disable();
        }
    }

    /**
     * Monitoriza cuando el valor del select de Proveedores cambia.
     *
     * @param {*} pro Objeto del proveedor seleccionado
     * @memberof AutorizacionesEventosDianGestionarComponent
     */
    proHasChanged(pro) {
        this.pro_id.setValue('', {emitEvent:false});
        this.pro_identificacion.setValue('', {emitEvent:false});

        if(pro) {
            this.pro_id.setValue(pro.pro_id);
            this.pro_identificacion.setValue(pro.pro_identificacion);
        }
    }

    /**
     * Monitoriza cuando el valor del select de Grupos de Trabajo cambia.
     *
     * @param {*} gtr Objeto del grupo de trabajo seleccionado
     * @memberof AutorizacionesEventosDianGestionarComponent
     */
    gtrHasChanged(gtr) {
        this.usu_id.setValue('', {emitEvent:false});
        this.usu_email.setValue('', {emitEvent:false});

        this.filteredUsuarios = [];
        this.noCoincidences = true;
    }

    /**
     * Válida el estado del formulario para saber si es Valid o Invalid.
     *
     * @return {*} 
     * @memberof AutorizacionesEventosDianGestionarComponent
     */
    validarEstadoFormulario() {
        let formInfoEventosDian = true;
        if (this.infoEventosDian != undefined)
            formInfoEventosDian = this.infoEventosDian.infoEventosDian.valid;

        let formEventosAutorizados = false;
        if (this.acuseRecibo.value || this.reciboBien.value || this.aceptacionExpresa.value || this.reclamo.value)
            formEventosAutorizados = true;

        if (this.form.valid && formInfoEventosDian && formEventosAutorizados)
            return true;
        
        return false;
    }
}
