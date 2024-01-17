<?php
namespace App\Traits;

use Validator;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Modulos\Configuracion\NominaElectronica\Empleadores\ConfiguracionEmpleador;
use App\Http\Modulos\Configuracion\NominaElectronica\Trabajadores\ConfiguracionTrabajador;
use App\Http\Modulos\Parametros\TiposDocumentosElectronicos\ParametrosTipoDocumentoElectronico;

trait NominaTrait {
    /**
     * Propiedad para almacenar las columnas reservadas a nivel de cabecera para documento Nomina / Novedad / Ajuste.
     *
     * @var array
     */
    public static $columnasDefaultNominaNovedadAjuste = [
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
     * Secciones y columnas para Devengados y Deducciones
     *
     * @var array
     */
    public static $seccionesColumnasDevengadosDeducciones = [
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
     * Array de contenido para la hoja de Conceptos en la plantilla de excel para Nómina Electrónica de Nómina, Ajuste, Novedad.
     *
     * @var array
     */
    public static $conceptosNominaElectronica = [
        [
            'DEVENGADOS', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', ''
        ], [
            'CONCEPTO', 'DESCRIPCION', 'TIPO', 'FECHA INICIO', 'HORA INICIO', 'FECHA FIN', 'HORA FIN', 'CANTIDAD', 'PORCENTAJE', 'VALOR', 'PAGO ADICIONAL', 'VALOR SALARIAL', 'VALOR NO SALARIAL', 'VALOR SALARIAL ADICIONAL', 'VALOR NO SALARIAL ADICIONAL', 'VALOR ORDINARIO', 'VALOR EXTRAORDINARIO',
        ], [
            'Basico', '', '', '', '', '', '', 'x', '', 'x', '', '', '', '', '', '', ''
        ], [
            'Transporte', '', '', '', '', '', '', '', '', 'x', '', 'x', 'x', '', '', '', ''
        ], [
            'HorasExtrasRecargos', '', "HEDs\r\nHENs\r\nHRNs\r\nHEDDFs\r\nHRDDFs\r\nHENDFs\r\nHRNDFs", 'x', 'x', 'x', 'x', 'x', 'x', 'x', '', '', '', '', '', '', ''
        ], [
            'Vacaciones', '', "VacacionesComunes\r\nVacacionesCompensadas", 'x', '', 'x', '', 'x', '', 'x', '', '', '', '', '', '', ''
        ], [
            'Primas', '', '', '', '', '', '', 'x', '', 'x', '', '', 'x', '', '', '', ''
        ], [
            'Cesantias', '', '', '', '', '', '', '', 'x', 'x', 'x', '', '', '', '', '', ''
        ], [
            'Incapacidades', '', 'Codigo Tipo Incapacidad', 'x', '', 'x', '', 'x', '', 'x', '', '', '', '', '', '', ''
        ], [
            'Licencias', '', "LicenciaMP\r\nLicenciaR\r\nLicenciaNR", 'x', '', 'x', '', 'x', '', 'x', '', '', '', '', '', '', ''
        ], [
            'Bonificaciones', '', '', '', '', '', '', '', '', '', '', 'x', 'x', '', '', '', ''
        ], [
            'Auxilios', '', '', '', '', '', '', '', '', '', '','x','x', '', '', '',''
        ], [
            'HuelgasLegales', '', '','x', '','x', '','x', '', '', '', '', '', '', '', '',''
        ], [
            'OtrosConceptos','x', '', '', '', '', '', '', '', '', '','x','x', '', '', '',''
        ], [
            'Compensaciones', '', '', '', '', '', '', '', '', '', '', '', '', '', '','x','x',
        ], [
            'BonoEPCTVs', '', '', '', '', '', '', '', '', '', '','x','x','x','x', '',''
        ], [
            'Comisiones', '', '', '', '', '', '', '', '','x', '', '', '', '', '', '',''
        ], [
            'PagosTerceros', '', '', '', '', '', '', '', '','x', '', '', '', '', '', '',''
        ], [
            'Anticipos', '', '', '', '', '', '', '', '','x', '', '', '', '', '', '',''
        ], [
            'Dotacion', '', '', '', '', '', '', '', '','x', '', '', '', '', '', '',''
        ], [
            'ApoyoSost', '', '', '', '', '', '', '', '','x', '', '', '', '', '', '',''
        ], [
            'Teletrabajo', '', '', '', '', '', '', '', '','x', '', '', '', '', '', '',''
        ], [
            'BonifRetiro', '', '', '', '', '', '', '', '','x', '', '', '', '', '', '',''
        ], [
            'Indemnizacion', '', '', '', '', '', '', '', '','x', '', '', '', '', '', '',''
        ], [
            'Reintegro', '', '', '', '', '', '', '', '','x', '', '', '', '', '', '',''
        ], [
            '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '',''
        ], [
            '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '',''
        ], [
            'DEDUCCIONES', '', '', '', '', ''
        ], [
            'CONCEPTO', 'DESCRIPCION', 'PORCENTAJE', 'VALOR', 'PORCENTAJE ADICIONAL', 'VALOR ADICIONAL'
        ], [
            'Salud', '', 'x', 'x', '', ''
        ], [
            'FondoPension', '', 'x', 'x', '', ''
        ], [
            'FondoSP', '', 'x', 'x', 'x', 'x'
        ], [
            'Sindicatos', '', 'x', 'x', '', ''
        ], [
            'Sanciones', '', '', 'x', '', 'x'
        ], [
            'Libranzas', 'x', '', 'x', '', ''
        ], [
            'PagosTerceros', '', '', 'x', '', ''
        ], [
            'Anticipos', '', '', 'x', '', ''
        ], [
            'OtrasDeducciones', '', '', 'x', '', ''
        ], [
            'PensionVoluntaria', '', '', 'x', '', ''
        ], [
            'RetencionFuente', '', '', 'x', '', ''
        ], [
            'AFC', '', '', 'x', '', ''
        ], [
            'Cooperativa', '', '', 'x', '', ''
        ], [
            'EmbargoFiscal', '', '', 'x', '', ''
        ], [
            'PlanComplementarios', '', '', 'x', '', ''
        ], [
            'Educacion', '', '', 'x', '', ''
        ], [
            'Reintegro', '', '', 'x', '', ''
        ], [
            'Deuda', '', '', 'x', '', ''
        ]
    ];

    /**
     * Propiedad para almacenar las columnas reservadas a nivel de cabecera para documento Eliminar.
     *
     * @var array
     */
    public static $columnasDefaultEliminar = [
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
     * Aplica formato numérico a un valor.
     * 
     * @param float $valor Valor a formatear
     * @param boolean $calcular Indica si se debe calcular la cantidad de decimales para el formato, false no se calcula y se aplica redondeo a dos decimales, true se aplica el redondeo dependiendo de la cantidad de decimales del numero
     * @param boolean $campoCantidad Indica si se trata de un campo _cantidad, en cuyo caso se debe verificar si tiene decimales mayores a cero se envían los decimales, sino, se envía el entero
     * @return float|int $valor Valor formateado
     */
    public function formatearValor(float $valor, bool $calcular = false, bool $campoCantidad = false) {
        if(!$campoCantidad){
            if (!$calcular) {
                return number_format($valor, 2, '.', '');
            } else {
                $valor = $valor + 0; //eliminando ceros a la izquierda de los decimales
                $parteDecimal = explode(".", $valor);
                $decimales = (isset($parteDecimal[1]) && $parteDecimal[1] > 0) ? strlen($parteDecimal[1]) : 2;
                return number_format($valor, $decimales, '.', '');
            }
        } else {
            $partes = explode('.', $valor);
            if(array_key_exists(1, $partes) && floatval($partes[1]) > 0) {
                $decimales = $valor = $valor + 0;
                $parteDecimal = explode(".", $valor);
                $decimales = (isset($parteDecimal[1]) && $parteDecimal[1] > 0) ? strlen($parteDecimal[1]) : 2;
                return number_format($valor, $decimales, '.', '');
            } else {
                return number_format($valor, 0, '', '');
            }
        }
    }

    /**
     * Encuentra información de detalle de devengados/deducciones de acuerdo al concepto recibido para buscar.
     *
     * @param array $arrDevengadosDeducciones Array con los registros de detalle de devengados/deducciones
     * @param string $prefijoColumna Prefijo de la columna sobre la cual buscar
     * @param string $conceptoBuscar Concepto a buscar
     * @return array Array con la información correspondiente si la búsqueda es efectiva
     */
    public function obtenerRegistroDevengadoDeduccion(array $arrDevengadosDeducciones, string $prefijoColumna, string $conceptoBuscar) {
        $registro = Arr::where($arrDevengadosDeducciones, function ($value, $key) use ($prefijoColumna, $conceptoBuscar) {
            if(isset($value[$prefijoColumna . '_concepto']))
                return ($value[$prefijoColumna . '_concepto'] == $conceptoBuscar);
        });

        if(!empty($registro))
            return array_values($registro);
        else
            return [];
    }

    /**
     * Encuentra información de detalle de devengados/deducciones de acuerdo al tipo recibido para buscar.
     *
     * @param array $arrDevengadosDeducciones Array con los registros de detalle de devengados/deducciones
     * @param string $tipoBuscar Concepto a buscar
     * @return array Array con la información correspondiente si la búsqueda es efectiva
     */
    public function obtenerRegistroDevengadoTipo(array $arrDevengadosDeducciones, string $tipoBuscar) {
        $registro = Arr::where($arrDevengadosDeducciones, function ($value, $key) use ($tipoBuscar) {
            if(isset($value['ddv_tipo']))
                return ($value['ddv_tipo'] == $tipoBuscar);
        });

        if(!empty($registro))
            return array_values($registro);
        else
            return [];
    }

    /**
     * Obtiene el datos paramétrico requerido del array de paramétricas.
     * 
     * @param array $arrParametrica Array de la parametrica desde el cual se va a obtener el dato
     * @param string $campoParametrica Nombre del campo dentro del array de paramétricas
     * @param string $campoDcumento Valor del campo asociado al documento con el cual se va a comparar
     * @param string $campoRetornar Nombre del campo del array de paramétricas cuyo valor será devuelto
     * @return string $parametrica Valor de la paramétrica solicitada
     */
    public function obtieneDatoParametrico($arrParametrica, $campoParametrica, $campoDocumento, $campoRetornar) {
        $parametrica = Arr::where($arrParametrica, function ($value, $key) use ($campoParametrica, $campoDocumento) {
            return (isset($value[$campoParametrica]) && $value[$campoParametrica] == $campoDocumento);
        });
        $parametrica = Arr::pluck($parametrica, $campoRetornar);
        if(array_key_exists(0, $parametrica)) {
            return $parametrica[0];
        } else {
            return null;
        }
    }

    /**
     * Realiza validaciones sobre el request que se recibe para consulta de documentos, consulta de estados y cambio de estados.
     *
     * @param Request $request
     * @param bool $incluyeEstado indica si el request debe incluir el parámetro estado
     * @return array $respuesta Resultado de las validaciones
     */
    public function validacionesConsultasDocumentos(Request $request, $incluyeEstado = false) {
        $respuesta = [];

        $rulesRequest = [
            'emp_identificacion' => 'required|string|max:20',
            'tra_identificacion' => 'required|string|max:20',
            'tipo_documento'     => 'required|string|max:3',
            'prefijo'            => 'nullable|string|max:10',
            'consecutivo'        => 'required|string|max:20'
        ];
        
        if($incluyeEstado)
            $rulesRequest['estado'] = 'required|string|in:ACTIVO,INACTIVO';

        $validador = Validator::make($request->all(), $rulesRequest);
        if ($validador->fails()) {
            $respuesta['errors'][] = $validador->errors()->all();
        } else {
            $empleador = ConfiguracionEmpleador::select(['emp_id','emp_identificacion'])
                ->where('emp_identificacion', $request->emp_identificacion)
                ->first();

            if (!$empleador) {
                $respuesta['errors'][] = "No existe el Empleador con NIT [{$request->emp_identificacion}]";
            } else {
                $respuesta['empleador'] = $empleador;

                $trabajador = ConfiguracionTrabajador::select(['tra_id','tra_identificacion'])
                    ->where('emp_id', $empleador->emp_id)
                    ->where('tra_identificacion', $request->tra_identificacion)
                    ->first();

                if (!$trabajador) {
                    $respuesta['errors'][] = "No existe el trabajador con identificacion [{$request->tra_identificacion}] para el empleador [{$request->emp_identificacion}]";
                } else {
                    $respuesta['trabajador'] = $trabajador;
                }

                $tdeID = ParametrosTipoDocumentoElectronico::select(['tde_id', 'tde_codigo', 'tde_aplica_para', 'fecha_vigencia_desde', 'fecha_vigencia_hasta'])
                    ->where('tde_codigo', $request->tipo_documento)
                    ->where('estado', 'ACTIVO')
                    ->first();

                if (!$tdeID || ($tdeID && $tdeID->tde_aplica_para != 'DN')) {
                    $respuesta['errors'][] = "No existe el tipo de documento electrónico [{$request->tipo_documento}] para nómina electrónica";
                } else {
                    $respuesta['tdeID'] = $tdeID;
                }
            }
        }

        return $respuesta;
    }
}