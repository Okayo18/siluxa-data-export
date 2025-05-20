<?php

namespace Siluxa\DataExport\Services;

use Illuminate\Support\Facades\Storage;
use ZipArchive;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class DataExportService
{
    public function export($entity, $formats, $startDate = null, $endDate = null, $relations = [], $manualConfig = null)
    {
        // Vérifier si l'entité est configurée ou manuelle
        $entityConfig = $manualConfig ?? config('data-export.entities.' . $entity);
        if (!$entityConfig || !class_exists($entityConfig['model'])) {
            throw new \Exception("Entité {$entity} non configurée ou introuvable.");
        }

        // Créer le répertoire s'il n'existe pas
        $storagePath = config('data-export.storage_path');
        if (!file_exists($storagePath)) {
            mkdir($storagePath, 0775, true);
        }

        // Préparer les formats
        $formats = is_array($formats) ? $formats : [$formats];
        foreach ($formats as $format) {
            if (!in_array($format, config('data-export.formats'))) {
                throw new \Exception("Format {$format} non supporté.");
            }
        }

        // Construire la requête avec conditions
        $query = $entityConfig['model']::query();
        if (!empty($entityConfig['conditions'])) {
            $query->where($entityConfig['conditions']);
        }
        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }
        if (!empty($relations)) {
            $query->with($relations);
        }

        // Générer les fichiers
        $files = [];
        $baseFilename = $entity . '_' . now()->format('YmdHis');
        $batchSize = config('data-export.batch_size', 1000);

        foreach ($formats as $format) {
            $filename = "{$baseFilename}.{$format}";
            $path = config('data-export.storage_path') . '/' . $filename;

            if ($format === 'csv') {
                $this->generateCsv($query, $path, $batchSize, $relations, $entityConfig);
            } elseif ($format === 'json') {
                $this->generateJson($query, $path, $batchSize, $relations, $entityConfig);
            } elseif ($format === 'xlsx' || $format === 'pdf') {
                $this->generateExcel($query, $path, $batchSize, $relations, $entityConfig, $format);
            }

            $files[] = $path;
        }

        // Compresser les fichiers
        $zipPath = $this->compressFiles($files, $baseFilename);

        // Générer un lien public
        $disk = config('data-export.notification.disk', 'public');
        $relativePath = 'exports/' . basename($zipPath);
        $link = Storage::disk($disk)->url($relativePath);

        return [
            'file' => $zipPath,
            'link' => $link,
        ];
    }

    protected function generateCsv($query, $path, $batchSize, $relations, $entityConfig)
    {
        $file = fopen($path, 'w');
        $isFirstBatch = true;

        $query->chunk($batchSize, function ($rows) use ($file, &$isFirstBatch, $relations, $entityConfig) {
            foreach ($rows as $row) {
                $data = $this->flattenRow($row, $relations, $entityConfig);
                if ($isFirstBatch) {
                    fputcsv($file, array_keys($data));
                    $isFirstBatch = false;
                }
                fputcsv($file, $data);
            }
        });

        fclose($file);
    }

    protected function generateJson($query, $path, $batchSize, $relations, $entityConfig)
    {
        $data = [];
        $query->chunk($batchSize, function ($rows) use (&$data, $relations, $entityConfig) {
            foreach ($rows as $row) {
                $data[] = $this->flattenRow($row, $relations, $entityConfig);
            }
        });
        file_put_contents($path, json_encode($data));
    }

    protected function generateExcel($query, $path, $batchSize, $relations, $entityConfig, $format)
    {
        $data = [];
        $query->chunk($batchSize, function ($rows) use (&$data, $relations, $entityConfig) {
            foreach ($rows as $row) {
                $data[] = $this->flattenRow($row, $relations, $entityConfig);
            }
        });

        if (empty($data)) {
            \Log::warning("Aucune donnée à exporter pour {$entityConfig['model']} en format {$format}");
            return;
        }

        $export = new class($data) implements FromCollection, WithHeadings {
            protected $data;

            public function __construct($data)
            {
                $this->data = $data;
            }

            public function collection()
            {
                return collect($this->data);
            }

            public function headings(): array
            {
                return array_keys($this->data[0] ?? []);
            }
        };

        $disk = config('data-export.notification.disk', 'public');
        $relativePath = 'exports/' . basename($path);
        $fullPath = storage_path('app/public/' . $relativePath);

        // Créer le répertoire si nécessaire
        $directory = dirname($fullPath);
        if (!file_exists($directory)) {
            mkdir($directory, 0775, true);
        }

        Excel::store($export, $relativePath, $disk, null, ['visibility' => 'public']);
        if ($format === 'pdf') {
            Excel::store($export, $relativePath, $disk, \Maatwebsite\Excel\Excel::DOMPDF, ['visibility' => 'public']);
        }

        if (!Storage::disk($disk)->exists($relativePath)) {
            \Log::error("Échec de la création du fichier : {$fullPath}");
        }
    }

    protected function flattenRow($row, $relations, $entityConfig)
    {
        $data = $row->toArray();
        $flattened = [];

        // Filtrer les attributs
        $attributes = $entityConfig['attributes'] ?? array_keys($data);
        $excludeAttributes = $entityConfig['exclude_attributes'] ?? [];
        foreach ($attributes as $attr) {
            if (!in_array($attr, $excludeAttributes) && isset($data[$attr]) && !is_array($data[$attr])) {
                $flattened[$attr] = $data[$attr];
            }
        }

        // Ajouter les relations
        foreach ($relations as $relation) {
            if (isset($data[$relation]) && isset($entityConfig['relations'][$relation])) {
                $relatedData = $data[$relation];
                $relAttributes = $entityConfig['relations'][$relation];
                if (is_array($relatedData)) {
                    $relatedData = is_array($relatedData[0] ?? []) ? $relatedData : [$relatedData];
                    foreach ($relatedData as $index => $related) {
                        foreach ($relAttributes as $relKey) {
                            if (isset($related[$relKey])) {
                                $flattened["{$relation}_{$index}_{$relKey}"] = $related[$relKey];
                            }
                        }
                    }
                }
            }
        }

        return $flattened;
    }

    protected function compressFiles($files, $baseFilename)
    {
        $zipPath = config('data-export.storage_path') . '/' . $baseFilename . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE) === true) {
            foreach ($files as $file) {
                if (file_exists($file)) {
                    $zip->addFile($file, basename($file));
                } else {
                    \Log::error("Fichier introuvable pour compression : {$file}");
                }
            }
            $zip->close();
        }
        foreach ($files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        return $zipPath;
    }

    public function listExportedFiles()
    {
        $disk = config('data-export.notification.disk', 'public');
        $files = Storage::disk($disk)->files('exports');
        $fileList = [];

        foreach ($files as $file) {
            $fileList[] = [
                'name' => basename($file),
                'path' => $file,
                'url' => Storage::disk($disk)->url($file),
                'size' => Storage::disk($disk)->size($file) / 1024 . ' KB',
                'last_modified' => date('Y-m-d H:i:s', Storage::disk($disk)->lastModified($file)),
            ];
        }

        return $fileList;
    }
}