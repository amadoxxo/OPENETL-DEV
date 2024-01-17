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
    selector: 'grupos-trabajo-proveedores-gestionar',
    templateUrl: './grupos-trabajo-proveedores-gestionar.component.html',
    styleUrls: ['./grupos-trabajo-proveedores-gestionar.component.scss']
})
export class GruposTrabajoProveedoresGestionarComponent extends BaseComponentView implements OnInit{

    public usuario                : any;
    public grupo_trabajo_singular : string;
    public grupo_trabajo_plural   : string;
    action                        : string;
    parent                        : any;

    // Formulario y controles
    public form                      : FormGroup;
    public formErrors                : any;
    public ofe_identificacion        : AbstractControl;
    public pro_id                    : AbstractControl;
    public pro_identificacion_nombre : AbstractControl;
    public gtr_id                    : AbstractControl;
    
    // Inicializa las propiedades del formulario
    public ofes               : Array<any> = [];
    public ofe_id             : string = "";
    public pro_identificacion : string = "";
    public arrGtrCodigo       : any = [];
    public noCoincidences     : boolean;
    filteredProveedores       : any = [];
    isLoading = false;

    gruposTrabajo$: Observable<GrupoTrabajo[]>;
    gruposTrabajoInput$ = new Subject<string>();
    selectedGtrId: any;
    arrGtrBusqueda: any;

    /**
     * Crea una instancia de GruposTrabajoProveedoresGestionarComponent.
     * 
     * @param {FormBuilder} formBuilder
     * @param {MatDialogRef<GruposTrabajoProveedoresGestionarComponent>} modalRef
     * @param {*} data
     * @param {CommonsService} _commonsService
     * @param {JwtHelperService} _jwtHelperService
     * @param {ConfiguracionService} _configuracionService
     * @memberof GruposTrabajoProveedoresGestionarComponent
     */
    constructor(
        private formBuilder: FormBuilder,
        private modalRef: MatDialogRef<GruposTrabajoProveedoresGestionarComponent>,
        @Inject(MAT_DIALOG_DATA) data,
        private _commonsService: CommonsService,
        private _jwtHelperService: JwtHelperService,
        private _configuracionService: ConfiguracionService
    ) {
        super();
        this.initForm();
        this.parent  = data.parent;
        this.action  = data.action;
        this.usuario = this._jwtHelperService.decodeToken();
        this.grupo_trabajo_singular = capitalize.startCase(capitalize.toLower(this.usuario.grupos_trabajo.singular));
        this.grupo_trabajo_plural   = capitalize.startCase(capitalize.toLower(this.usuario.grupos_trabajo.plural));
        this._configuracionService.setSlug = "grupos-trabajo-proveedores";
        this.buildErrorsObjetc();
    }

    /**
     * ngOnInit
     *
     * @memberof GruposTrabajoProveedoresGestionarComponent
     */
    ngOnInit() {
        this.initForBuild();
        this.valueChangesProveedores();
        this.listarGruposTrabajo();
        this.pro_identificacion_nombre.disable();
        this.gtr_id.disable();
    }

    /**
     * Inicializando el formulario.
     * 
     * @memberof GruposTrabajoProveedoresGestionarComponent
     */
    private initForm(): void {
        this.form = this.formBuilder.group({
            ofe_identificacion: this.requerido(),
            pro_id: this.requerido(),
            pro_identificacion_nombre: this.requerido(),
            gtr_id: this.requerido()
        }, {});

        this.ofe_identificacion        = this.form.controls['ofe_identificacion'];
        this.pro_id                    = this.form.controls['pro_id'];
        this.pro_identificacion_nombre = this.form.controls['pro_identificacion_nombre'];
        this.gtr_id                    = this.form.controls['gtr_id'];
    }

    /**
     * Construye un objeto para gestionar los errores en el formulario.
     * 
     * @memberof GruposTrabajoProveedoresGestionarComponent
     */
    public buildErrorsObjetc() {
        this.formErrors = {
            ofe_identificacion: {
                required: 'El Ofe es requerido!'
            },
            pro_id: {
                required: 'El Proveedor es requerido!'
            },
            gtr_id: {
                required: 'El ' + this.grupo_trabajo_singular + ' es requerido!'
            },
        };
    }

    /**
     * Detecta el cambio de OFE.
     * 
     * @memberof GruposTrabajoProveedoresGestionarComponent
     */
    changeOfe(value): void {
        this.ofe_id = value.ofe_id;
        this.pro_identificacion_nombre.enable();
        this.pro_identificacion_nombre.setValue('', {emitEvent: false});
        this.gtr_id.enable();
        this.gtr_id.setValue('', {emitEvent: false});
        this.arrGtrCodigo  = [];
        this.selectedGtrId = null;
        this.listarGruposTrabajo();
    }

