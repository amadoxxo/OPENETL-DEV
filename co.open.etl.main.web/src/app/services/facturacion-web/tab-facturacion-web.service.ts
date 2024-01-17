import { Injectable, Output, Input, EventEmitter, Directive } from '@angular/core';
import { TabFacturacionWeb } from '../../main/models/tab-facturacion-web.model';
import { BehaviorSubject } from 'rxjs';
import { FormGroup, FormArray, Validators } from '@angular/forms';
import swal from 'sweetalert2';

@Directive()
@Injectable({
    providedIn: 'root'
})
export class TabFacturacionWebService {
    public tabs: TabFacturacionWeb[] = [];
    public tabSub = new BehaviorSubject<TabFacturacionWeb[]>(this.tabs);
    public tab: any;

    @Output() etiquetaMonedaDocumento             = new EventEmitter;
    @Output() etiquetaMonedaExtranjera            = new EventEmitter;
    @Output() dataValoresResumen                  = new EventEmitter;
    @Output() mostrarCampoME                      = new EventEmitter;
    @Output() valorTRM                            = new EventEmitter;

    @Output() dataEmitidaFormCargo                = new EventEmitter;
    @Output() dataEmitidaFormDescuento            = new EventEmitter;
    @Output() dataEmitidaFormAnticipo             = new EventEmitter;
    @Output() dataEmitidaFormRetencionesSugeridas = new EventEmitter;

    @Output() dataEmitidaFormCargoItem            = new EventEmitter;
    @Output() dataEmitidaFormDescuentoItem        = new EventEmitter;

    @Output() dataTotalItemRetencionesSugeridas   = new EventEmitter;
    @Output() dataTotalItemValor                  = new EventEmitter;
    @Output() dataTotalItemCargo                  = new EventEmitter;
    @Output() dataTotalItemDescuento              = new EventEmitter;
    @Output() dataTotalItemTributosPorcentajeIVA  = new EventEmitter;
    @Output() dataTotalItemTributosUnidadIVA      = new EventEmitter;
    @Output() dataTotalItemOtrosTributosPorcentaje= new EventEmitter;
    @Output() dataTotalItemOtrosTributosUnidad    = new EventEmitter;
    @Output() dataTotalAutorretenciones           = new EventEmitter;
    @Output() valorTotalItem                      = new EventEmitter;

    @Input() formularioGeneral: FormGroup;

    // Variables de validación
    public decimales        : number = 1;
    public decimalesME      : number = 1;
    public expresionReg     : string = '^\\d+$';
    public requireME        : boolean = false;
    public trmFecha         : string = '';
    public trmValor         : string = '';
    public trmCodigoME      : string = 'ME';
    public mensajeDinamico  : string = '';
    public codigoMoneda     : string = '';
    public envioDianME      : string = 'NO';
    public arrTipoDocumentos: Array<any> = [];

    /**
     * Agrega un Tab Adicional al Tab group.
     *
     * @param {TabFacturacionWeb} tab
     * @returns {number}
     * @memberof TabFacturacionWebService
     */
    public addTab(tab: TabFacturacionWeb): number {
        tab.id = this.tabs.length + 1;
        tab.active = true;
        this.tabs.push(tab);
        this.tabSub.next(this.tabs);

        return tab.id;
    }

    /**
     * Actualiza la Data de un Tab por el Id.
     *
     * @param {Array<TabFacturacionWeb>} tabCollection
     * @param {*} tabData
     * @memberof TabFacturacionWebService
     */
    public updateTab(tabCollections: Array<TabFacturacionWeb>, tabData: any): void{
        tabCollections.forEach(tabCollection => {
            const indexTab = this.tabs.findIndex(tab => tabCollection.id === tab.id);
            this.tabs[indexTab].tabData = tabData;
        });
        this.tabSub.next(this.tabs);
    }

    /**
     * Elimina una posición de la colección de Tabs Dinámicos.
     *
     * @param {number} index
     * @memberof TabFacturacionWebService
     */
    public eliminarTab(index: number): void{
        this.tabs.splice(index, 1);
        this.tabSub.next(this.tabs);
    }

    /**
     * Deshabilita los Tabs Dinamicos.
     *
     * @memberof TabFacturacionWebService
     */
    public disableTabs(): void {
        for (const tabIterator of this.tabs) {
            tabIterator.active = false;
        }

        this.tabSub.next(this.tabs);
    }

