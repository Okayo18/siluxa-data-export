<?php

namespace Siluxa\DataExport\Commands;

use Illuminate\Console\Command;
use Siluxa\DataExport\Jobs\ExportDataJob;

class ExportDataCommand extends Command
{
    protected $signature = 'siluxa-export:data {export} {format} {emails} {--start-date=} {--end-date=} {--delay=}';
    protected $description = 'Exporte des données avec des options de ciblage, formats multiples, relations, et configuration manuelle.';

    public function handle()
    {
        $exportsInput = $this->argument('export') ? explode(",", $this->argument('export')) : [];
        $formats = explode(',', $this->argument('format'));
        $emails = $this->argument('emails');
        $startDate = $this->option('start-date');
        $endDate = $this->option('end-date');
        $delay = $this->option('delay');

        // Valider les formats
        foreach ($formats as $format) {
            if (!in_array($format, config('data-export.formats'))) {
                $this->error("Format {$format} non supporté.");
                return 1;
            }
        }

        // Gérer toutes les exportations si export = '*'
        $exports = $exportsInput[0] === '*' ? array_keys(config('data-export.exports', [])) : $exportsInput;

        // Exporter chaque objet
        $exportList = [];
        foreach ($exports as $exportKey) {
            $exportClass = config("data-export.exports.{$exportKey}.class");
            if (!class_exists($exportClass)) {
                throw new \Exception("Classe d'exportation {$exportClass} introuvable.");
            }
            $exportList[] = new $exportClass(null, null,$startDate, $endDate);
        }

        // Lancer un job unique pour toutes les exportations
        $job = new ExportDataJob($exportList, $formats, $emails, $startDate, $endDate);
        if ($delay) {
            $job->delay(now()->addMinutes($delay));
        }
        dispatch($job);
        $this->info("Exportation planifiée pour : " . implode(', ', $exports));

        $this->info('Exportation(s) planifiée(s) avec succès.');
        return 0;
    }
}