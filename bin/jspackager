#!/usr/bin/env php
<?php


$loaded = false;

foreach (array(__DIR__ . '/../vendor/autoload.php', __DIR__ . '/vendor/autoload.php', __DIR__ . '/../../../autoload.php') as $file) {
    if (file_exists($file)) {
        require $file;
        $loaded = true;
        break;
    }
}

if (!$loaded) {
    die(
        'You need to set up the project dependencies using the following commands:' . PHP_EOL .
        'wget http://getcomposer.org/composer.phar' . PHP_EOL .
        'php composer.phar install' . PHP_EOL
    );
}

date_default_timezone_set('America/Denver');

use JsPackager\Command;
use Symfony\Component\Console\Application;


// Read configs for Ingestion's DB access
//$configuration = @include('config/config.php');
//if  ( !isset( $configuration['referral-ingestion'] ) )
//{
//    throw new \Exception('Malformed or missing configuration. Need referral-ingestion configuration.');
//}
//
//$referralIngestCommandDbConfig = $configuration['referral-ingestion']['app-database'];
//$druidNodeConnInfo = $configuration['referral-ingestion']['druid-connection'];


$clearFoldersCommand = new Command\ClearFoldersCommand();
$compileFoldersCommand = new Command\CompileFoldersCommand();
$compileFilesCommand = new Command\CompileFilesCommand();

$console = new Application();

$console->add( $clearFoldersCommand );
$console->add( $compileFoldersCommand );
$console->add( $compileFilesCommand );

$console->run();


