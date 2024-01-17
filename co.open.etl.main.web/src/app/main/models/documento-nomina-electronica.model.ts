export interface DocumentoNominaElectronica {
    cdn_id: number;
    cdn_lote?: string;
    cdn_consecutivo?: string;
    cdn_origen?: string;
}

export interface DocumentoNominaElectronicaInteface {
    data: DocumentoNominaElectronica[];
}