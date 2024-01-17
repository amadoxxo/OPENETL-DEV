import {Component, ViewChild} from '@angular/core';
import {BaseComponentList} from '../../../core/base_component_list';
import {FormBuilder} from '@angular/forms';
import {Router} from '@angular/router';
import swal from 'sweetalert2';
import {Auth} from '../../../../services/auth/auth.service';
import {FuseConfigService} from '../../../../../@fuse/services/config.service';
import {CambiarClaveUsuarioComponent} from '../cambiar_clave_usuario/cambiar_clave_usuario.component';
import {UsuariosService} from '../../../../services/sistema/usuarios.service';
import {DatatableComponent} from '@swimlane/ngx-datatable';
import { MatDialog, MatDialogConfig } from '@angular/material/dialog';

@Component({
    selector: 'listar-usuarios',
    templateUrl: './usuarios.component.html',
    styleUrls: ['./usuarios.component.scss']
})

export class UsuariosComponent extends BaseComponentList {
    public selectedValue: any;
    public estadoActual: any;
    public loadingIndicator: any;
    public companias: any = [];
    public aclsUsuario: any;
    private modalCambioClave: any;

    public accionesBloque = [
        {id: 'cambiarEstado', itemName: 'Cambiar Estado'}
        ];

    @ViewChild('selectAcciones') selectAccionesBloque;
    @ViewChild('tracking') tracking: DatatableComponent;

    /**
     * Constructor
     * @param _fuseConfigService
     * @param _formBuilder
     * @param _router
     * @param _auth
     * @param modal
     * @param _usuariosService
     */
    constructor(private _fuseConfigService: FuseConfigService,
                private _formBuilder: FormBuilder,
                private _router: Router,
                public _auth: Auth,
                private modal: MatDialog,
                private _usuariosService: UsuariosService) {
        super();
        this.init();
    }

    /**
     * Se encarga de inicializar los parámetros para la búsqueda de usuarios.
     * 
     */
    private init() {
        this.rows = [];
        this.initDataSort('usu_id');
        this.loadingIndicator = true;
        this.aclsUsuario = this._auth.getAcls();
        this.loadUsuarios();
    }

   /**
     * Sobreescribe los parámetros de búsqueda inline - (Get).
     * 
     */
    getSearchParametersInline(excel = false): string {
        let query = 'start=' + this.start + '&' +
        'length=' + this.length + '&' +
        'buscar=' + this.buscar + '&' +
        'columnaOrden=' + this.columnaOrden + '&' +
        'ordenDireccion=' + this.ordenDireccion;
        if (excel)
            query += '&excel=true';

        return query;
    }

    /**
     * Se encarga de traer la data de los diferentes usuarios existentes.
     * 
     */
    public loadUsuarios(): void {
        this.loading(true);
        this._usuariosService.listarUsuarios(this.getSearchParametersInline()).subscribe(
            res => {
                this.loading(false);
                this.rows = res.data;
                this.totalElements = res.filtrados;
                this.loadingIndicator = false;
                this.totalShow = this.length !== -1 ? this.length : this.totalElements;
            },
            error => {
                this.loading(false);
                const texto_errores = this.parseError(error);
                this.loadingIndicator = false;
                this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al cargar los Usuarios', 'OK', 'btn btn-warning', '/dashboard', this._router);
            });
    }

    /**
     * Método utilizado por los checkbox en los listados.
     * 
     * @param evt
     */
    onCheckboxChangeFn(evt: any) {

    }

    /**
     * Gestiona el evento de paginación de la grid.
     * 
     * @param $evt
     */
    public onPage($evt) {
        this.start = $evt.offset * this.length;
        this.getData();
    }

    /**
     * Sobreescritura del método onSort.
     * 
     * @param $evt
     */
    public onSort($evt) {
        let column = $evt.column.prop;
        this.selected = [];
        switch (column) {
            case 'usu_nombre':
                this.columnaOrden = 'usu_nombre';
                break;
            case 'usu_identificacion':
                this.columnaOrden = 'usu_identificacion';
                break;
            case 'usu_email':
                this.columnaOrden = 'usu_email';
                break;
            case 'usu_type':
                this.columnaOrden = 'usu_type';
                break;
            case 'estado':
                this.columnaOrden = 'estado';
                break;
            default:
                break;
        }
        this.start = 0;
        this.ordenDireccion = $evt.newValue.toUpperCase();
        this.getData();
    }

    /**
     * Efectua la carga de datos.
     * 
     */
    public getData() {
        this.loadingIndicator = true;
        this.loadUsuarios();
    }

