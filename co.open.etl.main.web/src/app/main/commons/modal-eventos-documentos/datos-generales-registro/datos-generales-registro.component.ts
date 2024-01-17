import swal from 'sweetalert2';
import {Component, Input, OnDestroy, OnInit, ChangeDetectorRef, EventEmitter, Output} from '@angular/core';
import {AbstractControl, Validators} from '@angular/forms';
import {Router} from '@angular/router';
import {BaseComponent} from '../../core/base_component';
import {CommonsService} from '../../../services/commons/commons.service';
import {ConfiguracionService} from '../../../services/configuracion/configuracion.service';
import {Subject} from 'rxjs';
import {AdquirentesGestionarComponent} from 'app/main/configuracion/adquirentes/adquirentes-base/gestionar/adquirentes-gestionar.component';
import {IHash} from "../../models/hash";

@Component({
    selector: 'app-datos-generales-registro',
    templateUrl: './datos-generales-registro.component.html',
    styleUrls: ['./datos-generales-registro.component.scss']
})
export class DatosGeneralesRegistroComponent extends BaseComponent implements OnInit, OnDestroy {
    @Output() tdoSeleccionado = new EventEmitter();

    @Input() tdo_id              : AbstractControl = null;
    @Input() toj_id              : AbstractControl = null;
    @Input() identificacion      : AbstractControl = null;
    @Input() adq_id_personalizado: AbstractControl = null;
    @Input() pro_id_personalizado: AbstractControl = null;
    @Input() ofe_identificacion  : string = null;
    @Input() DV                  : AbstractControl = null;
    @Input() razon_social        : AbstractControl = null;
    @Input() nombre_comercial    : AbstractControl = null;
    @Input() primer_apellido     : AbstractControl = null;
    @Input() segundo_apellido    : AbstractControl = null;
    @Input() primer_nombre       : AbstractControl = null;
    @Input() otros_nombres       : AbstractControl = null;
    @Input() tiposPersonaCodigos: Array<any> = [];
    @Input() sololectura        : boolean;
    @Input() ver                : boolean;
    @Input() editar             : boolean;
    @Input() parent             : AdquirentesGestionarComponent;
    @Input() tipoAdquirente     : string;
    @Input() tipo               : string;
    
    @Input() maxlengthIdentificacion: number = 20;
    @Input() regexIdentificacion: RegExp = /^[0-9a-zA-Z-]{1,20}$/;

    @Input() set initOrganizacion(value) {
        if (value !== null) {
            if (!this.inicializadoToj)
                this.switchControles(value);
            this.inicializadoToj = true;
        }
    }

    @Input() set initDV(value) {
        if (value !== null) {
            if (!this.inicializadoDV)
                this.mostrarDV = value;
            this.inicializadoDV = true;
        }
    }

    private hashToj: IHash = {};
    private inicializadoToj:boolean = false;
    private inicializadoDV:boolean = false;

    public mostrarRazonSocial = null;

    @Input() set tiposOrganizacion(value) {
        this.tiposOrg = value;
        this.tiposOrg.forEach(el => {
            if (el.toj_codigo === this.codigoJuridica) {
                this.idJuridica1 = el.toj_id;
            }
            if (el.toj_codigo === this.codigoPersona) {
                this.idPersona2 = el.toj_id;
            }
            // Hashmap para configurar el control de razon social o datos personales
            this.hashToj[String(el.toj_id)] = el.toj_codigo;
        });
        this.cd.markForCheck();
    }

    @Input() set tiposDocumento(value) {
        this.tipoDoc = value;
    }

    @Input() set tipoDocumentoSelect(value) {
        if (value !== '' && value !== null && value.tdo_codigo) {
            value.tdo_codigo_descripion = value.tdo_codigo + ' - ' + value.tdo_descripcion;
            let tiposDocumentosSelect = value;

            let existe = false;
            this.tipoDoc.forEach(element => {
                if (element.tdo_codigo === tiposDocumentosSelect.tdo_codigo) {
                    existe = true;
                }
            });

            if (!existe) {
                this.tipoDoc.push(tiposDocumentosSelect);
            }
        }
        this.arrTiposDocumentos = this.tipoDoc;
    }

