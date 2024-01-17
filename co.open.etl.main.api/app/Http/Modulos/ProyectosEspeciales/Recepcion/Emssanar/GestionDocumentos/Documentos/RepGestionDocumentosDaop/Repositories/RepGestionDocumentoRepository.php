<?php
namespace App\Http\Modulos\ProyectosEspeciales\Recepcion\Emssanar\GestionDocumentos\Documentos\RepGestionDocumentosDaop\Repositories;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Pagination\CursorPaginator;
use App\Http\Modulos\Configuracion\Adquirentes\ConfiguracionAdquirente;
use App\Http\Modulos\Recepcion\Configuracion\Proveedores\ConfiguracionProveedor;
use App\Http\Modulos\ProyectosEspeciales\Recepcion\Emssanar\GestionDocumentos\Configuracion\CentrosCosto\CentroCosto;
use App\Http\Modulos\ProyectosEspeciales\Recepcion\Emssanar\GestionDocumentos\Configuracion\CentrosOperaciones\CentroOperacion;
use App\Http\Modulos\ProyectosEspeciales\Recepcion\Emssanar\GestionDocumentos\Configuracion\CausalesDevolucion\CausalDevolucion;
use App\Http\Modulos\ProyectosEspeciales\Recepcion\Emssanar\GestionDocumentos\Documentos\RepGestionDocumentosDaop\RepGestionDocumentoDaop;

/**
 * Clase encargada del procesamiento lógico frente al motor de base de datos.
 */
class RepGestionDocumentoRepository {

    public function __construct() {}

