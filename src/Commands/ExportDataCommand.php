<?php

namespace Siluxa\DataExport\Commands;

use Illuminate\Console\Command;
use Siluxa\DataExport\Jobs\ExportDataJob;

class ExportDataCommand extends Command
{
    protected $signature = 'siluxa-export:data {entity} {format} {emails} {--start-date=} {--end-date=} {--delay=} {--relations=} {--manual=}';
    protected $description = 'Exporte des données avec des options de ciblage, formats multiples, relations, et configuration manuelle.';

    public function handle()
    {
        $entity = $this->argument('entity');
        $formats = explode(',', $this->argument('format'));
        $emails = $this->argument('emails');
        $startDate = $this->option('start-date');
        $endDate = $this->option('end-date');
        $delay = $this->option('delay');
        $relations = $this->option('relations') ? explode(',', $this->option('relations')) : [];
        $manualConfig = $this->option('manual') ? json_decode($this->option('manual'), true) : null;

        // Valider les formats
        foreach ($formats as $format) {
            if (!in_array($format, config('data-export.formats'))) {
                $this->error("Format {$format} non supporté.");
                return 1;
            }
        }

        // Gérer toutes les entités si entity = '*'
        $entities = $entity === '*' ? array_keys(config('data-export.entities')) : [$entity];

        // Valider les entités si pas de config manuelle
        if (!$manualConfig) {
            foreach ($entities as $ent) {
                if (!array_key_exists($ent, config('data-export.entities'))) {
                    $this->error("Entité {$ent} non configurée.");
                    return 1;
                }
            }
        }

        // Lancer un job pour chaque entité
        foreach ($entities as $entity) {
            $job = new ExportDataJob($entity, $formats, $emails, $startDate, $endDate, $relations, $manualConfig);
            if ($delay) {
                $job->delay(now()->addMinutes($delay));
            }
            dispatch($job);
            $this->info("Exportation planifiée pour l'entité {$entity}.");
        }

        $this->info('Exportation(s) planifiée(s) avec succès.');
    }
}