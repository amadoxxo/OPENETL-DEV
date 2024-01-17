import {Component, ElementRef, ViewChild} from '@angular/core';
import {AbstractControl, FormArray, FormBuilder, FormGroup} from '@angular/forms';
import {Router, ActivatedRoute} from '@angular/router';
import {BaseComponentView} from '../../core/base_component_view';
import swal from 'sweetalert2';
// NGX File Drop
import { NgxFileDropEntry, FileSystemFileEntry } from 'ngx-file-drop';
import {DocumentosManualesService} from './../../../services/recepcion/documentos_manuales.service';
import {FilePairs} from '../../models/file_pairs.model';
import {ConfiguracionService} from '../../../services/configuracion/configuracion.service';
import {CommonsService} from '../../../services/commons/commons.service';
import { MatSnackBar } from '@angular/material/snack-bar';

@Component({
    selector: 'app-documentos-manuales-recepcion',
    templateUrl: './documentos-manuales.component.html',
    styleUrls: ['./documentos-manuales.component.scss']
})
export class DocumentosManualesComponent extends BaseComponentView {
    @ViewChild('fileInput') fileInput: ElementRef;

    // Lista de Oferentes
    public ofes                     : any;
    public arrDocumentos            : any[] = [];
    public formDocumentos           : FormGroup;
    public hasCompletePairs         : boolean;
    public archivoCargar            : any[] = [];
    public archivoCargarEvento      : any[] = [];
    public isSelected               : boolean;
    public checkGlobal              : AbstractControl;
    public tipoAccion               : AbstractControl;
    public nombreArchivoExcelEvento : AbstractControl;
    public documentoExcelEvento     : AbstractControl;
    
    
    public correoId;

    documentoExiste     = true;
    public orphans      : any[] = [];
    // Máximo tamaño de subida de archivos en lote = 25 Mb = 26214400
    private loteMaxSize = 26214400;
    public filesSize    : number;
    private pairFiles   : any[] = [];
    public cdo_tipo     : string = null;
    public oferente     : string = null;
    public documentos   : FilePairs[] = [];
    public subir        : boolean = false;
    public texto        : string = '';
    selectedOption      : any;


    public tipoDoc = [
        {label: 'Factura', cdo_tipo: 'FC'},
        {label: 'Nota Crédito', cdo_tipo: 'NC'},
        {label: 'Nota Débito', cdo_tipo: 'ND'}
    ];

    public mimeTypes = [
        'text/xml',
        'application/pdf'
    ];

    public arrAcciones : Array<any> = [
        { id: 'subir_documentos_manuales',        nombre: 'Subir Documentos Manuales'},
        { id: 'descargar_excel_registro_eventos', nombre: 'Descargar Excel para Registro de Eventos'},
        { id: 'subir_registro_eventos',           nombre: 'Subir Registro de Eventos'}
    ];

    /**
     * Constructor de la clase.
     * 
     * @param {FormBuilder} fbDocs
     * @param {MatSnackBar} snackBar
     * @param {Router} _router
     * @param {ActivatedRoute} _route
     * @param {CommonsService} _commonsService
     * @param {ConfiguracionService} _configuracionService
     * @param {DocumentosManualesService} _documentosManualesService
     * @memberof DocumentosManualesComponent
     */
    constructor(
        private fbDocs: FormBuilder,
        private snackBar: MatSnackBar,
        private _router: Router,
        private _route: ActivatedRoute,
        private _commonsService: CommonsService,
        private _configuracionService: ConfiguracionService,
        private _documentosManualesService: DocumentosManualesService
    ) {
        super();
        this.initForm();
        this.hasCompletePairs = false;
        this.filesSize = 0;
        this.isSelected = false;
        this.correoId = (this._route.snapshot.params) ? this._route.snapshot.params.epm_id : 0;
        this.loadOfes();
    }

