<?php

namespace JsPackager\Command;

use JsPackager\Compiler;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

class CompileFoldersCommand extends Command
{

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('compile-folders')
            ->setDescription('Compile files in given folder(s).')
            ->setDefinition($this->createDefinition())
            ->setHelp(<<<HELPBLURB
Examples:
Dates:
\t<info>clear-folders 2008-01-01 2009-01-01</info>
Dates with Time:
\t<info>php ingest.php ingest 2008-01-01T01:30:00 2009-01-01T04:20:00 -v</info>

HELPBLURB
            );
        ;
    }

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $foldersToClear = ( $input->getArgument('folder') );

        $success = true;

        $this->logger = new ConsoleLogger($output);

        $compilerTimeStart = microtime( true );

        $compiler = new Compiler();

        foreach( $foldersToClear as $inputFolder )
        {
            $this->logger->info("Compiling folder '{$inputFolder}'.");

            $compilationSuccessful = $this->analyzeAndCompileFolder( $compiler, $inputFolder );

            // If this folder failed to compile completely, we were not completely successful
            if ( !$compilationSuccessful )
            {
                $success = false;
            }
        }

        $compilerTimeEnd = microtime( true );
        $compilerTimeTotal = $compilerTimeEnd - $compilerTimeStart;
        $this->logger->notice( "JsPackager compiler finished folder compilation. (Total time: {$compilerTimeTotal} seconds)." );

        return !$success; // A-OK error code
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

        $this->logger->info("Confirming path to '{$folderPath}'.");
        $realPathResult = realpath( $folderPath );
        if ( $realPathResult === false ) {
            $this->logger->error("Path '{$folderPath}' resolved to nowhere.");
            return false;
        } else {
            $folderPath = $realPathResult;
        }

        $this->logger->info( "Parsing application folder: \"{$folderPath}\" for compilation" );


        try
        {
            $compilationTimingStart = microtime( true );

            $filesToCompile = $compiler->parseFolderForSourceFiles( $folderPath, 'updateUserInterface' );
            $numberOfFilesToCompile = count($filesToCompile);
            $numberOfFilesCompiled = 0;

            $this->updateUserInterface( "Found {$numberOfFilesToCompile} files to compile:" , 'status' );

            foreach( $filesToCompile as $fileToCompile )
            {
                $this->updateUserInterface( "\t{$fileToCompile}" , 'status' );
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
            $this->updateUserInterface( "Successfully compiled {$numberOfFilesCompiled} out of {$numberOfFilesToCompile} files in application folder \"{$folderPath}\" in {$compilationTotalTime} seconds.", 'status' );
        }
        catch ( \JsPackager\MissingFileException $e )
        {
            $this->updateUserInterface( "[ERROR] {$e->getMessage()}", 'error' );
            $completelySuccessful = false;
        }
        catch ( ParsingException $e )
        {
            $this->updateUserInterface( "[ERROR] {$e->getMessage()}", 'error' );
            $completelySuccessful = false;
        }
        catch ( CannotWriteException $e )
        {
            $this->updateUserInterface( "[ERROR] Failed to compile \"{$e->getFilePath()}\" - " . $e->getMessage(), 'error' );
            $completelySuccessful = false;
        }

        return $completelySuccessful;
    }



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
            $this->logger->error($line);
        }
        else if ( $type === 'warning' )
        {
            $this->logger->warning($line);
        }
        else if ( $type === 'success' )
        {
            $this->logger->notice($line);
        }
        else if ( $type === 'status' )
        {
            $this->logger->info($line);
        }
        else if ( $type == 'output' )
        {
            $this->logger->info($line);
        }
        else
        {
            $this->logger->error("Unknown UI Message Type: '$line'.");
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getNativeDefinition()
    {
        return $this->createDefinition();
    }


    /**
     * {@inheritdoc}
     */
    protected function createDefinition()
    {
        return new InputDefinition(array(
            new InputArgument('folder',  InputArgument::REQUIRED | InputArgument::IS_ARRAY,    'Relative path to folder to compile the files in.'),
        ));
    }
}