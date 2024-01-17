import {Component, OnInit} from '@angular/core';
import {BaseComponent} from 'app/main/core/base_component';
import {AbstractControl, FormGroup, FormBuilder, FormArray} from '@angular/forms';
import {Auth} from '../../../../services/auth/auth.service';
import {Router, ActivatedRoute} from '@angular/router';
import {NgxFileDropEntry, FileSystemFileEntry} from 'ngx-file-drop';
import {ModalDocumentosAnexosComponent} from '../../../modals/modal-documentos-anexos/modal-documentos-anexos.component';
import {CommonsService} from '../../../../services/commons/commons.service';
import {MatDialog, MatDialogConfig} from "@angular/material/dialog";
import {DocumentosRecibidosService} from '../../../../services/recepcion/documentos_recibidos.service';
import {CorreosRecibidosService} from '../../../../services/recepcion/correos_recibidos.service';
import {JwtHelperService} from '@auth0/angular-jwt';
import * as moment from 'moment';
import swal from 'sweetalert2';

@Component({
    selector: 'app-cargar-anexos',
    templateUrl: './cargar-anexos.component.html',
    styleUrls: ['./cargar-anexos.component.scss']
})
export class CargarAnexosComponent extends BaseComponent implements OnInit{

    public form                   : FormGroup;
    public ofe_id                 : AbstractControl;
    public pro_id                 : AbstractControl;
    public cdo_clasificacion      : AbstractControl;
    public cdo_fecha_desde        : AbstractControl;
    public cdo_fecha_hasta        : AbstractControl;
    public cdo_consecutivo        : AbstractControl;
    public rfa_prefijo            : AbstractControl;

    public formDocumentosAnexos   : FormGroup;
    public aclsUsuario            : any;
    public arrDocumentosAnexos    : any[] = [];
    public arrDescripciones       : any[] = [];
    public documentos             : any;
    public seleccionDocumento     : any;
    public ofes                   : Array<any> = [];
    public ofeID                  : number;
    private modalDocumentosAnexos : any;
    public correoId               : number = 0;
    public correoSubject          : string = '';
    public archivoCargar          : any[] = [];
    public download               : true;
    public selectedOfeId: any;
    public selectedOption: any;
    public selectedOptionFitac: any;

    public mimeTypes = [
        'image/tiff',
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/bmp',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/excel',
        'application/vnd.ms-excel',
        'application/x-excel',
        'application/x-msexcel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/zip',
        'application/x-rar-compressed',
        'application/octet-stream'
    ];

    public arrTipoDoc: Array<Object> = [
        {id: 'FC', name: 'FC'},
        {id: 'NC', name: 'NC'},
        {id: 'ND', name: 'ND'}
    ];

    /**
     * Crea una instancia de CargarAnexosComponent.
     * 
     * @param {Auth} _auth
     * @param {Router} _router
     * @param {ActivatedRoute} _route
     * @param {FormBuilder} fb
     * @param {MatDialog} modal
     * @param {CommonsService} _commonsService
     * @param {JwtHelperService} _jwtHelperService
     * @param {CorreosRecibidosService} _correosRecibidosService
     * @param {DocumentosRecibidosService} _documentosRecibidoService
     * @memberof CargarAnexosComponent
     */
    constructor(
        public _auth: Auth, 
        private _router: Router,
        private _route: ActivatedRoute,
        private fb: FormBuilder,
        private modal: MatDialog,
        private _commonsService: CommonsService,
        private _jwtHelperService: JwtHelperService,
        private _correosRecibidosService: CorreosRecibidosService,
        private _documentosRecibidoService: DocumentosRecibidosService
    ) {
        super();
        this.aclsUsuario = this._auth.getAcls();
        this.init();
    }

