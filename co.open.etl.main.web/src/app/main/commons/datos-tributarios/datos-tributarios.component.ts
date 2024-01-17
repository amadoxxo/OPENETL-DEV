import {Component, Input, OnInit, ViewChild, ChangeDetectionStrategy, ChangeDetectorRef} from '@angular/core';
import {BaseComponent} from '../../core/base_component';
import {AbstractControl, Validators} from '@angular/forms';
import {ConfiguracionService} from '../../../services/configuracion/configuracion.service';
import {NgSelectComponent} from '@ng-select/ng-select';
import {ResponsabilidadFiscal} from '../../models/responsabilidad-fiscal.model';
import {concat, Observable, of, Subject} from 'rxjs';
import {Router} from '@angular/router';
import {
    catchError,
    debounceTime,
    distinctUntilChanged,
    filter,
    switchMap,
    tap
} from 'rxjs/operators';

@Component({
    selector: 'app-datos-tributarios',
    changeDetection: ChangeDetectionStrategy.OnPush,
    templateUrl: './datos-tributarios.component.html',
    styleUrls: ['./datos-tributarios.component.scss']
})
export class DatosTributariosComponent extends BaseComponent implements OnInit {

    @Input() set tipoRegimen(value) {
        this.regimenFiscal = value;
    }
    
    //Setea el regimen fiscal seleccionado, en el objeto principal de las paramétricas
    @Input() set regimenFiscalSelect(value) {
        if (value !== '' && value !== null && value.rfi_codigo) {
            value.rfi_codigo_descripion = value.rfi_codigo + ' - ' + value.rfi_descripcion;
            let tipoRegimenSelect = value;
    
            let existe = false;
            this.regimenFiscal.forEach(element => {
                if (element.rfi_codigo === tipoRegimenSelect.rfi_codigo) {
                    existe = true;
                }
            });
        
            if (!existe) {
                this.regimenFiscal.push(tipoRegimenSelect);
            }
        }
        this.arrRegimenFiscal = this.regimenFiscal;
    }

    @Input() set tipoProcedenciaVendedor(value) {
        this.procedenciaVendedor = value;
    }

    //Setea la procedencia vendedor seleccionada, en el objeto principal de las paramétricas
    @Input() set procedenciaVendedorSelect(value) {
        if (value !== '' && value !== null && value.ipv_codigo) {
            value.ipv_codigo_descripcion = value.ipv_codigo + ' - ' + value.ipv_descripcion;
            let procedenciaVendedorSelect = value;

            let existe = false;
            this.procedenciaVendedor.forEach(element => {
                if (element.ipv_codigo === procedenciaVendedorSelect.ipv_codigo) {
                    existe = true;
                }
            });
        
            if (!existe) {
                this.procedenciaVendedor.push(procedenciaVendedorSelect);
            }
        }
        
        this.arrProcedenciaVendedor = this.procedenciaVendedor;
    }

    @Input() set responsabilidadesFiscales(values) {
        if (this.ver) {
            this._dataResponsabilidadesFiscales = values;
        } else {
            let data = values.filter(value => value.estado === 'ACTIVO');
            this._dataResponsabilidadesFiscales = data;
        }
        this.cd.markForCheck();
    }

    // Setea las responsabilidades fiscales seleccionadas, en el objeto principal de las paramétricas
    @Input() set responsabilidadesFiscalesSelect(values) {
        values.forEach(element => {
            let existe = false;
            this._dataResponsabilidadesFiscales.forEach(reg => {
                if (reg.ref_codigo === element.ref_codigo) {
                    existe = true;
                }
            });

            if (!existe) {
                this._dataResponsabilidadesFiscales.push(element);
            }
        });
        this.arrResponsabilidadFiscal = this._dataResponsabilidadesFiscales;
    }

    @Input() set tributos(values) {
        let data = values.filter(value => value.tri_aplica_persona === 'SI');
        this._dataTributos = data;
        this.cd.markForCheck();
    }

    @Input() set tributosSelect(datos) {
        let datosTributo: any;
        let arrSeleccionados = [];

        datos.forEach(element => {
            let existe = false;
            if (element.get_detalle_tributo !== undefined) {
                datosTributo = element.get_detalle_tributo;
                datosTributo.tri_codigo_descripcion = datosTributo.tri_codigo + ' - ' + datosTributo.tri_descripcion;
                datosTributo.disabled = true;
                
                this._dataTributos.forEach(reg => {
                    if (reg.tri_codigo == datosTributo.tri_codigo) {
                        datosTributo.tri_codigo_descripcion = reg.tri_codigo + ' - ' + reg.tri_descripcion
                        existe = true;
                    }
                });
    
                if (!existe) {
                    this._dataTributos.push(datosTributo);
                }
                arrSeleccionados.push(datosTributo.tri_codigo_descripcion);
            }
        });
        this.arrTributos = this._dataTributos;
        this.tributosSeleccionados = arrSeleccionados;
    }

