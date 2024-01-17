<?php
namespace App\Http\Traits;

use App\Http\Modulos\Recepcion\Configuracion\Proveedores\ConfiguracionProveedor;
use App\Http\Modulos\Parametros\XpathDocumentosElectronicos\ParametrosXpathDocumentoElectronico;
use App\Http\Modulos\Configuracion\AdministracionRecepcionErp\ConfiguracionAdministracionRecepcionErp;
use App\Http\Modulos\Parametros\XpathDocumentosElectronicos\ParametrosXpathDocumentoElectronicoTenant;
use App\Http\Modulos\Configuracion\GruposTrabajo\GruposTrabajoUsuarios\ConfiguracionGrupoTrabajoUsuario;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

/**
 * Trait de Recepción
 *
 * Trait RecepcionTrait
 * @package App\Http\Traits
 */
trait RecepcionTrait {
    /**
     * Tipo del documento del XML en procesamiento.
     *
     * @var string
     */
    public $tipoDocumentoOriginal = '';

    /**
     * Obiene los grupos de usuario de usuario del usuario autenticado.
     *
     * @param ConfiguracionObligadoFacturarElectronicamente $ofe Instancia del OFE
     * @param bool $usuarioGestor Indica cuando se debe tener en cuenta que se trate de un usuario gestor
     * @param bool $usuarioValidador Indica cuando se debe tener en cuenta que se trate de un usuario validador
     * @return array
     */
    public function getGruposTrabajoUsuarioAutenticado(ConfiguracionObligadoFacturarElectronicamente $ofe, bool $usuarioGestor = false, bool $usuarioValidador = false): array {
        return ConfiguracionGrupoTrabajoUsuario::select(['gtr_id'])
            ->where('usu_id', auth()->user()->usu_id)
            ->when($usuarioGestor && $ofe->ofe_recepcion_fnc_activo == 'SI', function($query) {
                return $query->where('gtu_usuario_gestor', 'SI');
            })
            ->when($usuarioValidador && $ofe->ofe_recepcion_fnc_activo == 'SI', function($query) {
                return $query->where('gtu_usuario_validador', 'SI');
            })
            ->where('estado', 'ACTIVO')
            ->get()
            ->pluck('gtr_id')
            ->toArray();
    }

    /**
     * Permite filtrar los documentos electrónicos teniendo en cuenta la configuración de grupos de trabajo a nivel de usuario autenticado y proveedores.
     * 
     * Si el usuario autenticado esta configurado en algún grupo de trabajo, solamente se deben listar documentos electrónicos de los proveedores asociados con ese mismo grupo o grupos de trabajo
     * Si el usuario autenticado no esta configurado en ningún grupo de trabajo, se verifica ssi el usuario está relacionado directamente con algún proveedor para mostrar solamente documentos de esos proveedores
     * Si no se da ninguna de las anteriores condiciones, el usuario autenticado debe poder ver todos los documentos electrónicos de todos los proveedores
     *
     * @param Builder $query Consulta que está en procesamiento
     * @param int $ofeId ID del OFE para el cual se está haciendo la consulta
     * @param bool $usuarioGestor Indica cuando se debe tener en cuenta que se trate de un usuario gestor
     * @param bool $usuarioValidador Indica cuando se debe tener en cuenta que se trate de un usuario validador
     * @return Builder
     */
    public function verificaRelacionUsuarioProveedor($query, int $ofeId, bool $usuarioGestor = false, bool $usuarioValidador = false) {
        $user = auth()->user();

        $ofe = ConfiguracionObligadoFacturarElectronicamente::select('ofe_recepcion_fnc_activo')
            ->where('ofe_id', $ofeId)
            ->first();

        $gruposTrabajoUsuario = $this->getGruposTrabajoUsuarioAutenticado($ofe, $usuarioGestor, $usuarioValidador);

        if(!empty($gruposTrabajoUsuario)) {
            $query->whereHas('getProveedorGruposTrabajo', function($gtrProveedor) use ($gruposTrabajoUsuario) {
                $gtrProveedor->whereIn('gtr_id', $gruposTrabajoUsuario)
                    ->where('estado', 'ACTIVO');
            });
        } else {
            // Verifica si el usuario autenticado esta asociado con uno o varios proveedores para mostrar solo los documentos de ellos, de lo contrario mostrar los documentos de todos los proveedores en la BD
            $consultaProveedoresUsuario = ConfiguracionProveedor::select(['pro_id'])
                ->where('ofe_id', $ofeId)
                ->where('pro_usuarios_recepcion', 'like', '%"' . $user->usu_identificacion . '"%')
                ->where('estado', 'ACTIVO')
                ->get();
                
            if($consultaProveedoresUsuario->count() > 0)
                $query->where('ofe_id', $ofeId)
                    ->where('pro_usuarios_recepcion', 'like', '%"' . $user->usu_identificacion . '"%');
        }

        return $query;
    }