    /**
     * Consulta la gestión de documentos dependiendo la etapa solicitada.
     *
     * @param Request $request Parámetros de la petición
     * @return CursorPaginator|Collection
     */
    public function consultarEtapasGestionDocumentos(Request $request) {
        $columnasSelect = [
            'rep_gestion_documentos_daop.gdo_id',
            'rep_gestion_documentos_daop.ofe_id',
            'rep_gestion_documentos_daop.adq_id',
            'rep_gestion_documentos_daop.pro_id',
            'rep_gestion_documentos_daop.mon_id',
            'rep_gestion_documentos_daop.mon_id_extranjera',
            'rep_gestion_documentos_daop.gdo_modulo',
            'rep_gestion_documentos_daop.gdo_clasificacion',
            'rep_gestion_documentos_daop.rfa_prefijo',
            'rep_gestion_documentos_daop.gdo_consecutivo',
            'rep_gestion_documentos_daop.gdo_identificacion',
            'rep_gestion_documentos_daop.gdo_fecha',
            'rep_gestion_documentos_daop.gdo_hora',
            'rep_gestion_documentos_daop.gdo_valor_a_pagar',
            'rep_gestion_documentos_daop.gdo_estado_etapa1',
            'rep_gestion_documentos_daop.gdo_estado_etapa2',
            'rep_gestion_documentos_daop.gdo_estado_etapa3',
            'rep_gestion_documentos_daop.gdo_estado_etapa4',
            'rep_gestion_documentos_daop.gdo_estado_etapa5',
            'rep_gestion_documentos_daop.gdo_estado_etapa6',
            'rep_gestion_documentos_daop.gdo_estado_etapa7',
            'rep_gestion_documentos_daop.estado',
            'rep_gestion_documentos_daop.fecha_creacion'
        ];

        $consultaDocumentos = RepGestionDocumentoDaop::select($columnasSelect)
            ->where('rep_gestion_documentos_daop.ofe_id', $request->ofe_id)
            ->when($request->filled('gdo_identificacion') && !empty($request->gdo_identificacion), function ($query) use ($request) {
                $query->whereIn('rep_gestion_documentos_daop.gdo_identificacion', $request->gdo_identificacion);
            })
            ->when($request->filled('gdo_clasificacion'), function ($query) use ($request) {
                $query->where('rep_gestion_documentos_daop.gdo_clasificacion', $request->gdo_clasificacion);
            })
            ->when($request->filled('rfa_prefijo'), function ($query) use ($request) {
                $query->where('rep_gestion_documentos_daop.rfa_prefijo', $request->rfa_prefijo);
            })
            ->when($request->filled('gdo_consecutivo'), function ($query) use ($request) {
                $query->where('rep_gestion_documentos_daop.gdo_consecutivo', $request->gdo_consecutivo);
            })
            // Condiciones Etapa 1
            ->when($request->filled('etapa') && $request->etapa == '1', function ($query) use ($request) {
                $query->where(function($query) {
                    $query->whereNull('rep_gestion_documentos_daop.gdo_estado_etapa2')
                        ->orWhereNotIn('rep_gestion_documentos_daop.gdo_estado_etapa2', ['SIN_GESTION', 'REVISION_CONFORME']);
                })
                ->when($request->filled('centro_operacion'), function ($query) use ($request) {
                    $query->when($request->centro_operacion === 'NA', function($query) {
                        $query->whereNull('rep_gestion_documentos_daop.cop_id');
                    }, function ($query) use($request) {
                        $query->where('rep_gestion_documentos_daop.cop_id', $request->centro_operacion);
                    });
                })
                ->where(function($query) use ($request) {
                    $query->when(!$request->filled('estado_gestion') || in_array("SIN_GESTION", $request->estado_gestion), function ($query) {
                        $query->orWhere('rep_gestion_documentos_daop.gdo_estado_etapa1', 'SIN_GESTION');
                    })
                    ->when(!$request->filled('estado_gestion') || in_array("CONFORME", $request->estado_gestion), function ($query) {
                        $query->orWhere('rep_gestion_documentos_daop.gdo_estado_etapa1', 'CONFORME')
                            ->where(function($query) {
                                $query->where('rep_gestion_documentos_daop.gdo_estado_etapa2', '!=', 'REVISION_NO_CONFORME')
                                    ->orWhereNull('rep_gestion_documentos_daop.gdo_estado_etapa2');
                            });
                    })
                    ->when(!$request->filled('estado_gestion') || in_array("NO_CONFORME", $request->estado_gestion), function ($query) {
                        $query->orWhere(function($query) {
                            $query->where('rep_gestion_documentos_daop.gdo_estado_etapa1', 'NO_CONFORME')
                                ->where(function($query) {
                                    $query->where('rep_gestion_documentos_daop.gdo_estado_etapa2', '!=', 'REVISION_NO_CONFORME')
                                        ->orWhereNull('rep_gestion_documentos_daop.gdo_estado_etapa2');
                                });
                        });
                    })
                    ->when(!$request->filled('estado_gestion') || in_array("RECHAZADO", $request->estado_gestion), function ($query) {
                        $query->orWhere('rep_gestion_documentos_daop.gdo_estado_etapa2', 'REVISION_NO_CONFORME');
                    });
                });
            })
            // Condiciones Etapa 2
            ->when($request->filled('etapa') && $request->etapa == '2', function ($query) use ($request) {
                $query->where(function($query) {
                    $query->whereNull('rep_gestion_documentos_daop.gdo_estado_etapa3')
                        ->orWhereNotIn('rep_gestion_documentos_daop.gdo_estado_etapa3', ['SIN_GESTION', 'APROBACION_CONFORME']);
                })
                ->when($request->filled('centro_costo'), function ($query) use ($request) {
                    $query->when($request->centro_costo === 'NA', function($query) {
                        $query->whereNull('rep_gestion_documentos_daop.cco_id');
                    }, function ($query) use($request) {
                        $query->where('rep_gestion_documentos_daop.cco_id', $request->centro_costo);
                    });
                })
                ->where(function($query) use ($request) {
                    $query->when(!$request->filled('estado_gestion') || in_array("SIN_GESTION", $request->estado_gestion), function ($query) {
                        $query->orWhere('rep_gestion_documentos_daop.gdo_estado_etapa2', 'SIN_GESTION');
                    })
                    ->when(!$request->filled('estado_gestion') || in_array("REVISION_CONFORME", $request->estado_gestion), function ($query) {
                        $query->orWhere('rep_gestion_documentos_daop.gdo_estado_etapa2', 'REVISION_CONFORME')
                            ->where(function($query) {
                                $query->where('rep_gestion_documentos_daop.gdo_estado_etapa3', '!=', 'APROBACION_NO_CONFORME')
                                    ->orWhereNull('rep_gestion_documentos_daop.gdo_estado_etapa3');
                            });
                    })
                    ->when(!$request->filled('estado_gestion') || in_array("REVISION_NO_CONFORME", $request->estado_gestion), function ($query) {
                        $query->orWhere(function($query) {
                            $query->where('rep_gestion_documentos_daop.gdo_estado_etapa2', 'REVISION_NO_CONFORME')
                                ->where(function($query) {
                                    $query->where('rep_gestion_documentos_daop.gdo_estado_etapa3', '!=', 'APROBACION_NO_CONFORME')
                                        ->orWhereNull('rep_gestion_documentos_daop.gdo_estado_etapa3');
                                });
                        });
                    })
                    ->when(!$request->filled('estado_gestion') || in_array("RECHAZADO", $request->estado_gestion), function ($query) {
                        $query->orWhere('rep_gestion_documentos_daop.gdo_estado_etapa3', 'APROBACION_NO_CONFORME');
                    });
                });
            })
            // Condiciones Etapa 3
            ->when($request->filled('etapa') && $request->etapa == '3', function ($query) use ($request) {
                $query->where(function($query) {
                    $query->whereNull('rep_gestion_documentos_daop.gdo_estado_etapa4')
                        ->orWhereNotIn('rep_gestion_documentos_daop.gdo_estado_etapa4', ['SIN_GESTION', 'APROBADA_POR_CONTABILIDAD']);
                })
                ->where(function($query) use ($request) {
                    $query->when(!$request->filled('estado_gestion') || in_array("SIN_GESTION", $request->estado_gestion), function ($query) {
                        $query->orWhere('rep_gestion_documentos_daop.gdo_estado_etapa3', 'SIN_GESTION');
                    })
                    ->when(!$request->filled('estado_gestion') || in_array("APROBACION_CONFORME", $request->estado_gestion), function ($query) {
                        $query->orWhere('rep_gestion_documentos_daop.gdo_estado_etapa3', 'APROBACION_CONFORME')
                            ->where(function($query) {
                                $query->where('rep_gestion_documentos_daop.gdo_estado_etapa4', '!=', 'NO_APROBADA_POR_CONTABILIDAD')
                                    ->orWhereNull('rep_gestion_documentos_daop.gdo_estado_etapa4');
                            });
                    })
                    ->when(!$request->filled('estado_gestion') || in_array("APROBACION_NO_CONFORME", $request->estado_gestion), function ($query) {
                        $query->orWhere(function($query) {
                            $query->where('rep_gestion_documentos_daop.gdo_estado_etapa3', 'APROBACION_NO_CONFORME')
                                ->where(function($query) {
                                    $query->where('rep_gestion_documentos_daop.gdo_estado_etapa4', '!=', 'NO_APROBADA_POR_CONTABILIDAD')
                                        ->orWhereNull('rep_gestion_documentos_daop.gdo_estado_etapa4');
                                });
                        });
                    })
                    ->when(!$request->filled('estado_gestion') || in_array("RECHAZADO", $request->estado_gestion), function ($query) {
                        $query->orWhere('rep_gestion_documentos_daop.gdo_estado_etapa4', 'NO_APROBADA_POR_CONTABILIDAD');
                    });
                });
            })
            // Condiciones Etapa 4
            ->when($request->filled('etapa') && $request->etapa == '4', function ($query) use ($request) {
                $query->where(function($query) {
                    $query->whereNull('rep_gestion_documentos_daop.gdo_estado_etapa5')
                        ->orWhereNotIn('rep_gestion_documentos_daop.gdo_estado_etapa5', ['SIN_GESTION', 'APROBADA_POR_IMPUESTOS']);
                })
                ->where(function($query) use ($request) {
                    $query->when(!$request->filled('estado_gestion') || in_array("SIN_GESTION", $request->estado_gestion), function ($query) {
                        $query->orWhere('rep_gestion_documentos_daop.gdo_estado_etapa4', 'SIN_GESTION');
                    })
                    ->when(!$request->filled('estado_gestion') || in_array("APROBADA_POR_CONTABILIDAD", $request->estado_gestion), function ($query) {
                        $query->orWhere('rep_gestion_documentos_daop.gdo_estado_etapa4', 'APROBADA_POR_CONTABILIDAD')
                            ->where(function($query) {
                                $query->where('rep_gestion_documentos_daop.gdo_estado_etapa5', '!=', 'NO_APROBADA_POR_IMPUESTOS')
                                    ->orWhereNull('rep_gestion_documentos_daop.gdo_estado_etapa5');
                            });
                    })
                    ->when(!$request->filled('estado_gestion') || in_array("NO_APROBADA_POR_CONTABILIDAD", $request->estado_gestion), function ($query) {
                        $query->orWhere(function($query) {
                            $query->where('rep_gestion_documentos_daop.gdo_estado_etapa4', 'NO_APROBADA_POR_CONTABILIDAD')
                                ->where(function($query) {
                                    $query->where('rep_gestion_documentos_daop.gdo_estado_etapa5', '!=', 'NO_APROBADA_POR_IMPUESTOS')
                                        ->orWhereNull('rep_gestion_documentos_daop.gdo_estado_etapa5');
                                });
                        });
                    })
                    ->when(!$request->filled('estado_gestion') || in_array("RECHAZADO", $request->estado_gestion), function ($query) {
                        $query->orWhere('rep_gestion_documentos_daop.gdo_estado_etapa5', 'NO_APROBADA_POR_IMPUESTOS');
                    });
                });
            })
            // Condiciones Etapa 5
            ->when($request->filled('etapa') && $request->etapa == '5', function ($query) use ($request) {
                $query->where(function($query) {
                    $query->whereNull('rep_gestion_documentos_daop.gdo_estado_etapa6')
                        ->orWhereNotIn('rep_gestion_documentos_daop.gdo_estado_etapa6', ['SIN_GESTION', 'APROBADA_Y_PAGADA']);
                })
                ->where(function($query) use ($request) {
                    $query->when(!$request->filled('estado_gestion') || in_array("SIN_GESTION", $request->estado_gestion), function ($query) {
                        $query->orWhere('rep_gestion_documentos_daop.gdo_estado_etapa5', 'SIN_GESTION');
                    })
                    ->when(!$request->filled('estado_gestion') || in_array("APROBADA_POR_IMPUESTOS", $request->estado_gestion), function ($query) {
                        $query->orWhere('rep_gestion_documentos_daop.gdo_estado_etapa5', 'APROBADA_POR_IMPUESTOS')
                            ->where(function($query) {
                                $query->where('rep_gestion_documentos_daop.gdo_estado_etapa6', '!=', 'NO_APROBADA_PARA_PAGO')
                                    ->orWhereNull('rep_gestion_documentos_daop.gdo_estado_etapa6');
                            });
                    })
                    ->when(!$request->filled('estado_gestion') || in_array("NO_APROBADA_POR_IMPUESTOS", $request->estado_gestion), function ($query) {
                        $query->orWhere(function($query) {
                            $query->where('rep_gestion_documentos_daop.gdo_estado_etapa5', 'NO_APROBADA_POR_IMPUESTOS')
                                ->where(function($query) {
                                    $query->where('rep_gestion_documentos_daop.gdo_estado_etapa6', '!=', 'NO_APROBADA_PARA_PAGO')
                                        ->orWhereNull('rep_gestion_documentos_daop.gdo_estado_etapa6');
                                });
                        });
                    })
                    ->when(!$request->filled('estado_gestion') || in_array("RECHAZADO", $request->estado_gestion), function ($query) {
                        $query->orWhere('rep_gestion_documentos_daop.gdo_estado_etapa6', 'NO_APROBADA_PARA_PAGO');
                    });
                });
            })
            // Condiciones Etapa 6
            ->when($request->filled('etapa') && $request->etapa == '6', function ($query) use ($request) {
                $query->where(function($query) use ($request) {
                    $query->when(!$request->filled('estado_gestion') || in_array("SIN_GESTION", $request->estado_gestion), function ($query) {
                        $query->orWhere('rep_gestion_documentos_daop.gdo_estado_etapa6', 'SIN_GESTION');
                    })
                    ->when(!$request->filled('estado_gestion') || in_array("APROBADA_Y_PAGADA", $request->estado_gestion), function ($query) {
                        $query->orWhere('rep_gestion_documentos_daop.gdo_estado_etapa6', 'APROBADA_Y_PAGADA');
                    })
                    ->when(!$request->filled('estado_gestion') || in_array("NO_APROBADA_PARA_PAGO", $request->estado_gestion), function ($query) {
                        $query->orWhere('rep_gestion_documentos_daop.gdo_estado_etapa6', 'NO_APROBADA_PARA_PAGO');
                    });
                });
            })
            // Condiciones Etapa 7
            ->when($request->filled('etapa') && $request->etapa == '7', function ($query) {
                $query->where(function($query) {
                    $query->where('rep_gestion_documentos_daop.gdo_estado_etapa7', 'SIN_GESTION');
                });
            })
            ->with([
                'getConfiguracionObligadoFacturarElectronicamente' => function($query) {
                    $query->select([
                        'ofe_id',
                        'ofe_identificacion',
                        DB::raw('IF(ofe_razon_social IS NULL OR ofe_razon_social = "", CONCAT(COALESCE(ofe_primer_nombre, ""), " ", COALESCE(ofe_otros_nombres, ""), " ", COALESCE(ofe_primer_apellido, ""), " ", COALESCE(ofe_segundo_apellido, "")), ofe_razon_social) as nombre_completo')
                    ]);
                },
                'getConfiguracionProveedor' => function($query) {
                    $query->select([
                            'pro_id',
                            'pro_identificacion',
                            DB::raw('IF(pro_razon_social IS NULL OR pro_razon_social = "", CONCAT(COALESCE(pro_primer_nombre, ""), " ", COALESCE(pro_otros_nombres, ""), " ", COALESCE(pro_primer_apellido, ""), " ", COALESCE(pro_segundo_apellido, "")), pro_razon_social) as nombre_completo')
                        ]);
                },
                'getConfiguracionAdquirente' => function($query) {
                    $query->select([
                            'adq_id',
                            'adq_identificacion',
                            DB::raw('IF(adq_razon_social IS NULL OR adq_razon_social = "", CONCAT(COALESCE(adq_primer_nombre, ""), " ", COALESCE(adq_otros_nombres, ""), " ", COALESCE(adq_primer_apellido, ""), " ", COALESCE(adq_segundo_apellido, "")), adq_razon_social) as nombre_completo')
                        ]);
                },
                'getParametrosMoneda:mon_id,mon_codigo',
                'getParametrosMonedaExtranjera:mon_id,mon_codigo',
            ])
            ->rightJoin('etl_obligados_facturar_electronicamente', function($query) {
                $query->whereRaw('rep_gestion_documentos_daop.ofe_id = etl_obligados_facturar_electronicamente.ofe_id')
                    ->where(function($query) {
                        if(!empty(auth()->user()->bdd_id_rg))
                            $query->where('etl_obligados_facturar_electronicamente' . '.bdd_id_rg', auth()->user()->bdd_id_rg);
                        else
                            $query->whereNull('etl_obligados_facturar_electronicamente' . '.bdd_id_rg');
                    });
            });

        $documentos = clone $consultaDocumentos;
        $documentos = $documentos->whereBetween('rep_gestion_documentos_daop.gdo_fecha', [$request->gdo_fecha_desde, $request->gdo_fecha_hasta])
            ->orderBy('rep_gestion_documentos_daop.' . $request->columnaOrden, $request->ordenDireccion)
            ->orderBy('rep_gestion_documentos_daop.gdo_id', $request->ordenDireccion);

        return $documentos
            ->cursorPaginate($request->length);
    }

