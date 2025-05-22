<?php

namespace Siluxa\DataExport\Services;

use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use ZipArchive;

class DataExportService
{
    public function export($export, $formats)
    {
        if (!$export || !is_object($export)) {
            throw new \Exception("Entité d'exportation non valide ou introuvable.");
        }

        $storagePath = config('data-export.storage_path');
        if (!file_exists($storagePath)) {
            mkdir($storagePath, 0775, true);
        }

        $formats = is_array($formats) ? $formats : [$formats];
        foreach ($formats as $format) {
            if (!in_array($format, config('data-export.formats'))) {
                throw new \Exception("Format {$format} non supporté.");
            }
        }

        $files = [];
        $baseFilename = class_basename($export) . '_' . now()->format('YmdHis');
        $disk = config('data-export.notification.disk', 'public0');

        foreach ($formats as $format) {
            $filename = \Str::snake($baseFilename . "." . $format);
            $relativePath = 'exports/' . $filename;
            $fullPath = storage_path('app/public/' . $relativePath);

            $directory = dirname($fullPath);
            if (!file_exists($directory)) {
                mkdir($directory, 0775, true);
            }

            try {
                $writerType = match (strtolower($format)) {
                    'xlsx' => \Maatwebsite\Excel\Excel::XLSX,
                    'csv' => \Maatwebsite\Excel\Excel::CSV,
                    'pdf' => \Maatwebsite\Excel\Excel::DOMPDF,
                    default => throw new \Exception("Format {$format} non supporté.")
                };

                $success = Excel::store($export, $relativePath, $disk, $writerType, ['visibility' => 'public']);

                if (!$success || !Storage::disk($disk)->exists($relativePath)) {
                    \Log::error("Échec de la création du fichier : {$fullPath}");
                } else {
                    $files[] = $fullPath;
                }
            } catch (\Throwable $e) {
                \Log::error("Erreur lors de l'export Excel ({$format}) : {$e->getMessage()}");
            }
        }

        return [
            'files' => $files,
        ];
    }

    public static function compressFiles($files, $baseFilename)
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
        $disk = config('data-export.notification.disk', 'public0');
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