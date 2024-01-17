import { BaseComponent } from 'app/main/core/base_component';
import { CommonsService } from 'app/services/commons/commons.service';
import { AbstractControl, FormGroup, FormBuilder, Validators } from '@angular/forms';
import { Component, OnInit, ViewChild, ElementRef, ViewEncapsulation, Input } from '@angular/core';

@Component({
    selector: 'app-filtros-gestion-documentos',
    templateUrl: './filtros-gestion-documentos.component.html',
    styleUrls: ['./filtros-gestion-documentos.component.scss'],
    encapsulation: ViewEncapsulation.None
})
export class FiltrosGestionDocumentosComponent implements OnInit {
    @Input() etapa  ?: number = 0;
    @Input() origen ?: string;
    @Input() parent  : BaseComponent;

    @ViewChild('inputCop') inputCentroOperacion: ElementRef<HTMLInputElement>;
    @ViewChild('inputCco') inputCentroCosto: ElementRef<HTMLInputElement>;

    // Controles del formulario
    public form              : FormGroup;
    public ofe_id            : AbstractControl;
    public gdo_id            : AbstractControl;
    public gdo_fecha_desde   : AbstractControl;
    public gdo_fecha_hasta   : AbstractControl;
    public gdo_consecutivo   : AbstractControl;
    public rfa_prefijo       : AbstractControl;
    public gdo_clasificacion : AbstractControl;
    public estado_gestion    : AbstractControl;
    public centro_operacion  : AbstractControl;
    public centro_costo      : AbstractControl;
    public filtro_etapas     : AbstractControl;