    /**
     * Habilita los Tabs Dinamicos.
     *
     * @memberof TabFacturacionWebService
     */
    public enableTabs(): void {
        for (const tabIterator of this.tabs) {
            tabIterator.active = true;
        }

        this.tabSub.next(this.tabs);
    }

    /**
     * Inicializa el array de tabs dinamicos.
     *
     * @memberof TabFacturacionWebService
     */
    public inicializarTabs(): void{
        this.tabs = [];
        this.tabSub.next(this.tabs);
    }

    /**
     * Asigna la información para las validaciones de valores y campos.
     *
     * @param {number} decimales Cantidad de decimales para la moneda del documento
     * @param {number} decimalesME Cantidad de decimales para la moneda extranjera
     * @param {string} expresionReg Expresión regular para los campos en donde se ingresan valores
     * @memberof TabFacturacionWebService
     */
    dataValidacionCampos(decimales: number, decimalesME: number, expresionReg: string){
        this.decimales      = decimales;
        this.decimalesME    = decimalesME;
        this.expresionReg   = expresionReg;
    }

    /**
     * Asigna la información para la validación de la TRM.
     *
     * @param {boolean} requireME Aplica ingresar los campos de la Moneda Extranjera (TRM, Fecha y Moneda)
     * @param {*} trmFecha Value del campo fecha de la TRM
     * @param {*} trmValor Value del campo valor de la TRM
     * @param {*} trmCodigoME Value del campo Moneda Extranjera
     * @memberof TabFacturacionWebService
     */
    dataValidacionTRM(requireME: boolean, trmFecha, trmValor, trmCodigoME, codigoMoneda, envioDianME){
        this.requireME      = requireME;
        this.trmFecha       = trmFecha;
        this.trmValor       = (!isNaN(trmValor) && Number(trmValor) > 0) ? trmValor : '';
        this.trmCodigoME    = trmCodigoME;
        this.codigoMoneda   = codigoMoneda;
        this.envioDianME    = envioDianME;
    }

    /**
     * Agrega el porcentaje correspondiente al codigo - descripción seleccionado en el combo select.
     *
     * @param {number} i Posición del formulario en el FormArray
     * @param {string} prefijo Prefijo de los controls
     * @param {*} objEvento $event registro seleccionado
     * @param {string} formGroupName Nombre del formArray
     * @param {FormGroup} formGroup FormGroup del formulario
     * @memberof TabFacturacionWebService
     */
    setValoresAutoComplete(i:number, prefijo: string, objEvento, formGroupName: string, formGroup: FormGroup){
        let formArray       : any    = <FormArray>formGroup.get(formGroupName);
        let valorCodigo     : string = (objEvento[prefijo+'_codigo']) ? objEvento[prefijo+'_codigo'] : '';
        let valorCodigoDescripcion : string = (objEvento[prefijo+'_codigo_descripcion']) ? objEvento[prefijo+'_codigo_descripcion'] : '';
        let valorPorcentaje : string = (objEvento[prefijo+'_porcentaje']) ? objEvento[prefijo+'_porcentaje'] : '';
        let valorCodigoDian : string = (prefijo === 'dmd' && objEvento['get_codigo_descuento']) ? objEvento.get_codigo_descuento.cde_codigo : '';
        let idCodigoDian    : string = (prefijo === 'dmd' && objEvento['cde_id'] !== null) ? objEvento.cde_id : '';

        if(objEvento) {
            let asignado = false;
            // Valida que el registro no haya sido seleccionado
            for (let grupo of formArray.controls) {
                if(grupo.get(prefijo+'_codigo').value === valorCodigo && !asignado)
                    asignado = true;
            }
            if(asignado){
                this.resetCamposAutoComplete(i, prefijo, formGroupName, formArray);
                swal({
                    html: 'El registro ya ha sido seleccionado',
                    type: 'error',
                    confirmButtonClass: 'btn btn-danger',
                    confirmButtonText: 'Aceptar',
                    buttonsStyling: false,
                    allowOutsideClick: false
                }).catch(swal.noop);
            } else {
                // formControls especiales para Descuentos
                if(formGroupName == 'descuentosGlobales'){
                    formArray.controls[i].controls['cde_id'].setValue(idCodigoDian);
                    formArray.controls[i].controls['cde_codigo'].setValue(valorCodigoDian);
                }

                formArray.controls[i].controls[prefijo+'_codigo'].setValue(valorCodigo, {emitEvent:false});
                formArray.controls[i].controls[prefijo+'_codigo_descripcion'].setValue(valorCodigoDescripcion, {emitEvent:false});
                formArray.controls[i].controls[prefijo+'_porcentaje'].setValue(valorPorcentaje, {emitEvent:false});
                formArray.controls[i].controls[prefijo+'_base'].setValidators([Validators.required, Validators.pattern(new RegExp(this.expresionReg))]);
                formArray.controls[i].controls[prefijo+'_base'].enable();
                formArray.controls[i].controls[prefijo+'_base'].setValue('');
                formArray.controls[i].controls[prefijo+'_valor'].setValidators([Validators.required, Validators.pattern(new RegExp(this.expresionReg))]);
                formArray.controls[i].controls[prefijo+'_valor'].enable();
                formArray.controls[i].controls[prefijo+'_valor'].setValue('');
                formArray.controls[i].controls[prefijo+'_base_moneda_extranjera'].setValue('');
                formArray.controls[i].controls[prefijo+'_valor_moneda_extranjera'].setValue('');
            }
        } else {
            this.resetCamposAutoComplete(i, prefijo, formGroupName, formArray);
        }
    }

