export interface Departamento {
    dep_id: number;
    dep_codigo: string;
    dep_descripcion: string;
}

export interface DepartamentoInteface {
    data: Departamento[];
}