    /**
     * Permite validar las reglas que aplican para un documento electrónico dependiendo la acción solicitada.
     * 
     * @param string $accion Acción de la regla
     * @param int    $ofeId ID del Oferente
     * @param string $aplicaPara Indica la clasificación del documento
     * @param string $xmlUbl xml-ubl del documento
     * @return array Aplica regla para el documento
     */
    public function verificaReglasXpath(string $accion, int $ofeId, string $aplicaPara, string $xmlUbl) {
        $arrRespuesta = [];
        $arrNotificar = [];

        $xml    = base64_decode($xmlUbl);
        $xmlUbl = $this->definirTipoDocumento($xml);
        if($this->tipoDocumentoOriginal == 'AttachedDocument') {
            // Obtiene el xml-ubl dentro del attached document para poder continuar con el procesamiento en este método
            $xml    = $this->getValueByXpath($xmlUbl, "//{$this->tipoDocumentoOriginal}/cac:Attachment/cac:ExternalReference/cbc:Description");
            $xmlUbl = $this->definirTipoDocumento((string) $xml[0]);
        }

        $tipoDocumento = '';
        switch($aplicaPara) {
            case 'FC':
                $tipoDocumento = 'Invoice';
                break;
            case 'NC':
                $tipoDocumento = 'CreditNote';
                break;
            case 'ND':
                $tipoDocumento = 'DebitNote';
                break;
        }

        $arrRespuesta['aplica_regla'] = false;
        $arrRespuesta['reglas']       = [];
        // Se obtiene las reglas que aplican para el documento en proceso
        $gruposReglas = ConfiguracionAdministracionRecepcionErp::select([
                'ate_grupo', 
                'ate_descripcion',
                'ate_deben_aplica', 
                'ate_accion', 
                'xde_accion_id_main', 
                'xde_accion_id_tenant', 
                'ate_accion_titulo'
            ])
            ->where('ofe_id', $ofeId)
            ->where('ate_accion', $accion)
            ->where('ate_aplica_para', 'like', '%'.$aplicaPara.'%')
            ->where('estado', 'ACTIVO')
            ->groupBy('ate_grupo')
            ->get();

        $aplicaRegla = 0;
        foreach ($gruposReglas as $grupo) {
            $countRegla = 0;
            $countAplicaRegla = 0;
            // Se recorren las reglas por grupos
            $reglas = ConfiguracionAdministracionRecepcionErp::where('ate_grupo', $grupo->ate_grupo)
                ->get()
                ->map(function($regla) use ($xmlUbl, $tipoDocumento, &$countRegla, &$countAplicaRegla) {
                    // Se obtiene la clase de Main o Tenant
                    if ($regla->xde_id_main != "" && $regla->xde_id_main != null) {
                        $idXpath = $regla->xde_id_main;
                        $class   = ParametrosXpathDocumentoElectronico::class;
                    } else {
                        $idXpath = $regla->xde_id_tenant;
                        $class   = ParametrosXpathDocumentoElectronicoTenant::class;
                    }

                    $xPathDocumento = $class::select(['xde_id', 'xde_descripcion', 'xde_xpath'])
                        ->where('xde_id', $idXpath)
                        ->where('estado', 'ACTIVO')
                        ->first();

                    // Se obtiene la información del XPath en el XML
                    $valuesXpath = $this->getValueByXpath($xmlUbl, "//{$tipoDocumento}{$xPathDocumento->xde_xpath}");
                    $regla->ate_valor = strtolower($regla->ate_valor);
					
                    if (!empty($valuesXpath)) {
                        foreach ($valuesXpath as $value) {
                            $valorXpath = strtolower($value);
                            // Validar la condición de la regla
                            switch($regla->ate_condicion) {
                                case 'IGUAL':
                                    if ($regla->ate_valor == $valorXpath)
                                        $countAplicaRegla++;
                                    break;
                                case 'NO_ES_IGUAL':
                                    if ($regla->ate_valor != $valorXpath)
                                        $countAplicaRegla++;
                                    break;
                                case 'MENOR':
                                    if ($valorXpath < $regla->ate_valor)
                                        $countAplicaRegla++;
                                    break;
                                case 'MENOR_O_IGUAL':
                                    if ($valorXpath <= $regla->ate_valor)
                                        $countAplicaRegla++;
                                    break;
                                case 'MAYOR':
                                    if ($valorXpath > $regla->ate_valor)
                                        $countAplicaRegla++;
                                    break;
                                case 'MAYOR_O_IGUAL':
                                    if ($valorXpath >= $regla->ate_valor)
                                        $countAplicaRegla++;
                                    break;
                                case 'CONTENGA':
                                    if (str_contains($valorXpath, $regla->ate_valor))
                                        $countAplicaRegla++;
                                    break;
                                case 'NO_CONTENGA':
                                    if (!str_contains($valorXpath, $regla->ate_valor))
                                        $countAplicaRegla++;
                                    break;
                                case 'COMIENZA':
                                    $intCadena = strlen($regla->ate_valor);
                                    if (substr($valorXpath, 0, $intCadena) == $regla->ate_valor)
                                        $countAplicaRegla++;
                                    break;
                                case 'NO_COMIENZA':
                                    $intCadena = strlen($regla->ate_valor);
                                    if (substr($valorXpath, 0, $intCadena) != $regla->ate_valor)
                                        $countAplicaRegla++;
                                    break;
                                case 'TERMINA':
                                    $intCadena = strlen($regla->ate_valor);
                                    if (substr($valorXpath, -$intCadena) == $regla->ate_valor)
                                        $countAplicaRegla++;
                                    break;
                                case 'NO_TERMINA':
                                    $intCadena = strlen($regla->ate_valor);
                                    if (substr($valorXpath, -$intCadena) != $regla->ate_valor)
                                        $countAplicaRegla++;
                                    break;
                            }
                        }
                    }
                    $countRegla++;
                });

            if ($grupo->ate_accion == 'NOTIFICAR') {
                if ($grupo->xde_accion_id_main != "" && $grupo->xde_accion_id_main != null) {
                    $idAccionXpath = $grupo->xde_accion_id_main;
                    $class = ParametrosXpathDocumentoElectronico::class;
                } else {
                    $idAccionXpath = $grupo->xde_accion_id_tenant;
                    $class = ParametrosXpathDocumentoElectronicoTenant::class;
                }

                $accionXpathDocumento = $class::select(['xde_id', 'xde_xpath'])
                    ->where('xde_id', $idAccionXpath)
                    ->where('estado', 'ACTIVO')
                    ->first();

                if ($accionXpathDocumento) {
                    $count = count($arrNotificar);
                    $arrNotificar[$count]['accion_titulo'] = $grupo->ate_accion_titulo;
                    $arrNotificar[$count]['accion_valor']  = $this->getValueByXpath($xmlUbl, "//{$tipoDocumento}/{$accionXpathDocumento->xde_xpath}");
                }
            }

            if (
            	($grupo->ate_deben_aplica == 'TODAS' && $countAplicaRegla == $countRegla) || 
                ($grupo->ate_deben_aplica == 'ALGUNA' && $countAplicaRegla > 0)
            ) {
                if ($grupo->ate_accion != 'NOTIFICAR')
                    $arrRespuesta['regla'][] = $grupo->ate_descripcion;
                $aplicaRegla++;
            }
        }

        if ($accion == 'NOTIFICAR')
            $arrRespuesta['notificar'] = $arrNotificar;

        if ($aplicaRegla > 0)
            $arrRespuesta['aplica_regla'] = true;

        return $arrRespuesta;
    }