    /**
     * Reinicia los valores de los campos para los autoComplete.
     *
     * @param {number} i Posición del formArray
     * @param {string} prefijo Prefijo de los formControls
     * @param {string} formGroupName Nombre del FormGroup
     * @param {FormArray} formArray Arreglo de registros en el formulario
     * @memberof TabFacturacionWebService
     */
    resetCamposAutoComplete(i: number, prefijo: string, formGroupName: string, formArray){
        formArray.controls[i].controls[prefijo+'_codigo'].setValue('', {emitEvent:false});
        formArray.controls[i].controls[prefijo+'_codigo_descripcion'].setValue('', {emitEvent:false});
        formArray.controls[i].controls[prefijo+'_porcentaje'].setValue('', {emitEvent:false});
        formArray.controls[i].controls[prefijo+'_base'].disable();
        formArray.controls[i].controls[prefijo+'_base'].clearValidators();
        formArray.controls[i].controls[prefijo+'_base'].setValue('');
        formArray.controls[i].controls[prefijo+'_valor'].disable();
        formArray.controls[i].controls[prefijo+'_valor'].clearValidators();
        formArray.controls[i].controls[prefijo+'_valor'].setValue('');
        formArray.controls[i].controls[prefijo+'_base_moneda_extranjera'].setValue('');
        formArray.controls[i].controls[prefijo+'_valor_moneda_extranjera'].setValue('');
        // formControls especiales para Descuentos
        if(formGroupName == 'descuentosGlobales'){
            formArray.controls[i].controls['cde_id'].setValue('');
            formArray.controls[i].controls['cde_codigo'].setValue('');
        }
    }

