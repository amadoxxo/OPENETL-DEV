import { Observable } from 'rxjs';
import { Injectable } from '@angular/core';
import { Router } from '@angular/router';
import { HttpClient, HttpHeaders } from '@angular/common/http';
import { JwtHelperService } from '@auth0/angular-jwt';
import { environment } from 'environments/environment';
import { PERMISOSROLES } from 'app/acl/permisos_roles';
import {FuseConfigService} from '@fuse/services/config.service';
import { FuseNavigationService } from '@fuse/components/navigation/navigation.service';
import * as capitalize from 'lodash';
import swal from 'sweetalert2';

@Injectable({
    providedIn: 'root'
})
export class Auth {
    private apiUrl = environment.API_ENDPOINT;
    private headers = new HttpHeaders();
    public permisosRoles: any = {};

    constructor(
        private http: HttpClient,
        private router: Router,
        private jwtHelperService: JwtHelperService,
        private _fuseConfigService: FuseConfigService,
        private _fuseNavigationService: FuseNavigationService,
    ) {
        this.permisosRoles = PERMISOSROLES;
        this.headers = this.headers.set('Content-type', 'application/x-www-form-urlencoded');
        this.headers = this.headers.set('X-Requested-Whith', 'XMLHttpRequest');
        this.headers = this.headers.set('Accept', 'application/json');
        this.headers = this.headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        this.headers = this.headers.set('Pragma', 'no-cache');
        this.headers = this.headers.set('Expires', '0');
    }

    /**
     * Setter para el token de autenticación.
     *
     * @param {string} token Token de autenticación
     * @memberof Auth
     */
    set access_token(token: string) {
        localStorage.setItem('id_token', token);
    }

    /**
     * Getter para el token de autenticación.
     *
     * @return {string} token Token de autenticación
     * @memberof Auth
     */
    get access_token(): string {
        return localStorage.getItem('id_token') ?? '';
    }

    /**
     * Autentica a un usuario.
     *
     * @param {*} user Información del usuario
     * @return {*}  {Observable<any>}
     * @memberof Auth
     */
    login(user): Observable<any> {
        return this.http.post(
            `${this.apiUrl}login`,
            this._parseObject(user),
            { headers: this.headers }
        );
    }

    /**
     * Envía al correo la contraseña del usuario.
     *
     * @param {Object} email Email
     * @return {*}  {Observable<any>}
     * @memberof Auth
     */
    forgot(email: Object): Observable<any> {
        return this.http.post(
            `${this.apiUrl}password/email`,
            this._parseObject(email),
            { headers: this.headers }
        );
    }

    /**
     * Cierra la sesion del usuario autenticado.
     *
     * @memberof Auth
     */
    logout() {
        // Configure the layout
        this._fuseConfigService.config = {
            layout: {
                navbar: {
                    hidden: true
                },
                toolbar: {
                    hidden: true
                },
                footer: {
                    hidden: true
                },
                sidepanel: {
                    hidden: true
                }
            }
        };
        localStorage.removeItem('cartera_vencida_mensaje');
        localStorage.removeItem('id_token');
        localStorage.removeItem('acl');
        this.router.navigate(['/auth/login']);
    }

    /**
     * Valida si la sesion no ha expirado.
     *
     * @return {*} 
     * @memberof Auth
     */
    loggedIn() {
        const token: string = this.jwtHelperService.tokenGetter();
        if (!token) {
            this.logout();
            return false;
        }
        const tokenExpired: boolean = this.jwtHelperService.isTokenExpired(token);
        if (!tokenExpired) {
            return !tokenExpired;
        } else {
            swal({
                html: '<h5>Tiempo de inactividad superado</h5><br>Debe autenticarse nuevamente',
                type: 'warning',
                showCancelButton: false,
                confirmButtonClass: 'btn btn-warning',
                confirmButtonText: 'Ok, entiendo',
                buttonsStyling: false,
                allowOutsideClick: false
            });
            this.logout();
            return tokenExpired;
        }
    }

    /**
     * Valida la existencia de un rol.
     *
     * @param {*} roles Array de roles
     * @param {*} rol Rol a buscar
     * @return {*}
     * @memberof Auth
     */
    existeRol(roles, rol) {
        return roles.includes(rol);
    }

    /**
     * Valida la existencia de un permiso.
     *
     * @param {*} permisos Array de permisos
     * @param {*} permiso Permiso a buscar
     * @return {*} 
     * @memberof Auth
     */
    existePermiso(permisos, permiso) {
        return permisos.includes(permiso);
    }

