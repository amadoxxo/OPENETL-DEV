<?php


namespace App\Http\Modulos\DataInputWorker;

/**
 * Constantes de uso general para validar el proceso de inserción.
 *
 * Class ConstantsDataInput
 * @package App\Http\Modulos\DataInputWorker
 */
class ConstantsDataInput {

    public const codigosValidos = ['01', '02', '03', '04', '05', '91', '92', '95'];

    public const ROOT  = 'documentos';
    public const NC    = 'NC';
    public const ND    = 'ND';
    public const FC    = 'FC';
    public const DS    = 'DS';
    public const DS_NC = 'DS_NC';
    public const NC_ND = 'NC_ND'; // Se determina por el tipo de documento a la final
    public const DN    = 'DN';

    /*
     * Sección de mensaje de errores
     */
    public const NOT_DOCUMENT_ROOT   = 'No existe la llave documentos';
    public const NOT_TYPE_ALLOWED    = 'No existe una clave FC, NC, ND, DS o DS_NC';
    public const NOT_TYPE_ALLOWED_DN = 'No existe una clave DN';
    public const DOCS_ARE_NOT_ARRAY  = 'El campo %s debe ser un arreglo de documentos';
}
