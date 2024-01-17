import {JwtHelperService} from '@auth0/angular-jwt';
import {Component, Input, OnInit} from '@angular/core';
import {BaseComponent} from '../../core/base_component';
import {AbstractControl, FormGroup, FormBuilder} from '@angular/forms';
import {CargasMasivasService} from '../../../services/commons/cargas_masivas.service';
import * as capitalize from 'lodash';

@Component({
    selector: 'app-cargas-masivas',
    templateUrl: './cargas-masivas.component.html',
    styleUrls: ['./cargas-masivas.component.scss']
})
export class CargasMasivasComponent extends BaseComponent implements OnInit {

    @Input() tipo: string;

    public form: FormGroup;
    public form_accion: AbstractControl;
    public nombreArchivoExcel: AbstractControl;
    public documentoManualExcel: AbstractControl;
    public texto = '';
    public subir = false;
    public archivoCargar: any[] = [];
    public arrAcciones: Array<Object> = [];
    public usuario            : any;
    public grupoTrabajoPlural : string;

    /**
     * Crea una instancia de CargasMasivasComponent.
     * 
     * @param {FormBuilder} fb
     * @param {JwtHelperService} _jwtHelperService
     * @param {CargasMasivasService} _cargasMasivasService
     * @memberof CargasMasivasComponent
     */
    constructor(
        private fb: FormBuilder,
        private _jwtHelperService: JwtHelperService,
        private _cargasMasivasService: CargasMasivasService
        ) {
        super();
        this.form = fb.group({
            'form_accion': [''],
            'nombreArchivoExcel': [''],
            'documentoManualExcel': [''],
        });

        this.form_accion = this.form.controls['form_accion'];
        this.nombreArchivoExcel = this.form.controls['nombreArchivoExcel'];
        this.documentoManualExcel = this.form.controls['documentoManualExcel'];
    }

    /**
     * ngOnInit de CargasMasivasComponent.
     *
     * @memberof CargasMasivasComponent
     */
    ngOnInit() {
        this.usuario            = this._jwtHelperService.decodeToken();
        this.grupoTrabajoPlural = capitalize.startCase(capitalize.toLower(this.usuario.grupos_trabajo.plural));

        let text;
        switch (this.tipo) {
            case 'USU':
                text = 'Usuarios';
                break;
            case 'ADQ':
                text = 'Adquirentes';
                break;
            case 'AUT':
                text = 'Autorizados';
                break;
            case 'RES':
                text = 'Responsables';
                break;
            case 'VEN':
                text = 'Vendedores';
                break;
            case 'PROV':
                text = 'Proveedores';
                break;
            case 'OFE':
                text = 'Oferentes';
                break;
            case 'RFA':
                text = 'Resoluciones de Facturación';
                break;
            case 'SPT':
                text = 'Software Proveedor Tecnológico';
                break;
            case 'AED':
                text = 'Autorizaciones Eventos DIAN';
                break;
            case 'USUECM':
                text = 'Usuarios openECM';
                break;
            case 'DMCARGOS':
                text = 'Cargos';
                break;
            case 'DMDESCUENTOS':
                text = 'Descuentos';
                break;
            case 'DMPRODUCTOS':
                text = 'Productos';
                break;
            case 'EMP':
                text = 'Empleadores';
                break;
            case 'TRA':
                text = 'Trabajadores';
                break;
            case 'GRUPOTRABAJO':
                text = this.grupoTrabajoPlural;
                break;
            case 'ASOCIARUSUARIO':
                text = 'Usuarios Asociados a ' + this.grupoTrabajoPlural;
                break;
            case 'ASOCIARPROVEEDOR':
                text = 'Proveedores Asociados a ' + this.grupoTrabajoPlural;
                break;
            case 'ACTOR':
                text = 'Radian Actores';
                break;
            default:
                break;
        }
        
        this.arrAcciones = [
            { id: 'descargar', nombre: 'Descargar Excel para Crear / Actualizar ' + text },
            { id: 'subir', nombre: 'Subir ' + text }
        ];

        if(this.tipo === 'ADQ') {
            this.arrAcciones.push(
                { id: 'descargar_excel_portal_clientes', nombre: 'Descargar Excel para Asignación Usuarios Portal Clientes' }
            );
            this.arrAcciones.push(
                { id: 'subir_excel_portal_clientes', nombre: 'Subir Usuarios Portal Clientes' }
            );
        }

        if(this.tipo === 'PROV') {
            this.arrAcciones.push(
                { id: 'descargar_excel_portal_proveedores', nombre: 'Descargar Excel para Asignación Usuarios Portal Proveedores' }
            );
            this.arrAcciones.push(
                { id: 'subir_excel_portal_proveedores', nombre: 'Subir Usuarios Portal Proveedores' }
            );
        }
    }

    /**
     * Metodo que se ejecuta al cambiar la selección de la acción.
     *
     * @param {*} event
     * @memberof CargasMasivasComponent
     */
    accionDownUp(event) {
        let accion = event;

        if (accion === 'descargar' || accion === 'descargar_excel_portal_proveedores' || accion === 'descargar_excel_portal_clientes') {
            this.texto = '<mat-icon color="#ffffff"></mat-icon> Generar';
            this.subir = false;
        } else if (accion === 'subir' || accion === 'subir_excel_portal_proveedores' || accion === 'subir_excel_portal_clientes') {
            this.texto = '<mat-icon color="#ffffff"></mat-icon> Subir';
            this.subir = true;
            this.archivoCargar = [];
            this.documentoManualExcel.setValue('');
        } else {
            this.archivoCargar = [];
            this.documentoManualExcel.setValue('');
            this.texto = '';
            this.subir = false;
        }
    }

