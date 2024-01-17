import {Component} from '@angular/core';
import {PERMISOSROLES} from '../../../../../acl/permisos_roles';

@Component({
    selector: 'app-adquirentes-autorizados-listar',
    templateUrl: './adquirentes-autorizados-listar.component.html',
    styleUrls: ['./adquirentes-autorizados-listar.component.scss']
})
export class AdquirentesAutorizadosListarComponent {

    public tipoAdquirente = 'autorizado';
    public permisosRoles = PERMISOSROLES;
}