    /**
     * Filtra la información de emisores (Adquirentes|Proveedores) de acuerdo a los parámetros recibidos y retorna un array de resultados.
     *
     * @param Request $request Parámetros de la petición
     * @return Collection
     */
    public function searchAdquirentes(Request $request): Collection {
        $adquirentes = ConfiguracionAdquirente::select([
                'adq_id as id',
                'adq_identificacion as identificacion',
                \DB::raw('IF(adq_razon_social IS NULL OR adq_razon_social = "", REPLACE(CONCAT(COALESCE(adq_primer_nombre, ""), " ", COALESCE(adq_otros_nombres, ""), " ", COALESCE(adq_primer_apellido, ""), " ", COALESCE(adq_segundo_apellido, "")), "  ", " "), adq_razon_social) as nombre_completo')
            ])
            ->where('ofe_id', $request->ofe_id)
            ->where('adq_tipo_vendedor_ds', 'SI')
            ->where(function ($query) use ($request) {
                $query->where('adq_razon_social', 'like', '%' . $request->buscar . '%')
                    ->orWhere('adq_nombre_comercial', 'like', '%' . $request->buscar . '%')
                    ->orWhere('adq_primer_apellido', 'like', '%' . $request->buscar . '%')
                    ->orWhere('adq_segundo_apellido', 'like', '%' . $request->buscar . '%')
                    ->orWhere('adq_primer_nombre', 'like', '%' . $request->buscar . '%')
                    ->orWhere('adq_otros_nombres', 'like', '%' . $request->buscar . '%')
                    ->orWhere('adq_identificacion', 'like', '%' . $request->buscar . '%')
                    ->orWhereRaw("REPLACE(CONCAT(COALESCE(adq_primer_nombre, ''), ' ', COALESCE(adq_otros_nombres, ''), ' ', COALESCE(adq_primer_apellido, ''), ' ', COALESCE(adq_segundo_apellido, '')), '  ', ' ') like ?", ['%' . $request->buscar . '%']);
            })
            ->where('estado', 'ACTIVO')
            ->get();

        $proveedores = ConfiguracionProveedor::select([
                'pro_id as id',
                'pro_identificacion as identificacion',
                \DB::raw('IF(pro_razon_social IS NULL OR pro_razon_social = "", REPLACE(CONCAT(COALESCE(pro_primer_nombre, ""), " ", COALESCE(pro_otros_nombres, ""), " ", COALESCE(pro_primer_apellido, ""), " ", COALESCE(pro_segundo_apellido, "")), "  ", " "), pro_razon_social) as nombre_completo')
            ])
            ->where('ofe_id', $request->ofe_id)
            ->where(function ($query) use ($request) {
                $query->where('pro_razon_social', 'like', '%' . $request->buscar . '%')
                    ->orWhere('pro_nombre_comercial', 'like', '%' . $request->buscar . '%')
                    ->orWhere('pro_primer_apellido', 'like', '%' . $request->buscar . '%')
                    ->orWhere('pro_segundo_apellido', 'like', '%' . $request->buscar . '%')
                    ->orWhere('pro_primer_nombre', 'like', '%' . $request->buscar . '%')
                    ->orWhere('pro_otros_nombres', 'like', '%' . $request->buscar . '%')
                    ->orWhere('pro_identificacion', 'like', '%' . $request->buscar . '%')
                    ->orWhereRaw("REPLACE(CONCAT(COALESCE(pro_primer_nombre, ''), ' ', COALESCE(pro_otros_nombres, ''), ' ', COALESCE(pro_primer_apellido, ''), ' ', COALESCE(pro_segundo_apellido, '')), '  ', ' ') like ?", ['%' . $request->buscar . '%']);
            })
            ->where('estado', 'ACTIVO')
            ->get();

        $emisores = $proveedores->concat($adquirentes);
        $emisores = $emisores->unique('identificacion')->values();

        return $emisores;
    }

