import {Component, Input, OnInit, ViewChild} from '@angular/core';
import {FormBuilder, FormGroup, AbstractControl, FormArray, ControlValueAccessor, Validators} from '@angular/forms';
import {PaisesService} from '../../../services/parametros/paises.service';
import {BaseComponent} from '../../core/base_component';
import {CodigoPostal} from '../../models/codigo-postal.model';
import {DepartamentosService} from '../../../services/parametros/departamentos.service';
import {ParametrosService} from '../../../services/parametros/parametros.service';
import {MunicipiosService} from '../../../services/parametros/municipios.service';
import {concat, Observable, of, Subject} from 'rxjs';
import {Pais} from '../../models/pais.model';
import {
    catchError,
    debounceTime,
    distinctUntilChanged,
    filter,
    switchMap,
    tap
} from 'rxjs/operators';
import {Departamento} from '../../models/departamento.model';
import {Municipio} from '../../models/municipio.model';
import {NgSelectComponent} from '@ng-select/ng-select';

@Component({
    selector: 'app-ubicacion-open',
    templateUrl: './ubicacion-open.component.html',
    styleUrls: ['./ubicacion-open.component.scss']
})
export class UbicacionOpenComponent extends BaseComponent implements ControlValueAccessor, OnInit {

    @ViewChild('selectPaises') selectPaises: NgSelectComponent;
    @ViewChild('selectDepartamentos', { static: true }) selectDepartamentos: NgSelectComponent;
    @ViewChild('selectMunicipios', { static: true }) selectMunicipios: NgSelectComponent;

    @Input() pai_id: AbstractControl = null;
    @Input() mun_id: AbstractControl = null;
    @Input() dep_id: AbstractControl = null;
    @Input() direccion: AbstractControl = null;
    @Input() telefono: AbstractControl = null;
    @Input() codigo_postal: AbstractControl = null;
    @Input() sololectura: boolean;
    @Input() tipoUbicacion: string;
    @Input() longitudTelefono: number;
    @Input() tipo: string;
    @Input() ver: boolean;
    @Input() validacionOnFly: any = null;
    @Input() direccionRequerida?: boolean = false;

    // Observables
    paises$: Observable<Pais[]>;
    paisesInput$ = new Subject<string>();
    paisesLoading = false;
    selectedPaiId: any;

    departamentos$: Observable<Departamento[]>;
    departamentosInput$ = new Subject<string>();
    departamentosLoading = false;
    selectedDepId: any;
    
    municipios$: Observable<Municipio[]>;
    municipiosInput$ = new Subject<string>();
    municipiosLoading = false;
    selectedMunId: any;
    
    codigos$: Observable<CodigoPostal[]>;
    codigosInput$ = new Subject<string>();
    codigosLoading = false;
    selectedCpoId: any;
    
    formDireccionesAdicionales: FormGroup;
    direcciones_adicionales: FormArray;

    onChange;
    icon_req = false;

    opcionesComboPais = [
        {pai_id: 1, pai_codigo: 'CO', pai_descripcion: 'COLOMBIA', pai_codigo_descripion: 'CO - COLOMBIA'},
    ];

    // Private
    private _unsubscribeAll: Subject<any>;

    /**
     *
     * @param _paisesServices
     * @param _departamentosServices
     * @param _municipiosServices
     */
    constructor(
        private _paisesServices       : PaisesService,
        private _departamentosServices: DepartamentosService,
        private _municipiosServices   : MunicipiosService,
        private _parametrosService    : ParametrosService,
        private formBuilder           : FormBuilder
    ) {
        super();
    }

    /**
     * Setea el NGSelect de paises
     */
    private setearControlPaises() {
        const vacioPaises: Pais[] = [];
        this.paises$ = concat(
            of(vacioPaises),
            this.paisesInput$.pipe(
                debounceTime(750),
                filter((query: string) =>  query && query.length > 0),
                distinctUntilChanged(),
                tap(() => this.loading(true)),
                switchMap(term => this._paisesServices.searchPaisesNgSelect(term).pipe(
                    catchError(() => of(vacioPaises)),
                    tap(() => this.loading(false))
                ))
            ));
    }

    /**
     * Setea el NGSelect de departamentos
     */
    private setearControlDepartamentos() {
        const vacioDepartamanetos: Departamento[] = [];
        this.departamentos$ = concat(
            of(vacioDepartamanetos),
            this.departamentosInput$.pipe(
                debounceTime(750),
                filter((query: string) =>  query && query.length > 0),
                distinctUntilChanged(),
                tap(() => this.loading(true)),
                switchMap(term => this._departamentosServices.searchDepartamentoNgSelect(term, this.pai_id.value).pipe(
                    catchError(() => of(vacioDepartamanetos)),
                    tap(() => this.loading(false))
                ))
            ));
    }

