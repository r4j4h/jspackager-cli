<?php

namespace JsPackager\Command;

use JsPackager\Compiler;
use JsPackager\DefaultRemotePath;
use JsPackager\DependencyTree;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

class ResolveFilesCommand extends Command
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
            ->setName('resolve-files')
            ->setDescription('Resolve a file\'s dependencies.')
            ->setDefinition($this->createDefinition())
            ->setHelp(<<<HELPBLURB
<info>%command.name%</info> provides an easy way to programmatically use JsPackager.

\t<info>%command.full_name% src/main.js</info> returns the dependent files, including src/main.js, separated by newlines.

By default, output is fairly quiet unless there are problems. To see what's going on, increase the verbosity level.

\t<info>%command.full_name% src/main.js -vv</info>

Multiple files are supported, and each input will be separated with an extra newline.

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
     * @var OutputInterface
    */
    protected $output;

    /**
     * @var String
     */
    protected $defaultRemotePath;

    /**
     * @var String
     */
    protected $remotePath;

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $foldersToClear = $input->getArgument('file');
        $asJson = $input->getOption('json');
        $excludingStylesheets = $input->getOption('excludeStylesheets');
        $excludingScripts = $input->getOption('excludeScripts');
        $remoteFolderPath = $input->getOption('remotePath');


        $completelySuccessful = true;

        $this->logger = new ConsoleLogger($output);
        $this->output = $output;

        $compiler = new Compiler();
        if ( $remoteFolderPath ) {
            $this->logger->info('Remote base path given: "'. $remoteFolderPath . '".');
            $this->remotePath = $remoteFolderPath;
        } else {
            $this->logger->info('No remote base path given, using "'. $this->defaultRemotePath . '" as default.');
            $this->remotePath = $this->defaultRemotePath;
        }
        $compiler->remoteFolderPath = $this->remotePath;
        $compiler->logger = $this->logger;

        foreach( $foldersToClear as $inputFile )
        {
            $this->logger->info("Resolving dependencies from file '{$inputFile}'.");

            $compilationSuccessful = $this->compileFile( $compiler, $inputFile, $asJson, $excludingScripts, $excludingStylesheets );

            // If this file failed to compile, we were not completely successful
            if ( $compilationSuccessful === false )
            {
                $completelySuccessful = false;
            }
        }

        return (int)(!$completelySuccessful); // A-OK error code
    }


    /**
     * Compile a file using a Compiler, reporting back to the UI
     *
     * @param Compiler $compiler
     * @param string $filePath
     * @param bool $asJson
     * @param bool $excludingScripts
     * @param bool $excludingStylesheets
     */
    function compileFile($compiler, $filePath, $asJson, $excludingScripts, $excludingStylesheets) {
        $completelySuccessful = true;

        $this->logger->info("Confirming path to '{$filePath}'.");
        $realPathResult = realpath( $filePath );
        if ( $realPathResult === false ) {
            $this->logger->error("Path '{$filePath}' resolved to nowhere.");
            return false;
        } else {
            $filePath = $realPathResult;
        }

        $this->logger->notice( "Resolving \"{$filePath}\" for dependencies..." );

        try
        {
            $dependencyTree = new DependencyTree( $filePath, null, false, $this->logger, $this->remotePath );
            $dependencyTree->logger = $this->logger;

            $files = $dependencyTree->flattenDependencyTreeIntoAssocArrays();
            $returningFiles = array();
            if ( !$excludingStylesheets ) {
                $returningFiles = array_merge($returningFiles, $files['stylesheets']);
            }
            if ( !$excludingScripts ) {
                $returningFiles = array_merge($returningFiles, $files['scripts']);
            }

            if ( $asJson ) {
                $this->output->writeln( json_encode( $returningFiles ) );
            } else {
                foreach ($returningFiles as $file) {
                    $this->output->writeln($file);
                }
            }
        }
        catch ( \Exception $e )
        {
            $this->updateUserInterface( "[ResolveFiles] [ERROR] {$e->getMessage()}", 'error' );
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
            new InputArgument('file',  InputArgument::REQUIRED | InputArgument::IS_ARRAY,    'Relative path to file to resolve.'),
            new InputOption('json', null, InputOption::VALUE_NONE, "Return results as a JSON array"),
            new InputOption('excludeStylesheets', null, InputOption::VALUE_NONE, "Exclude stylesheets from the return results"),
            new InputOption('excludeScripts', null, InputOption::VALUE_NONE, "Exclude scripts from the return results"),
            new InputOption('remotePath',  'r', InputArgument::OPTIONAL,    'Relative or absolute base path to use for parsing @remote files.', $this->defaultRemotePath),
        ));
    }
}