import {Injectable} from '@angular/core';
import {CanActivate, CanActivateChild, ActivatedRouteSnapshot, RouterStateSnapshot, UrlTree} from '@angular/router';
import {Observable} from 'rxjs';
import {MatSnackBar} from '@angular/material/snack-bar';
import {Auth} from './services/auth/auth.service';
import {JwtHelperService} from '@auth0/angular-jwt';
import {PERMISOSROLES} from './acl/permisos_roles';

@Injectable({
    providedIn: 'root'
})
export class AuthGuard implements CanActivate, CanActivateChild {
    public usuario: any;
    public permisosRoles: any = {};
    public acl: any;

    constructor(
        private _auth: Auth,
        private jwtHelperService: JwtHelperService,
        private snackBar: MatSnackBar
    ) {}

    /**
     * Evalua si una ruta específica puede ser activada.
     *
     * @param ActivatedRouteSnapshot route Ruta asociada al componente cargado
     * @param RouterStateSnapshot state Contiene el estado del router
     */
    canActivate(route: ActivatedRouteSnapshot, state: RouterStateSnapshot): Observable<boolean> | Promise<boolean> | boolean {
        this.permisosRoles = PERMISOSROLES;
        this.usuario       = this.jwtHelperService.decodeToken();
        this.acl           = this._auth.getAcls();

        // Si ha iniciado sesión
        if (this._auth.loggedIn()) {
            let url = route.url.join('/');
            // Si la url no esta prohibida
            if (!this.isForbidden(url)) {
                const permisos = route.data['permisos'] as Array<string>;
                if (route.data['permisos'] && route.data['permisos'].length > 0) {
                    if (this._auth.loggedIn() && this._auth.existePermiso(this.acl.permisos, permisos[0])) {
                        return true;
                    }
                } else {
                    return true;
                }
            } else
                this.snackBar.open('Ud. no está autorizado para acceder a esta sección', 'OK', {
                    duration: 2000, verticalPosition: 'top'
                });
        }
        this._auth.logout();
        return false;
    }

    /**
     * Evalua si una ruta específica puede ser activada en un módulo hijo.
     *
     * @param childRoute Información de la ruta asociada al componente cargado
     * @param state Contiene el estado del router
     */
    canActivateChild(childRoute: ActivatedRouteSnapshot, state: RouterStateSnapshot): Observable<boolean | UrlTree> | Promise<boolean | UrlTree> | boolean | UrlTree {
        this.permisosRoles = PERMISOSROLES;
        this.usuario = this.jwtHelperService.decodeToken();
        this.acl = this._auth.getAcls();

        if (this._auth.loggedIn()) {
            let url = state.url.slice(1);
            // Si la url no esta prohibida
            if (!this.isForbidden(url))
                return true;
            else
                this.snackBar.open('Ud. no está autorizado para acceder a esta sección', 'OK', {
                    duration: 2000, verticalPosition: 'top'
                });
        }
        this._auth.logout();
        return false;
    }

