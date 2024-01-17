export interface GestionDocumentosTrackingInterface {
    /**
     * Modifica el total de elementos a mostrar en la grid.
     *
     * @param {number} size Cantidad de registros
     * @memberof GestionDocumentosTrackingInterface
     */
    onChangeSizePage(size: number);

    /**
     * Evento para ordenar por una columna.
     *
     * @param {string} column Columna a ordenar
     * @param {string} $order Dirección del ordenamiento
     * @memberof GestionDocumentosTrackingInterface
     */
    onOrderBy(column: string, $order: string);

    /**
     * Cambio de página en el tracking.
     * 
     * @param page
     */
    onPage(page);

    /**
     * Acciones en lote.
     *
     * @param {*} opcion Acción a realizar
     * @param {any[]} selected Registros seleccionados
     * @memberof GestionDocumentosTrackingInterface
     */
    onOptionMultipleSelected?(opcion: any, selected: any[]);

    /**
     * Evento para reenviar notificaciones de eventos.
     * 
     * @param item Información del registro
     */
    onReenvioNotificacion?(selected: any[], tiposEnvioCorreo);

    /**
     * Evento para descargar el excel de la grid.
     * 
     */
    onDescargarExcel();

    /**
     * Evento para la gestión Fe/Ds de uno o más registros.
     *
     * @param item Información del registro
     * @memberof GestionDocumentosTrackingInterface
     */
    onValidarGestionarFeDs?(item: any[]);

    /**
     * Evento para la asignación del centro de operación de uno o más registros.
     *
     * @param item Información del registro
     * @memberof GestionDocumentosTrackingInterface
     */
    onValidarAsignarCentroOperacion?(item: any[]);

    /**
     * Evento para la asignación del centro de costo de uno o más registros.
     *
     * @param item
     * @memberof GestionDocumentosTrackingInterface
     */
    onValidarAsignarCentroCosto?(item: any[]);

    /**
     * Evento para la asignación de los datos contabilizado de uno o más registros.
     *
     * @param item
     * @memberof GestionDocumentosTrackingInterface
     */
    onValidarDatosContabilizado?(item: any[]);

    /**
     * Evento para la asignación del centro de costo de uno o más registros.
     *
     * @param item Información del registro
     * @memberof GestionDocumentosTrackingInterface
     */
    onValidarSiguienteEtapa?(item: any[]);

    /**
     * Evento para manejar los click en los iconos de las opciones.
     * 
     * @param item
     */
    onOptionItem?(item: any, opcion: string);

    /**
     * Evento para agendar el reporte en background.
     * 
     */
    onAgendarReporteBackground?();
}

export interface GestionDocumentosTrackingColumnInterface {
    name     : string;
    prop     : string;
    sorteable: boolean;
    width?   : string;
    derecha? : boolean;
}
