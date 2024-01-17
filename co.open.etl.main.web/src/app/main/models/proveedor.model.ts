export interface Proveedor {
    pro_id            : number;
    pro_identificacion: string;
    pro_razon_social  : string;
    pro_identificacion_pro_razon_social: string;
}

export interface ProveedorInteface {
    data: Proveedor[];
}