#!/usr/bin/env php
<?php
date_default_timezone_set('UTC');
error_reporting(E_ALL | E_STRICT);

$JsPackagerCliVersion = 0.1;

////////////////////////////////////
// Initialize Autoloader
////////////////////////////////////

$JsPackagerFolder = realpath( __DIR__ . '/../' );
define('APPLICATION_PATH', realpath( $JsPackagerFolder . '/../../../' ));
//chdir(APPLICATION_PATH);

require 'vendor/autoload.php';


////////////////////////////////////
// Use Statements
////////////////////////////////////

use JsPackager\Compiler;
use JsPackager\DependencyTree;
use JsPackager\Exception\CannotWrite as CannotWriteException;
use JsPackager\Exception\MissingFile as MissingFileException;
use JsPackager\Exception\Parsing as ParsingException;
use JsPackager\Exception\Recursion as RecursionException;



////////////////////////////////////
// Parse arguments
////////////////////////////////////

$shortOpts = '';
$shortOpts .= 'f';
$shortOpts .= 'c';
$longOpts = array(
    'inputFile:',   // Required value
    'inputFolder:',
    'force',
    'clearFolder:',
    'cwd',
    'help',
);
$arguments = getopt( $shortOpts, $longOpts );

// Input file argument: --inputFile <filepath>
$inputFiles = ( isset( $arguments['inputFile'] ) ? $arguments['inputFile'] : false );

// Force Compile argument: -f or --force
$forceCompile = ( isset( $arguments['f'] ) || isset( $arguments['force'] ) );

// Clear Packages argument: --clearFolder
$clearPackages = ( isset( $arguments['clearFolder'] ) ? $arguments['clearFolder'] : false );

$checkCwd = isset( $arguments['cwd'] );

$help = isset( $arguments['help'] );

$inputFolders = ( isset( $arguments['inputFolder'] ) ? $arguments['inputFolder'] : false );

$noArgs = ( !$inputFiles && !$forceCompile && !$clearPackages && !$checkCwd && !$help && !$inputFolders );

////////////////////////////////////
// Startup & Help
////////////////////////////////////

updateUserInterface( "JsPackager Compiler v" . $JsPackagerCliVersion . "\n", 'output' );

if ( $noArgs )
{
    updateUserInterface( "Missing argument. Pass --help for help.\n", 'output' );

    returnWithExitCode(false);
}
else if ( $help )
{
    $optionsMessage = <<<OPTIONS
Options available:
 Input file argument:       --inputFile <filepath>
 Input folder argument:     --inputFolder <folderpath>
 Force Compile argument:    -f or --force
 Clear Packages argument:   --clearFolder <folderpath>
 Check working directory:   --cwd
 View help (this):          --help

OPTIONS;

    updateUserInterface( $optionsMessage, 'output' );

    returnWithExitCode(false);
}
else if ( $checkCwd )
{
    updateUserInterface( 'JsPackager Working directory: "' . getcwd() . '"' . PHP_EOL, 'output' );

    returnWithExitCode(false);

}

////////////////////////////////////
// Execute functionality
////////////////////////////////////

$completelySuccessful = true;
$compilerTimeStart = microtime( true );

updateUserInterface( 'JsPackager Compiler beginning execution... (Working directory: "' . getcwd() . '")' . PHP_EOL . PHP_EOL, 'output' );

// Clear packages if necessary
if ( $clearPackages )
{
    // If one application folder file was passed, make it an array
    // to simplify logic of handling many
    if ( is_string( $clearPackages ) ) {
        $clearPackages = array( $clearPackages );
    }


    $compiler = new Compiler();

    updateUserInterface( "Clear packages flag detected.\n", 'output' );

    foreach( $clearPackages as $folderPath )
    {
        $folderPath = realpath( $folderPath );

        updateUserInterface( "\t[Clearing] Clearing all packages (compiled files and manifests) in {$folderPath}...\n", 'output' );

        $success = $compiler->clearPackages($folderPath);
        if ( !$success )
        {
            updateUserInterface( "\t[Clearing] An error occurred while clearing packages. Halting compilation.\n", 'output' );

            returnWithExitCode($success);
        }

    }

    updateUserInterface( "\t[Clearing] Finished clearing packages.\n", 'output' );
}

