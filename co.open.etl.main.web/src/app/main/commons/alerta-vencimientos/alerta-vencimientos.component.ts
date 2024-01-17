import { Component } from '@angular/core';
import { BaseComponentList } from 'app/main/core/base_component_list';
import { CertificadosService } from '../../../services/certificados/certificados.service';
import { ConfiguracionService } from '../../../services/configuracion/configuracion.service';

@Component({
    selector: 'app-alerta-vencimientos',
    templateUrl: './alerta-vencimientos.component.html',
    styleUrls: ['./alerta-vencimientos.component.scss']
})
export class AlertaVencimientosComponent extends BaseComponentList {

    public arrListaVencimientos        : any;
    public arrListaDiasVencidos        : any;
    public arrListaConsecutivoVencidos : any;

    /**
     * Crea una instancia de AlertaVencimientosComponent.
     * 
     * @memberof AlertaVencimientosComponent
     */
    constructor(
        private _configuracion : ConfiguracionService,
        private _certificadosService : CertificadosService
    ) {
        super();
        this.loadCertificadosVencimiento();
    }

    /**
     * Consulta la lista de certificados de vencimiento del usuario autenticado.
     *
     * @memberof AlertaVencimientosComponent
     */
    async loadCertificadosVencimiento() {
        this.loading(true);
        let vencimientoCertificados = await this._certificadosService.consultaVencimientoCertificados().toPromise().catch(error => {
            let texto_errores = this.parseError(error);
            this.loading(false);
            this.showError(texto_errores, 'error', 'Error al consultar los certificados vencidos', 'Ok', 'btn btn-danger');
        });

        this.loading(true);
        if(vencimientoCertificados !== undefined) {
            this.arrListaVencimientos = vencimientoCertificados['vencimientos'];
        }

        this.loadVencimientoResoluciones();
    }

    /**
     * Consulta la lista de las resoluciones próximas a vencer para el usuario autenticado.
     *
     * @memberof AlertaVencimientosComponent
     */
    async loadVencimientoResoluciones() {
        this.loading(true);
        let vencimientoResoluciones = await this._configuracion.consultaVencimientoResoluciones().toPromise().catch(error => {
            let texto_errores = this.parseError(error);
            this.loading(false);
            this.showError(texto_errores, 'error', 'Error al consultar el control de resoluciones y vigencia de resoluciones', 'Ok', 'btn btn-danger');
        });

        this.loading(false);
        if(vencimientoResoluciones !== undefined) {
            this.arrListaDiasVencidos        = vencimientoResoluciones['vencimientos']['diasVencidos'];
            this.arrListaConsecutivoVencidos = vencimientoResoluciones['vencimientos']['consecutivosVencidos'];
        }
    }
}
