export interface ResponsabilidadFiscal {
    ref_id: number;
    ref_codigo: string;
    ref_descripcion: string;
}

export interface ResponsabilidadFiscalInteface {
    data: ResponsabilidadFiscal[];
}