    // Variables del formulario
    public arrFiltroOperacion: number[] = [1, 99];
    public arrFiltroCosto    : number[] = [2, 99];
    public arrOfes           : any[] = [];
    public arrTipoDoc        : any[] = [{ id: 'FC' }, { id: 'DS' }];
    public arrCentroOperacion: any[] = [];
    public arrCentroCosto    : any[] = [];
    public arrEstadoGestion  : any = {
        1 : [
            { id: 'SIN_GESTION', name: 'SIN GESTIÓN', default: true },
            { id: 'CONFORME',    name: 'CONFORME' },
            { id: 'NO_CONFORME', name: 'NO CONFORME' },
            { id: 'RECHAZADO',   name: 'RECHAZADO' },
        ],
        2 : [
            { id: 'SIN_GESTION',          name: 'SIN GESTIÓN', default: true },
            { id: 'REVISION_CONFORME',    name: 'REVISIÓN CONFORME' },
            { id: 'REVISION_NO_CONFORME', name: 'REVISIÓN NO CONFORME' },
            { id: 'RECHAZADO',            name: 'RECHAZADO' },
        ],
        3 : [
            { id: 'SIN_GESTION',             name: 'SIN GESTIÓN', default: true },
            { id: 'APROBACION_CONFORME',     name: 'APROBACION CONFORME' },
            { id: 'APROBACION_NO_CONFORME ', name: 'APROBACION NO CONFORME' },
            { id: 'RECHAZADO',               name: 'RECHAZADO' },
        ],
        4 : [
            { id: 'SIN_GESTION',                  name: 'SIN GESTIÓN', default: true },
            { id: 'APROBADA_POR_CONTABILIDAD',    name: 'APROBADA POR CONTABILIDAD' },
            { id: 'NO_APROBADA_POR_CONTABILIDAD', name: 'NO APROBADA POR CONTABILIDAD' },
            { id: 'RECHAZADO',                    name: 'RECHAZADO' },
        ],
        5 : [
            { id: 'SIN_GESTION',               name: 'SIN GESTIÓN', default: true },
            { id: 'APROBADA_POR_IMPUESTOS',    name: 'APROBADA POR IMPUESTOS' },
            { id: 'NO_APROBADA_POR_IMPUESTOS', name: 'NO APROBADA POR IMPUESTOS' },
            { id: 'RECHAZADO',                 name: 'RECHAZADO' },
        ],
        6 : [
            { id: 'SIN_GESTION',           name: 'SIN GESTIÓN', default: true },
            { id: 'APROBADA_Y_PAGADA',     name: 'APROBADA Y PAGADA' },
            { id: 'NO_APROBADA_PARA_PAGO', name: 'NO APROBADA PARA PAGO' },
        ],
        99 : [
            { id: 'CONFORME',                     name: 'CONFORME'},
            { id: 'NO_CONFORME',                  name: 'NO CONFORME'},
            { id: 'REVISION_CONFORME',            name: 'REVISIÓN CONFORME'},
            { id: 'REVISION_NO_CONFORME',         name: 'NO REVISIÓN CONFORME'},
            { id: 'APROBACION_CONFORME',          name: 'APROBACIÓN CONFORME'},
            { id: 'APROBACION_NO_CONFORME',       name: 'APROBACIÓN NO CONFORME'},
            { id: 'APROBADA_POR_CONTABILIDAD',    name: 'APROBADA POR CONTABILIDAD'},
            { id: 'NO_APROBADA_POR_CONTABILIDAD', name: 'NO APROBADA POR CONTABILIDAD'},
            { id: 'APROBADA_POR_IMPUESTOS',       name: 'APROBADA POR IMPUESTOS'},
            { id: 'NO_APROBADA_POR_IMPUESTOS',    name: 'NO APROBADA POR IMPUESTOS'},
            { id: 'APROBADA_Y_PAGADA',            name: 'APROBADA Y PAGADA'},
            { id: 'NO_APROBADA_PARA_PAGO',        name: 'NO APROBADA PARA PAGO'},
            { id: 'GESTIONADO',                   name: 'FE/DOC SOPORTE ELECTRONICO GESTIONADO'},
            { id: 'SIN_GESTION',                  name: 'SIN GESTIÓN'},
            { id: 'RECHAZADA',                    name: 'RECHAZADA'},
        ]
    };
    public arrFiltroEtapas: any[] = [
        { id: '1', name: 'FE/DOC SOPORTE ELECTRÓNICO'},
        { id: '2', name: 'PENDIENTE REVISIÓN'},
        { id: '3', name: 'PENDIENTE APROBAR CONFORMIDAD'},
        { id: '4', name: 'PENDIENTE RECONOCIMIENTO CONTABLE'},
        { id: '5', name: 'PENDIENTE REVISIÓN DE IMPUESTOS'},
        { id: '6', name: 'PENDIENTE DE PAGO'},
        { id: '7', name: 'FE/DOC SOPORTE ELECTRONICO GESTIONADO'},
    ];

    /**
     * Constructor del componente.
     * 
     * @param {FormBuilder} _formBuilder
     * @param {CommonsService} _commonsService
     * @memberof FiltrosGestionDocumentosComponent
     */
    constructor(
        private _formBuilder: FormBuilder,
        private _commonsService: CommonsService,
    ) {}

    /**
     * Inicializa las variables del componente.
     *
     * @private
     * @memberof FiltrosGestionDocumentosComponent
     */
    private init(): void {
        this.form = this._formBuilder.group({
            ofe_id             : ['', Validators.compose([Validators.required])],
            gdo_id             : [''],
            gdo_fecha_desde    : ['', Validators.compose([Validators.required])],
            gdo_fecha_hasta    : ['', Validators.compose([Validators.required])],
            gdo_consecutivo    : [''],
            rfa_prefijo        : [''],
            gdo_clasificacion  : [''],
            estado_gestion     : [''],
            centro_operacion   : [''],
            centro_costo       : [''],
            filtro_etapas      : [''],
        });

        this.ofe_id             = this.form.controls['ofe_id'];
        this.gdo_id             = this.form.controls['gdo_id'];
        this.gdo_fecha_desde    = this.form.controls['gdo_fecha_desde'];
        this.gdo_fecha_hasta    = this.form.controls['gdo_fecha_hasta'];
        this.gdo_consecutivo    = this.form.controls['gdo_consecutivo'];
        this.rfa_prefijo        = this.form.controls['rfa_prefijo'];
        this.gdo_clasificacion  = this.form.controls['gdo_clasificacion'];
        this.estado_gestion     = this.form.controls['estado_gestion'];
        this.centro_operacion   = this.form.controls['centro_operacion'];
        this.centro_costo       = this.form.controls['centro_costo'];
        this.filtro_etapas      = this.form.controls['filtro_etapas'];

        this.gdo_id.disable();

        if(this.origen === 'reportes') {
            this.etapa = 99;
        }
    }

