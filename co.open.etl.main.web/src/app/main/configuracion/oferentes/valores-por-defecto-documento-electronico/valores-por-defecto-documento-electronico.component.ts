import {MatAccordion} from '@angular/material/expansion';
import {MatDialog, MatDialogConfig} from '@angular/material/dialog';
import {ActivatedRoute, Router} from '@angular/router';
import {Auth} from '../../../../services/auth/auth.service';
import { BaseComponentList } from 'app/main/core/base_component_list';
import {Component, OnInit, ViewChild, AfterViewInit} from '@angular/core';
import {ConfiguracionService} from '../../../../services/configuracion/configuracion.service';
import {TrackingColumnInterface, TrackingInterface, TrackingOptionsInterface} from '../../../commons/open-tracking/tracking-interface';
import {ValoresPorDefectoDocumentoElectronicoGestionarComponent} from '../valores-por-defecto-documento-electronico-gestionar/valores-por-defecto-documento-electronico-gestionar.component';

@Component({
    selector: 'app-valores-por-defecto-documento-electronico',
    templateUrl: './valores-por-defecto-documento-electronico.component.html',
    styleUrls: ['./valores-por-defecto-documento-electronico.component.scss']
})
export class ValoresPorDefectoDocumentoElectronicoComponent extends BaseComponentList implements OnInit, AfterViewInit, TrackingInterface {
    @ViewChild('acordion', {static: false}) acordion: MatAccordion;

    public aclsUsuario         : any;
    public mostrarFormulario   : boolean = false;
    public datosFacturacionWeb : any[] = [];
    public titulo              : string;
    public ofe_id              : any = null;
    public _ofe_identificacion : any;
    public _razon_social       : any;

    public trackingInterface: TrackingInterface;
    public trackingOpciones : TrackingOptionsInterface = {
        editButton: true, 
        showButton: true
    };

    public columns: TrackingColumnInterface[] = [
        {name: 'Descripción', prop: 'descripcion', sorteable: false, width: 250},
        {name: 'Código / Valor', prop: 'valor', sorteable: false, width: 120},
        {name: 'Descripción Código Valor', prop: 'valor_descripcion', sorteable: false, width: 250}
    ];

    private modalValorPorDefecto : any;

    constructor(
        public _auth: Auth,
        private _router: Router,
        private _route: ActivatedRoute,
        private _configuracionService: ConfiguracionService,
        private modal: MatDialog,
    ) {
        super();
        this.titulo = 'Configuración Documento Electrónico';
        this._configuracionService.setSlug = 'ofe';
        this.trackingInterface = this;
    }

    ngOnInit() {
        this._ofe_identificacion = this._route.snapshot.params['ofe_identificacion'];
        this.aclsUsuario = this._auth.getAcls();
        if(!this._auth.existeRol(this.aclsUsuario.roles, 'superadmin') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ValoresDefectoDocumento)) {
            this.showError('<h4>No tiene los permisos necesarios para esta acción</h4>', 'error', 'Configuración Documento Electrónico', 'Ok', 'btn btn-danger', 'configuracion/oferentes', this._router);
        } else {
            this.mostrarFormulario = true;
            this.loadOfe();
        }
    }

    /**
     * Vista construida
     */
    ngAfterViewInit() {
        if(this.mostrarFormulario) this.acordion.openAll();
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

                if(res.data.ofe_datos_documentos_manuales) {
                    this.datosFacturacionWeb = res.data.ofe_datos_documentos_manuales;
                    this.totalElements = this.datosFacturacionWeb.length;
                    this.totalShow = this.length !== -1 ? this.length : this.totalElements;

                    this.loadingIndicator = false;
                }
            },
            error => {
                this.loading(false);
                const texto_errores = this.parseError(error);
                this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al cargar la información de valores por defecto en documento electrónico', 'Ok', 'btn btn-danger', 'configuracion/oferentes', this._router);
            }
        );
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
     * Cambia la cantidad de registros del paginado y recarga el listado.
     * 
     */
    onChangeSizePage(size: number) {
        if(size !== -1)
            this.totalShow = this.length = size;
        else
            this.totalShow = this.length = this.datosFacturacionWeb.length;
    }

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
}
