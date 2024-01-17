import {Component, Inject, OnInit} from '@angular/core';
import {BaseComponentView} from '../../core/base_component_view';
import {MAT_DIALOG_DATA, MatDialogRef} from '@angular/material/dialog';

@Component({
    selector: 'app-modal-resumen-estados-documento',
    templateUrl: './modal-resumen-estados-documento.component.html',
    styleUrls: ['./modal-resumen-estados-documento.component.scss']
})
export class ModalResumenEstadosDocumentoComponent extends BaseComponentView implements OnInit {

    parent                         : any;
    documento                      : any;
    titulo                         : string = '';
    estados                        : any [] = [];
    xmlUblMensaje                  : string = '';
    wsDianMensaje                  : string = '';
    attachedDocumentMensaje        : string = '';
    transmisionEdmMensaje          : string = '';
    notificacionMensaje            : string = '';
    registroRecepcionMensaje       : string = '';
    aceptadoDianMensaje            : string = '';
    estadosValidacion              : object [] = [];

    mostrarXmlUbl                  : boolean = false;
    mostrarWsDian                  : boolean = false;
    mostrarAttachedDocument        : boolean = false;
    mostrarTransmisionEdm          : boolean = false;
    mostrarNotificacion            : boolean = false;
    mostrarNotificacionMensaje     : boolean = false;
    mostrarRegistroRecepcion       : boolean = false;
    mostrarValidacion              : boolean = false;
    mostrarAceptadoDian            : boolean = false;
    mostrarAceptadoDianNotificacion: boolean = false;

    errroMessage                   : string [] = [];
    erroresAceptacionTFallido      : any = {};

    xmlUblEstado                   : string = '';
    wsDianEstado                   : string = '';
    ublAttachedDocumentEstado      : string = '';
    transmisionEdmEstado           : string = '';
    wsDianProcesoEstado            : string = '';
    notificacionEstado             : string = '';
    registroRecepcionEstado        : string = '';
    aceptadoDianEstado             : string = '';

    estObject                        : any = {};
    estObjectAcuse                   : any = {};
    estObjectAceptacionTacita        : any = {};
    estadoDoExitoso                  : any = {};
    estObjectDian                    : any = {};
    estObjectTransmisionErp          : any = {};
    estObjectRdi                     : any = {};
    estObjectReciboBien              : any = {};
    estObjectTransmisionOpenComex    : any = {};
    estObjectUblAcuseReciboFallido   : any = null;
    estObjectUblReciboBienFallido    : any = null;
    estObjectUblAceptadoFallido      : any = null;
    estObjectUblRechazadoFallido     : any = null;
    estObjectUblAdAcuseReciboFallido : any = null;
    estObjectUblAdReciboBienFallido  : any = null;
    estObjectUblAdAceptadoFallido    : any = null;
    estObjectUblAdRechazadoFallido   : any = null;

    /**
     * Constructor
     *
     * @param data
     * @param modalRef
     */
    constructor(
        @Inject(MAT_DIALOG_DATA) data,
        private modalRef: MatDialogRef<ModalResumenEstadosDocumentoComponent>
    ) {
        super();
        this.documento = data.documento;
        if (data.documento.proceso == 'nomina-electronica') {
            this.titulo    = 'Documento Nómina - ' + data.documento.cdn_clasificacion + data.documento.documento.replace(' ', '-');
        } else if(data.documento.proceso === 'radian') {
            this.titulo    = 'Documento ' + data.documento.documento.replace(' ', '-');
        } else {
            this.titulo    = 'Documento ' + data.documento.cdo_clasificacion + data.documento.documento.replace(' ', '-');
        }
        this.estados   = data.documento.estados;
        this.init();
    }

    public replaceAll(str, find, replace) {
        return str.replace(new RegExp(find, 'g'), replace);
    }

    /**
     * Codifica los mensajes de fallos con los correspondientes saltos de linea
     * @param msj
     */
    public configureMensaje(msj) {
        if (msj) {
            let str =  this.replaceAll(msj, '//', '<br>');
            str =  this.replaceAll(str, '~', '<br>');
            return str.split('<br>');
        }
        return '';
    }

