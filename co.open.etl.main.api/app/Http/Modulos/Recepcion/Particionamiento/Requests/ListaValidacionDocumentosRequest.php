<?php

namespace App\Http\Modulos\Recepcion\Particionamiento\Requests;

use Illuminate\Http\JsonResponse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Clase para la verificación HTTP request en la lista de validación de documentos.
 */
class ListaValidacionDocumentosRequest extends FormRequest {
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

        if(isset($this->estado_validacion))
            $this->merge([
                'estado_validacion' => array_map('strtoupper', $this->estado_validacion)
            ]);

        if(isset($this->cdo_origen))
            $this->merge([
                'cdo_origen' => strtoupper($this->cdo_origen)
            ]);
    }

    /**
     * Retona las reglas de validación que aplican a la petición.
     *
     * @return array<string>, \Illuminate\Contracts\Validation\Rule|array|string>
     */
    public function rules(): array {
        return [
            'ofe_id'                            => 'required|numeric',
            'length'                            => 'required|numeric',
            'columnaOrden'                      => 'required|string',
            'ordenDireccion'                    => 'required|string|in:asc,desc',
            'cdo_fecha_desde'                   => 'required|date_format:Y-m-d',
            'cdo_fecha_hasta'                   => 'required|date_format:Y-m-d|after_or_equal:cdo_fecha_desde',
            'pro_id'                            => 'nullable|array',
            'buscar'                            => 'nullable|string',
            'pag_anterior'                      => 'nullable|string',
            'pag_siguiente'                     => 'nullable|string',
            'excel'                             => 'nullable|boolean',
            'estado_validacion'                 => 'nullable|array',
            'cdo_origen'                        => 'nullable|string|in:RPA,MANUAL,NO-ELECTRONICO,CORREO',
            'cdo_clasificacion'                 => 'nullable|string|in:FC,NC,ND,DS,DS_NC',
            'estado'                            => 'nullable|string|in:ACTIVO,INACTIVO',
            'rfa_prefijo'                       => 'nullable|string|max:5',
            'cdo_consecutivo'                   => 'nullable|string|max:20',
        ];
    }
}