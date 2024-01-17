import { JwtHelperService } from '@auth0/angular-jwt';
import { concat, Observable, of, Subject } from 'rxjs';
import { Component, Inject, OnInit } from '@angular/core';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { GrupoTrabajo } from '../../../../models/grupo-trabajo.model';
import { BaseComponentView } from 'app/main/core/base_component_view';
import { AbstractControl, FormBuilder, FormGroup } from '@angular/forms';
import { catchError, debounceTime, distinctUntilChanged, filter, finalize, switchMap, tap } from 'rxjs/operators';

import { CommonsService } from '../../../../../services/commons/commons.service';
import { ConfiguracionService } from '../../../../../services/configuracion/configuracion.service';
import * as capitalize from 'lodash';

@Component({
    selector: 'grupos-trabajo-usuarios-gestionar',
    templateUrl: './grupos-trabajo-usuarios-gestionar.component.html',
    styleUrls: ['./grupos-trabajo-usuarios-gestionar.component.scss']
})
export class GruposTrabajoUsuariosGestionarComponent extends BaseComponentView implements OnInit{

    public usuario                : any;
    public grupo_trabajo_singular : string;
    public grupo_trabajo_plural   : string;
    action                        : string;
    parent                        : any;
    gtrCodigoToGet                : string;
    ofeIdentificacion              : string;
    usuIdToGet                    : string;
    dataModal                     : any;
    ver                           : boolean;
    usuario_email                 : string = '';

    // Formulario y controles
    public form                      : FormGroup;
    public formErrors                : any;
    public ofe_identificacion        : AbstractControl;
    public usu_id                    : AbstractControl;
    public usu_identificacion_nombre : AbstractControl;
    public gtr_id                    : AbstractControl;
    public gtu_usuario_gestor        : AbstractControl;
    public gtu_usuario_validador     : AbstractControl;

    // Inicializa las propiedades del formulario
    public ofes               : Array<any> = [];
    public ofe_id             : string = "";
    public identificacion     : string = "";
    public usu_identificacion : string = "";
    public usu_email          : string = "";
    public arrGtrCodigo       : any = [];
    public noCoincidences     : boolean;
    filteredUsuarios          : any = [];
    isLoading = false;

    gruposTrabajo$: Observable<any[]>;
    gruposTrabajo: any;
    gruposTrabajoInput$ = new Subject<string>();
    selectedGtrId: any;
    arrGtrBusqueda: any;
    mostrarCamposTipoUsuario: boolean = false

    /**
     * Crea una instancia de GruposTrabajoUsuariosGestionarComponent.
     * 
     * @param {FormBuilder} formBuilder
     * @param {MatDialogRef<GruposTrabajoUsuariosGestionarComponent>} modalRef
     * @param {*} data
     * @param {CommonsService} _commonsService
     * @param {JwtHelperService} _jwtHelperService
     * @param {ConfiguracionService} _configuracionService
     * @memberof GruposTrabajoUsuariosGestionarComponent
     */
    constructor(
        private formBuilder: FormBuilder,
        private modalRef: MatDialogRef<GruposTrabajoUsuariosGestionarComponent>,
        @Inject(MAT_DIALOG_DATA) data,
        private _commonsService: CommonsService,
        private _jwtHelperService: JwtHelperService,
        private _configuracionService: ConfiguracionService
    ) {
        super();
        this.initForm();
        this.parent  = data.parent;
        this.action  = data.action;
        console.log(this.action);
        if(data.item) {
            this.gtrCodigoToGet    = data.item.gtr_codigo;
            this.usuIdToGet        = data.item.usu_email;
            this.ofeIdentificacion = data.item.ofe_identificacion
        }
        this.usuario = this._jwtHelperService.decodeToken();
        this.grupo_trabajo_singular = capitalize.startCase(capitalize.toLower(this.usuario.grupos_trabajo.singular));
        this.grupo_trabajo_plural   = capitalize.startCase(capitalize.toLower(this.usuario.grupos_trabajo.plural));
        this._configuracionService.setSlug = "grupos-trabajo-usuarios";
        this.buildErrorsObjetc();
    }

    /**
     * ngOnInit
     *
     * @memberof GruposTrabajoUsuariosGestionarComponent
     */
    ngOnInit() {
        this.initForBuild();
        if (this.action === 'view' || this.action === 'edit'){
            this.disableFormControl(
                this.ofe_identificacion, 
                this.gtu_usuario_gestor,
                this.gtu_usuario_validador
            );
        }
        
        this.valueChangesUsuarios();
        if (this.action == 'new') {
            this.listarGruposTrabajo();
        }
        this.usu_identificacion_nombre.disable();
        this.gtr_id.disable();
    }