    /**
     * Event que se ejecuta al cambiar un archivo.
     *
     * @param {*} fileInput
     * @memberof DocumentosManualesComponent
     */
    public fileChangeEvent(fileInput: any) {
        if (fileInput.target.files) {
            this.archivoCargar = fileInput.target.files;
        } else {
            this.archivoCargar = [];
        }
    }

    /**
     * Inicializa el formulario maestro de control
     * 
     * @memberof DocumentosManualesComponent
     */
    private initForm(): void {
        // Formulario para cargue de Documentos
        this.formDocumentos = this.fbDocs.group({
            'camposDocumentos'        : this.fbDocs.array([]),
            'checkGlobal'             : [''],
            'tipoAccion'              : [''],
            'nombreArchivoExcelEvento': [''],
            'documentoExcelEvento'    : ['']
        });
        this.checkGlobal              = this.formDocumentos.controls['checkGlobal'];
        this.tipoAccion               = this.formDocumentos.controls['tipoAccion'];
        this.nombreArchivoExcelEvento = this.formDocumentos.controls['nombreArchivoExcelEvento'];
        this.documentoExcelEvento     = this.formDocumentos.controls['documentoExcelEvento'];

        let evento = {id: 'subir_documentos_manuales', nombre: 'Subir Documentos Manuales'};
        this.changeAccion(evento);
    }

    /**
     * Carga la lista de Ofes que posee el usuario.
     * 
     * @memberof DocumentosManualesComponent
     *
     */
    public loadOfes() {
        this.loading(true);
        this._commonsService.getDataInitForBuild('tat=false').subscribe(
            result => {
                this.loading(false);
                this.ofes = [];
                result.data.ofes.forEach(ofe => {
                    if(ofe.ofe_recepcion === 'SI') {
                        ofe.ofe_identificacion_ofe_razon_social = ofe.ofe_identificacion + ' - ' + ofe.ofe_razon_social;
                        this.ofes.push(ofe);
                    }
                });
            }, error => {
                const texto_errores = this.parseError(error);
                this.loading(false);
                this.showError(texto_errores, 'error', 'Error al cargar los OFEs', 'Ok', 'btn btn-danger');
            }
        );
    }

    /**
     * Muestra una ventana flotante - Snackbar.
     *
     * @param snackBar
     * @param message
     * @param action
     * @memberof DocumentosManualesComponent
     */
    public openSnackBar(snackBar: MatSnackBar, message: string, action: string) {
        snackBar.open(message, action, {
            duration: 2000,
        });
    }

    /**
     * Permite la creación dinámica de grupos en el
     * 
     * @memberof DocumentosManualesComponent
     */
    private _documento(): any {
        const CTRL = <FormArray>this.formDocumentos.controls['camposDocumentos'];
        let nuevo = this.fbDocs.group({});
        CTRL.push(nuevo);
        return nuevo;
    }

    /**
     * Crea de manera dinámica, campos para selección de archivos
     * en el formulario de cargue de documentos.
     * 
     * @memberof DocumentosManualesComponent
     */
    agregarDocumento() {
        const CTRL = <FormArray>this.formDocumentos.controls['camposDocumentos'];
        CTRL.push(this._documento());
    }

    /**
     * busca un archivo en el array de documentos
     * @param archivo
     */
    buscarEnArrayDocumentos(archivo) {
        let i = 0;
        while (i < this.arrDocumentos.length) {
            if (this.arrDocumentos[i].name.toLowerCase() === archivo)
                return i;
            else i++;
        }
        return -1;
    }

    /**
     * Elimina un campo de cargue de archivo
     * en el formulario de cargue de documentos.
     * 
     * @param i
     * @memberof DocumentosManualesComponent
     */
    eliminarDocumento(i: number) {
        // Elimina archivo principal sobre el que se hizo clic en el botón de eliminar
        const pvt = this.documentos[i];
        let k;
        if (pvt.xml) {
            k = this.buscarEnArrayDocumentos(pvt.nombre + '.xml');
            if (k !== -1) {
                this.filesSize -= this.arrDocumentos[k].size;
                this.arrDocumentos.splice(k, 1);
            }
        }
        if (pvt.pdf) {
            k = this.buscarEnArrayDocumentos(pvt.nombre + '.pdf');
            if (k !== -1) {
                this.filesSize -= this.arrDocumentos[k].size;
                this.arrDocumentos.splice(k, 1);
            }
        }
        this.documentos.splice(i, 1);
        this.verifyCompletePairs();
    }