    /**
     * Inicialización del formulario.
     *
     * @private
     * @memberof CargarAnexosComponent
     */
    private init(){
        this.form = this.fb.group({
            ofe_id: this.requerido(),
            pro_id: [''],
            cdo_clasificacion: [''],
            cdo_fecha_desde: this.requerido(),
            cdo_fecha_hasta: this.requerido(),
            cdo_consecutivo: this.requerido(),
            rfa_prefijo: ['']
        });
        
        this.ofe_id = this.form.controls['ofe_id'];
        this.pro_id = this.form.controls['pro_id'];
        this.cdo_clasificacion = this.form.controls['cdo_clasificacion'];
        this.cdo_fecha_desde = this.form.controls['cdo_fecha_desde'];
        this.cdo_fecha_hasta = this.form.controls['cdo_fecha_hasta'];
        this.cdo_consecutivo = this.form.controls['cdo_consecutivo'];
        this.rfa_prefijo = this.form.controls['rfa_prefijo'];

        // Formulario para cargue de documentos anexos
        this.formDocumentosAnexos = this.fb.group({
            'camposDocumentosAnexos': this.fb.array([]),
        });
    }

    /**
     * ngOnInit de CargarAnexosComponent.
     *
     * @memberof CargarAnexosComponent
     */
    ngOnInit() {
        this.pro_id.disable();
        this.correoId = (this._route.snapshot.params) ? this._route.snapshot.params.epm_id : 0;
        this.loadInformacion();
    }

    /**
     * Ejecuta las peticiones asincronas para obtener la información necesaria.
     *
     * @memberof CargarAnexosComponent
     */
    async loadInformacion(){
        this.loading(true);
        await this.cargarOfes();
        if(this.correoId)
            await this.cargarCorreo();
        this.loading(false);
    }

    /**
     * Consulta la información del correo cuando viene desde el tracking.
     *
     * @private
     * @memberof CargarAnexosComponent
     */
    async cargarCorreo() {
        let registro = {
            epm_id: this.correoId
        };
        let peticion = await this._correosRecibidosService.obtenerCorreoRecibido(registro).toPromise()
            .then(resolve => {
                this.correoSubject = resolve.data.epm_subject;
            })
            .catch(error => {
                let texto_errores = (error.message) ? error.message : this.parseError(error);
                this.loading(false);
                this.showError(texto_errores, 'error', 'Error al consultar el correo', '0k, entiendo', 'btn btn-danger', '/recepcion/correos-recibidos', this._router);
            });
    }

    /**
     * Carga los OFEs en el select de emisores.
     * 
     * @memberof CargarAnexosComponent
     */
    async cargarOfes() {
        let peticion = await this._commonsService.getDataInitForBuild('tat=false').toPromise()
            .then( resolve => {
                this.ofes = [];
                resolve.data.ofes.forEach(ofe => {
                    if(ofe.ofe_emision === 'SI') {
                        ofe.ofe_identificacion_ofe_razon_social = ofe.ofe_identificacion + ' - ' + ofe.ofe_razon_social;
                        this.ofes.push(ofe);
                    }
                });
            })
            .catch(error => {
                let texto_errores = this.parseError(error);
                this.loading(false);
                this.showError(texto_errores, 'error', 'Error al cargar los OFEs', 'Ok', 'btn btn-danger');
            });
    }

    /**
     * Recarga la consulta de documentos
     * 
     * @memberof CargarAnexosComponent
     */
    recargarConsulta() {
        this.searchDocumentos(this.form.value);
    }

    /**
     * Obtiene los documentos indicados en el formulario de búsqueda.
     *
     * @param {*} values
     * @memberof CargarAnexosComponent
     */
    searchDocumentos(values){
        this.clearFormDocumentos();
        values.cdo_fecha_desde = moment(this.cdo_fecha_desde.value).format('YYYY-MM-DD'),
        values.cdo_fecha_hasta = moment(this.cdo_fecha_hasta.value).format('YYYY-MM-DD')

        this.loading(true);
        this.seleccionDocumento = undefined;
        this._documentosRecibidoService.encontrarDocumentoAnexo(values)
        .subscribe(
            data => {
                this.loading(false);
                this.documentos = data;
                if (data.data.length <= 0)
                    this.showError('<h3>No se encontraron coincidencias</h3>', 'warning', 'Búsqueda de Documentos', 'Ok', 'btn btn-warning');
            },
            error => {
                this.loading(false);
                this.showError('<h5>Se generó un error al realizar la búsqueda</h5>', 'error', 'Error al buscar los documentos', 'Ok', 'btn btn-danger');
        });
    }