    /**
     * Cambiar el valor de las variables al cambiar el archivo.
     *
     * @param {*} fileInput
     * @memberof CargasMasivasComponent
     */
    fileChangeEvent(fileInput: any) {
        if (fileInput.target.files) {
            this.archivoCargar = fileInput.target.files;
        } else {
            this.archivoCargar = [];
        }
    }
    
    /**
     * Permite la descarga de la interface.
     * 
     * @memberof CargasMasivasComponent
     */
    generarInterface() {
        this.loading(true);
        this._cargasMasivasService.generarInterface(this.tipo).subscribe(
            response => {
                this.loading(false);

                this.form.reset();
                this.form_accion.setValue('');
                this.texto = '';
                this.subir = false;
            },
            error => {
                this.loading(false);
            }
        );
    }

    /**
     * Gestiona la descarga de la interfaz de Excel para portal de clientes o proveedores
     *
     * @param string tipo
     * @memberof CargasMasivasComponent
     */
    generarInterfaceUsuariosPortales(tipo: string) {
        this.form.reset();
        this.form_accion.setValue('');
        this.loading(true);
        this._cargasMasivasService.generarInterfaceUsuariosPortales(tipo).subscribe(
            response => {
                this.loading(false);

                this.form.reset();
                this.form_accion.setValue('');
                this.texto = '';
                this.subir = false;
            },
            error => {
                this.loading(false);
            }
        );
    }

    /**
     * Gestiona el envío del Excel con la información.
     *
     * @private
     * @param {*} values
     * @memberof CargasMasivasComponent
     */
    private uploadExcel(values: any): void {
        let evento = values.form_accion;
        if (evento === 'descargar') {
            this.generarInterface();

            this.form.reset();
            this.form_accion.setValue('');

        } else if (evento === 'descargar_excel_portal_proveedores' || evento === 'descargar_excel_portal_clientes') {
            let tipo = evento.replace('descargar_excel_portal_', '');
            this.generarInterfaceUsuariosPortales(tipo);
        } else if (evento === 'subir') {
            if (this.archivoCargar.length < 1) {
                this.showError('<h3>Debe seleccionar un archivo.</h3>', 'warning', 'Ningún archivo seleccionado', 'Ok, entiendo', 'btn btn-warning');
            } else {
                this.loading(true);
                let archivoSeleccionado = this.archivoCargar[0];

                this._cargasMasivasService.cargar(archivoSeleccionado, this.tipo).subscribe(
                    response => {
                        this.loading(false);
                        this.archivoCargar = [];

                        this.form.reset();
                        this.form_accion.setValue('');
                        this.texto = '';
                        this.subir = false;
                        this.showSuccess('<h3> El archivo fue cargado y está siendo procesado en background </h3>' , 'success', 'Carga exitosa', 'Ok', 'btn btn-success');
                    }, error => {
                        this.loading(false);
                        let message = (error.message) ? error.message : '';
                        let errors = '';
                        if (Array.isArray(error.errors) && error.errors.length > 0) {
                            error.errors.forEach(strError => {
                                errors += '<li>' + strError + '</li>';
                            });
                        }
                        this.showError('<h3>' + message + '</h3>' + ((errors !== '') ? '<span style="text-align:left"><ul>' + errors + '</ul></span>' : ''), 'error', 'Error al cargar el archivo', 'OK', 'btn btn-danger');

                        this.nombreArchivoExcel.setValue('');
                        this.documentoManualExcel.setValue('');
                        this.form_accion.reset();
                    }
                );
            }
        } else if (evento === 'subir_excel_portal_proveedores' || evento === 'subir_excel_portal_clientes') {
            if (this.archivoCargar.length < 1) {
                this.showError('<h3>Debe seleccionar un archivo.</h3>', 'warning', 'Ningún archivo seleccionado', 'Ok, entiendo', 'btn btn-warning');
            } else {
                this.loading(true);
                let archivoSeleccionado = this.archivoCargar[0];
                let tipo = evento.replace('subir_excel_portal_', '');

                this._cargasMasivasService.cargarUsuariosPortales(archivoSeleccionado, tipo).subscribe(
                    response => {
                        this.loading(false);
                        this.archivoCargar = [];

                        this.form.reset();
                        this.form_accion.setValue('');
                        this.texto = '';
                        this.subir = false;
                        this.showSuccess('<h3> El archivo fue cargado y está siendo procesado en background </h3>' , 'success', 'Carga exitosa', 'Ok', 'btn btn-success');
                    }, error => {
                        this.loading(false);
                        let message = (error.message) ? error.message : '';
                        let errors = '';
                        if (Array.isArray(error.errors) && error.errors.length > 0) {
                            error.errors.forEach(strError => {
                                errors += '<li>' + strError + '</li>';
                            });
                        }
                        this.showError('<h3>' + message + '</h3>' + ((errors !== '') ? '<span style="text-align:left"><ul>' + errors + '</ul></span>' : ''), 'error', 'Error al cargar el archivo', 'OK', 'btn btn-danger');

                        this.nombreArchivoExcel.setValue('');
                        this.documentoManualExcel.setValue('');
                        this.form_accion.reset();
                    }
                );
            }
        }
    }
}