    /**
     * Consulta el documento para realizar sus respectivas validaciones.
     *
     * @param int $gdoId Id del documento en gestión
     * @return RepGestionDocumentoDaop|null
     */
    public function consultarGestionDocumento(int $gdoId) {
        $documentoGestion = RepGestionDocumentoDaop::where('gdo_id', $gdoId)
            ->first();

        return $documentoGestion;
    }

    /**
     * Permite actualizar la información del documento por la opción de gestionar.
     *
     * @param Request $request Parámetros de la petición
     * @return array
     */
    public function actualizarGestionDocumentos(Request $request): array {
        $arrErrores = [];
        $objUser    = auth()->user();
        $etapa      = $request->etapa;
        $arrGdoIds  = explode(',', $request->gdo_ids);

        $causalDevolucion = CausalDevolucion::select(['cde_id', 'cde_descripcion'])
            ->where('cde_id', $request->cde_id)
            ->first();

        RepGestionDocumentoDaop::find($arrGdoIds)
            ->map(function ($documento) use ($request, $etapa, $objUser, $causalDevolucion, &$arrErrores) {
                $arrHistoricoEtapas = !empty($documento->gdo_historico_etapas) ? json_decode($documento->gdo_historico_etapas, true) : [];
                $arrHistorico[] = [
                    "accion"            => "ESTADO",
                    "etapa"             => $etapa,
                    "usu_id"            => $objUser->usu_id,
                    "usu_correo"        => $objUser->usu_email,
                    "usu_nombre"        => $objUser->usu_nombre,
                    "fecha"             => date('Y-m-d'),
                    "hora"              => date('H:i:s'),
                    "estado"            => $request->estado,
                    "causal_devolucion" => $causalDevolucion ? $causalDevolucion->cde_descripcion : null,
                    "observacion"       => $request->observacion
                ];
                $arrHistoricoEtapas = array_merge($arrHistoricoEtapas, $arrHistorico);

                try {
                    $documento->update([
                        "gdo_estado_etapa".$etapa      => $request->estado,
                        "cde_id_etapa".$etapa          => $request->cde_id,
                        "gdo_observacion_etapa".$etapa => $request->filled('observacion') ? $request->observacion : null,
                        "gdo_historico_etapas"         => json_encode($arrHistoricoEtapas),
                    ]);
                } catch (\Exception $e) {
                    $arrErrores[] = "Error al actualizar el documento [{$documento->rfa_prefijo}-{$documento->gdo_consecutivo}]";
                }
            });
        
            return $arrErrores;
    }

