<?php

namespace Siluxa\DataExport\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class ExportCompletedNotification extends Notification
{
    protected $downloadLink;

    public function __construct($downloadLink)
    {
        $this->downloadLink = $downloadLink;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->view(config('data-export.notification.view'), ['downloadLink' => $this->downloadLink])
            ->subject('Exportation des données terminée');
    }
}