import {MAT_DIALOG_DATA, MatDialogRef} from '@angular/material/dialog';
import {BaseComponentView} from '../../../core/base_component_view';
import {Component, Inject, OnInit, Input, AfterViewInit} from '@angular/core';
import {AbstractControl, FormBuilder, FormControl, FormGroup, Validators} from '@angular/forms';
import {ConfiguracionService} from '../../../../services/configuracion/configuracion.service';

@Component({
    selector: 'app-xpath-documentos-electronicos-gestionar',
    templateUrl: './xpath-documentos-electronicos-gestionar.component.html',
    styleUrls: ['./xpath-documentos-electronicos-gestionar.component.scss'],
    providers: []
})
export class XPathDocumentosElectronicosGestionarComponent extends BaseComponentView implements OnInit, AfterViewInit{

    @Input() ver: boolean;

    // Usuario en línea
    public usuario  : any;
    public objMagic = {};
    public ofes     : Array<any> = [];

    form              : FormGroup;
    pathId            : string;
    ofeIdeToGet       : string;
    action            : string;
    parent            : any;
    dataModal         : any;

    public formErrors               : any;
    public ofe_identificacion       : AbstractControl;
    public xde_xpath                : AbstractControl;
    public xde_descripcion          : AbstractControl;
    public xde_aplica_para          : AbstractControl;
    public estado                   : AbstractControl;
    public tituloModulo             : string = '';
    public tipoConfiguracion        : string;

    public aplicaParaSeleccionado;
    public aplicaParaItems = [
        {
            xde_aplica_para : 'FC',
            xde_aplica_para_descripcion : 'FC - Factura de Venta'
        },
        {
            xde_aplica_para : 'NC',
            xde_aplica_para_descripcion : 'NC - Nota Crédito'
        },
        {
            xde_aplica_para : 'ND',
            xde_aplica_para_descripcion : 'ND - Nota Débito'
        }
    ];

    /**
     * Crea una instancia de XPathDocumentosElectronicosGestionarComponent.
     * 
     * @param {FormBuilder} formBuilder
     * @param {MatDialogRef<XPathDocumentosElectronicosGestionarComponent>} modalRef
     * @param {*} data
     * @param {ConfiguracionService} _configuracionService
     * @memberof XPathDocumentosElectronicosGestionarComponent
     */
    constructor(
        private formBuilder           : FormBuilder,
        private modalRef              : MatDialogRef<XPathDocumentosElectronicosGestionarComponent>,
        @Inject(MAT_DIALOG_DATA) data,
        private _configuracionService : ConfiguracionService
    ) {
        super();
        this.initForm();
        this.buildErrorsObjetc();
        this.parent                        = data.parent;
        this.action                        = data.action;
        this.tipoConfiguracion             = data.tipoConfiguracion;
        this.pathId                        = data.xde_id;
        this.ofeIdeToGet                   = data.ofe_identificacion;
        this.ofes                          = data.ofes;
        this.dataModal                     = data;
    }

    /**
     * ngOnInit de XPathDocumentosElectronicosGestionarComponent.
     *
     * @memberof XPathDocumentosElectronicosGestionarComponent
     */
    ngOnInit() {
        if (this.action === "view") {
            this.ver = true;
        }

        if(this.tipoConfiguracion === 'personalizados') {
            let ofeFormControl: FormControl = new FormControl('', Validators.required);
            this.form.addControl('ofe_identificacion', ofeFormControl);
            this.ofe_identificacion = this.form.controls['ofe_identificacion'];
            this.tituloModulo = 'XPath Documentos Electrónicos Personalizados';
        } else {
            this.tituloModulo = 'XPath Documentos Electrónicos Estándar';
        }

        if(this.action === 'edit') {
            let controlEstado: FormControl = new FormControl('', Validators.required);
            this.form.addControl('estado', controlEstado);
            this.estado = this.form.controls['estado'];
            this.setDataRegistro(this.dataModal.item);
        } else if (this.action === 'view') {
            this.disableFormControl(this.xde_xpath, this.xde_descripcion, this.xde_aplica_para);
            if(this.tipoConfiguracion === 'personalizados')
                this.disableFormControl(this.ofe_identificacion);

            this.setDataRegistro(this.dataModal.item);
        }
    }