    /**
     * Asigna el centro de operaciones sobre la gestión del documento en la etapa 1.
     *
     * @param Request $request Parámetros de la petición
     * @return array
     */
    public function asignarCentroOperaciones(Request $request): array {
        $arrErrores = [];
        $objUser    = auth()->user();
        $etapa      = $request->etapa;
        $arrGdoIds  = explode(',', $request->gdo_ids);

        $centroOperacion = CentroOperacion::select(['cop_id', 'cop_descripcion'])
            ->where('cop_id', $request->cop_id)
            ->first();

        RepGestionDocumentoDaop::find($arrGdoIds)
            ->map(function ($documento) use ($etapa, $objUser, $centroOperacion, &$arrErrores) {
                $arrHistoricoEtapas = !empty($documento->gdo_historico_etapas) ? json_decode($documento->gdo_historico_etapas, true) : [];
                $arrHistorico[] = [
                    "accion"            => "CENTRO_OPERACIONES",
                    "etapa"             => $etapa,
                    "usu_id"            => $objUser->usu_id,
                    "usu_correo"        => $objUser->usu_email,
                    "usu_nombre"        => $objUser->usu_nombre,
                    "fecha"             => date('Y-m-d'),
                    "hora"              => date('H:i:s'),
                    "centro_operacion"  => $centroOperacion->cop_descripcion
                ];
                $arrHistoricoEtapas = array_merge($arrHistoricoEtapas, $arrHistorico);

                try {
                    $documento->update([
                        "cop_id"                => $centroOperacion->cop_id,
                        "gdo_historico_etapas"  => json_encode($arrHistoricoEtapas),
                    ]);
                } catch (\Exception $e) {
                    $arrErrores[] = "Error al actualizar el documento [{$documento->rfa_prefijo}-{$documento->gdo_consecutivo}]";
                }
            });
        
        return $arrErrores;
    }

