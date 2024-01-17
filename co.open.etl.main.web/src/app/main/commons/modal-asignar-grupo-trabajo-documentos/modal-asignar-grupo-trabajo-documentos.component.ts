import {Component, Inject, OnInit} from '@angular/core';
import {MAT_DIALOG_DATA, MatDialogRef} from '@angular/material/dialog';
import {AbstractControl, FormBuilder, FormGroup, Validators} from '@angular/forms';
import {ConfiguracionService} from 'app/services/configuracion/configuracion.service';
import {DocumentosRecibidosService} from '../../../services/recepcion/documentos_recibidos.service';
import {BaseComponentView} from 'app/main/core/base_component_view';

@Component({
    selector: 'app-modal-asignar-grupo-trabajo-documentos',
    templateUrl: './modal-asignar-grupo-trabajo-documentos.component.html',
    styleUrls: ['./modal-asignar-grupo-trabajo-documentos.component.scss']
})
export class ModalAsignarGrupoTrabajoDocumentosComponent extends BaseComponentView implements OnInit{
    public form           : FormGroup;
    public gtr_id         : AbstractControl;
    public parent         : any;
    public pro_id         : any;
    public cdo_ids        : any;
    public documentos     : any;
    public _grupo_trabajo : any;
    public textosDinamicos: any;
    public formErrors     : any;

    public arrGruposTrabajoProveedor = [];
    public listaDocumentos = '';

    /**
     * Constructor
     * @param formBuilder
     * @param modalRef
     * @param data
     * @param _configuracionService
     */
    constructor(
        private formBuilder: FormBuilder,
        private modalRef: MatDialogRef<ModalAsignarGrupoTrabajoDocumentosComponent>,
        @Inject(MAT_DIALOG_DATA) data,
        private _configuracionService: ConfiguracionService,
        private _documentosRecibidosService: DocumentosRecibidosService
    ) {
            super();
            this.parent         = data.parent;
            this.pro_id         = data.documentos.pro_id;
            this.cdo_ids        = data.documentos.cdo_ids;
            this.documentos     = data.documentos.documentos_asignar_grupo_trabajo;
            this._grupo_trabajo = data.documentos._grupo_trabajo;

            this.initForm();
            this.buildErrorsObjetc();
    }

    ngOnInit() {
        this.loadGruposTrabajoProveedor();
        this.textosDinamicos = 'Asignar ' + this._grupo_trabajo;  
    }

    /**
     * Inicializando el formulario.
     * 
     */
    private initForm(): void {
        this.form = this.formBuilder.group({
            'gtr_id': ['', Validators.compose([Validators.required])]
        });

        this.gtr_id = this.form.controls['gtr_id'];
    }

    /**
     * Construye un objeto para gestionar los errores en el formulario.
     * 
     */
    public buildErrorsObjetc() {
        this.formErrors = {
            gtr_id: {
                required: 'Debe seleccionar el (la) ' + this._grupo_trabajo
            }
        };   
    }

    /**
     * Carga el listado de conceptos de rechazo.
     * 
     * @memberof ModalAsignarGrupoTrabajoDocumentosComponent
     * @return void
     */
    public loadGruposTrabajoProveedor(): void {
        this.loading(true);
        this._configuracionService.listarGruposTrabajoProveedor('pro_id=' + this.pro_id).subscribe(
            res => {
                this.loading(false);
                if (res) {
                    this.arrGruposTrabajoProveedor = res.data.grupos_trabajo_proveedor;
                }
            },
            error => {
                this.loading(false);
                this.mostrarErrores(error, 'Error al cargar los (las) ' + this._grupo_trabajo);
            }
        );
    }

    /**
     * Cierra la ventana modal.
     * 
     */
    public closeModal(reload): void {
        this.modalRef.close();
        if(reload)
            this.parent.getData();
    }

    /**
     * Procesa el rechazo de documentos.
     * 
     */
    public asignarGrupoTrabajoDocumentos() {
        if (this.form.valid) {
            this.loading(true);
            this._documentosRecibidosService.asignarGrupoTrabajo(this.cdo_ids, this.gtr_id.value, this.pro_id, this._grupo_trabajo).subscribe(
                response => {
                    this.loading(false);
                    this.showSuccess(response.message, 'success', 'Asignar ' + this._grupo_trabajo, 'Ok', 'btn btn-success');
                    this.closeModal(true);
                },
                error => {
                    this.loading(false);
                    this.mostrarErrores(error, 'Error al intentar la Asignación de ' + this._grupo_trabajo);
                }
            );
        }
    }
}
