<?php

namespace App\Http\Modulos\Recepcion\Particionamiento\Requests;

use Illuminate\Http\JsonResponse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class ListaDocumentosRecibidosRequest extends FormRequest {
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

        if(isset($this->estado_dian))
            $this->merge([
                'estado_dian' => array_map('strtoupper', $this->estado_dian)
            ]);

        if(isset($this->estado_eventos_dian))
            $this->merge([
                'estado_eventos_dian' => array_map('strtoupper', $this->estado_eventos_dian)
            ]);

        if(isset($this->resultado_eventos_dian))
            $this->merge([
                'resultado_eventos_dian' => strtoupper($this->resultado_eventos_dian)
            ]);

        if(isset($this->transmision_erp))
            $this->merge([
                'transmision_erp' => array_map('strtoupper', $this->transmision_erp)
            ]);

        if(isset($this->transmision_opencomex))
            $this->merge([
                'transmision_opencomex' => strtoupper($this->transmision_opencomex)
            ]);
    }

    /**
     * Retona las reglas de validación que aplican a la petición.
     *
     * @return array<string>, \Illuminate\Contracts\Validation\Rule|array|string>
     */
    public function rules(): array {
        return [
            'buscar'                            => 'nullable|string',
            'length'                            => 'required|numeric',
            'pag_siguiente'                     => 'nullable|string',
            'pag_anterior'                      => 'nullable|string',
            'columnaOrden'                      => 'required|string',
            'ordenDireccion'                    => 'required|string|in:asc,desc',
            'cdo_fecha_desde'                   => 'required|date_format:Y-m-d',
            'cdo_fecha_hasta'                   => 'required|date_format:Y-m-d|after_or_equal:cdo_fecha_desde',
            'ofe_id'                            => 'required|numeric',
            'pro_id'                            => 'nullable|array',
            'cdo_lote'                          => 'nullable|string',
            'cdo_cufe'                          => 'nullable|string',
            'cdo_origen'                        => 'nullable|string|in:RPA,INTEGRACION,MANUAL,CORREO,NO-ELECTRONICO',
            'cdo_clasificacion'                 => 'nullable|string|in:FC,NC,ND,DS,DS_NC',
            'estado'                            => 'nullable|string|in:ACTIVO,INACTIVO',
            'rfa_prefijo'                       => 'nullable|string|max:5',
            'cdo_consecutivo'                   => 'nullable|string|max:20',
            'forma_pago'                        => 'nullable|numeric|min:1',
            'cdo_fecha_validacion_dian_desde'   => 'nullable|date_format:Y-m-d',
            'cdo_fecha_validacion_dian_hasta'   => 'nullable|date_format:Y-m-d|after_or_equal:cdo_fecha_validacion_dian_desde',
            'estado_dian'                       => 'nullable|array|in:ENPROCESO,APROBADO,CONNOTIFICACION,RECHAZADO',
            'estado_acuse_recibo'               => 'nullable|string|in:SI,NO',
            'estado_recibo_bien'                => 'nullable|string|in:SI,NO',
            'estado_eventos_dian'               => 'nullable|array|in:SINESTADO,ACEPTACION,ACEPTACIONT,RECHAZO',
            'resultado_eventos_dian'            => 'nullable|string|in:EXITOSO,FALLIDO',
            'transmision_erp'                   => 'nullable|array',
            'transmision_opencomex'             => 'nullable|string|in:SINESTADO,EXITOSO,FALLIDO',
            'filtro_grupos_trabajo'             => 'nullable|string',
            'filtro_grupos_trabajo_usuario'     => 'nullable|numeric',
            'cdo_usuario_responsable_recibidos' => 'nullable|array',
            'estado_validacion'                 => 'nullable|array',
            'campo_validacion'                  => 'nullable|string',
            'valor_campo_validacion'            => 'nullable|string'
        ];
    }
}