    /**
     * Ciclo OnInit del componente.
     *
     * @memberof FiltrosGestionDocumentosComponent
     */
    ngOnInit(): void {
        this.init();
        this.cargaDataComponent();
        this.selectEstadosDefault();
    }

    /**
     * Realiza una cadena de consultas para cargar la información en los combos.
     *
     * @private
     * @memberof FiltrosGestionDocumentosComponent
     */
    private async cargaDataComponent(): Promise<void> {
        this.parent.loading(true);

        // Consulta los oferentes, centros de costo y centros de operación
        await this.cargarDataFiltros().then((data: any) => {
            this.arrOfes = [];
            const { ofes, centros_costo, centros_operacion } = data;
            if(ofes.length === 1) {
                const [ oferente ] = ofes;
                this.ofe_id.setValue(oferente.ofe_id);
                this.gdo_id.enable();
            }

            ofes.forEach(ofe => {
                if(ofe.ofe_recepcion === 'SI') {
                    ofe.ofe_identificacion_ofe_razon_social = ofe.ofe_identificacion + ' - ' + ofe.ofe_razon_social;
                    this.arrOfes.push(ofe);
                }
            });

            // Carga el combo Centro de Operación
            if(this.origen === 'reportes' || this.arrFiltroOperacion.includes(this.etapa!)) {
                this.centro_operacion.setValue('', {emitEvent: false});
                this.arrCentroOperacion.push({ id: 'NA', name: 'Sin Asignar Centro Operación'});

                if(centros_operacion && centros_operacion.length > 0) {
                    centros_operacion.forEach(registro => {
                        let { cop_id, cop_descripcion } = registro;
                        this.arrCentroOperacion.push({
                            id   : cop_id,
                            name : cop_descripcion
                        });
                    });
                }
                this.arrCentroOperacion = [...this.arrCentroOperacion];
            }

            // Carga el combo Centro de Costo
            if(this.origen === 'reportes' || this.arrFiltroCosto.includes(this.etapa!)) {
                this.centro_costo.setValue('', {emitEvent: false});
                this.arrCentroCosto.push({ id: 'NA', name: 'Sin Asignar Centro Costo'});

                if(centros_costo && centros_costo.length > 0) {
                    centros_costo.forEach(registro => {
                        let { cco_id, cco_codigo, cco_descripcion } = registro;
                        this.arrCentroCosto.push({
                            id   : cco_id,
                            name : `${ cco_codigo } - ${ cco_descripcion }`
                        });
                    });
                }
                this.arrCentroCosto = [...this.arrCentroCosto];
            }
        }).catch( (error) => {
            this.parent.showError(error, 'error', 'Error al cargar la información de los filtros', 'Ok', 'btn btn-danger');
        });
        this.parent.loading(false);
    }

    /**
     * Realiza la petición para obtener los OFEs asociados.
     *
     * @private
     * @return {Promise<any>}
     * @memberof FiltrosGestionDocumentosComponent
     */
    private cargarDataFiltros(): Promise<any> {
        return new Promise((resolve, reject) => {
            this._commonsService.getDataInitForBuild('tat=false').subscribe({
                next: ({ data }) => {
                    resolve(data);
                },
                error: (error) => {
                    reject(this.parent.parseError(error));
                }
            });
        });
    }

