<!DOCTYPE html>
<html>
<head>
    <title>Exportation terminée</title>
    <meta http-equiv="Content-Type" content="text/html;charset=UTF-8">
</head>
<body>
    <h1>Exportation des données terminée</h1>
    <p>Votre exportation de données est prête à être téléchargée.</p>
    <p><a href="{{ $downloadLink }}">Télécharger le fichier</a></p>
    <p>Ce lien expirera dans {{ config('data-export.notification.url_expiration') }} minutes.</p>
</body>
</html>