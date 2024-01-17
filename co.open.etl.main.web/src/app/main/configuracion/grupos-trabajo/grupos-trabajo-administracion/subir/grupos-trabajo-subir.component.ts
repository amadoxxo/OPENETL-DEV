import {Component} from '@angular/core';
import {BaseComponent} from 'app/main/core/base_component';
import { JwtHelperService } from '@auth0/angular-jwt';

@Component({
    selector: 'app-grupos-trabajo-subir',
    templateUrl: './grupos-trabajo-subir.component.html',
    styleUrls: ['./grupos-trabajo-subir.component.scss']
})
export class GruposTrabajoSubirComponent extends BaseComponent{

    public titleGrupoTrabajo    : string = '';

    /**
     * Crea una instancia de GruposTrabajoSubirComponent.
     * 
     * @param {JwtHelperService} jwtHelperService
     * @memberof GruposTrabajoSubirComponent
     */
    constructor(private jwtHelperService: JwtHelperService){
        super();
        let usuario = this.jwtHelperService.decodeToken();
        this.titleGrupoTrabajo = usuario.grupos_trabajo.plural;
    }
}
