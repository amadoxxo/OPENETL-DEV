import {Component} from '@angular/core';
import {PERMISOSROLES} from '../../../../../acl/permisos_roles';

@Component({
    selector: 'app-adquirentes-vendedores-listar',
    templateUrl: './adquirentes-vendedores-listar.component.html',
    styleUrls: ['./adquirentes-vendedores-listar.component.scss']
})
export class AdquirentesVendedoresListarComponent {

    public tipoAdquirente = 'vendedor';
    public permisosRoles = PERMISOSROLES;
}
