import {Component, OnInit} from '@angular/core';
import {ActivatedRoute} from '@angular/router';

@Component({
    selector: 'app-adquirentes-vendedores-gestionar',
    templateUrl: './adquirentes-vendedores-gestionar.component.html',
    styleUrls: ['./adquirentes-vendedores-gestionar.component.scss']
})
export class AdquirentesVendedoresGestionarComponent implements OnInit{
    
    public tipoAdquirente: string = 'vendedor';
    public ver;
    public editar;
    _adq_id: any;
    _adq_identificacion: any;
    
    /**
     * Constructor.
     */
    constructor(private _route: ActivatedRoute) {
    }

    ngOnInit() {
        this._adq_id = this._route.snapshot.params['adq_id'];
        this._adq_identificacion = this._route.snapshot.params['adq_identificacion'];
        this.ver = false;
        this.editar = false;
        if (this._adq_id && !this._adq_identificacion)
            this.editar = true;
        if (this._adq_id && this._adq_identificacion) {
            this.ver = true
        }
    }
}