    /**
     * Crea nuevos elementos en el array de documentos
     * con la información de los archivos cargados desde los
     * campos correspondientes.
     *
     * @param event
     * @param item
     * @memberof DocumentosManualesComponent
     */
    loadDocumento(event, item) {
        if (this.arrDocumentos[item]) {
            this.arrDocumentos[item] = event.target.files[0];
        } else {
            this.arrDocumentos.push(event.target.files[0]);
        }
    }

    /**
     * Crea nuevos elementos en el array de documentos
     * con la información de los archivos cargados desde los
     * campos correspondientes.
     *
     * @param Event event
     * @memberof DocumentosManualesComponent
     */
    public loadDocumentoButton(event) {
        const CTRL = <FormArray>this.formDocumentos.controls['camposDocumentos'];
        const i = CTRL.length;
        // let i = Number(this.hasXML) + Number(this.hasXML) + 1;
        const noPermitidos = [];
        const files = event.target.files;
        for (let i = 0; i < files.length; i++) {
            if (this.mimeTypes.find(x => x === files[i].type) !== undefined) {
                this.appendNewfile(files[i]);
            } else {
                const tmp = {
                    'indice_archivo': i,
                    'nombre_archivo': files[i].name
                };
                noPermitidos.push(tmp);
            }
        }

        // Debido a la carga asíncrona de los archivos, se debe procesar
        // el siguiente bloque dentro de un setTimeout
        this.appendInZone(CTRL, noPermitidos);
    }

    /**
     * Ejecuta la busqueda de un archivo.
     *
     * @private
     * @param {*} nombre Nombre del archivo
     * @return {*} 
     * @memberof DocumentosManualesComponent
     */
    private buscarArchivo(nombre) {
        let i = 0;
        while (i < this.documentos.length) {
            if (this.documentos[i].nombre === nombre)
                return i;
            else
                i++;
        }
        return -1;
    }

    /**
     * Determina si es posible agregar un nuevo archivo en funcion de los filtros establecidos, si es asi, es agregado
     * 
     * @param file
     * @memberof DocumentosManualesComponent
     */
    private appendNewfile(file) {
        // Se comprueba que el archivo no haya sido agregado anteriormente al área de cargue de documentos
        const indice = this.arrDocumentos.findIndex(f => f.name === file.name);
        if (indice == -1) {
            // Se comprueba si se ha alcanzado el tamaño límite permitido de subida en lote de 25 Mb
            if (this.filesSize > this.loteMaxSize) {
                swal({
                    html: '<h5>Error</h5><strong>Se ha alcanzado el tamaño límite permitido de subida en lote de 25 Mb</strong><br>',
                    type: 'error',
                    showCancelButton: true,
                    showConfirmButton: false,
                    cancelButtonColor: '#f44336',
                    cancelButtonText: 'OK, entiendo'
                }).catch(swal.noop);
                return;
            } else {
                this.arrDocumentos.push(file);
                this.filesSize += file.size;
            }
        }
        const fileName = this.getPartsName(file.name);
        let pvt = null;
        // El array de objetos pairFiles se encarga de hacer el seguimiento sobre archivos sin sus correspondiente pareja
        const i = this.buscarArchivo(fileName[0]);
        if (i !== -1) {
            if (fileName[1] === 'pdf' && this.documentos[i].pdf !== true) {
                this.documentos[i].pdf = true;
                pvt = this.documentos[i];
                pvt.hasFormComponent = true;
            }
            else if (fileName[1] === 'xml' && this.documentos[i].xml !== true) {
                this.documentos[i].xml = true;
                pvt = this.documentos[i];
                pvt.hasFormComponent = true;
            }
        } else {
            const pair: FilePairs = {
                nombre: fileName[0],
                check: false,
                fecha: null,
                hora: null
            };
            if (fileName[1] === 'pdf') pair.pdf = true;
            else pair.xml = true;
            pvt = pair;
            pvt.FormComponent = this._documento();
            this.documentos.push(pvt);

            let arrayform = <FormArray>this.formDocumentos.controls['camposDocumentos'];
            let N = arrayform.length;
            for (let i = 0; i < N; i++)
                arrayform.removeAt(0);
            for (let doc of this.documentos) {
                arrayform.push(doc.FormComponent);
            }

        }
        this.verifyCompletePairs();
        return pvt;
    }