    /**
     * Obtiene el valor de un nodo dado su XPATH.
     *
     * @param SimpleXMLElement $xml XML-UBL en procesamiento
     * @param string $xpath xPath desde donde se debe obtener data
     * @return array Array con el valor de los Xpath que se encontraron en el XML
     */
    public function getValueByXpath($xml, string $xpath) {
        $objetos = $xml->xpath($xpath);
        if ($objetos) {
            $xpath = [];
            foreach ($objetos as $obj) {
                $xpath[] = trim($obj[0]);
            }
            return $xpath;
        }
        return [];
    }

    /**
     * Permite obtener el tipo de documento electrónico que se está procesando y retorna la estructura del XML.
     *
     * @param string $xml Xml-Ubl en procesamiento
     * @return object $xmlUbl Estrutcura del XML para poder iterar como una colección de matrices y objetos
     */
    private function definirTipoDocumento($xml) {
        $xmlUbl = str_replace('xmlns=', 'ns=', $xml);
        $xmlUbl = new \SimpleXMLElement($xmlUbl, LIBXML_NOERROR);
        $this->tipoDocumentoOriginal = $xmlUbl->getName();

        return $xmlUbl;
    }

    /**
     * Permite Eliminar los namespace no estandar del xml.
     *
     * @param string  $xml Xml-Ubl en procesamiento
     * @return string $xmlUbl con namespace eliminados 
     */
    private function eliminarNsNoEstandar($xml) {
        // Definiendo los namespace estandar
        $namespaceEstandar = [
            'cac',
            'cbc',
            'ext',
            'sts',
            'xades',
            'xades141',
            'xsi',
            'ds'
        ];

        // Extrayendo los namespace del xml
        $xmlUbl = str_replace('xmlns=', 'ns=', $xml);
        $xmlUbl = new \SimpleXMLElement($xmlUbl, LIBXML_NOERROR);
        $namespaces = $xmlUbl->getNameSpaces(true);

        //Eliminando los namespace no estandar
        foreach ($namespaces as $key => $value) {
            if (!in_array($key, $namespaceEstandar)) {
                $xml = str_replace("<" . $key . ':', '<', $xml);
                $xml = str_replace("</" . $key . ':', '</', $xml);
            }
        }

        return $xml;
    }

