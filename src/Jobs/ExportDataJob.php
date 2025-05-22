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
use Illuminate\Support\Facades\Storage;

class ExportDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    protected $exports;
    protected $formats;
    protected $emails;

    public function __construct($exports, $formats, $emails)
    {
        $this->exports = is_array($exports) ? $exports : [$exports];
        $this->formats = is_array($formats) ? $formats : [$formats];
        $this->emails = is_array($emails) ? $emails : explode(',', $emails);
    }

    public function handle()
    {
        try {
            $service = new DataExportService();
            $allFiles = [];
            $baseFilename = 'export_' . now()->format('YmdHis');

            // Exporter chaque objet
            foreach ($this->exports as $export) {
                if (!is_object($export)) {
                    throw new \Exception("Classe d'exportation {$export} introuvable.");
                }
                $result = $service->export(clone $export, $this->formats);
                $allFiles = array_merge($allFiles, $result['files']);
            }

            // Compresser tous les fichiers en un seul ZIP
            $zipPath = DataExportService::compressFiles($allFiles, $baseFilename);
            $disk = config('data-export.notification.disk', 'public');

            //relativePath
            $relativePath = 'exports/' . basename($zipPath);
            $link = Storage::disk($disk)->url($relativePath);

            // Envoyer une seule notification de succès
            foreach ($this->emails as $email) {
                Notification::route('mail', trim($email))
                    ->notify(new ExportCompletedNotification($link));
            }
        } catch (\Exception $e) {
            // Journaliser l'erreur
            Log::error("Échec de l'exportation pour " . implode(', ', $this->exports) . ": {$e->getMessage()}", [
                'exports' => $this->exports,
                'formats' => $this->formats,
                'exception' => $e,
            ]);

            // Envoyer une notification d'échec
            foreach ($this->emails as $email) {
                Notification::route('mail', trim($email))
                    ->notify(new ExportFailedNotification($e->getMessage()));
            }

            throw $e;
        }
    }

    public function failed(\Exception $exception)
    {
        Log::error("Échec définitif de l'exportation pour " . implode(', ', $this->exports) . ": {$exception->getMessage()}", [
            'exports' => $this->exports,
            'formats' => $this->formats,
        ]);
    }
}