    /**
     * Configura la data a ser motrada en la modal
     */
    private init() {
        if(this.documento.proceso === 'recepcion') {
            const getStatusEstado           = this.findEstado('GETSTATUS');
            const acuseReciboExitoso        = this.findEstadoAcuseAceptadoRechazado('ACUSERECIBO', 'EXITOSO');
            const acuseReciboFallido        = this.findEstadoAcuseAceptadoRechazado('ACUSERECIBO', 'FALLIDO');
            const reciboBienExitoso         = this.findEstadoAcuseAceptadoRechazado('RECIBOBIEN', 'EXITOSO');
            const reciboBienFallido         = this.findEstadoAcuseAceptadoRechazado('RECIBOBIEN', 'FALLIDO');
            const aceptadoEstado            = this.findEstadoAcuseAceptadoRechazado('ACEPTACION', 'EXITOSO');
            const aceptadoEstadoFallido     = this.findEstadoAcuseAceptadoRechazado('ACEPTACION', 'FALLIDO');
            const aceptadoTacitamenteEstado = this.findEstadoAcuseAceptadoRechazado('ACEPTACIONT', 'EXITOSO');
            const aceptadoTacitamenteFallido= this.findEstadoAcuseAceptadoRechazado('ACEPTACIONT', 'FALLIDO');
            const rechazadoEstado           = this.findEstadoAcuseAceptadoRechazado('RECHAZO', 'EXITOSO');
            const rechazadoEstadoFallido    = this.findEstadoAcuseAceptadoRechazado('RECHAZO', 'FALLIDO');
            const ublAcuseReciboFallido     = this.findEstadoAcuseAceptadoRechazado('UBLACUSERECIBO', 'FALLIDO');
            const ublReciboBienFallido      = this.findEstadoAcuseAceptadoRechazado('UBLRECIBOBIEN', 'FALLIDO');
            const ublAceptadoFallido        = this.findEstadoAcuseAceptadoRechazado('UBLACEPTACION', 'FALLIDO');
            const ublRechazadoFallido       = this.findEstadoAcuseAceptadoRechazado('UBLRECHAZO', 'FALLIDO');
            const ublAdAcuseReciboFallido   = this.findEstadoAcuseAceptadoRechazado('UBLADACUSERECIBO', 'FALLIDO');
            const ublAdReciboBienFallido    = this.findEstadoAcuseAceptadoRechazado('UBLADRECIBOBIEN', 'FALLIDO');
            const ublAdAceptadoFallido      = this.findEstadoAcuseAceptadoRechazado('UBLADACEPTACION', 'FALLIDO');
            const ublAdRechazadoFallido     = this.findEstadoAcuseAceptadoRechazado('UBLADRECHAZO', 'FALLIDO');
            const transmisionErp            = this.findEstadoTransmisionErp();
            const transmisionOpenComex      = this.findEstadoTransmisionOpenComex();
            const estadoRdi                 = this.findEstadoRdi();
            this.findEstadosValidacion();

            if (getStatusEstado) {
                this.mostrarAceptadoDian = true;

                this.estObjectDian = getStatusEstado.est_object;
                this.errroMessage = (this.estObjectDian.ErrorMessage) ? this.configureMensaje(this.estObjectDian.ErrorMessage) : [];

                if (this.estObjectDian.ErrorMessage !== '')
                    this.mostrarAceptadoDianNotificacion = true;
            }

            if (acuseReciboExitoso) {
                this.estObjectAcuse = acuseReciboExitoso.est_informacion_adicional;
                this.estObjectAcuse.observacion = acuseReciboExitoso.est_motivo_rechazo !== null ? acuseReciboExitoso.est_motivo_rechazo : {};
                this.estObjectAcuse.mensaje_resultado = acuseReciboExitoso.est_mensaje_resultado;
            } else if(acuseReciboFallido) {
                this.estObjectAcuse = acuseReciboFallido.est_informacion_adicional;
                this.estObjectAcuse.observacion = acuseReciboFallido.est_motivo_rechazo !== null ? acuseReciboFallido.est_motivo_rechazo : {};
                this.estObjectAcuse.mensaje_resultado = acuseReciboFallido.est_mensaje_resultado;
            }

            if(this.estObjectAcuse && this.estObjectAcuse.created) {
                this.estObjectAcuse.created = this.estObjectAcuse.created.replace('<u:Created>', '');
                this.estObjectAcuse.created = this.estObjectAcuse.created.replace('</u:Created>', '');
            }

            if(this.estObjectAcuse && this.estObjectAcuse.ResponseCode) {
                let arrResponseCode = this.estObjectAcuse.ResponseCode.split('<cbc:Description>');
                if (arrResponseCode.length > 0) {
                    this.estObjectAcuse.code    = arrResponseCode[0].replace('<cac:DocumentResponse><cac:Response><cbc:ResponseCode>', '');
                    this.estObjectAcuse.code    = this.estObjectAcuse.code.replace('</cbc:ResponseCode>', '');
                    this.estObjectAcuse.message = arrResponseCode[1].replace('</cbc:Description></cac:Response></cac:DocumentResponse>', '');
                }
            }

            if (reciboBienExitoso) {
                this.estObjectReciboBien = reciboBienExitoso.est_informacion_adicional;
                this.estObjectReciboBien.observacion = reciboBienExitoso.est_motivo_rechazo !== null ? reciboBienExitoso.est_motivo_rechazo : {};
                this.estObjectReciboBien.mensaje_resultado = reciboBienExitoso.est_mensaje_resultado;

            } else if(reciboBienFallido) {
                this.estObjectReciboBien = reciboBienFallido.est_informacion_adicional;
                this.estObjectReciboBien.observacion = reciboBienFallido.est_motivo_rechazo !== null ? reciboBienFallido.est_motivo_rechazo : {};
                this.estObjectReciboBien.mensaje_resultado = reciboBienFallido.est_mensaje_resultado;
            }

            if(this.estObjectReciboBien && this.estObjectReciboBien.created) {
                this.estObjectReciboBien.created = this.estObjectReciboBien.created.replace('<u:Created>', '');
                this.estObjectReciboBien.created = this.estObjectReciboBien.created.replace('</u:Created>', '');
            }

            if(this.estObjectReciboBien && this.estObjectReciboBien.ResponseCode) {
                let arrResponseCode = this.estObjectReciboBien.ResponseCode.split('<cbc:Description>');
                if (arrResponseCode.length > 0) {
                    this.estObjectReciboBien.code    = arrResponseCode[0].replace('<cac:DocumentResponse><cac:Response><cbc:ResponseCode>', '');
                    this.estObjectReciboBien.code    = this.estObjectReciboBien.code.replace('</cbc:ResponseCode>', '');
                    this.estObjectReciboBien.message = arrResponseCode[1].replace('</cbc:Description></cac:Response></cac:DocumentResponse>', '');
                }
            }

            if (aceptadoTacitamenteEstado) {
                this.estObjectAceptacionTacita.est_informacion_adicional = aceptadoTacitamenteEstado.est_informacion_adicional;
                this.estObjectAceptacionTacita.est_inicio_proceso = aceptadoTacitamenteEstado.est_inicio_proceso;
                this.estObjectAceptacionTacita.est_resultado = aceptadoTacitamenteEstado.est_resultado;
                this.estObjectAceptacionTacita.observacion = aceptadoTacitamenteEstado.est_motivo_rechazo && aceptadoTacitamenteEstado.est_motivo_rechazo.observacion !== null ? aceptadoTacitamenteEstado.est_motivo_rechazo.observacion : {};
                this.estObjectAceptacionTacita.est_mensaje_resultado = aceptadoTacitamenteEstado.est_mensaje_resultado;
            }else if (aceptadoEstado) {
                this.estObject = aceptadoEstado;
            } else if(aceptadoTacitamenteFallido) {
                this.estObjectAceptacionTacita.est_informacion_adicional = aceptadoTacitamenteFallido.est_informacion_adicional;
                this.estObjectAceptacionTacita.est_inicio_proceso = aceptadoTacitamenteFallido.est_inicio_proceso;
                this.estObjectAceptacionTacita.est_resultado = aceptadoTacitamenteFallido.est_resultado;
                this.estObjectAceptacionTacita.observacion = aceptadoTacitamenteFallido.est_motivo_rechazo && aceptadoTacitamenteFallido.est_motivo_rechazo.observacion !== null ? aceptadoTacitamenteFallido.est_motivo_rechazo.observacion : {};
                this.estObjectAceptacionTacita.est_mensaje_resultado = aceptadoTacitamenteFallido.est_mensaje_resultado;
            } else if (rechazadoEstado) {
                this.estObject = rechazadoEstado;
            }

            if(Object.entries(this.estObject).length === 0) {
                let estId = 0;

                if (aceptadoEstadoFallido && estId < aceptadoEstadoFallido.est_id) {
                    estId = aceptadoEstadoFallido.est_id;
                    this.estObject = aceptadoEstadoFallido;
                }
                
                if (aceptadoTacitamenteFallido && estId < aceptadoTacitamenteFallido.est_id) {
                    estId = aceptadoTacitamenteFallido.est_id;
                    this.estObject = aceptadoTacitamenteFallido;
                }

                if (rechazadoEstadoFallido && estId < rechazadoEstadoFallido.est_id) {
                    estId = rechazadoEstadoFallido.est_id;
                    this.estObject = rechazadoEstadoFallido;
                }
            }
            
            if(transmisionErp)
                this.estObjectTransmisionErp = transmisionErp;

            if(transmisionOpenComex)
                this.estObjectTransmisionOpenComex = transmisionOpenComex;

            if(estadoRdi)
                this.estObjectRdi = estadoRdi;

            if(ublAcuseReciboFallido)
                this.estObjectUblAcuseReciboFallido = ublAcuseReciboFallido;

            if(ublReciboBienFallido)
                this.estObjectUblReciboBienFallido = ublReciboBienFallido;

            if(ublAceptadoFallido)
                this.estObjectUblAceptadoFallido = ublAceptadoFallido;

            if(ublRechazadoFallido)
                this.estObjectUblRechazadoFallido = ublRechazadoFallido;

            if(ublAdAcuseReciboFallido)
                this.estObjectUblAdAcuseReciboFallido = ublAdAcuseReciboFallido;

            if(ublAdReciboBienFallido)
                this.estObjectUblAdReciboBienFallido = ublAdReciboBienFallido;

            if(ublAdAceptadoFallido)
                this.estObjectUblAdAceptadoFallido = ublAdAceptadoFallido;

            if(ublAdRechazadoFallido)
                this.estObjectUblAdRechazadoFallido = ublAdRechazadoFallido;

        } else if (this.documento.proceso === 'validacion-documentos') {
            this.findEstadosValidacion();
        } else if (this.documento.proceso === 'radian') {
            const getStatusEstado = this.findEstado('GETSTATUS');
            this.findEstadosValidacion();

            if (getStatusEstado) {
                this.mostrarAceptadoDian = true;

                this.estObjectDian = JSON.parse(getStatusEstado.est_object);
                this.errroMessage = (this.estObjectDian.ErrorMessage) ? this.configureMensaje(this.estObjectDian.ErrorMessage) : [];

                if (this.estObjectDian.ErrorMessage !== '')
                    this.mostrarAceptadoDianNotificacion = true;
            }

        } else {
            const xmlEstado                  = (this.documento.proceso == 'nomina-electronica') ? this.findEstado('XML') : this.findEstado('UBL');
            const dianEstado                 = this.findEstado('DO');
            const attachedDocumentEstado     = this.findEstado('UBLATTACHEDDOCUMENT');
            const notificacionEstado         = this.findEstado('NOTIFICACION');
            const registroRecepcionEstado    = this.findEstado('REGISTRORECEPCION');
            const acuseReciboExitoso         = this.findEstadoAcuseAceptadoRechazado('ACUSERECIBO', 'EXITOSO');
            const acuseReciboFallido         = this.findEstadoAcuseAceptadoRechazado('ACUSERECIBO', 'FALLIDO');
            const ReciboBienExitoso          = this.findEstadoAcuseAceptadoRechazado('RECIBOBIEN', 'EXITOSO');
            const ReciboBienFallido          = this.findEstadoAcuseAceptadoRechazado('RECIBOBIEN', 'FALLIDO');
            const aceptadoEstado             = this.findEstadoAcuseAceptadoRechazado('ACEPTACION', 'EXITOSO');
            const aceptadoEstadoFallido      = this.findEstadoAcuseAceptadoRechazado('ACEPTACION', 'FALLIDO');
            const aceptadoTacitamenteEstado  = this.findEstadoAcuseAceptadoRechazado('ACEPTACIONT', 'EXITOSO');
            const aceptadoTacitamenteFallido = this.findEstadoAcuseAceptadoRechazado('ACEPTACIONT', 'FALLIDO');
            const rechazadoEstado            = this.findEstadoAcuseAceptadoRechazado('RECHAZO', 'EXITOSO');
            const rechazadoEstadoFallido     = this.findEstadoAcuseAceptadoRechazado('RECHAZO', 'FALLIDO');
            const transmisionEdmEstado       = this.findEstado('TRANSMISION_EDM');
            this.estadoDoExitoso             = this.findEstadDoExitoso();

            if (this.estadoDoExitoso)
                this.mostrarValidacion = true;

            if (this.estadoDoExitoso) {
                this.estObject    = this.estadoDoExitoso.est_object;
                this.errroMessage = (this.estObject.ErrorMessage) ? this.configureMensaje(this.estObject.ErrorMessage) : [];

                if (this.estObject.ErrorMessage !== '')
                    this.mostrarNotificacionMensaje = true;
            }

            if (xmlEstado) {
                this.mostrarXmlUbl = true;
                this.xmlUblEstado  = xmlEstado.est_resultado;
                this.xmlUblMensaje = (xmlEstado.est_resultado === 'FALLIDO') ? this.configureMensaje(xmlEstado.est_mensaje_resultado) : (xmlEstado.est_resultado === 'EXITOSO') ? ['Proceso Realizado con Éxito'] : '';
            }

            if (dianEstado) {
                this.mostrarWsDian       = true;
                this.wsDianEstado        = dianEstado.est_resultado;
                this.wsDianProcesoEstado = dianEstado.est_resultado;
                this.wsDianMensaje       = (this.wsDianEstado === 'FALLIDO') ? this.configureMensaje(dianEstado.est_mensaje_resultado) : (this.wsDianEstado === 'EXITOSO') ? ['Proceso Realizado con Éxito'] : '';
            }

            if (attachedDocumentEstado) {
                this.mostrarAttachedDocument   = true;
                this.ublAttachedDocumentEstado = attachedDocumentEstado.est_resultado;
                this.attachedDocumentMensaje   = (attachedDocumentEstado.est_resultado === 'FALLIDO') ? this.configureMensaje(attachedDocumentEstado.est_mensaje_resultado) : (attachedDocumentEstado.est_resultado === 'EXITOSO') ? ['Proceso Realizado con Éxito'] : '';
            }

            if (notificacionEstado) {
                this.mostrarNotificacion = true;
                this.notificacionEstado  = notificacionEstado.est_resultado;
                this.notificacionMensaje = (notificacionEstado.est_resultado === 'FALLIDO') ? (notificacionEstado.est_mensaje_resultado !== null ? this.configureMensaje(notificacionEstado.est_mensaje_resultado) : ['Se presentaron errores al enviar correo de notificacion']) : ((notificacionEstado.est_resultado === 'EXITOSO') ? ['Proceso Realizado con Éxito' + (notificacionEstado.est_mensaje_resultado !== null ? (' - ' + this.configureMensaje(notificacionEstado.est_mensaje_resultado)) : '')] : '');
            }

            if (registroRecepcionEstado) {
                this.mostrarRegistroRecepcion = true;
                this.registroRecepcionEstado  = registroRecepcionEstado.est_resultado;
                this.registroRecepcionMensaje = (registroRecepcionEstado.est_resultado === 'FALLIDO') ? (registroRecepcionEstado.est_mensaje_resultado !== null ? this.configureMensaje(registroRecepcionEstado.est_mensaje_resultado) : ['Se presentaron errores al registrar el documento en recepción']) : ((registroRecepcionEstado.est_resultado === 'EXITOSO') ? ['Proceso Realizado con Éxito' + (registroRecepcionEstado.est_mensaje_resultado !== null ? (' - ' + this.configureMensaje(registroRecepcionEstado.est_mensaje_resultado)) : '')] : '');
            }

            if (transmisionEdmEstado) {
                this.mostrarTransmisionEdm = true;
                this.transmisionEdmEstado  = transmisionEdmEstado.est_resultado;
                this.transmisionEdmMensaje = (transmisionEdmEstado.est_resultado === 'FALLIDO') ? this.configureMensaje(transmisionEdmEstado.est_mensaje_resultado) : (transmisionEdmEstado.est_resultado === 'EXITOSO') ? ['Proceso Realizado con Éxito'] : '';
            }

            if (acuseReciboExitoso) {
                this.estObjectAcuse.acuseRecibo = acuseReciboExitoso.est_informacion_adicional;
            } else if(acuseReciboFallido) {
                this.estObjectAcuse.acuseRecibo = acuseReciboFallido.est_informacion_adicional;
            }

            if (ReciboBienExitoso) {
                this.estObjectReciboBien.reciboBien = ReciboBienExitoso.est_informacion_adicional;
            } else if(ReciboBienFallido) {
                this.estObjectReciboBien.reciboBien = ReciboBienFallido.est_informacion_adicional;
            }

            if (aceptadoEstado) {
                this.estObject.estadoFinal              = aceptadoEstado.est_informacion_adicional;
                this.estObject.estadoFinal.tituloEvento = 'Aceptación Expresa';
            } else if (aceptadoTacitamenteEstado) {
                this.estObject.estadoFinal              = aceptadoTacitamenteEstado.est_informacion_adicional;
                this.estObject.estadoFinal.tituloEvento = 'Aceptación Tácita';
            } else if (rechazadoEstado) {
                this.estObject.estadoFinal              = rechazadoEstado.est_informacion_adicional;
                this.estObject.estadoFinal.tituloEvento = 'Reclamo (Rechazo)'
            } else if (aceptadoEstadoFallido) {
                this.estObject.estadoFinal              = aceptadoEstadoFallido.est_informacion_adicional;
                this.estObject.estadoFinal.tituloEvento = 'Aceptación Expresa Fallida';
            } else if (aceptadoTacitamenteFallido) {
                this.estObject.estadoFinal              = aceptadoTacitamenteFallido.est_informacion_adicional ? aceptadoTacitamenteFallido.est_informacion_adicional : {};
                this.estObject.estadoFinal.tituloEvento = 'Aceptación Tácita Fallida';
                let arrErroresAceptacionTFallido        = this.documento.estados.find(est => est.est_estado === 'ACEPTACIONT' && est.est_resultado == 'FALLIDO');
                this.erroresAceptacionTFallido          = {...arrErroresAceptacionTFallido};
            } else if (rechazadoEstadoFallido) {
                this.estObject.estadoFinal               = rechazadoEstadoFallido.est_informacion_adicional;
                this.estObject.estadoFinal.tituloEvento  = 'Reclamo (Rechazo) Fallido'
            } 
        }
    }

