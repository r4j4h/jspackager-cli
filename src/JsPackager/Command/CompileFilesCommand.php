<?php

namespace JsPackager\Command;

use JsPackager\Compiler;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

class CompileFilesCommand extends Command
{

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('compile-files')
            ->setDescription('Compiled given file(s).')
            ->setDefinition($this->createDefinition())
            ->setHelp(<<<HELPBLURB
<info>%command.name%</info> provides an easy way to compile a given file or list of files.

\t<info>%command.full_name% src/main.js</info> compiles the src/main.js file with its dependencies.

By default, output is fairly quiet unless there are problems. To see what's going on, increase the verbosity level.

\t<info>%command.full_name% src/main.js -vv</info>

Multiple files are supported.

\t<info>%command.full_name% src/main-file.js src/batched-lib.js</info>.

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
        $foldersToClear = ( $input->getArgument('file') );

        $completelySuccessful = true;

        $this->logger = new ConsoleLogger($output);

        $compilerTimeStart = microtime( true );


        $compiler = new Compiler();
        $compiler->sharedFolderPath = getcwd() . '/' . 'public/shared';
        $compiler->logger = $this->logger;

        $filesCompiled = array();

        foreach( $foldersToClear as $inputFile )
        {
            $this->logger->info("Compiling file '{$inputFile}'.");

            $compilationSuccessful = $this->compileFile( $compiler, $inputFile );

            array_push($filesCompiled, array($inputFile, $compilationSuccessful?'<info>Yes</info>':'<error>No</error>'));

            // If this file failed to compile, we were not completely successful
            if ( $compilationSuccessful === false )
            {
                $completelySuccessful = false;
            }
        }

        $compilerTimeEnd = microtime( true );
        $compilerTimeTotal = $compilerTimeEnd - $compilerTimeStart;
        $this->logger->notice( "JsPackager compiler finished file compilation. (Total time: {$compilerTimeTotal} seconds)." );

        $table = new Table($output);
        $table->setHeaders(array('Folder','Successfully Compiled'));
        $table->setRows($filesCompiled);
        $table->render();

        return (int)(!$completelySuccessful); // A-OK error code
    }


    /**
     * Compile a file using a Compiler, reporting back to the UI
     *
     * @param Compiler $compiler
     * @param string $filePath
     */
    function compileFile($compiler, $filePath) {
        $completelySuccessful = true;

        $this->logger->info("Confirming path to '{$filePath}'.");
        $realPathResult = realpath( $filePath );
        if ( $realPathResult === false ) {
            $this->logger->error("Path '{$filePath}' resolved to nowhere.");
            return false;
        } else {
            $filePath = $realPathResult;
        }

        $this->updateUserInterface( "Compiling \"{$filePath}\"\n", 'output' );

        $this->updateUserInterface( "[Compiling] Loading file \"{$filePath}\" for compilation..." . PHP_EOL, 'status' );

        try
        {
            $compilationTimingStart = microtime( true );

            $compiledFiles = $compiler->compileAndWriteFilesAndManifests( $filePath, 'updateUserInterface' );

            $compilationTimingEnd = microtime( true );
            $compilationTotalTime = $compilationTimingEnd - $compilationTimingStart;
            $this->updateUserInterface( "[Compiling] Successfully compiled file \"{$filePath}\" in {$compilationTotalTime} seconds.", 'status' );

            $this->updateUserInterface( "\t\tIt resulted in " . count($compiledFiles) . ' compiled packages:' . PHP_EOL, 'output' );

            foreach( $compiledFiles as $compiledFile ) {
                $this->updateUserInterface( "\t\t{$compiledFile->sourcePath}\n", 'output' );
                $this->updateUserInterface( "\t\t\t{$compiledFile->compiledPath}\n", 'output' );
                $this->updateUserInterface( "\t\t\t{$compiledFile->manifestPath}\n", 'output' );
            }
        }
        catch ( MissingFileException $e )
        {
            $this->updateUserInterface( "[Compiling] [ERROR] {$e->getMessage()}", 'error' );
            $completelySuccessful = false;
        }
        catch ( ParsingException $e )
        {
            $this->updateUserInterface( "[Compiling] [ERROR] {$e->getMessage()}", 'error' );
            $completelySuccessful = false;
        }
        catch ( CannotWriteException $e )
        {
            $this->updateUserInterface( "[Compiling] [ERROR] Failed to compile \"{$e->getFilePath()}\" - " . $e->getMessage(), 'error' );
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
            new InputArgument('file',  InputArgument::REQUIRED | InputArgument::IS_ARRAY,    'Relative path to file to compile.'),
        ));
    }
}