    @Input() set tipoOrganizacionSelect(value) {
        if (value !== '' && value !== null && value.toj_codigo) {
            value.toj_codigo_descripion = value.toj_codigo + ' - ' + value.toj_descripcion;
            let tiposOrganizacionesSelect = value;

            let existe = false;
            this.tiposOrg.forEach(element => {
                if (element.toj_codigo === tiposOrganizacionesSelect.toj_codigo) {
                    existe = true;
                }
            });

            if (!existe) {
                this.tiposOrg.push(tiposOrganizacionesSelect);
            }
        }
        this.arrTiposOrganizaciones = this.tiposOrg;
    }

    public tipoDoc                : Array<any> = [];
    public arrTiposDocumentos     : Array<any> = [];
    public tiposOrg               : Array<any> = [];
    public arrTiposOrganizaciones : Array<any> = [];
    public tipoDocumentoId        : any;
    public tipoOrgId              : any;
    public hidden                 = false;
    public codigoDocumentoNIT     = '31';
    public codigoJuridica         = '1';
    public codigoPersona          = '2';
    public codigoFirmaPersonal    = '03';
    public idJuridica1            : string;
    public idPersona2             : string;
    public idNIT                  : string;
    public mostrarDV              = false;
    public mostrarRazon           = false;
    public mostrarApellidos       = false;
    public ultimoComprobado       : string;
    public ultimoOfeComprobado    : string;

    public formErrors: any;

    // Private
    private _unsubscribeAll: Subject<any> = new Subject();

    /**
     * Constructor
     * @param _commonsService
     * @param _configuracionService
     * @param _router
     * @param cd
     */
    constructor(
            private _commonsService: CommonsService,
            private _configuracionService: ConfiguracionService,
            private _router: Router,
            private cd: ChangeDetectorRef
        ) {
        super(); 
        this.buildErrorsObject();
    }

    /**
     * Construye un objeto para gestionar los errores en el formulario.
     * 
     */
    public buildErrorsObject() {
        this.formErrors = {
            identificacion: {
                required: 'La Identificación es requerida!',
                maxLength: 'Ha introducido más de ' + this.maxlengthIdentificacion + ' caracteres'
            },
            tdo_id: {
                required: 'El Tipo de Documento es requerido!',
            },
            toj_id: {
                required: 'El Tipo de Organización Jurídica es requerida!',
            },
            razon_social: {
                required: 'La Razón Social es requerida!',
            },
            nombre_comercial: {
                required: 'El Nombre Comercial es requerido!',
            },
            primer_apellido: {
                required: 'El Primer Apellido es requerido!',
            },
            primer_nombre: {
                required: 'El Primer Nombre es requerido!',
            }
        };   
    }

