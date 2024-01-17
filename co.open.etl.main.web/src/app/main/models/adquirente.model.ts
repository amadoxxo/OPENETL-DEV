export interface Adquirente {
    adq_id: number;
    adq_identificacion?: string;
    adq_razon_social?: string;
    adq_identificacion_adq_razon_social?: string
}

export interface AdquirenteInteface {
    data: Adquirente[];
}