    /**
     * Asigna el centro de costos sobre la gestión del documento en la etapa 2.
     *
     * @param Request $request Parámetros de la petición
     * @return array
     */
    public function asignarCentroCostos(Request $request): array {
        $arrErrores = [];
        $objUser    = auth()->user();
        $etapa      = $request->etapa;
        $arrGdoIds  = explode(',', $request->gdo_ids);

        $centroCosto = CentroCosto::select(['cco_id', 'cco_codigo', 'cco_descripcion'])
            ->where('cco_id', $request->cco_id)
            ->first();

        RepGestionDocumentoDaop::find($arrGdoIds)
            ->map(function ($documento) use ($etapa, $objUser, $centroCosto, &$arrErrores) {
                $arrHistoricoEtapas = !empty($documento->gdo_historico_etapas) ? json_decode($documento->gdo_historico_etapas, true) : [];
                $arrHistorico[] = [
                    "accion"                   => "CENTRO_COSTO",
                    "etapa"                    => $etapa,
                    "usu_id"                   => $objUser->usu_id,
                    "usu_correo"               => $objUser->usu_email,
                    "usu_nombre"               => $objUser->usu_nombre,
                    "fecha"                    => date('Y-m-d'),
                    "hora"                     => date('H:i:s'),
                    "codigo_centro_costo"      => $centroCosto->cco_codigo,
                    "descripcion_centro_costo" => $centroCosto->cco_descripcion,
                ];
                $arrHistoricoEtapas = array_merge($arrHistoricoEtapas, $arrHistorico);

                try {
                    $documento->update([
                        "cco_id"                => $centroCosto->cco_id,
                        "gdo_historico_etapas"  => json_encode($arrHistoricoEtapas),
                    ]);
                } catch (\Exception $e) {
                    $arrErrores[] = "Error al actualizar el documento [{$documento->rfa_prefijo}-{$documento->gdo_consecutivo}]";
                }
            });

        return $arrErrores;
    }

