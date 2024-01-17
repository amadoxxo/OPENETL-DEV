import { Component } from '@angular/core';
import { JwtHelperService } from '@auth0/angular-jwt';
import { BaseComponent } from 'app/main/core/base_component';
import * as capitalize from 'lodash';

@Component({
    selector: 'app-grupos-trabajo-proveedores-subir',
    templateUrl: './grupos-trabajo-proveedores-subir.component.html',
    styleUrls: ['./grupos-trabajo-proveedores-subir.component.scss']
})
export class GruposTrabajoProveedoresSubirComponent extends BaseComponent{

    public grupo_trabajo_plural : string;

    /**
     * Crea una instancia de GruposTrabajoProveedoresSubirComponent.
     * 
     * @param {JwtHelperService} _jwtHelperService
     * @memberof GruposTrabajoProveedoresSubirComponent
     */
    constructor(
        private _jwtHelperService: JwtHelperService
    ) {
        super();
        let usuario = this._jwtHelperService.decodeToken();
        this.grupo_trabajo_plural = capitalize.startCase(capitalize.toLower(usuario.grupos_trabajo.plural));
    }
}