    /**
     * Inicializando el formulario.
     * 
     * @memberof GruposTrabajoUsuariosGestionarComponent
     */
    private initForm(): void {
        this.form = this.formBuilder.group({
            ofe_identificacion        : this.requerido(),
            usu_id                    : this.requerido(),
            usu_identificacion_nombre : this.requerido(),
            gtr_id                    : this.requerido(),
            gtu_usuario_gestor        : [''],
            gtu_usuario_validador     : ['']
        }, {});

        this.ofe_identificacion        = this.form.controls['ofe_identificacion'];
        this.usu_id                    = this.form.controls['usu_id'];
        this.usu_identificacion_nombre = this.form.controls['usu_identificacion_nombre'];
        this.gtr_id                    = this.form.controls['gtr_id'];
        this.gtu_usuario_gestor        = this.form.controls['gtu_usuario_gestor'];
        this.gtu_usuario_validador     = this.form.controls['gtu_usuario_validador'];
    }

    /**
     * Construye un objeto para gestionar los errores en el formulario.
     * 
     * @memberof GruposTrabajoUsuariosGestionarComponent
     */
    public buildErrorsObjetc() {
        this.formErrors = {
            ofe_identificacion: {
                required: 'El Ofe es requerido!'
            },
            usu_id: {
                required: 'El Usuario es requerido!'
            },
            gtr_id: {
                required: 'El ' + this.grupo_trabajo_singular + ' es requerido!'
            }
        };
    }

    /**
     * Detecta el cambio de OFE.
     * 
     * @memberof GruposTrabajoUsuariosGestionarComponent
     */
    changeOfe(value): void {
        if (this.action === 'new') {
            let newValue = this.ofes.find(ofe => ofe.ofe_identificacion === value);
            this.ofe_id = newValue.ofe_id;
            this.identificacion = newValue.ofe_identificacion;

            if (newValue.ofe_recepcion_fnc_activo == "SI") 
                this.mostrarCamposTipoUsuario = true;
            else 
                this.mostrarCamposTipoUsuario = false;

        } else if(this.action === 'view' || this.action === 'edit'){
            this.ofe_id = value.ofe_id;
            this.identificacion = value.ofe_identificacion;

            if (value.ofe_recepcion_fnc_activo == "SI" && this.action === 'edit') {
                this.mostrarCamposTipoUsuario = true;   
                this.gtu_usuario_gestor.enable(); 
                this.gtu_usuario_validador.enable(); 
            } else if(value.ofe_recepcion_fnc_activo == "SI" && this.action === 'view'){
                this.mostrarCamposTipoUsuario = true;   
                this.gtu_usuario_gestor.disable(); 
                this.gtu_usuario_validador.disable(); 
            } else {
                this.mostrarCamposTipoUsuario = false;
            }

        } 

        this.usu_identificacion_nombre.enable();
        this.usu_identificacion_nombre.setValue('', {emitEvent: false});
        if (this.action == 'new') {
            this.gtr_id.enable();
            this.gtr_id.setValue('', {emitEvent: false});
        }
        this.arrGtrCodigo  = [];
        this.selectedGtrId = null;
        this.listarGruposTrabajo();
    }