if ( $inputFiles )
{
    // If one input file was passed, make it an array
    // to simplify logic of handling many
    if ( is_string( $inputFiles ) ) {
        $inputFiles = array( $inputFiles );
    }


    $compiler = new Compiler();

    foreach( $inputFiles as $inputFile )
    {
        $compilationSuccessful = compileFile( $compiler, $inputFile );

        // If this file failed to compile, we were not completely successful
        if ( !$compilationSuccessful )
        {
            $completelySuccessful = false;
        }
    }

    $compilerTimeEnd = microtime( true );
    $compilerTimeTotal = $compilerTimeEnd - $compilerTimeStart;
    updateUserInterface( "\nJsPackager compiler finished execution. (Total time: {$compilerTimeTotal} seconds)\n\n", 'output' );
}

if ( $inputFolders )
{
    // If one application folder file was passed, make it an array
    // to simplify logic of handling many
    if ( is_string( $inputFolders ) ) {
        $inputFolders = array( $inputFolders );
    }


    $compiler = new Compiler();

    foreach( $inputFolders as $inputFolder )
    {
        $compilationSuccessful = analyzeAndCompileFolder( $compiler, $inputFolder );

        // If this folder failed to compile completely, we were not completely successful
        if ( !$compilationSuccessful )
        {
            $completelySuccessful = false;
        }
    }

    $compilerTimeEnd = microtime( true );
    $compilerTimeTotal = $compilerTimeEnd - $compilerTimeStart;
    updateUserInterface( "\nJsPackager compiler finished application folder compilation. (Total time: {$compilerTimeTotal} seconds)\n\n", 'output' );
}



// Return with exit code so CI's like us
returnWithExitCode($completelySuccessful);

/**
 * Exits with the appropriate exit code.
 *
 * (This wraps the inverted logic of 0 = true in exit codes)
 *
 * @param bool $success Pass true if successful
 */
function returnWithExitCode($success)
{
    // Return with exit code so CI's like us
    $exitCode = (int)(!$success);
    exit($exitCode);
}


/**
 * Update the user interface by decorating and echoing the given string to standard output.
 *
 * @param string $line
 * @param string $type An option out of
 *  output  Outputs the line exactly as given.
 *  status  Outputs the line tabbed, with a newline added.
 *  error   Outputs the line tabbed, colored, and with a newline added.
 *  warning Outputs the line tabbed, colored, and with a newline added.
 *  success Outputs the line tabbed, colored, and with a newline added.
 */
function updateUserInterface($line, $type = 'output') {

    if ( $type === 'error' )
    {
        echo "\t\033[31m$line\033[m\n";
    }
    else if ( $type === 'warning' )
    {
        echo "\t\033[33m$line\033[m\n";
    }
    else if ( $type === 'success' )
    {
        echo "\t\033[32m$line\033[m\n";
    }
    else if ( $type === 'status' )
    {
        echo "\t$line\n";
    }
    else if ( $type == 'output' )
    {
        echo $line;
    }
    else
    {
        echo "Unknown UI Message Type: $line\n";
    }
}

/**
 * Compile a file using a Compiler, reporting back to the UI
 *
 * @param Compiler $compiler
 * @param string $filePath
 */
