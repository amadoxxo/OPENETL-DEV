<?php

namespace App\Mail\DhlExpress;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;

class notificarDocumentoRecepcionDhlExpress extends Mailable {
    use Queueable, SerializesModels;

    // Propiedad en donde se almacenan el tema o subject del correo
    public $subject;
    // Propiedad en donde se almacena el nombre del remitente (Ofe)
    public $remite;
    // Propiedad en donde se almacena el email del remitente
    public $emailRemite;
    // Propiedad en donde se almacena las notas del documento
    public $notas;
    // Propiedad en donde se almacena las reglas del documento
    public $reglas;
    // Propiedad que puede contener como adjuntos el PDF y el XML cuando el adquirente esta marcado para ello
    public $adjuntos;
    // Propiedad en donde se almacena la ruta a la plantilla Blade
    public $rutaBlade;
    // Propiedad en donde se almacena el Set de Configuración de AWS SES
    public $awsSesConfigurationSet;

    public function __construct($subject, $remite, $emailRemite, $notas, $reglas, $adjuntos, $rutaBlade, $awsSesConfigurationSet) {
        $this->subject                = $subject;
        $this->remite                 = $remite;
        $this->emailRemite            = $emailRemite;
        $this->adjuntos               = $adjuntos;
        $this->notas                  = $notas;
        $this->reglas                 = $reglas;
        $this->rutaBlade              = $rutaBlade;
        $this->awsSesConfigurationSet = $awsSesConfigurationSet;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build() {
        if($this->adjuntos != null) {
            $correoDocumentosAdjuntos = $this->from($this->emailRemite, $this->remite)
                ->subject($this->subject)
                ->view($this->rutaBlade)
                ->attachData(
                    $this->adjuntos['pdf']['archivo'],
                    $this->adjuntos['pdf']['nombre'],
                    [
                        'mime' => $this->adjuntos['pdf']['mime'],
                    ]
                );
            if (array_key_exists('xml', $this->adjuntos)) {
                $correoDocumentosAdjuntos = $correoDocumentosAdjuntos->attachData(
                    $this->adjuntos['xml']['archivo'],
                    $this->adjuntos['xml']['nombre'],
                    [
                        'mime' => $this->adjuntos['xml']['mime'],
                    ]
                );
            }
            if ($this->awsSesConfigurationSet != null) {
                $correoDocumentosAdjuntos->withSwiftMessage(function ($message) {
                    $message->getHeaders()
                        ->addTextHeader('X-SES-CONFIGURATION-SET', $this->awsSesConfigurationSet);
                });
            }

            return $correoDocumentosAdjuntos;
        } else {
            $correo = $this->from($this->emailRemite, $this->remite)
                ->subject($this->subject)
                ->view($this->rutaBlade);
                if ($this->awsSesConfigurationSet != null) {
                    $correo->withSwiftMessage(function ($message) {
                        $message->getHeaders()
                            ->addTextHeader('X-SES-CONFIGURATION-SET', $this->awsSesConfigurationSet);
                    });
                }

            return $correo;
        }
    }
}
