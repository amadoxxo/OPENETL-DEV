<?php
namespace App\Http\Modulos\FirmarManager;

use Ramsey\Uuid\Uuid;

/**
 * Clase principal de firmado con libreria SBB.
 */
class Sbb {
    public function __construct () {
        \SBUtils\SetLicenseKey(env('SBB_KEY'));
    }
    
    /**
     * Firma Electrónica de Documentos XML.
     *
     * @param  string  $xml  XML en Base64
     * @param  string  $fechaHoraDocumento  Fecha y hora del documento
     * @param  string  $bd  Nombre de la BD del Ofe
     * @param  string  $certificado  Nombre del archivo del certificado firmante
     * @param  string  $password  Clave en base64 del certificado firmante
     * @param  string  $proceso Proceso sobre el cual se generarán los XML-UBL
     * @return  array  Contiene información de error y tiempo de procesamiento, o
     *                 String en Base64 con el Xml firmado y tiempo de procesamiento
     */
    public function firmarXml ($xml, $fechaHoraDocumento, $bd, $certificado, $password, $proceso = null) {
        // TimeZone por defecto
        date_default_timezone_set('America/Bogota');

        $uuid = Uuid::uuid4();

        // Carga el XML
        $xmlDocument = new \TElXMLDOMDocument();
        $streamIn    = new \TElMemoryStream;
        $buffer      = base64_decode($xml);
        $streamIn->Write($buffer, strlen($buffer));
        $streamIn->Position = 0;
        $xmlDocument->LoadFromStream($streamIn);

        $error_happened = false;
        try {
            // Detecta el formato de archivo del certificado
            // Valores válidos 2 o 3 que corresponden a P12/PFX o PEM
            $cert       = new \TElX509Certificate(null);
            $fileFormat = $cert->DetectCertFileFormat(config('variables_sistema.PATH_CERTIFICADOS') . '/' . $bd . '/' . $certificado);
            
            if($fileFormat == 2 || $fileFormat == 3) {
                // Inicia lectura del certificado de firmante
                //      Importante mencionar que no se utiliza la clase
                //      TFileStream toda vez que retrasa de manera asyncrona
                //      la obtención del contenido del archivo, lo que retrasa
                //      y produce resultados inesperados en adelante
                $contenidoCertificado = file_get_contents(config('variables_sistema.PATH_CERTIFICADOS') . '/' . $bd . '/' . $certificado);
                // Inicializa un stream
                $streamInCert = new \TElMemoryStream;
                // Escribe el contenido del archivo hacia el stream
                $streamInCert->Write($contenidoCertificado, strlen($contenidoCertificado));
                // Reestablece la posición del stream al inicio del mismo
                $streamInCert->Position = 0;
                // Inicializa 
                $SigningCertificates = new \TElMemoryCertStorage(null);
                if($fileFormat == 2) {
                    $storage = $SigningCertificates->LoadFromStreamPEM($streamInCert, base64_decode($password), 0);
                } elseif($fileFormat == 3) {
                    $storage = $SigningCertificates->LoadFromStreamPFX($streamInCert, base64_decode($password), 0);
                }

                if ($storage != 0) {
                    $error_happened = true;
                    $cert = null;
                    $error_details = "Error al cargar los certificados, Error: " . (string)$storage;
                    // Para los posibles códigos de error referirse a:
                    // https://www.secureblackbox.com/kb/help/ref_err_pkcs12errorcodes.html
                } else {
                  $cert = $SigningCertificates->get_Certificates($SigningCertificates->get_ChainCount()-1);

                  $fechaHoraDocumento = new \DateTime($fechaHoraDocumento);
                  $momentoActual      = new \DateTime('now');
                  $validoDesde        = $cert->get_ValidFrom();
                  $validoHasta        = $cert->get_ValidTo();

                  if (
                      $proceso == null &&
                      ($validoDesde > $fechaHoraDocumento || $fechaHoraDocumento > $validoHasta)
                  ) {
                      $error_happened = true;
                      $cert = null;
                      $error_details = 'La fecha del documento no se encuentra entre el rango de fecha válido del certificado firmante (válido desde ' . $validoDesde->format('Y-m-d H:i:s') .' hasta el ' . $validoHasta->format('Y-m-d H:i:s') . ')';
                  }

                  if (!$error_happened) {
                    // Valida el certificado
                    // El proceso verifica que el certificado no sea autofirmado, que todos los certificados de la cadena esten vigentes,
                    // que el almacenamiento del certificado sea correcto y que la cadena de certificados como un todo sea válida
                    $reason = 0;
                    $valido = $SigningCertificates->Validate($cert, $reason, true, $momentoActual);
                    switch ($valido) {
                        case 1:
                            $razon = 'El certificado es autofirmado';
                            break;
                        case 2:
                            $razon = 'El certificado no es válido, válido desde ' . $validoDesde->format('Y-m-d H:i:s') .' hasta el ' . $validoHasta->format('Y-m-d H:i:s') . ' - Debe verificar la vigencia de todos los certificados de la cadena firmante';
                            break;
                        case 3:
                            $razon = 'El certificado no fue validado debido a un error de almacenamiento del certificado';
                            break;
                        case 4:
                            $razon = 'La cadena de certificados no se validó porque, si bien el certificado en sí es válido, uno o más de los certificados de CA en la cadena tienen problemas de validación';
                            break;
                    }

                    if ($valido != 0) {
                        $error_happened = true;
                        $cert = null;
                        $error_details = $razon;
                    }
                  }
                }
            } else {
                $error_happened = true;
                $cert = null;
                $error_details = "Formato de archivo del certificado no reconocido, Tipo de archivo: " . (string)$fileFormat;
            }
        } catch(\SBException $e) {
            $error_happened = true;
            $cert = null;
            $error_details = "Carga del certificado falló con el mensaje: " . $e->getErrorMessage();
        }

        if (!$error_happened and (!is_null($cert))) {
            $polFile = config('variables_sistema.PATH_CERTIFICADOS') . '/dian/politicadefirma/v2/politicadefirmav2.pdf';
            // De acuerdo al al tipo de algoritmo de firma en el certificado
            // Se definen las constantes de encripción que se utilizarán
            switch($cert->get_SignatureAlgorithm()) {
                case 8:
                    $TElXMLSignatureMethod = \TElXMLSignatureMethod::xsmRSA_SHA256;
                    $TElXMLDigestMethod    = \TElXMLDigestMethod::xdmSHA256;
                    $shaPolFile            = hash_file('sha256', $polFile, true);
                    break;
                
                case 9:
                    $TElXMLSignatureMethod = \TElXMLSignatureMethod::xsmRSA_SHA384;
                    $TElXMLDigestMethod    = \TElXMLDigestMethod::xdmSHA384;
                    $shaPolFile            = hash_file('sha384', $polFile, true);
                    break;
                
                case 10:
                    $TElXMLSignatureMethod = \TElXMLSignatureMethod::xsmRSA_SHA512;
                    $TElXMLDigestMethod    = \TElXMLDigestMethod::xdmSHA512;
                    $shaPolFile            = hash_file('sha512', $polFile, true);
                    break;

                default:
                    $errorAlgoritmo = true;
                    $error_happened = true;
                    $error_details  = "Algoritmo de Firma del certificado digital no previsto por la DIAN [" . $cert->get_SignatureAlgorithm() . "]";
                    break;
            }

            if(!isset($errorAlgoritmo)) {
                // Permite no convertir el nodo OID a 'E'
                \SBXMLSec\RDNDescriptorMap()->ClearOID(\SBStrUtils\StrToOID('1.2.840.113549.1.9.1'));

                // Version XAdES
                $XAdESSigner               = new \TElXAdESSigner(NULL);
                $XAdESSigner->XAdESVersion = \TSBXAdESVersion::XAdES_v1_4_1;

                // Signingtime inicialmente en UTC y modificado más adelante de acuerdo al Timezone
                $XAdESSigner->SigningTime = \SBUtils\UTCNow();

                // Signature Policy
                $XAdESSigner->PolicyId->SigPolicyId->Identifier     = "https://facturaelectronica.dian.gov.co/politicadefirma/v2/politicadefirmav2.pdf";
                $XAdESSigner->PolicyId->SigPolicyId->Description    = 'Política de firma para facturas electrónicas de la República de Colombia.';
                $XAdESSigner->PolicyId->SigPolicyHash->DigestMethod = \SBXMLSec\DigestMethodToURI($TElXMLDigestMethod);
                $XAdESSigner->PolicyId->SigPolicyHash->DigestValue  = $shaPolFile;

                $XAdESSigner->SigningCertificates             = $SigningCertificates;
                $XAdESSigner->SigningCertificatesDigestMethod = $TElXMLDigestMethod;

                /**
                 * Se debe utilizar 'supplier' cuando la firma del documento la realiza el Obligado a Facturar
                 * Se debe utilizar 'third party' cuando la firma la realiza un Proveedor Tecnológico que en su caso, actué en su nombre
                */
                $XAdESSigner->Included = \TElXAdESIncludedProperties::xipSignerRole;
                $XAdESSigner->SignerRole->ClaimedRoles->AddText($XAdESSigner->XAdESVersion, $xmlDocument, 'supplier');
                
                // Genera los transformadores para XAdES-EPES
                $XAdESSigner->Generate(\TSBXAdESForm::XAdES_EPES);

                // Obtiene fecha y hora del sistema para la zona horario America/Bogota
                $dateObj = \DateTime::createFromFormat('U.u', microtime(TRUE));
                $msg     = $dateObj->format('u');
                $msg    /= 1000;
                $dateObj->setTimeZone(new \DateTimeZone('America/Bogota'));

                $dateTime = $dateObj->format('Y-m-d').'T'.$dateObj->format('H:i:s.').intval($msg).'-05:00';
                $XAdESSigner->QualifyingProperties->SignedProperties->SignedSignatureProperties->SignedTime = $dateTime;
                $XAdESSigner->QualifyingProperties->SignedProperties->ID = "xmldsig-" . $uuid . "-signedprops";
                
                $XAdESSigner->QualifyingProperties->XAdESv141Prefix = '';
                $XAdESSigner->QualifyingProperties->Target = '#xmldsig-'.$uuid;
                
                $X509KeyInfoData = new \TElXMLKeyInfoX509Data(false);
                $X509KeyInfoData->IncludeKeyValue = true;
                $X509KeyInfoData->IncludeDataParams = \TElXMLKeyInfoX509DataParams::xkidX509Certificate;
                $X509KeyInfoData->IncludeDataParams = 8;
                $X509KeyInfoData->IncludeKeyValue = false;
                $X509KeyInfoData->Certificate = $cert;

                // Obtiene el tipo de documento: Invoice - DebitNote - CreditNote
                $docType = $xmlDocument->DocumentElement->get_LocalName();

                // Namespaces que permiten ubicar la firma en el lugar correcto
                $nsMap = new \TElXMLNamespaceMap();
                $nsMap->AddNamespace('ns', 'urn:oasis:names:specification:ubl:schema:xsd:'.$docType.'-2');
                $nsMap->AddNamespace('ext', 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2');
                if($proceso == null)
                    $nodeSet = $xmlDocument->SelectNodes('/ns:'.$docType.'/ext:UBLExtensions/ext:UBLExtension[2]/ext:ExtensionContent', $nsMap);
                else
                    $nodeSet = $xmlDocument->SelectNodes('/ns:'.$docType.'/ext:UBLExtensions/ext:UBLExtension[2]/ext:ExtensionContent', $nsMap);
                
                if ($nodeSet->Count > 0) {
                    $Signer                         = new \TElXMLSigner(NULL);
                    $Signer->XAdESProcessor         = $XAdESSigner;
                    $Signer->SignatureMethodType    = \TElXMLSigMethodType::xmtSig;
                    $Signer->SignatureMethod        = $TElXMLSignatureMethod;
                    $Signer->CanonicalizationMethod = \TElXMLCanonicalizationMethod::xcmCanon;
                    $Signer->IncludeKey             = true;

                    // Referencias
                    $k   = $Signer->References->Add();
                    $Ref = $Signer->References->get_Reference($k);
                    $Ref->DigestMethod = $TElXMLDigestMethod;
                    $Ref->ID  = 'xmldsig-'.$uuid.'-ref0';
                    $Ref->URI = '';
                    $Ref->URINode = $xmlDocument->DocumentElement;
                    $Ref->TransformChain->AddEnvelopedSignatureTransform();
                    
                    $Signer->UpdateReferencesDigest();

                    // Keyinfo de las referencias
                    $k   = $Signer->References->Add();
                    $Ref = $Signer->References->get_Reference($k);
                    $Ref->DigestMethod = $TElXMLDigestMethod;
                    $Ref->URI = '#xmldsig-'.$uuid.'-keyinfo';

                    $Signer->KeyData = $X509KeyInfoData;
                
                    // Generación de la firma
                    $Signer->GenerateSignature();
                    $Signer->Signature->SignedInfo->SigPropRef->DigestMethod = $TElXMLDigestMethod;

                    $Signer->Signature->ID = 'xmldsig-'.$uuid;
                    $Signer->Signature->KeyInfo->ID = 'xmldsig-'.$uuid.'-keyinfo';
                    $Signer->Signature->SignatureValue->ID = 'xmldsig-'.$uuid.'-sigvalue';
                    $signatureNode = $nodeSet->get_Node(0)->CastTo();

                    // Graba el enveloped signature
                    $Signer->SaveEnveloped($signatureNode);
                    $signatureValue = base64_encode($Signer->Signature->SignatureValue->get_Value());
                } else {
                    $error_happened = true;
                    $error_details = " El XML no cuenta con el nodo ext:UBLExtension donde va la firma";
                }
            }
        }

        if ($error_happened) {
            return [
                'error'                    => $error_details,
                'xmlFirmado'               => ''
            ];
        } else {
            if($proceso == 'recepcion') {
                // Retorna el XML firmado
                $streamOut = new \TElMemoryStream;
                $streamOut->Position = 0;
                $xmlDocument->SaveToStream($streamOut);
                $xmlFirmado = \SBStreams\StreamReadAll($streamOut);
            } else {
                $Codec           = new TElXMLUTF8Codec_UpperCase();
                $Codec->WriteBOM = false;

                // Retorna el XML firmado
                $streamOut = new \TElMemoryStream;
                $streamOut->Position = 0;
                $xmlDocument->SaveToStream($streamOut);
                $xmlFirmado = \SBStreams\StreamReadAll($streamOut);
            }

            return [
                'error'      => '',
                'xmlFirmado' => $xmlFirmado
            ];
        }
    }
}

/**
 * Sobreescribe el Codec UTF8 de SBB.
 */
class TElXMLUTF8Codec_UpperCase extends \TElXMLUTF8Codec {
    public function __construct() {
        parent::__construct();
    }

    public function GetName() {
        return strtoupper(parent::get_Name());
    }
}