    /**
     * Cambia el numero de items a mostrar y refresca la grid.
     * 
     * @param evt
     */
    paginar(evt) {
        this.length = evt;
        this.getData();
    }

    onChangeSelect() {
    }

    /**
     * Evento de selectall del checkbox primario de la grid.
     * 
     * @param selected
     */
    onSelect({selected}) {
        this.selected.splice(0, this.selected.length);
        this.selected.push(...selected);
    }

    /**
     * Evento de búsqueda rápida.
     * 
     */
    searchinline() {
        this.start = 0;
        this.tracking.offset = 0;
        this.getData();
    }

    /**
     * Apertura una ventana modal para crear o editar un tipo de documento.
     * 
     * @param usuario
     */
    public openModalCambioClave(usu_id = null): void {
        const modalConfig = new MatDialogConfig();
        modalConfig.autoFocus = true;
        modalConfig.width = '600px';
        modalConfig.data = {
            parent: this,
            usu_id: usu_id
        };
        this.modalCambioClave = this.modal.open(CambiarClaveUsuarioComponent, modalConfig);
    }

    /**
     * Se encarga de cerrar y eliminar la referencia del modal para visualizar el cambio de clave de un usuario.
     * 
     */
    public closeModalCambioClave(): void {
        if (this.modalCambioClave) {
            this.modalCambioClave.close();
            this.modalCambioClave = null;
        }
    }

     /**
     * Gestiona el cambio de estado de los Usuarios seleccionados en la grid.
     * 
     */
    public cambiarEstado(accion) {
        if (accion === 'cambiarEstado') {
            if (this.selected.length == 0){
                this.showError('<h3>Debe seleccionar al menos un Usuario para cambiar su estado</h3>', 'warning', 'Cambio de Estado', 'Ok, entiendo',
                    'btn btn-warning');
                this.selectAccionesBloque.value = 'Acciones en Bloque';
            } else {
                const ids = [];
                let usuariosIntegracion = false;
                this.selected.forEach(usuario => {
                    if(usuario.usu_type === 'INTEGRACION' && !usuariosIntegracion)
                        usuariosIntegracion = true;
                    else
                        ids.push(usuario.usu_id);
                });

                if(usuariosIntegracion) {
                    this.showError('<h3>Seleccionó usuarios del tipo [INTEGRACION] a los cuales no se puede cambiar su estado</h3>', 'warning', 'Cambio de Estado', 'Ok, entiendo',
                    'btn btn-warning');
                    this.selectAccionesBloque.value = 'Acciones en Bloque';
                } else {
                    swal({
                        html: '¿Está seguro de cambiar el estado de los Usuarios seleccionados?',
                        type: 'warning',
                        showCancelButton: true,
                        confirmButtonClass: 'btn btn-success',
                        confirmButtonText: 'Aceptar',
                        cancelButtonText: 'Cancelar',
                        cancelButtonClass: 'btn btn-danger',
                        buttonsStyling: false,
                        allowOutsideClick: false
                    })
                    .then((result) => {
                        if (result.value) {
                            this.loading(true);
                            this._usuariosService.cambiarEstado(ids).subscribe(
                                response => {
                                    this.loadUsuarios();
                                    this.showSuccess('<h3>Los Usuarios han cambiado de estado</h3>', 'success', 'Cambio de estado exitoso', 'OK', 'btn btn-success');
                                },
                                error => {
                                    this.loading(false);
                                    const texto_errores = this.parseError(error);
                                    this.loadingIndicator = false;
                                    this.showError('<h4>' + texto_errores + '</h4>', 'error', 'Error al cambiar de estado', 'OK', 'btn btn-danger');
                                }
                            );
                        }
                    }).catch(swal.noop);
                }
            }
        } 
        this.selected = [];
        this.selectAccionesBloque.value = 'Acciones en Bloque';
    }

    cambiarEstadoIndividual(usu_id){
        this.selected.push({'usu_id': usu_id});
        this.cambiarEstado('cambiarEstado');
    }

    /**
     * Descarga el los usuarios filtrados en un archivos de excel
     */
    descargarUsuarios() {
        this.loading(true);
        this._usuariosService.descargarExcelListadoUsuarios(this.getSearchParametersInline(true)).subscribe(
            response => {
                this.loading(false);
            },
            (error) => {
                this.loading(false);
                this.showError('<h3>Error en descarga</h3><p>Verifique que la consulta tenga resultados.</p>', 'error', 'Error al descargar excel de Usuarios', 'OK', 'btn btn-danger');
            }
        );
    }
}

