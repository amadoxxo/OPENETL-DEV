<?php
namespace App\Http\Modulos\NominaElectronica;

use DateTime;
use DateTimeZone;
use Ramsey\Uuid\Uuid;
use App\Http\Modulos\NominaElectronica\DataInput;

class DataBuilder extends DataInput  {
    /**
     * Json que contiene la data de entrada.
     *
     * @var mixed
     */
    protected $data;

    /**
     * Usuario autenticado para el proceso.
     *
     * @var int
     */
    protected $usu_id;

    /**
     * Almacena el tipo de documentos que contiene el array.
     *
     * @var string
     */
    public $docType = '';

    /**
     * Define el tipo de origen de la carga del documento.
     *
     * @var string
     */
    protected $origen;

    /**
     * Lista de documentos que pueden ser registrados.
     *
     * @var array
     */
    protected $successful = [];

    /**
     * Lista de documentos que no pueden ser registrados.
     *
     * @var array
     */
    protected $failure = [];

    /**
     * Lista con errores encontrados.
     *
     * @var array
     */
    public $errors = [];

    /**
     * Lista documentos procesados
     *
     * @var array
     */
    protected $procesados = [];

    /**
     * Número de lote de procesamiento.
     *
     * @var string
     */
    protected $lote;

    /**
     * Array de empleadores
     *
     * @var array
     */
    public $empleadores = [];

    /**
     * Array de trabajadores
     *
     * @var array
     */
    public $trabajadores = [];

    /**
     * Array de países
     *
     * @var array
     */
    public $paises = [];

    /**
     * Array de departamentos
     *
     * @var array
     */
    public $departamentos = [];

    /**
     * Array de municipios
     *
     * @var array
     */
    public $municipios = [];

    /**
     * DataBuilder constructor.
     *
     * @param int $usu_id
     * @param mixed $data Datos a validar y registrar
     * @param string $origen Indica la procedencia de la data (API - MANUAL - RPA)
     */
    public function __construct($usu_id, $data, $origen) {
        parent::__construct();

        $this->usu_id = $usu_id;
        $this->data   = $data;
        $this->origen = $origen;
        $this->buildLote();
    }

    /**
     * Contruye el lote de procesamiento de información.
     *
     * @throws \Exception
     */
    private function buildLote() {
        $uuid       = Uuid::uuid4();
        $dateObj    = DateTime::createFromFormat('U.u', microtime(TRUE));
        $msg        = $dateObj->format('u');
        $msg        /= 1000;
        $dateObj->setTimeZone(new DateTimeZone('America/Bogota'));
        $dateTime   = $dateObj->format('YmdHis').intval($msg);
        $this->lote = $dateTime . '_' . $uuid->toString();
    }

    /**
     * Procesammiento de la data recibida.
     *
     * @return array Resultado del procesamiento
     */
    public function procesar() {
        $this->validacionesNominaElectronica();
        $this->guardarDocumentosNominaElectronica();

        return [
            'resultado' =>  [
                'message'               => 'Bloque de informacion procesado bajo el lote ' . $this->lote,
                'lote'                  => $this->lote,
                'documentos_procesados' => $this->procesados,
                'documentos_fallidos'   => $this->failure
            ],
            'codigo_http' => !empty($this->procesados) ? 201 : 200
        ];
    }
}
