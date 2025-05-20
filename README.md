Siluxa Data Export
A reusable Laravel package for exporting data with delayed processing, precise targeting, multiple formats (CSV, JSON, XLSX, PDF), email notifications, and listing exported files.
Installation
Online Installation
Install the package and dependencies via Composer:
composer require siluxa/data-export maatwebsite/excel barryvdh/laravel-dompdf

Local Installation

Clone the repository:git clone <repository-url>


Install dependencies:composer install


Link to a Laravel project by adding to composer.json:"repositories": [
    {
        "type": "path",
        "url": "../siluxa-data-export"
    }
],
"require": {
    "siluxa/data-export": "*",
    "maatwebsite/excel": "^3.1",
    "barryvdh/laravel-dompdf": "^2.0"
}


Run:composer update



Post-Installation

Publish configuration and views:php artisan vendor:publish --tag=config
php artisan vendor:publish --tag=views


Configure queue in .env:QUEUE_CONNECTION=database


Create storage link:php artisan storage:link


Ensure the exports directory exists:mkdir -p storage/app/public/exports
chmod -R 775 storage/app/public/exports



Configuration
Edit config/filesystems.php to include the public0 disk:
'disks' => [
    'public0' => [
        'driver' => 'local',
        'root' => storage_path('app/public'),
        'url' => env('APP_URL').'/storage',
        'visibility' => 'public',
        'throw' => false,
    ],
],

Edit config/data-export.php to define entities, attributes, and relations:
'entities' => [
    'users' => [
        'model' => \App\Models\User::class,
        'attributes' => ['id', 'name', 'email'],
        'exclude_attributes' => ['password'],
        'relations' => [
            'profile' => ['name', 'phone'],
        ],
    ],
    'transactions' => [
        'model' => \App\Models\Transaction::class,
        'attributes' => ['id', 'amount', 'created_at'],
        'exclude_attributes' => ['company_id'],
        'relations' => [
            'company' => ['name'],
        ],
    ],
],
'formats' => ['csv', 'json', 'xlsx', 'pdf'],
'storage_path' => storage_path('app/public/exports'),
'notification' => [
    'emails' => env('EXPORT_NOTIFICATION_EMAILS', 'admin@example.com'),
    'disk' => 'public0',
    'view' => 'data-export::notifications.export-completed',
    'failed_view' => 'data-export::notifications.export-failed',
],
'batch_size' => 1000,

Ensure APP_URL is set in .env:
APP_URL=http://localhost

Usage
Export Command
Export data with the Artisan command:
php artisan siluxa-export:data {entity} {format} {emails} [--start-date=] [--end-date=] [--delay=] [--relations=] [--manual=]

Examples:

Export users in CSV and XLSX with relations:php artisan siluxa-export:data users csv,xlsx admin@example.com --relations=profile


Export all entities in PDF:php artisan siluxa-export:data * pdf admin1@example.com,admin2@example.com


Manual configuration:php artisan siluxa-export:data transactions csv admin@example.com --manual='{"model":"App\\Models\\Transaction","attributes":["id","amount"],"relations":{"company":["name"]}}'



List Exported Files
List all exported files in the exports directory:
php artisan siluxa-export:list

Scheduling
In app/Console/Kernel.php:
protected function schedule(Schedule $schedule)
{
    $schedule->command('siluxa-export:data users csv,xlsx admin@example.com --relations=profile')->dailyAt('00:00');
}

Features

Delayed Export: Immediate or scheduled via queue (dispatch or delay).
Precise Targeting: Filter by entity, date range, and conditions (e.g., STI).
Multiple Formats: CSV, JSON, XLSX, PDF (via maatwebsite/excel and barryvdh/laravel-dompdf).
Relations: Include related data (e.g., company.name instead of company_id).
Notifications: Email with public download link on success or failure.
File Listing: List exported files with details (name, path, URL, size, last modified).
Large Data Handling: Processes data in batches (batch_size).

Error Handling
Errors are logged, and failure notifications are sent to specified emails using export-failed.blade.php.
Requirements

PHP ^8.0
Laravel ^9.0|^10.0
maatwebsite/excel ^3.1
barryvdh/laravel-dompdf ^2.0

License
MIT
