<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class notificarEventosDian extends Mailable {
    use Queueable, SerializesModels;

    public $dataCorreo;
    public $adjuntos;

    public function __construct($dataCorreo, $adjuntos = null) {
        $this->dataCorreo = $dataCorreo;
        $this->adjuntos   = $adjuntos;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build() {
        if($this->adjuntos != null) {
            $correoDocumentosAdjuntos = $this->from($this->dataCorreo['emailRemite'], $this->dataCorreo['nombreGeneradorEvento'])
                ->subject($this->dataCorreo['subject'])
                ->view($this->dataCorreo['rutaBlade'])
                ->replyTo($this->dataCorreo['ofeCorreo'])
                ->attachData(
                    $this->adjuntos['zip']['archivo'],
                    $this->adjuntos['zip']['nombre'],
                    [
                        'mime' => $this->adjuntos['zip']['mime'],
                    ]
                );

            if ($this->dataCorreo['awsSesConfigurationSet'] != null) {
                $correoDocumentosAdjuntos->withSwiftMessage(function ($message) {
                    $message->getHeaders()
                        ->addTextHeader('X-SES-CONFIGURATION-SET', $this->dataCorreo['awsSesConfigurationSet']);
                });
            }

            return $correoDocumentosAdjuntos;
        } else {
            $correo = $this->from($this->dataCorreo['emailRemite'], $this->dataCorreo['nombreGeneradorEvento'])
                ->subject($this->dataCorreo['subject'])
                ->view($this->dataCorreo['rutaBlade'])
                ->replyTo($this->dataCorreo['ofeCorreo']);

                if ($this->dataCorreo['awsSesConfigurationSet'] != null) {
                    $correo->withSwiftMessage(function ($message) {
                        $message->getHeaders()
                            ->addTextHeader('X-SES-CONFIGURATION-SET', $this->dataCorreo['awsSesConfigurationSet']);
                    });
                }

            return $correo;
        }
    }
}
