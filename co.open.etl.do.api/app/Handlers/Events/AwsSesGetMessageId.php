<?php

namespace App\Handlers\Events;

use Illuminate\Mail\Events\MessageSent;

class AwsSesGetMessageId {
    /**
     * Manejador de evento al enviar correos a través de AWS SES.
     *
     * @param  MessageSent  $event
     * @return void
     */
    public function handle(MessageSent $event) {
        $awsSesMessageId = $event->message
            ->getHeaders()
            ->get('x-ses-message-id')
            ->getValue();

        $openEtlSubject = $event->message
            ->getHeaders()
            ->get('subject')
            ->getValue();

        $subject = explode('#', $openEtlSubject);

        // dump('AWS SES MessageID');
        // dump($awsSesMessageId);
        // dump('openETL Subject');
        // dump($openEtlSubject);
        // dump('openETL ID');
        // dump($subject[count($subject) - 1]);
    }
}
