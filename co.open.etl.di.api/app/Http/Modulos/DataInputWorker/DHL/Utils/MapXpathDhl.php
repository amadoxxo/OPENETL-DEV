<?php


namespace App\Http\Modulos\DataInputWorker\DHL\Utils;

/**
 * Contiene el mapeo de XPATH de los archivos XML de DHL Agencia, GlobalForwarding, ZonaFranca
 * Class MapXpathDhl
 * @package App\Http\Modulos\DataInputWorker\DHL\Utils
 */
class MapXpathDhl
{
    /**
     * @var array
     */
    public static $xpathArrayCabeceraFC = [
        'cdo_consecutivo'                  => '//identificaciondeldocumento/numerodocumento',
        'cdo_hora'                         => '//identificaciondeldocumento/horafactura',
        'cdo_fecha'                        => '//identificaciondeldocumento/fechafactura',
        'cdo_vencimiento'                  => '//identificaciondeldocumento/vencimiento',
        'rfa_prefijo'                      => '//identificaciondeldocumento/prefijo',
        'top_codigo'                       => '//identificaciondeldocumento/tipoOperacion',
        'men_identificador_pago_id'        => '//identificaciondeldocumento/identificadorPago',
        'tde_codigo'                       => '//identificaciondeldocumento/tipo',
        'rfa_resolucion'                   => '//cae//numeroresolucion',
        'ofe_identificacion'               => '//emisor//nrodocemisor',
        'mpa_codigo'                       => '//receptor/formadepago',
        'fpa_codigo'                       => '//receptor/condiciondepago',
        'tipo_cambio'                      => '//identificaciondeldocumento/tipocambio',
        'ant_identificacion'               => '//identificaciondeldocumento/identificadorAnticipo',
        'ant_fecha_recibido'               => '//identificaciondeldocumento/fecharecepcionPagoAnticipo',
        'ant_valor_moneda_extranjera'      => '//montos/monedaextranjera/valoranticipoDIAN',
        'ant_valor'                        => '//montos/monedanacional/valoranticipoDIANmontobase',
        'mon_codigo'                       => '//identificaciondeldocumento/moneda',
        'nit_receptor'                     => '//receptor/nitreceptor',
        'trm'                              => '//identificaciondeldocumento/tipocambio',
        'cdo_redondeo'                     => '//montos/monedanacional/redondeomontobase',
        'cdo_redondeo_moneda_extranjera'   => '//montos/monedaextranjera/redondeo',
    ];

    /**
     * @var array
     */
    public static $xpathArrayCabeceraNCND = [
        'cdo_consecutivo'                  => '//identificaciondeldocumento/numerodocumento',
        'cdo_hora'                         => '//identificaciondeldocumento/horanotacredito',
        'cdo_fecha'                        => '//identificaciondeldocumento/fechanotacredito',
        'cdo_vencimiento'                  => '//identificaciondeldocumento/vencimiento',
        'rfa_prefijo'                      => '//identificaciondeldocumento/prefijo',
        'top_codigo'                       => '//identificaciondeldocumento/tipoOperacion',
        'men_identificador_pago_id'        => '//identificaciondeldocumento/identificadorPago',
        'tde_codigo'                       => '//identificaciondeldocumento/tipo',
        'ofe_identificacion'               => '//emisor/nrodocemisor',
        'mpa_codigo'                       => '//receptor/formadepago',
        'fpa_codigo'                       => '//receptor/condiciondepago',
        'clasificacion'                    => '//referencia/tipodocref',
        'prefijo'                          => '//referencia/serieref',
        'consecutivo_factura'              => '//referencia/numeroref',
        'fecha_emision'                    => '//referencia/fecharef',
        'cco_codigo'                       => '//referencia/codref',
        'cdo_observacion_correccion'       => '//referencia/razonref',
        'cufe'                             => '//referencia//ECB01',
        'ant_identificacion'               => '//identificaciondeldocumento/identificadorAnticipo',
        'ant_fecha_recibido'               => '//identificaciondeldocumento/fecharecepcionPagoAnticipo',
        'ant_valor_moneda_extranjera'      => '//montos/monedaextranjera/valoranticipoDIAN',
        'ant_valor'                        => '//montos/monedanacional/valoranticipoDIANmontobase',
        'mon_codigo'                       => '//identificaciondeldocumento/moneda',
        'nit_receptor'                     => '//receptor/nitreceptor',
        'trm'                              => '//identificaciondeldocumento/tipocambio',
        'cdo_redondeo'                     => '//montos/monedanacional/redondeomontobase',
        'cdo_redondeo_moneda_extranjera'   => '//montos/monedaextranjera/redondeo',
    ];

