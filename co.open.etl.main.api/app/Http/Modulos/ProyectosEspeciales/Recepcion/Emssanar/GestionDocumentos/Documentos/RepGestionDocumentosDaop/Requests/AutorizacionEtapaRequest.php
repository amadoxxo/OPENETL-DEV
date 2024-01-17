<?php

namespace App\Http\Modulos\ProyectosEspeciales\Recepcion\Emssanar\GestionDocumentos\Documentos\RepGestionDocumentosDaop\Requests;

use Illuminate\Http\JsonResponse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class AutorizacionEtapaRequest extends FormRequest {
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
        if (strpos($this->path(), 'consultar-documento') !== false)
            return [
                'ofe_id'                => 'required|numeric',
                'gdo_identificacion'    => 'nullable|array',
                'gdo_clasificacion'     => 'nullable|string|in:FC,DS',
                'rfa_prefijo'           => 'nullable|string',
                'gdo_consecutivo'       => 'required|string',
                'gdo_fecha_desde'       => 'required|date_format:Y-m-d',
                'gdo_fecha_hasta'       => 'required|date_format:Y-m-d|after_or_equal:cdo_fecha_desde',
            ];
        elseif (strpos($this->path(), 'autorizar-etapa') !== false)
            return [
                'gdo_id'        => 'required|numeric',
                'etapa'         => 'required|numeric|between:1,7',
                'observacion'   => 'required|string'
            ];
    }
}
