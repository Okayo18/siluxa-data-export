<?php

   namespace Siluxa\DataExport\Commands;

   use Illuminate\Console\Command;
   use Siluxa\DataExport\Services\DataExportService;

   class ListExportedFilesCommand extends Command
   {
       protected $signature = 'siluxa-export:list';
       protected $description = 'Liste les fichiers exportés dans le dossier de stockage.';

       public function handle()
       {
           $service = new DataExportService();
           $files = $service->listExportedFiles();

           if (empty($files)) {
               $this->info('Aucun fichier exporté trouvé.');
               return 0;
           }

           $this->table(
               ['Nom', 'Chemin', 'URL', 'Taille', 'Dernière modification'],
               $files
           );

           return 0;
       }
   }