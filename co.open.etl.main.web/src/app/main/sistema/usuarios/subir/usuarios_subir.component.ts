import {Component} from '@angular/core';
import {BaseComponent} from 'app/main/core/base_component';

@Component({
    selector: 'subir-usuarios',
    templateUrl: './usuarios_subir.component.html',
    styleUrls: ['./usuarios_subir.component.scss']
})
export class UsuariosSubirComponent extends BaseComponent{

    /**
     * Constructor
     * @param fb
     * @param route
     * @param router
     * @param _tiposNotificacionService
     * @param _logErroresService
     * @param _usuariosService
     */
    constructor(
    ) {
        super();
    }
}
