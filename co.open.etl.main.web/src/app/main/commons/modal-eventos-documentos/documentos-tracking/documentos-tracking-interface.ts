export interface DocumentosTrackingInterface {

    /**
     * Gestiona el proceso de búsqueda rápida.
     * 
     * @param buscar
     */
    onSearchInline(buscar: string);

    /**
     * Modifica el total de elementos a mostrar en la grid.
     * 
     * @param size
     */
    onChangeSizePage(size: number);

    /**
     * Evento para ordenar por una columna.
     * 
     * @param column
     * @param $order
     */
    onOrderBy(column: string, $order: string);

    /**
     * Cambio de Página en el tracking.
     * 
     * @param page
     */
    onPage(page);

    /**
     * Acciones en lote.
     * 
     * @param opcion
     * @param selected
     */
    onOptionMultipleSelected(opcion: any, selected: any[]);

    /**
     * Evento para manejar los click en los iconos de las opciones.
     * 
     * @param item
     */
    onOptionItem(item: any, opcion: string);

    /**
     * Evento para descargar uno o varios documentos.
     * 
     * @param item
     */
    onDescargarItems(selected: any[], tiposDescargas);

    /**
     * Evento para enviar uno o varios documentos.
     * 
     * @param item
     */
    onEnviarItems(selected: any[], tiposEnvioCorreo);

    /**
     * Evento para reenviar notificaciones de eventos.
     * 
     * @param item
     */
    onReenvioNotificacion?(selected: any[], tiposEnvioCorreo);

    /**
     * Evento para descargar el excel de la grid.
     * 
     * @param item
     */
    onDescargarExcel();

    /**
     * Evento para agendar el reporte en background.
     * 
     * @param item
     */
    onAgendarReporteBackground?();
}

export interface DocumentosTrackingColumnInterface {
    name: string;
    prop: string;
    sorteable: boolean;
    width?: string;
    derecha?: boolean;
}