    /**
     * Agrega los archivos anexados a la zona de archivos por subir.
     *
     * @param CTRL
     * @param noPermitidos
     * @memberof DocumentosManualesComponent
     */
    private appendInZone(CTRL, noPermitidos) {
        const that = this;
        setTimeout(() => {
            // Procesamiento de archivos No Permitidos para informar al usuario
            if (noPermitidos.length > 0) {
                let mensaje = '<ul class="text-left">';
                noPermitidos.forEach(archivo => {
                    mensaje += '<li>' + archivo.nombre_archivo + '</li>';
                });
                mensaje += '</ul>';
                swal({
                    html: '<h5>Error</h5><strong>Los siguientes archivos no son permitidos</strong><br>' + mensaje,
                    type: 'error',
                    showCancelButton: true,
                    showConfirmButton: false,
                    cancelButtonColor: '#f44336',
                    cancelButtonText: 'OK'
                }).catch(swal.noop);
            }

            // Elimina los campos file previamente existentes para reorganizarlos
            for (let i = CTRL.length - 1; i >= 0; i--) {
                const CTRL = <FormArray>this.formDocumentos.controls['camposDocumentos'];
                CTRL.removeAt(i);
            }
            that.verifyCompletePairs();
        }, 300);
    }

    /**camposDocumentos
     * Gestion del evento drop del ngxdropfile.
     *
     * @param event
     * @memberof DocumentosManualesComponent
     */
    public dropped(event: NgxFileDropEntry[]) {
        const CTRL = <FormArray>this.formDocumentos.controls['camposDocumentos'];
        let i = CTRL.length;
        const noPermitidos = [];
        for (const droppedFile of event) {
            // Valida si se trata de un archivo
            if (droppedFile.fileEntry.isFile) {
                const fileEntry = droppedFile.fileEntry as FileSystemFileEntry;
                fileEntry.file((file: File) => {
                    if (this.mimeTypes.find(x => x === file.type) !== undefined) {
                        this.appendNewfile(file);
                    } else {
                        const tmp = {
                            'indice_archivo': i,
                            'nombre_archivo': file.name
                        };
                        noPermitidos.push(tmp);
                    }
                    i++;
                });
            }
        }

        // Debido a la carga asincrona de los archivos, se debe procesar
        // el siguiente bloque dentro de un setTimeout
        this.appendInZone(CTRL, noPermitidos);
    }

    // ---------------------------------------------------------------------------------------------------------------

