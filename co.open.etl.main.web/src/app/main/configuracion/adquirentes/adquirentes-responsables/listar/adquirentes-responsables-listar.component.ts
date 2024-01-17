import {Component} from '@angular/core';
import {PERMISOSROLES} from '../../../../../acl/permisos_roles';

@Component({
    selector: 'app-adquirentes-responsables-listar',
    templateUrl: './adquirentes-responsables-listar.component.html',
    styleUrls: ['./adquirentes-responsables-listar.component.scss']
})
export class AdquirentesResponsablesListarComponent {

    public tipoAdquirente = 'responsable';
    public permisosRoles = PERMISOSROLES;
}