    /**
     * Actualiza los estados de la gestión del documento para pasar a la siguiente etapa.
     *
     * @param Request $request Parámetros de la petición
     * @return array
     */
    public function actualizarEstadoSiguienteEtapa(Request $request): array {
        $arrErrores = [];
        $etapa      = ($request->etapa+1);
        $arrGdoIds  = explode(',', $request->gdo_ids);

        RepGestionDocumentoDaop::find($arrGdoIds)
            ->map(function ($documento) use ($etapa, &$arrErrores) {
                try {
                    $documento->update([
                        "gdo_estado_etapa".$etapa => "SIN_GESTION"
                    ]);
                } catch (\Exception $e) {
                    $arrErrores[] = "Error al actualizar el documento [{$documento->rfa_prefijo}-{$documento->gdo_consecutivo}]";
                }
            });

            return $arrErrores;
    }

    /**
     * Consulta el detalle de las etapas del documento.
     *
     * @param int $gdoId Id del documento en gestión
     * @return RepGestionDocumentoDaop|null
     */
    public function consultarDetalleGestionDocumento(int $gdoId) {
        $documentoGestion = RepGestionDocumentoDaop::
            with([
                'getConfiguracionProveedor' => function($query) {
                    $query->select([
                            'pro_id',
                            'pro_identificacion',
                            DB::raw('IF(pro_razon_social IS NULL OR pro_razon_social = "", CONCAT(COALESCE(pro_primer_nombre, ""), " ", COALESCE(pro_otros_nombres, ""), " ", COALESCE(pro_primer_apellido, ""), " ", COALESCE(pro_segundo_apellido, "")), pro_razon_social) as nombre_completo')
                        ]);
                },
                'getConfiguracionAdquirente' => function($query) {
                    $query->select([
                            'adq_id',
                            'adq_identificacion',
                            DB::raw('IF(adq_razon_social IS NULL OR adq_razon_social = "", CONCAT(COALESCE(adq_primer_nombre, ""), " ", COALESCE(adq_otros_nombres, ""), " ", COALESCE(adq_primer_apellido, ""), " ", COALESCE(adq_segundo_apellido, "")), adq_razon_social) as nombre_completo')
                        ]);
                },
                'getConfiguracionCausalDevolucionEtapa1:cde_id,cde_descripcion',
                'getConfiguracionCausalDevolucionEtapa2:cde_id,cde_descripcion',
                'getConfiguracionCausalDevolucionEtapa3:cde_id,cde_descripcion',
                'getConfiguracionCausalDevolucionEtapa4:cde_id,cde_descripcion',
                'getConfiguracionCausalDevolucionEtapa5:cde_id,cde_descripcion',
                'getConfiguracionCausalDevolucionEtapa6:cde_id,cde_descripcion',
                'getConfiguracionCentroOperacion:cop_id,cop_descripcion',
                'getConfiguracionCentroCosto:cco_id,cco_codigo,cco_descripcion'
            ])
            ->where('gdo_id', $gdoId)
            ->first();

        return $documentoGestion;
    }

