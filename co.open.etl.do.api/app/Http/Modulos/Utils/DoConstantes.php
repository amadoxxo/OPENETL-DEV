<?php
namespace App\Http\Modulos\Utils;

/**
 * Constantes de uso general para el API de DO
 *
 * Class DoConstantes
 * @package App\Http\Modulos
 */
class DoConstantes {

    /*
     * Oferentes especiales que disponen de algun tipo de personalización o tratamiento especial
     */
    public const NIT_DHLGLOBAL            = '860030380';
    public const NIT_DHLADUANAS           = '830002397';
    public const NIT_SIACO                = '800251957';
    public const NIT_REPREMUNDO           = '860079024';
    public const NIT_RIS                  = '900698414';
    public const NIT_LOGISTICA_REPREMUNDO = '830104929';
    public const NIT_MAP_CARGO            = '830004237';
    public const NIT_FEDERACION_NACIONAL  = '830004237';

    public const EMPRESAS_DHL_DO = [
        self::NIT_DHLADUANAS, self::NIT_DHLGLOBAL
    ];

    /*
     * Estados en que puede estar un documento Electronico.
     */
    public const ESTADO_EDI          = 'EDI'; // Aunque este estado no se esta registrando, pero queda a modo de referencia
    public const ESTADO_UBL          = 'UBL';
    public const ESTADO_DO           = 'DO';
    public const ESTADO_NOTIFICACION = 'NOTIFICACION';
    public const FINALIZADO          = 'FINALIZADO';

    /**
     * Compañias para DHL.
     *
     * @var array
     */
    public const DHL_COMPANIAS = [
        self::NIT_DHLGLOBAL  => 'CO',
        self::NIT_DHLADUANAS => 'SI'
    ];

    /**
     * Tipos para DHL
     *
     * @var array
     */
    public const DHL_TIPOS = [
        'FC' => 'IN',
        'NC' => 'CR',
        'ND' => ''/** NO DEFINIDO A LA FECHA */
    ];

}