    /**
     * Setea el NGSelect de municipios
     */
    private setearControlMunicipios() {
        const vacioMunicipios: Municipio[] = [];
        this.municipios$ = concat(
            of(vacioMunicipios),
            this.municipiosInput$.pipe(
                debounceTime(750),
                filter((query: string) =>  query && query.length > 0),
                distinctUntilChanged(),
                tap(() => this.loading(true)),
                switchMap(term => this._municipiosServices.searchMunicipioNgSelect(term, this.pai_id.value, this.dep_id.value).pipe(
                    catchError(() => of(vacioMunicipios)),
                    tap(() => this.loading(false))
                ))
            ));
    }

    /**
     * Setea el NGSelect de ofes
     */
    private setearControlOfes() {
        const vacioOfes: CodigoPostal[] = [];
        this.codigos$ = concat(
            of(vacioOfes),
            this.codigosInput$.pipe(
                debounceTime(750),
                filter((query: string) =>  query && query.length > 0),
                distinctUntilChanged(),
                tap(() => this.loading(true)),
                switchMap(term => this._parametrosService.searchCodigosPostalesNgSelect(term).pipe(
                    catchError(() => of(vacioOfes)),
                    tap(() => this.loading(false))
                ))
            ));
    }

    ngOnInit(): void {
        this.setearControlPaises();
        this.setearControlDepartamentos();
        this.setearControlMunicipios();
        this.setearControlOfes();
        if (this.ver){
            this.pai_id.disable();
            this.mun_id.disable();
            this.dep_id.disable();
            this.codigo_postal.disable();
        }

        this.formDireccionesAdicionales = this.formBuilder.group({
            'direcciones_adicionales': this.formBuilder.array([])
        });
    }

    /**
      * Evento de cambio de país.
      *
      */
    cambiarPais(evt) {
        this.dep_id.setValue(null);
        this.mun_id.setValue(null);
        this.setearControlDepartamentos();
        this.setearControlMunicipios();

        if (this.validacionOnFly === true)
            this.activarValidadadores(evt && evt.pai_codigo ?  evt.pai_codigo : null);
        else {
            if (evt && evt.pai_codigo && (evt.pai_codigo === 'CO')) {
                this.icon_req = true;
            } else {
                this.dep_id.clearValidators();
                this.icon_req = false;
            }
            this.dep_id.updateValueAndValidity();
        }
    }

    /**
     * Evento de cambio de departamento.
     *
     * @param evt
     */
    cambiarDepartamento(evt) {
        this.mun_id.setValue(null);
    }

    /**
     * Detecta los cambios cuando se cambia el campo de direccion.
     *
     * @param evt
     */
    cambioDireccion(evt) {
        this.activarValidadadores(this.pai_id.value ? this.pai_id.value.pai_codigo : null);
        if(this.tipo === 'OFE' && this.direcciones_adicionales !== undefined && (evt.target.value === '' || evt.target.value === undefined ||evt.target.value === null)) {
            while (this.direcciones_adicionales.length !== 0) {
                this.direcciones_adicionales.removeAt(0)
            }
        }
    }

    /**
     * Permite controlar la activacion o de las validaciones para esta sección.
     *
     */
    cambiarCodigoPostal(evt) {
        this.activarValidadadores(this.pai_id.value ? this.pai_id.value.pai_codigo : null);
    }

    /**
     * Elimina los validadores del componente si este esta configurado como una sección opcional
     */
    eliminarValidadores() {
        if (this.validacionOnFly === true && this.tipo != 'OFE') {
            if (
                this.isEmpty(this.pai_id.value) &&
                this.isEmpty(this.dep_id.value) &&
                this.isEmpty(this.mun_id.value) &&
                (this.direccion.value === '' || this.direccion.value === null || this.direccion.value === undefined) &&
                this.isEmpty(this.codigo_postal.value)
            ) {
                this.pai_id.setValidators(null);
                this.dep_id.setValidators(null);
                this.mun_id.setValidators(null);
                this.direccion.setValidators(null);
                this.codigo_postal.setValidators(null);
                this.pai_id.updateValueAndValidity();
                this.dep_id.updateValueAndValidity();
                this.mun_id.updateValueAndValidity();
                this.direccion.updateValueAndValidity();
                this.codigo_postal.updateValueAndValidity();
            }
        }
    }

    private fixValue(control: AbstractControl) {
        if (this.isEmpty(control.value))
            control.setValue(null);
    }