    /**
     * Realiza una búsqueda de los proveedores que estan asociados al Ofe seleccionado.
     * 
     * Muestra una lista de proveedores según la coincidencia del valor diligenciado en el input text de pro_identificacion_nombre.
     * La lista se muestra con la siguiente estructura: Identificación - Nombre.
     * 
     * @memberof GruposTrabajoProveedoresGestionarComponent
     */
    valueChangesProveedores(): void {
        this.form
        .get('pro_identificacion_nombre')
        .valueChanges
        .pipe(
            filter(value => value.length >= 1),
            debounceTime(1000),
            distinctUntilChanged(),
            tap(() => {
                this.loading(true);
                this.form.get('pro_identificacion_nombre').disable();
            }),
            switchMap(value =>
                this._configuracionService.searchProveedores(value, this.ofe_id)
                    .pipe(
                        finalize(() => {
                            this.loading(false);
                            this.form.get('pro_identificacion_nombre').enable();
                        })
                    )
            )
        )
        .subscribe(res => {
            this.filteredProveedores = res.data;
            if (this.filteredProveedores.length <= 0) {
                this.filteredProveedores = [];
                this.noCoincidences = true;
            } else {
                this.noCoincidences = false;
            }
        });
    }

    /**
     * Asigna los valores del proveedor seleccionado en el autocompletar.
     *
     * @param {*} proveedor Información el registro
     * @memberof GruposTrabajoProveedoresGestionarComponent
     */
    setProveedor(proveedor: any): void {
        this.pro_id.setValue(proveedor.pro_id, {emitEvent: false});
        this.pro_identificacion_nombre.setValue(proveedor.pro_identificacion + ' - ' + proveedor.nombre_completo, {emitEvent: false});
        this.pro_identificacion = proveedor.pro_identificacion;
    }

    /**
     * Limpia la lista de los proveedores obtenidos en el autocompletar del campo pro_identificacion_nombre.
     *
     * @memberof GruposTrabajoProveedoresGestionarComponent
     */
    clearProveedor(): void {
        this.pro_identificacion = "";
        if (this.pro_identificacion_nombre.value === ''){
            this.filteredProveedores = [];
        }
    }

    /**
     * Realiza una búsqueda de los grupos de trabajo que estan asociados al Ofe seleccionado.
     * 
     * @memberof GruposTrabajoProveedoresGestionarComponent
     */
    listarGruposTrabajo() {
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
                            grupo['gtr_codigo_nombre'] = grupo.gtr_codigo + ' - ' + grupo.gtr_nombre;
                        });
                    } 
                )))
            )
        );
    }

    /**
     * Obtiene los grupos de trabajo seleccionados.
     *
     * @param {*} grupo Registros seleccionados de grupos de trabajo
     * @memberof GruposTrabajoProveedoresGestionarComponent
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
     * Cierra la ventana modal de Asociar Proveedores.
     *
     * @param {*} reload Recargar tracking
     * @memberof GruposTrabajoProveedoresGestionarComponent
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
     * @memberof GruposTrabajoProveedoresGestionarComponent
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
                this.loading(false);
            }, error => {
                const texto_errores = this.parseError(error);
                this.loading(false);
                this.showError(texto_errores, 'error', 'Error al cargar los Ofes', 'Ok', 'btn btn-danger');
            }
        );
    }

    /**
     * Permite guardar un proveedor asociado a un grupo de trabajo.
     * 
     * @param values Datos a guardar
     * @memberof GruposTrabajoProveedoresGestionarComponent
     */
    public saveGrupoProveedor(values) {
        let payload = {
            ofe_identificacion: values.ofe_identificacion.ofe_identificacion,
            pro_identificacion: this.pro_identificacion,
            gtr_codigo        : this.arrGtrCodigo
        }

        this.loading(true);
        if (this.form.valid) {
            this._configuracionService.create(payload).subscribe(
                response => {
                    this.loading(false);
                    this.showTimerAlert('<strong>Proveedor asociado a ' + this.grupo_trabajo_plural + ' correctamente.</strong>', 'success', 'center', 2000);
                    this.closeModal(true);
                },
                error => {
                    this.loading(false);
                    this.mostrarErrores(error, 'Error al asociar el Proveedor');
                }
            );
        }
    }
}