    /**
     * Asigna los valores por defecto en el combo estados de gestión.
     *
     * @private
     * @memberof FiltrosGestionDocumentosComponent
     */
    private selectEstadosDefault(): void {
        const opciones: string[] = [];
        this.arrEstadoGestion[this.etapa!]?.forEach( estado => { if(estado.default) opciones.push(estado.id) });
        this.estado_gestion.patchValue([...opciones]);
    }

    /**
     * Evento que se ejecuta cuando se selecciona un nuevo oferente.
     *
     * @param {*} ofe
     * @memberof FiltrosGestionDocumentosComponent
     */
    public ofeHasChanged(ofe): void {}

    /**
     * Selecciona o Deselecciona todas las opciones.
     *
     * @param {boolean} [todos=true] Selecionar todos
     * @param {string} combo Indica el combo de donde hace el llamado
     * @memberof OperacionesImpoComponent
     */
    public multiSelect(todos: boolean = true, combo: string): void {
        if(todos) {
            if(combo === 'estado_gestion') {
                this.estado_gestion.patchValue([...this.arrEstadoGestion[this.etapa!].map((item: any) => item.id)]);
            } else {
                this.filtro_etapas.patchValue([...this.arrFiltroEtapas.map((item: any) => item.id)]);
            }
        } else {
            if(combo === 'estado_gestion') {
                this.estado_gestion.setValue([]);
            } else {
                this.filtro_etapas.setValue([]);
            }
        }
    }

    /**
     * Realiza el filtrado de los registros del combo según el texto predictivo.
     *
     * @param {string} texto Texto predictivo
     * @param {string} combo Indica el combo de origen
     * @return {any[]}
     * @memberof OperacionesImpoComponent
     */
    public filtrarCombos(texto: string, combo: string): any[] {
        if(combo === 'centro_operacion') {
            return this.arrCentroOperacion.filter(cop => cop.name.toLowerCase().includes(texto.toLowerCase()));
        } else {
            return this.arrCentroCosto.filter(cop => cop.name.toLowerCase().includes(texto.toLowerCase()));
        }
    }

    /**
     * Acción a realizar cuando se selecciona una opción del combo.
     *
     * @param {string} combo Indica el combo de origen
     * @memberof FiltrosGestionDocumentosComponent
     */
    public setComboValue(combo: string): void {
        if(combo === 'centro_operacion') {
            this.inputCentroOperacion.nativeElement.value = '';
            this.inputCentroOperacion.nativeElement.focus();
        } else {
            this.inputCentroCosto.nativeElement.value = '';
            this.inputCentroCosto.nativeElement.focus();
        }
    }

    /**
     * Limpia la opción seleccionada en la lista del combo.
     *
     * @param {string} combo Indica el combo de origen
     * @memberof FiltrosGestionDocumentosComponent
     */
    public clearComboValue(combo: string): void {
        if(combo === 'centro_operacion') {
            this.centro_operacion.setValue('');
        } else if (combo === 'gdo_clasificacion'){
            this.gdo_clasificacion.setValue('');
        } else {
            this.centro_costo.setValue('');
        }
    }

    /**
     * Maneja el evento de la tecla espacio para evitar auto seleccionar la opción.
     *
     * @param {KeyboardEvent} event Evento ejecutado
     * @param {string} combo Indica el combo de origen
     * @memberof FiltrosGestionDocumentosComponent
     */
    public keyDownEvent(event: KeyboardEvent, combo: string): void {
        // Si la tecla presionada es el espacio, prevenimos la acción por defecto
        if (event.key === ' ' || event.code === 'Space') {
            event.preventDefault();
            if(combo === 'centro_operacion') {
                this.inputCentroOperacion.nativeElement.value += ' ';
                this.filtrarCombos(this.inputCentroOperacion.nativeElement.value, combo);
            } else {
                this.inputCentroCosto.nativeElement.value += ' ';
                this.filtrarCombos(this.inputCentroCosto.nativeElement.value, combo);
            }
        }
    }
}
