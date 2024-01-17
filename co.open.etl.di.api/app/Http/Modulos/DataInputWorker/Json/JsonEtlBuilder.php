<?php
namespace App\Http\Modulos\DataInputWorker\Json;

/**
 * Contructor del JSON necesario para registrar data en las tenant de openEtl.
 *
 * Class JsonEtlBuilder
 * @package App\Http\Modulos\DataInputWorker
 */
class JsonEtlBuilder
{
    /**
     * Contiene los arreglos.
     * @var JsonDocumentBuilder[]
     */
    private $jsonDocumentos = [];

    /**
     * Tipo de documento.
     *
     * @var string
     */
    private $docType;

    /**
     * Almacena el tipo de documentos.
     *
     * @var array
     */
    private $types = [];

    /**
     * Permite setear el tipo de documento que se esta procesando.
     *
     * @param string $docType
     * @return JsonEtlBuilder
     */
    public function setDocType(string $docType): JsonEtlBuilder
    {
        $this->docType = $docType;
        return $this;
    }

    /**
     * JsonEtlBuilder constructor.
     * 
     * @param string $docType Tipo del documento a procesar
     */
    public function __construct(string $docType){
        $this->docType = $docType;
    }

    /**
     * Agrega un nuevo documento a la colección de items a ser registrados para su procesamiento.
     * 
     * @param $documento
     */
    public function addDocument($documento) {
        $this->jsonDocumentos[] = $documento;
        $this->types[] = $this->docType;
    }

    /**
     * Retorna un objeto json que puede proccesar DataBuilder.
     *
     * @return false|string
     */
    public function build() {
        $nc    = [];
        $nd    = [];
        $fc    = [];
        $ds    = [];
        $ds_nc = [];
        foreach ($this->jsonDocumentos as $k =>  $item) {
            $documento = $item->buildDocumento($this->types[$k]);
            if ($documento['tde_codigo'] === '01' || $documento['tde_codigo'] === '02' || $documento['tde_codigo'] === '03' || $documento['tde_codigo'] === '04')
                $fc[] = $documento;
            elseif ($documento['tde_codigo'] == '91')
                $nc[] = $documento;
            elseif ($documento['tde_codigo'] == '92')
                $nd[] = $documento;
            elseif ($documento['tde_codigo'] == '05')
                $ds[] = $documento;
            elseif ($documento['tde_codigo'] == '95')
                $ds_nc[] = $documento;
        }

        $objetos = [];
        if (!empty($fc))
            $objetos['FC'] = $fc;
        if (!empty($nc))
            $objetos['NC'] = $nc;
        if (!empty($nd))
            $objetos['ND'] = $nd;
        if (!empty($ds))
            $objetos['DS'] = $ds;
        if (!empty($ds_nc))
            $objetos['DS_NC'] = $ds_nc;
        return json_encode(['documentos' => $objetos]);
    }
}