    /**
     * ngAfterViewInit de XPathDocumentosElectronicosGestionarComponent.
     *
     * @memberof XPathDocumentosElectronicosGestionarComponent
     */
    ngAfterViewInit() {
        if(this.action !== 'new' && this.tipoConfiguracion === 'personalizados')
            this.ofe_identificacion.setValue(this.ofeIdeToGet);

        if(this.ofes.length === 1 && this.tipoConfiguracion === 'personalizados')
            this.ofe_identificacion.setValue(this.ofes[0].ofe_identificacion);
    }

    /**
     * Inicializando el formulario.
     * 
     * @memberof XPathDocumentosElectronicosGestionarComponent
     */
    private initForm(): void {
        this.form = this.formBuilder.group({
            'xde_xpath'          : this.requerido(),
            'xde_descripcion'    : this.requeridoMaxlong(255),
            'xde_aplica_para'    : this.requerido(),
        });

        this.xde_xpath          = this.form.controls['xde_xpath'];
        this.xde_descripcion    = this.form.controls['xde_descripcion'];
        this.xde_aplica_para    = this.form.controls['xde_aplica_para'];
    }

    /**
     * Construye un objeto para gestionar los errores en el formulario.
     * 
     * @memberof XPathDocumentosElectronicosGestionarComponent
     */
    public buildErrorsObjetc() {
        this.formErrors = {
            ofe_identificacion: {
                required: 'El OFE / Receptor es requerido!'
            },
            xde_xpath: {
                required: 'El XPath es requerido!'
            },
            xde_descripcion: {
                required: 'La descripción es requerida!',
                maxLength: 'Ha introducido más de 255 caracteres'
            },
            xde_aplica_para: {
                required: 'El Aplica Para es requerido!'
            },
        };
    }

    /**
     * Se encarga de setear los datos del registro de un XPath que se ha seleccionado en el tracking.
     *
     * @param {*} data Información del registro seleccionado
     * @memberof XPathDocumentosElectronicosGestionarComponent
     */
    public setDataRegistro(data) {
        if (this.action === 'edit') {
            if (data.estado === 'ACTIVO')
                this.estado.setValue('ACTIVO');
            else
                this.estado.setValue('INACTIVO');
        }
        this.aplicaParaSeleccionado = data.xde_aplica_para.split(',');
        this.xde_xpath.setValue(data.xde_xpath);
        this.xde_descripcion.setValue(data.xde_descripcion);

        this.objMagic['fecha_creacion'] = data.fecha_creacion;
        this.objMagic['fecha_modificacion'] = data.fecha_modificacion;
        this.objMagic['estado'] = data.estado;
    }

    /**
     * Cierra la ventana modal de XPath.
     * 
     * @memberof XPathDocumentosElectronicosGestionarComponent
     */
    public closeModal(reload): void {
        this.modalRef.close();
        if(reload)
            this.parent.getData();
    }

    /**
     * Crea o actualiza un nuevo XPath.
     * 
     * @param values
     * @memberof XPathDocumentosElectronicosGestionarComponent
     */
    public savePathConfiguracion(values) {
        let formWithAction: any = values;
        this.loading(true);
        if (this.form.valid) {
            if (formWithAction.xde_aplica_para !== undefined && formWithAction.xde_aplica_para !== '') {
                formWithAction.xde_aplica_para = formWithAction.xde_aplica_para.join(',');
            }
            if (this.action === 'edit') {
                this._configuracionService.update(formWithAction, this.pathId).subscribe(
                    response => {
                        this.loading(false);
                        this.showTimerAlert('<strong>Registro actualizado correctamente.</strong>', 'success', 'center', 2000);
                        this.closeModal(true);
                    },
                    error => {
                        this.loading(false);
                        this.showError('<h4>' + error.errors + '</h4>', 'error', 'Error al actualizar el registro', 'Ok', 'btn btn-danger');
                    }
                );
            } else if(this.action === 'new') {
                this._configuracionService.create(formWithAction).subscribe(
                    response => {
                        this.loading(false);
                        this.showTimerAlert('<strong>Registro creado correctamente.</strong>', 'success', 'center', 2000);
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
}