    /**
     * Realiza una búsqueda de los usuarios que estan asociados al Ofe seleccionado.
     * 
     * Muestra una lista de usuarios según la coincidencia del valor diligenciado en el input text de usu_identificacion_nombre.
     * La lista se muestra con la siguiente estructura: Identificación - Nombre.
     * 
     * @memberof GruposTrabajoUsuariosGestionarComponent
     */
    valueChangesUsuarios(): void {
        this.form
        .get('usu_identificacion_nombre')
        .valueChanges
        .pipe(
            filter(value => value.length >= 1),
            debounceTime(1000),
            distinctUntilChanged(),
            tap(() => {
                this.loading(true);
                this.form.get('usu_identificacion_nombre').disable();
            }),
            switchMap(value =>
                this._configuracionService.searchUsuarios(value, btoa(JSON.stringify({'oferente': 'ofe_identificacion|' + this.identificacion})))
                    .pipe(
                        finalize(() => {
                            this.loading(false);
                            if (this.action == 'new')
                                this.form.get('usu_identificacion_nombre').enable();
                            
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
     * @memberof GruposTrabajoUsuariosGestionarComponent
     */
    setUsuario(usuario: any): void {
        this.usu_id.setValue(usuario.usu_id, {emitEvent: false});
        this.usu_identificacion_nombre.setValue(usuario.usu_identificacion + ' - ' + usuario.usu_nombre, {emitEvent: false});
        this.usu_identificacion = usuario.usu_identificacion;
        this.usu_email         = usuario.usu_email;
    }

    /**
     * Limpia la lista de los usuarios obtenidos en el autocompletar del campo usu_identificacion_nombre.
     *
     * @memberof GruposTrabajoUsuariosGestionarComponent
     */
    clearUsuario(): void {
        this.usu_identificacion = "";
        this.usu_email = "";
        if (this.usu_identificacion_nombre.value === ''){
            this.filteredUsuarios = [];
        }
    }

    /**
     * Realiza una búsqueda de los grupos de trabajo que estan asociados al Ofe seleccionado.
     * 
     * @memberof GruposTrabajoUsuariosGestionarComponent
     */
    listarGruposTrabajo(termino: any = null) {
        const vacioGrupos: GrupoTrabajo[] = [];
        this.gruposTrabajo$ = concat(
            of(vacioGrupos),
            this.gruposTrabajoInput$.pipe(
                debounceTime(750),
                filter((query: string) =>  query && query.length > 0),
                distinctUntilChanged(),
                tap(() => this.loading(true)),
                switchMap(term => this._configuracionService.searchGruposTrabajo(term, this.ofe_id).pipe(
                    catchError(() => of(vacioGrupos)),
                    tap((data) => {
                        this.loading(false);
                        this.arrGtrBusqueda = data;
                        this.arrGtrBusqueda.forEach( (grupo) => {
                            if (termino == null) {
                                grupo['gtr_codigo_nombre'] = grupo.gtr_codigo + ' - ' + grupo.gtr_nombre;
                            } else {
                                grupo['gtr_codigo_nombre'] = termino.gtr_codigo + ' - ' + termino.gtr_nombre;
                            }
                        });
                    } 
                )))
            )
        );
    }

    /**
     * Obtiene los grupos de trabajo seleccionados.
     *
     * @param {*} grupos Registros seleccionados de grupos de trabajo
     * @memberof GruposTrabajoUsuariosGestionarComponent
     */
    onGrupoTrabajoSeleccionado(grupos) {
        this.arrGtrCodigo = [];
        if (grupos != null && grupos != '' && grupos != undefined) {
            grupos.forEach(reg => {
                this.arrGtrCodigo.push(reg.gtr_codigo);
            });
        }
    }

    /**
     * Cierra la ventana modal de Asociar Usuarios.
     *
     * @param {*} reload Recargar tracking
     * @memberof GruposTrabajoUsuariosGestionarComponent
     */
    public closeModal(reload): void {
        this.modalRef.close();
        if(reload)
            this.parent.getData();
    }

    /**
     * Inicializa la data necesaria para la construcción del formulario.
     *
     * @private
     * @memberof GruposTrabajoUsuariosGestionarComponent
     */
    private initForBuild() {
        this.loading(true);
        this._commonsService.getDataInitForBuild('tat=false').subscribe(
            result => {
                this.ofes = [];
                result.data.ofes.forEach(ofe => {
                    ofe.ofe_identificacion = ofe.ofe_identificacion;
                    this.ofes.push(ofe);
                });

                if (this.action == 'view' || this.action == 'edit') {
                    this.loadGrupoTrabajoUsuario(); 
                } else {
                    this.loading(false);
                }

            }, error => {
                const texto_errores = this.parseError(error);
                this.loading(false);
                this.showError(texto_errores, 'error', 'Error al cargar los Ofes', 'Ok', 'btn btn-danger');
            }
        );
    }

    /**
     * Permite guardar un usuario asociado a un grupo de trabajo.
     * 
     * @param values Datos a guardar
     * @memberof GruposTrabajoUsuariosGestionarComponent
     */
    public saveGrupoUsuario(values) {
        this.loading(true);
        if (this.form.valid) {
            if (this.action == 'new') {
                let payload = {
                    ofe_identificacion     : values.ofe_identificacion,
                    usu_identificacion     : this.usu_identificacion,
                    usu_email             : this.usu_email,
                    gtr_codigo            : this.arrGtrCodigo,
                    gtu_usuario_gestor    : this.gtu_usuario_gestor.value === true ? 'SI' : null,
                    gtu_usuario_validador : this.gtu_usuario_validador.value === true ? 'SI' : null
                };

                this._configuracionService.create(payload).subscribe(
                    response => {
                        this.loading(false);
                        this.showTimerAlert('<strong>Usuario asociado a ' + this.grupo_trabajo_plural + ' correctamente.</strong>', 'success', 'center', 2000);
                        this.closeModal(true);
                    },
                    error => {
                        this.loading(false);
                        this.mostrarErrores(error, 'Error al asociar el Usuario');
                    }
                );
            } else {
                let payloadAsociarUsuarios = {
                    ofe_identificacion     : this.form.controls['ofe_identificacion'].value,
                    usu_email             : this.usuario_email, 
                    usu_identificacion     : this.form.controls['usu_id'].value,
                    gtr_codigo            : [this.form.controls['gtr_id'].value[0]['gtr_codigo']],
                    gtu_usuario_gestor    : this.gtu_usuario_gestor.value === true || this.gtu_usuario_gestor.value === 'SI' ? 'SI' : null,
                    gtu_usuario_validador : this.gtu_usuario_validador.value === true || this.gtu_usuario_validador.value === 'SI' ? 'SI' : null
                };
                
                this._configuracionService.updateGrupoTrabajoAsociarUsuario(payloadAsociarUsuarios, this.gtrCodigoToGet, this.ofeIdentificacion, this.usuIdToGet).subscribe(
                    response => {
                        this.loading(false);
                        this.showTimerAlert('<strong>' +response.message+ '.</strong>', 'success', 'center', 2000);
                        this.closeModal(true);
                    },
                    error => {
                        this.loading(false);
                        this.showError('<h4>' + error.errors + '</h4>', 'error', 'Error al actualizar el registro', 'Ok', 'btn btn-danger');
                    }
                );
            }
        }
    }

    /**
     * Se encarga de setear los datos del registro de un Grupo de Trabajo que se ha seleccionado en el tracking.
     *
     * @param {*} data Información del registro seleccionado
     * @memberof GruposTrabajoGestionarComponent
     */
    public loadGrupoTrabajoUsuario() {
        this.loading(true);
        this._configuracionService.getGrupoTrabajoAsociado(this.gtrCodigoToGet, this.ofeIdentificacion, this.usuIdToGet).subscribe(
            res => {
                if (res) {
                    this.loading(false);
                    if (res.data.get_grupo_trabajo.get_configuracion_obligado_facturar_electronicamente) {
                        this.ofe_identificacion.setValue(res.data.get_grupo_trabajo.get_configuracion_obligado_facturar_electronicamente.ofe_identificacion);
                        this.changeOfe(res.data.get_grupo_trabajo.get_configuracion_obligado_facturar_electronicamente);
                    }

                    if(res.data.get_usuario) {
                        this.usuario_email = res.data.get_usuario.usu_email;
                        this.usu_id.setValue(res.data.get_usuario.usu_identificacion);
                        let usuarioSelecccionado = res.data.get_usuario.usu_identificacion + '-' + res.data.get_usuario.usu_nombre;
                        this.usu_identificacion_nombre.setValue(usuarioSelecccionado);
                        this.usu_identificacion_nombre.disable();
                    }

                    if (res.data.get_grupo_trabajo) {
                        let objGruposTrabajo = {
                            'gtr_codigo' : res.data.get_grupo_trabajo.gtr_codigo,
                            'gtr_codigo_nombre': res.data.get_grupo_trabajo.gtr_codigo + ' - ' +res.data.get_grupo_trabajo.gtr_nombre,
                            'gtr_id': res.data.get_grupo_trabajo.gtr_id,
                            'gtr_nombre': res.data.get_grupo_trabajo.gtr_nombre
                        };
                        
                        let arrgrupos = [objGruposTrabajo];
                        this.gtr_id.setValue(arrgrupos);
                        this.listarGruposTrabajo(objGruposTrabajo);
                        this.onGrupoTrabajoSeleccionado(arrgrupos);
                    }

                    if ((res.data.gtu_usuario_gestor || res.data.gtu_usuario_validador) && this.mostrarCamposTipoUsuario) {
                        this.gtu_usuario_gestor.setValue((res.data.gtu_usuario_gestor !== null ? res.data.gtu_usuario_gestor : null));
                        this.gtu_usuario_validador.setValue(res.data.gtu_usuario_validador !== null ? res.data.gtu_usuario_validador : null);
                    }
                }
            },
            error => {
                this.loading(false);
                this.mostrarErrores(error, 'Error al cargar el Grupo Trabajo Usuario');
            }
        );
    }
    

    /**
     * Se encarga comparar el item que llega al select con el item seleccionado y asignarlo al select de grupos cuando es edit o view.
     *
     * @param {object} item Información item que llega al select de grupos
     * @param {object} data Información del item que se selcciona en el el select de grupos
     * @memberof GruposTrabajoGestionarComponent
     */
    compareFn(item, selected) {
        return item.value === selected.value;
    }
}
