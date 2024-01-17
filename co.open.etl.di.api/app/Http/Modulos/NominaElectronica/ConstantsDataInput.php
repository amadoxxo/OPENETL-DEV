<?php
namespace App\Http\Modulos\NominaElectronica;

class ConstantsDataInput {
    public const codigosValidos = ['102', '103'];

    public const ORIGEN_API    = 'API';
    public const ORIGEN_MANUAL = 'MANUAL';

    public const ROOT = 'documentos';
    public const DN   = 'DN';

    public const NOT_DOCUMENT_ROOT   = 'No existe la llave documentos';
    public const NOT_TYPE_ALLOWED_DN = 'No existe una clave DN';
    public const DOCS_ARE_NOT_ARRAY  = 'El campo %s debe ser un arreglo de documentos';

    public const CLASIFICACION_NOMINA   = 'NOMINA';
    public const CLASIFICACION_NOVEDAD  = 'NOVEDAD';
    public const CLASIFICACION_AJUSTE   = 'AJUSTE';
    public const CLASIFICACION_ELIMINAR = 'ELIMINAR';

    // Aplicadas en procesos de Excel
    public const MAX_MEMORY     = '512M';
    public const EXCEL_NOMINA   = 'NOMINA';
    public const EXCEL_ELIMINAR = 'ELIMINAR';

    /**
     * Columnas reservadas a nivel de cabecera para documento Nomina / Novedad / Ajuste.
     *
     * @var array
     */
    public const COLUMNAS_DEFAULT_NOMINA_NOVEDAD_AJUSTE = [
        'TIPO DOCUMENTO',
        'APLICA NOVEDAD',
        'TIPO NOTA',
        'NIT EMPLEADOR',
        'NIT TRABAJADOR',
        'PREFIJO',
        'CONSECUTIVO',
        'PERIODO NOMINA',
        'FECHA EMISION',
        'HORA EMISION',
        'FECHA INICIO LIQUIDACION',
        'FECHA FIN LIQUIDACION',
        'TIEMPO LABORADO',
        'COD PAIS GENERACION',
        'COD DEPARTAMENTO GENERACION',
        'COD MUNICIPIO GENERACION',
        'COD FORMA DE PAGO',
        'COD MEDIO DE PAGO',
        'FECHAS PAGO',
        'NOTAS',
        'MONEDA',
        'TRM',
        'PREFIJO PREDECESOR',
        'CONSECUTIVO PREDECESOR',
        'FECHA EMISION PREDECESOR',
        'CUNE PREDECESOR'
    ];

    /**
     * Secciones y columnas reservadas para Devengados y Deducciones en NOMINA/NOVEDAD/AJUSTE
     *
     * @var array
     */
    public const SECCIONES_COLUMNAS_DEVENGADOS_DEDUCCIONES = [
        "DEVENGADOS" => [
            "NIT EMPLEADOR",
            "NIT TRABAJADOR",
            "PREFIJO",
            "CONSECUTIVO",
            "CONCEPTO",
            "DESCRIPCION",
            "TIPO",
            "FECHA INICIO",
            "HORA INICIO",
            "FECHA FIN",
            "HORA FIN",
            "CANTIDAD",
            "PORCENTAJE",
            "VALOR",
            "PAGO ADICIONAL",
            "VALOR SALARIAL",
            "VALOR NO SALARIAL",
            "VALOR SALARIAL ADICIONAL",
            "VALOR NO SALARIAL ADICIONAL",
            "VALOR ORDINARIO",
            "VALOR EXTRAORDINARIO"
        ],
        "DEDUCCIONES" => [
            "NIT EMPLEADOR",
            "NIT TRABAJADOR",
            "PREFIJO",
            "CONSECUTIVO",
            "CONCEPTO",
            "DESCRIPCION",
            "PORCENTAJE",
            "VALOR",
            "PORCENTAJE ADICIONAL",
            "VALOR ADICIONAL"
        ]
    ];

