export interface GrupoTrabajo {
    gtr_id: number;
    gtr_codigo?: string;
    gtr_nombre?: string;
    gtr_codigo_nombre?: string;
}

export interface GrupoTrabajoInteface {
    data: GrupoTrabajo[];
}