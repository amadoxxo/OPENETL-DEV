import {Component} from '@angular/core';
import {PERMISOSROLES} from '../../../../../acl/permisos_roles';

@Component({
    selector: 'app-adquirentes-adquirentes-listar',
    templateUrl: './adquirentes-adquirentes-listar.component.html',
    styleUrls: ['./adquirentes-adquirentes-listar.component.scss']
})
export class AdquirentesAdquirentesListarComponent {

    public tipoAdquirente = 'adquirente';
    public permisosRoles = PERMISOSROLES;
}