    /**
     * Efectua la peticion al servidor para procesar los documentos.
     *
     * @memberof DocumentosManualesComponent
     */
    procesar() {
        if (this.oferente === undefined || this.oferente === null || this.oferente === ''){
            swal({
                type: 'error',
                title: 'Error',
                html: 'Debe seleccionar un Oferente',
                showCancelButton: true,
                showConfirmButton: false,
                cancelButtonColor: '#f44336',
                cancelButtonText: 'OK'
            });
            return;
        }

        this.loading(true);
        const input = new FormData();
        const documentos = [];

        for (let i = 0; i < this.documentos.length; i++) {
            documentos.push(this.replaceSpecialChars(this.documentos[i].nombre));
        }

        this.arrDocumentos.forEach( f => {
            const n = this.getPartsName(f.name);
            n[0] = this.replaceSpecialChars(n[0]);
            input.append(n.join('_'), f);
        });
        input.append('oferente', this.oferente);
        input.append('documentos', documentos.join(';'));
        if(this.correoId)
            input.append('epm_id', this.correoId);

        this._documentosManualesService.procesarDocumentos(input)
            .subscribe(
                res => {
                    this.loading(false);
                    this.arrDocumentos = [];
                    this.pairFiles = [];
                    this.cdo_tipo = null;
                    this.orphans = [];
                    this.documentos = [];
                    this.filesSize = 0;
                    this.hasCompletePairs = false;
                    this.formDocumentos.removeControl('camposDocumentos');
                    this.formDocumentos.addControl( 'camposDocumentos', this.fbDocs.array([]));
                    this.limpiarTodos();

                    let lotes = '';
                    if(res.lotes_procesamiento) {
                        if (Array.isArray(res.lotes_procesamiento) && res.lotes_procesamiento.length > 0) {
                            res.lotes_procesamiento.forEach(lote => {
                                lotes += '<li>' + lote + '</li>';
                            });
                        } else if (typeof res.lotes_procesamiento === 'string')
                            lotes = '<li>' + res.lotes_procesamiento + '</li>';
                        else if (typeof res.lotes_procesamiento === 'undefined'){
                            lotes = '';
                        }
                    }

                    swal({
                        type: 'success',
                        title: 'Documentos Manuales',
                        html: res.message  + (lotes ? '<br><br><span style="text-align:left;"><strong>Lotes de Procesamiento</strong>:<ul>' + lotes + '</ul></span>' : '')
                    });
                    if(this.correoId)
                        this.regresar();
                }, error => {
                    this.loading(false);
                    const texto_errores = this.parseError(error);
                    swal({
                        type: 'error',
                        title: error.message,
                        html: texto_errores,
                        showCancelButton: true,
                        showConfirmButton: false,
                        cancelButtonColor: '#f44336',
                        cancelButtonText: 'OK'
                    });
                });
    }