    /**
     * Permite agregar un nuevo documento al formulario de carga.
     *
     * @memberof CargarAnexosComponent
     */
    public agregarDocumentoAnexo() {
        const CTRL = <FormArray>this.formDocumentosAnexos.controls['camposDocumentosAnexos'];
        if (CTRL.length < 10)
            CTRL.push(this.documentoAnexo());
        else
            this.showError('<h3>Puede cargar un máximo de 10 documentos de anexos</h3>', 'error', 'Error al agregar los documentos anexos', 'Ok', 'btn btn-danger');
    }

    /**
     * Crea los campos en el formulario de documentos de anexos.
     *
     * @memberof CargarAnexosComponent
     */
    private documentoAnexo(): any {
        return this.fb.group({
            archivo: [],
            descripcion: this.requerido()
        });
    }

    /**
     * Elimina los archivos del formulario si reinicia la búsqueda de documentos.
     *
     * @memberof CargarAnexosComponent
     */
    clearFormDocumentos() {
        this.arrDocumentosAnexos = [];
        this.formDocumentosAnexos.removeControl('camposDocumentosAnexos');
        this.formDocumentosAnexos.addControl( 'camposDocumentosAnexos', this.fb.array([]));
    }

    /**
     * Crea nuevos elementos en el array de documentos anexos con la información de los archivos cargados desde los
     * campos correspondientes.
     *
     * @param Event event
     * @memberof CargarAnexosComponent
     */
    public loadDocumentoAnexo(event) {
        let e = false;
        let e2 = false;
        const files = event.target.files;
        for (let i = 0; i < files.length; i++) {
            if (files[i] !== undefined && files[i].size > 1048576)
                e2 = true;

            for (let j = 0; j < this.arrDocumentosAnexos.length; j++) {
                if (this.arrDocumentosAnexos[j].name === files[i].name) {
                    e = true;
                    break;
                }
            }

            if (e){
                this.showError('<h3>Un archivo con el mismo nombre ya fue agregado</h3>', 'error', 'Error al cargar los documentos anexos', 'Ok', 'btn btn-danger');
            } else if (e2) {
                this.showError('<h3>No puede cargar archivos de más de 1Mb de tamaño</h3>', 'error', 'Error al cargar los documentos anexos', 'Ok, entiendo', 'btn btn-danger');
            } else {
                this.arrDocumentosAnexos.push(files[i]);
                const CTRL = <FormArray>this.formDocumentosAnexos.controls['camposDocumentosAnexos'];

                for (let k = CTRL.length - 1; k >= 0; k--) {
                    CTRL.removeAt(k);
                }

                if (this.arrDocumentosAnexos.length > 0) {
                    let cont = 0;
                    this.arrDocumentosAnexos.forEach(archivo => {
                        this.agregarDocumentoAnexo();
                        setTimeout(() => {
                            const doc  = <HTMLInputElement>document.getElementById('archivo' + cont);
                            doc.value  = archivo.name;
                            cont++;
                        }, 300);
                    });
                }
            }
        };
    }

