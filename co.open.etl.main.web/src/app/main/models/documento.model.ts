export interface Documento {
    cdo_id: number;
    cdo_lote?: string;
    cdo_consecutivo?: string;
    cdo_origen?: string;
}

export interface DocumentoInteface {
    data: Documento[];
}