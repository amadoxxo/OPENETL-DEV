import { Component } from '@angular/core';
import { JwtHelperService } from '@auth0/angular-jwt';
import { BaseComponent } from 'app/main/core/base_component';
import * as capitalize from 'lodash';

@Component({
    selector: 'app-grupos-trabajo-usuarios-subir',
    templateUrl: './grupos-trabajo-usuarios-subir.component.html',
    styleUrls: ['./grupos-trabajo-usuarios-subir.component.scss']
})
export class GruposTrabajoUsuariosSubirComponent extends BaseComponent{

    public grupo_trabajo_plural : string;

    /**
     * Crea una instancia de GruposTrabajoUsuariosSubirComponent.
     * 
     * @param {JwtHelperService} _jwtHelperService
     * @memberof GruposTrabajoUsuariosSubirComponent
     */
    constructor(
        private _jwtHelperService: JwtHelperService
    ) {
        super();
        let usuario = this._jwtHelperService.decodeToken();
        this.grupo_trabajo_plural = capitalize.startCase(capitalize.toLower(usuario.grupos_trabajo.plural));
    }
}
