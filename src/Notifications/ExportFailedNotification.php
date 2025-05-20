<?php

namespace Siluxa\DataExport\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class ExportFailedNotification extends Notification
{
    protected $error;

    public function __construct($error)
    {
        $this->error = $error;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->view(config('data-export.notification.failed_view'), ['error' => $this->error])
            ->subject('Échec de l\'exportation des données');
    }
}