    /**
     * Busca un estado en particular de la lista de estados.
     *
     * @param estado
     */
    private findEstado(estado) {
        let i = 0;
        while(i < this.estados.length) {
            if (this.estados[i].est_estado === estado)
                return this.estados[i];
            i++;
        }
        return null;
    }

    /**
     * Busca el estado DO éxitoso de la lista de estados.
     *
     * @param estado
     */
    private findEstadDoExitoso() {
        let i = 0;
        while(i < this.estados.length) {
            if (this.estados[i].est_estado === 'DO' && this.estados[i].est_resultado === 'EXITOSO')
                return this.estados[i];
            i++;
        }
        return null;
    }

    /**
     * Busca el estado solicitado de acuerdo al resultado.
     *
     * @param estado
     * @param resultado
     */
    private findEstadoAcuseAceptadoRechazado(estado, resultado) {
        let i = 0;
        while(i < this.estados.length) {
            if (this.estados[i].est_estado === estado && this.estados[i].est_resultado === resultado)
                return this.estados[i];
            i++;
        }
        return null;
    }

    /**
     * Busca el estado TRANSMISIONERP DEL PROCESO RECEPCION.
     *
     */
    private findEstadoTransmisionErp() {
        let i = this.estados.length-1;
        let estadoTransmisionErp = undefined;
        while(i >= 0) {
            if (this.estados[i].est_estado === 'TRANSMISIONERP')
                estadoTransmisionErp = this.estados[i];
            i--;
        }

        if(estadoTransmisionErp !== undefined)
            return estadoTransmisionErp;

        return null;
    }