    /**
     * @var array
     */
    public static $xpathArrayInformacionAdicional = [
        'tipo_cambio'                         => '//identificaciondeldocumento//tipocambio',
        'valor_moneda_org'                    => '//identificaciondeldocumento//vlrmdaorig',
        'valorfob'                            => '//identificaciondeldocumento//valorfob',
        'pedido_orden_de_compra'              => '//receptor//pedidoordendecompra',
        'condicion_pago'                      => '//receptor//condiciondepago',
        'file'                                => '//identificaciondeldocumento//file',
        'fecha'                               => '//identificaciondeldocumento//fechafactura',
        'vencimiento'                         => '//identificaciondeldocumento//vencimiento',
        'guia_master'                         => '//identificaciondeldocumento//guiamaster',
        'aerol_naviera'                       => '//identificaciondeldocumento//aerolnaviera',
        'aduanas'                             => '//identificaciondeldocumento//aduanas',
        'ciudad_prestacion_servicio'          => '//emisor//sucursalprestacionservicio',
        'codigo_receptor'                     => '//receptor//codigoreceptor',
        'nombre_receptor'                     => '//receptor//nombrereceptor',
        'nombre_comercial'                    => '//receptor//nombrecomercial',
        'adq_identificacion'                  => '//receptor//nitreceptor',
        'nit_receptor'                        => '//receptor//nitreceptor',
        'direccion_receptor'                  => '//receptor//direccionreceptor',
        'telefono_receptor'                   => '//receptor//telefonoreceptor',
        'ciudad_receptor'                     => '//receptor//ciudadreceptor',
        'departamento_receptor'               => '//receptor//departamentoreceptor',
        'pais_receptor'                       => '//receptor//paisreceptor',
        'guia_hija'                           => '//receptor//guiahija',
        'procedencia'                         => '//receptor//procedencia',
        'pais_origen'                         => '//receptor//paisorigen',
        'pais_destino'                        => '//receptor//paisdestino',
        'proveedor'                           => '//receptor//proveedor',
        'piezas'                              => '//receptor//piezas',
        'peso_volumen'                        => '//receptor//pesovolumen',
        'cssv'                                => '//receptor//cssvbogexl',
        'primer_nombre_receptor'              => '//receptor//primernombre',
        'correos_receptor'                    => '//receptor//correoelectronico',
        'direccion_domicilio_fiscal_receptor' => '//receptor//domiciliofiscal',
        'motivo'                              => '//referencia//razonref',
        'descripcion'                         => '//receptor//descripcion',
        'valor_letras_cop'                    => '//montos//valor_letras',
    ];

    /**
     * @var array
     */
    public static $xpathCamposItems = [
        'cpr_codigo'                           => 'codigoclasificacion',
        'ddo_codigo'                           => 'codigoestandar',
        'descripcion_uno'                      => 'descripcionestandar',
        'descripcion_dos'                      => 'descripcion',
        //'descripcion_tres'                     => 'descripcionestandar',
        'und_codigo'                           => 'unmitem',
        'ddo_cantidad'                         => 'qtyitem',
        'ddo_valor_unitario'                   => 'montocop',
        'ddo_total'                            => 'montobase',
        'ddo_valor_unitario_moneda_extranjera' => 'montousd',
        'ddo_total_moneda_extranjera'          => 'montousd',
        'department'                           => 'department'
    ];

    /**
     * @var array
     */
    public static $xpathImpuestosItems = [
        'tri_codigo'     => 'codigoTributo',
        'iid_base'       => 'baseImponiblecop',
        'iid_valor'      => 'valorTributocop',
        'iid_porcentaje' => 'tarifaTributo',
    ];

