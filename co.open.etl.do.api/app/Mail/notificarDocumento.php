<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class notificarDocumento extends Mailable {
    use Queueable, SerializesModels;

    // Propiedad en donde se almacenan los valores que serán insertados en la plantilla del correo
    public $registro;
    // Propiedad que puede contener como adjuntos el PDF y el XML cuando el adquirente esta marcado para ello
    public $adjuntos;
    // Propiedad en donde se almacena el Nit del Ofe
    // Importante para poder encadenar la vista Blade que corresponda
    public $nit;
    // Propiedad en donde se almacena el dígito de verificación del Ofe
    public $dv;
    // Propiedad en donde se almacena el nombre del remitente (Ofe)
    public $remite;
    // Propiedad en donde se almacena el email del remitente
    public $emailRemite;
    // Propiedad en donde se almacena la configuración personalizada del asunto del correo
    public $asunto;
    // Propiedad en donde se almacena el código del tipo de documento electrónico
    public $tipoDocumentoElectronico;
    // Propiedad en donde se almacena el nombre comercial del OFE (remitente)
    public $nombreComercial;
    // Propiedad en donde se almacena el correo de autorespuesta
    public $correoAutorespuesta;
    // Propiedad en donde se almacena la ruta a la plantilla Blade
    public $rutaBlade;
    // Propiedad en donde se almacena el Set de Configuración de AWS SES
    public $awsSesConfigurationSet;

    public function __construct($registro, $adjuntos, $nit, $dv, $remite, $emailRemite, $asunto, $tipoDocumentoElectronico, $nombreComercial, $correoAutorespuesta = null, $rutaBlade, $awsSesConfigurationSet) {
        $this->registro                 = $registro;
        $this->adjuntos                 = $adjuntos;
        $this->nit                      = $nit;
        $this->dv                       = $dv;
        $this->remite                   = $remite;
        $this->emailRemite              = $emailRemite;
        $this->asunto                   = $asunto;
        $this->tipoDocumentoElectronico = $tipoDocumentoElectronico;
        $this->nombreComercial          = $nombreComercial;
        $this->correoAutorespuesta      = $correoAutorespuesta;
        $this->rutaBlade                = $rutaBlade;
        $this->awsSesConfigurationSet   = $awsSesConfigurationSet;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build() {
        $prefijo = ($this->registro['rfa_prefijo'] != null && $this->registro['rfa_prefijo'] != '') ? $this->registro['rfa_prefijo'] : '';
        $subject = $this->nit . ';' . 
            $this->remite . ';' . 
            $prefijo . $this->registro['consecutivo'] . ';' . 
            $this->tipoDocumentoElectronico . ';' . 
            ($this->nombreComercial != '' ? $this->nombreComercial : '');

        if($this->asunto != null && $this->asunto != '' && $this->asunto != 'null') {
            $subject .= ';' . $this->asunto;
        }

        // Aplica a DHL Global y DHL Aduanas
        if(array_key_exists('documento_transporte', $this->registro) && $this->registro['documento_transporte'] != '') {
            $subject .= ';Documento de Transporte No. ' . $this->registro['documento_transporte'];
        }

        // Aplica a Mario Londoño y Malco Cargo
        if(array_key_exists('pedido', $this->registro) && $this->registro['pedido'] != '') {
            $subject .= ';Pedido: ' . $this->registro['pedido'];
        }

        // Aplica a Mario Londoño
        if(array_key_exists('tipo_operacion', $this->registro) && $this->registro['tipo_operacion'] != '') {
            $subject .= ';Tipo Operacion: ' . $this->registro['tipo_operacion'];
        }

        //Aplica para:
        // 800024075 / Coltrans
        // 900841486 / Coldepositos Logistica
        // 901016877 / Coldeposits Bodega Nacional
        // 900451936 / Col OTM
        if(array_key_exists('informacion_col', $this->registro) && $this->registro['informacion_col'] != '') {
            $subject .= ';' . $this->registro['informacion_col'];
        }

        $identificadorCorreo = '#' . config('variables_sistema.ID_SERVIDOR') . '.' . $this->registro['bdd_id'] . '.' . $this->registro['cdo_id'];
        $subject            .= ';' . $identificadorCorreo;

        if($this->adjuntos != null) {
            $documentosAdjuntos = $this->from($this->emailRemite, $this->remite)
                ->subject($subject)
                ->view($this->rutaBlade)
                ->attachData(
                    $this->adjuntos['zip']['archivo'],
                    $this->adjuntos['zip']['nombre'],
                    [
                        'mime' => $this->adjuntos['zip']['mime'],
                    ]
                );
                if ($this->awsSesConfigurationSet != null) {
                    $documentosAdjuntos->withSwiftMessage(function ($message) {
                        $message->getHeaders()
                            ->addTextHeader('X-SES-CONFIGURATION-SET', $this->awsSesConfigurationSet);
                    });
                }

            if($this->correoAutorespuesta != null)
                $documentosAdjuntos->replyTo($this->correoAutorespuesta);

            return $documentosAdjuntos;
        } else {
            $correo = $this->from($this->emailRemite, $this->remite)
                ->subject($subject)
                ->view($this->rutaBlade);
                if ($this->awsSesConfigurationSet != null) {
                    $correo->withSwiftMessage(function ($message) {
                        $message->getHeaders()
                            ->addTextHeader('X-SES-CONFIGURATION-SET', $this->awsSesConfigurationSet);
                    });
                }

            if($this->correoAutorespuesta != null)
                $correo->replyTo($this->correoAutorespuesta);

            return $correo;
        }
    }
}