    /**
     * Busca el estado OPENCOMEXCXP DEL PROCESO RECEPCION.
     *
     */
    private findEstadoTransmisionOpenComex() {
        let i = this.estados.length-1;
        let estadoExitoso = undefined;
        let estadoFallido = undefined;
        while(i >= 0) {
            if (this.estados[i].est_estado === 'OPENCOMEXCXP' && this.estados[i].est_resultado === 'EXITOSO')
                estadoExitoso = this.estados[i];
            else if (this.estados[i].est_estado === 'OPENCOMEXCXP' && this.estados[i].est_resultado === 'FALLIDO')
                estadoFallido = this.estados[i];
            i--;
        }

        if(estadoExitoso !== undefined)
            return estadoExitoso;
        else if(estadoExitoso === undefined && estadoFallido !== undefined)
            return estadoFallido;

        return null;
    }

    /**
     * Busca el estado RDI DEL PROCESO RECEPCION.
     *
     */
    private findEstadoRdi() {
        let i = 0;
        while(i < this.estados.length) {
            if (this.estados[i].est_estado === 'RDI' && this.estados[i].est_resultado === 'EXITOSO')
                return this.estados[i];
            i++;
        }
        return null;
    }

    /**
     * Obtiene la información de todos los estados de validación.
     *
     * @private
     * @memberof ModalResumenEstadosDocumentoComponent
     */
    private findEstadosValidacion() {
        this.estados.forEach(estado => {
            if (estado.est_estado === 'VALIDACION') {
                let estObjectValidacion = {
                    tituloEstado        : estado.est_resultado,
                    fechaEstado         : estado.fecha_creacion,
                    usuarioEstado       : estado.get_usuario_creacion.usu_nombre,
                    informacionAdicional: JSON.parse(estado?.est_informacion_adicional || "[]")
                }

                this.estadosValidacion.push(estObjectValidacion);
            }
        });
    }

    ngOnInit() {
    }

    /**
     * Cierra la ventana modal de Códigos de Postal.
     *
     */
    public closeModal(reload): void {
        this.modalRef.close();
    }

    /**
     * Retorna la etiqueta CUFE o CUDE segun el caso
     */
    public cudeCufeLabel() {
        if (this.documento && this.documento.proceso == 'nomina-electronica') {
            return 'CUNE';
        } else if (this.documento) {
            return this.documento.cdo_clasificacion === 'FC' ? 'CUFE' : 'CUDE';
        }
        return '';
    }

    /**
     * Retorna el CUFE|CUDE del documento
     */
    public getCufe() {
        if (this.documento && this.documento.proceso == 'nomina-electronica') {
            return  this.documento.cdn_cune;
        } else if (this.documento) {
            return  this.documento.cdo_cufe;
        }
        return '';
    }

}