    /**
     * Obtiene el valor de un nodo dado su XPATH.
     *
     * @param SimpleXMLElement $xml XML-UBL en procesamiento
     * @param string $xpath xPath desde donde se debe obtener data
     * @return array Array toda la informacion del Xpath
     */
    public function getAllByXpath($xml, string $xpath) {
        $objetos = $xml->xpath($xpath);
        if ($objetos) {
            $xpath = [];
            $item = 0;
            foreach ($objetos as $obj) {
                foreach ((array) $obj as $key => $value) {
                    if (is_array($value)) {
                        $xpath[$item][$key] = $value;
                    } else {
                        $xpath[$item][$key] = trim($value);
                    }
                }
                $item++;
            }
            return $xpath;
        }
        return [];
    }

    /**
     * Registra los namespaces obligatorios de un documento electrónico.
     * 
     * En el proceso se tiene en cuenta cualquier otro namespace que pudiera existir.
     *
     * @return void
     */
    public function registrarNamespaces(array $namespaces, object &$xml) {
        if(array_key_exists('cac', $namespaces))
            $xml->registerXPathNamespace('cac', $namespaces['cac']);
        else
            $xml->registerXPathNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');

        if(array_key_exists('cbc', $namespaces))
            $xml->registerXPathNamespace('cbc', $namespaces['cbc']);
        else
            $xml->registerXPathNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');

        if(array_key_exists('ext', $namespaces))
            $xml->registerXPathNamespace('ext', $namespaces['ext']);
        else
            $xml->registerXPathNamespace('ext', 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2');

        if(array_key_exists('sts', $namespaces))
            $xml->registerXPathNamespace('sts', $namespaces['sts']);
        else
            $xml->registerXPathNamespace('sts', 'dian:gov:co:facturaelectronica:Structures-2-1');

        if(array_key_exists('xades', $namespaces))
            $xml->registerXPathNamespace('xades', $namespaces['xades']);
        else
            $xml->registerXPathNamespace('xades', 'http://uri.etsi.org/01903/v1.3.2#');

        if(array_key_exists('xades141', $namespaces))
            $xml->registerXPathNamespace('xades141', $namespaces['xades141']);
        else
            $xml->registerXPathNamespace('xades141', 'http://uri.etsi.org/01903/v1.4.1#');

        if(array_key_exists('ds', $namespaces))
            $xml->registerXPathNamespace('ds', $namespaces['ds']);
        else
            $xml->registerXPathNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');

        if(array_key_exists('xsi', $namespaces))
            $xml->registerXPathNamespace('xsi', $namespaces['xsi']);
        else
            $xml->registerXPathNamespace('xsi', 'http://www.w3.org/2001/XMLSchema-instance');    
    }
}

