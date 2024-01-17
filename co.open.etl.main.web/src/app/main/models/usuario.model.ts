export interface Usuario {
    usu_id: number;
    usu_identificacion?: string;
    usu_nombre?: string;
    usu_email?: string;
    usu_identificacion_nombre?: string;
}

export interface UsuarioInteface {
    data: Usuario[];
}