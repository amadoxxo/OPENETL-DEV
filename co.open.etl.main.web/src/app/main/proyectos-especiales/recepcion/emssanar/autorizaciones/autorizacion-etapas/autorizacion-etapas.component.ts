import { Component, ViewEncapsulation, OnInit, ViewChild, AfterViewChecked } from '@angular/core';
import { ActivatedRoute } from '@angular/router';
import { FormGroup, FormBuilder, Validators, AbstractControl } from '@angular/forms';
import * as moment from 'moment';
import { Auth } from '../../../../../../services/auth/auth.service';
import { AutorizacionesService } from '../../../../../../services/proyectos-especiales/recepcion/emssanar/autorizaciones.service';
import { BaseComponent } from '../../../../../core/base_component';
import { FiltrosGestionDocumentosComponent } from '../../../../../commons/filtros-gestion-documentos/filtros-gestion-documentos.component';

interface ParametrosListar {
    ofe_id              : number;
    gdo_identificacion ?: string[];
    gdo_clasificacion  ?: string;
    rfa_prefijo        ?: string;
    gdo_consecutivo     : string;
    gdo_fecha_desde     : string;
    gdo_fecha_hasta     : string;
}

@Component({
    selector: 'app-autorizacion-etapas',
    templateUrl: './autorizacion-etapas.component.html',
    styleUrls: ['./autorizacion-etapas.component.scss'],
    encapsulation: ViewEncapsulation.None
})
export class AutorizacionEtapasComponent extends BaseComponent implements OnInit, AfterViewChecked {
    @ViewChild('filtros', {static: true}) filtros: FiltrosGestionDocumentosComponent;

    // Variables globales
    public txtBreadCrum: string = '';
    public form: FormGroup;
    public etapa: AbstractControl;
    public observacion: AbstractControl;
    public gdo_id: AbstractControl;
    public loadingIndicator: boolean = true;
    public documento: any;

    /**
     * Creates an instance of AutorizacionEtapasComponent.
     * @param {Auth} _auth
     * @param {Router} _router
     * @param {ActivatedRoute} _activatedRoute
     * @param {AutorizacionesService} _autorizacionesService
     * @param {MatDialog} _matDialog
     * @param {FormBuilder} _formBuilder
     * @memberof AutorizacionEtapasComponent
     */
    constructor(
        public _auth : Auth,
        private _activatedRoute: ActivatedRoute,
        private _autorizacionesService: AutorizacionesService,
        private _formBuilder: FormBuilder
    ) {
        super();
        this.txtBreadCrum = this._activatedRoute.snapshot.data['breadcrum'];
    }

    /**
     * Inicializa las variables del componente.
     *
     * @private
     * @memberof AutorizacionEtapasComponent
     */
    private init(): void {
        this.form = this._formBuilder.group({
            observacion: this.requerido(),
            gdo_id: this.requerido(),
            etapa: this.requerido()
        });

        this.observacion = this.form.controls['observacion'];
        this.etapa = this.form.controls['etapa'];
        this.gdo_id = this.form.controls['gdo_id'];
    }

    /**
     * Ciclo OnInit del componente.
     *
     * @memberof AutorizacionEtapasComponent
     */
    ngOnInit(): void {
        this.init();
    }

    /**
     * Ciclo ngAfterViewChecked del componente.
     *
     * @memberof AutorizacionEtapasComponent
     */
    ngAfterViewChecked(): void {
        this.filtros.gdo_consecutivo.setValidators([ Validators.required ]);
        this.filtros.gdo_consecutivo.updateValueAndValidity();
    }

    /**
     * Retorna los parámetros de los filtros para ser enviados en la petición.
     *
     * @private
     * @return {ParametrosListar}
     * @memberof AutorizacionEtapasComponent
     */
    private getPayload(): ParametrosListar {
        const { 
            ofe_id,
            gdo_id,
            gdo_clasificacion,
            rfa_prefijo,
            gdo_consecutivo,
            gdo_fecha_desde,
            gdo_fecha_hasta
        } = this.filtros.form.getRawValue();
        
        const params: ParametrosListar = {
            ofe_id          : ofe_id,
            gdo_consecutivo : gdo_consecutivo,
            gdo_fecha_desde : String(moment(gdo_fecha_desde).format('YYYY-MM-DD')),
            gdo_fecha_hasta : String(moment(gdo_fecha_hasta).format('YYYY-MM-DD')),
        };

        if(gdo_id && gdo_id.length > 0)
            params.gdo_identificacion = gdo_id;

        if(gdo_clasificacion)
            params.gdo_clasificacion = gdo_clasificacion;

        if(rfa_prefijo)
            params.rfa_prefijo = rfa_prefijo;

        if(gdo_consecutivo)
            params.gdo_consecutivo = gdo_consecutivo;

        return params;
    }

    /**
     * Limpia los datos del formulario.
     *
     * @memberof AutorizacionEtapasComponent
     */
    public clearFormDocumento(): void {
        this.filtros.gdo_clasificacion.setValue('');
        this.filtros.rfa_prefijo.setValue('');
        this.filtros.gdo_consecutivo.setValue('');
        this.filtros.gdo_fecha_desde.setValue('');
        this.filtros.gdo_fecha_hasta.setValue('');

        this.observacion.setValue('');
        delete this.documento;
    }

    /**
     * Realiza la petición para obtener los documentos.
     *
     * @private
     * @memberof AutorizacionEtapasComponent
     */
    private loadDocumentos(): void {
        this.loading(true);
        this.loadingIndicator = true;
        const parameters = this.getPayload();
        this._autorizacionesService.consultarDocumento(parameters).subscribe({
            next: res => {
                this.loading(false);
                this.loadingIndicator = false;
                this.documento = res.data;

                this.gdo_id.setValue(this.documento.gdo_id);
                this.etapa.setValue(this.documento.numero_etapa_actual);
            },
            error: error => {
                this.loading(false);
                this.loadingIndicator = false;
                const texto_errores = this.parseError(error);

                this.showError('<h4>' + texto_errores + '</h4>', 'warning', 'Error al consultar el documento', 'Ok, entiendo', 'btn btn-warning');
            }
        });
    }

    /**
     * Realiza la petición de autorizar y devolver una etapa en un documento.
     *
     * @memberof AutorizacionEtapasComponent
     */
    public devolverDocumento(): void {

        const { 
            gdo_id,
            etapa,
            observacion,
        } = this.form.getRawValue();

        const params = {
            gdo_id,
            etapa,
            observacion
        }

        this.loading(true);
        this.loadingIndicator = true;
        this._autorizacionesService.autorizarEtapa(params).subscribe({
            next: res => {
                this.loading(false);
                this.loadingIndicator = false;
                this.showSuccess('Se envió a la anterior etapa el documento consultado', 'success', 'Anterior Etapa', 'Ok', 'btn btn-success');
                this.form.reset();
                this.loadDocumentos();
            },
            error: error => {
                this.loading(false);
                this.loadingIndicator = false;
                const texto_errores = this.parseError(error);

                this.showError('<h4>' + texto_errores + '</h4>', 'warning', 'Error al devolver el documento', 'Ok, entiendo', 'btn btn-warning');

            }
        })
    }

    /**
     * Efectua la carga de datos.
     *
     * @memberof AutorizacionEtapasComponent
     */
    public searchDocumentos(): void {
        this.loadDocumentos();
    }

}
