import { Component } from '@angular/core';
import { JwtHelperService } from '@auth0/angular-jwt';

@Component({
    selector: 'app-alerta-sagrilaft',
    templateUrl: './alerta-sagrilaft.component.html'
})
export class AlertaSagrilaftComponent {

    public usuario  : any;
    public sagrilaftMensaje;
    public sagrilaftActivarMensaje;

    /**
     * Crea una instancia de AlertaSagrilaftComponent.
     * 
     * @param {JwtHelperService} jwtHelperService
     * @memberof AlertaSagrilaftComponent
     */
    constructor(
        private jwtHelperService:JwtHelperService
    ) {
        this.usuario                 = this.jwtHelperService.decodeToken();
        this.sagrilaftMensaje        = this.usuario.sagrilaft_mensaje;
        this.sagrilaftActivarMensaje = this.usuario.sagrilaft_activar_mensaje;
    }
}