    /**
     * Calcula los campos de base o valor, según el primer campo digitado.
     * 
     * Si se ha digitado primero el campo base, se calcula el campo del
     * valor, así mismo en caso contrario
     *
     * @param {number} i Posición del control en el formulario
     * @param {string} prefijo Prefijo de los formControls
     * @param {string} nombreControl Nombre del control dentro del form array
     * @param {string} formGroupName Nombre del FormGroup
     * @param {FormGroup} formGroup FormGroup del formulario
     * @memberof TabFacturacionWebService
     */
    calcularCamposBaseValor(i:number, prefijo:string, nombreControl: string, formGroupName: string, formGroup: FormGroup) {
        let formArray:any      = <FormArray>formGroup.get(formGroupName);
        let formControl        = formArray.controls[i].controls;
        let valueControlBase   = (formControl[prefijo+'_base'] != undefined) ? formControl[prefijo+'_base'].value : '';
        let valueControlValor  = (formControl[prefijo+'_valor'] != undefined) ? formControl[prefijo+'_valor'].value : '';
        let valueControlPtj    = (formControl[prefijo+'_porcentaje'] != undefined) ? formControl[prefijo+'_porcentaje'].value : '';
        let aplicaCalculoME    = this.aplicaCalculoMonedaExtranjera();

        if(aplicaCalculoME) {
            if(!isNaN(valueControlBase) && !isNaN(valueControlValor) && !isNaN(valueControlPtj)) {
                // Condicional para calcular el valor, cuando la base y porcentaje han sido digitados
                if(nombreControl === prefijo+'_base' && valueControlPtj !== '' &&
                    ((valueControlValor === '' && valueControlBase === '') ||
                    (valueControlValor !== '' && valueControlBase === '')  ||
                    (valueControlValor !== '' && valueControlBase !== '')  ||
                    (valueControlValor === '' && valueControlBase !== ''))
                ){
                    valueControlValor = (valueControlBase * valueControlPtj)/100;
                    let valor = this.redondearDecimales(Number(valueControlValor), this.decimales);
                    valueControlBase = this.asignarDecimales(valueControlBase);
                    formControl[prefijo+'_valor'].setValue(valor);
                    formControl[prefijo+'_base'].setValue(valueControlBase);
                    this.calcularValorME(prefijo, 'base', formArray);
                }
    
                // Condicional para calcular la base, cuando el valor y el porcentaje han sido digitados
                if(nombreControl === prefijo+'_valor' && valueControlPtj !== '' &&
                    ((valueControlBase === '' && valueControlValor === '') ||
                    (valueControlBase !== '' && valueControlValor === '') ||
                    (valueControlBase !== '' && valueControlValor !== '') ||
                    (valueControlBase === '' && valueControlValor !== ''))
                ){
                    valueControlBase = (valueControlValor * 100)/valueControlPtj;
                    let valor = this.redondearDecimales(Number(valueControlBase), this.decimales);
                    valueControlValor = this.asignarDecimales(valueControlValor);
                    formControl[prefijo+'_base'].setValue(valor);
                    formControl[prefijo+'_valor'].setValue(valueControlValor);
                    this.calcularValorME(prefijo, 'base', formArray);
                }
    
                // Condicional para recalcular la base y el valor, cuando se cambia el porcentaje
                if(nombreControl === prefijo+'_porcentaje' && valueControlPtj !== '' &&
                    (valueControlBase !== '' || valueControlValor !== '')
                ){
                    valueControlValor = (valueControlBase * valueControlPtj)/100;
                    let valor = this.redondearDecimales(Number(valueControlValor), this.decimales);
                    valueControlBase = this.redondearDecimales(Number(valueControlBase), this.decimales);
                    valueControlBase = this.asignarDecimales(valueControlBase);
                    formControl[prefijo+'_valor'].setValue(valor);
                    formControl[prefijo+'_base'].setValue(valueControlBase);
                    this.calcularValorME(prefijo, 'base', formArray);
                }
    
                this.asignarValoresTotales(prefijo, formArray, formGroup);
            }
        } else {
            formControl[prefijo+'_valor'].setValue('');
            formControl[prefijo+'_base'].setValue('');
        }
    }

    /**
     * Redondea un valor númerico según los decimales envíados como parametros o se redondea según el tipo de moneda.
     * 
     * Ejemplos para cuando se desea redondear a un decimal definido:
     * 
     *     valor     |    decimalDefinido  |      moneda    |    return
     *   --------------------------------------------------------------
     *   7.4212312   |          3          |  [No se envia] |    7.421
     *    7.3154     |          2          |  [No se envia] |     7.32
     *    3.0004     |          2          |  [No se envia] |      3
     * 
     * Para cuando se busca redondear a los decimales definidos para el OFE de moneda documento o moneda extranjera:
     * 
     * Ejemplo: decimales para la moneda del documento es 2 y para la moneda extranjera es 1.
     * 
     *      valor     |    decimalDefinido  |       moneda        |   return
     *    -------------------------------------------------------------------
     *     1.5312     |          0          |  moneda-documento   |   1.53
     *     2.3142     |          0          |  moneda-extranjera  |   2.3
     *     4.7889     |          0          |  moneda-documento   |   4.79
     *  
     * @param {number} valor El valor númerico a redondear
     * @param {number} decimalDefinido Los decimales a los que serán redondeados
     * @memberof TabFacturacionWebService
     */
    redondearDecimales(valor: number, decimalDefinido:number, moneda:string=''): number{
        if(moneda === '')
            return Number(valor.toFixed(decimalDefinido));
        if(moneda === 'moneda-documento')
            return Number(valor.toFixed(this.decimales));
        if(moneda === 'moneda-extranjera')
            return Number(valor.toFixed(this.decimalesME));
    }

