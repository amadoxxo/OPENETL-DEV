export interface Tributo {
    tri_id: number;
    tri_codigo?: string;
    tri_nombre?: string;
    tri_descripcion?: string;
}

export interface TributoInteface {
    data: Tributo[];
}