    /**
     * Valida si el usuario tiene acceso al menú Sistema y sus opciones.
     * 
     * @return bool
     */
    validaAccesoSistema () {
        if (this.jwtHelperService.tokenGetter()) {
            let aclsUsuario = this.getAcls();
            if (this.existeRol(aclsUsuario.roles, this.permisosRoles.roles.Superadministrador) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.SistemaAdministracionVariablesSistema) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.SistemaAdministracionVariablesSistemaEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.SistemaAdministracionVariablesSistemaVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.SistemaAdministracionVariablesSistemaCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.SistemaAdministracionFestivos) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.SistemaAdministracionFestivosNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.SistemaAdministracionFestivosEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.SistemaAdministracionFestivosVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.SistemaAdministracionFestivosCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.SistemaAdministracionTiemposAceptacionTacita) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.SistemaAdministracionTiemposAceptacionTacitaNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.SistemaAdministracionTiemposAceptacionTacitaEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.SistemaAdministracionTiemposAceptacionTacitaVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.SistemaAdministracionTiemposAceptacionTacitaCambia) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.AdministracionRolesUsuarios) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.AdministracionRolesUsuariosNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.AdministracionRolesUsuariosEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.AdministracionRolesUsuariosVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.AdministracionRolesUsuariosCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.AdministracionUsuarios) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.AdministracionUsuariosVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.AdministracionUsuariosNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.AdministracionUsuariosEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.AdministracionUsuariosCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.AdministracionUsuariosDescargarExcel) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.AdministracionUsuariosSubir) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.AdministracionUsuariosBajarUsuariosExcel)
                ) 
                return true;
        } 
        return false;
    }

    /**
     * Valida si el usuario tiene acceso al menú Parámetros y sus opciones.
     * 
     * @return bool
     */
    validaAccesoParametros () {
        if (this.jwtHelperService.tokenGetter()) {
            let aclsUsuario = this.getAcls();
            if (this.existeRol(aclsUsuario.roles, this.permisosRoles.roles.Superadministrador) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaPaises) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaPaisesVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaPaisesNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaPaisesEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaPaisesCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaDepartamentos) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaDepartamentosVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaDepartamentosNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaDepartamentosEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaDepartamentosCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaMunicipios) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaMunicipiosVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaMunicipiosNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaMunicipiosEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaMunicipiosCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaClasificacionProductos) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaClasificacionProductosVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaClasificacionProductosNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaClasificacionProductosEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaClasificacionProductosCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaCodigosDescuentos) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaCodigoDescuentoVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaCodigoDescuentoNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaCodigoDescuentoEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaCodigoDescuentoCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaCodigosPostales) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaCodigoPostalVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaCodigoPostalNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaCodigoPostalEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaCodigoPostalCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaColombiaCompraEficiente) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaColombiaCompraEficienteVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaColombiaCompraEficienteNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaColombiaCompraEficienteEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaColombiaCompraEficienteCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaFormasPago) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaFormasPagoVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaFormasPagoNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaFormasPagoEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaFormasPagoCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaMediosPago) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaMediosPagoVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaMediosPagoNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaMediosPagoEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaMediosPagoCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaMandatos) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaMandatosVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaMandatosNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaMandatosEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaMandatosCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaCondicionesEntrega) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaCondicionesEntregaVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaCondicionesEntregaNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaCondicionesEntregaEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaCondicionesEntregaCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaMonedas) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaMonedasVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaMonedasNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaMonedasEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaMonedasCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaPartidasArancelarias) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaPartidasArancelariasVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaPartidasArancelariasNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaPartidasArancelariasEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaPartidasArancelariasCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaPreciosReferencia) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaPreciosReferenciaVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaPreciosReferenciaNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaPreciosReferenciaEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaPreciosReferenciaCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaRegimenFiscal) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaRegimenFiscalVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaRegimenFiscalNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaRegimenFiscalEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaRegimenFiscalCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaResponsabilidadesFiscales) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaResponsabilidadesFiscalesVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaResponsabilidadesFiscalesNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaResponsabilidadesFiscalesEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaResponsabilidadesFiscalesCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaTiposDocumentos) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaTiposDocumentosVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaTiposDocumentosNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaTiposDocumentosEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaTiposDocumentosCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaTiposOrganizacionJuridica) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaTiposOrganizacionJuridicaVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaTiposOrganizacionJuridicaNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaTiposOrganizacionJuridicaEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaTiposOrganizacionJuridicaCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaTiposDocumentosElectronicos) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaTiposDocumentosElectronicosVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaTiposDocumentosElectronicosNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaTiposDocumentosElectronicosEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaTiposDocumentosElectronicosCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaTiposOperacion) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaTiposOperacionVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaTiposOperacionNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaTiposOperacionEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaTiposOperacionCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaTributos) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaTributosVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaTributosNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaTributosEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaTributosCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaTarifasImpuesto) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaTarifasImpuestoVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaTarifasImpuestoNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaTarifasImpuestoEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaTarifasImpuestoCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaUnidades) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaUnidadesVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaUnidadesNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaUnidadesEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaUnidadesCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaReferenciaOtrosDocumentos) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaReferenciaOtrosDocumentosVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaReferenciaOtrosDocumentosNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaReferenciaOtrosDocumentosEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaReferenciaOtrosDocumentosCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaConceptosCorreccion) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaConceptosCorreccionVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaConceptosCorreccionNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaConceptosCorreccionEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaConceptosCorreccionCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaConceptosRechazo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaConceptosRechazoVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaConceptosRechazoNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaConceptosRechazoEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaConceptosRechazoCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaAmbienteDestinoDocumentos) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaAmbienteDestinoDocumentosVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaAmbienteDestinoDocumentosNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaAmbienteDestinoDocumentosEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaAmbienteDestinoDocumentosCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaProcedenciaVendedor) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaProcedenciaVendedorVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaProcedenciaVendedorNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaProcedenciaVendedorEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaProcedenciaVendedorCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaFormaGeneracionTransmision) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaFormaGeneracionTransmisionVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaFormaGeneracionTransmisionNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaFormaGeneracionTransmisionEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaFormaGeneracionTransmisionCambiarEstado) ||
                this.validaAccesoParametrosSectorSalud() ||
                this.validaAccesoParametrosSectorTransporte() ||
                this.validaAccesoParametrosSectorCambiario() ||
                this.validaAccesoParametrosNominaElectronica() ||
                this.validaAccesoParametrosRadian()
            ) 
                return true;
        } 
        return false;
    }

    /**
     * Valida si el usuario tiene acceso al menú Parámetros - Sector Salud y sus opciones.
     * 
     * @return bool
     */
    validaAccesoParametrosSectorSalud () {
        if (this.jwtHelperService.tokenGetter()) {
            let aclsUsuario = this.getAcls();
            if (this.existeRol(aclsUsuario.roles, this.permisosRoles.roles.Superadministrador) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaSectorSaludDocumentosIdentificacion) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaSectorSaludDocumentosIdentificacionVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaSectorSaludDocumentosIdentificacionNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaSectorSaludDocumentosIdentificacionEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaSectorSaludDocumentosIdentificacionCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaSectorSaludTipoUsuario) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaSectorSaludTipoUsuarioVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaSectorSaludTipoUsuarioNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaSectorSaludTipoUsuarioEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaSectorSaludTipoUsuarioCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaSectorSaludModalidades) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaSectorSaludModalidadesVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaSectorSaludModalidadesNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaSectorSaludModalidadesEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaSectorSaludModalidadesCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaSectorSaludCobertura) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaSectorSaludCoberturaVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaSectorSaludCoberturaNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaSectorSaludCoberturaEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaSectorSaludCoberturaCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaSectorSaludDocumentoReferenciado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaSectorSaludDocumentoReferenciadoVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaSectorSaludDocumentoReferenciadoNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaSectorSaludDocumentoReferenciadoEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaSectorSaludDocumentoReferenciadoCambiarEstado)
            ) 
                return true;
        } 
        return false;
    }

    /**
     * Valida si el usuario tiene acceso al menú Parámetros - Sector Transporte y sus opciones.
     * 
     * @return bool
     */
    validaAccesoParametrosSectorTransporte () {
        if (this.jwtHelperService.tokenGetter()) {
            let aclsUsuario = this.getAcls();
            if (this.existeRol(aclsUsuario.roles, this.permisosRoles.roles.Superadministrador) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaSectorTransporteRegistro) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaSectorTransporteRegistroVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaSectorTransporteRegistroNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaSectorTransporteRegistroEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaSectorTransporteRegistroCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaSectorTransporteRemesa) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaSectorTransporteRemesaVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaSectorTransporteRemesaNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaSectorTransporteRemesaEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaSectorTransporteRemesaCambiarEstado)
            ) 
                return true;
        } 
        return false;
    }

    /**
     * Valida si el usuario tiene acceso al menú Parámetros - Sector Cambiario y sus opciones.
     * 
     * @return bool
     */
    validaAccesoParametrosSectorCambiario () {
        if (this.jwtHelperService.tokenGetter()) {
            let aclsUsuario = this.getAcls();
            if (this.existeRol(aclsUsuario.roles, this.permisosRoles.roles.Superadministrador) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaSectorCambiarioMandatoProfesional) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaSectorCambiarioMandatoProfesionalVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaSectorCambiarioMandatoProfesionalNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaSectorCambiarioMandatoProfesionalEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaSectorCambiarioMandatoProfesionalCambiarEstado)
            ) 
                return true;
        } 
        return false;
    }

    /**
     * Valida si el usuario tiene acceso al menú Parámetros - Nomina Electrónica y sus opciones.
     * 
     * @return bool
     */
    validaAccesoParametrosNominaElectronica () {
        if (this.jwtHelperService.tokenGetter()) {
            let aclsUsuario = this.getAcls();
            if (this.existeRol(aclsUsuario.roles, this.permisosRoles.roles.Superadministrador) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaNominaPeriodo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaNominaPeriodoVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaNominaPeriodoNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaNominaPeriodoEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaNominaPeriodoCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaTipoContrato) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaTipoContratoVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaTipoContratoNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaTipoContratoEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaTipoContratoCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaTipoTrabajador) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaTipoTrabajadorVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaTipoTrabajadorNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaTipoTrabajadorEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaTipoTrabajadorCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaSubtipoTrabajador) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaSubtipoTrabajadorVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaSubtipoTrabajadorNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaSubtipoTrabajadorEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaSubtipoTrabajadorCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaTipoHoraExtraRecargo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaTipoHoraExtraRecargoVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaTipoHoraExtraRecargoNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaTipoHoraExtraRecargoEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaTipoHoraExtraRecargoCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaTipoIncapacidad) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaTipoIncapacidadVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaTipoIncapacidadNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaTipoIncapacidadEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaTipoIncapacidadCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaTipoNota) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaTipoNotaVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaTipoNotaNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaTipoNotaEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaTipoNotaCambiarEstado)
            ) 
                return true;
        } 
        return false;
    }

    /**
     * Valida si el usuario tiene acceso al menú Parámetros - Radian y sus opciones.
     * 
     * @return bool
     */
    validaAccesoParametrosRadian () {
        if (this.jwtHelperService.tokenGetter()) {
            let aclsUsuario = this.getAcls();
            if (this.existeRol(aclsUsuario.roles, this.permisosRoles.roles.Superadministrador) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaRadianReferenciaDocumentosElectronicos) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaRadianReferenciaDocumentosElectronicosVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaRadianReferenciaDocumentosElectronicosNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaRadianReferenciaDocumentosElectronicosEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaRadianReferenciaDocumentosElectronicosCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaRadianTiposPagos) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaRadianTiposPagosVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaRadianTiposPagosNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaRadianTiposPagosEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaRadianTiposPagosCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaRadianNaturalezaMandato) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaRadianNaturalezaMandatoVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaRadianNaturalezaMandatoNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaRadianNaturalezaMandatoEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaRadianNaturalezaMandatoCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaRadianTipoMandante) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaRadianTipoMandanteVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaRadianTipoMandanteNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaRadianTipoMandanteEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaRadianTipoMandanteCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaRadianTiempoMandato) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaRadianTiempoMandatoVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaRadianTiempoMandatoNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaRadianTiempoMandatoEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaRadianTiempoMandatoCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaRadianTipoMandatario) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaRadianTipoMandatarioVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaRadianTipoMandatarioNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaRadianTipoMandatarioEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaRadianTipoMandatarioCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaRadianEventoDocumentoElectronico) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaRadianEventoDocumentoElectronicoVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaRadianEventoDocumentoElectronicoNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaRadianEventoDocumentoElectronicoEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaRadianEventoDocumentoElectronicoCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaRadianTipoOperacion) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaRadianTipoOperacionVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaRadianTipoOperacionNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaRadianTipoOperacionEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaRadianTipoOperacionCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaRadianFactor) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaRadianFactorVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaRadianFactorNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaRadianFactorEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaRadianFactorCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaRadianRoles) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaRadianRolesVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaRadianRolesNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaRadianRolesEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaRadianRolesCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaRadianEndoso) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaRadianEndosoVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaRadianEndosoNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaRadianEndosoEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaRadianEndosoCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaRadianAlcanceMandato) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaRadianAlcanceMandatoVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaRadianAlcanceMandatoNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaRadianAlcanceMandatoEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ParametricaRadianAlcanceMandatoCambiarEstado)
            ) 
                return true;
        } 
        return false;
    }

    /**
     * Valida si el usuario tiene acceso al menú Sistema y sus opciones.
     * 
     * @return bool
     */
    validaAccesoConfiguracion () {
        if (this.jwtHelperService.tokenGetter()) {
            let aclsUsuario = this.getAcls();
            if (
                this.existeRol(aclsUsuario.roles, this.permisosRoles.roles.Superadministrador) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionAdquirentes) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionAdquirentesCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionAdquirentesDescargarExcel) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionAdquirentesEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionAdquirentesNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionAdquirentesSubir) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionAdquirentesVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionAutorizados) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionAutorizadosCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionAutorizadosDescargarExcel) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionAutorizadosEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionAutorizadosNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionAutorizadosSubir) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionAutorizadosVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionResponsables) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionResponsablesCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionResponsablesDescargarExcel) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionResponsablesEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionResponsablesNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionResponsablesSubir) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionResponsablesVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionVendedorDS) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionVendedorDSCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionVendedorDSDescargarExcel) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionVendedorDSEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionVendedorDSNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionVendedorDSSubir) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionVendedorDSVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionOFE) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionOFENuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionOFEEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionOFEVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionOFECambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionOFESubir) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfigurarDocumentoElectronico) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfigurarDocumentoSoporte) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfigurarServicios) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ValoresDefectoDocumento) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionRadianActores) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionRadianActoresNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionRadianActoresEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionRadianActoresVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionRadianActoresCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionRadianActoresSubir) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionRadianActoresDescargarExcel) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionProveedores) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionProveedoresCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionProveedoresEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionProveedoresNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionProveedoresSubir) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionProveedoresVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionResolucionesFacturacion) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionResolucionesFacturacionCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionResolucionesFacturacionEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionResolucionesFacturacionNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionResolucionesFacturacionSubir) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionResolucionesFacturacionVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionAutorizacionesEventosDian) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionAutorizacionesEventosDianNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionAutorizacionesEventosDianEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionAutorizacionesEventosDianVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionAutorizacionesEventosDianCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionAutorizacionesEventosDianSubir) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionAutorizacionesEventosDianDescargarExcel) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionAdministracionRecepcionERP) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionAdministracionRecepcionERPNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionAdministracionRecepcionERPEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionAdministracionRecepcionERPVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionAdministracionRecepcionERPCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionAdministracionRecepcionERPSubir) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionAdministracionRecepcionERPDescargarExcel) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionCentrosCosto) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionCentrosCostoCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionCentrosCostoEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionCentrosCostoNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionCentrosCostoVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionCausalesDevolucion) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionCausalesDevolucionCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionCausalesDevolucionEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionCausalesDevolucionNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionCausalesDevolucionVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionCentrosOperacion) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionCentrosOperacionCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionCentrosOperacionEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionCentrosOperacionNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionCentrosOperacionVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.SoftwareProveedorTecnologico) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.SoftwareProveedorTecnologicoNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.SoftwareProveedorTecnologicoEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.SoftwareProveedorTecnologicoVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.SoftwareProveedorTecnologicoSubir) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.SoftwareProveedorTecnologicoCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAdministracion) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAdministracionNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAdministracionEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAdministracionCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAdministracionSubir) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAdministracionVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAdministracionDescargarExcel) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAsociarUsuario) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAsociarUsuarioNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAsociarUsuarioCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAsociarUsuarioSubir) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAsociarUsuarioDescargarExcel) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAsociarUsuarioVerUsuariosAsociados) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAsociarUsuarioEditarUsuariosAsociados) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAsociarProveedor) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAsociarProveedorNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAsociarProveedorCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAsociarProveedorSubir) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAsociarProveedorDescargarExcel) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAsociarProveedorVerProveedoresAsociados) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionXPathDEEstandar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionXPathDEEstandarNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionXPathDEEstandarEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionXPathDEEstandarVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionXPathDEEstandarCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionXPathDEEstandarDescargar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionXPathDEPersonalizado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionXPathDEPersonalizadoNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionXPathDEPersonalizadoEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionXPathDEPersonalizadoVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionXPathDEPersonalizadoCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionXPathDEPersonalizadoDescargar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionDnEmpleador) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionDnEmpleadorNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionDnEmpleadorEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionDnEmpleadorVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionDnEmpleadorCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionDnEmpleadorSubir) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionDnEmpleadorDescargarExcel) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionDnTrabajador) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionDnTrabajadorNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionDnTrabajadorEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionDnTrabajadorVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionDnTrabajadorCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionDnTrabajadorSubir) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionDnTrabajadorDescargarExcel) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionUsuarioEcm) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionUsuarioEcmNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionUsuarioEcmEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionUsuarioEcmVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionUsuarioEcmCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionUsuarioEcmSubir) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionUsuarioEcmDescargarExcel)
            ) 
                return true;
        } 
        return false;
    }

    /**
     * Valida si el usuario tiene acceso al menú Sistema y sus opciones.
     * 
     * @return bool
     */
    validaAccesoConfiguracionEmision () {
        if (this.jwtHelperService.tokenGetter()) {
            let aclsUsuario = this.getAcls();
            if (
                this.existeRol(aclsUsuario.roles, this.permisosRoles.roles.Superadministrador) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionAdquirentes) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionAdquirentesCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionAdquirentesDescargarExcel) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionAdquirentesEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionAdquirentesNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionAdquirentesSubir) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionAdquirentesVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionAutorizados) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionAutorizadosCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionAutorizadosDescargarExcel) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionAutorizadosEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionAutorizadosNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionAutorizadosSubir) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionAutorizadosVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionResponsables) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionResponsablesCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionResponsablesDescargarExcel) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionResponsablesEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionResponsablesNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionResponsablesSubir) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionResponsablesVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionVendedorDS) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionVendedorDSCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionVendedorDSDescargarExcel) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionVendedorDSEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionVendedorDSNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionVendedorDSSubir) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionVendedorDSVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionResolucionesFacturacion) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionResolucionesFacturacionCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionResolucionesFacturacionEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionResolucionesFacturacionNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionResolucionesFacturacionSubir) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionResolucionesFacturacionVer)
            ) 
                return true;
        } 
        return false;
    }

    /**
     * Valida si el usuario tiene acceso al menú Sistema y sus opciones.
     * 
     * @return bool
     */
    validaAccesoConfiguracionRecepcion () {
        if (this.jwtHelperService.tokenGetter()) {
            let aclsUsuario = this.getAcls();
            if (
                this.existeRol(aclsUsuario.roles, this.permisosRoles.roles.Superadministrador) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionProveedores) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionProveedoresCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionProveedoresEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionProveedoresNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionProveedoresSubir) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionProveedoresVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionAutorizacionesEventosDian) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionAutorizacionesEventosDianNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionAutorizacionesEventosDianEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionAutorizacionesEventosDianVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionAutorizacionesEventosDianCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionAutorizacionesEventosDianSubir) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionAutorizacionesEventosDianDescargarExcel) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionAdministracionRecepcionERP) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionAdministracionRecepcionERPNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionAdministracionRecepcionERPEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionAdministracionRecepcionERPVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionAdministracionRecepcionERPCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionAdministracionRecepcionERPSubir) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionAdministracionRecepcionERPDescargarExcel) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionCentrosCosto) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionCentrosCostoCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionCentrosCostoEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionCentrosCostoNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionCentrosCostoVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionCausalesDevolucion) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionCausalesDevolucionCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionCausalesDevolucionEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionCausalesDevolucionNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionCausalesDevolucionVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionCentrosOperacion) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionCentrosOperacionCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionCentrosOperacionEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionCentrosOperacionNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionCentrosOperacionVer)
            ) 
                return true;
        } 
        return false;
    }

    /**
     * Valida si el usuario tiene acceso al menú Sistema y sus opciones.
     * 
     * @return bool
     */
    validaAccesoConfiguracionComunes () {
        if (this.jwtHelperService.tokenGetter()) {
            let aclsUsuario = this.getAcls();
            if (
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionOFE) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionOFENuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionOFEEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionOFEVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionOFECambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionOFESubir) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfigurarDocumentoElectronico) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfigurarDocumentoSoporte) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfigurarServicios) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ValoresDefectoDocumento) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionRadianActores) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionRadianActoresNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionRadianActoresEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionRadianActoresVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionRadianActoresCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionRadianActoresSubir) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.SoftwareProveedorTecnologico) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.SoftwareProveedorTecnologicoNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.SoftwareProveedorTecnologicoEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.SoftwareProveedorTecnologicoVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.SoftwareProveedorTecnologicoSubir) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.SoftwareProveedorTecnologicoCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAdministracion) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAdministracionNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAdministracionEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAdministracionCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAdministracionSubir) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAdministracionVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAdministracionDescargarExcel) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAsociarUsuario) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAsociarUsuarioNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAsociarUsuarioCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAsociarUsuarioSubir) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAsociarUsuarioDescargarExcel) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAsociarUsuarioVerUsuariosAsociados) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAsociarUsuarioEditarUsuariosAsociados) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAsociarProveedor) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAsociarProveedorNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAsociarProveedorCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAsociarProveedorSubir) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAsociarProveedorDescargarExcel) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAsociarProveedorVerProveedoresAsociados) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionXPathDEEstandar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionXPathDEEstandarNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionXPathDEEstandarEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionXPathDEEstandarVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionXPathDEEstandarCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionXPathDEEstandarDescargar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionXPathDEPersonalizado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionXPathDEPersonalizadoNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionXPathDEPersonalizadoEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionXPathDEPersonalizadoCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionXPathDEPersonalizadoDescargar)
            )
                return true;
        } 
        return false;
    }

    /**
     * Valida si el usuario tiene acceso al menú Configuración - Comunes - Grupos Trabajo y sus opciones.
     * 
     * @return bool
     */
    validaAccesoConfiguracionGruposTrabajo() {
        if (this.jwtHelperService.tokenGetter()) {
            let aclsUsuario = this.getAcls();
            if (
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAdministracion) || 
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAdministracionNuevo) || 
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAdministracionEditar) || 
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAdministracionCambiarEstado) || 
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAdministracionSubir) || 
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAdministracionVer) || 
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAdministracionDescargarExcel) || 
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAsociarUsuario) || 
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAsociarUsuarioNuevo) || 
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAsociarUsuarioCambiarEstado) || 
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAsociarUsuarioSubir) || 
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAsociarUsuarioDescargarExcel) || 
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAsociarUsuarioVerUsuariosAsociados) || 
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAsociarUsuarioEditarUsuariosAsociados) || 
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAsociarProveedor) || 
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAsociarProveedorNuevo) || 
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAsociarProveedorCambiarEstado) || 
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAsociarProveedorSubir) || 
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAsociarProveedorDescargarExcel) || 
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAsociarProveedorVerProveedoresAsociados)
            ) {
                let usuario = this.jwtHelperService.decodeToken();
                let menuPlural = usuario.grupos_trabajo.plural;
                menuPlural = capitalize.startCase(capitalize.toLower(menuPlural));
                this._fuseNavigationService.updateNavigationItem('configuracion-grupos-trabajo', {
                    title: menuPlural
                });
                return true;
            }
        } 
        return false;
    }

    /**
     * Valida si el usuario tiene acceso al menú Sistema y sus opciones.
     * 
     * @return bool
     */
    validaAccesoConfiguracionIntegracionEcm () {
        if (this.jwtHelperService.tokenGetter()) {
            let aclsUsuario = this.getAcls();
            if (
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionUsuarioEcm) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionUsuarioEcmNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionUsuarioEcmEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionUsuarioEcmVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionUsuarioEcmCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionUsuarioEcmSubir) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionUsuarioEcmDescargarExcel)
            ) 
                return true;
        } 
        return false;
    }

    /**
     * Valida si el usuario tiene acceso al menú configuración - reportes y sus opciones.
     *
     * @return {*}  {Boolean}
     * @memberof Auth
     */
    validaAccesoConfiguracionReportes(): Boolean {
        if (this.jwtHelperService.tokenGetter()) {
            let aclsUsuario = this.getAcls();
            if (this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionAdquirentesDescargarExcel)) 
                return true;
        } 
        return false;
    }

    /**
     * Valida si el usuario tiene acceso al menú Emisión y sus opciones.
     * 
     * @return bool
     */
    validaAccesoEmision () {
        if (this.jwtHelperService.tokenGetter()) {
            let aclsUsuario = this.getAcls();
            let usuario = this.jwtHelperService.decodeToken();
            if (
                usuario.ofe_emision === 'SI' &&
                (
                    this.existeRol(aclsUsuario.roles, this.permisosRoles.roles.Superadministrador) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.EmisionDocumentosPorExcel) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.EmisionDocumentosPorExcelSubirNotas) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.EmisionDocumentosPorExcelSubirFactura) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.EmisionDocumentosPorExcelDescargarNotas) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.EmisionDocumentosPorExcelDescargarFactura) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.EmisionDocumentosSinEnvio) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.EmisionDocumentosSinEnvioEnvio) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.EmisionDocumentosSinEnvioDescargar) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.EmisionDocumentosSinEnvioDescargarJson) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.EmisionDocumentosSinEnvioDescargarExcel) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.EmisionDocumentosSinEnvioDescargarCertificado) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.EmisionDocumentosEnviados) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.EmisionDocumentosEnviadosDescargar) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.EmisionDocumentosEnviadosEnviarCorreo) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.EmisionDocumentosEnviadosDescargarJson) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.EmisionDocumentosEnviadosDescargarExcel) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.EmisionDocumentosEnviadosDescargarCertificado) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.EmisionDocumentosAnexos) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.EmisionCargaDocumentosAnexos) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.EmisionEliminarDocumentosAnexos) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.EmisionAceptacionTacita) ||
                    this.validaAccesoEmisionReportes() ||
                    this.validaAccesoDocumentosCco()
                )
            ) 
                return true;
        } 
        return false;
    }

    /**
     * Valida si el usuario tiene acceso al menú Documento Soporte y sus opciones.
     * 
     * @return bool
     */
    validaAccesoDocumentoSoporte () {
        if (this.jwtHelperService.tokenGetter()) {
            let aclsUsuario = this.getAcls();
            let usuario = this.jwtHelperService.decodeToken();
            if (
                usuario.ofe_documento_soporte === 'SI' &&
                (
                    this.existeRol(aclsUsuario.roles, this.permisosRoles.roles.Superadministrador) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.DocumentosSoporteDocumentosPorExcel) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.DocumentosSoporteDocumentosPorExcelDescargar) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.DocumentosSoporteDocumentosPorExcelSubir) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.DocumentosSoporteNotasCreditoPorExcelDescargar) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.DocumentosSoporteNotasCreditoPorExcelSubir) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.DocumentosSoporteDocumentosSinEnvio) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.DocumentosSoporteDocumentosSinEnvioDescargar) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.DocumentosSoporteDocumentosSinEnvioDescargarExcel) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.DocumentosSoporteDocumentosEnviados) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.DocumentosSoporteDocumentosEnviadosDescargar) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.DocumentosSoporteDocumentosEnviadosDescargarExcel) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.DocumentosSoporteDocumentosEnviadosEnviarGestionDocumentos) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.DocumentosSoporteReporteDocumentosProcesados) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.DocumentosSoporteReporteNotificacionDocumentos) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.DocumentosSoporteReporteBackground) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebCrearDocumentoSoporte) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebEditarDocumentoSoporte) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebVerDocumentoSoporte)
                )
            ) 
                return true;
        } 
        return false;
    }

    /**
     * Valida si el usuario tiene acceso al menú Recepción y sus opciones.
     * 
     * @return bool
     */
    validaAccesoRecepcion () {
        if (this.jwtHelperService.tokenGetter()) {
            let aclsUsuario = this.getAcls();
            let usuario = this.jwtHelperService.decodeToken();
            if (
                usuario.ofe_recepcion === 'SI' &&
                (
                    this.existeRol(aclsUsuario.roles, this.permisosRoles.roles.Superadministrador) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.RecepcionDocumentosManuales) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.RecepcionDocumentosAnexos) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.RecepcionCargaDocumentosAnexos) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.RecepcionDocumentosRecibidos) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.RecepcionDocumentosRecibidosDescargar) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.RecepcionDocumentosRecibidosAccionesBloque) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.RecepcionDocumentosRecibidosDescargarExcel) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.RecepcionDocumentosRecibidosEnviarGestionDocumentos) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.RecepcionDocumentosRecibidosReenvioNotificacion) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.RecepcionDocumentoNoElectronicoNuevo) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.RecepcionCorreosRecibidos) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.RecepcionCorreosRecibidosVer) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.RecepcionCorreosRecibidosDescargar) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.RecepcionCorreosRecibidosAsociarAnexos) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.RecepcionCorreosRecibidosCrearDocumentoAsociarAnexos) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.RecepcionValidacionDocumentos) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.RecepcionValidacionDocumentosDescargar) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.RecepcionValidacionDocumentosAsignar) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.RecepcionValidacionDocumentosLiberar) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.RecepcionValidacionDocumentosValidar) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.RecepcionValidacionDocumentosRechazar) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.RecepcionValidacionDocumentosPagar) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.RecepcionGestionDocumentosEtapa2) || 
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.RecepcionGestionDocumentosEtapa2DescargarExcel) || 
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.RecepcionGestionDocumentosEtapa2GestionarFeDs) || 
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.RecepcionGestionDocumentosEtapa2CentroCosto) || 
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.RecepcionGestionDocumentosEtapa2SiguienteEtapa) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.RecepcionGestionDocumentosEtapa3) || 
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.RecepcionGestionDocumentosEtapa3DescargarExcel) || 
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.RecepcionGestionDocumentosEtapa3GestionarFeDs) || 
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.RecepcionGestionDocumentosEtapa3SiguienteEtapa) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.RecepcionGestionDocumentosEtapa4) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.RecepcionGestionDocumentosEtapa4DescargarExcel) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.RecepcionGestionDocumentosEtapa4GestionarFeDs) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.RecepcionGestionDocumentosEtapa4DatosContabilizado) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.RecepcionGestionDocumentosEtapa4SiguienteEtapa) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.RecepcionGestionDocumentosEtapa7) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.RecepcionGestionDocumentosEtapa7DescargarExcel) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.RecepcionReporteGestionDocumentos) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.RecepcionAutorizacionEtapas)
                )
            ) 
                return true;
        } 
        return false;
    }

    /**
     * Valida si el usuario tiene acceso al menú Gestión Documentos y sus opciones.
     *
     * @return {*}  {boolean}
     * @memberof Auth
     */
    validaAccesoGestionDocumentos(): boolean {
        if (this.jwtHelperService.tokenGetter()) {
            let aclsUsuario = this.getAcls();
            let usuario = this.jwtHelperService.decodeToken();
            if (
                usuario.ofe_recepcion === 'SI' &&
                (
                    this.existeRol(aclsUsuario.roles, this.permisosRoles.roles.Superadministrador) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.RecepcionGestionDocumentosEtapa1) || 
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.RecepcionGestionDocumentosEtapa2) || 
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.RecepcionGestionDocumentosEtapa2DescargarExcel) || 
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.RecepcionGestionDocumentosEtapa2GestionarFeDs) || 
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.RecepcionGestionDocumentosEtapa2CentroCosto) || 
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.RecepcionGestionDocumentosEtapa2SiguienteEtapa) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.RecepcionGestionDocumentosEtapa3) || 
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.RecepcionGestionDocumentosEtapa3DescargarExcel) || 
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.RecepcionGestionDocumentosEtapa3GestionarFeDs) || 
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.RecepcionGestionDocumentosEtapa3SiguienteEtapa) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.RecepcionGestionDocumentosEtapa4) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.RecepcionGestionDocumentosEtapa4DescargarExcel) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.RecepcionGestionDocumentosEtapa4GestionarFeDs) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.RecepcionGestionDocumentosEtapa4DatosContabilizado) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.RecepcionGestionDocumentosEtapa4SiguienteEtapa) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.RecepcionGestionDocumentosEtapa7) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.RecepcionGestionDocumentosEtapa7DescargarExcel)
                )
            ) 
                return true;
        } 
        return false;
    }

    /**
     * Valida si el usuario tiene acceso al menú Gestión Documentos y sus opciones.
     *
     * @return {*}  {boolean}
     * @memberof Auth
     */
    validaAccesoAutorizacionEtapas(): boolean {
        if (this.jwtHelperService.tokenGetter()) {
            let aclsUsuario = this.getAcls();
            let usuario = this.jwtHelperService.decodeToken();
            if (
                usuario.ofe_recepcion === 'SI' &&
                (
                    this.existeRol(aclsUsuario.roles, this.permisosRoles.roles.Superadministrador) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.RecepcionAutorizacionEtapas)
                )
            ) 
                return true;
        } 
        return false;
    }

    /**
     * Valida si el usuario tiene acceso al menú Radian y sus opciones.
     * 
     * @return {boolean}
     */
    validaAccesoRadian(): boolean {
        if (this.jwtHelperService.tokenGetter()) {
            let aclsUsuario = this.getAcls();
            if (this.existeRol(aclsUsuario.roles, this.permisosRoles.roles.Superadministrador) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.RadianRegistroDocumentos) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.RadianRegistroDocumentosLogErrores) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.RadianDocumentos) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.RadianDocumentosDescargar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.RadianDocumentosAccionesBloque) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.RadianDocumentosDescargarExcel) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.RadianDocumentosReenvioNotificacion) 
            ) 
                return true;
        } 
        return false;
    }

    /**
     * Valida si el usuario tiene acceso al menú de Nómina Electrónica y sus opciones.
     * 
     * @return bool
     */
    validaAccesoNominaElectronica () {
        if (this.jwtHelperService.tokenGetter()) {
            let aclsUsuario = this.getAcls();
            let usuario = this.jwtHelperService.decodeToken();
            if (
                usuario.nomina === 'SI' &&
                (
                    this.existeRol(aclsUsuario.roles, this.permisosRoles.roles.Superadministrador) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.DnDocumentosSinEnvio) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.DnDocumentosSinEnvioDescargar) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.DnDocumentosSinEnvioDescargarExcel) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.DnDocumentosSinEnvioEnvio) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.DnDocumentosEnviados) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.DnDocumentosEnviadosDescargar) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.DnDocumentosEnviadosDescargarExcel) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.DnDocumentosEnviadosDescargarJson) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.DnDocumentosEnviadosDescargarXml) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.DnDocumentosEnviadosDescargarArEstadoDian) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.DnDocumentosPorExcel) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.DnDocumentosPorExcelDescargarNomina) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.DnDocumentosPorExcelDescargarEliminar) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.DnDocumentosPorExcelSubirNomina) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.DnDocumentosPorExcelSubirEliminar)
                )
            ) 
                return true;
        } 
        return false;
    }

    /** 
     * Valida si el usuario tiene acceso en emisión al menú Resportes y sus opciones.
     * 
     * @return bool
     */
    validaAccesoEmisionReportes () {
        if (this.jwtHelperService.tokenGetter()) {
            let aclsUsuario = this.getAcls();
            let usuario = this.jwtHelperService.decodeToken();
            if (
                usuario.ofe_emision === 'SI' &&
                (
                    this.existeRol(aclsUsuario.roles, this.permisosRoles.roles.Superadministrador) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ReportePersonalizadoDhlExpress) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.EmisionReporteDocumentosProcesados) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.EmisionReporteNotificacionDocumentos) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.EmisionReporteBackground)
                )
            ) 
                return true;
        } 
        return false;
    }

    /* Valida si el usuario tiene acceso en Documento Soporte al menú Reportes y sus opciones.
     * 
     * @return bool
     */
    validaAccesoDocumentoSoporteReportes () {
        if (this.jwtHelperService.tokenGetter()) {
            let aclsUsuario = this.getAcls();
            let usuario = this.jwtHelperService.decodeToken();
            if (
                usuario.ofe_documento_soporte === 'SI' &&
                (
                this.existeRol(aclsUsuario.roles, this.permisosRoles.roles.Superadministrador) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.DocumentosSoporteReporteDocumentosProcesados) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.DocumentosSoporteReporteNotificacionDocumentos) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.DocumentosSoporteReporteBackground)
                )
            ) 
                return true;
        } 
        return false;
    }

    /** 
     * Valida si el usuario tiene accesoen Recepción al menú Reportes y sus opciones.
     * 
     * @return bool
     */
    validaAccesoRecepcionReportes() {
        if (this.jwtHelperService.tokenGetter()) {
            let aclsUsuario = this.getAcls();
            let usuario = this.jwtHelperService.decodeToken();
            if (
                usuario.ofe_recepcion === 'SI' &&
                (
                    this.existeRol(aclsUsuario.roles, this.permisosRoles.roles.Superadministrador) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.RecepcionReporteBackground) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.RecepcionReporteDocumentosProcesados) || 
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.RecepcionReporteGestionDocumentos) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.RecepcionReportesLogValidacionDocumentos) || 
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.RecepcionReporteDependencias)
                )
            ) 
                return true;
        } 
        return false;
    }

    /** 
     * Valida si el usuario tiene acceso en Radian al menú Reportes y sus opciones.
     * 
     * @return {boolean}
     */
    validaAccesoRadianReportes(): boolean {
        if (this.jwtHelperService.tokenGetter()) {
            let aclsUsuario = this.getAcls();
            if (
                this.existeRol(aclsUsuario.roles, this.permisosRoles.roles.Superadministrador) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.RadianDocumentosReporteBackground)
            )
                return true;
        } 
        return false;
    }

    /* Valida si el usuario tiene acceso en Nómina al menú Reportes y sus opciones.
     * 
     * @return bool
     */
    validaAccesoNominaReportes() {
        if (this.jwtHelperService.tokenGetter()) {
            let aclsUsuario = this.getAcls();
            let usuario = this.jwtHelperService.decodeToken();
            if (
                usuario.nomina === 'SI' &&
                (
                    this.existeRol(aclsUsuario.roles, this.permisosRoles.roles.Superadministrador) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.NominaReporteBackground)
                )
            ) 
                return true;
        } 
        return false;
    }

    /** 
     * Valida si el usuario tiene acceso al menú Documentos CCO y sus opciones.
     * 
     * @return bool
     */
    validaAccesoDocumentosCco () {
        if (this.jwtHelperService.tokenGetter()) {
            let aclsUsuario = this.getAcls();
            let usuario = this.jwtHelperService.decodeToken();
            if (
                usuario.ofe_emision === 'SI' &&
                (
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionPickupCashDatosComunesVer) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionPickupCashDatosComunesEditar) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionPickupCashDatosFijosVer) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionPickupCashDatosFijosEditar) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionPickupCashDatosVariablesVer) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionPickupCashDatosVariablesEditar) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionPickupCashExtracargosVer) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionPickupCashExtracargosNuevo) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionPickupCashExtracargosEditar) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionPickupCashExtracargosCambiarEstado) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionPickupCashProductosVer) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionPickupCashProductosNuevo) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionPickupCashProductosEditar) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionPickupCashProductosCambiarEstado) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionPickupCashNuevoDocumentoFactura) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionPickupCashEditarDocumentoFactura)
                )
            ) 
                return true;
        } 
        return false;
    }

    /* Valida si el usuario tiene acceso al menú Documentod CCO - Parámetros y sus opciones.
     * 
     * @return bool
     */
    validaAccesoDocumentosCcoParametros () {
        if (this.jwtHelperService.tokenGetter()) {
            let aclsUsuario = this.getAcls();
            let usuario = this.jwtHelperService.decodeToken();
            if (
                usuario.ofe_emision === 'SI' &&
                (
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionPickupCashDatosComunesVer) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionPickupCashDatosComunesEditar) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionPickupCashDatosFijosVer) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionPickupCashDatosFijosEditar) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionPickupCashDatosVariablesVer) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionPickupCashDatosVariablesEditar) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionPickupCashExtracargosVer) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionPickupCashExtracargosNuevo) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionPickupCashExtracargosEditar) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionPickupCashExtracargosCambiarEstado) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionPickupCashProductosVer) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionPickupCashProductosNuevo) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionPickupCashProductosEditar) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionPickupCashProductosCambiarEstado)
                )
            ) 
                return true;
        } 
        return false;
    }

    /* Valida si el usuario tiene acceso al menú Documentod CCO - Nuevo Documento y sus opciones.
     * 
     * @return bool
     */
    validaAccesoDocumentosCcoNuevoDocumento () {
        if (this.jwtHelperService.tokenGetter()) {
            let aclsUsuario = this.getAcls();
            let usuario = this.jwtHelperService.decodeToken();
            if (
                usuario.ofe_emision === 'SI' &&
                (
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionPickupCashNuevoDocumentoFactura)
                )
            ) 
                return true;
        } 
        return false;
    }

    /* Valida si el usuario tiene acceso al menú Facturación Web y sus opciones.
     * 
     * @return bool
     */
    validaAccesoFacturacionWeb () {
        if (this.jwtHelperService.tokenGetter()) {
            let aclsUsuario = this.getAcls();
            let usuario = this.jwtHelperService.decodeToken();
            if (
                (
                    usuario.ofe_emision === 'SI' &&
                    (
                        this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebControlConsecutivosNuevo) ||
                        this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebControlConsecutivosEditar) ||
                        this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebControlConsecutivosVer) ||
                        this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebControlConsecutivosCambiarEstado) ||
                        this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebCargosNuevo) ||
                        this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebCargosEditar) ||
                        this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebCargosVer) ||
                        this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebCargosCambiarEstado) ||
                        this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebCargosSubir) ||
                        this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebDescuentosNuevo) ||
                        this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebDescuentosEditar) ||
                        this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebDescuentosVer) ||
                        this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebDescuentosCambiarEstado) ||
                        this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebDescuentosSubir) ||
                        this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebProductosNuevo) ||
                        this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebProductosEditar) ||
                        this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebProductosVer) ||
                        this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebProductosCambiarEstado) ||
                        this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebProductosSubir) ||
                        this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebCrearFactura) ||
                        this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebEditarFactura) ||
                        this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebVerFactura) ||
                        this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebCrearNotaCredito) ||
                        this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebEditarNotaCredito) ||
                        this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebVerNotaCredito) ||
                        this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebCrearNotaDebito) ||
                        this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebEditarNotaDebito) ||
                        this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebVerNotaDebito)
                    )
                ) ||
                (
                    usuario.ofe_documento_soporte === 'SI' &&
                    (
                        this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebControlConsecutivosNuevo) ||
                        this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebControlConsecutivosEditar) ||
                        this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebControlConsecutivosVer) ||
                        this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebControlConsecutivosCambiarEstado) ||
                        this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebCargosNuevo) ||
                        this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebCargosEditar) ||
                        this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebCargosVer) ||
                        this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebCargosCambiarEstado) ||
                        this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebCargosSubir) ||
                        this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebDescuentosNuevo) ||
                        this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebDescuentosEditar) ||
                        this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebDescuentosVer) ||
                        this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebDescuentosCambiarEstado) ||
                        this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebDescuentosSubir) ||
                        this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebProductosNuevo) ||
                        this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebProductosEditar) ||
                        this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebProductosVer) ||
                        this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebProductosCambiarEstado) ||
                        this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebProductosSubir) ||
                        this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebCrearDocumentoSoporte) ||
                        this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebEditarDocumentoSoporte) ||
                        this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebVerDocumentoSoporte) ||
                        this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebCrearNotaCreditoDS) ||
                        this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebEditarNotaCreditoDS) ||
                        this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebVerNotaCreditoDS)
                    )
                )
            ) 
                return true;
        } 
        return false;
    }

    /* Valida si el usuario tiene acceso al menú Documentod CCO - Parámetros y sus opciones.
     * 
     * @return bool
     */
    validaAccesoFacturacionWebParametros () {
        if (this.jwtHelperService.tokenGetter()) {
            let aclsUsuario = this.getAcls();
            let usuario = this.jwtHelperService.decodeToken();
            if (
                (usuario.ofe_emision === 'SI' || usuario.ofe_documento_soporte === 'SI') &&
                (
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebControlConsecutivosNuevo) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebControlConsecutivosEditar) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebControlConsecutivosVer) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebControlConsecutivosCambiarEstado) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebCargosNuevo) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebCargosEditar) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebCargosVer) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebCargosCambiarEstado) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebCargosSubir) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebDescuentosNuevo) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebDescuentosEditar) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebDescuentosVer) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebDescuentosCambiarEstado) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebDescuentosSubir) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebProductosNuevo) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebProductosEditar) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebProductosVer) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebProductosCambiarEstado) ||
                    this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebProductosSubir)
                )
            ) 
                return true;
        } 
        return false;
    }

    /* Valida si el usuario tiene acceso al menú Facturación Web - Crear Documentos y sus opciones.
     * 
     * @return bool
     */
    validaAccesoFacturacionWebCrearDocumentos () {
        if (this.jwtHelperService.tokenGetter()) {
            let aclsUsuario = this.getAcls();
            let usuario = this.jwtHelperService.decodeToken();
            if (
                (
                    usuario.ofe_emision === 'SI' &&
                    (
                        this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebCrearFactura) ||
                        this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebEditarFactura) ||
                        this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebVerFactura) ||
                        this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebCrearNotaCredito) ||
                        this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebEditarNotaCredito) ||
                        this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebVerNotaCredito) ||
                        this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebCrearNotaDebito) ||
                        this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebEditarNotaDebito) ||
                        this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebVerNotaDebito)
                    )
                ) ||
                (
                    usuario.ofe_documento_soporte === 'SI' &&
                    (
                        this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebCrearDocumentoSoporte) || 
                        this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebEditarDocumentoSoporte) || 
                        this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebVerDocumentoSoporte) || 
                        this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebCrearNotaCreditoDS) || 
                        this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebEditarNotaCreditoDS) || 
                        this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.FacturacionWebVerNotaCreditoDS)
                    )
                )
            ) 
                return true;
        } 
        return false;
    }

    /**
     * Valida si el usuario tiene acceso al menú Configuración - Nómina Electrónica.
     * 
     * @return bool
     */
    validaAccesoConfiguracionNominaElectronica () {
        if (this.jwtHelperService.tokenGetter()) {
            let aclsUsuario = this.getAcls();
            if (this.existeRol(aclsUsuario.roles, this.permisosRoles.roles.Superadministrador) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionDnEmpleador) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionDnEmpleadorNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionDnEmpleadorEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionDnEmpleadorVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionDnEmpleadorCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionDnEmpleadorSubir) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionDnEmpleadorDescargarExcel) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionDnTrabajador) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionDnTrabajadorNuevo) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionDnTrabajadorEditar) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionDnTrabajadorVer) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionDnTrabajadorCambiarEstado) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionDnTrabajadorSubir) ||
                this.existePermiso(aclsUsuario.permisos, this.permisosRoles.permisos.ConfiguracionDnTrabajadorDescargarExcel)
            ) 
                return true;
        } 
        return false;
    }

    /**
     * Permite visualizar o no un sub item de un menu.
     * 
     * @param permiso_requerido
     */
    validaAccesoItem(permiso_requerido, superadmin = true) {
        if (this.jwtHelperService.tokenGetter()) {
            let aclsUsuario = this.getAcls();
            if (
                (
                    superadmin &&
                    this.existeRol(aclsUsuario.roles, this.permisosRoles.roles.Superadministrador)
                ) ||
                this.existePermiso(aclsUsuario.permisos, permiso_requerido)
            )
                return true;
        }
        return false;
    }

    /**
     * Permite visualizar o no una opcion de menu asociada a un modulo
     * 
     * @param permiso_requerido
     */
    validaAccesoModulo(permiso_requerido, modulo) {
        if (this.jwtHelperService.tokenGetter()) {
            let aclsUsuario = this.getAcls();
            let usuario     = this.jwtHelperService.decodeToken();
            let acceso      = false;

            acceso =  (modulo === 'ecm' && usuario.ecm === 'SI') ? true : false;

            if (
                (
                    this.existeRol(aclsUsuario.roles, this.permisosRoles.roles.Superadministrador) ||
                    this.existePermiso(aclsUsuario.permisos, permiso_requerido)
                ) &&
                acceso === true
            )
                return true;
        }
        return false;
    }

    /**
     * Permite construir los items de navegación conforme a los roles y permisos del usuario
     *
     * @memberof Auth
     */
    construirNavegacion() {
        // Salida del Sistema
        this._fuseNavigationService.updateNavigationItem('salir', {
            function: () => {
                this.logout();
            }
        });

        // Dashboard
        this._fuseNavigationService.updateNavigationItem('dashboard', {
            hidden: !this.loggedIn()
        });

        // Perfil de Usuario
        this._fuseNavigationService.updateNavigationItem('perfil_usuario', {
            hidden: !this.loggedIn() && !this.validaAccesoItem(this.permisosRoles.permisos.ActualizarPerfilUsuario)
        });

        this.construirMenuSistema();
        this.construirMenuParametros();
        this.construirMenuConfiguracion();
        this.construirMenuFacturacionWeb();
        this.construirMenuEmision();
        this.construirMenuDocumentoSoporte();
        this.construirMenuRecepcion();
        this.construirMenuNominaElectronica();
        this.construirMenuRadian();
    }

    /**
     * Permite construir los items del menú Sistema y sus opciones conforme a los roles y permisos del usuario.
     *
     * @memberof Auth
     */
    construirMenuSistema() {
        // Bloque Sistema
        this._fuseNavigationService.updateNavigationItem('sistema', {
            hidden: !this.validaAccesoSistema()
        });

        // Sistema - Administración Variables Sistema
        this._fuseNavigationService.updateNavigationItem('variables-sistema', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.SistemaAdministracionVariablesSistema) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.SistemaAdministracionVariablesSistemaEditar) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.SistemaAdministracionVariablesSistemaVer) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.SistemaAdministracionVariablesSistemaCambiarEstado) 
            )
        });

        // Sistema - Administración Festivos
        this._fuseNavigationService.updateNavigationItem('festivos', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.AdministracionFestivos) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.SistemaAdministracionFestivosNuevo) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.SistemaAdministracionFestivosEditar) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.SistemaAdministracionFestivosVer) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.SistemaAdministracionFestivosCambiarEstado)
            )
        });

        // Sistema - Administración Tiempos Aceptación Tácita
        this._fuseNavigationService.updateNavigationItem('tiempos-aceptacion-tacita', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.SistemaAdministracionTiemposAceptacionTacita) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.SistemaAdministracionTiemposAceptacionTacitaNuevo) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.SistemaAdministracionTiemposAceptacionTacitaEditar) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.SistemaAdministracionTiemposAceptacionTacitaVer) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.SistemaAdministracionTiemposAceptacionTacitaCambia)
            )
        });

        // Sistema - Administración Roles
        this._fuseNavigationService.updateNavigationItem('roles-usuarios', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.AdministracionRolesUsuarios) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.AdministracionRolesUsuariosNuevo) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.AdministracionRolesUsuariosEditar) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.AdministracionRolesUsuariosVer) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.AdministracionRolesUsuariosCambiarEstado)
            )
        });

        // Sistema - Administración Usuarios
        this._fuseNavigationService.updateNavigationItem('usuarios', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.AdministracionUsuarios) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.AdministracionUsuariosVer) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.AdministracionUsuariosNuevo) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.AdministracionUsuariosEditar) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.AdministracionUsuariosCambiarEstado) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.AdministracionUsuariosDescargarExcel) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.AdministracionUsuariosSubir) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.AdministracionUsuariosBajarUsuariosExcel)
            ) 
        });
    }

    /**
     * Permite construir los items del menú Parámetros y sus opciones conforme a los roles y permisos del usuario.
     *
     * @memberof Auth
     */
    construirMenuParametros() {
        // Bloque Parámetros DIAN
        this._fuseNavigationService.updateNavigationItem('parametros', {
            hidden: !this.validaAccesoParametros()
        });

        // Parámetro - Administración Paises
        this._fuseNavigationService.updateNavigationItem('paises', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaPaises) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaPaisesVer) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaPaisesNuevo) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaPaisesEditar) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaPaisesCambiarEstado)
            )
        });

        // Parámetro - Administración Departamentos
        this._fuseNavigationService.updateNavigationItem('departamentos', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaDepartamentos) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaDepartamentosVer) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaDepartamentosNuevo) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaDepartamentosEditar) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaDepartamentosCambiarEstado)
            )
        });

        // Parámetro - Administración Municipios
        this._fuseNavigationService.updateNavigationItem('municipios', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaMunicipios) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaMunicipiosVer) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaMunicipiosNuevo) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaMunicipiosEditar) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaMunicipiosCambiarEstado)
            )
        });

        // Parámetro - Administración Clasificación Productos
        this._fuseNavigationService.updateNavigationItem('clasificacion-productos', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaClasificacionProductos) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaClasificacionProductosVer) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaClasificacionProductosNuevo) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaClasificacionProductosEditar) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaClasificacionProductosCambiarEstado)
            )
        });

        // Parámetro - Administración Códigos Descuentos
        this._fuseNavigationService.updateNavigationItem('codigos-descuentos', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaCodigosDescuentos) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaCodigoDescuentoVer) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaCodigoDescuentoNuevo) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaCodigoDescuentoEditar) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaCodigoDescuentoCambiarEstado)
            )
        });

        // Parámetro - Administración Códigos Postales
        this._fuseNavigationService.updateNavigationItem('codigos-postales', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaCodigosPostales) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaCodigoPostalVer) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaCodigoPostalNuevo) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaCodigoPostalEditar) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaCodigoPostalCambiarEstado)
            )
        });

        // Parámetro - Administración Colombia Compra Eficiente
        this._fuseNavigationService.updateNavigationItem('colombia-compra-eficiente', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaColombiaCompraEficiente) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaColombiaCompraEficienteVer) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaColombiaCompraEficienteNuevo) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaColombiaCompraEficienteEditar) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaColombiaCompraEficienteCambiarEstado)
            )
        });

        // Parámetro - Administración Formas de Pago
        this._fuseNavigationService.updateNavigationItem('formas-pago', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaFormasPago) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaFormasPagoVer) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaFormasPagoNuevo) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaFormasPagoEditar) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaFormasPagoCambiarEstado)
            )
        });

        // Parámetro - Administración Medios de Pago
        this._fuseNavigationService.updateNavigationItem('medios-pago', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaMediosPago) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaMediosPagoVer) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaMediosPagoNuevo) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaMediosPagoEditar) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaMediosPagoCambiarEstado)
            )
        });

        // Parámetro - Administración Mandatos
        this._fuseNavigationService.updateNavigationItem('mandatos', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaMandatos) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaMandatosVer) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaMandatosNuevo) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaMandatosEditar) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaMandatosCambiarEstado)
            )
        });

        // Parámetro - Administración Condiciones de Entrega
        this._fuseNavigationService.updateNavigationItem('condiciones-entrega', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaCondicionesEntrega) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaCondicionesEntregaVer) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaCondicionesEntregaNuevo) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaCondicionesEntregaEditar) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaCondicionesEntregaCambiarEstado)
            )
        });

        // Parámetro - Administración Monedas
        this._fuseNavigationService.updateNavigationItem('monedas', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaMonedas) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaMonedasVer) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaMonedasNuevo) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaMonedasEditar) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaMonedasCambiarEstado)
            )
        });

        // Parámetro - Administración Partidas Arancelarias
        this._fuseNavigationService.updateNavigationItem('partidas-arancelarias', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaPartidasArancelarias) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaPartidasArancelariasVer) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaPartidasArancelariasNuevo) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaPartidasArancelariasEditar) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaPartidasArancelariasCambiarEstado)
            )
        });

        // Parámetro - Administración Precios de Referencia
        this._fuseNavigationService.updateNavigationItem('precios-referencia', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaPreciosReferencia) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaPreciosReferenciaVer) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaPreciosReferenciaNuevo) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaPreciosReferenciaEditar) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaPreciosReferenciaCambiarEstado)
            )
        });

        // Parámetro - Administración Regimen Fiscal
        this._fuseNavigationService.updateNavigationItem('regimen-fiscal', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaRegimenFiscal) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaRegimenFiscalVer) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaRegimenFiscalNuevo) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaRegimenFiscalEditar) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaRegimenFiscalCambiarEstado)
            )
        });

        // Parámetro - Administración Responsabilidades Fiscales
        this._fuseNavigationService.updateNavigationItem('responsabilidades-fiscales', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaResponsabilidadesFiscales) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaResponsabilidadesFiscalesVer) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaResponsabilidadesFiscalesNuevo) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaResponsabilidadesFiscalesEditar) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaResponsabilidadesFiscalesCambiarEstado)
            )
        });

        // Parámetro - Administración Tipos de Documentos
        this._fuseNavigationService.updateNavigationItem('tipos-documentos', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaTiposDocumentos) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaTiposDocumentosVer) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaTiposDocumentosNuevo) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaTiposDocumentosEditar) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaTiposDocumentosCambiarEstado)
            )
        });

        // Parámetro - Administración Tipos de Organización Jurídica
        this._fuseNavigationService.updateNavigationItem('tipos-organizacion-juridica', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaTiposOrganizacionJuridica) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaTiposOrganizacionJuridicaVer) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaTiposOrganizacionJuridicaNuevo) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaTiposOrganizacionJuridicaEditar) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaTiposOrganizacionJuridicaCambiarEstado)
            )
        });

        // Parámetro - Administración Procedencia Vendedor
        this._fuseNavigationService.updateNavigationItem('procedencia-vendedor', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaProcedenciaVendedor) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaProcedenciaVendedorVer) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaProcedenciaVendedorNuevo) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaProcedenciaVendedorEditar) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaProcedenciaVendedorCambiarEstado)
            )
        });

        // Parámetro - Administración Tipos Documentos Electrónicos
        this._fuseNavigationService.updateNavigationItem('tipos-documentos-electronicos', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaTiposDocumentosElectronicos) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaTiposDocumentosElectronicosVer) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaTiposDocumentosElectronicosNuevo) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaTiposDocumentosElectronicosEditar) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaTiposDocumentosElectronicosCambiarEstado)
            )
        });
        
        // Parámetro - Administración Tipos Operación
        this._fuseNavigationService.updateNavigationItem('tipos-operacion', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaTiposOperacion) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaTiposOperacionVer) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaTiposOperacionNuevo) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaTiposOperacionEditar) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaTiposOperacionCambiarEstado)
            )
        });

        // Parámetro - Administración Tributos
        this._fuseNavigationService.updateNavigationItem('tributos', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaTributos) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaTributosVer) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaTributosNuevo) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaTributosEditar) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaTributosCambiarEstado)
            )
        });

        // Parámetro - Administración Tarifas Impuesto
        this._fuseNavigationService.updateNavigationItem('tarifas-impuesto', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaTarifasImpuesto) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaTarifasImpuestoVer) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaTarifasImpuestoNuevo) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaTarifasImpuestoEditar) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaTarifasImpuestoCambiarEstado)
            )
        });

        // Parámetro - Administración Unidades
        this._fuseNavigationService.updateNavigationItem('unidades', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaUnidades) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaUnidadesVer) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaUnidadesNuevo) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaUnidadesEditar) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaUnidadesCambiarEstado)
            )
        });

        // Parámetro - Administración Referencia a Otros Documentos
        this._fuseNavigationService.updateNavigationItem('referencia-otros-documentos', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaReferenciaOtrosDocumentos) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaReferenciaOtrosDocumentosVer) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaReferenciaOtrosDocumentosNuevo) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaReferenciaOtrosDocumentosEditar) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaReferenciaOtrosDocumentosCambiarEstado)
            )
        });

        // Parámetro - Administración Conceptos Correccion
        this._fuseNavigationService.updateNavigationItem('conceptos-correccion', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaConceptosCorreccion) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaConceptosCorreccionVer) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaConceptosCorreccionNuevo) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaConceptosCorreccionEditar) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaConceptosCorreccionCambiarEstado)
            )
        });

        // Parámetro - Administración Conceptos de Rechazo
        this._fuseNavigationService.updateNavigationItem('conceptos-rechazo', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaConceptosRechazo) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaConceptosRechazoVer) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaConceptosRechazoNuevo) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaConceptosRechazoEditar) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaConceptosRechazoCambiarEstado)
            )
        });

        // Parámetro - Administración Ambiente Destino Documentos
        this._fuseNavigationService.updateNavigationItem('ambiente-destino-documentos', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaAmbienteDestinoDocumentos) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaAmbienteDestinoDocumentosVer) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaAmbienteDestinoDocumentosNuevo) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaAmbienteDestinoDocumentosEditar) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaAmbienteDestinoDocumentosCambiarEstado)
            )
        });

        // Parámetro - Administración Formas de Generación y Transmisión
        this._fuseNavigationService.updateNavigationItem('formas-generacion-transmision', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaFormaGeneracionTransmision) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaFormaGeneracionTransmisionVer) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaFormaGeneracionTransmisionNuevo) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaFormaGeneracionTransmisionEditar) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaFormaGeneracionTransmisionCambiarEstado)
            )
        });

        // Parámetros - Sector Salud
        this.construirMenuParametrosSectorSalud();

        // Parámetros - Sector Transporte
        this.construirMenuParametrosSectorTransporte();

        // Parámetros - Sector Cambiario
        this.construirMenuParametrosSectorCambiario();

        // Parámetros - Nómina Electrónica
        this.construirMenuParametrosNominaElectronica();

        // Parámetros - Radian
        this.construirMenuParametrosRadian();
    }

    /**
     * Permite construir los items del menú Parámetros - Sector Salud y sus opciones conforme a los roles y permisos del usuario.
     *
     * @memberof Auth
     */
    construirMenuParametrosSectorSalud() {
        // Parámetros - Sector Salud
        this._fuseNavigationService.updateNavigationItem('sector-salud', {
            hidden: !this.validaAccesoParametrosSectorSalud()
        });

        // Parámetro - Sector Salud - Documentos Identificacion
        this._fuseNavigationService.updateNavigationItem('documentos-identificacion', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaSectorSaludDocumentosIdentificacion, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaSectorSaludDocumentosIdentificacionVer, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaSectorSaludDocumentosIdentificacionNuevo, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaSectorSaludDocumentosIdentificacionEditar, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaSectorSaludDocumentosIdentificacionCambiarEstado, false)
            )
        });

        // Parámetro - Sector Salud - Tipo Usuario
        this._fuseNavigationService.updateNavigationItem('tipo-usuario', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaSectorSaludTipoUsuario, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaSectorSaludTipoUsuarioVer, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaSectorSaludTipoUsuarioNuevo, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaSectorSaludTipoUsuarioEditar, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaSectorSaludTipoUsuarioCambiarEstado, false)
            )
        });

        // Parámetro - Sector Salud - Modalidades
        this._fuseNavigationService.updateNavigationItem('modalidad-contratacion-pago', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaSectorSaludModalidades, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaSectorSaludModalidadesVer, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaSectorSaludModalidadesNuevo, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaSectorSaludModalidadesEditar, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaSectorSaludModalidadesCambiarEstado, false)
            )
        });

        // Parámetro - Sector Salud - Cobertura
        this._fuseNavigationService.updateNavigationItem('cobertura', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaSectorSaludCobertura, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaSectorSaludCoberturaVer, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaSectorSaludCoberturaNuevo, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaSectorSaludCoberturaEditar, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaSectorSaludCoberturaCambiarEstado, false)
            )
        });

        // Parámetro - Sector Salud - Tipo Documento Referenciado
        this._fuseNavigationService.updateNavigationItem('tipo-documentos-referenciados', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaSectorSaludDocumentoReferenciado, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaSectorSaludDocumentoReferenciadoVer, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaSectorSaludDocumentoReferenciadoNuevo, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaSectorSaludDocumentoReferenciadoEditar, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaSectorSaludDocumentoReferenciadoCambiarEstado, false)
            )
        });
    }

    /**
     * Permite construir los items del menú Parámetros - Sector Transporte y sus opciones conforme a los roles y permisos del usuario.
     *
     * @memberof Auth
     */
    construirMenuParametrosSectorTransporte() {
        // Parámetros - Sector Transporte
        this._fuseNavigationService.updateNavigationItem('sector-transporte', {
            hidden: !this.validaAccesoParametrosSectorTransporte()
        });

        // Parámetro - Sector Transporte - Registros
        this._fuseNavigationService.updateNavigationItem('transporte-registros', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaSectorTransporteRegistro, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaSectorTransporteRegistroVer, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaSectorTransporteRegistroNuevo, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaSectorTransporteRegistroEditar, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaSectorTransporteRegistroCambiarEstado, false)
            )
        });

        // Parámetro - Sector Transporte - Remesas
        this._fuseNavigationService.updateNavigationItem('transporte-remesas', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaSectorTransporteRemesa, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaSectorTransporteRemesaVer, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaSectorTransporteRemesaNuevo, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaSectorTransporteRemesaEditar, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaSectorTransporteRemesaCambiarEstado, false)
            )
        });
    }

    /**
     * Permite construir los items del menú Parámetros - Sector Cambiario y sus opciones conforme a los roles y permisos del usuario.
     *
     * @memberof Auth
     */
    construirMenuParametrosSectorCambiario() {
        // Parámetros - Sector Cambiario
        this._fuseNavigationService.updateNavigationItem('sector-cambiario', {
            hidden: !this.validaAccesoParametrosSectorCambiario()
        });

        // Parámetro - Sector Cambiario - Registros
        this._fuseNavigationService.updateNavigationItem('mandatos-profesional-cambios', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaSectorCambiarioMandatoProfesional, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaSectorCambiarioMandatoProfesionalVer, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaSectorCambiarioMandatoProfesionalEditar, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaSectorCambiarioMandatoProfesionalNuevo, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaSectorCambiarioMandatoProfesionalCambiarEstado, false)
            )
        });

        // Parámetro - Sector Cambiario - Debida Diligencia
        this._fuseNavigationService.updateNavigationItem('debida-diligencia', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaSectorCambiarioDebidaDiligencia, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaSectorCambiarioDebidaDiligenciaVer, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaSectorCambiarioDebidaDiligenciaNuevo, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaSectorCambiarioDebidaDiligenciaEditar, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaSectorCambiarioDebidaDiligenciaCambiarEstado, false)
            )
        });
    }

    /**
     * Permite construir los items del menú Parámetros - Nomna Electrónica y sus opciones conforme a los roles y permisos del usuario.
     *
     * @memberof Auth
     */
    construirMenuParametrosNominaElectronica() {
        // Parámetros - Nomina Electrónica
        this._fuseNavigationService.updateNavigationItem('parametros-nomina-electronica', {
            hidden: !this.validaAccesoParametrosNominaElectronica()
        });

        // Parámetro - Nomina Electrónica - Periodo Nomina
        this._fuseNavigationService.updateNavigationItem('periodo-nomina', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaNominaPeriodo, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaNominaPeriodoVer, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaNominaPeriodoEditar, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaNominaPeriodoNuevo, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaNominaPeriodoCambiarEstado, false)
            )
        });

        // Parámetro - Nomina Electrónica - Tipo Contrato
        this._fuseNavigationService.updateNavigationItem('tipo-contrato', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaTipoContrato, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaTipoContratoVer, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaTipoContratoEditar, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaTipoContratoNuevo, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaTipoContratoCambiarEstado, false)
            )
        });

        // Parámetro - Nomina Electrónica - Tipo Trabajador
        this._fuseNavigationService.updateNavigationItem('tipo-trabajador', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaTipoTrabajador, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaTipoTrabajadorVer, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaTipoTrabajadorEditar, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaTipoTrabajadorNuevo, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaTipoTrabajadorCambiarEstado, false)
            )
        });

        // Parámetro - Nomina Electrónica - Subtipo Trabajador
        this._fuseNavigationService.updateNavigationItem('subtipo-trabajador', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaSubtipoTrabajador, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaSubtipoTrabajadorVer, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaSubtipoTrabajadorEditar, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaSubtipoTrabajadorNuevo, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaSubtipoTrabajadorCambiarEstado, false)
            )
        });

        // Parámetro - Nomina Electrónica - Tipo Hora Extra o Recargo
        this._fuseNavigationService.updateNavigationItem('tipo-hora-extra-recargo', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaTipoHoraExtraRecargo, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaTipoHoraExtraRecargoVer, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaTipoHoraExtraRecargoEditar, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaTipoHoraExtraRecargoNuevo, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaTipoHoraExtraRecargoCambiarEstado, false)
            )
        });

        // Parámetro - Nomina Electrónica - Tipo Incapacidad
        this._fuseNavigationService.updateNavigationItem('tipo-incapacidad', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaTipoIncapacidad, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaTipoIncapacidadVer, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaTipoIncapacidadEditar, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaTipoIncapacidadNuevo, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaTipoIncapacidadCambiarEstado, false)
            )
        });

        // Parámetro - Nomina Electrónica - Tipo Nota
        this._fuseNavigationService.updateNavigationItem('tipo-nota', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaTipoNota, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaTipoNotaVer, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaTipoNotaEditar, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaTipoNotaNuevo, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaTipoNotaCambiarEstado, false)
            )
        });
    }

    /**
     * Permite construir los ítems del menú Parámetros - Radian y sus opciones conforme a los roles y permisos del usuario.
     *
     * @memberof Auth
     */
    construirMenuParametrosRadian() {
        // Parámetros - Radian
        this._fuseNavigationService.updateNavigationItem('parametros-radian', {
            hidden: !this.validaAccesoParametrosRadian()
        });

        // Parámetro - Radian - Referencia Documentos Electrónicos
        this._fuseNavigationService.updateNavigationItem('referencia-documentos-electronicos', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaRadianReferenciaDocumentosElectronicos, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaRadianReferenciaDocumentosElectronicosVer, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaRadianReferenciaDocumentosElectronicosNuevo, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaRadianReferenciaDocumentosElectronicosEditar, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaRadianReferenciaDocumentosElectronicosCambiarEstado, false)
            )
        });

        // Parámetro - Radian - Tipos Pagos
        this._fuseNavigationService.updateNavigationItem('tipos-pagos', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaRadianTiposPagos, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaRadianTiposPagosVer, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaRadianTiposPagosNuevo, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaRadianTiposPagosEditar, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaRadianTiposPagosCambiarEstado, false)
            )
        });

        // Parámetro - Radian - Tiempo Mandatos
        this._fuseNavigationService.updateNavigationItem('tiempo-mandato', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaRadianTiempoMandato, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaRadianTiempoMandatoVer, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaRadianTiempoMandatoEditar, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaRadianTiempoMandatoNuevo, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaRadianTiempoMandatoCambiarEstado, false)
            )
        });

        // Parámetro - Radian - Tipo Mandatario
        this._fuseNavigationService.updateNavigationItem('tipo-mandatario', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaRadianTipoMandatario, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaRadianTipoMandatarioVer, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaRadianTipoMandatarioEditar, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaRadianTipoMandatarioNuevo, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaRadianTipoMandatarioCambiarEstado, false)
            )
        });

        // Parámetro - Radian - Naturaleza Mandato
        this._fuseNavigationService.updateNavigationItem('naturaleza-mandato', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaRadianNaturalezaMandato, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaRadianNaturalezaMandatoVer, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaRadianNaturalezaMandatoNuevo, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaRadianNaturalezaMandatoEditar, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaRadianNaturalezaMandatoCambiarEstado, false)
            )
        });

        // Parámetro - Radian - Tipo Mandante
        this._fuseNavigationService.updateNavigationItem('tipo-mandante', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaRadianTipoMandante, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaRadianTipoMandanteVer, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaRadianTipoMandanteNuevo, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaRadianTipoMandanteEditar, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaRadianTipoMandanteCambiarEstado, false)
            )
        });

        // Parámetro - Radian - Evento Documento Electronico
        this._fuseNavigationService.updateNavigationItem('evento-documento-electronico', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaRadianEventoDocumentoElectronico, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaRadianEventoDocumentoElectronicoVer, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaRadianEventoDocumentoElectronicoNuevo, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaRadianEventoDocumentoElectronicoEditar, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaRadianEventoDocumentoElectronicoCambiarEstado, false)
            )
        });

        // Parámetro - Radian - Tipos Operacion
        this._fuseNavigationService.updateNavigationItem('tipo-operacion', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaRadianTipoOperacion, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaRadianTipoOperacionVer, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaRadianTipoOperacionNuevo, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaRadianTipoOperacionEditar, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaRadianTipoOperacionCambiarEstado, false)
            )
        });

        // Parámetro - Radian - Factor
        this._fuseNavigationService.updateNavigationItem('factor', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaRadianFactor, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaRadianFactorVer, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaRadianFactorNuevo, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaRadianFactorEditar, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaRadianFactorCambiarEstado, false)
            )
        });

        // Parámetro - Radian - Roles
        this._fuseNavigationService.updateNavigationItem('roles', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaRadianRoles, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaRadianRolesVer, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaRadianRolesNuevo, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaRadianRolesEditar, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaRadianRolesCambiarEstado, false)
            )
        });

        // Parámetro - Radian - Endosos
        this._fuseNavigationService.updateNavigationItem('endoso', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaRadianEndoso, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaRadianEndosoVer, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaRadianEndosoNuevo, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaRadianEndosoEditar, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaRadianEndosoCambiarEstado, false)
            ) 
        });

        // Parámetro - Radian - Alcance Mandatos
        this._fuseNavigationService.updateNavigationItem('alcance-mandato', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaRadianAlcanceMandato, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaRadianAlcanceMandatoVer, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaRadianAlcanceMandatoNuevo, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaRadianAlcanceMandatoEditar, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ParametricaRadianAlcanceMandatoCambiarEstado, false)
            )
        });
    }

    /**
     * Permite construir los items del menú configuración y sus opciones conforme a los roles y permisos del usuario.
     *
     * @memberof Auth
     */
    construirMenuConfiguracion() {
        // Módulo Configuración
        this._fuseNavigationService.updateNavigationItem('configuracion', {
            hidden: !this.validaAccesoConfiguracion()
        });

        // Módulo Configuración
        this._fuseNavigationService.updateNavigationItem('configuracion-comunes', {
            hidden: !this.validaAccesoConfiguracionComunes()
        });

        // Módulo Configuración - Emisión
        this._fuseNavigationService.updateNavigationItem('configuracion-emision', {
            hidden: !this.validaAccesoConfiguracionEmision()
        });

        // Módulo Configuración - Recepción
        this._fuseNavigationService.updateNavigationItem('configuracion-recepcion', {
            hidden: !this.validaAccesoConfiguracionRecepcion()
        });

        // Módulo Configuración - openECM
        this._fuseNavigationService.updateNavigationItem('configuracion-ecm', {
            hidden: !this.validaAccesoConfiguracionIntegracionEcm()
        });

        // Módulo Configuración -  Reportes
        this._fuseNavigationService.updateNavigationItem('configuracion-reportes', {
            hidden: !this.validaAccesoConfiguracionReportes()
        });

        // Configuración - Administración Adquirentes
        this._fuseNavigationService.updateNavigationItem('adquirentes', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionAdquirentes, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionAdquirentesCambiarEstado, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionAdquirentesDescargarExcel, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionAdquirentesEditar, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionAdquirentesNuevo, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionAdquirentesSubir, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionAdquirentesVer, false)
            )
        });

        // Configuración - Administración Autorizados
        this._fuseNavigationService.updateNavigationItem('autorizados', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionAutorizados, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionAutorizadosCambiarEstado, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionAutorizadosDescargarExcel, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionAutorizadosEditar, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionAutorizadosNuevo, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionAutorizadosSubir, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionAutorizadosVer, false)
            )
        });

        // Configuración - Administración Responsables
        this._fuseNavigationService.updateNavigationItem('responsables', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionResponsables, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionResponsablesCambiarEstado, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionResponsablesDescargarExcel, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionResponsablesEditar, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionResponsablesNuevo, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionResponsablesSubir, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionResponsablesVer, false)
            )
        });

        // Configuración - Administración Vendedores
        this._fuseNavigationService.updateNavigationItem('vendedores', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionVendedorDS, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionVendedorDSCambiarEstado, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionVendedorDSDescargarExcel, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionVendedorDSEditar, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionVendedorDSNuevo, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionVendedorDSSubir, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionVendedorDSVer, false)
            )
        });

        // Configuración - Administración OFEs
        this._fuseNavigationService.updateNavigationItem('oferentes', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionOFE, false) && 
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionOFENuevo, false) && 
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionOFEEditar, false) && 
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionOFEVer, false) && 
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionOFECambiarEstado, false) && 
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionOFESubir, false) && 
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfigurarDocumentoElectronico, false) && 
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfigurarDocumentoSoporte, false) && 
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfigurarServicios, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ValoresDefectoDocumento, false)
            )
        });

        // Configuración - Actores Radian
        this._fuseNavigationService.updateNavigationItem('radian-actores', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionRadianActores, false) && 
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionRadianActoresNuevo, false) && 
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionRadianActoresEditar, false) && 
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionRadianActoresVer, false) && 
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionRadianActoresCambiarEstado, false) && 
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionRadianActoresSubir, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionRadianActoresDescargarExcel, false)
            )
        });

        // Configuración - Administración Software Proveedor Tecnológico
        this._fuseNavigationService.updateNavigationItem('software-proveedor-tecnologico', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.SoftwareProveedorTecnologico, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.SoftwareProveedorTecnologicoNuevo, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.SoftwareProveedorTecnologicoEditar, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.SoftwareProveedorTecnologicoVer, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.SoftwareProveedorTecnologicoSubir, false) && 
                !this.validaAccesoItem(this.permisosRoles.permisos.SoftwareProveedorTecnologicoCambiarEstado, false)
            )
        });

        // Configuración - Comunes - Administración Grupos de Trabajo
        this.construirMenuConfiguracionGruposTrabajo();

        // Configuración - Administración XPath Documentos Electrónicos Estándar
        this._fuseNavigationService.updateNavigationItem('xpath-documentos-electronicos-estandar', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionXPathDEEstandar, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionXPathDEEstandarNuevo, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionXPathDEEstandarEditar, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionXPathDEEstandarVer, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionXPathDEEstandarCambiarEstado, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionXPathDEEstandarDescargar, false)
            )
        });

        // Configuración - Administración XPath Documentos Electrónicos Personalizados
        this._fuseNavigationService.updateNavigationItem('xpath-documentos-electronicos-personalizados', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionXPathDEPersonalizado, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionXPathDEPersonalizadoNuevo, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionXPathDEPersonalizadoEditar, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionXPathDEPersonalizadoVer, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionXPathDEPersonalizadoCambiarEstado, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionXPathDEPersonalizadoDescargar, false)
            )
        });

        // Configuración - Administración Resoluciones de Facturación
        this._fuseNavigationService.updateNavigationItem('resoluciones-facturacion', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionResolucionesFacturacion, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionResolucionesFacturacionCambiarEstado, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionResolucionesFacturacionEditar, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionResolucionesFacturacionNuevo, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionResolucionesFacturacionSubir, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionResolucionesFacturacionVer, false)
            )
        });

        // Configuración - Administración Proveedores
        this._fuseNavigationService.updateNavigationItem('proveedores', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionProveedores, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionProveedoresCambiarEstado, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionProveedoresEditar, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionProveedoresNuevo, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionProveedoresSubir, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionProveedoresVer, false)
            )
        });

        // Configuración - Administración Autorizaciones Eventos DIAN
        this._fuseNavigationService.updateNavigationItem('autorizaciones-eventos-dian', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionAutorizacionesEventosDian, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionAutorizacionesEventosDianNuevo, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionAutorizacionesEventosDianEditar, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionAutorizacionesEventosDianVer, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionAutorizacionesEventosDianCambiarEstado, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionAutorizacionesEventosDianSubir, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionAutorizacionesEventosDianDescargarExcel, false)
            )
        });

        // Configuración - Administración Recepción ERP
        this._fuseNavigationService.updateNavigationItem('administracion-recepcion-erp', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionAdministracionRecepcionERP, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionAdministracionRecepcionERPNuevo, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionAdministracionRecepcionERPEditar, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionAdministracionRecepcionERPVer, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionAdministracionRecepcionERPCambiarEstado, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionAdministracionRecepcionERPSubir, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionAdministracionRecepcionERPDescargarExcel, false)
            )
        });

        // Configuración - Recepcion - Centros Costo
        this._fuseNavigationService.updateNavigationItem('centros-costo', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionCentrosCosto, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionCentrosCostoNuevo, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionCentrosCostoEditar, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionCentrosCostoVer, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionCentrosCostoCambiarEstado, false)
            )
        });

        // Configuración - Recepcion - Causales Devolucion
        this._fuseNavigationService.updateNavigationItem('causales-devolucion', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionCausalesDevolucion, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionCausalesDevolucionNuevo, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionCausalesDevolucionEditar, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionCausalesDevolucionVer, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionCausalesDevolucionCambiarEstado, false)
            )
        });

        // Configuración - Recepcion - Centros Operacion
        this._fuseNavigationService.updateNavigationItem('centros-operacion', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionCentrosOperacion, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionCentrosOperacionNuevo, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionCentrosOperacionEditar, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionCentrosOperacionVer, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionCentrosOperacionCambiarEstado, false)
            )
        });

        // Configuración - Administración Usuarios Ecm
        this._fuseNavigationService.updateNavigationItem('usuarios-ecm', {
            hidden: (
                !this.validaAccesoModulo(this.permisosRoles.permisos.ConfiguracionUsuarioEcm, 'ecm') &&
                !this.validaAccesoModulo(this.permisosRoles.permisos.ConfiguracionUsuarioEcmNuevo, 'ecm') &&
                !this.validaAccesoModulo(this.permisosRoles.permisos.ConfiguracionUsuarioEcmEditar, 'ecm') &&
                !this.validaAccesoModulo(this.permisosRoles.permisos.ConfiguracionUsuarioEcmVer, 'ecm') &&
                !this.validaAccesoModulo(this.permisosRoles.permisos.ConfiguracionUsuarioEcmCambiarEstado, 'ecm') &&
                !this.validaAccesoModulo(this.permisosRoles.permisos.ConfiguracionUsuarioEcmSubir, 'ecm') &&
                !this.validaAccesoModulo(this.permisosRoles.permisos.ConfiguracionUsuarioEcmDescargarExcel, 'ecm')
            )
        });

        let usuario   = this.jwtHelperService.decodeToken();
        let existeFNC = false;
        if(usuario && usuario.ofe_recepcion_fnc && usuario.ofe_recepcion_fnc === 'SI')
            existeFNC = true;

        // Condiguración - Recepción - Fondos (Aplica solamente a FNC)
        this._fuseNavigationService.updateNavigationItem('fondos', {
            hidden: (!existeFNC ||
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionRecepcionFondos) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionRecepcionFondosNuevo) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionRecepcionFondosEditar) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionRecepcionFondosVer) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionRecepcionFondosCambiarEstado)
            )
        });

        // Configuración - Nómina Electrónica
        this.construirMenuConfiguracionNominaElectronica();

        // Configuración - Reportes - Reportes en Background
        this._fuseNavigationService.updateNavigationItem('configuracion-reportes-background', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionAdquirentesDescargarExcel, false)
            )
        });
    }

    /**
     * Permite construir los items del menú Configuración - Comunes - Grupos Trabajo y sus opciones conforme a los roles y permisos del usuario.
     *
     * @memberof Auth
     */
    construirMenuConfiguracionGruposTrabajo() {
        // Configuración - Comunes - Grupos Trabajo
        this._fuseNavigationService.updateNavigationItem('configuracion-grupos-trabajo', {
            hidden: !this.validaAccesoConfiguracionGruposTrabajo()
        });

        // Configuración - Comunes - Grupos Trabajo - Administración
        this._fuseNavigationService.updateNavigationItem('administracion-grupos-trabajo', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAdministracion, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAdministracionNuevo, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAdministracionEditar, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAdministracionCambiarEstado, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAdministracionSubir, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAdministracionVer, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAdministracionDescargarExcel, false)
            )
        });

        // Configuración - Comunes - Grupos Trabajo - Asociar Usuarios
        this._fuseNavigationService.updateNavigationItem('asociar-usuarios', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAsociarUsuario, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAsociarUsuarioNuevo, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAsociarUsuarioCambiarEstado, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAsociarUsuarioSubir, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAsociarUsuarioDescargarExcel, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAsociarUsuarioVerUsuariosAsociados, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAsociarUsuarioEditarUsuariosAsociados, false)
            )
        });

        // Configuración - Comunes - Grupos Trabajo - Asociar Proveedores
        this._fuseNavigationService.updateNavigationItem('asociar-proveedores', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAsociarProveedor, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAsociarProveedorNuevo, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAsociarProveedorCambiarEstado, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAsociarProveedorSubir, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAsociarProveedorDescargarExcel, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionGrupoTrabajoAsociarProveedorVerProveedoresAsociados, false)
            )
        });
    }

    /**
     * Permite construir los items del menú Configuración - Nómina Electrónica y sus opciones conforme a los roles y permisos del usuario.
     *
     * @memberof Auth
     */
    construirMenuConfiguracionNominaElectronica() {
        // Configuración - Nómina Electrónica
        this._fuseNavigationService.updateNavigationItem('nomina-electonica', {
            hidden: !this.validaAccesoConfiguracionNominaElectronica()
        });

        // Configuración - Nómina Electrónica - Empleadores
        this._fuseNavigationService.updateNavigationItem('empleadores', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionDnEmpleador, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionDnEmpleadorNuevo, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionDnEmpleadorEditar, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionDnEmpleadorVer, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionDnEmpleadorCambiarEstado, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionDnEmpleadorSubir, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionDnEmpleadorDescargarExcel, false)
            )
        });

        // Configuración - Nómina Electrónica - Trabajadores
        this._fuseNavigationService.updateNavigationItem('trabajadores', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionDnTrabajador, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionDnTrabajadorNuevo, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionDnTrabajadorEditar, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionDnTrabajadorVer, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionDnTrabajadorCambiarEstado, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionDnTrabajadorSubir, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.ConfiguracionDnTrabajadorDescargarExcel, false)
            )
        });
    }

    /**
     * Permite construir los items del menú Emisión y sus opciones conforme a los roles y permisos del usuario.
     *
     * @memberof Auth
     */
    construirMenuEmision() {

        // Módulo Emisión
        this._fuseNavigationService.updateNavigationItem('emision', {
            hidden: !this.validaAccesoEmision()
        });

        this.construirMenuEmisionDocumentosCco();

        // Emisión - Documentos Por Excel
        this._fuseNavigationService.updateNavigationItem('creacion-documentos-por-excel', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.EmisionDocumentosPorExcel) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.EmisionDocumentosPorExcelSubirNotas) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.EmisionDocumentosPorExcelSubirFactura) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.EmisionDocumentosPorExcelDescargarNotas) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.EmisionDocumentosPorExcelDescargarFactura)
            )
        });

        // Emisión - Documentos Sin Envío
        this._fuseNavigationService.updateNavigationItem('documentos-sin-envio', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.EmisionDocumentosSinEnvio) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.EmisionDocumentosSinEnvioEnvio) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.EmisionDocumentosSinEnvioDescargar) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.EmisionDocumentosSinEnvioDescargarJson) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.EmisionDocumentosSinEnvioDescargarExcel) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.EmisionDocumentosSinEnvioDescargarCertificado) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.EmisionAceptacionTacita)
            )
        });

        // Emisión - Documentos Enviados
        this._fuseNavigationService.updateNavigationItem('documentos-enviados', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.EmisionDocumentosEnviados) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.EmisionDocumentosEnviadosDescargar) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.EmisionDocumentosEnviadosEnviarCorreo) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.EmisionDocumentosEnviadosDescargarJson) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.EmisionDocumentosEnviadosDescargarExcel) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.EmisionDocumentosEnviadosDescargarCertificado) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.EmisionAceptacionTacita)
            )
        });

        // Emisión - Documentos Anexos
        this._fuseNavigationService.updateNavigationItem('emision-documentos-anexos', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.EmisionDocumentosAnexos) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.EmisionCargaDocumentosAnexos) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.EmisionEliminarDocumentosAnexos)
            )
        });

        this.construirMenuEmisionReportes();
    }

    /**
     * Permite construir los items del menú Documento Soporte y sus opciones conforme a los roles y permisos del usuario.
     *
     * @memberof Auth
     */
    construirMenuDocumentoSoporte() {
        // Documento Soporte
        this._fuseNavigationService.updateNavigationItem('documento-soporte', {
            hidden: !this.validaAccesoDocumentoSoporte()
        });

        // Documento Soporte - Creación Documentos por Excel
        this._fuseNavigationService.updateNavigationItem('creacion-documentos-por-excel-ds', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.DocumentosSoporteDocumentosPorExcel) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.DocumentosSoporteDocumentosPorExcelDescargar) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.DocumentosSoporteDocumentosPorExcelSubir) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.DocumentosSoporteNotasCreditoPorExcelDescargar) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.DocumentosSoporteNotasCreditoPorExcelSubir)
            )
        });

        // Documento Soporte - Documentos Sin Envío
        this._fuseNavigationService.updateNavigationItem('documentos-sin-envio-ds', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.DocumentosSoporteDocumentosSinEnvio) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.DocumentosSoporteDocumentosSinEnvioDescargar) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.DocumentosSoporteDocumentosSinEnvioDescargarExcel) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.FacturacionWebCrearDocumentoSoporte) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.FacturacionWebEditarDocumentoSoporte) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.FacturacionWebVerDocumentoSoporte)
            )
        });

        // Documento Soporte - Documentos Enviados
        this._fuseNavigationService.updateNavigationItem('documentos-enviados-ds', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.DocumentosSoporteDocumentosEnviados) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.DocumentosSoporteDocumentosEnviadosDescargar) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.DocumentosSoporteDocumentosEnviadosDescargarExcel) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.DocumentosSoporteDocumentosEnviadosEnviarGestionDocumentos)
            )
        });

        this.construirMenuDocumentoSoporteReportes();
    }

    /**
     * Permite construir los items del menú Facturación Web y sus opciones conforme a los roles y permisos del usuario.
     *
     * @memberof Auth
     */
    construirMenuFacturacionWeb() {
        // Módulo Facturación Web
        this._fuseNavigationService.updateNavigationItem('facturacion-web', {
            hidden: !this.validaAccesoFacturacionWeb()
        });

        this.construirMenuFacturacionWebParametros();
        this.construirMenuFacturacionWebCrearDocumento();
    }

    /**
     * Permite construir los items del menú Documentos CCO y sus opciones conforme a los roles y permisos del usuario.
     *
     * @memberof Auth
     */
    construirMenuEmisionDocumentosCco() {
        // Módulo Documentos CCO
        this._fuseNavigationService.updateNavigationItem('documentos-cco', {
            hidden: !this.validaAccesoDocumentosCco()
        });

        this.construirMenuEmisionDocumentosCcoParametros();

        this.construirMenuEmisionDocumentosCcoNuevoDocumento();
    }

    /**
     * Permite construir los items del menú Documentos CCO - Parámetros y sus opciones conforme a los roles y permisos del usuario.
     *
     * @memberof Auth
     */
    construirMenuEmisionDocumentosCcoParametros() {
        // Documentos CCO - Parámetros
        this._fuseNavigationService.updateNavigationItem('documentos-cco-parametros', {
            hidden: !this.validaAccesoDocumentosCcoParametros()
        });

        // Documentos CCO - Parámetros - Datos Comunes
        this._fuseNavigationService.updateNavigationItem('documentos-cco-parametros-datos-comunes', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.FacturacionPickupCashDatosComunesVer, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.FacturacionPickupCashDatosComunesEditar, false)
            )
        });

        // Documentos CCO - Parámetros - Datos Fijos
        this._fuseNavigationService.updateNavigationItem('documentos-cco-parametros-datos-fijos', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.FacturacionPickupCashDatosFijosVer, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.FacturacionPickupCashDatosFijosEditar, false)
            )
        });

        // Documentos CCO - Parámetros - Datos Variables
        this._fuseNavigationService.updateNavigationItem('documentos-cco-parametros-datos-variables', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.FacturacionPickupCashDatosVariablesVer, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.FacturacionPickupCashDatosVariablesEditar, false)
            )
        });

        // Documentos CCO - Parámetros - Extracargos
        this._fuseNavigationService.updateNavigationItem('documentos-cco-parametros-extracargos', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.FacturacionPickupCashExtracargosVer, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.FacturacionPickupCashExtracargosNuevo, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.FacturacionPickupCashExtracargosEditar, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.FacturacionPickupCashExtracargosCambiarEstado, false)
            )
        });

        // Documentos CCO - Parámetros - Productos
        this._fuseNavigationService.updateNavigationItem('documentos-cco-parametros-productos', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.FacturacionPickupCashProductosVer, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.FacturacionPickupCashProductosNuevo, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.FacturacionPickupCashProductosEditar, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.FacturacionPickupCashProductosCambiarEstado, false)
            )
        });
    }

    /**
     * Permite construir los items del menú Facturación Web - Parámetros y sus opciones conforme a los roles y permisos del usuario.
     *
     * @memberof Auth
     */
    construirMenuFacturacionWebParametros() {
        // Facturación Web - Parámetros
        this._fuseNavigationService.updateNavigationItem('facturacion-web-parametros', {
            hidden: !this.validaAccesoFacturacionWebParametros()
        });

        // Facturación Web - Parámetros - Control Consecutivos
        this._fuseNavigationService.updateNavigationItem('facturacion-web-parametros-control-consecutivos', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.FacturacionWebControlConsecutivosNuevo, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.FacturacionWebControlConsecutivosEditar, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.FacturacionWebControlConsecutivosVer, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.FacturacionWebControlConsecutivosCambiarEstado, false)
            )
        });

        // Facturación Web - Parámetros - Cargos
        this._fuseNavigationService.updateNavigationItem('facturacion-web-parametros-cargos', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.FacturacionWebCargosNuevo, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.FacturacionWebCargosEditar, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.FacturacionWebCargosVer, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.FacturacionWebCargosCambiarEstado, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.FacturacionWebCargosSubir, false)
            )
        });

        // Facturación Web - Parámetros - Descuentos
        this._fuseNavigationService.updateNavigationItem('facturacion-web-parametros-descuentos', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.FacturacionWebDescuentosNuevo, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.FacturacionWebDescuentosEditar, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.FacturacionWebDescuentosVer, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.FacturacionWebDescuentosCambiarEstado, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.FacturacionWebDescuentosSubir, false)
            )
        });

        // Facturación Web - Parámetros - Productos
        this._fuseNavigationService.updateNavigationItem('facturacion-web-parametros-productos', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.FacturacionWebProductosNuevo, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.FacturacionWebProductosEditar, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.FacturacionWebProductosVer, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.FacturacionWebProductosCambiarEstado, false) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.FacturacionWebProductosSubir, false)
            )
        });
    }

    /**
     * Permite construir los items del menú Facturación Web - Crear Documento y sus opciones conforme a los roles y permisos del usuario.
     *
     * @memberof Auth
     */
    construirMenuFacturacionWebCrearDocumento() {
        // Facturación Web - Crear Documento
        this._fuseNavigationService.updateNavigationItem('facturacion-web-crear-documento', {
            hidden: !this.validaAccesoFacturacionWebCrearDocumentos()
        });

        // Facturación Web - Crear Documento - Factura
        this._fuseNavigationService.updateNavigationItem('facturacion-web-crear-documento-factura', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.FacturacionWebCrearFactura, false)
            )
        });

        // Facturación Web - Crear Documento - Nota Crédito
        this._fuseNavigationService.updateNavigationItem('facturacion-web-crear-documento-nota-credito', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.FacturacionWebCrearNotaCredito, false)
            )
        });

        // Facturación Web - Crear Documento - Nota Débito
        this._fuseNavigationService.updateNavigationItem('facturacion-web-crear-documento-nota-debito', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.FacturacionWebCrearNotaDebito, false)
            )
        });

        // Facturación Web - Crear Documento - Documento Soporte
        this._fuseNavigationService.updateNavigationItem('facturacion-web-crear-documento-soporte', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.FacturacionWebCrearDocumentoSoporte, false)
            )
        });

        // Facturación Web - Crear Documento - Nota Crédito DS
        this._fuseNavigationService.updateNavigationItem('facturacion-web-crear-documento-ds-nota-credito', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.FacturacionWebCrearNotaCreditoDS, false)
            )
        });
    }

    /**
     * Permite construir los items del menú Documentos CCO - Nuevo Documento y sus opciones conforme a los roles y permisos del usuario.
     *
     * @memberof Auth
     */
    construirMenuEmisionDocumentosCcoNuevoDocumento() {
        // Documentos CCO - Nuevo Documento
        this._fuseNavigationService.updateNavigationItem('documentos-cco-nuevo-documento', {
            hidden: !this.validaAccesoDocumentosCcoNuevoDocumento()
        });

        // Documentos CCO - Nuevo Documento - Factura
        this._fuseNavigationService.updateNavigationItem('documentos-cco-nuevo-documento-factura', {
            hidden: !this.validaAccesoItem(this.permisosRoles.permisos.FacturacionPickupCashNuevoDocumentoFactura, false)
        });
    }

    /**
     * Permite construir los items del menú Reportes y sus opciones conforme a los roles y permisos del usuario.
     *
     * @memberof Auth
     */
    construirMenuEmisionReportes() {
        // Módulo Reportes
        this._fuseNavigationService.updateNavigationItem('emision-reportes', {
            hidden: !this.validaAccesoEmisionReportes()
        });

        // Reportes - Reporte DHL Express
        this._fuseNavigationService.updateNavigationItem('emision-reportes-dhl-express', {
            hidden: !this.validaAccesoItem(this.permisosRoles.permisos.ReportePersonalizadoDhlExpress, false)
        });

        // Reportes - Reporte Documentos Procesados
        this._fuseNavigationService.updateNavigationItem('emision-documentos-procesados', {
            hidden: !this.validaAccesoItem(this.permisosRoles.permisos.EmisionReporteDocumentosProcesados)
        });

        // Reportes - Reporte Notificación Documentos
        this._fuseNavigationService.updateNavigationItem('emision-notificacion-documentos', {
            hidden: !this.validaAccesoItem(this.permisosRoles.permisos.EmisionReporteNotificacionDocumentos)
        });

        // Reportes - Reporte Background
        this._fuseNavigationService.updateNavigationItem('emision-reportes-background', {
            hidden: !this.validaAccesoItem(this.permisosRoles.permisos.EmisionReporteBackground)
        });
    }

    /**
     * Permite construir los items del menú Reportes y sus opciones conforme a los roles y permisos del usuario.
     *
     * @memberof Auth
     */
    construirMenuDocumentoSoporteReportes() {
        // Módulo Reportes
        this._fuseNavigationService.updateNavigationItem('documento-soporte-reportes', {
            hidden: !this.validaAccesoDocumentoSoporteReportes()
        });

        // Reportes - Documentos Procesados
        this._fuseNavigationService.updateNavigationItem('documento-soporte-documentos-procesados', {
            hidden: !this.validaAccesoItem(this.permisosRoles.permisos.DocumentosSoporteReporteDocumentosProcesados)
        });

        // Reportes - Notificación Documentos
        this._fuseNavigationService.updateNavigationItem('documento-soporte-notificacion-documentos', {
            hidden: !this.validaAccesoItem(this.permisosRoles.permisos.DocumentosSoporteReporteNotificacionDocumentos)
        });

        // Reportes - Reportes Background
        this._fuseNavigationService.updateNavigationItem('documento-soporte-reportes-background', {
            hidden: !this.validaAccesoItem(this.permisosRoles.permisos.DocumentosSoporteReporteBackground)
        });
    }

    /**
     * Permite construir los items del menú Recepción y sus opciones conforme a los roles y permisos del usuario.
     *
     * @memberof Auth
     */
    construirMenuRecepcion() {
        let usuario   = this.jwtHelperService.decodeToken();
        let existeFNC = false;
        if(usuario && usuario.ofe_recepcion_fnc && usuario.ofe_recepcion_fnc === 'SI')
            existeFNC = true;

        // Módulo Recepción
        this._fuseNavigationService.updateNavigationItem('recepcion', {
            hidden: !this.validaAccesoRecepcion()
        });

        // Recepción - Documentos Recibidos
        this._fuseNavigationService.updateNavigationItem('documentos-recibidos', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.RecepcionDocumentosRecibidos) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.RecepcionDocumentosRecibidosDescargar) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.RecepcionDocumentosRecibidosAccionesBloque) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.RecepcionDocumentosRecibidosDescargarExcel) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.RecepcionDocumentosRecibidosEnviarGestionDocumentos) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.RecepcionDocumentosRecibidosReenvioNotificacion)
            )
        });

        this.construirMenuRecepcionGestionDocumentos();

        this.construirAutorizaciones();

        // Recepción - Validación Documentos
        this._fuseNavigationService.updateNavigationItem('validacion-documentos', {
            hidden: (!existeFNC ||
                !this.validaAccesoItem(this.permisosRoles.permisos.RecepcionValidacionDocumentos) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.RecepcionValidacionDocumentosDescargar) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.RecepcionValidacionDocumentosAsignar) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.RecepcionValidacionDocumentosLiberar) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.RecepcionValidacionDocumentosValidar) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.RecepcionValidacionDocumentosRechazar) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.RecepcionValidacionDocumentosPagar)
            )
        });

        // Recepción - Correos Recibidos
        this._fuseNavigationService.updateNavigationItem('correos-recibidos', {
            hidden: (!existeFNC || 
                !this.validaAccesoItem(this.permisosRoles.permisos.RecepcionCorreosRecibidos) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.RecepcionCorreosRecibidosVer) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.RecepcionCorreosRecibidosDescargar) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.RecepcionCorreosRecibidosAsociarAnexos) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.RecepcionCorreosRecibidosCrearDocumentoAsociarAnexos)
                ? true : false
            )
        });

        // Recepción - Documentos Manuales
        this._fuseNavigationService.updateNavigationItem('documentos-manuales-recepcion', {
            hidden: !this.validaAccesoItem(this.permisosRoles.permisos.RecepcionDocumentosManuales)
        });

        this.construirMenuRecepcionDocumentosAnexos();

        // Recepción - Documentos No Electrónicos
        this._fuseNavigationService.updateNavigationItem('documentos-no-electronicos', {
            hidden: !this.validaAccesoItem(this.permisosRoles.permisos.RecepcionDocumentoNoElectronicoNuevo)
        });

        // Recepción - Reportes
        this.construirMenuRecepcionReportes();
    }

    /**
     * Permite construir los items del menú de Radian y sus opciones conforme a los roles y permisos del usuario.
     *
     * @memberof Auth
     * @return {void}
     */
    construirMenuRadian(): void {
        // Módulo Radian
        this._fuseNavigationService.updateNavigationItem('radian-registro', {
            hidden: !this.validaAccesoRadian()
        });

        // Radian - Registrar Documentos
        this._fuseNavigationService.updateNavigationItem('radian-registro-documentos-registrar', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.RadianRegistroDocumentos)
            )
        });

        // Radian - Log Errores Documentos
        this._fuseNavigationService.updateNavigationItem('radian-log-errores', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.RadianRegistroDocumentos)
            )
        });

        // Radian - Documentos Radian
        this._fuseNavigationService.updateNavigationItem('radian-documentos', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.RadianDocumentos) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.RadianDocumentosDescargar) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.RadianDocumentosAccionesBloque) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.RadianDocumentosDescargarExcel) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.RadianDocumentosReenvioNotificacion)
            )
        });

        // Radian - Reportes
        this.construirMenuRadianReportes();
    }

    /**
     * Permite construir los items del menú Nómina Electrónica y sus opciones conforme a los roles y permisos del usuario.
     *
     * @memberof Auth
     */
    construirMenuNominaElectronica() {
        // Módulo Nómina Electrónica
        this._fuseNavigationService.updateNavigationItem('nomina-electronica', {
            hidden: !this.validaAccesoNominaElectronica()
        });

        // Nómina Electrónica - Documentos Sin Envío
        this._fuseNavigationService.updateNavigationItem('documentos-sin-envio-dn', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.DnDocumentosSinEnvio) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.DnDocumentosSinEnvioDescargar) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.DnDocumentosSinEnvioDescargarExcel) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.DnDocumentosSinEnvioEnvio)
            )
        });

        // Nómina Electrónica - Documentos Enviados
        this._fuseNavigationService.updateNavigationItem('documentos-enviados-dn', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.DnDocumentosEnviados) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.DnDocumentosEnviadosDescargar) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.DnDocumentosEnviadosDescargarExcel) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.DnDocumentosEnviadosDescargarJson) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.DnDocumentosEnviadosDescargarXml) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.DnDocumentosEnviadosDescargarArEstadoDian)
            )
        });

        // Nómina Electrónica - Documentos Enviados
        this._fuseNavigationService.updateNavigationItem('creacion-documentos-por-excel-dn', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.DnDocumentosPorExcel) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.DnDocumentosPorExcelDescargarNomina) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.DnDocumentosPorExcelDescargarEliminar) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.DnDocumentosPorExcelSubirNomina) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.DnDocumentosPorExcelSubirEliminar)
            )
        });

        // Nómina Electrónica - Reportes
        this.construirMenuNominaReportes();
    }

    /**
     * Permite construir los items del menú Nómina Electrónica - Reportes y sus opciones conforme a los roles y permisos del usuario.
     *
     * @memberof Auth
     */
    construirMenuNominaReportes() {
        // Nómina Electrónica - Reportes
        this._fuseNavigationService.updateNavigationItem('reportes-dn', {
            hidden: !this.validaAccesoNominaReportes()
        });

        // Nómina Electrónica - Reportes - Reporte Background
        this._fuseNavigationService.updateNavigationItem('reportes-dn-background', {
            hidden: !this.validaAccesoItem(this.permisosRoles.permisos.NominaReporteBackground)
        });
    }

    /**
     * Permite construir los items del menú Recepción - Gestión Documentos y sus opciones asociadas.
     *
     * @memberof Auth
     */
    construirMenuRecepcionGestionDocumentos() {
        // Recepción - Gestión Documentos
        this._fuseNavigationService.updateNavigationItem('gestion-documentos', {
            hidden: !this.validaAccesoGestionDocumentos()
        });

        // Recepción - Gestión Documentos - Fe/Doc Soporte Electrónico
        this._fuseNavigationService.updateNavigationItem('fe-doc-soporte-electronico', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.RecepcionGestionDocumentosEtapa1) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.RecepcionGestionDocumentosEtapa1DescargarExcel) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.RecepcionGestionDocumentosEtapa1GestionarFeDs) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.RecepcionGestionDocumentosEtapa1CentroOperaciones) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.RecepcionGestionDocumentosEtapa1SiguienteEtapa)
            )
        });

        // Recepción - Gestión Documentos - Pendiente Revisión
        this._fuseNavigationService.updateNavigationItem('pendiente-revision', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.RecepcionGestionDocumentosEtapa2) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.RecepcionGestionDocumentosEtapa2DescargarExcel) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.RecepcionGestionDocumentosEtapa2GestionarFeDs) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.RecepcionGestionDocumentosEtapa2CentroCosto) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.RecepcionGestionDocumentosEtapa2SiguienteEtapa)
            )
        });

        // Recepción - Gestión Documentos - Pendiente Aprobar Conformidad
        this._fuseNavigationService.updateNavigationItem('pendiente-aprobar-conformidad', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.RecepcionGestionDocumentosEtapa3) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.RecepcionGestionDocumentosEtapa3DescargarExcel) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.RecepcionGestionDocumentosEtapa3GestionarFeDs) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.RecepcionGestionDocumentosEtapa3SiguienteEtapa)
            )
        });

        // Recepción - Gestión Documentos - Pendiente Reconocimiento Contable
        this._fuseNavigationService.updateNavigationItem('pendiente-reconocimiento-contable', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.RecepcionGestionDocumentosEtapa4) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.RecepcionGestionDocumentosEtapa4DescargarExcel) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.RecepcionGestionDocumentosEtapa4GestionarFeDs) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.RecepcionGestionDocumentosEtapa4DatosContabilizado) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.RecepcionGestionDocumentosEtapa4SiguienteEtapa)
            )
        });

        // Recepción - Gestión Documentos - Pendiente Revisión de Impuestos
        this._fuseNavigationService.updateNavigationItem('pendiente-revision-impuestos', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.RecepcionGestionDocumentosEtapa5) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.RecepcionGestionDocumentosEtapa5DescargarExcel) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.RecepcionGestionDocumentosEtapa5GestionarFeDs) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.RecepcionGestionDocumentosEtapa5SiguienteEtapa)
            )
        });

        // Recepción - Gestión Documentos - Pendiente de Pago
        this._fuseNavigationService.updateNavigationItem('pendiente-pago', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.RecepcionGestionDocumentosEtapa6) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.RecepcionGestionDocumentosEtapa6DescargarExcel) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.RecepcionGestionDocumentosEtapa6GestionarFeDs) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.RecepcionGestionDocumentosEtapa6SiguienteEtapa)
            )
        });

        // Recepción - Gestión Documentos - Fe/Doc Soporte Electrónico Gestionado
        this._fuseNavigationService.updateNavigationItem('fe-doc-soporte-electronico-gestionado', {
            hidden: (
                !this.validaAccesoItem(this.permisosRoles.permisos.RecepcionGestionDocumentosEtapa7) &&
                !this.validaAccesoItem(this.permisosRoles.permisos.RecepcionGestionDocumentosEtapa7DescargarExcel)
            )
        });
    }

    /**
     * Permite construir los items del menú Recepción - Autorizaciones y sus opciones asociadas.
     *
     * @memberof Auth
     */
    construirAutorizaciones() {
        // Recepción - Autorizaciones
        this._fuseNavigationService.updateNavigationItem('autorizaciones', {
            hidden: !this.validaAccesoAutorizacionEtapas()
        });

        // Recepción - Autorizaciones - Autorización Etapas
        this._fuseNavigationService.updateNavigationItem('autorizaciones-etapas', {
            hidden: !this.validaAccesoItem(this.permisosRoles.permisos.RecepcionAutorizacionEtapas)
        });
    }

    /**
     * Permite construir los items del menú Recpeción - Documentos anexos y sus opciones conforme a los roles y permisos del usuario.
     *
     * @memberof Auth
     */
    construirMenuRecepcionDocumentosAnexos() {
        // Recepción - Documentos Anexos
        this._fuseNavigationService.updateNavigationItem('documentos-anexos', {
            hidden: !this.validaAccesoItem(this.permisosRoles.permisos.RecepcionDocumentosAnexos)
        });

        // Recepción - Documentos Anexos - Cargar Anexos
        this._fuseNavigationService.updateNavigationItem('documentos-anexos-cargar', {
            hidden: !this.validaAccesoItem(this.permisosRoles.permisos.RecepcionCargaDocumentosAnexos)
        });
    }

    /**
     * Permite construir los items del menú Recpeción - Reportes y sus opciones conforme a los roles y permisos del usuario.
     *
     * @memberof Auth
     */
    construirMenuRecepcionReportes() {
        // Recepción - Reportes
        this._fuseNavigationService.updateNavigationItem('recepcion-reportes', {
            hidden: !this.validaAccesoRecepcionReportes()
        });

        // Reportes - Reporte Documentos Procesados
        this._fuseNavigationService.updateNavigationItem('recepcion-documentos-procesados', {
            hidden: !this.validaAccesoItem(this.permisosRoles.permisos.RecepcionReporteDocumentosProcesados)
        });

        // Reportes - Reporte Documentos Procesados
        this._fuseNavigationService.updateNavigationItem('recepcion-reportes-reporte-gestion-documentos', {
            hidden: !this.validaAccesoItem(this.permisosRoles.permisos.RecepcionReporteGestionDocumentos)
        });

        let usuario   = this.jwtHelperService.decodeToken();
        let existeFNC = false;
        if(usuario && usuario.ofe_recepcion_fnc && usuario.ofe_recepcion_fnc === 'SI')
            existeFNC = true;

        // Recepción - Reportes - Log Validación Documentos
        this._fuseNavigationService.updateNavigationItem('recepcion-reportes-log-validacion-documentos', {
            hidden: (
                !existeFNC ||
                !this.validaAccesoItem(this.permisosRoles.permisos.RecepcionReportesLogValidacionDocumentos)
            )
        });

        // Recepción - Reportes - Reporte Depencencias
        this._fuseNavigationService.updateNavigationItem('recepcion-reportes-reporte-dependencias', {
            hidden: (
                !existeFNC ||
                !this.validaAccesoItem(this.permisosRoles.permisos.RecepcionReporteDependencias)
            )
        });

        // Recepción - Reportes - Reporte Background
        this._fuseNavigationService.updateNavigationItem('recepcion-reportes-background', {
            hidden: !this.validaAccesoItem(this.permisosRoles.permisos.RecepcionReporteBackground)
        });
    }

    /**
     * Permite construir los items del menú Radian - Reportes y sus opciones conforme a los roles y permisos del usuario.
     *
     * @memberof Auth
     * @return {void}
     */
    construirMenuRadianReportes(): void {
        // Radian - Reportes
        this._fuseNavigationService.updateNavigationItem('radian-reportes', {
            hidden: !this.validaAccesoRadianReportes()
        });

        // Radian - Reportes - Reporte Background
        this._fuseNavigationService.updateNavigationItem('radian-reportes-background', {
            hidden: !this.validaAccesoItem(this.permisosRoles.permisos.RadianDocumentosReporteBackground)
        });
    }

    /**
     * Obtiene los permisos y roles del usuario.
     *
     * @memberof Auth
     */
    getAcls(){
        if(localStorage.getItem('acl') === null)
            return {
                roles: [],
                permisos: []
            }
        return JSON.parse(window.atob(localStorage.getItem('acl')));
    }

    private _parseObject(object: any) {
        return Object.keys(object).map(
            k => `${encodeURIComponent(k)}=${encodeURIComponent(object[k])}`
        ).join('&');
    }
}