    /**
     * Permite activar los validadores en caso que estos se encuentren inactivo
     *
     * @param pai_id
     */
    activarValidadadores (pai_id = null) {
        if (this.validacionOnFly === true) {
            if (
                !this.isEmpty(this.pai_id.value) ||
                !this.isEmpty(this.dep_id.value) ||
                !this.isEmpty(this.mun_id.value) ||
                (this.direccion.value !== '' && this.direccion.value !== null && this.direccion.value !== undefined)
            ) {
                this.pai_id.setValidators([Validators.required]);
                this.fixValue(this.pai_id);
                this.pai_id.updateValueAndValidity();
                if (pai_id === 'CO') {
                    this.dep_id.setValidators([Validators.required]);
                    this.fixValue(this.dep_id);
                    this.dep_id.updateValueAndValidity();
                    this.icon_req = true;
                } else {
                    this.dep_id.setValue(null);
                    this.dep_id.setValidators([]);
                    this.dep_id.updateValueAndValidity();
                    this.icon_req = false;
                }

                this.mun_id.setValidators([Validators.required]);
                this.fixValue(this.mun_id);
                this.mun_id.updateValueAndValidity();

                this.direccion.setValidators([Validators.required]);
                this.fixValue(this.direccion);
                this.direccion.updateValueAndValidity();
            } else {
                this.eliminarValidadores();
            }
        }
    }

    /**
     * Evento de limpiar el combo de paises.
     * 
     */
    clearPais() {
        this.clearFormControl(this.pai_id, this.mun_id, this.dep_id);
        this.selectedPaiId = null;
        this.selectedDepId = null;
        this.selectedMunId = null;
        this.selectPaises.items = [];
        this.selectDepartamentos.items = [];
        this.selectMunicipios.items = [];
        this.paisesInput$.next('');
        this.departamentosInput$.next('');
        this.municipiosInput$.next('');

        this.eliminarValidadores();
    }

    /**
     * Evento de limpiar el combo de departamentos.
     * 
     */
    clearDepartamento() {
        this.clearFormControl(this.mun_id, this.dep_id);
        this.selectedDepId = null;
        this.selectedMunId = null;
        this.selectDepartamentos.items = [];
        this.selectMunicipios.items = [];
        this.departamentosInput$.next('');
        this.municipiosInput$.next('');

        this.eliminarValidadores();
    }

    /**
     * Evento de limpiar el combo de municipios.
     * 
     */
    clearMunicipio() {
        this.clearFormControl(this.mun_id);
        this.selectedMunId = null;
        this.selectMunicipios.items = [];
        this.municipiosInput$.next('');

        this.eliminarValidadores();
    }

    /**
     * Evento de limpiar el combo de codigos postales.
     *
     */
    clearCodigoPostal() {
        this.eliminarValidadores();
    }

    registerOnChange( fn: any ): void {
        this.change = fn;
    }

    registerOnTouched(fn: any): void {
    }

    setDisabledState(isDisabled: boolean): void {
    }

    writeValue(obj: any): void {
    }

    change( $event ) {
        // Angular does not know that the value has changed
        // from our component, so we need to update her with the new value.
        this.onChange($event.target.textContent);
    }

    /**
     * Agrega un FormGroup de direcciones al formulario de direcciones adicionales.
     *
     * @param {string} [direccion='']
     * @returns {FormGroup}
     * @memberof UbicacionOpenComponent
     */
    agregarCamposDireccionAdicional(direccion?): FormGroup {
        return this.formBuilder.group({
            direccion: [direccion, Validators.compose([])]
        });
    }

    /**
     * Agrega los nuevos campos de dirección adicional.
     *
     * @memberof UbicacionOpenComponent
     */
    agregarDireccion(direccion?): void {
        if(this.direccion.value !== undefined && this.direccion.value !=='') {
            this.direcciones_adicionales = this.formDireccionesAdicionales.get('direcciones_adicionales') as FormArray;
            this.direcciones_adicionales.push(this.agregarCamposDireccionAdicional(direccion));
        } else {
            this.showError('<h4>Debe ingresar la dirección principal para poder agregar direcciones adicionales</h4>', 'error', 'Direcciones Adicionales', 'Ok', 'btn btn-danger');
        }
    }

    /**
     * ELimina una dirección de la grilla.
     * 
     * @param i
     * @memberof UbicacionOpenComponent
     */
    eliminarDireccion(i: number) {
        const CTRL = <FormArray>this.formDireccionesAdicionales.controls['direcciones_adicionales'];
        CTRL.removeAt(i);
    }

    /**
     * Crea los controles para ñas direcciones adicionales existentes.
     *
     * Generalmente este método es llamado desde el componente padre en donde se pasá el array del parámetro
     *
     * @param array arrDirecciones
     * @memberof UbicacionOpenComponent
     */
    direccionesExistentes(arrDirecciones) {
        if(arrDirecciones !== undefined && arrDirecciones.length >0) {
            arrDirecciones.forEach(direccion => {
                this.agregarDireccion(direccion);
            })
        }
    }
}