    /**
     * Obtiene el nombre de la cabecera content dispostion.
     *
     * @param contentDisposition
     * @memberof DocumentosManualesComponent
     */
    private getNombreArchivo(contentDisposition) {
        const nombre = contentDisposition.split(';')[1].trim().split('=')[1];
        return nombre.replace(/"/g, '');
    }

    /**
     * Divide el nombre de un archivo en "nombre" y ".ext".
     * 
     * @param string name  - Nombre del archivo completo
     * @return array       - ["nombre", ".ext"]
     * @memberof DocumentosManualesComponent
     */
    private getPartsName(fileName: string){
        let ext = '';
        let name = '';
        const pos = fileName.lastIndexOf('.');
        if ( pos !== -1) {
            ext  = fileName.substring(pos + 1).toLowerCase();
            name = fileName.substring(0, pos).toLowerCase();
        } else {
            name = fileName;
        }
        return [name, ext];
    }

    /**
     * Verifica si cada archivo xml cargado tiene su representación gráfica en pdf cargada y viceversa.
     *
     * @return boolean
     * @memberof DocumentosManualesComponent
     */
    private verifyCompletePairs(): boolean{
        let complete = true;
        let i = 0;
        if (this.documentos.length === 0)
            complete = false;
        for (i = 0; i < this.documentos.length && complete; i++) {
            if (!this.documentos[i].pdf || !this.documentos[i].xml)
                complete = false;
        }
        this.hasCompletePairs = complete;
        return complete;
    }

    /**
     * Elimina del área de cargue todos los documentos
     *
     * @memberof DocumentosManualesComponent
     */
    public limpiarTodos(){
        const CTRL = <FormArray>this.formDocumentos.controls['camposDocumentos'];
        for (let i = CTRL.length - 1; i >= 0; i--) {
            CTRL.removeAt(i);
        }
        this.documentos = [];
        this.arrDocumentos = [];
        this.pairFiles = [];
        this.orphans = [];
        this.verifyCompletePairs();
    }

    /**
     * Selecciona o libera todos los documentos.
     *
     * @param evt
     * @memberof DocumentosManualesComponent
     */
    checkAll(evt) {
        if (evt.checked) {
            this.documentos.forEach((doc) => doc.check = true);
        } else {
            this.documentos.forEach((doc) => doc.check = false);
        }
    }

    /**
     * Evento para los check.
     *
     * @param i
     * @memberof DocumentosManualesComponent
     */
    onCheckboxChangeFn(i: number) {
        const pvt = this.documentos[i];
        pvt.check = !pvt.check;
    }

    /**
     * Regresa al tracking de correos recibidos
     *
     * @memberof DocumentosManualesComponent
     */
    public regresar(){
        this._router.navigate(['recepcion/correos-recibidos']);
    }

    /**
     * Método que se dispara al cambiar de acción en el formulario para identificar si se va a descargar o subir el Excel.
     *
     * @param {*} event Información del evento
     * @memberof DocumentosManualesComponent
     */
    changeAccion(event: any): void {
        this.selectedOption = event.id;

        if (event.id === 'descargar_excel_registro_eventos' ) {
            this.texto = '<mat-icon color="#ffffff" [fontIcon]="cloud_download" [fontSet]="material-icons"></mat-icon> Generar';
            this.subir = false;
        } else if (event.id === 'subir_registro_eventos') {
            this.texto = '<mat-icon color="#ffffff" [fontIcon]="cloud_upload"></mat-icon> Subir';
            this.subir = true;
            this.archivoCargarEvento = [];
        } else {
            this.archivoCargarEvento = [];
            this.texto = '';
            this.subir = false;
        }
    }

    /**
     * Permite limpiar las variables de subir.
     *
     * @memberof DocumentosManualesComponent
     */
    clear(): void {
        this.subir = false;
        this.texto = '';
    }

    /**
     * Permite almacenar el archivo excel seleccionado en la propiedad que se envía en la petición.
     *
     * @param {*} fileInput Archivo Excel para el registro de eventos
     * @memberof DocumentosManualesComponent
     */
    fileChangeSubirEvento(fileInput: any): void {
        if (fileInput.target.files) {
            this.archivoCargarEvento = fileInput.target.files;
        } else {
            this.archivoCargarEvento = [];
        }
    }

    /**
     * Gestiona la carga o descarga del Excel que registrar los eventos DIAN.
     *
     * @memberof DocumentosManualesComponent
     */
    uploadExcel(): void {
        if (this.selectedOption === 'descargar_excel_registro_eventos') {
            this.generarInterfaceEventos();
        } else if (this.selectedOption === 'subir_registro_eventos') {
            if (this.archivoCargarEvento.length < 1) {
                this.showError('<h3>Debe seleccionar un archivo.</h3>', 'warning', 'Ningún archivo seleccionado', 'Ok, entiendo',
                        'btn btn-warning');
            } else {
                this.loading(true);
                this._documentosManualesService.cargarRegistroEventos(this.archivoCargarEvento[0]).subscribe(
                    response => {
                        this.loading(false);
                        this.archivoCargarEvento = [];
                        this.documentoExcelEvento.setValue('', {});
                        this.showSuccess(response.message, 'success', 'Registro de eventos DIAN', 'Ok', 'btn btn-success');
                    }, error => {
                        this.documentoExcelEvento.setValue('', {});
                        this.loading(false);
                        this.mostrarErrores(error, 'Error al cargar el archivo');
                    }
                );
            }
        }
    }

    /**
     * Descarga la interface de Excel para el registro de eventos DIAN.
     *
     * @memberof DocumentosManualesComponent
     */
    generarInterfaceEventos(): void {
        this.loading(true);
        this._documentosManualesService.generarInterfaceFacturas().subscribe(
            response => {
                this.loading(false);
            },
            error => {
                this.loading(false);
            }
        );
    }
}
