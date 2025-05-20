<?php

namespace Siluxa\DataExport\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Siluxa\DataExport\Services\DataExportService;
use Siluxa\DataExport\Notifications\ExportCompletedNotification;
use Siluxa\DataExport\Notifications\ExportFailedNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Log;

class ExportDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    protected $entity;
    protected $formats;
    protected $emails;
    protected $startDate;
    protected $endDate;
    protected $relations;
    protected $manualConfig;

    public function __construct($entity, $formats, $emails, $startDate = null, $endDate = null, $relations = [], $manualConfig = null)
    {
        $this->entity = $entity;
        $this->formats = is_array($formats) ? $formats : [$formats];
        $this->emails = is_array($emails) ? $emails : explode(',', $emails);
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->relations = $relations;
        $this->manualConfig = $manualConfig;
    }

    public function handle()
    {
        try {
            $service = new DataExportService();
            $result = $service->export($this->entity, $this->formats, $this->startDate, $this->endDate, $this->relations, $this->manualConfig);

            // Envoyer la notification de succès
            foreach ($this->emails as $email) {
                Notification::route('mail', trim($email))
                    ->notify(new ExportCompletedNotification($result['link']));
            }
        } catch (\Exception $e) {
            // Journaliser l'erreur
            Log::error("Échec de l'exportation pour {$this->entity}: {$e->getMessage()}", [
                'entity' => $this->entity,
                'formats' => $this->formats,
                'exception' => $e,
            ]);

            // Envoyer la notification d'échec
            foreach ($this->emails as $email) {
                Notification::route('mail', trim($email))
                    ->notify(new ExportFailedNotification($e->getMessage()));
            }

            throw $e; // Relancer pour que Laravel marque le job comme échoué
        }
    }

    public function failed(\Exception $exception)
    {
        Log::error("Échec définitif de l'exportation pour {$this->entity}: {$exception->getMessage()}", [
            'entity' => $this->entity,
            'formats' => $this->formats,
        ]);
    }
}