    @Input() rfi_id: AbstractControl = null;
    @Input() ref_id: AbstractControl = null;
    @Input() ipv_id: AbstractControl = null;
    @Input() tributosControl: AbstractControl = null;
    @Input() ver: boolean;
    @Input() tipo: string;
    public _dataTributos: Array<any> = [];
    public _dataResponsabilidadesFiscales: Array<any> = [];
    public objTributo : any = {};
    public origenVendedor: boolean = false;
    @ViewChild('selectResponsabilidades', { static: true }) selectResponsabilidades: NgSelectComponent;

    ResFiscal$: Observable<ResponsabilidadFiscal[]>;
    ResFiscalInput$ = new Subject<string>();
    // ResFiscalLoading: boolean = false;
    selectedsResFiscalId: any;

    public tributosSeleccionados = [];
    public arrTributosSelect     = [];

    public regimenFiscal             : Array<any> = [];
    public procedenciaVendedor       : Array<any> = [];
    public arrRegimenFiscal          : Array<any> = [];
    public arrProcedenciaVendedor    : Array<any> = [];
    public arrResponsabilidadFiscal  : Array<any> = [];
    public arrTributos               : Array<any> = [];

    /**
     * Constructor
     */
    constructor(
        private _configuracionService: ConfiguracionService,
        private router: Router,
        private cd: ChangeDetectorRef) {
        super();
    }

    ngOnInit() {
        const vacioResFiscal: ResponsabilidadFiscal[] = [];
        this.ResFiscal$ = concat(
            of(vacioResFiscal),
            this.ResFiscalInput$.pipe(
                debounceTime(750),
                filter((query: string) =>  query && query.length > 0),
                distinctUntilChanged(),
                tap(() => this.loading(true)),
                switchMap(term => this._configuracionService.searchResFiscalNgSelect(term).pipe(
                    catchError(() => of(vacioResFiscal)),
                    tap(() => this.loading(false))
                ))
            )
        );

        if(this.ver){
            this.rfi_id.disable();
            this.ref_id.disable();
            if(this.router.url.indexOf('vendedores') !== -1) {
                this.ipv_id.disable();
            }
            if(this.tipo !== 'PROV') {
                this.tributosControl.disable();
            }
        }

        if(this.router.url.indexOf('vendedores') !== -1) {
            this.ipv_id.setValidators([Validators.required]);
            this.origenVendedor = true;
        }
    }

    /**
     * Evento de limpiar el combo de paises.
     * 
     */
    // clearResFiscal() {
    //     this.clearFormControl(this.ref_id);
    //     this.selectedsResFiscalId = null;
    //     this.selectResponsabilidades.items = [];
    //     this.ResFiscalInput$.next('');
    // }

    /**
     * Obtiene el código de los registros seleccionados en el select de tributos y los setea al AbstractControl tributosControl.
     *
     * @param {*} event Información seleccionada
     * @memberof DatosTributariosComponent
     */
    obtenerRegistro(event) {
        this.arrTributosSelect = [];
        
        event.forEach(element => {
            let codigoTributo = [];
            if (element.tri_codigo === undefined) {
                codigoTributo = element.split(' - ');
                this.arrTributosSelect.push(codigoTributo[0]);
            } else {
                this.arrTributosSelect.push(element.tri_codigo);
            }
        });
        this.tributosControl.setValue(this.arrTributosSelect);
    }

    customSearchFnResponsabilidadesFiscales(term: string, item) {
        term = term.toLocaleLowerCase();
        return item.ref_codigo.toLocaleLowerCase().indexOf(term) > -1 || item.ref_descripcion.toLocaleLowerCase().indexOf(term) > -1;
    }

    customSearchFnTributo(term: string, item) {
        term = term.toLocaleLowerCase();
        return item.tri_codigo.toLocaleLowerCase().indexOf(term) > -1 || item.tri_descripcion.toLocaleLowerCase().indexOf(term) > -1;
    }

    customSearchFnRegimen(term: string, item) {
        term = term.toLocaleLowerCase();
        return item.rfi_codigo.toLocaleLowerCase().indexOf(term) > -1 || item.rfi_descripcion.toLocaleLowerCase().indexOf(term) > -1;
    }

    customSearchFnProcedenciaVendedor(term: string, item) {
        term = term.toLocaleLowerCase();
        return item.ipv_codigo.toLocaleLowerCase().indexOf(term) > -1 || item.ipv_descripcion.toLocaleLowerCase().indexOf(term) > -1;
    }
}
