<?php

declare(strict_types=1);

/**
 * Mezzio Entry Point — Document-Root: public/
 *
 * Der Webserver (Apache/Nginx) zeigt auf dieses Verzeichnis (public/).
 * Alle Anfragen die keiner statischen Datei entsprechen werden hierher geleitet.
 *
 * Apache-Beispiel (.htaccess in public/):
 *   RewriteEngine On
 *   RewriteCond %{REQUEST_FILENAME} !-f
 *   RewriteCond %{REQUEST_FILENAME} !-d
 *   RewriteRule ^ index.php [QSA,L]
 *
 * Nginx-Beispiel:
 *   root /var/www/desec-manager/public;
 *   location / {
 *       try_files $uri $uri/ /index.php$is_args$args;
 *   }
 *
 * WICHTIG: Das Projekt-Root (eine Ebene oberhalb von public/) enthält
 * config/, src/, vendor/ — diese sind dem Webserver NICHT direkt zugänglich.
 * Das ist der Sicherheitsvorteil gegenüber dem bisherigen index.php im Root.
 */

// Arbeitsverzeichnis auf Projekt-Root setzen (eine Ebene über public/)
chdir(dirname(__DIR__));

// Composer-Autoloader
require 'vendor/autoload.php';

// PSR-11-Container aus app/container.php laden
/** @var \Psr\Container\ContainerInterface $container */
$container = require 'app/container.php';

// Mezzio-Application aus Container holen
/** @var \Mezzio\Application $app */
$app = $container->get(\Mezzio\Application::class);

/** @var \Mezzio\MiddlewareFactory $factory */
$factory = $container->get(\Mezzio\MiddlewareFactory::class);

// Middleware-Pipeline konfigurieren (app/pipeline.php)
(require 'app/pipeline.php')($app, $factory, $container);

// Routen registrieren (app/routes.php)
(require 'app/routes.php')($app, $factory, $container);

// Anfrage verarbeiten und Antwort senden
$app->run();