    /**
     * Columnas reservadas a nivel de cabecera para documento Eliminar.
     *
     * @var array
     */
    public const COLUMNAS_DEFAULT_ELIMINAR = [
        'TIPO DOCUMENTO',
        'TIPO NOTA',
        'NIT EMPLEADOR',
        'NIT TRABAJADOR',
        'PREFIJO',
        'CONSECUTIVO',
        'FECHA EMISION',
        'HORA EMISION',
        'COD PAIS GENERACION',
        'COD DEPARTAMENTO GENERACION',
        'COD MUNICIPIO GENERACION',
        'NOTAS',
        'PREFIJO PREDECESOR',
        'CONSECUTIVO PREDECESOR',
        'FECHA EMISION PREDECESOR',
        'CUNE PREDECESOR'
    ];

    /**
     * Campos que cuyo valor date debe ser reformateado.
     * 
     * @var array
     */
    public const FIX_FECHAS_NOMINA = [
        'fecha_emision',
        'fecha_inicio_liquidacion',
        'fecha_fin_liquidacion',
        'fechas_pago', // Esta columnas es un string que separa las fechas mediante comas (,)
        'fecha_emision_predecesor',
        'fecha_inicio',
        'fecha_fin'
    ];

    /**
     * Conceptos válidos para devengados.
     * 
     * @var array
     */
    public const CONCEPTOS_VALIDOS_DEVENGADOS = [
        'Basico',
        'Transporte',
        'HorasExtrasRecargos',
        'Vacaciones',
        'Primas',
        'Cesantias',
        'Incapacidades',
        'Licencias',
        'Bonificaciones',
        'Auxilios',
        'HuelgasLegales',
        'OtrosConceptos',
        'Compensaciones',
        'BonoEPCTVs',
        'Comisiones',
        'PagosTerceros',
        'Anticipos',
        'Dotacion',
        'ApoyoSost',
        'Teletrabajo',
        'BonifRetiro',
        'Indemnizacion',
        'Reintegro'
    ];

    /**
     * Conceptos válidos para deducciones.
     * 
     * @var array
     */
    public const CONCEPTOS_VALIDOS_DEDUCCIONES = [
        'Salud',
        'FondoPension',
        'FondoSP',
        'Sindicatos',
        'Sanciones',
        'Libranzas',
        'PagosTerceros',
        'Anticipos',
        'OtrasDeducciones',
        'PensionVoluntaria',
        'RetencionFuente',
        'AFC',
        'Cooperativa',
        'EmbargoFiscal',
        'PlanComplementarios',
        'Educacion',
        'Reintegro',
        'Deuda'
    ];

    /**
     * Conceptos que sólamente pueden existir una vez en Deducciones.
     * 
     * @var array
     */
    public const CONTADOR_CONCEPTOS_DEVENGADOS_NO_REPETIR = [
        'Basico'        => 0,
        'Primas'        => 0,
        'Cesantias'     => 0,
        'Dotacion'      => 0,
        'ApoyoSost'     => 0,
        'Teletrabajo'   => 0,
        'BonifRetiro'   => 0,
        'Indemnizacion' => 0,
        'Reintegro'     => 0
    ];

    /**
     * Conceptos que sólamente pueden existir una vez en Devegados.
     * 
     * @var array
     */
    public const CONTADOR_CONCEPTOS_DEDUCCIONES_NO_REPETIR = [
        'Salud'               => 0,
        'FondoPension'        => 0,
        'FondoSP'             => 0,
        'PensionVoluntaria'   => 0,
        'RetencionFuente'     => 0,
        'AFC'                 => 0,
        'Cooperativa'         => 0,
        'EmbargoFiscal'       => 0,
        'PlanComplementarios' => 0,
        'Educacion'           => 0,
        'Reintegro'           => 0,
        'Deuda'               => 0
    ];
}