    /**
     * Permite validar si un usuario está autorizado para acceder a una ruta específica.
     *
     * @param url Ruta a validar
     */
    public isForbidden(url) {
        // Se quiere accesar a perfil de usuario pero no se está autorizado
        if (url.includes('perfil_usuario') && !this._auth.loggedIn())
            return true;

        // *********************** SISTEMA **************************

        // Se quiere accesar a variables del sistema pero no se está autorizado
        if (url.includes('sistema/variables-sistema') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.SistemaAdministracionVariablesSistema) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.SistemaAdministracionVariablesSistemaEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.SistemaAdministracionVariablesSistemaVer) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.SistemaAdministracionVariablesSistemaCambiarEstado)
            )
        )
            return true;

        // Se quiere accesar a festivos pero no se está autorizado
        if (url.includes('sistema/festivos') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.SistemaAdministracionFestivos) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.SistemaAdministracionFestivosNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.SistemaAdministracionFestivosEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.SistemaAdministracionFestivosVer) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.SistemaAdministracionFestivosCambiarEstado)
            )
        )
            return true;

        // Se quiere accesar a tiempos-aceptacion-tacita pero no se está autorizado
        if (url.includes('sistema/tiempos-aceptacion-tacita') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.SistemaAdministracionTiemposAceptacionTacita) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.SistemaAdministracionTiemposAceptacionTacitaNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.SistemaAdministracionTiemposAceptacionTacitaEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.SistemaAdministracionTiemposAceptacionTacitaVer) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.SistemaAdministracionTiemposAceptacionTacitaCambia)
            )        
        )
            return true;

        // Se quiere accesar a roles pero no se está autorizado
        if (url.includes('sistema/roles') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.AdministracionRolesUsuarios) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.AdministracionRolesUsuariosNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.AdministracionRolesUsuariosEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.AdministracionRolesUsuariosVer) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.AdministracionRolesUsuariosCambiarEstado)
            )
        )
            return true;

         // Se quiere accesar a nuevo rol pero no se está autorizado
        if (url.includes('sistema/roles/nuevo-rol') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.AdministracionRolesUsuariosNuevo))
            return true;

        // Se quiere accesar a editar rol pero no se está autorizado
        if (url.includes('sistema/roles/editar-rol') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.AdministracionRolesUsuariosEditar))
            return true;

        // Se quiere accesar a ver rol pero no se está autorizado
        if (url.includes('sistema/roles/ver-rol') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.AdministracionRolesUsuariosVer))
            return true;    

        // Se quiere accesar a usuarios pero no se está autorizado
        if (url.includes('sistema/usuarios') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.AdministracionUsuarios) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.AdministracionUsuariosVer) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.AdministracionUsuariosNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.AdministracionUsuariosEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.AdministracionUsuariosCambiarEstado) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.AdministracionUsuariosDescargarExcel) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.AdministracionUsuariosSubir) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.AdministracionUsuariosBajarUsuariosExcel)
            )
        )
            return true;

        // Se quiere accesar a nuevo usuario pero no se está autorizado
        if (url.includes('sistema/usuarios/nuevo-usuario') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.AdministracionUsuariosNuevo))
            return true;

        // Se quiere accesar a editar usuario pero no se está autorizado
        if (url.includes('sistema/usuarios/editar-usuario') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.AdministracionUsuariosEditar))
            return true;

        // Se quiere accesar a ver usuario pero no se está autorizado
        if (url.includes('sistema/usuarios/ver-usuario') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.AdministracionUsuariosVer))
            return true;

        // Se quiere accesar a subir usuarios pero no se está autorizado
        if (url.includes('sistema/usuarios/subir-usuarios') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.AdministracionUsuariosSubir))
            return true;

        // ******************** PARAMETROS ***********************    

        // Se quiere accesar a paises pero no se está autorizado
        if (url.includes('parametros/paises') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaPaises) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaPaisesVer) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaPaisesNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaPaisesEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaPaisesCambiarEstado)
            )
        )
            return true;

        // Se quiere accesar a departamentos pero no se está autorizado
        if (url.includes('parametros/departamentos') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaDepartamentos) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaDepartamentosVer) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaDepartamentosNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaDepartamentosEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaDepartamentosCambiarEstado)
            )
        )
            return true;

        // Se quiere accesar a municipios pero no se está autorizado
        if (url.includes('parametros/municipios') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaMunicipios) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaMunicipiosVer) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaMunicipiosNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaMunicipiosEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaMunicipiosCambiarEstado)
            )
        )
            return true;

        // Se quiere accesar a clasificación de productos pero no se está autorizado
        if (url.includes('parametros/clasificacion-productos') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaClasificacionProductos) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaClasificacionProductosVer) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaClasificacionProductosNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaClasificacionProductosEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaClasificacionProductosCambiarEstado)
            )
        )
            return true;

        // Se quiere accesar a codigos de descuentos} pero no se está autorizado
        if (url.includes('parametros/codigos-descuentos') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaCodigosDescuentos) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaCodigoDescuentoVer) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaCodigoDescuentoNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaCodigoDescuentoEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaCodigoDescuentoCambiarEstado)
            )
        )
            return true;

        // Se quiere accesar a codigos postales} pero no se está autorizado
        if (url.includes('parametros/codigos-postales') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaCodigosPostales) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaCodigoPostalVer) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaCodigoPostalNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaCodigoPostalEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaCodigoPostalCambiarEstado)
            )
        )
            return true;

        // Se quiere accesar a colombia compra eficiente pero no se está autorizado
        if (url.includes('parametros/colombia-compra-eficiente') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaColombiaCompraEficiente) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaColombiaCompraEficienteVer) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaColombiaCompraEficienteNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaColombiaCompraEficienteEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaColombiaCompraEficienteCambiarEstado)
            )
        )
            return true;

        // Se quiere accesar a formas de pago pero no se está autorizado
        if (url.includes('parametros/formas-pago') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaFormasPago) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaFormasPagoVer) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaFormasPagoNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaFormasPagoEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaFormasPagoCambiarEstado)
            )
        )
            return true;

        // Se quiere accesar a medios de pago pero no se está autorizado
        if (url.includes('parametros/medios-pago') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaMediosPago) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaMediosPagoVer) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaMediosPagoNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaMediosPagoEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaMediosPagoCambiarEstado)
            )
        )
            return true;

        // Se quiere accesar a mandatos pero no se está autorizado
        if (url.includes('parametros/mandatos') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaMandatos) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaMandatosVer) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaMandatosNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaMandatosEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaMandatosCambiarEstado)
            )
        )
            return true;
        
        // Se quiere accesar a documentos identificacion pero no se está autorizado
        if (url.includes('parametros/sector-salud/documentos-identificacion') &&
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaSectorSaludDocumentosIdentificacion) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaSectorSaludDocumentosIdentificacionVer) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaSectorSaludDocumentosIdentificacionNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaSectorSaludDocumentosIdentificacionEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaSectorSaludDocumentosIdentificacionCambiarEstado)
            )
        )
            return false;
        
        // Se quiere accesar a tipo usuario pero no se está autorizado
        if (url.includes('parametros/sector-salud/tipo-usuario') &&
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaSectorSaludTipoUsuario) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaSectorSaludTipoUsuarioVer) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaSectorSaludTipoUsuarioNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaSectorSaludTipoUsuarioEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaSectorSaludTipoUsuarioCambiarEstado)
            )
        )
            return true;

        // Se quiere accesar a mandatos pero no se está autorizado
        if (url.includes('parametros/sector-salud/modalidad-contratacion-pago') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaSectorSaludModalidades) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaSectorSaludModalidadesVer) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaSectorSaludModalidadesNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaSectorSaludModalidadesEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaSectorSaludModalidadesCambiarEstado)
            )
        )
            return true;

        // Se quiere accesar a cobertura pero no se está autorizado
        if (url.includes('parametros/sector-salud/cobertura') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaSectorSaludCobertura) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaSectorSaludCoberturaVer) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaSectorSaludCoberturaNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaSectorSaludCoberturaEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaSectorSaludCoberturaCambiarEstado)
            )
        )
            return true;

        // Se quiere accesar a Salud Documento Referenciado pero no se está autorizado
        if (url.includes('parametros/sector-salud/tipo-documentos-referenciados') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaSectorSaludDocumentoReferenciado) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaSectorSaludDocumentoReferenciadoVer) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaSectorSaludDocumentoReferenciadoNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaSectorSaludDocumentoReferenciadoEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaSectorSaludDocumentoReferenciadoCambiarEstado)
            )
        )
            return true;

        // Se quiere accesar a Transporte Registros pero no se está autorizado
        if (url.includes('parametros/sector-transporte/registro') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaSectorTransporteRegistro) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaSectorTransporteRegistroVer) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaSectorTransporteRegistroNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaSectorTransporteRegistroEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaSectorTransporteRegistroCambiarEstado)
            )
        )
            return true;

        // Se quiere accesar a Transporte Remesas pero no se está autorizado
        if (url.includes('parametros/sector-transporte/remesa') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaSectorTransporteRemesa) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaSectorTransporteRemesaVer) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaSectorTransporteRemesaNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaSectorTransporteRemesaEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaSectorTransporteRemesaCambiarEstado)
            )
        )
            return true;

        // Se quiere accesar a Control Cambiario Mandatos Profesional pero no se está autorizado
        if (url.includes('parametros/sector-cambiario/mandatos-profesional-cambios') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaSectorCambiarioMandatoProfesional) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaSectorCambiarioMandatoProfesionalVer) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaSectorCambiarioMandatoProfesionalNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaSectorCambiarioMandatoProfesionalEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaSectorCambiarioMandatoProfesionalCambiarEstado)
            )
        )
            return true;

        // Se quiere accesar a Control Cambiario Debida Diligencia pero no se está autorizado
        if (url.includes('parametros/sector-cambiario/debida-diligencia') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaSectorCambiarioDebidaDiligencia,) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaSectorCambiarioDebidaDiligenciaVer,) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaSectorCambiarioDebidaDiligenciaNuevo,) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaSectorCambiarioDebidaDiligenciaEditar,) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaSectorCambiarioDebidaDiligenciaCambiarEstado)
            )
        )
            return true;

        // Se quiere accesar a medios de pago pero no se está autorizado
        if (url.includes('parametros/condiciones-entrega') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaCondicionesEntrega) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaCondicionesEntregaVer) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaCondicionesEntregaNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaCondicionesEntregaEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaCondicionesEntregaCambiarEstado)
            )
        )
            return true;

        // Se quiere accesar a monedas pero no se está autorizado
        if (url.includes('parametros/monedas') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaMonedas) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaMonedasVer) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaMonedasNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaMonedasEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaMonedasCambiarEstado)
            )
        )
            return true;

        // Se quiere accesar a partidas arancelarias pero no se está autorizado
        if (url.includes('parametros/partidas-arancelarias') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaPartidasArancelarias) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaPartidasArancelariasVer) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaPartidasArancelariasNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaPartidasArancelariasEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaPartidasArancelariasCambiarEstado)
            )
        )
            return true;

        // Se quiere accesar a precios de referencia pero no se está autorizado
        if (url.includes('parametros/precios-referencia') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaPreciosReferencia) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaPreciosReferenciaVer) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaPreciosReferenciaNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaPreciosReferenciaEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaPreciosReferenciaCambiarEstado)
            )
        )
            return true;

        // Se quiere accesar a regimen fiscal pero no se está autorizado
        if (url.includes('parametros/regimen-fiscal') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaRegimenFiscal) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaRegimenFiscalVer) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaRegimenFiscalNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaRegimenFiscalEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaRegimenFiscalCambiarEstado)
            )
        )
            return true;

        // Se quiere accesar a responsabilidades fiscales pero no se está autorizado
        if (url.includes('parametros/responsabilidades-fiscales') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaResponsabilidadesFiscales) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaResponsabilidadesFiscalesVer) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaResponsabilidadesFiscalesNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaResponsabilidadesFiscalesEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaResponsabilidadesFiscalesCambiarEstado)
            )
        )
            return true;

        // Se quiere accesar a tipos de documentos electrónicos pero no se está autorizado -- Debe ir ante que tipos-documentos porque este valor lo ofuzca y nos saca
        if (url.includes('parametros/tipos-documentos-electronicos') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaTiposDocumentosElectronicos) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaTiposDocumentosElectronicosVer) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaTiposDocumentosElectronicosNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaTiposDocumentosElectronicosEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaTiposDocumentosElectronicosCambiarEstado)
            )
        )
            return true;

        // Se quiere accesar a tipos de documentos pero no se está autorizado
        if ( url !== 'parametros/tipos-documentos-electronicos' && url.includes('parametros/tipos-documentos') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaTiposDocumentos) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaTiposDocumentosVer) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaTiposDocumentosNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaTiposDocumentosEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaTiposDocumentosCambiarEstado)
            )
        )
            return true;

        // Se quiere accesar a tipo de organización jurídica pero no se está autorizado
        if (url.includes('parametros/tipos-organizacion-juridica') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaTiposOrganizacionJuridica) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaTiposOrganizacionJuridicaVer) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaTiposOrganizacionJuridicaNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaTiposOrganizacionJuridicaEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaTiposOrganizacionJuridicaCambiarEstado)
            )
        )
            return true;

        // Se quiere accesar a procedencia vendedor pero no se está autorizado
        if (url.includes('parametros/procedencia-vendedor') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaProcedenciaVendedor) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaProcedenciaVendedorVer) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaProcedenciaVendedorNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaProcedenciaVendedorEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaProcedenciaVendedorCambiarEstado)
            )
        )
            return true;

        // Se quiere accesar a tipos de operación pero no se está autorizado
        if (url.includes('parametros/tipos-operacion') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaTiposOperacion) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaTiposOperacionVer) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaTiposOperacionNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaTiposOperacionEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaTiposOperacionCambiarEstado)
            )
        )
            return true;

        // Se quiere accesar a tributos pero no se está autorizado
        if (url.includes('parametros/tributos') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaTributos) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaTributosVer) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaTributosNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaTributosEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaTributosCambiarEstado)
            )
        )
            return true;

        // Se quiere accesar a tarifas impuesto pero no se está autorizado
        if (url.includes('parametros/tarifas-impuesto') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaTarifasImpuesto) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaTarifasImpuestoVer) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaTarifasImpuestoNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaTarifasImpuestoEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaTarifasImpuestoCambiarEstado)
            )
        )
            return true;

        // Se quiere accesar a unidades pero no se está autorizado
        if (url.includes('parametros/unidades') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaUnidades) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaUnidadesVer) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaUnidadesNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaUnidadesEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaUnidadesCambiarEstado)
            )
        )
            return true;

        // Se quiere accesar a unidades pero no se está autorizado
        if (url.includes('parametros/referencia-otros-documentos') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaReferenciaOtrosDocumentos) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaReferenciaOtrosDocumentosVer) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaReferenciaOtrosDocumentosNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaReferenciaOtrosDocumentosEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaReferenciaOtrosDocumentosCambiarEstado)
            )
        )
            return true;

        // Se quiere accesar a conceptos corrección pero no se está autorizado
        if (url.includes('parametros/conceptos-correccion') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaConceptosCorreccion) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaConceptosCorreccionVer) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaConceptosCorreccionNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaConceptosCorreccionEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaConceptosCorreccionCambiarEstado)
            )
        )
            return true;

        // Se quiere accesar a conceptos de rechazo pero no se está autorizado
        if (url.includes('parametros/conceptos-rechazo') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaConceptosRechazo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaConceptosRechazoVer) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaConceptosRechazoNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaConceptosRechazoEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaConceptosRechazoCambiarEstado)
            )
        )
            return true;

        // Se quiere accesar a las formas de generación y transmisión pero no se está autorizado
        if (url.includes('parametros/formas-generacion-transmision') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaFormaGeneracionTransmision) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaFormaGeneracionTransmisionVer) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaFormaGeneracionTransmisionNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaFormaGeneracionTransmisionEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaFormaGeneracionTransmisionCambiarEstado)
            )
        )
            return true;

        // Se quiere accesar a ambiente destino documentos pero no se está autorizado
        if (url.includes('parametros/ambiente-destino-documentos') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaAmbienteDestinoDocumentos) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaAmbienteDestinoDocumentosVer) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaAmbienteDestinoDocumentosNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaAmbienteDestinoDocumentosEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaAmbienteDestinoDocumentosCambiarEstado)
            )
        )
            return true;

        // Se quiere accesar a periodo nomina pero no se está autorizado
        if (url.includes('parametros/nomina-electronica/nomina-periodos') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaNominaPeriodo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaNominaPeriodoVer) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaNominaPeriodoNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaNominaPeriodoEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaNominaPeriodoCambiarEstado)
            )
        )
            return true;

        // Se quiere accesar a periodo nomina pero no se está autorizado
        if (url.includes('parametros/nomina-electronica/tipo-contrato') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaTipoContrato) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaTipoContratoVer) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaTipoContratoNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaTipoContratoEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaTipoContratoCambiarEstado)
            )
        )
            return true;

        // Se quiere accesar a periodo nomina pero no se está autorizado
        if (url.includes('parametros/nomina-electronica/tipo-trabajador') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaTipoTrabajador) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaTipoTrabajadorVer) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaTipoTrabajadorNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaTipoTrabajadorEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaTipoTrabajadorCambiarEstado)
            )
        )
            return true;

        // Se quiere accesar a periodo nomina pero no se está autorizado
        if (url.includes('parametros/nomina-electronica/subtipo-trabajador') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaSubtipoTrabajador) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaSubtipoTrabajadorVer) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaSubtipoTrabajadorNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaSubtipoTrabajadorEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaSubtipoTrabajadorCambiarEstado)
            )
        )
            return true;

        // Se quiere accesar a periodo nomina pero no se está autorizado
        if (url.includes('parametros/nomina-electronica/tipo-hora-extra-recargo') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaTipoHoraExtraRecargo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaTipoHoraExtraRecargoVer) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaTipoHoraExtraRecargoNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaTipoHoraExtraRecargoEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaTipoHoraExtraRecargoCambiarEstado)
            )
        )
            return true;

        // Se quiere accesar a periodo nomina pero no se está autorizado
        if (url.includes('parametros/nomina-electronica/tipo-incapacidad') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaTipoIncapacidad) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaTipoIncapacidadVer) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaTipoIncapacidadNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaTipoIncapacidadEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaTipoIncapacidadCambiarEstado)
            )
        )
            return true;

        // Se quiere accesar a periodo nomina pero no se está autorizado
        if (url.includes('parametros/nomina-electronica/tipo-nota') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaTipoNota) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaTipoNotaVer) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaTipoNotaNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaTipoNotaEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaTipoNotaCambiarEstado)
            )
        )
            return true;

        // Se quiere accesar a referencia documentos electrónicos pero no se está autorizado
        if (url.includes('parametros/radian/referencia-documentos-electronicos') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaRadianReferenciaDocumentosElectronicos) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaRadianReferenciaDocumentosElectronicosVer) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaRadianReferenciaDocumentosElectronicosNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaRadianReferenciaDocumentosElectronicosEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaRadianReferenciaDocumentosElectronicosCambiarEstado)
            )
        )
            return true;

        // Se quiere accesar a tipos pagos pero no se está autorizado
        if (url.includes('parametros/radian/tipos-pagos') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaRadianTiposPagos) && 
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaRadianTiposPagosVer) && 
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaRadianTiposPagosNuevo) && 
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaRadianTiposPagosEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaRadianTiposPagosCambiarEstado)
            )
        )
            return true;


        // Se quiere accesar a tiempo mandatos pero no se está autorizado
        if (url.includes('parametros/radian/tiempo-mandato') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaRadianTiempoMandato) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaRadianTiempoMandatoVer) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaRadianTiempoMandatoNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaRadianTiempoMandatoEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaRadianTiempoMandatoCambiarEstado)
            )
        )
            return true;

        // Se quiere accesar a tipo mandatario pero no se está autorizado
        if (url.includes('parametros/radian/tipo-mandatario') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaRadianTipoMandatario) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaRadianTipoMandatarioVer) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaRadianTipoMandatarioNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaRadianTipoMandatarioEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaRadianTipoMandatarioCambiarEstado)
            )
        )
            return true;

        // Se quiere accesar a naturaleza mandato pero no se está autorizado
        if (url.includes('parametros/radian/naturaleza-mandato') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaRadianNaturalezaMandato) && 
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaRadianNaturalezaMandatoVer) && 
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaRadianNaturalezaMandatoNuevo) && 
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaRadianNaturalezaMandatoEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaRadianNaturalezaMandatoCambiarEstado)
            )
        )
            return true;

        // Se quiere accesar a tipo mandante pero no se está autorizado
        if (url.includes('parametros/radian/tipo-mandante') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaRadianTipoMandante) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaRadianTipoMandanteVer) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaRadianTipoMandanteNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaRadianTipoMandanteEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaRadianTipoMandanteCambiarEstado)
            )
        )
            return true;

        // Se quiere accesar a Evento Documento Electronico pero no se está autorizado
        if (url.includes('parametros/radian/evento-documento-electronico') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaRadianEventoDocumentoElectronico) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaRadianEventoDocumentoElectronicoVer) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaRadianEventoDocumentoElectronicoNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaRadianEventoDocumentoElectronicoEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaRadianEventoDocumentoElectronicoCambiarEstado)
            )
        )
            return true;

        // Se quiere accesar a Tipo Operacion pero no se está autorizado
        if (url.includes('parametros/radian/tipo-operacion') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaRadianTipoOperacion) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaRadianTipoOperacionVer) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaRadianTipoOperacionNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaRadianTipoOperacionEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaRadianTipoOperacionCambiarEstado)
            )
        )
            return true;

        // Se quiere accesar a Factor pero no se está autorizado
        if (url.includes('parametros/radian/factor') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaRadianFactor) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaRadianFactorVer) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaRadianFactorNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaRadianFactorEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaRadianFactorCambiarEstado)
            )
        )
            return true;

        // Se quiere accesar a Roles pero no se está autorizado
        if (url.includes('parametros/radian/roles') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaRadianRoles) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaRadianRolesVer) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaRadianRolesNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaRadianRolesEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaRadianRolesCambiarEstado)
            )
        )
            return true;

        // Se quiere accesar a Endoso pero no se está autorizado
        if (url.includes('parametros/radian/endoso') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaRadianEndoso) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaRadianEndosoVer) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaRadianEndosoNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaRadianEndosoEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaRadianEndosoCambiarEstado)
            )
        )
            return true;

        // Se quiere accesar a Alcance Mandato pero no se está autorizado
        if (url.includes('parametros/radian/alcance-mandato') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaRadianAlcanceMandato) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaRadianAlcanceMandatoVer) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaRadianAlcanceMandatoNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaRadianAlcanceMandatoEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ParametricaRadianAlcanceMandatoCambiarEstado)
            )
        )
            return true;

        // ******************** CONFIGURACIÓN ***********************

        if (
            url.includes('configuracion/adquirentes') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionAdquirentes) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionAdquirentesCambiarEstado) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionAdquirentesDescargarExcel) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionAdquirentesEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionAdquirentesNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionAdquirentesSubir) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionAdquirentesVer)
            )
        )
            return true;

        // Se quiere accesar a nuevo adquirente pero no se está autorizado
        if (url.includes('configuracion/adquirentes/nuevo-adquirente') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionAdquirentesNuevo))
            return true;

        // Se quiere accesar a editar adquirente pero no se está autorizado
        if (url.includes('configuracion/adquirentes/editar-adquirente') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionAdquirentesEditar))
            return true;

        // Se quiere accesar a ver adquirente pero no se está autorizado
        if (url.includes('configuracion/adquirentes/ver-adquirente') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionAdquirentesVer))
            return true;

        // Se quiere accesar a subir adquirentes pero no se está autorizado
        if (url.includes('configuracion/adquirentes/subir-adquirentes') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionAdquirentesSubir))
            return true;   

        if (
            url.includes('configuracion/autorizados') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionAutorizados) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionAutorizadosCambiarEstado) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionAutorizadosDescargarExcel) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionAutorizadosEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionAutorizadosNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionAutorizadosSubir) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionAutorizadosVer)
            )
        )
            return true;

        // Se quiere accesar a nuevo autorizado pero no se está autorizado
        if (url.includes('configuracion/autorizados/nuevo-autorizado') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionAutorizadosNuevo))
            return true;

        // Se quiere accesar a editar autorizado pero no se está autorizado
        if (url.includes('configuracion/autorizados/editar-autorizado') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionAutorizadosEditar))
            return true;

        // Se quiere accesar a ver autorizado pero no se está autorizado
        if (url.includes('configuracion/autorizados/ver-autorizado') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionAutorizadosVer))
            return true;

        // Se quiere accesar a subir autorizado pero no se está autorizado
        if (url.includes('configuracion/autorizados/subir-autorizados') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionAutorizadosSubir))
            return true; 

        if (
            url.includes('configuracion/vendedores') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionVendedorDS) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionVendedorDSCambiarEstado) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionVendedorDSDescargarExcel) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionVendedorDSEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionVendedorDSNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionVendedorDSSubir) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionVendedorDSVer)
            )
        )
            return true;

        // Se quiere accesar a nuevo vendedor pero no se está autorizado
        if (url.includes('configuracion/vendedores/nuevo-vendedor') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionVendedorDSNuevo))
            return true;

        // Se quiere accesar a editar vendedor pero no se está autorizado
        if (url.includes('configuracion/vendedores/editar-vendedor') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionVendedorDSEditar))
            return true;

        // Se quiere accesar a ver vendedor pero no se está autorizado
        if (url.includes('configuracion/vendedores/ver-vendedor') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionVendedorDSVer))
            return true;

        // Se quiere accesar a subir vendedores pero no se está autorizado
        if (url.includes('configuracion/vendedores/subir-vendedores') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionVendedorDSSubir))
            return true; 

        if (
            url.includes('configuracion/responsables') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionResponsables) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionResponsablesCambiarEstado) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionResponsablesDescargarExcel) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionResponsablesEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionResponsablesNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionResponsablesSubir) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionResponsablesVer)
            )
        )
            return true;

        // Se quiere accesar a nuevo responsable pero no se está autorizado
        if (url.includes('configuracion/responsables/nuevo-responsable') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionResponsablesNuevo))
            return true;

        // Se quiere accesar a editar responsable pero no se está autorizado
        if (url.includes('configuracion/responsables/editar-responsable') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionResponsablesEditar))
            return true;

        // Se quiere accesar a ver responsable pero no se está autorizado
        if (url.includes('configuracion/responsables/ver-responsable') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionResponsablesVer))
            return true;

        // Se quiere accesar a subir responsable pero no se está autorizado
        if (url.includes('configuracion/responsables/subir-responsables') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionResponsablesSubir))
            return true;   

        // Se quiere accesar a oferentes pero no se está autorizado    
        if (
            url.includes('configuracion/oferentes') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionOFE) && 
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionOFENuevo) && 
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionOFEEditar) && 
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionOFEVer) && 
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionOFECambiarEstado) && 
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionOFESubir) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfigurarDocumentoElectronico) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfigurarDocumentoSoporte) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfigurarServicios) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ValoresDefectoDocumento)
            )
        )
            return true;

        // Se quiere accesar a nuevo oferente pero no se está autorizado
        if (url.includes('configuracion/oferentes/nuevo-oferente') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionOFENuevo))
            return true;

        // Se quiere accesar a editar oferente pero no se está autorizado
        if (url.includes('configuracion/oferentes/editar-oferente') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionOFEEditar))
            return true;

        // Se quiere accesar a ver oferente pero no se está autorizado
        if (url.includes('configuracion/oferentes/ver-oferente') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionOFEVer))
            return true;

        // Se quiere accesar a la configuracion de servicios de un oferente pero no se está autorizado
        if (url.includes('configuracion/oferentes/configuracion-servicios') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfigurarServicios))
            return true;

        // Se quiere accesar a la configuracion del documento electrónico de un oferente pero no se está autorizado
        if (url.includes('configuracion/oferentes/configuracion-documento-electronico') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfigurarDocumentoElectronico))
            return true;

        // Se quiere accesar a la configuracion del documento soporte de un oferente pero no se está autorizado
        if (url.includes('configuracion/oferentes/configuracion-documento-soporte') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfigurarDocumentoSoporte))
            return true;

        // Se quiere accesar a los valores por defecto del documento electrónico de un oferente pero no se está autorizado
        if (url.includes('configuracion/oferentes/valores-por-defecto-documento-electronico') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ValoresDefectoDocumento))
            return true;

        // Se quiere accesar sobre actores pero no se está autorizado    
        if (
            url.includes('configuracion/radian-actores') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionRadianActores) && 
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionRadianActoresNuevo) && 
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionRadianActoresEditar) && 
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionRadianActoresVer) && 
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionRadianActoresCambiarEstado) && 
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionRadianActoresSubir) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionRadianActoresDescargarExcel)
            )
        )
            return true;

        // Se quiere accesar a nuevo actor radian pero no se está autorizado
        if (url.includes('configuracion/radian-actores/nuevo-actor') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionRadianActoresNuevo))
            return true;

        // Se quiere accesar a editar actor radian pero no se está autorizado
        if (url.includes('configuracion/radian-actores/editar-actor') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionRadianActoresEditar))
            return true;

        // Se quiere accesar a ver actor radian pero no se está autorizado
        if (url.includes('configuracion/radian-actores/ver-actor') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionRadianActoresVer))
            return true;

        // Se quiere accesar a subir actores de Radian pero no está autorizado
        if (url.includes('configuracion/radian-actores/subir-actor') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionRadianActoresSubir))
            return true;

        // Se quiere accesar a software proveedor tecnológico pero no se está autorizado
        if (
            url.includes('configuracion/software-proveedor-tecnologico') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.SoftwareProveedorTecnologico) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.SoftwareProveedorTecnologicoNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.SoftwareProveedorTecnologicoEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.SoftwareProveedorTecnologicoVer) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.SoftwareProveedorTecnologicoSubir) && 
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.SoftwareProveedorTecnologicoCambiarEstado)
            )
        )
            return true;

        // Se quiere accesar a nuevo software proveedor tecnológico pero no se está autorizado
        if (url.includes('configuracion/software-proveedor-tecnologico/nuevo-software-proveedor-tecnologico') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.SoftwareProveedorTecnologicoNuevo))
            return true;

        // Se quiere accesar a editar software proveedor tecnológico pero no se está autorizado
        if (url.includes('configuracion/software-proveedor-tecnologico/editar-software-proveedor-tecnologico') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.SoftwareProveedorTecnologicoEditar))
            return true;

        // Se quiere accesar a ver software proveedor tecnológico pero no se está autorizado
        if (url.includes('configuracion/software-proveedor-tecnologico/ver-software-proveedor-tecnologico') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.SoftwareProveedorTecnologicoVer))
            return true; 

        // Se quiere accesar a subir software proveedor tecnológico pero no se está autorizado
        if (url.includes('configuracion/software-proveedor-tecnologico/subir-software-proveedor-tecnologico') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.SoftwareProveedorTecnologicoSubir))
            return true;   

        // Se quiere accesar a administración grupos de trabajo pero no se está autorizado
        if (
            url.includes('configuracion/grupos-trabajo/administracion') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionGrupoTrabajoAdministracion) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionGrupoTrabajoAdministracionNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionGrupoTrabajoAdministracionEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionGrupoTrabajoAdministracionCambiarEstado) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionGrupoTrabajoAdministracionSubir) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionGrupoTrabajoAdministracionVer) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionGrupoTrabajoAdministracionDescargarExcel)
            )
        )
            return true; 

        // Se quiere accesar a subir administración grupos de trabajo pero no se está autorizado
        if (url.includes('configuracion/grupos-trabajo/administracion/subir-grupos-trabajo') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionGrupoTrabajoAdministracionSubir))
            return true;

        // Se quiere accesar a grupos de trabajo - asociar usuarios pero no se está autorizado
        if (
            url.includes('configuracion/grupos-trabajo/asociar-usuarios') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionGrupoTrabajoAsociarUsuario) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionGrupoTrabajoAsociarUsuarioNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionGrupoTrabajoAsociarUsuarioCambiarEstado) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionGrupoTrabajoAsociarUsuarioSubir) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionGrupoTrabajoAsociarUsuarioDescargarExcel) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionGrupoTrabajoAsociarUsuarioVerUsuariosAsociados)
            )
        )
            return true;

        // Se quiere accesar a subir grupos de trabajo - asociar usuarios pero no se está autorizado
        if (url.includes('configuracion/grupos-trabajo/asociar-usuarios/subir-usuarios-asociados') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionGrupoTrabajoAsociarUsuarioSubir))
            return true;

        // Se quiere accesar a grupos de trabajo - asociar proveedores pero no se está autorizado
        if (
            url.includes('configuracion/grupos-trabajo/asociar-proveedores') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionGrupoTrabajoAsociarProveedor) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionGrupoTrabajoAsociarProveedorNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionGrupoTrabajoAsociarProveedorCambiarEstado) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionGrupoTrabajoAsociarProveedorSubir) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionGrupoTrabajoAsociarProveedorDescargarExcel) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionGrupoTrabajoAsociarProveedorVerProveedoresAsociados)
            )
        )
            return true;

        // Se quiere accesar a subir grupos de trabajo - asociar proveedores pero no se está autorizado
        if (url.includes('configuracion/grupos-trabajo/asociar-proveedores/subir-proveedores-asociados') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionGrupoTrabajoAsociarProveedorSubir))
            return true;

        // Se quiere accesar a xpath documentos electrónicos estándar pero no se está autorizado
        if (
            url.includes('configuracion/xpath-documentos-electronicos/estandar') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionXPathDEEstandar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionXPathDEEstandarNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionXPathDEEstandarEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionXPathDEEstandarVer) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionXPathDEEstandarCambiarEstado) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionXPathDEEstandarDescargar)
            )
        )
            return true;

        // Se quiere accesar a xpath documentos electrónicos personalizados pero no se está autorizado
        if (
            url.includes('configuracion/xpath-documentos-electronicos/personalizados') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionXPathDEPersonalizado) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionXPathDEPersonalizadoNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionXPathDEPersonalizadoVer) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionXPathDEPersonalizadoEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionXPathDEPersonalizadoCambiarEstado) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionXPathDEPersonalizadoDescargar)
            )
        )
            return true;

        // Se quiere accesar a resoluciones de facturación pero no se está autorizado
        if (
            url.includes('configuracion/resoluciones-facturacion') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionResolucionesFacturacion) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionResolucionesFacturacionCambiarEstado) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionResolucionesFacturacionEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionResolucionesFacturacionNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionResolucionesFacturacionSubir) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionResolucionesFacturacionVer)
            )
        )
            return true; 

        // Se quiere accesar a nuevo resoluciones de facturación pero no se está autorizado
        if (url.includes('configuracion/resoluciones-facturacion/nueva-resolucion-facturacion') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionResolucionesFacturacionNuevo))
            return true;

        // Se quiere accesar a editar resoluciones de facturación pero no se está autorizado
        if (url.includes('configuracion/resoluciones-facturacion/editar-resolucion-facturacion') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionResolucionesFacturacionEditar))
            return true;

        // Se quiere accesar a ver resoluciones de facturación pero no se está autorizado
        if (url.includes('configuracion/resoluciones-facturacion/ver-resolucion-facturacion') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionResolucionesFacturacionVer))
            return true; 

        // Se quiere accesar a subir resoluciones de facturación pero no se está autorizado
        if (url.includes('configuracion/resoluciones-facturacion/subir-resoluciones-facturacion') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionResolucionesFacturacionSubir))
            return true;    

        if (
            url.includes('configuracion/proveedores') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionProveedores) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionProveedoresCambiarEstado) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionProveedoresEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionProveedoresNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionProveedoresSubir) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionProveedoresVer)
            )
        )
            return true;

        // Se quiere accesar a nuevo proveedor pero no se está autorizado
        if (url.includes('configuracion/proveedores/nuevo-proveedor') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionProveedoresNuevo))
            return true;

        // Se quiere accesar a editar proveedor pero no se está autorizado
        if (url.includes('configuracion/proveedores/editar-proveedor') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionProveedoresEditar))
            return true;

        // Se quiere accesar a ver proveedor pero no se está autorizado
        if (url.includes('configuracion/proveedores/ver-proveedor') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionProveedoresVer))
            return true;

        // Se quiere accesar a subir proveedores pero no se está autorizado
        if (url.includes('configuracion/proveedores/subir-proveedores') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionProveedoresSubir))
            return true;   

        // Se quiere accesar a autorizaciones eventos DIAN pero no se está autorizado
        if (
            url.includes('configuracion/autorizaciones-eventos-dian') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionAutorizacionesEventosDian) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionAutorizacionesEventosDianNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionAutorizacionesEventosDianEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionAutorizacionesEventosDianVer) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionAutorizacionesEventosDianCambiarEstado) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionAutorizacionesEventosDianSubir) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionAutorizacionesEventosDianDescargarExcel)
            )
        )
            return true;

        // Se quiere accesar a nuevo usuarios eventos pero no se está autorizado
        if (url.includes('configuracion/autorizaciones-eventos-dian/nuevo-autorizaciones-eventos-dian') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionAutorizacionesEventosDianNuevo))
            return true;

        // Se quiere accesar a editar usuarios eventos pero no se está autorizado
        if (url.includes('configuracion/autorizaciones-eventos-dian/editar-autorizaciones-eventos-dian') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionAutorizacionesEventosDianEditar))
            return true;

        // Se quiere accesar a ver usuarios eventos pero no se está autorizado
        if (url.includes('configuracion/autorizaciones-eventos-dian/ver-autorizaciones-eventos-dian') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionAutorizacionesEventosDianVer))
            return true; 

        // Se quiere accesar a subir usuarios eventos pero no se está autorizado
        if (url.includes('configuracion/autorizaciones-eventos-dian/subir-autorizaciones-eventos-dian') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionAutorizacionesEventosDianSubir))
            return true;

        // Se quiere accesar a administracion recepcion ERP pero no se está autorizado
        if (
            url.includes('configuracion/administracion-recepcion-erp') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionAdministracionRecepcionERP) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionAdministracionRecepcionERPNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionAdministracionRecepcionERPEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionAdministracionRecepcionERPVer) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionAdministracionRecepcionERPCambiarEstado) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionAdministracionRecepcionERPSubir) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionAdministracionRecepcionERPDescargarExcel)
            )
        )
            return true;

        // Se quiere accesar a nuevo administracion recepcion ERP pero no se está autorizado
        if (url.includes('configuracion/administracion-recepcion-erp/nuevo-administracion-recepcion-erp') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionAdministracionRecepcionERPNuevo))
            return true;

        // Se quiere accesar a editar administracion recepcion ERP pero no se está autorizado
        if (url.includes('configuracion/administracion-recepcion-erp/editar-administracion-recepcion-erp') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionAdministracionRecepcionERPEditar))
            return true;

        // Se quiere accesar a ver administracion recepcion ERP pero no se está autorizado
        if (url.includes('configuracion/administracion-recepcion-erp/ver-administracion-recepcion-erp') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionAdministracionRecepcionERPVer))
            return true; 

        // Se quiere accesar a subir administracion recepcion ERP pero no se está autorizado
        if (url.includes('configuracion/administracion-recepcion-erp/subir-administracion-recepcion-erp') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionAdministracionRecepcionERPSubir))
            return true;

        // Se quiere accesar a centros costo pero no se está autorizado
        if (
            url.includes('configuracion/recepcion/centros-costo') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionCentrosCosto) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionCentrosCostoNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionCentrosCostoEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionCentrosCostoVer) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionCentrosCostoCambiarEstado)
            )
        )
            return true;

        // Se quiere accesar a causales devolucion pero no se está autorizado
        if (
            url.includes('configuracion/recepcion/causales-devolucion') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionCausalesDevolucion) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionCausalesDevolucionNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionCausalesDevolucionEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionCausalesDevolucionVer) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionCausalesDevolucionCambiarEstado)
            )
        )
            return true;

        // Se quiere accesar a centros operacion pero no se está autorizado
        if (
            url.includes('configuracion/recepcion/centros-operacion') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionCentrosOperacion) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionCentrosOperacionNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionCentrosOperacionEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionCentrosOperacionVer) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionCentrosOperacionCambiarEstado)
            )
        )
            return true;

        // Se quiere accesar a usuarios ecm pero no se está autorizado
        if (url.includes('configuracion/usuarios-ecm') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionUsuarioEcm) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionUsuarioEcmNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionUsuarioEcmEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionUsuarioEcmVer) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionUsuarioEcmCambiarEstado) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionUsuarioEcmSubir) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionUsuarioEcmDescargarExcel)
            )
        )
            return true; 

        // Se quiere accesar a nuevo usuarios ecm pero no se está autorizado
        if (url.includes('configuracion/usuarios-ecm/nuevo-usuario-ecm') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionUsuarioEcmNuevo))
            return true;

        // Se quiere accesar a editar usuarios ecm pero no se está autorizado
        if (url.includes('configuracion/usuarios-ecm/editar-usuario-ecm') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionUsuarioEcmEditar))
            return true;

        // Se quiere accesar a ver usuarios ecm pero no se está autorizado
        if (url.includes('configuracion/usuarios-ecm/ver-usuario-ecm') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionUsuarioEcmVer))
            return true; 

        // Se quiere accesar a subir usuarios ecm pero no se está autorizado
        if (url.includes('configuracion/usuarios-ecm/subir-usuarios-ecm') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionUsuarioEcmSubir))
            return true; 

        // Se quiere accesar a la administracion de recepcion Fondos que aplica a FNC pero no se está autorizado
        if (
            url.includes('configuracion/recepcion/fondos') && 
            (
                (
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionRecepcionFondos) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionRecepcionFondosNuevo) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionRecepcionFondosEditar) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionRecepcionFondosVer) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionRecepcionFondosCambiarEstado)
                ) || (this.usuario.ofe_recepcion !== 'SI' || (this.usuario.ofe_recepcion === 'SI' && this.usuario.ofe_recepcion_fnc !== 'SI'))
            )
        )
            return true; 

        // Se quiere accesar anómina electrónica - empleadores pero no se está autorizado
        if (url.includes('configuracion/nomina-electronica/empleadores') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionDnEmpleador) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionDnEmpleadorNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionDnEmpleadorEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionDnEmpleadorVer) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionDnEmpleadorCambiarEstado) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionDnEmpleadorSubir) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionDnEmpleadorDescargarExcel)
            )
        )
            return true;

        // Se quiere accesar a nuevo empleadores pero no se está autorizado
        if (url.includes('configuracion/nomina-electronica/empleadores/nuevo-empleador') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionDnEmpleadorNuevo))
            return true;

        // Se quiere accesar a editar empleadores pero no se está autorizado
        if (url.includes('configuracion/nomina-electronica/empleadores/editar-empleador') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionDnEmpleadorEditar))
            return true;

        // Se quiere accesar a ver empleadores pero no se está autorizado
        if (url.includes('configuracion/nomina-electronica/empleadores/ver-empleador') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionDnEmpleadorVer))
            return true; 

        // Se quiere accesar a subir empleadores pero no se está autorizado
        if (url.includes('configuracion/nomina-electronica/empleadores/subir-empleadores') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionDnEmpleadorSubir))
            return true;

        // Se quiere accesar a nómina electrónica - trabajadores pero no se está autorizado
        if (url.includes('configuracion/nomina-electronica/trabajadores') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionDnTrabajador) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionDnTrabajadorNuevo) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionDnTrabajadorEditar) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionDnTrabajadorVer) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionDnTrabajadorCambiarEstado) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionDnTrabajadorSubir) &&
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionDnTrabajadorDescargarExcel)
            )
        )
            return true;

        // Se quiere accesar a nuevo trabajadores pero no se está autorizado
        if (url.includes('configuracion/nomina-electronica/trabajadores/nuevo-trabajador') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionDnTrabajadorNuevo))
            return true;

        // Se quiere accesar a editar trabajadores pero no se está autorizado
        if (url.includes('configuracion/nomina-electronica/trabajadores/editar-trabajador') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionDnTrabajadorEditar))
            return true;

        // Se quiere accesar a ver trabajadores pero no se está autorizado
        if (url.includes('configuracion/nomina-electronica/trabajadores/ver-trabajador') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionDnTrabajadorVer))
            return true; 

        // Se quiere accesar a subir trabajadores pero no se está autorizado
        if (url.includes('configuracion/nomina-electronica/trabajadores/subir-trabajadores') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionDnTrabajadorSubir))
            return true;

        // Se quiere accesar a Configuración Reportes - Reportes en Background pero no se está autorizado
        if (url.includes('configuracion/reportes/background') &&  !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ConfiguracionAdquirentesDescargarExcel))
            return true;

        // ******************** FACTURACIÓN WEB ***********************
        // Se quiere accesar a Facturación Web - Parámetros - Control Consecutivos
        if (
            url.includes('facturacion-web/parametros/control-consecutivos') &&
            (
                (
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.FacturacionWebControlConsecutivosNuevo) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.FacturacionWebControlConsecutivosEditar) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.FacturacionWebControlConsecutivosVer) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.FacturacionWebControlConsecutivosCambiarEstado)
                ) ||
                (this.usuario.ofe_emision !== 'SI' && this.usuario.ofe_documento_soporte !== 'SI')
            )
        )
            return true;  

        // Se quiere accesar a Facturación Web - Parámetros - Cargos
        if (
            url.includes('facturacion-web/parametros/cargos') &&
            (
                (
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.FacturacionWebCargosNuevo) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.FacturacionWebCargosEditar) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.FacturacionWebCargosVer) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.FacturacionWebCargosCambiarEstado) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.FacturacionWebCargosSubir)
                ) ||
                (this.usuario.ofe_emision !== 'SI' && this.usuario.ofe_documento_soporte !== 'SI')
            )
        )
            return true;  

        // Se quiere accesar a subir cargos pero no se está autorizado
        if (url.includes('facturacion-web/parametros/cargos/subir') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.FacturacionWebCargosSubir))
            return true;   

        // Se quiere accesar a Facturación Web - Parámetros - Descuentos
        if (
            url.includes('facturacion-web/parametros/descuentos') &&
            (
                (
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.FacturacionWebDescuentosNuevo) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.FacturacionWebDescuentosEditar) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.FacturacionWebDescuentosVer) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.FacturacionWebDescuentosCambiarEstado) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.FacturacionWebDescuentosSubir)
                ) ||
                (this.usuario.ofe_emision !== 'SI' && this.usuario.ofe_documento_soporte !== 'SI')
            )
        )
            return true;

        // Se quiere accesar a subir descuentos pero no se está autorizado
        if (url.includes('facturacion-web/parametros/descuentos/subir') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.FacturacionWebDescuentosSubir))
            return true;

        // Se quiere accesar a Facturación Web - Parámetros - Productos
        if (
            url.includes('facturacion-web/parametros/productos') &&
            (
                (
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.FacturacionWebProductosNuevo, false) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.FacturacionWebProductosEditar, false) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.FacturacionWebProductosVer, false) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.FacturacionWebProductosCambiarEstado, false) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.FacturacionWebProductosSubir, false)
                ) ||
                (this.usuario.ofe_emision !== 'SI' && this.usuario.ofe_documento_soporte !== 'SI')
            )
        )
            return true;  

        // Se quiere accesar a nuevo producto pero no se está autorizado
        if (url.includes('facturacion-web/parametros/productos/nuevo') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.FacturacionWebProductosNuevo))
            return true;   

        // Se quiere accesar a editar producto pero no se está autorizado
        if (url.includes('facturacion-web/parametros/productos/editar') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.FacturacionWebProductosEditar))
            return true;   

        // Se quiere accesar a ver producto pero no se está autorizado
        if (url.includes('facturacion-web/parametros/productos/ver') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.FacturacionWebProductosVer))
            return true;   

        // Se quiere accesar a cambiar estado producto pero no se está autorizado
        if (url.includes('facturacion-web/parametros/productos/cambiar-estado') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.FacturacionWebProductosCambiarEstado))
            return true;

        // Se quiere accesar a subir productos pero no se está autorizado
        if (url.includes('facturacion-web/parametros/productos/subir') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.FacturacionWebProductosSubir))
            return true;   

        // Se quiere accesar a Facturación Web - Nuevo Documento - Factura pero no se está autorizado
        if (
            url.includes('facturacion-web/crear-documento/factura') &&
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.FacturacionWebCrearFactura, false) ||
                this.usuario.ofe_emision !== 'SI'
            )
        )
            return true;

        // Se quiere accesar a Facturación Web - Editar Documento - Factura pero no se está autorizado
        if (
            url.includes('facturacion-web/editar-documento/factura') &&
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.FacturacionWebEditarFactura, false) ||
                this.usuario.ofe_emision !== 'SI'
            )
        )
            return true;

        // Se quiere accesar a Facturación Web - Ver Documento - Factura pero no se está autorizado
        if (
            url.includes('facturacion-web/ver-documento/factura') &&
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.FacturacionWebVerFactura, false) ||
                this.usuario.ofe_emision !== 'SI'
            )
        )
            return true;

        // Se quiere accesar a Facturación Web - Nuevo Documento - Nota Crédito pero no se está autorizado
        if (
            url.includes('facturacion-web/crear-documento/nota-credito') &&
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.FacturacionWebCrearNotaCredito, false) ||
                this.usuario.ofe_emision !== 'SI'
            )
        )
            return true;

        // Se quiere accesar a Facturación Web - Editar Documento - Nota Crédito pero no se está autorizado
        if (
            url.includes('facturacion-web/editar-documento/nota-credito') &&
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.FacturacionWebEditarNotaCredito, false) ||
                this.usuario.ofe_emision !== 'SI'
            )
        )
            return true;

        // Se quiere accesar a Facturación Web - Ver Documento - Nota Crédito pero no se está autorizado
        if (
            url.includes('facturacion-web/ver-documento/nota-credito') &&
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.FacturacionWebVerNotaCredito, false) ||
                this.usuario.ofe_emision !== 'SI'
            )
        )
            return true;

        // Se quiere accesar a Facturación Web - Nuevo Documento - Nota Débito pero no se está autorizado
        if (
            url.includes('facturacion-web/crear-documento/nota-debito') &&
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.FacturacionWebCrearNotaDebito, false) ||
                this.usuario.ofe_emision !== 'SI'
            )
        )
            return true;

        // Se quiere accesar a Facturación Web - Editar Documento - Nota Débito pero no se está autorizado
        if (
            url.includes('facturacion-web/editar-documento/nota-debito') &&
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.FacturacionWebEditarNotaDebito, false) ||
                this.usuario.ofe_emision !== 'SI'
            )
        )
            return true;

        // Se quiere accesar a Facturación Web - Ver Documento - Nota Débito pero no se está autorizado
        if (
            url.includes('facturacion-web/ver-documento/nota-debito') &&
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.FacturacionWebVerNotaDebito, false) ||
                this.usuario.ofe_emision !== 'SI'
            )
        )
            return true;

        // Se quiere accesar a Facturación Web - Crear Documento - Documento Soporte pero no se está autorizado
        if (
            url.includes('facturacion-web/crear-documento/documento-soporte') &&
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.FacturacionWebCrearDocumentoSoporte, false) ||
                this.usuario.ofe_documento_soporte !== 'SI'
            )
        )
            return true;

        // Se quiere accesar a Facturación Web - Editar Documento - Documento Soporte pero no se está autorizado
        if (
            url.includes('facturacion-web/editar-documento/documento-soporte') &&
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.FacturacionWebEditarDocumentoSoporte, false) ||
                this.usuario.ofe_documento_soporte !== 'SI'
            )
        )
            return true;

        // Se quiere accesar a Facturación Web - Ver Documento - Documento Soporte pero no se está autorizado
        if (
            url.includes('facturacion-web/ver-documento/documento-soporte') &&
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.FacturacionWebVerDocumentoSoporte, false) ||
                this.usuario.ofe_documento_soporte !== 'SI'
            )
        )
            return true;

        // Se quiere accesar a Facturación Web - Crear Documento - Documento Soporte Nota Crédito DS pero no se está autorizado
        if (
            url.includes('facturacion-web/crear-documento/ds-nota-credito') &&
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.FacturacionWebCrearNotaCreditoDS, false) ||
                this.usuario.ofe_documento_soporte !== 'SI'
            )
        )
            return true;

        // Se quiere accesar a Facturación Web - Editar Documento - Documento Soporte Nota Crédito pero no se está autorizado
        if (
            url.includes('facturacion-web/editar-documento/ds-nota-credito') &&
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.FacturacionWebEditarNotaCreditoDS, false) ||
                this.usuario.ofe_documento_soporte !== 'SI'
            )
        )
            return true;

        // Se quiere accesar a Facturación Web - Ver Documento - Documento Soporte Nota Crédito pero no se está autorizado
        if (
            url.includes('facturacion-web/ver-documento/ds-nota-credito') &&
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.FacturacionWebVerNotaCreditoDS, false) ||
                this.usuario.ofe_documento_soporte !== 'SI'
            )
        )
            return true;

        // ******************** EMISIÓN ***********************
        // Se quiere accesar a Documentos CCO - Parámetros - Datos Comunes pero no se está autorizado
        if (
            url.includes('emision/documentos-cco/parametros/datos-comunes') &&
            (
                (
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.FacturacionPickupCashDatosComunesVer, false) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.FacturacionPickupCashDatosComunesEditar, false)
                ) ||
                this.usuario.ofe_emision !== 'SI'
            )
        )
            return true;  

        // Se quiere accesar a Documentos CCO - Parámetros - Datos Fijos pero no se está autorizado
        if (
            url.includes('emision/documentos-cco/parametros/datos-fijos') &&
            (
                (
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.FacturacionPickupCashDatosFijosVer, false) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.FacturacionPickupCashDatosFijosEditar, false)
                ) ||
                this.usuario.ofe_emision !== 'SI'
            )
        )
            return true;  

        // Se quiere accesar a Documentos CCO - Parámetros - Datos Variables pero no se está autorizado
        if (
            url.includes('emision/documentos-cco/parametros/datos-variables') &&
            (
                (
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.FacturacionPickupCashDatosVariablesVer, false) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.FacturacionPickupCashDatosVariablesEditar, false)
                ) ||
                this.usuario.ofe_emision !== 'SI'
            )
        )
            return true;  

        // Se quiere accesar a Documentos CCO - Parámetros - Extracargos pero no se está autorizado
        if (
            url.includes('emision/documentos-cco/parametros/extracargos') &&
            (
                (
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.FacturacionPickupCashExtracargosVer, false) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.FacturacionPickupCashExtracargosNuevo, false) && 
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.FacturacionPickupCashExtracargosEditar, false) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.FacturacionPickupCashExtracargosCambiarEstado, false)
                ) ||
                this.usuario.ofe_emision !== 'SI'
            )
        )
            return true;  

        // Se quiere accesar a Documentos CCO - Parámetros - Productos pero no se está autorizado
        if (
            url.includes('emision/documentos-cco/parametros/productos') &&
            (
                (
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.FacturacionPickupCashProductosVer, false) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.FacturacionPickupCashProductosNuevo, false) && 
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.FacturacionPickupCashProductosEditar, false) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.FacturacionPickupCashProductosCambiarEstado, false)
                ) ||
                this.usuario.ofe_emision !== 'SI'
            )
        )
            return true;  

        // Se quiere accesar a Documentos CCO - Nuevo Documento - Factura pero no se está autorizado
        if (
            url.includes('emision/documentos-cco/nuevo-documento/factura') &&
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.FacturacionPickupCashNuevoDocumentoFactura, false) ||
                this.usuario.ofe_emision !== 'SI'
            )
        )
            return true;  

        // Se quiere accesar a Documentos CCO - Editar Documento - Factura pero no se está autorizado
        if (
            url.includes('emision/documentos-cco/editar-documento/factura') &&
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.FacturacionPickupCashEditarDocumentoFactura, false) ||
                this.usuario.ofe_emision !== 'SI'
            )
        )
            return true;  

        // Se quiere accesar a documentos por excel pero no se está autorizado
        if (url.includes('emision/creacion-documentos-por-excel') && 
            (
                (
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.EmisionDocumentosPorExcel) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.EmisionDocumentosPorExcelSubirNotas) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.EmisionDocumentosPorExcelSubirFactura) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.EmisionDocumentosPorExcelDescargarNotas) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.EmisionDocumentosPorExcelDescargarFactura)
                ) || 
                this.usuario.ofe_emision !== 'SI'
            )
        )
            return true;  

        // Se quiere accesar a documentos sin-envio pero no se está autorizado
        if (url.includes('emision/documentos-sin-envio') && 
            (
                (
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.EmisionDocumentosSinEnvio) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.EmisionDocumentosSinEnvioEnvio) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.EmisionDocumentosSinEnvioDescargar) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.EmisionDocumentosSinEnvioDescargarJson) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.EmisionDocumentosSinEnvioDescargarExcel) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.EmisionDocumentosSinEnvioDescargarCertificado)
                ) || 
                this.usuario.ofe_emision !== 'SI'
            )
        )
            return true;  

        // Se quiere accesar a documentos enviados pero no se está autorizado
        if (url.includes('emision/documentos-enviados') && 
            (
                (
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.EmisionDocumentosEnviados) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.EmisionDocumentosEnviadosDescargar) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.EmisionDocumentosEnviadosEnviarCorreo) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.EmisionDocumentosEnviadosDescargarJson) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.EmisionDocumentosEnviadosDescargarExcel) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.EmisionDocumentosEnviadosDescargarCertificado)
                ) || 
                this.usuario.ofe_emision !== 'SI'
            )
        )
            return true; 

        // Se quiere accesar a documentos anexos pero no se está autorizado
        if (url.includes('emision/documentos-anexos') && 
            (
                (
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.EmisionDocumentosAnexos) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.EmisionCargaDocumentosAnexos) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.EmisionEliminarDocumentosAnexos)
                ) || 
                this.usuario.ofe_emision !== 'SI'
            )
        )
            return true; 

        // Se quiere accesar a reporte DHL Express pero no se está autorizado
        if (url.includes('emision/reportes/dhl-express') && (!this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.ReportePersonalizadoDhlExpress) || this.usuario.ofe_emision !== 'SI'))
            return true;  

        // Se quiere accesar a reporte Documentos Procesados pero no se está autorizado
        if (url.includes('emision/reportes/documentos-procesados') && (!this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.EmisionReporteDocumentosProcesados) || this.usuario.ofe_emision !== 'SI'))
            return true;  

        // Se quiere accesar a reporte Eventos Notificación pero no se está autorizado
        if (url.includes('emision/reportes/eventos-notificacion') && (!this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.EmisionReporteNotificacionDocumentos) || this.usuario.ofe_emision !== 'SI'))
            return true;  

        // Se quiere accesar a reporte Reportes Background pero no se está autorizado
        if (url.includes('emision/reportes/background') && (!this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.EmisionReporteBackground) || this.usuario.ofe_emision !== 'SI'))
            return true;

        // ******************** DOCUMENTOS SOPORTE ***********************
        // Se quiere accesar a documentos por excel de doocumento soporte pero no se está autorizado
        if (url.includes('documento-soporte/creacion-documentos-por-excel') && 
            (
                (
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.DocumentosSoporteDocumentosPorExcel) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.DocumentosSoporteDocumentosPorExcelDescargar) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.DocumentosSoporteDocumentosPorExcelSubir) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.DocumentosSoporteNotasCreditoPorExcelDescargar) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.DocumentosSoporteNotasCreditoPorExcelSubir)
                ) || 
                this.usuario.ofe_documento_soporte !== 'SI'
            )
        )
            return true;

        // Se quiere accesar a documentos sin envio de doocumento soporte pero no se está autorizado
        if (url.includes('documento-soporte/documentos-sin-envio') && 
            (
                (
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.DocumentosSoporteDocumentosSinEnvio) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.DocumentosSoporteDocumentosSinEnvioDescargar) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.DocumentosSoporteDocumentosSinEnvioDescargarExcel) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.FacturacionWebCrearDocumentoSoporte) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.FacturacionWebEditarDocumentoSoporte) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.FacturacionWebVerDocumentoSoporte)
                ) || 
                this.usuario.ofe_documento_soporte !== 'SI'
            )
        )
            return true;

        // Se quiere accesar a documentos enviados de doocumento soporte pero no se está autorizado
        if (url.includes('documento-soporte/documentos-enviados') && 
            (
                (
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.DocumentosSoporteDocumentosEnviados) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.DocumentosSoporteDocumentosEnviadosDescargar) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.DocumentosSoporteDocumentosEnviadosDescargarExcel) && 
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.DocumentosSoporteDocumentosEnviadosEnviarGestionDocumentos)
                ) ||
                this.usuario.ofe_documento_soporte !== 'SI'
            )
        )
            return true;

        // Se quiere accesar a reporte de documentos procesados de documento soporte pero no se está autorizado
        if (url.includes('documento-soporte/reportes/documentos-procesados') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.DocumentosSoporteReporteDocumentosProcesados) || 
                this.usuario.ofe_documento_soporte !== 'SI'
            )
        )
            return true;

        // Se quiere accesar a reporte de notificación documentos de documento soporte pero no se está autorizado
        if (url.includes('documento-soporte/reportes/notificacion-documentos') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.DocumentosSoporteReporteNotificacionDocumentos) || 
                this.usuario.ofe_documento_soporte !== 'SI'
            )
        )
            return true;

        // Se quiere accesar a reportes en background de documento soporte pero no se está autorizado
        if (url.includes('documento-soporte/reportes/background') && 
            (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.DocumentosSoporteReporteBackground) || 
                this.usuario.ofe_documento_soporte !== 'SI'
            )
        )
            return true;

        // ******************** RECEPCIÓN ***********************
        // Se quiere accesar a documentos recibidos pero no se está autorizado
        if (url.includes('recepcion/documentos-recibidos') && 
            (
                (
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.RecepcionDocumentosRecibidos) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.RecepcionDocumentosRecibidosDescargar) && 
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.RecepcionDocumentosRecibidosAccionesBloque) && 
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.RecepcionDocumentosRecibidosDescargarExcel) && 
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.RecepcionDocumentosRecibidosEnviarGestionDocumentos) && 
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.RecepcionDocumentosRecibidosReenvioNotificacion) 
                ) || this.usuario.ofe_recepcion !== 'SI'
            )
        )
            return true;

        // Se quiere accesar a validación documentos pero no se está autorizado
        if (url.includes('recepcion/validacion-documentos') && 
            (
                (
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.RecepcionValidacionDocumentos) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.RecepcionValidacionDocumentosDescargar) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.RecepcionValidacionDocumentosAsignar) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.RecepcionValidacionDocumentosLiberar) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.RecepcionValidacionDocumentosValidar) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.RecepcionValidacionDocumentosRechazar) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.RecepcionValidacionDocumentosPagar) 
                ) || (this.usuario.ofe_recepcion !== 'SI' && this.usuario.ofe_recepcion_fnc !== 'SI')
            )
        )
            return true;

        // Se quiere accesar a correos recibidos pero no se está autorizado
        if (url.includes('recepcion/correos-recibidos') && 
            (
                (
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.RecepcionCorreosRecibidos) && 
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.RecepcionCorreosRecibidosVer) && 
                !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.RecepcionCorreosRecibidosDescargar)
                ) || this.usuario.ofe_recepcion !== 'SI'
            )
        )
            return true;

        // Se quiere accesar a gestión documentos en la opción fe/ds soporte electrónico pero no se está autorizado
        if (url === 'recepcion/gestion-documentos/fe-doc-soporte-electronico' && 
            (
                (
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.RecepcionGestionDocumentosEtapa1) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.RecepcionGestionDocumentosEtapa1DescargarExcel) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.RecepcionGestionDocumentosEtapa1GestionarFeDs) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.RecepcionGestionDocumentosEtapa1CentroOperaciones) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.RecepcionGestionDocumentosEtapa1SiguienteEtapa)
                ) || this.usuario.ofe_recepcion !== 'SI'
            )
        )
            return true;

        // Se quiere accesar a gestión documentos en la opción pendiente revisión pero no se está autorizado
        if (url.includes('recepcion/gestion-documentos/pendiente-revision') && 
            (
                (
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.RecepcionGestionDocumentosEtapa2) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.RecepcionGestionDocumentosEtapa2DescargarExcel) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.RecepcionGestionDocumentosEtapa2GestionarFeDs) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.RecepcionGestionDocumentosEtapa2CentroCosto) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.RecepcionGestionDocumentosEtapa2SiguienteEtapa) 
                ) || this.usuario.ofe_recepcion !== 'SI'
            )
        )
            return true;

        // Se quiere accesar a gestión documentos en la opción pendiente aprobar conformidad pero no se está autorizado
        if (url.includes('recepcion/gestion-documentos/pendiente-aprobar-conformidad') && 
            (
                (
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.RecepcionGestionDocumentosEtapa3) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.RecepcionGestionDocumentosEtapa3DescargarExcel) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.RecepcionGestionDocumentosEtapa3GestionarFeDs) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.RecepcionGestionDocumentosEtapa3SiguienteEtapa) 
                ) || this.usuario.ofe_recepcion !== 'SI'
            )
        )
            return true;

        // Se quiere accesar a gestión documentos en la opción pendiente reconocimiento contable pero no se está autorizado
        if (url.includes('recepcion/gestion-documentos/pendiente-reconocimiento-contable') && 
            (
                (
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.RecepcionGestionDocumentosEtapa4) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.RecepcionGestionDocumentosEtapa4DescargarExcel) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.RecepcionGestionDocumentosEtapa4GestionarFeDs) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.RecepcionGestionDocumentosEtapa4DatosContabilizado) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.RecepcionGestionDocumentosEtapa4SiguienteEtapa) 
                ) || this.usuario.ofe_recepcion !== 'SI'
            )
        )
            return true;

        // Se quiere accesar a gestión documentos en la opción pendiente reconocimiento contable pero no se está autorizado
        if (url.includes('recepcion/gestion-documentos/pendiente-revision-impuestos') && 
            (
                (
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.RecepcionGestionDocumentosEtapa5) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.RecepcionGestionDocumentosEtapa5DescargarExcel) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.RecepcionGestionDocumentosEtapa5GestionarFeDs) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.RecepcionGestionDocumentosEtapa5SiguienteEtapa) 
                ) || this.usuario.ofe_recepcion !== 'SI'
            )
        )
        return true;

        // Se quiere accesar a gestión documentos en la opción pendiente reconocimiento contable pero no se está autorizado
        if (url.includes('recepcion/gestion-documentos/pendiente-pago') && 
            (
                (
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.RecepcionGestionDocumentosEtapa6) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.RecepcionGestionDocumentosEtapa6DescargarExcel) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.RecepcionGestionDocumentosEtapa6GestionarFeDs) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.RecepcionGestionDocumentosEtapa6SiguienteEtapa) 
                ) || this.usuario.ofe_recepcion !== 'SI'
            )
        )
            return true;

        // Se quiere accesar a gestión documentos en la opción fe/ds soporte electrónico gestionado pero no se está autorizado
        if (url === 'recepcion/gestion-documentos/fe-doc-soporte-electronico-gestionado' && 
            (
                (
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.RecepcionGestionDocumentosEtapa7) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.RecepcionGestionDocumentosEtapa7DescargarExcel)
                ) || this.usuario.ofe_recepcion !== 'SI'
            )
        )
            return true;

        // Se quiere accesar a gestión documentos en la opción autorizaciones etapas pero no se está autorizado
        if (url.includes('recepcion/autorizaciones/autorizacion-etapas') && ((!this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.RecepcionAutorizacionEtapas)) || this.usuario.ofe_recepcion !== 'SI'))
            return true;

        // Se quiere accesar a documentos manuales a crear un documento y asociar un anexo pero no está autorizado
        if (url.includes('recepcion/documentos-manuales/asociar') && (!this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.RecepcionCorreosRecibidosCrearDocumentoAsociarAnexos, false) || this.usuario.ofe_recepcion !== 'SI'))
            return true;

        // Se quiere accesar a Recepción - Documentos Manuales pero no se está autorizado
        if (url.includes('recepcion/documentos-manuales') && (!this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.RecepcionDocumentosManuales) || this.usuario.ofe_recepcion !== 'SI'))
            return true;

        // Se quiere accesar a documentos anexos para asociar un documento pero no se está autorizado
        if (url.includes('recepcion/documentos-anexos/cargar-anexos/asociar') && (!this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.RecepcionCorreosRecibidosAsociarAnexos, false) || this.usuario.ofe_recepcion !== 'SI'))
            return true;

        // Se quiere accesar a documentos anexos pero no se está autorizado
        if (url.includes('recepcion/documentos-anexos') && (!this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.RecepcionDocumentosAnexos) || this.usuario.ofe_recepcion !== 'SI'))
            return true;

        // Se quiere accesar a Documentos No Electrónicos pero no se está autorizado
        if (url.includes('recepcion/documentos-no-electronicos') && (!this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.RecepcionDocumentoNoElectronicoNuevo) || this.usuario.ofe_recepcion !== 'SI'))
            return true;

        // Se quiere accesar a Documentos No Electrónicos - Ver Documento pero no se está autorizado
        if (url.includes('recepcion/documentos-no-electronicos/ver-documento') && (!this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.RecepcionDocumentoNoElectronicoVer, false) || this.usuario.ofe_recepcion !== 'SI'))
            return true;

        // Se quiere accesar a Documentos No Electrónicos - Editar Documento pero no se está autorizado
        if (url.includes('recepcion/documentos-no-electronicos/editar-documento') && (!this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.RecepcionDocumentoNoElectronicoEditar, false) || this.usuario.ofe_recepcion !== 'SI'))
            return true;

        // Se quiere accesar a reporte Reportes Documentos Procesados pero no se está autorizado
        if (url.includes('recepcion/reportes/documentos-procesados') && (!this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.RecepcionReporteDocumentosProcesados) || this.usuario.ofe_recepcion !== 'SI'))
            return true;

        // Se quiere accesar a reporte Reportes Gestión Documentos pero no se está autorizado
        if (url.includes('recepcion/reportes/reporte-gestion-documentos') && (!this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.RecepcionReporteGestionDocumentos) || this.usuario.ofe_recepcion !== 'SI'))
            return true;

        // Se quiere accesar a reporte Log Documentos Validación pero no se está autorizado
        if (url.includes('recepcion/reportes/log-validacion-documentos') && (
            !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.RecepcionReportesLogValidacionDocumentos) ||
            (this.usuario.ofe_recepcion !== 'SI' || (this.usuario.ofe_recepcion === 'SI' && this.usuario.ofe_recepcion_fnc !== 'SI')))
        )
            return true;

        // Se quiere accesar al Reporte de Dependencias pero no se está autorizado
        if (url.includes('recepcion/reportes/reporte-dependencias') && (
            !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.RecepcionReporteDependencias) ||
            (this.usuario.ofe_recepcion !== 'SI' || (this.usuario.ofe_recepcion === 'SI' && this.usuario.ofe_recepcion_fnc !== 'SI')))
        )
            return true;

        // Se quiere accesar a reporte Reportes Background pero no se está autorizado
        if (url.includes('recepcion/reportes/background') && (!this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.RecepcionReporteBackground) || this.usuario.ofe_recepcion !== 'SI'))
            return true;

        // ******************** RADIAN REGISTRO DOCUMENTO *********************** 
        // Se quiere accesar a radian registro documento pero no se está autorizado
        if (url.includes('radian/registro-documentos/registrar') && 
            (
                (
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.RadianRegistroDocumentos)
                )
            )
        )
            return true;

        // Se quiere accesar a Log Errores Radian pero no se está autorizado
        if (url.includes('radian/registro-documentos/log-errores') && (!this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.RadianRegistroDocumentosLogErrores)))
            return true;

        // Se quiere accesar a reporte Reportes Background pero no se está autorizado
        if (url.includes('radian/reportes/background') && !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.RadianDocumentosReporteBackground))
            return true;

        // Se quiere accesar a radian documentos pero no se está autorizado
        if (url.includes('radian/registro-documentos/documentos-radian') && 
            (
                (
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.RadianDocumentos) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.RadianDocumentosDescargar) && 
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.RadianDocumentosAccionesBloque) && 
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.RadianDocumentosDescargarExcel) && 
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.RadianDocumentosReenvioNotificacion) 
                )
            )
        )
            return true;

        // ******************** NÓMINA ELECTRÓNICA ***********************
        // Se quiere accesar a documentos sin-envio pero no se está autorizado
        if (url.includes('nomina-electronica/documentos-sin-envio') && 
            (
                (
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.DnDocumentosSinEnvio) && 
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.DnDocumentosSinEnvioDescargar) && 
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.DnDocumentosSinEnvioDescargarExcel) && 
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.DnDocumentosSinEnvioEnvio)
                ) || this.usuario.nomina !== 'SI'
            )
        )
            return true;

        // Se quiere accesar a documentos enviados pero no se está autorizado
        if (url.includes('nomina-electronica/documentos-enviados') && 
            (
                (
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.DnDocumentosEnviados) && 
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.DnDocumentosEnviadosDescargar) && 
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.DnDocumentosEnviadosDescargarExcel) && 
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.DnDocumentosEnviadosDescargarJson) && 
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.DnDocumentosEnviadosDescargarXml) && 
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.DnDocumentosEnviadosDescargarArEstadoDian) 
                ) || this.usuario.nomina !== 'SI'
            )
        )
            return true;

        // Se quiere accesar a creación documentos por excel pero no se está autorizado
        if (url.includes('nomina-electronica/creacion-documentos-por-excel') && 
            (
                (
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.DnDocumentosPorExcel) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.DnDocumentosPorExcelDescargarNomina) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.DnDocumentosPorExcelDescargarEliminar) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.DnDocumentosPorExcelSubirNomina) &&
                    !this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.DnDocumentosPorExcelSubirEliminar)
                ) || this.usuario.nomina !== 'SI'
            )
        )
            return true;

        // Se quiere accesar a reporte Reportes Background pero no se está autorizado
        if (url.includes('nomina-electronica/reportes/background') && (!this._auth.validaAccesoItem(this._auth.permisosRoles.permisos.NominaReporteBackground) || this.usuario.nomina !== 'SI'))
            return true;

        return false;
    }
}