    /**
     * Permite asignar los decimales dependiendo de la moneda del documento.
     * 
     * Este metodo se usa en los input donde el usuario digita valores numericos con decimales, y la cantidad de decimales que digita
     * Debe coincidir con los decimales parametrizados.
     *
     * @param {*} valor Valor para asignar los decimales
     * @return {*}  {number}
     * @memberof TabFacturacionWebService
     */
    asignarDecimales(valor): number{
        let resultado = 0;
        if(!isNaN(valor)){
            valor = valor.toString();
            let arrValor = valor.split('.');
            let entero = arrValor[0];
            if(arrValor.length > 1){
                let decimal = arrValor[1].substring(0, this.decimales);
                resultado = Number(entero+'.'+decimal);
            }else
                resultado = valor;
        };

        return resultado;
    }

    /**
     * Asigna los valores totales a los campos total y total moneda extranjera.
     *
     * @param {string} prefijo Prefijo de los campos de los controles
     * @param {*} formArray Arreglo de registros en el formulario
     * @param {*} formGroup FormGroup del formulario
     * @memberof TabFacturacionWebService
     */
    asignarValoresTotales(prefijo:string, formArray, formGroup){
        let valor:number = 0;
        let valorME:number = 0;
        let valorTotal: number = 0;
        let valorTotalME: number = 0;
        for (let grupo of formArray.controls) {
            valor = (!isNaN(grupo.get(prefijo+'_valor').value) || grupo.get(prefijo+'_valor').value == '') ? Number(grupo.get(prefijo+'_valor').value): 0;
            valorTotal += valor;
            valorME = (!isNaN(grupo.get(prefijo+'_valor_moneda_extranjera').value) || grupo.get(prefijo+'_valor_moneda_extranjera').value == '') ? Number(grupo.get(prefijo+'_valor_moneda_extranjera').value): 0;
            valorTotalME += valorME;
        }
        valorTotal   = this.redondearDecimales(valorTotal, this.decimales);
        valorTotalME = this.redondearDecimales(valorTotalME, this.decimalesME);
        formGroup.get('total').setValue(valorTotal);
        formGroup.get('total_moneda_extranjera').setValue(valorTotalME);
    }

    /**
     * Metodo que calcula el valor de la moneda extranjera.
     *
     * @param {string} prefijo Prefijo de los formControls
     * @param {string} nombreCampoDinamico Nombre del campo variable Ej: Valor unitario (valor_unitario) o Base (base)
     * @param {(FormArray | FormGroup)} form Formulario en el cual se va a iterar para hacer el calculo
     * @param {string} [tipoFormulario='array'] Tipo de formulario de donde se toman los valores
     * @memberof TabFacturacionWebService
     */
    calcularValorME(prefijo:string, nombreCampoDinamico: string, form: FormArray | FormGroup, tipoFormulario:string ='formArray'){
        let controls:any = form.controls;
        let validacionTRM = this.verificarTrm(prefijo, form, tipoFormulario);
        if(validacionTRM){
            if(this.trmValor !== '' && (this.trmCodigoME !== '' && this.trmCodigoME !== null) && (this.trmFecha !== '' && this.trmFecha !== null)) {
                if(tipoFormulario === 'formArray'){
                    for (let grupo of controls) {
                        let valor     :number = 0;
                        let valorTotal:number = 0;

                        // Variable Campo (Base o Vlr Unitario)
                        // dmc_base     ddo_valor_unitario
                        valor = (!isNaN(grupo.get(prefijo+'_'+nombreCampoDinamico).value)) ? Number(grupo.get(prefijo+'_'+nombreCampoDinamico).value): 0;

                        // Variable Campo Valor ( Valor ME o Valor Total ME)
                        // dmc_valor    ddo_valor
                        valorTotal = (!isNaN(grupo.get(prefijo+'_valor').value)) ? Number(grupo.get(prefijo+'_valor').value): 0;

                        // Conversión de TRM
                        valor      = this.conversionTRM(valor);
                        valorTotal = this.conversionTRM(valorTotal);

                        // Variable Campo Moneda Extranjera (Base ME o Vlr Unitario ME)
                        // dmc_base_moneda_extranjera     ddo_valor_unitario_moneda_extranjera
                        grupo.get(prefijo+'_'+nombreCampoDinamico+'_moneda_extranjera').setValue(valor);

                        // Variable Campo Moneda Extranjera (Valor ME o Vlr Total ME)
                        // dmc_valor_moneda_extranjera     ddo_valor_moneda_extranjera
                        grupo.get(prefijo+'_valor_moneda_extranjera').setValue(valorTotal);
                    }
                } else {
                    let valor     :number = 0;
                    let valorTotal:number = 0;
                    let grupo     = form.controls;

                    valor = (!isNaN(grupo[prefijo+'_'+nombreCampoDinamico].value)) ? Number(grupo[prefijo+'_'+nombreCampoDinamico].value): 0;
                    valorTotal = (!isNaN(grupo[prefijo+'_valor'].value)) ? Number(grupo[prefijo+'_valor'].value): 0;

                    valor      = this.conversionTRM(valor);
                    valorTotal = this.conversionTRM(valorTotal);

                    grupo[prefijo+'_'+nombreCampoDinamico+'_moneda_extranjera'].setValue(valor);
                    grupo[prefijo+'_valor_moneda_extranjera'].setValue(valorTotal);
                }
            }
        }
    }

