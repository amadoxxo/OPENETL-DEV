<?php

namespace App\Http\Modulos\ProyectosEspeciales\Recepcion\Emssanar\GestionDocumentos\Documentos\RepGestionDocumentosDaop\Requests;

use Illuminate\Http\JsonResponse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class TrackingGestionDocumentosAccionesEnBloqueRequest extends FormRequest {
    /**
     * Determina si el usuario está autenticado o no y puede realizar está petición.
     */
    public function authorize(): bool {
        return auth()->user() ? true : false;
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param  Validator  $validator
     * @return void
     *
     * @throws HttpResponseException
     */
    protected function failedValidation(Validator $validator): void {
        throw new HttpResponseException(response()->json([
            'message' => 'Error de validación',
            'errors'  => $validator->errors()->all(),
        ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY));
    }

    /**
     * Retona las reglas de validación que aplican a la petición.
     *
     * @return array<string>, \Illuminate\Contracts\Validation\Rule|array|string>
     */
    public function rules() {
        $arrValidaciones   = [];
        $arrEstadosGestion = [
            "NO_CONFORME",
            "REVISION_NO_CONFORME",
            "APROBACION_NO_CONFORME",
            "NO_APROBADA_POR_CONTABILIDAD",
            "NO_APROBADA_POR_IMPUESTOS",
            "NO_APROBADA_PARA_PAGO"
        ];

        if (strpos($this->path(), 'gestionar-etapas') !== false) {
            $arrValidaciones = array_merge($arrValidaciones, [
                'gdo_ids' => 'required|string',
                'etapa'   => 'required|numeric|between:1,6'
            ]);

            // Se realizan las validaciones de los demás campos solo si el parámetro validar no existe o es false
            if (!isset($this->validar) || !$this->validar) {
                // Validación del estado dependiendo la etapa
                switch ($this->etapa) {
                    case 1:
                        $arrValidaciones = array_merge($arrValidaciones, [
                            'estado'  => 'required|string|in:CONFORME,NO_CONFORME'
                        ]);
                        break;
                    case 2:
                        $arrValidaciones = array_merge($arrValidaciones, [
                            'estado'  => 'required|string|in:REVISION_CONFORME,REVISION_NO_CONFORME'
                        ]);
                        break;
                    case 3:
                        $arrValidaciones = array_merge($arrValidaciones, [
                            'estado'  => 'required|string|in:APROBACION_CONFORME,APROBACION_NO_CONFORME'
                        ]);
                        break;
                    case 4:
                        $arrValidaciones = array_merge($arrValidaciones, [
                            'estado'  => 'required|string|in:APROBADA_POR_CONTABILIDAD,NO_APROBADA_POR_CONTABILIDAD'
                        ]);
                        break;
                    case 5:
                        $arrValidaciones = array_merge($arrValidaciones, [
                            'estado'  => 'required|string|in:APROBADA_POR_IMPUESTOS,NO_APROBADA_POR_IMPUESTOS'
                        ]);
                        break;
                    case 6:
                        $arrValidaciones = array_merge($arrValidaciones, [
                            'estado'  => 'required|string|in:APROBADA_Y_PAGADA,NO_APROBADA_PARA_PAGO'
                        ]);
                        break;
                    default:
                        break;
                }

                // Dependiendo el estado de Rechazo de cada etapa se hace obligatoria la causal de devolución
                if (in_array($this->estado, $arrEstadosGestion))
                    $arrValidaciones = array_merge($arrValidaciones, [
                        'cde_id'  => 'required|numeric|exists:App\Http\Modulos\ProyectosEspeciales\Recepcion\Emssanar\GestionDocumentos\Configuracion\CausalesDevolucion\CausalDevolucion,cde_id,estado,ACTIVO'
                    ]);
            }
        } elseif (strpos($this->path(), 'asignar-centro-operaciones') !== false) {
            $arrValidaciones = array_merge($arrValidaciones, [
                'gdo_ids' => 'required|string'
            ]);

            if (!isset($this->validar) || !$this->validar)
                $arrValidaciones = array_merge($arrValidaciones, [
                    'cop_id' => 'required|numeric||exists:App\Http\Modulos\ProyectosEspeciales\Recepcion\Emssanar\GestionDocumentos\Configuracion\CentrosOperaciones\CentroOperacion,cop_id,estado,ACTIVO'
                ]);
        } elseif (strpos($this->path(), 'siguiente-etapa') !== false) {
            $arrValidaciones = array_merge($arrValidaciones, [
                'gdo_ids' => 'required|string',
                'etapa'   => 'required|numeric|between:1,6'
            ]);
        } elseif(strpos($this->path(), 'datos-contabilizado') !== false) {
            $arrValidaciones = array_merge($arrValidaciones, [
                'gdo_ids' => 'required|string'
            ]);
            if (!isset($this->validar) || !$this->validar)
                $arrValidaciones = array_merge($arrValidaciones, [
                    'tipo_documento'   => 'required|string',
                    'numero_documento' => 'required|string'
                ]);
        }

        return $arrValidaciones;
    }

    /**
     * Obtiene los atributos personalizados para errores del validador.
     *
     * @return array
     */
    public function attributes(): array {
        return [
            'cde_id'            => 'Id de la causal de devolución',
            'tipo_documento'    => 'Tipo de Documento',
            'numero_documento'  => 'Número de Documento'
        ];
    }
}