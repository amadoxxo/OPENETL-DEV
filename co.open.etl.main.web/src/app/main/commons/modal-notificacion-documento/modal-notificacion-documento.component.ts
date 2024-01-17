import {Component, Inject, OnInit} from '@angular/core';
import {BaseComponentView} from '../../core/base_component_view';
import {MAT_DIALOG_DATA, MatDialogRef} from '@angular/material/dialog';

import * as moment from 'moment';

class DataRecepcion {
    estado: string;
    fechaCreacion: string;
    correos: string;
    observacion: any;
    motivoRechazo?: string;
    mensajeResultado?: string;
}

@Component({
    selector: 'app-modal-notificacion-documento',
    templateUrl: './modal-notificacion-documento.component.html',
    styleUrls: ['./modal-notificacion-documento.component.scss']
})
export class ModalNotificacionDocumentoComponent extends BaseComponentView implements OnInit {
    parent: any;
    documento: any;
    titulo: string = '';
    proceso: string = '';
    estados: any [] = [];
    eventos: any [] = [];
    mostrarEventos: boolean = false;

    estadoNotificacionExitoso: any = null;

    fechas: string = '';
    correos: string = '';
    tamanoArchivoSuperior: string = '';

    // Propiedades recepción
    arrEstados: any [] = [];
    estadoNotificacionRecepcion: any = null;
    eventosRecepcion: Array<DataRecepcion>;

    moment = moment;

    /**
     * Constructor
     *
     * @param data
     * @param modalRef
     */
    constructor(@Inject(MAT_DIALOG_DATA) data,
                private modalRef: MatDialogRef<ModalNotificacionDocumentoComponent>) {
        super();
        this.documento = data.documento;
        this.titulo = 'Notificación del Documento ' +  data.documento.cdo_clasificacion + data.documento.documento.replace(' ', '-');
        this.estados = data.documento.estados;
        this.eventos = data.documento.eventos_notificacion;
        this.proceso = data.proceso;
        this.init();
    }

    /**
     * Codifica los mensajes de fallos con los correspondientes saltos de linea
     * @param msj
     */
    public configureMensaje(msj) {
        return msj.replace('//', '\n').replace('~', '\n');
    }

    /**
     * Configura la data a ser motrada en la modal
     */
    private init() {
        if (this.proceso === 'recepcion') {
            this.eventosRecepcion = [];
            this.estadoNotificacionRecepcion = this.findEstadoNotificacionRecepcion();

            if (this.estadoNotificacionRecepcion.length > 0) {
                this.estadoNotificacionRecepcion.forEach(property => {
                    const dataRecepcion = new DataRecepcion();
                    let tituloEstado = '';

                    switch (property.est_estado) {
                        case 'NOTACUSERECIBO': 
                            tituloEstado = 'ACUSE DE RECIBO'; 
                            break;
                        case 'NOTRECIBOBIEN': 
                            tituloEstado = 'RECIBO DEL BIEN'; 
                            break;
                        case 'NOTACEPTACION': 
                            tituloEstado = 'ACEPTACIÓN EXPRESA'; 
                            break;
                        case 'NOTRECHAZO': 
                            tituloEstado = 'RECLAMO (RECHAZO)'; 
                            break;
                        default:
                            break;
                    }
    
                    dataRecepcion.estado = tituloEstado;
                    dataRecepcion.fechaCreacion = property.fecha_creacion;
                    if(property.est_correos !== '')
                        dataRecepcion.correos = property.est_correos;
                    
                    if(property.est_motivo_rechazo && Object.keys(property.est_motivo_rechazo).length > 0) {
                        if (property.est_estado !== 'NOTRECHAZO' && property.est_motivo_rechazo.observacion && property.est_motivo_rechazo.observacion !== null && property.est_motivo_rechazo.observacion !== '')
                            dataRecepcion.observacion = property.est_motivo_rechazo.observacion;
                        else if (property.est_estado === 'NOTRECHAZO' && property.est_motivo_rechazo.motivo_rechazo && property.est_motivo_rechazo.motivo_rechazo !== null && property.est_motivo_rechazo.motivo_rechazo !== '')
                            dataRecepcion.motivoRechazo = property.est_motivo_rechazo.motivo_rechazo;
                    }

                    if(property.est_mensaje_resultado && property.est_mensaje_resultado !== null && property.est_mensaje_resultado !== '')
                        dataRecepcion.mensajeResultado = property.est_mensaje_resultado;

                    this.eventosRecepcion.push(dataRecepcion);
                });
            }
        } else {
            this.estadoNotificacionExitoso = this.findEstadoNotificacionExitoso();
            if (this.estadoNotificacionExitoso) {
                this.fechas = this.estadoNotificacionExitoso.fecha_creacion;
                if(this.estadoNotificacionExitoso.est_correos !== '')
                    this.correos = this.estadoNotificacionExitoso.est_correos;
                else
                    this.correos = this.estadoNotificacionExitoso.est_mensaje_resultado;
    
                if(this.estadoNotificacionExitoso.est_mensaje_resultado !== null && this.estadoNotificacionExitoso.est_mensaje_resultado.indexOf('zip adjunto al correo es superior a 2M') !== -1)
                    this.tamanoArchivoSuperior = this.estadoNotificacionExitoso.est_mensaje_resultado;
            }
    
            if(Object.keys(this.eventos).length > 0 && this.eventos.constructor === Object) {
                this.mostrarEventos = true;
            }
        }
    }

    /**
     * Busca un estado en particular de la lista de estados.
     *
     * @param estado
     */
    private findEstadoNotificacionExitoso() {
        let i = 0;
        while(i < this.estados.length) {
            if (this.estados[i].est_estado === 'NOTIFICACION' && this.estados[i].est_resultado === 'EXITOSO')
                return this.estados[i];
            i++;
        }
        return null;
    }

    /**
     * Busca un estado en particular de la lista de estados.
     *
     * @param estado
     */
    private findEstadoNotificacionRecepcion() {
        let i = 0;
        while(i < this.estados.length) {
            if (this.estados[i].est_estado === 'NOTACUSERECIBO' || this.estados[i].est_estado === 'NOTRECIBOBIEN' ||
                this.estados[i].est_estado === 'NOTACEPTACION' || this.estados[i].est_estado === 'NOTRECHAZO')
            {
                this.arrEstados[i] = this.estados[i];
            }
            i++;
        }
        return this.arrEstados;
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
}
