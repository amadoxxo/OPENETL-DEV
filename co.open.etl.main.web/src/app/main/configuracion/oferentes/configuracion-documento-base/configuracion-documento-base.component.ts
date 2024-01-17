import {Subject} from 'rxjs';
import {MatAccordion} from '@angular/material/expansion';
import {MatDialog, MatDialogConfig} from '@angular/material/dialog';
import {ActivatedRoute, Router} from '@angular/router';
import {DomSanitizer} from '@angular/platform-browser';
import {Auth} from '../../../../services/auth/auth.service';
import { BaseComponentList } from 'app/main/core/base_component_list';
import {Component, OnInit, ViewChild, AfterViewInit, OnDestroy, Input} from '@angular/core';
import {OferentesService} from '../../../../services/configuracion/oferentes.service';
import {AbstractControl, FormArray, FormBuilder, FormGroup, Validators} from '@angular/forms';
import {ConfiguracionService} from '../../../../services/configuracion/configuracion.service';
import {ValoresPorDefectoDocumentoElectronicoGestionarComponent} from '../valores-por-defecto-documento-electronico-gestionar/valores-por-defecto-documento-electronico-gestionar.component';

@Component({
    selector: 'app-configuracion-documento-base',
    templateUrl: './configuracion-documento-base.component.html',
    styleUrls: ['./configuracion-documento-base.component.scss']
})
export class ConfiguracionDocumentoBaseComponent extends BaseComponentList implements OnInit, OnDestroy, AfterViewInit {
    @ViewChild('acordion', {static: false}) acordion: MatAccordion;
    @Input() tipoConfiguracion: string;

    public formulario                      : FormGroup;
    public representacion_grafica_estandar : AbstractControl;
    public aplica_sector_salud             : AbstractControl;
    public archivoImagenRepGrafica         : AbstractControl;
    public encabezado                      : AbstractControl;
    public piePagina                       : AbstractControl;
    public cargosCabeceraPersonalizados    : AbstractControl;
    public cargosItemsPersonalizados       : AbstractControl;
    public descuentosCabeceraPersonalizados: AbstractControl;
    public descuentosItemsPersonalizados   : AbstractControl;
    public imagePath;
    public logo                            : any;
    public aclsUsuario                     : any;
    public mostrarFormulario               : boolean = false;
    public validators                      = [Validators.pattern("^[a-zA-Z0-9_ %]*$")];
    public validatorsTags                  = [Validators.pattern("^[a-zA-ZñÑáéíóúÁÉÍÓÚ.,-\s]+$")];
    public validatorsPersonalizados        = [Validators.pattern("^[a-zA-Z0-9_ ]*$")];

    private _unsubscribeAll: Subject<any> = new Subject();

    public selectedCampos                          : any[] = [];
    public selectedCargosCabeceraPersonalizados    : any[] = [];
    public selectedCargosItemsPersonalizados       : any[] = [];
    public selectedDescuentosCabeceraPersonalizados: any[] = [];
    public selectedDescuentosItemsPersonalizados   : any[] = [];

    public valores_resumen: any = [
        // Cabecera
        {id: 'anticipos',                     label: 'ANTICIPOS',                               nivel: 'cabecera',  checked: false},
        {id: 'cargos-a-nivel-documento',      label: 'CARGOS',                                  nivel: 'cabecera',  checked: false},
        {id: 'descuentos-a-nivel-documento',  label: 'DESCUENTOS',                              nivel: 'cabecera',  checked: false},
        {id: 'reteica-a-nivel-documento',     label: 'RETENCIÓN SUGERIDA RETEICA',              nivel: 'cabecera',  checked: false},
        {id: 'reteiva-a-nivel-documento',     label: 'RETENCIÓN SUGERIDA RETEIVA',              nivel: 'cabecera',  checked: false},
        {id: 'retefuente-a-nivel-documento',  label: 'RETENCIÓN SUGERIDA RETEFUENTE',           nivel: 'cabecera',  checked: false},
        {id: 'moneda-extranjera',             label: 'MONEDA EXTRANJERA',                       nivel: 'cabecera',  checked: false},
        {id: 'enviar-dian-moneda-extranjera', label: 'ENVIAR A LA DIAN EN MONEDA EXTRANJERA',   nivel: 'cabecera',  checked: false},
        // Items
        {id: 'cargos-a-nivel-item',           label: 'CARGOS',                                  nivel: 'item',      checked: false},
        {id: 'descuentos-a-nivel-item',       label: 'DESCUENTOS',                              nivel: 'item',      checked: false},
        {id: 'reteica-a-nivel-item',          label: 'RETENCIÓN SUGERIDA RETEICA',              nivel: 'item',      checked: false},
        {id: 'reteiva-a-nivel-item',          label: 'RETENCIÓN SUGERIDA RETEIVA',              nivel: 'item',      checked: false},
        {id: 'retefuente-a-nivel-item',       label: 'RETENCIÓN SUGERIDA RETEFUENTE',           nivel: 'item',      checked: false}
    ];
    repGrafica                : FormGroup;
    campoPersonalizadoCabecera: FormGroup;
    campoPersonalizadoItem    : FormGroup;

    public titulo             : string;
    public ofe_id             : any = null;
    public _ofe_identificacion: any;
    public _razon_social      : any;
    public imgURL             : any;
    public tipoFormulario     : any;
    public placeholderLongitud: string = '';
    public flexLongitud       : number;

    private modalValorPorDefecto : any;

    constructor(
        public _auth: Auth,
        private _router: Router,
        private _route: ActivatedRoute,
        private _formBuilder: FormBuilder,
        private sanitizer: DomSanitizer,
        private _configuracionService: ConfiguracionService,
        private _oferentesService: OferentesService,
        private modal: MatDialog,
    ) {
        super();
        this._configuracionService.setSlug = 'ofe';
    }