    /**
     * Gestiona el evento drop del ngx-dropfile, es decir, permite capturar el archivo cuando ha sido dejado sobre la
     * zona de host de nuestro controlador de archivos.
     *
     * @param {UploadEvent} event
     * @memberof CargarAnexosComponent
     */
    public dropped(event: NgxFileDropEntry[]) {
        const CTRL = <FormArray>this.formDocumentosAnexos.controls['camposDocumentosAnexos'];
        let i = CTRL.length;
        let noPermitidos = [];
        for (const droppedFile of event) {
            if (i < 10) {
                // Valida si se trata de un archivo
                if (droppedFile.fileEntry.isFile) {
                    const fileEntry = droppedFile.fileEntry as FileSystemFileEntry;
                    fileEntry.file((file: File) => {
                        for (let I = 0; I < this.arrDocumentosAnexos.length; I++) {
                            if (this.arrDocumentosAnexos[I].name === file.name) {
                                this.showError('<h3>Un archivo con el mismo nombre ya fue agregado</h3>', 'error', 'Error al cargar los documentos anexos', 'Ok, entiendo', 'btn btn-danger');
                                return;
                            }
                        }
                        if (file.size > 1048576) {
                            this.showError('<h3>No puede cargar archivos de más de 1Mb de tamaño</h3>', 'error', 'Error al cargar los documentos anexos', 'Ok, entiendo', 'btn btn-danger');
                            return;
                        }
                        // Verifica el mime-type del archivo
                        if (this.mimeTypes.find(x => x === file.type) !== undefined) {
                            this.arrDocumentosAnexos.push(file);
                        } else {
                            let tmp = {
                                'indice_archivo': i,
                                'nombre_archivo': file.name
                            };
                            noPermitidos.push(tmp);
                        }
                        i++;
                    });
                }
            } else {
                this.showError('<h3>Puede cargar un máximo de 10 documentos de anexos</h3>', 'error', 'Error al cargar los documentos anexos', 'Ok, entiendo', 'btn btn-danger');
                break;
            }
        }

        // Debido a la carga asíncrona de los archivos, se debe procesar
        // el siguiente bloque dentro de un setTimeout
        let that = this;
        setTimeout(() => {
            // Procesamiento de archivos No Permitidos para informar al usuario
            if (noPermitidos.length > 0) {
                let mensaje = '<ul class="text-left">';
                noPermitidos.forEach(archivo => {
                    mensaje += '<li>' + archivo.nombre_archivo + '</li>';
                });
                mensaje += '</ul>';
                this.showError('<h3>Los siguientes archivos no son permitidos</h3>' + mensaje, 'error', 'Error al cargar los documentos anexos', 'Ok, entiendo', 'btn btn-danger');
            }

            // Elimina los campos file previamente existentes para reorganizarlos
            for (let i = CTRL.length - 1; i >= 0; i--) {
                const CTRL = <FormArray>this.formDocumentosAnexos.controls['camposDocumentosAnexos'];
                CTRL.removeAt(i);
            }

            // Creación dinámica de los campos de archivo y asignación de los
            // nombres de archivo que si fueron permitidos
            if (that.arrDocumentosAnexos.length > 0) {
                let i = 0;
                this.arrDocumentosAnexos.forEach(archivo => {
                    this.agregarDocumentoAnexo();
                    setTimeout(() => {
                        let doc = <HTMLInputElement>document.getElementById('archivo' + i);
                        doc.value = archivo.name;
                        i++;
                    }, 300);
                });
            }
        }, 300);
    }

    /**
     * Selecciona un documento para posteriormente agregarle los anexos.
     * 
     * @param {*} documento Documento seleccionado
     * @memberof CargarAnexosComponent
     */
    async seleccionarDocumento(documento) {
        if(this.correoId) {
            await swal({
                html: `Va asociar los documentos anexos del correo <b>${this.correoSubject}</b> al documento <b>${documento.rfa_prefijo} ${documento.cdo_consecutivo}</b> ¿Desea Continuar?`,
                type: 'warning',
                showCancelButton: true,
                confirmButtonClass: 'btn btn-success',
                confirmButtonText: 'Continuar',
                cancelButtonText: 'Cancelar',
                cancelButtonClass: 'btn btn-danger',
                buttonsStyling: false,
                allowOutsideClick: false
            })
            .then((result) => {
                if (result.value) {
                    this.loading(true);
                    let data = this._correosRecibidosService.asociarAnexoCorreoDocumento(this.correoId, documento.cdo_id).toPromise()
                        .then(resolve => {
                            this.loading(false);
                            swal({
                                type: 'success',
                                title: 'Proceso Exitoso',
                                html: resolve.message
                            })
                            .then((respuesta) => {
                                if(respuesta.value) {
                                    this.regresar();
                                }
                            }).catch(swal.noop);
                        })
                        .catch(error => {
                            this.loading(false);

                            let errores = '';
                            if (typeof error.errors !== undefined && typeof error.errors === 'object') {
                                if(error.errors.length > 1) {
                                    errores = '<ul style="text-align:left;">';
                                    error.errors.forEach(strError => {
                                        if (typeof strError.errors !== undefined && typeof strError.errors === 'object') {
                                            strError.errors.forEach(erroresDoc => {
                                                errores += '<li>' + erroresDoc + '</li>';
                                            });
                                        }
                                    });
                                    errores += '</ul>';
                                } else {
                                    errores = error.errors[0];
                                }
                            } else if (typeof error.message !== undefined && error.status_code !== 500) {
                                errores = error.message;
                            } else {
                                errores = 'Se produjo un error al procesar la información.';
                            }

                            if (errores === undefined && error.message === undefined) {
                                errores = 'NO fue posible realizar la operación solicitada';
                            }
                            
                            this.showError('<h5>' + errores + '</h5>', 'warning', 'Solicitud procesada con advertencias', 'Ok', 'btn btn-danger');
                        });
                }
            }).catch(swal.noop);
        } else
            this.seleccionDocumento = documento;
    }

