export interface TrackingInterface {

    /**
     * Gestiona el proceso de busqueda rapida.
     * @param buscar
     */
    onSearchInline(buscar: string);

    /**
     * Modifica el total de elementos a mostrar en la grid.
     * @param size
     */
    onChangeSizePage(size: number);

    /**
     * Evento para ordenar por una columna.
     * @param column
     * @param $order
     */
    onOrderBy(column: string, $order: string);

    /**
     * Cambio de Página en el tracking.
     * @param page
     */
    onPage(page);

    /**
     * Acciones en lote.
     * @param opcion
     * @param selected
     */
    onOptionMultipleSelected(opcion: any, selected: any[]);

    /**
     * Evento para ver un Item.
     * @param item
     */
    onViewItem(item: any);

    /**
     * Evento de para solicitar la eliminación de un Item.
     * @param item
     */
    onRequestDeleteItem(item: any);

    /**
     * Evento de Edicion de un Item.
     * @param item
     */
    onEditItem(item: any);

    /**
     * Evento para el cambio de estado de un Item.
     * @param item
     */
    onCambiarEstadoItem?(item: any);

    /**
     * Evento de configuración de documento electrónico.
     * @param item
     */
    onConfigurarDocumentoElectronico?(item: any);

    /**
     * Evento de configuración de documento soporte.
     * @param item
     */
    onConfigurarDocumentoSoporte?(item: any);

    /**
     * Evento de valores por defecto en documento.
     * @param item
     */
    onValoresPorDefectoEnDocumento?(item: any);

    /**
     * Evento para ver los usuarios asociados a un grupo de trabajo.
     * @param item
     */
    onViewUsuariosAsociados?(item: any);

    /**
     * Evento para ver los proveedores asociados a un grupo de trabajo.
     * @param item
     */
    onViewProveedoresAsociados?(item: any);

    /**
      * Evento de configuración de servicios.
      * @param item
      */
    onConfigurarServicios?(item: any); 

    /**
     * Evento de Descarga de un Item.
     * @param item
     */
    onDownloadItem?(item: any);

    /**
     * Evento de aperturar periodo de control de consecutivos.
     * @param item
     */
    onAperturarPeriodoControlConsecutivos?();
}

export interface TrackingOptionsInterface {
    editButton?: boolean;
    showButton?: boolean;
    deleteButton?: boolean;
    downloadButton?: boolean;
    portalesButton?: boolean;
    cambiarEstadoButton?: boolean;
    unableDownloadButton?: boolean;
    verUsuarioAsociadoButton?: boolean;
    verProveedorAsociadoButton?: boolean;
    configuracionServicioButton?: boolean;
    valoresPorDefectoEnDocumentoButton?: boolean;
    configuracionDocumentoSoporteButton?: boolean;
    configuracionDocumentoElectronicoButton?: boolean;
}

export interface TrackingColumnInterface {
    name: string;
    prop: string;
    sorteable: boolean;
    width?: number;
    align?: string;
}