    /**
     * Actualiza los datos contabilizado sobre la gestión del documento en la etapa 4.
     *
     * @param Request $request Parámetros de la petición
     * @return array
     */
    public function actualizarDatosContabilizado(Request $request): array {
        $arrErrores = [];
        $objUser    = auth()->user();
        $arrGdoIds  = explode(',', $request->gdo_ids);

        RepGestionDocumentoDaop::find($arrGdoIds)
            ->map(function ($documento) use (&$arrErrores, $request, $objUser) {
                try {
                    $arrUpdate = [
                        'tipo_documento' => $request->tipo_documento,
                        'numero_documento' => $request->numero_documento,
                    ];
                    $arrHistoricoEtapas = !empty($documento->gdo_historico_etapas) ? json_decode($documento->gdo_historico_etapas, true) : [];
                    $arrHistorico[] = [
                        "accion"           => "DATOS_CONTABILIZADO",
                        "etapa"            => $request->etapa,
                        "usu_id"           => $objUser->usu_id,
                        "usu_correo"       => $objUser->usu_email,
                        "usu_nombre"       => $objUser->usu_nombre,
                        "fecha"            => date('Y-m-d'),
                        "hora"             => date('H:i:s'),
                        "tipo_documento"   => $request->tipo_documento,
                        "numero_documento" => $request->numero_documento,
                    ];
                    $arrHistoricoEtapas = array_merge($arrHistoricoEtapas, $arrHistorico);

                    $documento->update([
                        "gdo_informacion_etapa4" => json_encode($arrUpdate),
                        "gdo_historico_etapas"  => json_encode($arrHistoricoEtapas)
                    ]);
                } catch (\Exception $e) {
                    $arrErrores[] = "Error al actualizar el documento [{$documento->rfa_prefijo}-{$documento->gdo_consecutivo}]";
                }
            });

        return $arrErrores;
    }
}