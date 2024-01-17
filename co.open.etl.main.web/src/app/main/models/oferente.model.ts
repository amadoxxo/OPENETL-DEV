export interface Oferente {
    ofe_id: number;
    ofe_identificacion?: string;
    ofe_razon_social?: string;
}

export interface OferenteInteface {
    data: Oferente[];
}