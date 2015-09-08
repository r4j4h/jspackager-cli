<?php

namespace JsPackager\Command;

use JsPackager\CompiledFileAndManifest\CompiledAndManifestFileUtilityService;
use JsPackager\CompiledFileAndManifest\FilenameConverter;
use JsPackager\Compiler;
use JsPackager\Compiler\FileCompilationResult;
use JsPackager\DefaultRemotePath;
use JsPackager\Helpers\FileFinder;
use JsPackager\Helpers\FileHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

class CompileFoldersCommand extends Command
{

    public function __construct($name = null) {
        $defaultRemotePathInstance = new DefaultRemotePath();
        $this->defaultRemotePath = $defaultRemotePathInstance->getDefaultRemotePath();
        return parent::__construct($name);
    }

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
<info>%command.name%</info> provides an easy way to compile files in the given folder(s).

\t<info>%command.full_name% src/</info> compiles everything in the local ./src folder.

By default, output is fairly quiet unless there are problems. To see what's going on, increase the verbosity level.

\t<info>%command.full_name% src/ -vv</info>

Multiple folders are supported.

\t<info>%command.full_name% src/main-files/ src/batched-libs/</info>.
HELPBLURB
            );
        ;
    }

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var String
     */
    protected $defaultRemotePath;

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $foldersToClear = $input->getArgument('folder');
        $remoteFolderPath = $input->getOption('remotePath');

        $completelySuccessful = true;

        $this->logger = new ConsoleLogger($output);

        $compilerTimeStart = microtime( true );

        $compiler = new Compiler('shared', '@remote', $this->logger, false, new FileHandler());
        if ( $remoteFolderPath ) {
            $this->logger->info('Remote base path given: "'. $remoteFolderPath . '".');
            $compiler->remoteFolderPath = $remoteFolderPath;
        } else {
            $defaultRemotePath = $this->defaultRemotePath;
            $this->logger->info('No remote base path given, using "'. $defaultRemotePath . '" as default.');
            $compiler->remoteFolderPath = $defaultRemotePath;
        }
        $compiler->logger = $this->logger;

        $foldersCompiled = array();

        foreach( $foldersToClear as $inputFolder )
        {
            $this->logger->info("Compiling folder '{$inputFolder}'.");

            $compilationSuccessful = $this->analyzeAndCompileFolder( $compiler, $inputFolder );

            array_push($foldersCompiled, array($inputFolder, $compilationSuccessful?'<info>Yes</info>':'<error>No</error>'));

            // If this folder failed to compile completely, we were not completely successful
            if ( $compilationSuccessful === false )
            {
                $completelySuccessful = false;
            }
        }

        $compilerTimeEnd = microtime( true );
        $compilerTimeTotal = $compilerTimeEnd - $compilerTimeStart;
        $this->logger->notice( "JsPackager compiler finished folder compilation. (Total time: {$compilerTimeTotal} seconds)." );

        $table = new Table($output);
        $table->setHeaders(array('Folder','Successfully Compiled'));
        $table->setRows($foldersCompiled);
        $table->render();

        return (int)(!$completelySuccessful); // A-OK error code
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

        $finder = new FileFinder($this->logger);

        try
        {
            $compilationTimingStart = microtime( true );

            $filesToCompile = $finder->parseFolderForSourceFiles( $folderPath );
            $numberOfFilesToCompile = count($filesToCompile);
            $numberOfFilesCompiled = 0;

            $this->updateUserInterface( "Found {$numberOfFilesToCompile} files to compile:" , 'status' );

            foreach( $filesToCompile as $fileToCompile )
            {
                $this->updateUserInterface( "\t{$fileToCompile}" , 'status' );
            }

            foreach( $filesToCompile as $fileToCompile )
            {
                $compilationSuccessful = $this->compileFile($compiler, $fileToCompile);

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

            $compiledFiles = $compiler->compileAndWriteFilesAndManifests( $filePath );
            $compiledFiles = $compiledFiles->getValuesAsArray();

            $compilationTimingEnd = microtime( true );
            $compilationTotalTime = $compilationTimingEnd - $compilationTimingStart;
            $this->updateUserInterface( "[Compiling] Successfully compiled file \"{$filePath}\" in {$compilationTotalTime} seconds.", 'status' );

            $this->updateUserInterface( "\t\tIt resulted in " . count($compiledFiles) . ' compiled packages:' . PHP_EOL, 'output' );

            foreach( $compiledFiles as $compiledFile ) {
                /**
                 * @var FileCompilationResult $compiledFile
                 */
                $this->updateUserInterface( "\t\t{$compiledFile->getSourcePath()}\n", 'output' );
                $this->updateUserInterface( "\t\t\t{$compiledFile->getCompiledPath()}\n", 'output' );
                $this->updateUserInterface( "\t\t\t{$compiledFile->getManifestPath()}\n", 'output' );
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
            new InputArgument('folder',  InputArgument::REQUIRED | InputArgument::IS_ARRAY,    'Relative path to folder to compile the files in.'),
            new InputOption('remotePath',  'r', InputArgument::OPTIONAL,    'Relative or absolute base path to use for parsing @remote files.', $this->defaultRemotePath),
        ));
    }
}