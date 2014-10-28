<?php

require_once( dirname(__FILE__) . '/../vendor/autoload.php');

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


