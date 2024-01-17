export interface Trabajador {
    tra_id: number;
    tra_identificacion?: string;
    tra_nombre_completo?: string;
    tra_identificacion_tra_nombre_completo?: string
}

export interface TrabajadorInteface {
    data: Trabajador[];
}