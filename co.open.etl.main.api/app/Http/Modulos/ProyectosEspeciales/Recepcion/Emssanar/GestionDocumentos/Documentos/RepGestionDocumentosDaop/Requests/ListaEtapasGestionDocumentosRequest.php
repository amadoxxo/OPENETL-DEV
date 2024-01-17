<?php

namespace App\Http\Modulos\ProyectosEspeciales\Recepcion\Emssanar\GestionDocumentos\Documentos\RepGestionDocumentosDaop\Requests;

use Illuminate\Http\JsonResponse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class ListaEtapasGestionDocumentosRequest extends FormRequest {
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
     * Prepara la data para ser validada.
     * 
     * Permite realizar modificaciones sobre los parámetros recibidos para adaptarlos a reglas específicas de validación
     *
     * @return void
     */
    protected function prepareForValidation(): void {
        $this->merge([
            'ordenDireccion' => strtolower($this->ordenDireccion)
        ]);

        if(isset($this->estado_gestion))
            $this->merge([
                'estado_gestion' => array_map('strtoupper', $this->estado_gestion)
            ]);

    }

    /**
     * Retona las reglas de validación que aplican a la petición.
     *
     * @return array<string>, \Illuminate\Contracts\Validation\Rule|array|string>
     */
    public function rules(): array {
        return [
            'buscar'                => 'nullable|string',
            'length'                => 'required|numeric',
            'pag_siguiente'         => 'nullable|string',
            'pag_anterior'          => 'nullable|string',
            'columnaOrden'          => 'required|string',
            'ordenDireccion'        => 'required|string|in:asc,desc',
            'etapa'                 => 'required|numeric|between:1,7',
            'ofe_id'                => 'required|numeric',
            'gdo_identificacion'    => 'nullable|array',
            'gdo_clasificacion'     => 'nullable|string|in:FC,DS',
            'rfa_prefijo'           => 'nullable|string',
            'gdo_consecutivo'       => 'nullable|string',
            'gdo_fecha_desde'       => 'required|date_format:Y-m-d',
            'gdo_fecha_hasta'       => 'required|date_format:Y-m-d|after_or_equal:gdo_fecha_desde',
            'estado_gestion'        => 'nullable|array',
            "centro_operacion"      => $this->validateCentros(),
            "centro_costo"          => $this->validateCentros(),
        ];
    }

    /**
     * Obtiene los atributos personalizados para errores del validador.
     *
     * @return array
     */
    public function attributes(): array {
        return [
            'centro_operacion' => 'Centro de Operación',
            'centro_costo'     => 'Centro de Costo',
        ];
    }

    /**
     * Retorna las reglas para la validación de los centros.
     * 
     * @return array
     */
    private function validateCentros(): array {
        return ['nullable', function ($q, $value, $fail) {
            if ($value !== 'NA' && !is_numeric($value)) {
                $fail("El campo :attribute debe ser 'NA' o un valor numérico.");
            }
        }];
    }
}