    ngOnInit() {
        if (this.DV && this.DV.value !== null) {
            this.mostrarDV = true;
        }
        if (this.ver){
            this.tdo_id.disable();
            this.toj_id.disable();
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
     * Activa o Inactiva el campo del dígito de verificación.
     * 
     */
    cambiarTdo(evt) {
        if (evt && evt.tdo_codigo && (evt.tdo_codigo === this.codigoDocumentoNIT)){
            this.mostrarDV = true;
            this.calcularDV();
            this.maxlengthIdentificacion = 20;
            this.regexIdentificacion = new RegExp("^[0-9]{1,20}$");
        } else {
            this.mostrarDV = false; 
            this.DV.setValue(null);
            this.maxlengthIdentificacion = 20;
            this.regexIdentificacion = new RegExp("^[0-9a-zA-Z-]{1,20}$");
        }

        if(evt && evt.tdo_codigo)
            this.tdoSeleccionado.emit(evt.tdo_codigo);
    }

    /**
     * Activa o Inactiva los campos de datos generales.
     * 
     */
    cambiarToj(evt) {
        if (evt && evt.toj_codigo )
            this.switchControles(evt.toj_codigo);
    }

    /**
     * Alterna los formularios de datos generales entre personas y organizaciones
     *
     * @param toj_codigo
     */
    switchControles(toj_codigo) {
        if (toj_codigo === this.codigoPersona || toj_codigo === this.codigoFirmaPersonal) {
            this.hidden = true;
            this.mostrarApellidos = true;
            this.razon_social.clearValidators();
            this.razon_social.setValue('');
            this.nombre_comercial.clearValidators();
            this.nombre_comercial.setValue('');
            this.primer_apellido.setValidators([Validators.required, Validators.maxLength(100)]);
            this.segundo_apellido.setValidators([Validators.maxLength(100)]);
            this.primer_nombre.setValidators([Validators.required, Validators.maxLength(100)]);
            this.otros_nombres.setValidators([Validators.maxLength(100)]);
            this.mostrarRazon = false;
            this.mostrarRazonSocial = 'NO';
        } else {
            this.hidden = false;
            this.mostrarRazon = true;
            this.razon_social.setValidators([Validators.required, Validators.maxLength(255)]);
            this.nombre_comercial.setValidators([Validators.required, Validators.maxLength(255)]);
            this.primer_apellido.clearValidators();
            this.primer_apellido.setValue('');
            this.segundo_apellido.clearValidators();
            this.segundo_apellido.setValue('');
            this.primer_nombre.clearValidators();
            this.primer_nombre.setValue('');
            this.otros_nombres.clearValidators();
            this.otros_nombres.setValue('');
            this.mostrarApellidos = false;
            this.mostrarRazonSocial = 'SI';
        }

        this.razon_social.updateValueAndValidity();
        this.nombre_comercial.updateValueAndValidity();
        this.primer_apellido.updateValueAndValidity();
        this.segundo_apellido.updateValueAndValidity();
        this.primer_nombre.updateValueAndValidity();
        this.otros_nombres.updateValueAndValidity();
    }

    /**
     * Calcula el Dígito de Verificación para los NITs
     * 
     */
    calcularDV() {
        if (this.mostrarDV && !this.ver) {
            if (this.identificacion.value.trim() !== '') {
                this.loading(true);
                this._commonsService.calcularDV(this.identificacion.value).subscribe(
                result => {
                    if (result.data || result.data === 0) {
                        this.DV.setValue(result.data);
                    }
                    if(this.tipo === 'ADQ')
                        this.parent.checkIfAdqExists();
                    else{
                        this.loading(false);
                    }    
                }, error => {
                    const texto_errores = this.parseError(error);
                    this.loading(false);
                    this.showError(texto_errores, 'error', 'Error al calcular el DV', 'Ok', 'btn btn-danger');
                }
                );
            } else {
                this.DV.setValue(null);
            }
        } else {
            if(this.tipo === 'ADQ')
                this.parent.checkIfAdqExists();
        }
    }

    /**
     * TODO:
     * @param term
     * @param item
     */
    customSearchFnTdo(term: string, item) {
        term = term.toLocaleLowerCase();
        return item.tdo_codigo.toLocaleLowerCase().indexOf(term) > -1 || item.tdo_descripcion.toLocaleLowerCase().indexOf(term) > -1;
    }

    /**
     * TODO:
     * @param term
     * @param item
     */
    customSearchFnToj(term: string, item) {
        term = term.toLocaleLowerCase();
        return item.toj_codigo.toLocaleLowerCase().indexOf(term) > -1 || item.toj_descripcion.toLocaleLowerCase().indexOf(term) > -1;
    }

    /**
     * Permite mostrar o no la seccion de datos para una persona persona o firma personal
     */
    mostrarInformacionPersonal() {
        if (this.toj_id && this.tiposOrg)
            return this.toj_id.value === this.codigoPersona || this.toj_id.value === this.codigoFirmaPersonal;
        return false;
    }

    /**
     * Permite mostrar o no la seccion de datos para entes juridicos o empresas
     */
    mostrarInformacionJuridica() {
        if (this.toj_id && this.tiposOrg) 
            return this.toj_id.value !== this.codigoPersona && this.toj_id.value !== this.codigoFirmaPersonal;
        return false;
    }
}