    /**
     * Verifica que la TRM o la fecha esté digitada, si se seleccionó moneda extranjera.
     *
     * @param {string} prefijo Prefijo de los formControls
     * @param {(FormArray | FormGroup)} form Formulario en el cual se va a iterar para hacer el calculo
     * @param {string} tipoFormulario Tipo de formulario de donde se toman los valores
     * @return {*}  {boolean} Estado de la validación, si retorna TRUE es porque la validación está correctamente
     * @memberof TabFacturacionWebService
     */
    verificarTrm(prefijo: string, form: FormArray | FormGroup, tipoFormulario: string): boolean {
        let estadoCalculoME: boolean = this.aplicaCalculoMonedaExtranjera();
        if(!estadoCalculoME) {
            let controls:any = form.controls;
            if(tipoFormulario == 'formArray'){
                for (let grupo of controls) {
                    grupo.get(prefijo+'_valor').setValue('');
                    grupo.get(prefijo+'_valor_moneda_extranjera').setValue('');
                    if(controls[prefijo+'_base'] != undefined){
                        grupo.get(prefijo+'_base').setValue('');
                        grupo.get(prefijo+'_base_moneda_extranjera').setValue('');
                    }
                }
            }else{
                controls[prefijo+'_valor'].setValue('');
                controls[prefijo+'_valor_moneda_extranjera'].setValue('');
                if(controls[prefijo+'_base'] != undefined){
                    controls[prefijo+'_base'].setValue('');
                    controls[prefijo+'_base_moneda_extranjera'].setValue('');
                }
            }
        }

        return estadoCalculoME;
    }

    /**
     * Hace la conversión de la TRM que aplica para la moneda extranjera.
     *
     * @param {number} valor Valor al que se le aplicará la conversión
     * @return {*}  {number} Resultado de la conversión
     * @memberof TabFacturacionWebService
     */
    conversionTRM(valor: number): number{
        let aplicaCalculoME = this.aplicaCalculoMonedaExtranjera(false);
        if(aplicaCalculoME && this.trmValor !== '') {
            let conversion = 0;
            if(this.envioDianME === 'SI')
                conversion = Number(valor) * Number(this.trmValor);
            else
                conversion = Number(valor) / Number(this.trmValor);

            return this.redondearDecimales(conversion, 0, 'moneda-extranjera');
        }else{
            return 0;
        }
    }

    /**
     * Valida si el documento se crea con moneda extranjera y si los campos del código moneda extranjera, valor TRM y fecha TRM se hayan digitado.
     *
     * @param {boolean} [alerta=true] Aplica mensaje de alerta
     * @return {*}  {boolean}
     * @memberof TabFacturacionWebService
     */
    aplicaCalculoMonedaExtranjera(alerta: boolean = true): boolean {
        if((this.trmCodigoME !== undefined && this.trmCodigoME !== '') && ((this.trmValor == '') || (this.trmFecha === null || this.trmFecha  === ''))) {
            if(alerta) {
                swal({
                    html: 'En la sección moneda del documento debe digitar el valor y la fecha de la TRM.',
                    type: 'error',
                    confirmButtonClass: 'btn btn-danger',
                    confirmButtonText: 'Aceptar',
                    buttonsStyling: false,
                    allowOutsideClick: false
                }).catch(swal.noop);
            }

            return false;
        } else {
            return true;
        }
    }
}
