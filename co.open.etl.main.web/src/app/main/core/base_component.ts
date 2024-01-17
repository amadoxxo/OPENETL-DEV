import {environment} from '../../../environments/environment';
import {HttpHeaders} from '@angular/common/http';
import swal from 'sweetalert2';
import {MatSnackBar} from '@angular/material/snack-bar';
import {AppComponent} from '../../app.component';
import {Validators} from '@angular/forms';

export class BaseComponent {
    public apiUrl = environment.API_ENDPOINT;
    private headers = new HttpHeaders();

    constructor() {
        this.headers = this.headers.set('Content-type', 'application/x-www-form-urlencoded');
        this.headers = this.headers.set('X-Requested-Whith', 'XMLHttpRequest');
        this.headers = this.headers.set('Accept', 'application/json');
        this.headers = this.headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        this.headers = this.headers.set('Pragma', 'no-cache');
        this.headers = this.headers.set('Expires', '0');
        this.loading(false);
    }

    /**
     * Devuelve las cabeceras para las peticiones http.
     * 
     */
    public getHeaders(): HttpHeaders {
        return this.headers;
    }

    /**
     * Formatea un objeto date en un string de formato YYYY-MM-DD.
     * 
     * @param date
     */
    public formatDate(date) {
        var d = new Date(date),
            month = '' + (d.getMonth() + 1),
            day = '' + d.getDate(),
            year = d.getFullYear();

        if (month.length < 2) {
            month = '0' + month;
        }
        if (day.length < 2) {
            day = '0' + day;
        }

        return [year, month, day].join('-');
    }

    /**
     * Ventana modal de errores.
     * 
     * @param html mensaje
     * @param type Tipo de ventana
     * @param cancelButtonText  Texto del boton de cancelar
     * @param cancelButtonClass Clase css del boton de cancelar
     * @param url URL a redireccionar
     * @param router Enrutador
     */
    public showError(html, type, title, cancelButtonText, cancelButtonClass, url = null, router = null) {
        let _swal = swal({
            html: html,
            type: type,
            title: title,
            showCancelButton: true,
            showConfirmButton: false,
            cancelButtonClass: cancelButtonClass,
            cancelButtonText: cancelButtonText,
            cancelButtonColor: '#f44336',
            buttonsStyling: false,
            allowOutsideClick: false
        });

        if (url && router) {
            _swal.then((result) => {
                if (url instanceof Array) {
                    router.navigate(url);
                } else {
                    router.navigate([url]);
                }
            });
        } else {
            return _swal;
        }
    }

    /**
     * Ventana modal de mensajes de éxito.
     * 
     * @param html mensaje
     * @param type Tipo de ventana
     * @param confirmButtonText  Texto del boton de confirmacion
     * @param confirmButtonClass Clase css del boton de confirmacion
     * @param url URL a redireccionar
     * @param router Enrutador
     */
    public showSuccess(html, type, title, confirmButtonText, confirmButtonClass, url = null, router = null) {
        let _swal = swal({
            html: html,
            type: type,
            title: title,
            showConfirmButton: true,
            showCancelButton: false,
            confirmButtonClass: confirmButtonClass,
            confirmButtonText: confirmButtonText,
            buttonsStyling: false,
            allowOutsideClick: false
        });

        if (url && router) {
            _swal.then((result) => {
                if (url instanceof Array) {
                    router.navigate(url);
                } else {
                    router.navigate([url]);
                }
            });
        } else {
            return _swal;
        }
    }

    /**
     * Ventana modal con timer para auto cierre.
     * 
     * @param html mensaje
     * @param type Tipo de ventana
     * @param position Posicion
     * @param time Tiempo en milisegundos
     */
    public showTimerAlert(html, type, position, time) {
        let _swal = swal({
            html: html,
            type: type,
            position: position,
            showConfirmButton: false,
            showCancelButton: false,
            timer: time
        });
        return _swal;
    }

    /**
     * Retorna una lista de ID de estados de tickets contatenadps por comas
     * @param array_objetos
     */
    public joinTicketsEstados(array_objetos): string {
        let value = '';
        for (let i = 0; i < array_objetos.length; i++) {
            if (i > 0) {
                value = value + ',' + array_objetos[i].id;
            } else {
                value = array_objetos[i].id;
            }
        }
        return value;
    }