function compileFile($compiler, $filePath) {
    $completelySuccessful = true;

    $filePath = realpath( $filePath );

    updateUserInterface( "Compiling \"{$filePath}\"\n", 'output' );

    updateUserInterface( "[Compiling] Loading file \"{$filePath}\" for compilation..." . PHP_EOL, 'status' );

    try
    {
        $compilationTimingStart = microtime( true );

        $compiledFiles = $compiler->compileAndWriteFilesAndManifests( $filePath, 'updateUserInterface' );

        $compilationTimingEnd = microtime( true );
        $compilationTotalTime = $compilationTimingEnd - $compilationTimingStart;
        updateUserInterface( "[Compiling] Successfully compiled file \"{$filePath}\" in {$compilationTotalTime} seconds.", 'status' );

        updateUserInterface( "\t\tIt resulted in " . count($compiledFiles) . ' compiled packages:' . PHP_EOL, 'output' );

        foreach( $compiledFiles as $compiledFile ) {
            updateUserInterface( "\t\t{$compiledFile->sourcePath}\n", 'output' );
            updateUserInterface( "\t\t\t{$compiledFile->compiledPath}\n", 'output' );
            updateUserInterface( "\t\t\t{$compiledFile->manifestPath}\n", 'output' );
        }
    }
    catch ( MissingFileException $e )
    {
        updateUserInterface( "[Compiling] [ERROR] {$e->getMessage()}", 'error' );
        $completelySuccessful = false;
    }
    catch ( ParsingException $e )
    {
        updateUserInterface( "[Compiling] [ERROR] {$e->getMessage()}", 'error' );
        $completelySuccessful = false;
    }
    catch ( CannotWriteException $e )
    {
        updateUserInterface( "[Compiling] [ERROR] Failed to compile \"{$e->getFilePath()}\" - " . $e->getMessage(), 'error' );
        $completelySuccessful = false;
    }

    return $completelySuccessful;
}

/**
 * Analyze a folder of js files and compile the js files inside using a Compiler, reporting back to the UI
 *
 * @param Compiler $compiler
 * @param string $folderPath
 */
function analyzeAndCompileFolder($compiler, $folderPath)
{
    $completelySuccessful = true;

    $folderPath = realpath( $folderPath );

    updateUserInterface( "Parsing application folder: \"{$folderPath}\" for compilation\n", 'output' );


    try
    {
        $compilationTimingStart = microtime( true );

        $filesToCompile = $compiler->parseFolderForSourceFiles( $folderPath, 'updateUserInterface' );
        $numberOfFilesToCompile = count($filesToCompile);
        $numberOfFilesCompiled = 0;

        updateUserInterface( "Found {$numberOfFilesToCompile} files to compile:" , 'status' );

        foreach( $filesToCompile as $fileToCompile )
        {
            updateUserInterface( "\t{$fileToCompile}" , 'status' );
        }

        foreach( $filesToCompile as $fileToCompile )
        {
            $compilationSuccessful = compileFile($compiler, $fileToCompile);

            // If this file failed to compile, we were not completely successful
            if ( !$compilationSuccessful )
            {
                $completelySuccessful = false;
            }
            else
            {
                $numberOfFilesCompiled++;
            }
        }

        $compilationTimingEnd = microtime( true );
        $compilationTotalTime = $compilationTimingEnd - $compilationTimingStart;
        updateUserInterface( "[Compiling] Successfully compiled {$numberOfFilesCompiled} out of {$numberOfFilesToCompile} files in application folder \"{$folderPath}\" in {$compilationTotalTime} seconds.", 'status' );
    }
    catch ( MissingFileException $e )
    {
        updateUserInterface( "[Compiling] [ERROR] {$e->getMessage()}", 'error' );
        $completelySuccessful = false;
    }
    catch ( ParsingException $e )
    {
        updateUserInterface( "[Compiling] [ERROR] {$e->getMessage()}", 'error' );
        $completelySuccessful = false;
    }
    catch ( CannotWriteException $e )
    {
        updateUserInterface( "[Compiling] [ERROR] Failed to compile \"{$e->getFilePath()}\" - " . $e->getMessage(), 'error' );
        $completelySuccessful = false;
    }

    return $completelySuccessful;
}