    /**
     * @var array
     */
    public static $xpathImpuestosCabecera = [
        'tri_codigo'     => 'tipoimpuesto',
        'iid_base'       => 'montodelimpuesto',
        'iid_valor'      => 'montobasedelimpuesto',
        'iid_porcentaje' => 'tasadeimpuestos',
    ];

    /**
     * @var array
     */
    public static $xpathRetenciones = [
        'tri_codigo'     => 'codigoautoretencion',
        'iid_base'       => 'baseimponibleautoretencion',
        'iid_valor'      => 'valorautoretencion',
        'iid_porcentaje' => 'tarifaautoretencion',
    ];

    /**
     * @var array
     */
    public static $camposItemsInformacionAdicional = [
        'descripcion' => 'descripcion',
        'codigo' => 'codigo',
        'monedacotizacion' => 'monedacotizacion',
        'tasacambiocotizacion' => 'tasacambiocotizacion',
        'montocotizacion' => 'montocotizacion'
    ];

    /**
     * @var array
     */
    public static $camposItemsPropiedadesAdicionales = [
        'monedacotizacion' => 'monedacotizacion',
        'tasacambiocotizacion' => 'tasacambiocotizacion',
        'montocotizacion' => 'montocotizacion'
    ];

    /**
     * @var array
     */
    public static $xpathCamposObservaciones = [
        'observacion1'          => '//observaciones//observacioneslinea01',
        'observacion2'          => '//observaciones//observacioneslinea02',
        'observacion3'          => '//observaciones//observacioneslinea03',
        'observacion4'          => '//observaciones//observacioneslinea04',
        'observacion5'          => '//observaciones//observacioneslinea05',
        'observacion6'          => '//observaciones//observacioneslinea06',
        'observacion7'          => '//observaciones//observacioneslinea07',
        'observacion8'          => '//observaciones//observacioneslinea08',
        'observacion9'          => '//observaciones//observacioneslinea09',
        'observacion10'         => '//observaciones//observacioneslinea10',
        'observacion11'         => '//observaciones//observacioneslinea11',
        'observacion12'         => '//observaciones//observacioneslinea12',
        'observacion13'         => '//observaciones//observacioneslinea13',
        'observacion14'         => '//observaciones//observacioneslinea14',
        'observacion15'         => '//observaciones//observacioneslinea15',
        'observacion16'         => '//observaciones//observaciones'
    ];

    /**
     * @var array
     */
    public static $camposMontosEXT = [
        'moneda'                              => '//montos//monedaextranjera//moneda',
        'base_iva_moneda_extranjera'          => '//montos//monedaextranjera//baseiva',
        'iva'                                 => '//montos//monedaextranjera//iva',
        'valor_no_gravado'                    => '//montos//monedaextranjera//valornogravado',
        'subtotal_pcc_moneda_extranjera'      => '//montos//monedaextranjera//subtotalingresosterceros',
        'subtotal_ip_moneda_extranjera'       => '//montos//monedaextranjera//subtotalingresospropios',
        'total'                               => '//montos//monedaextranjera//total',
        'cdo_valor_a_pagar_moneda_extranjera' => '//montos//monedaextranjera//valorapagar',
        'valor_anticipo_moneda_extranjera'    => '//montos//monedaextranjera//valoranticipo',
        'cdo_anticipo_moneda_extranjera'      => '//montos//monedaextranjera//valoranticipoDIAN',
    ];

    /**
     * @var array
     */
    public static $camposMontosCOP = [
        'moneda'           => '//montos//monedanacional//moneda',
        'baseiva'          => '//montos//monedanacional//baseivamontobase',
        'iva_monto_base'   => '//montos//monedanacional//ivamontobase',
        'valor_no_grabado' => '//montos//monedanacional//valornograbadomontobase',
        'subtotal_pcc'     => '//montos//monedanacional//subtotalingresosterceros',
        'subtotal_ip'      => '//montos//monedanacional//subtotalingresospropios',
        'total_monto_base' => '//montos//monedanacional//totalmontobase',
        'valor_anticipo'   => '//montos//monedanacional//valoranticipomontobase',
        'cdo_anticipo'     => '//montos//monedanacional//valoranticipoDIANmontobase',
        'saldo_anticipo'   => '//montos//monedanacional//saldoanticipo',
    ];

}