    /**
     * Retorna una lista de ID de compañias contatenadps por comas
     * @param array_objetos
     */
    public joinObjects(array_objetos, property): string {
        let value = '';
        for (let i = 0; i < array_objetos.length; i++) {
            if (i > 0) {
                value = value + ',' + array_objetos[i][property];
            } else {
                value = array_objetos[i][property];
            }
        }
        return value;
    }

    /**
     * Muestra una ventana flotante - Snackbar
     * @param snackBar
     * @param message
     * @param action
     */
    public openSnackBar(snackBar: MatSnackBar, message: string, action: string) {
        snackBar.open(message, action, {
            duration: 2000,
        });
    }

    /**
     * Apertura un fileinput dado su ID
     * @param id
     */
    openFileInput(id) {
        document.getElementById(id).click();
    }

    /**
     * Obtiene el nombre de un archivo que ha sido seleccionado por un file input.
     * 
     * @param _adjunto
     */
    getName(_adjunto) {
        if (_adjunto && _adjunto.value && _adjunto.value.archivo) {
            return _adjunto.value.archivo.match(/\\([^\\]+)$/)[1];
        }
        return null;
    }

    /**
     * Remueve el fakepath de una ruta.
     * 
     * @param _adjunto
     */
    removeFakePath(path){
        return path.replace(/^C:\\fakepath\\/, "");
    }

    /**
     * Parsea errores recibidos durante peticiones HTTP
     *
     * @param object error
     * @returns string
     */
    parseError(error) {
        let errores = '';
        if (typeof error.errors !== undefined && typeof error.errors === 'object') {
            let index = Object.keys(error.errors);
            if(index[0] !== '0') {
                if(error.errors[index[0]].length > 1) {
                    errores = '<ul>';
                    error.errors[index[0]].forEach(strError => {
                        errores += '<li>' + strError + '</li>';
                    });
                    errores += '</ul>';
                } else {
                    errores = error.errors[index[0]][0];
                }
            } else {
                if(error.errors.length > 1) {
                    errores = '<ul>';
                    error.errors.forEach(strError => {
                        errores += '<li>' + strError + '</li>';
                    });
                    errores += '</ul>';
                } else {
                    errores = error.errors[0];
                }
            }

        } else if (typeof error.message !== undefined && error.status_code !== 500) {
            errores = error.message;
        } else {
            errores = 'Se produjo un error al procesar la información.';
        }
        if (errores === undefined && error.message === undefined) {
            errores = 'NO fue posible realizar la operación solicitada';
        }
        return errores;
    }

    /**
     * Construye la configuracion inicial de los objetos angular multiselect
     * @param ref
     * @param singleSelection
     * @param text
     * @param secondaryPlaceholder
     * @param position
     */
    initConfigurationSettings(ref, singleSelection, text, secondaryPlaceholder, position = 'top', badgeShowLimit = null) {
        ref.singleSelection = singleSelection;
        ref.text = text;
        ref.selectAllText = 'Seleccionar Todos';
        ref.unSelectAllText = 'Deseleccionar Todos';
        ref.filterSelectAllText = 'Seleccionar todos los resultados filtrados';
        ref.filterUnSelectAllText = 'Deseleccionar todos los resultados filtrados';
        ref.enableSearchFilter = true;
        ref.searchPlaceholderText = secondaryPlaceholder;
        ref.noDataLabel = 'No hay coincidencias';
        ref.classes = 'form-control';
        if(badgeShowLimit !== null)
            ref.badgeShowLimit = badgeShowLimit;
        ref.position = position;
    }

    /**
     * Popula cualquier AngularMultiselect2
     * @param data
     * @param keyNameProperty
     * @param textNameProperty
     * @param target
     */
    populateMultiSelect(data, keyNameProperty, textNameProperty, target) {
        if (data.length > 0) {
            data.forEach(item => {
                target.push({
                    'id': item[keyNameProperty],
                    'itemName': item[textNameProperty],
                    'item': item
                });
            });
        }
    }