    /**
     * Inicia la carga de los documentos anexos.
     *
     * @memberof CargarAnexosComponent
     */
    public cargarDocumentos() {
        this.arrDescripciones = [];
        for (let i = 0; i < this.arrDocumentosAnexos.length; i++) {
            let desc = <HTMLInputElement>document.getElementById('descripcion' + i);
            if(desc.value !== '' && desc.value !== undefined)
                this.arrDescripciones.push(desc.value);
        }

        if(this.arrDescripciones.length !== this.arrDocumentosAnexos.length) {
            this.mostrarErrores('Error', 'Verifique que todos los documentos anexos tengan la descripción correspondiente');
        } else {
            this.loading(true);

            this._documentosRecibidoService.cargarDocumentosAnexo(this.arrDescripciones, this.arrDocumentosAnexos, this.seleccionDocumento.cdo_id)
                .subscribe(
                    data => {
                        this.loading(false);
                        this.seleccionDocumento = null;
                        this.showSuccess(data.message, 'success', 'Envío exitoso', 'Ok', 'btn btn-success');
                        this.clearFormDocumentos();
                        this.recargarConsulta();
                    },
                    error => {
                        this.loading(false);
                        this.clearFormDocumentos();
                        this.mostrarErrores(error, 'Error al cargar los documentos anexos');
                    });
        }
    }

    /**
     * Elimina un campo de cargue de archivo en el formulario de cargue de documentos anexos.
     *
     * @param number i
     * @memberof CargarAnexosComponent
     */
    private eliminarDocumentoAnexo(i: number) {
        let CTRL = <FormArray>this.formDocumentosAnexos.controls['camposDocumentosAnexos'];
        CTRL.removeAt(i);
        this.arrDescripciones.splice(i, 1);
        this.arrDocumentosAnexos.splice(i, 1);
    }

    /**
     * Monitoriza cuando el valor del select de OFEs cambia.
     *
     * @param {*} ofe
     * @memberof CargarAnexosComponent
     */
    ofeHasChanged(ofe){
        if(ofe){
            let encontrado = this.ofes.find(item => {
                return item.ofe_identificacion == ofe.ofe_identificacion
            });
            this.ofeID = encontrado.ofe_id;
        }
    }

    /**
     * Apertura una ventana modal para ver los documentos anexos de un documento.
     *
     * @param item
     * @memberof CargarAnexosComponent
     */
    public openModalDocumentosAnexos(item: any): void {
        const modalConfig = new MatDialogConfig();
        modalConfig.autoFocus = true;
        modalConfig.width = '800px';
        modalConfig.data = {
            item: item,
            parent: this,
            proceso: 'recepcion'
        };
        this.modalDocumentosAnexos = this.modal.open(ModalDocumentosAnexosComponent, modalConfig);
    }

    /**
     * Regresa al tracking de correos recibidos.
     *
     * @memberof CargarAnexosComponent
     */
    public regresar(){
        this._router.navigate(['recepcion/correos-recibidos']);
    }
}