    ngOnInit() {
        this._ofe_identificacion = this._route.snapshot.params['ofe_identificacion'];
        this.aclsUsuario = this._auth.getAcls();
        this.titulo = (this.tipoConfiguracion === 'DE') ? 'Configuración Documento Electrónico' : 'Configuración Documento Soporte';
        if(!this._auth.existeRol(this.aclsUsuario.roles, 'superadmin') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfigurarDocumentoElectronico) && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfigurarDocumentoSoporte)) {
            this.showError('<h4>No tiene los permisos necesarios para esta acción</h4>', 'error', this.titulo, 'Ok', 'btn btn-danger', 'configuracion/oferentes', this._router);
        } else {
            this.mostrarFormulario = true;
            this.buildFormulario();
            this.loadOfe();
        }
        if(this.tipoConfiguracion === 'DS') {
            this.valores_resumen = this.valores_resumen.filter(function(item) {
                return item.id !== 'anticipos' && item.id !== 'reteica-a-nivel-documento' && item.id !== 'reteica-a-nivel-item';
            });
        }
    }

    /**
     * Vista construida
     */
    ngAfterViewInit() {
        if(this.mostrarFormulario) this.acordion.openAll();
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
     * Permite regresar a la lista de oferentes.
     *
     */
    regresar() {
        this._router.navigate(['configuracion/oferentes']);
    }

    /**
     * Se encarga de cargar los datos de un ofe que se ha seleccionado en el tracking.
     *
     */
    public loadOfe(): void {
        this.loading(true);
        this._configuracionService.get(this._ofe_identificacion).subscribe(
            res => {
                this.loading(false);
                this.ofe_id = res.data.ofe_id;
                if(res.data.ofe_razon_social != '') {
                    this._razon_social = res.data.ofe_razon_social;
                } else {
                    this._razon_social = res.data.ofe_primer_nombre + ' ' + res.data.ofe_otros_nombres + ' ' + res.data.ofe_primer_apellido + ' ' + res.data.ofe_segundo_apellido;
                }

                if(this.tipoConfiguracion === 'DS')
                    this.setDataDocumentoSoporte(res.data);
                else
                    this.setDataDocumentoElectronico(res.data);
            },
            error => {
                this.loading(false);
                const texto_errores = this.parseError(error);
                this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al cargar la información de configuración de documento electrónico', 'Ok', 'btn btn-danger', 'configuracion/oferentes', this._router);
            }
        );
    }

    /**
     * Construccion del formulario principal.
     *
     */
    buildFormulario() {
        this.formulario = this._formBuilder.group({
            repGrafica: this.buildFormularioConfiguracionDocumentoElectronico()
        });
    }

    /**
     * Construcción del formgroup de configuración de documento electrónico.
     *
     */
    buildFormularioConfiguracionDocumentoElectronico() {
        this.repGrafica = this._formBuilder.group({
            representacion_grafica_estandar : [''],
            aplica_sector_salud             : [''],
            archivoImagenRepGrafica         : [''],
            encabezado                      : [''],
            piePagina                       : [''],
            cargosCabeceraPersonalizados    : [''],
            cargosItemsPersonalizados       : [''],
            descuentosCabeceraPersonalizados: [''],
            descuentosItemsPersonalizados   : [''],
            camposPersonalizadosCabecera    : this._formBuilder.array([]),
            camposPersonalizadosItem        : this._formBuilder.array([]),
        });

        this.representacion_grafica_estandar  = this.repGrafica.controls['representacion_grafica_estandar'];
        this.aplica_sector_salud              = this.repGrafica.controls['aplica_sector_salud'];
        this.archivoImagenRepGrafica          = this.repGrafica.controls['archivoImagenRepGrafica'];
        this.encabezado                       = this.repGrafica.controls['encabezado'];
        this.piePagina                        = this.repGrafica.controls['piePagina'];
        this.cargosCabeceraPersonalizados     = this.repGrafica.controls['cargosCabeceraPersonalizados'];
        this.cargosItemsPersonalizados        = this.repGrafica.controls['cargosItemsPersonalizados'];
        this.descuentosCabeceraPersonalizados = this.repGrafica.controls['descuentosCabeceraPersonalizados'];
        this.descuentosItemsPersonalizados    = this.repGrafica.controls['descuentosItemsPersonalizados'];
        this.agregarCampoPersonalizadoCabecera();
        this.agregarCampoPersonalizadoItem();

        return this.repGrafica;
    }

    /**
     * Agrega nuevos campos personalizados de cabecera al formulario.
     *
     * @memberof ConfiguracionDocumentoBaseComponent
     */
    agregarCampoPersonalizadoCabecera(): void {
        const CTRL = this.repGrafica.get('camposPersonalizadosCabecera') as FormArray;
        CTRL.push(
            this._formBuilder.group({
                campo            : ['', Validators.pattern(new RegExp(/^[a-zA-Z0-9_ %]*$/))],
                tipo_dato        : [''],
                longitud         : [''],
                longitud_decimal : [''],
                opciones         : [''],
                valor_defecto    : [''],
                exacta           : [''],
                obligatorio      : ['']
            })
        );
    }

    /**
     * Agrega nuevos campos personalizados de ítem al formulario.
     *
     * @memberof ConfiguracionDocumentoBaseComponent
     */
    agregarCampoPersonalizadoItem(): void {
        const CTRL = this.repGrafica.get('camposPersonalizadosItem') as FormArray;
        CTRL.push(
            this._formBuilder.group({
                campo            : ['', Validators.pattern(new RegExp(/^[a-zA-Z0-9_ %]*$/))],
                tipo_dato        : [''],
                longitud         : [''],
                longitud_decimal : [''],
                opciones         : [''],
                valor_defecto    : [''],
                exacta           : [''],
                obligatorio      : ['']
            })
        );
    }

    /**
     * Elimina una grilla de campos personalizados de cabecera.
     *
     * @param {number} indice Posición del FormArray
     * @memberof ConfiguracionDocumentoBaseComponent
     */
    eliminarCampoPersonalizadoCabecera(indice: number) {
        const CTRL = <FormArray>this.repGrafica.controls['camposPersonalizadosCabecera'];
        this.eliminarValidacionesCamposPersonalizados(indice, 'camposPersonalizadosCabecera');

        if(CTRL.length > 1)
            CTRL.removeAt(indice);
        else {
            const camposCabecera:any = this.repGrafica.get('camposPersonalizadosCabecera') as FormArray;
            camposCabecera.controls[indice].controls['campo'].setValue('');
            camposCabecera.controls[indice].controls['tipo_dato'].setValue('');
            camposCabecera.controls[indice].controls['longitud'].setValue('');
            camposCabecera.controls[indice].controls['longitud_decimal'].setValue('');
            camposCabecera.controls[indice].controls['opciones'].setValue('');
            camposCabecera.controls[indice].controls['valor_defecto'].setValue('');
            camposCabecera.controls[indice].controls['exacta'].setValue('');
            camposCabecera.controls[indice].controls['obligatorio'].setValue('');
        }
    }

    /**
     * Elimina una grilla de campos personalizados de ítem.
     *
     * @param {number} indice Posición del FormArray
     * @memberof ConfiguracionDocumentoBaseComponent
     */
    eliminarCampoPersonalizadoItem(indice: number) {
        const CTRL = <FormArray>this.repGrafica.controls['camposPersonalizadosItem'];
        this.eliminarValidacionesCamposPersonalizados(indice, 'camposPersonalizadosItem');

        if(CTRL.length > 1)
            CTRL.removeAt(indice);
        else {
            const camposItem:any = this.repGrafica.get('camposPersonalizadosItem') as FormArray;
            camposItem.controls[indice].controls['campo'].setValue('');
            camposItem.controls[indice].controls['tipo_dato'].setValue('');
            camposItem.controls[indice].controls['longitud'].setValue('');
            camposItem.controls[indice].controls['longitud_decimal'].setValue('');
            camposItem.controls[indice].controls['opciones'].setValue('');
            camposItem.controls[indice].controls['valor_defecto'].setValue('');
            camposItem.controls[indice].controls['exacta'].setValue('');
            camposItem.controls[indice].controls['obligatorio'].setValue('');
        }
    }

    /**
     * Permite identificar si cambia el Tipo de Dato de una grilla de campos personalizados cabecera.
     *
     * @param {string} value Opción seleccionada
     * @param {number} indice Posición del FormArray
     * @param {string} formArray Indica el origen del formulario
     * @memberof ConfiguracionDocumentoBaseComponent
     */
    changeTipoDato(value: string, indice: number, formArray: string) {
        if (formArray == 'camposPersonalizadosCabecera') {
            this.tipoFormulario = this.repGrafica.get('camposPersonalizadosCabecera') as FormArray;
        } else {
            this.tipoFormulario = this.repGrafica.get('camposPersonalizadosItem') as FormArray;
        }
        this.placeholderLongitud = 'Longitud';
        this.flexLongitud = 20;

        if (value != undefined && value != null) {
            this.eliminarValidacionesCamposPersonalizados(indice, formArray);
            this.tipoFormulario.controls[indice].controls['longitud'].setValue('');
            this.tipoFormulario.controls[indice].controls['longitud_decimal'].setValue('');
            this.tipoFormulario.controls[indice].controls['opciones'].setValue('');
            this.tipoFormulario.controls[indice].controls['valor_defecto'].setValue('');
            this.tipoFormulario.controls[indice].controls['exacta'].setValue('');
            this.tipoFormulario.controls[indice].controls['obligatorio'].setValue('');
            this.tipoFormulario.controls[indice].controls['campo'].setValidators([Validators.required, Validators.pattern(new RegExp(/^[a-zA-Z0-9_ %]*$/))]);
            this.tipoFormulario.controls[indice].controls['campo'].updateValueAndValidity();

            switch(value) {
                case "texto":
                    this.flexLongitud = 41;
                    this.tipoFormulario.controls[indice].controls['longitud'].setValidators([Validators.pattern(new RegExp(/^\d+$/))]);
                break;
                case "numerico":
                    this.placeholderLongitud = 'Longitud Entera';
                    this.tipoFormulario.controls[indice].controls['longitud'].setValidators([Validators.required, Validators.pattern(new RegExp(/^\d+$/))]);
                    this.tipoFormulario.controls[indice].controls['longitud_decimal'].setValidators([Validators.pattern(new RegExp(/^\d+$/))]);
                break;
                case "multiple":
                    this.tipoFormulario.controls[indice].controls['opciones'].setValidators([Validators.required]);
                break;
                case "por_defecto":
                    this.tipoFormulario.controls[indice].controls['valor_defecto'].setValidators([Validators.required]);
                    this.tipoFormulario.controls[indice].controls['longitud'].setValidators([Validators.pattern(new RegExp(/^\d+$/))]);
                break;
            }
        }
    }

    /**
     * Elimina las validaciones de los campos personalizados a nivel de cabecera e ítem.
     *
     * @param {number} indice Posición del FormArray
     * @param {string} formArray Indica el origen del formulario
     * @memberof ConfiguracionDocumentoBaseComponent
     */
    eliminarValidacionesCamposPersonalizados(indice: number, formArray: string) {
        if (formArray == 'camposPersonalizadosCabecera') {
            this.tipoFormulario = this.repGrafica.get('camposPersonalizadosCabecera') as FormArray;
        } else {
            this.tipoFormulario = this.repGrafica.get('camposPersonalizadosItem') as FormArray;
        }

        this.tipoFormulario.controls[indice].controls['campo'].clearValidators();
        this.tipoFormulario.controls[indice].controls['longitud'].clearValidators();
        this.tipoFormulario.controls[indice].controls['longitud_decimal'].clearValidators();
        this.tipoFormulario.controls[indice].controls['opciones'].clearValidators();
        this.tipoFormulario.controls[indice].controls['valor_defecto'].clearValidators();
    }

    /**
     * Actualiza el contenido del array selectedCampos con base en los elementos seleccionados del array valores_resumen
     *
     * @param {object} event
     * @param {string} id
     * @memberof ConfiguracionDocumentoBaseComponent
     */
    actualizaSelectedCampos(event, id) {
        if(event.checked) {
            this.selectedCampos.push(id);
        } else {
            let index = this.selectedCampos.indexOf(id, 0);
            this.selectedCampos.splice(index, 1);
        }
    }

    /**
     * Crea un json para enviar los campos del formulario.
     *
     */
    getPayload() {
        if(this.selectedCampos.length > 0 && this.selectedCampos.indexOf('total-a-pagar') === -1)
            this.selectedCampos.push('total-a-pagar');

        let arrCamposPersonalizadosCabecera = [];
        let controlCamposCabecera = this.repGrafica.get('camposPersonalizadosCabecera')['controls'];
        if(controlCamposCabecera != undefined && controlCamposCabecera.length > 0) {
            controlCamposCabecera.forEach( control => {
                if (control.value.tipo_dato != '' && control.value.tipo_dato != null) {
                    const objCampoCabecera = new Object();
                    switch(control.value.tipo_dato) {
                        case "texto":
                            objCampoCabecera['campo']       = control.value.campo;
                            objCampoCabecera['tipo']        = control.value.tipo_dato;
                            objCampoCabecera['longitud']    = control.value.longitud;
                            objCampoCabecera['exacta']      = (control.value.exacta == true) ? "SI" : "NO";
                            objCampoCabecera['obligatorio'] = (control.value.obligatorio == true) ? "SI" : "NO";
                        break;
                        case "numerico":
                            const decimales = (control.value.longitud_decimal != '' && control.value.longitud_decimal != null) ? control.value.longitud_decimal : 0;
                            objCampoCabecera['campo']       = control.value.campo;
                            objCampoCabecera['tipo']        = control.value.tipo_dato;
                            objCampoCabecera['longitud']    = control.value.longitud + '.' + decimales;
                            objCampoCabecera['exacta']      = (control.value.exacta == true) ? "SI" : "NO";
                            objCampoCabecera['obligatorio'] = (control.value.obligatorio == true) ? "SI" : "NO";
                        break;
                        case "multiple":
                            objCampoCabecera['campo']       = control.value.campo;
                            objCampoCabecera['tipo']        = control.value.tipo_dato;
                            objCampoCabecera['opciones']    = control.value.opciones;
                            objCampoCabecera['obligatorio'] = (control.value.obligatorio == true) ? "SI" : "NO";
                        break;
                        case "por_defecto":
                            objCampoCabecera['campo']       = control.value.campo;
                            objCampoCabecera['tipo']        = control.value.tipo_dato;
                            objCampoCabecera['opciones']    = control.value.valor_defecto;
                            objCampoCabecera['longitud']    = control.value.longitud;
                            objCampoCabecera['obligatorio'] = (control.value.obligatorio == true) ? "SI" : "NO";
                        break;
                    }
                    arrCamposPersonalizadosCabecera.push(objCampoCabecera);
                }
            });
        }

        let arrCamposPersonalizadosItem = [];
        let controlCamposItem = this.repGrafica.get('camposPersonalizadosItem')['controls'];
        if(controlCamposItem != undefined && controlCamposItem.length > 0) {
            controlCamposItem.forEach( control => {
                if (control.value.tipo_dato != '' && control.value.tipo_dato != null) {
                    const objCampoItem = new Object();
                    switch(control.value.tipo_dato) {
                        case "texto":
                            objCampoItem['campo']       = control.value.campo;
                            objCampoItem['tipo']        = control.value.tipo_dato;
                            objCampoItem['longitud']    = control.value.longitud;
                            objCampoItem['exacta']      = (control.value.exacta == true) ? "SI" : "NO";
                            objCampoItem['obligatorio'] = (control.value.obligatorio == true) ? "SI" : "NO";
                        break;
                        case "numerico":
                            const decimales = (control.value.longitud_decimal != '' && control.value.longitud_decimal != null) ? control.value.longitud_decimal : 0;
                            objCampoItem['campo']       = control.value.campo;
                            objCampoItem['tipo']        = control.value.tipo_dato;
                            objCampoItem['longitud']    = control.value.longitud + '.' + decimales;
                            objCampoItem['exacta']      = (control.value.exacta == true) ? "SI" : "NO";
                            objCampoItem['obligatorio'] = (control.value.obligatorio == true) ? "SI" : "NO";
                        break;
                        case "multiple":
                            objCampoItem['campo']       = control.value.campo;
                            objCampoItem['tipo']        = control.value.tipo_dato;
                            objCampoItem['opciones']    = control.value.opciones;
                            objCampoItem['obligatorio'] = (control.value.obligatorio == true) ? "SI" : "NO";
                        break;
                        case "por_defecto":
                            objCampoItem['campo']       = control.value.campo;
                            objCampoItem['tipo']        = control.value.tipo_dato;
                            objCampoItem['opciones']    = control.value.valor_defecto;
                            objCampoItem['longitud']    = control.value.longitud;
                            objCampoItem['obligatorio'] = (control.value.obligatorio == true) ? "SI" : "NO";
                        break;
                    }
                    arrCamposPersonalizadosItem.push(objCampoItem);
                }
            });
        }

        const payload = {
            representacion_grafica_estandar   : this.representacion_grafica_estandar.value ? this.representacion_grafica_estandar.value : '',
            aplica_sector_salud               : this.aplica_sector_salud.value ? this.aplica_sector_salud.value : '',
            encabezado                        : this.encabezado.value ? this.encabezado.value : '',
            piePagina                         : this.piePagina.value ? this.piePagina.value : '',
            valores_resumen                   : this.selectedCampos,
            valores_personalizados            : JSON.stringify(arrCamposPersonalizadosCabecera),
            valores_personalizados_item       : JSON.stringify(arrCamposPersonalizadosItem),
            cargos_cabecera_personalizados    : this.selectedCampos.includes('cargos-a-nivel-documento') ? this.selectedCargosCabeceraPersonalizados : '',
            cargos_items_personalizados       : this.selectedCampos.includes('cargos-a-nivel-item') ? this.selectedCargosItemsPersonalizados : '',
            descuentos_cabecera_personalizados: this.selectedCampos.includes('descuentos-a-nivel-documento') ? this.selectedDescuentosCabeceraPersonalizados : '',
            descuentos_items_personalizados   : this.selectedCampos.includes('descuentos-a-nivel-item') ? this.selectedDescuentosItemsPersonalizados : ''
        };

        return payload;
    }

    previsualizarImagen(files) {
        if (files.length === 0) {
            return;
        }
        const mimeType = files[0].type;
        if (mimeType.match(/image\/png/) == null) {
            this.showError('<h4>Debe seleccionar un archivo de imagen</h4>', 'error', 'Error en selección de imagen', 'Ok', 'btn btn-danger');
            return;
        }

        const reader = new FileReader();
        this.imagePath = files;
        reader.readAsDataURL(files[0]);
        reader.onload = (_event) => {
            const img = new Image();
            img.src = window.URL.createObjectURL(files[0]);
            img.onload = () => {
                const width = img.naturalWidth;
                const height = img.naturalHeight;
                window.URL.revokeObjectURL(img.src);
                if (width > 200) {
                    this.showError('<h4>La imagen debe tener un máximo de 200 píxeles de ancho</h4>', 'error', 'Error en selección de imagen', 'Ok', 'btn btn-danger');
                    this.quitarLogo();
                    return;
                } else if (height > 150) {
                    this.showError('<h4>La imagen debe tener un máximo de 150 píxeles de alto</h4>', 'error', 'Error en selección de imagen', 'Ok', 'btn btn-danger');
                    this.quitarLogo();
                    return;
                }
                this.imgURL = reader.result;
            };
        };
        this.logo = files[0];
    }

    /**
     * Gestiona el cambio de selección en los radio buttons de la personalización de la imagen de la representación gráfica.
     *
     */
    changeOptionRepGrafica(value){
        if (value === 'NO')
            this.quitarLogo();
    }

    /**
     * Manejador para el click del botón de Eliminar Imagen.
     *
     */
    quitarLogo(){
        this.imgURL = null;
        this.logo = null;
        this.archivoImagenRepGrafica.setValue(null);
        this.encabezado.setValue(null);
        this.piePagina.setValue(null);
    }

    /**
     * Convierte la Data Uri con la imagen que se obtiene en Edición a un objeto File.
     *
     */
    dataURItoFile(dataURI) {
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

    /**
     * Crea o actualiza un nuevo registro.
     *
     * @param values
     */
    guardarConfiguracionDocumentoElectronico(values) {
        let procesar = true;

        if(this.selectedCampos.indexOf('enviar-dian-moneda-extranjera') !== -1 && this.selectedCampos.indexOf('moneda-extranjera') === -1) {
            procesar = false;
            this.showError('<span style="text-align:left"><h4>Verifique lo siguiente:</h4><ul><li>Seleccionó la opción [Enviar a la DIAN en Moneda extranjera] pero no seleccionó la opción [Moneda Extranjera]</li></ul></span>', 'error', 'Error en campos a nivel de documentos', 'Ok', 'btn btn-danger');
        }
                
        // En los campos opcionales si se seleccionaron cargos o descuentos a nivel de cabecera no se pueden seleccionar campos de cargos o descuentos a nivel de item
        if(!this.verificarCamposOpcionales() || !this.verificarCamposPersonalizados()) {
            procesar = false;
            this.showError('<span style="text-align:left"><h4>Verifique lo siguiente:</h4><ul><li>No seleccionar retenciones a nivel de documento y a nivel de items</li><li>No incluir cargos y/o descuentos personalizados con el mismo nombre para documentos e items</li></ul></span>', 'error', 'Error en campos opcionales y/o personalizaciones', 'Ok', 'btn btn-danger');
        }

        if(procesar) {
            this.loading(true);
            const payload = this.getPayload();
            if (this.formulario.valid) {
                this._oferentesService.updateConfiguracionDocumento(this.logo, this._ofe_identificacion, payload, this.tipoConfiguracion).subscribe(
                    response => {
                        this.loading(false);
                        let index = this.selectedCampos.indexOf('total-a-pagar');
                        if(index !== -1)
                            this.selectedCampos.splice(index, 1);

                        this.showSuccess('<h3>Actualización exitosa</h3>', 'success', this.titulo, 'Ok', 'btn btn-success');
                    },
                    error => {
                        this.loading(false);
                        let index = this.selectedCampos.indexOf('total-a-pagar');
                        if(index !== -1)
                            this.selectedCampos.splice(index, 1);

                        const texto_errores = this.parseError(error);
                        this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al actualizar la información', 'Ok', 'btn btn-danger');
                    });
            }
        }
    }

    /**
     * Verifica que no se hayan seleccionado retenciones a nive de documento y a nivel de item
     *
     * @returns
     * @memberof ConfiguracionDocumentoBaseComponent
     */
    verificarCamposOpcionales() {
        if(
            (this.selectedCampos.includes('reteica-a-nivel-documento') || this.selectedCampos.includes('reteiva-a-nivel-documento') || this.selectedCampos.includes('retefuente-a-nivel-documento')) && 
            (this.selectedCampos.includes('reteica-a-nivel-item')  || this.selectedCampos.includes('reteiva-a-nivel-item')  || this.selectedCampos.includes('retefuente-a-nivel-item'))
        ) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Verifica que no se hayan creado cargos o descuentos iguales para documento e items
     *
     * @returns
     * @memberof ConfiguracionDocumentoBaseComponent
     */
    verificarCamposPersonalizados() {
        let continuar = true;
        // Verificación de cargos personalizados en documento y cabecera
        this.selectedCargosCabeceraPersonalizados.forEach(cargoDocumento => {
            this.selectedCargosItemsPersonalizados.forEach(cargoItem => {
                if(cargoDocumento === cargoItem && continuar) {
                    continuar = false;
                }
            });
        });

        // Verificación de descuentos personalizados en documento y cabecera
        this.selectedDescuentosCabeceraPersonalizados.forEach(descuentoDocumento => {
            this.selectedDescuentosItemsPersonalizados.forEach(descuentoItem => {
                if(descuentoDocumento === descuentoItem && continuar) {
                    continuar = false;
                }
            });
        });

        return continuar;
    }

    /**
     * Gestiona el evento de paginación de la grid.
     * 
     * @param $evt
     */
    onPage($evt) {}

    /**
     * Recarga el listado en base al término de búsqueda.
     * 
     */
    onSearchInline(buscar: string) {}

    /**
     * Realiza el ordenamiento de los registros y recarga el listado.
     * 
     */
    onOrderBy(column: string, $order: string) {}

    /**
     * Gestiona la acción seleccionada en el select de Acciones en Bloque.
     * 
     */
    onOptionMultipleSelected(opcion: any, selected: any[]) {
    }

    /**
     * Gestiona la acción del botón de ver un registro
     * 
     */
    onViewItem(item: any) {
        this.openModalValorPorDefecto('view', item);
    }

    /**
     * Gestiona la acción del botón de eliminar un registro
     * 
     */
    onRequestDeleteItem(item: any) {}

    /**
     * Gestiona la acción del botón de editar un registro
     * 
     */
    onEditItem(item: any) {
        this.openModalValorPorDefecto('edit', item);
    }

    /**
     * Apertura una ventana modal para crear o editar un registro.
     * 
     * @param usuario
     */
    public openModalValorPorDefecto(action: string, item = null): void {
        const modalConfig = new MatDialogConfig();
        modalConfig.autoFocus = true;
        modalConfig.width = '600px';
        modalConfig.data = {
            action         : action,
            parent         : this,
            valorPorDefecto: item,
            ofe_id         : this.ofe_id
        };
        modalConfig.disableClose = true;
        this.modalValorPorDefecto = this.modal.open(ValoresPorDefectoDocumentoElectronicoGestionarComponent, modalConfig);
    }

    /**
     * Se encarga de cerrar y eliminar la referencia del modal para visualizar el detalle de un registro.
     * 
     */
    public closeModalValorPorDefecto(): void {
        if (this.modalValorPorDefecto) {
            this.modalValorPorDefecto.close();
            this.modalValorPorDefecto = null;
        }
    }

    /**
     * Setea la información de configuración del documento electrónico en el formulario.
     *
     * @private
     * @param {*} data
     * @memberof ConfiguracionDocumentoBaseComponent
     */
    private setDataDocumentoElectronico(data) {
        if(data.ofe_tiene_representacion_grafica_personalizada == 'SI')
            this.representacion_grafica_estandar.setValue('NO');
        else if(data.ofe_tiene_representacion_grafica_personalizada == 'NO')
            this.representacion_grafica_estandar.setValue('SI');
        else
            this.representacion_grafica_estandar.setValue('NO');

        if(data.ofe_campos_personalizados_factura_generica && data.ofe_campos_personalizados_factura_generica.aplica_sector_salud == 'SI')
            this.aplica_sector_salud.setValue('SI');
        else
            this.aplica_sector_salud.setValue('NO');

        if (data.ofe_campos_personalizados_factura_generica && data.ofe_campos_personalizados_factura_generica.encabezado)
            this.encabezado.setValue(data.ofe_campos_personalizados_factura_generica.encabezado);

        if (data.ofe_campos_personalizados_factura_generica && data.ofe_campos_personalizados_factura_generica.pie)
            this.piePagina.setValue(data.ofe_campos_personalizados_factura_generica.pie);

        if (data.ofe_campos_personalizados_factura_generica && data.ofe_campos_personalizados_factura_generica.valores_resumen) {
            this.valores_resumen.forEach(valor => {
                if(data.ofe_campos_personalizados_factura_generica.valores_resumen.indexOf(valor.id) !== -1) {
                    valor.checked = true;
                }
            });
            this.selectedCampos = data.ofe_campos_personalizados_factura_generica.valores_resumen;

            let index = this.selectedCampos.indexOf('total-a-pagar');
            if(index !== -1)
                this.selectedCampos.splice(index, 1);
        }

        if (data.ofe_campos_personalizados_factura_generica && data.ofe_campos_personalizados_factura_generica.valores_personalizados) {
            let indice = 0;
            let camposPersonalizadosCabecera = this.repGrafica.get('camposPersonalizadosCabecera') as FormArray;
            data.ofe_campos_personalizados_factura_generica.valores_personalizados.forEach(campoCabecera => {
                if(campoCabecera.tipo && campoCabecera.tipo != "") {
                    this.agregarCampoPersonalizadoCabecera();
                    this.changeTipoDato(campoCabecera.tipo, indice, 'camposPersonalizadosCabecera');

                    switch(campoCabecera.tipo) {
                        case "texto":
                            camposPersonalizadosCabecera.controls[indice].get('campo').setValue(campoCabecera.campo);
                            camposPersonalizadosCabecera.controls[indice].get('tipo_dato').setValue(campoCabecera.tipo, {emitEvent:false});
                            camposPersonalizadosCabecera.controls[indice].get('longitud').setValue(campoCabecera.longitud);
                            camposPersonalizadosCabecera.controls[indice].get('exacta').setValue(campoCabecera.exacta == "SI" ? true : false);
                            camposPersonalizadosCabecera.controls[indice].get('obligatorio').setValue(campoCabecera.obligatorio == "SI" ? true : false);
                        break;
                        case "numerico":
                            const arrLongitud = campoCabecera.longitud.split('.');
                            camposPersonalizadosCabecera.controls[indice].get('campo').setValue(campoCabecera.campo);
                            camposPersonalizadosCabecera.controls[indice].get('tipo_dato').setValue(campoCabecera.tipo, {emitEvent:false});
                            camposPersonalizadosCabecera.controls[indice].get('longitud').setValue(arrLongitud[0]);
                            camposPersonalizadosCabecera.controls[indice].get('longitud_decimal').setValue(arrLongitud[1]);
                            camposPersonalizadosCabecera.controls[indice].get('exacta').setValue(campoCabecera.exacta == "SI" ? true : false);
                            camposPersonalizadosCabecera.controls[indice].get('obligatorio').setValue(campoCabecera.obligatorio == "SI" ? true : false);
                        break;
                        case "multiple":
                            camposPersonalizadosCabecera.controls[indice].get('campo').setValue(campoCabecera.campo);
                            camposPersonalizadosCabecera.controls[indice].get('tipo_dato').setValue(campoCabecera.tipo, {emitEvent:false});
                            camposPersonalizadosCabecera.controls[indice].get('opciones').setValue(campoCabecera.opciones);
                            camposPersonalizadosCabecera.controls[indice].get('obligatorio').setValue(campoCabecera.obligatorio == "SI" ? true : false);
                        break;
                        case "por_defecto":
                            camposPersonalizadosCabecera.controls[indice].get('campo').setValue(campoCabecera.campo);
                            camposPersonalizadosCabecera.controls[indice].get('tipo_dato').setValue(campoCabecera.tipo, {emitEvent:false});
                            camposPersonalizadosCabecera.controls[indice].get('valor_defecto').setValue(campoCabecera.opciones);
                            camposPersonalizadosCabecera.controls[indice].get('longitud').setValue(campoCabecera.longitud);
                            camposPersonalizadosCabecera.controls[indice].get('obligatorio').setValue(campoCabecera.obligatorio == "SI" ? true : false);
                        break;
                    }
                    indice++
                }
            });
            let intLongitudForm = camposPersonalizadosCabecera.length;
            if(intLongitudForm > 1)
                this.eliminarCampoPersonalizadoCabecera(intLongitudForm-1);
        }

        if (data.ofe_campos_personalizados_factura_generica && data.ofe_campos_personalizados_factura_generica.valores_personalizados_item) {
            let indice = 0;
            let camposPersonalizadosItem = this.repGrafica.get('camposPersonalizadosItem') as FormArray;
            data.ofe_campos_personalizados_factura_generica.valores_personalizados_item.forEach(campoItem => {
                if(campoItem.tipo && campoItem.tipo != "") {
                    this.agregarCampoPersonalizadoItem();
                    this.changeTipoDato(campoItem.tipo, indice, 'camposPersonalizadosItem');

                    switch(campoItem.tipo) {
                        case "texto":
                            camposPersonalizadosItem.controls[indice].get('campo').setValue(campoItem.campo);
                            camposPersonalizadosItem.controls[indice].get('tipo_dato').setValue(campoItem.tipo, {emitEvent:false});
                            camposPersonalizadosItem.controls[indice].get('longitud').setValue(campoItem.longitud);
                            camposPersonalizadosItem.controls[indice].get('exacta').setValue(campoItem.exacta == "SI" ? true : false);
                            camposPersonalizadosItem.controls[indice].get('obligatorio').setValue(campoItem.obligatorio == "SI" ? true : false);
                        break;
                        case "numerico":
                            const arrLongitud = campoItem.longitud.split('.');
                            camposPersonalizadosItem.controls[indice].get('campo').setValue(campoItem.campo);
                            camposPersonalizadosItem.controls[indice].get('tipo_dato').setValue(campoItem.tipo, {emitEvent:false});
                            camposPersonalizadosItem.controls[indice].get('longitud').setValue(arrLongitud[0]);
                            camposPersonalizadosItem.controls[indice].get('longitud_decimal').setValue(arrLongitud[1]);
                            camposPersonalizadosItem.controls[indice].get('exacta').setValue(campoItem.exacta == "SI" ? true : false);
                            camposPersonalizadosItem.controls[indice].get('obligatorio').setValue(campoItem.obligatorio == "SI" ? true : false);
                        break;
                        case "multiple":
                            camposPersonalizadosItem.controls[indice].get('campo').setValue(campoItem.campo);
                            camposPersonalizadosItem.controls[indice].get('tipo_dato').setValue(campoItem.tipo, {emitEvent:false});
                            camposPersonalizadosItem.controls[indice].get('opciones').setValue(campoItem.opciones);
                            camposPersonalizadosItem.controls[indice].get('obligatorio').setValue(campoItem.obligatorio == "SI" ? true : false);
                        break;
                        case "por_defecto":
                            camposPersonalizadosItem.controls[indice].get('campo').setValue(campoItem.campo);
                            camposPersonalizadosItem.controls[indice].get('tipo_dato').setValue(campoItem.tipo, {emitEvent:false});
                            camposPersonalizadosItem.controls[indice].get('valor_defecto').setValue(campoItem.opciones);
                            camposPersonalizadosItem.controls[indice].get('longitud').setValue(campoItem.longitud);
                            camposPersonalizadosItem.controls[indice].get('obligatorio').setValue(campoItem.obligatorio == "SI" ? true : false);
                        break;
                    }

                    indice++
                }
            });
            let intLongitudForm = camposPersonalizadosItem.length;
            if(intLongitudForm > 1)
                this.eliminarCampoPersonalizadoItem(intLongitudForm-1);
        }

        if (data.ofe_campos_personalizados_factura_generica && data.ofe_campos_personalizados_factura_generica.cargos_cabecera_personalizados) {
            this.cargosCabeceraPersonalizados.setValue(data.ofe_campos_personalizados_factura_generica.cargos_cabecera_personalizados);
            this.selectedCargosCabeceraPersonalizados = data.ofe_campos_personalizados_factura_generica.cargos_cabecera_personalizados;
        }

        if (data.ofe_campos_personalizados_factura_generica && data.ofe_campos_personalizados_factura_generica.cargos_items_personalizados) {
            this.cargosItemsPersonalizados.setValue(data.ofe_campos_personalizados_factura_generica.cargos_items_personalizados);
            this.selectedCargosItemsPersonalizados = data.ofe_campos_personalizados_factura_generica.cargos_items_personalizados;
        }

        if (data.ofe_campos_personalizados_factura_generica && data.ofe_campos_personalizados_factura_generica.descuentos_cabecera_personalizados) {
            this.descuentosCabeceraPersonalizados.setValue(data.ofe_campos_personalizados_factura_generica.descuentos_cabecera_personalizados);
            this.selectedDescuentosCabeceraPersonalizados = data.ofe_campos_personalizados_factura_generica.descuentos_cabecera_personalizados;
        }

        if (data.ofe_campos_personalizados_factura_generica && data.ofe_campos_personalizados_factura_generica.descuentos_items_personalizados) {
            this.descuentosItemsPersonalizados.setValue(data.ofe_campos_personalizados_factura_generica.descuentos_items_personalizados);
            this.selectedDescuentosItemsPersonalizados = data.ofe_campos_personalizados_factura_generica.descuentos_items_personalizados;
        }

        if (data.logo && data.ofe_tiene_representacion_grafica_personalizada === 'NO') {
            this.imgURL = this.sanitizer.bypassSecurityTrustUrl(data.logo);
            this.logo = this.dataURItoFile(this.imgURL.changingThisBreaksApplicationSecurity);
        }
    }

    /**
     * Setea la información de configuración del documento soporte en el formulario.
     *
     * @private
     * @param {*} data
     * @memberof ConfiguracionDocumentoBaseComponent
     */
    private setDataDocumentoSoporte(data) {
        if(data.ofe_tiene_representacion_grafica_personalizada_ds == 'SI')
            this.representacion_grafica_estandar.setValue('NO');
        else if(data.ofe_tiene_representacion_grafica_personalizada_ds == 'NO')
            this.representacion_grafica_estandar.setValue('SI');
        else
            this.representacion_grafica_estandar.setValue('NO');

        if (data.ofe_campos_personalizados_factura_generica && data.ofe_campos_personalizados_factura_generica.encabezado_ds)
            this.encabezado.setValue(data.ofe_campos_personalizados_factura_generica.encabezado_ds);

        if (data.ofe_campos_personalizados_factura_generica && data.ofe_campos_personalizados_factura_generica.pie_ds)
            this.piePagina.setValue(data.ofe_campos_personalizados_factura_generica.pie_ds);

        if (data.ofe_campos_personalizados_factura_generica && data.ofe_campos_personalizados_factura_generica.valores_resumen_ds) {
            this.valores_resumen.forEach(valor => {
                if(data.ofe_campos_personalizados_factura_generica.valores_resumen_ds.indexOf(valor.id) !== -1) {
                    valor.checked = true;
                }
            });
            this.selectedCampos = data.ofe_campos_personalizados_factura_generica.valores_resumen_ds;

            let index = this.selectedCampos.indexOf('total-a-pagar');
            if(index !== -1)
                this.selectedCampos.splice(index, 1);
        }

        if (data.ofe_campos_personalizados_factura_generica && data.ofe_campos_personalizados_factura_generica.valores_personalizados_ds) {
            let indice = 0;
            let camposPersonalizadosCabecera = this.repGrafica.get('camposPersonalizadosCabecera') as FormArray;
            data.ofe_campos_personalizados_factura_generica.valores_personalizados_ds.forEach(campoCabecera => {
                if(campoCabecera.tipo && campoCabecera.tipo != "") {
                    this.agregarCampoPersonalizadoCabecera();
                    this.changeTipoDato(campoCabecera.tipo, indice, 'camposPersonalizadosCabecera');

                    switch(campoCabecera.tipo) {
                        case "texto":
                            camposPersonalizadosCabecera.controls[indice].get('campo').setValue(campoCabecera.campo);
                            camposPersonalizadosCabecera.controls[indice].get('tipo_dato').setValue(campoCabecera.tipo, {emitEvent:false});
                            camposPersonalizadosCabecera.controls[indice].get('longitud').setValue(campoCabecera.longitud);
                            camposPersonalizadosCabecera.controls[indice].get('exacta').setValue(campoCabecera.exacta == "SI" ? true : false);
                            camposPersonalizadosCabecera.controls[indice].get('obligatorio').setValue(campoCabecera.obligatorio == "SI" ? true : false);
                        break;
                        case "numerico":
                            const arrLongitud = campoCabecera.longitud.split('.');
                            camposPersonalizadosCabecera.controls[indice].get('campo').setValue(campoCabecera.campo);
                            camposPersonalizadosCabecera.controls[indice].get('tipo_dato').setValue(campoCabecera.tipo, {emitEvent:false});
                            camposPersonalizadosCabecera.controls[indice].get('longitud').setValue(arrLongitud[0]);
                            camposPersonalizadosCabecera.controls[indice].get('longitud_decimal').setValue(arrLongitud[1]);
                            camposPersonalizadosCabecera.controls[indice].get('exacta').setValue(campoCabecera.exacta == "SI" ? true : false);
                            camposPersonalizadosCabecera.controls[indice].get('obligatorio').setValue(campoCabecera.obligatorio == "SI" ? true : false);
                        break;
                        case "multiple":
                            camposPersonalizadosCabecera.controls[indice].get('campo').setValue(campoCabecera.campo);
                            camposPersonalizadosCabecera.controls[indice].get('tipo_dato').setValue(campoCabecera.tipo, {emitEvent:false});
                            camposPersonalizadosCabecera.controls[indice].get('opciones').setValue(campoCabecera.opciones);
                            camposPersonalizadosCabecera.controls[indice].get('obligatorio').setValue(campoCabecera.obligatorio == "SI" ? true : false);
                        break;
                        case "por_defecto":
                            camposPersonalizadosCabecera.controls[indice].get('campo').setValue(campoCabecera.campo);
                            camposPersonalizadosCabecera.controls[indice].get('tipo_dato').setValue(campoCabecera.tipo, {emitEvent:false});
                            camposPersonalizadosCabecera.controls[indice].get('valor_defecto').setValue(campoCabecera.opciones);
                            camposPersonalizadosCabecera.controls[indice].get('longitud').setValue(campoCabecera.longitud);
                            camposPersonalizadosCabecera.controls[indice].get('obligatorio').setValue(campoCabecera.obligatorio == "SI" ? true : false);
                        break;
                    }
                    indice++
                }
            });
            let intLongitudForm = camposPersonalizadosCabecera.length;
            if(intLongitudForm > 1)
                this.eliminarCampoPersonalizadoCabecera(intLongitudForm-1);
        }

        if (data.ofe_campos_personalizados_factura_generica && data.ofe_campos_personalizados_factura_generica.valores_personalizados_item_ds) {
            let indice = 0;
            let camposPersonalizadosItem = this.repGrafica.get('camposPersonalizadosItem') as FormArray;
            data.ofe_campos_personalizados_factura_generica.valores_personalizados_item_ds.forEach(campoItem => {
                if(campoItem.tipo && campoItem.tipo != "") {
                    this.agregarCampoPersonalizadoItem();
                    this.changeTipoDato(campoItem.tipo, indice, 'camposPersonalizadosItem');

                    switch(campoItem.tipo) {
                        case "texto":
                            camposPersonalizadosItem.controls[indice].get('campo').setValue(campoItem.campo);
                            camposPersonalizadosItem.controls[indice].get('tipo_dato').setValue(campoItem.tipo, {emitEvent:false});
                            camposPersonalizadosItem.controls[indice].get('longitud').setValue(campoItem.longitud);
                            camposPersonalizadosItem.controls[indice].get('exacta').setValue(campoItem.exacta == "SI" ? true : false);
                            camposPersonalizadosItem.controls[indice].get('obligatorio').setValue(campoItem.obligatorio == "SI" ? true : false);
                        break;
                        case "numerico":
                            const arrLongitud = campoItem.longitud.split('.');
                            camposPersonalizadosItem.controls[indice].get('campo').setValue(campoItem.campo);
                            camposPersonalizadosItem.controls[indice].get('tipo_dato').setValue(campoItem.tipo, {emitEvent:false});
                            camposPersonalizadosItem.controls[indice].get('longitud').setValue(arrLongitud[0]);
                            camposPersonalizadosItem.controls[indice].get('longitud_decimal').setValue(arrLongitud[1]);
                            camposPersonalizadosItem.controls[indice].get('exacta').setValue(campoItem.exacta == "SI" ? true : false);
                            camposPersonalizadosItem.controls[indice].get('obligatorio').setValue(campoItem.obligatorio == "SI" ? true : false);
                        break;
                        case "multiple":
                            camposPersonalizadosItem.controls[indice].get('campo').setValue(campoItem.campo);
                            camposPersonalizadosItem.controls[indice].get('tipo_dato').setValue(campoItem.tipo, {emitEvent:false});
                            camposPersonalizadosItem.controls[indice].get('opciones').setValue(campoItem.opciones);
                            camposPersonalizadosItem.controls[indice].get('obligatorio').setValue(campoItem.obligatorio == "SI" ? true : false);
                        break;
                        case "por_defecto":
                            camposPersonalizadosItem.controls[indice].get('campo').setValue(campoItem.campo);
                            camposPersonalizadosItem.controls[indice].get('tipo_dato').setValue(campoItem.tipo, {emitEvent:false});
                            camposPersonalizadosItem.controls[indice].get('valor_defecto').setValue(campoItem.opciones);
                            camposPersonalizadosItem.controls[indice].get('longitud').setValue(campoItem.longitud);
                            camposPersonalizadosItem.controls[indice].get('obligatorio').setValue(campoItem.obligatorio == "SI" ? true : false);
                        break;
                    }
                    indice++
                }
            });
            let intLongitudForm = camposPersonalizadosItem.length;
            if(intLongitudForm > 1)
                this.eliminarCampoPersonalizadoItem(intLongitudForm-1);
        }

        if (data.ofe_campos_personalizados_factura_generica && data.ofe_campos_personalizados_factura_generica.cargos_cabecera_personalizados_ds) {
            this.cargosCabeceraPersonalizados.setValue(data.ofe_campos_personalizados_factura_generica.cargos_cabecera_personalizados_ds);
            this.selectedCargosCabeceraPersonalizados = data.ofe_campos_personalizados_factura_generica.cargos_cabecera_personalizados_ds;
        }

        if (data.ofe_campos_personalizados_factura_generica && data.ofe_campos_personalizados_factura_generica.cargos_items_personalizados_ds) {
            this.cargosItemsPersonalizados.setValue(data.ofe_campos_personalizados_factura_generica.cargos_items_personalizados_ds);
            this.selectedCargosItemsPersonalizados = data.ofe_campos_personalizados_factura_generica.cargos_items_personalizados_ds;
        }

        if (data.ofe_campos_personalizados_factura_generica && data.ofe_campos_personalizados_factura_generica.descuentos_cabecera_personalizados_ds) {
            this.descuentosCabeceraPersonalizados.setValue(data.ofe_campos_personalizados_factura_generica.descuentos_cabecera_personalizados_ds);
            this.selectedDescuentosCabeceraPersonalizados = data.ofe_campos_personalizados_factura_generica.descuentos_cabecera_personalizados_ds;
        }

        if (data.ofe_campos_personalizados_factura_generica && data.ofe_campos_personalizados_factura_generica.descuentos_items_personalizados_ds) {
            this.descuentosItemsPersonalizados.setValue(data.ofe_campos_personalizados_factura_generica.descuentos_items_personalizados_ds);
            this.selectedDescuentosItemsPersonalizados = data.ofe_campos_personalizados_factura_generica.descuentos_items_personalizados_ds;
        }

        if (data.logo_ds && data.ofe_tiene_representacion_grafica_personalizada_ds === 'NO') {
            this.imgURL = this.sanitizer.bypassSecurityTrustUrl(data.logo_ds);
            this.logo = this.dataURItoFile(this.imgURL.changingThisBreaksApplicationSecurity);
        }
    }
}