    /**
     *
     * @param vars
     */
    clearVars(...vars) {
        vars.forEach(
            variable => {
                if (variable instanceof Array)
                    variable.length = 0;
                if (variable instanceof Object)
                    variable = null;
            }
        );
    }

    /**
     *
     * @param controles
     */
    disableFormControl(...controles) {
        controles.forEach(
            control => {
                control.disable();
            }
        );
    }

    /**
     *
     * @param controles
     */
    clearFormControl(...controles) {
        controles.forEach(
            control => {
                control.setValue(null);
            }
        );
    }

    /**
     * Analiza una colecion de AngularMultiselect y obtiene el objeto seleccionado
     * @param collection
     * @param id
     */
    getSimpleSelectionForCollection(collection, id) {
        let i = 0;
        while(i < collection.length) {
            if (collection[i].id === id)
                return collection[i];
            i++;
        }
        return null;
    }

    /**
     * Permite setear el indicador de carga
     * @param flag
     */
    public loading(flag) {
        AppComponent.loading = flag;
    }

    /**
     * Retorna un validador para valores requeridos
     */
    public requerido() {
        return ['', Validators.compose([Validators.required])];
    }

    /**
     * Retorna un validador para email
     */
    public email() {
        return ['', Validators.compose([Validators.email, Validators.minLength(10), Validators.maxLength(255)])];
    }

    /**
     * Retorna un validador para email y además requerido
     */
    public emailRequerido() {
        return ['', Validators.compose([Validators.required, Validators.email, Validators.minLength(10), Validators.maxLength(255)])];
    }

    /**
     * Validador de longitud maxima
     * @param maximo
     */
    public maxlong(maximo) {
        return ['', Validators.compose([Validators.maxLength(maximo)])];
    }

    /**
     * Validador de longitud máxima y requerido
     * @param maximo
     */
    public requeridoMaxlong(maximo) {
        return ['', Validators.compose([Validators.required, Validators.maxLength(maximo)])];
    }

    /**
     * Validador de longitud máxima, mínima y requerido
     * @param maximo
     */
    public requeridoMaxMinlong(maximo, minimo) {
        return ['', Validators.compose([Validators.required, Validators.maxLength(maximo), Validators.maxLength(minimo)])];
    }

    /**
     * Muestra los errores en un modal de Sweet Alert
     *
     * @param error
     * @param msjTitle
     */
    mostrarErrores(error, msjTitle, url = null, router = null){
        let message = error.message ? error.message : msjTitle;
        let errores = '';
        if (Array.isArray(error.errors) && error.errors.length > 0) {
            error.errors.forEach(strError => {
                errores += '<li>' + strError + '</li>';
            });
        } else if (typeof error.errors === 'string')
            errores = '<li>' + error.errors + '</li>';
        else if (typeof error.errors === 'undefined'){
            errores = message;
            message = 'Se produjo un error al procesar la información';
        }
        this.showError(((errores !== '') ? '<span style="text-align:left; font-weight: bold;"><ul>' + errores + '</ul></span>' : 'Se produjo un error al procesar la información.'), 'error', message, 'OK', 'btn btn-danger', url, router);
    }

    /**
     * Convierte la primera letra de un string a mayúscula.
     *
     */
    capitalize(string){
        return string.charAt(0).toUpperCase() + string.slice(1);
    }

    /**
     * Determina si un objeto esta vacío.
     *
     * @param {object} obj Objeto a analizar
     */
    isEmpty(obj) {
        if (obj) {
            for (var key in obj) {
                if (obj.hasOwnProperty(key))
                    return false;
            }
        }
        return true;
    }

    /**
     * Elimina caracteres especiales de una cadena (" ' : \ /).
     * 
     * @param cadena Cadena a procesar
     * @returns 
     */
    replaceSpecialChars(cadena: string) {
        cadena = cadena.replace('"', '_');
        cadena = cadena.replace("'", '_');
        cadena = cadena.replace(/:/g, '_');
        cadena = cadena.replace(/\\/g, '_');
        cadena = cadena.replace('/', '_');

        return cadena;
    }
}