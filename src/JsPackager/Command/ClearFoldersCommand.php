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

class ClearFoldersCommand extends Command
{

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('clear-folders')
            ->setDescription('Clear compiled files and manifests in given folder(s).')
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

        $success = false;

        $this->logger = new ConsoleLogger($output);

        $compiler = new Compiler();

        foreach( $foldersToClear as $folderPath )
        {
            $this->logger->info("Confirming path to '{$folderPath}'.");
            $realPathResult = realpath( $folderPath );
            if ( $realPathResult === false ) {
                $this->logger->error("Path '{$folderPath}' resolved to nowhere.");
                continue;
            } else {
                $folderPath = $realPathResult;
            }

            $this->logger->notice("Clearing all packages (compiled files and manifests) in {$folderPath}...");

            $success = $compiler->clearPackages($folderPath);

            if ( !$success )
            {
                $this->logger->error( "An error occurred while clearing packages. Halting compilation." );
                return 1; // Something went wrong
            }
        }

        $this->logger->notice( "Finished clearing packages." );

        return 0; // A-OK error code
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
            new InputArgument('folder',  InputArgument::REQUIRED | InputArgument::IS_ARRAY,    'Relative path to folder to clear compiled files and manifests in.'),
        ));
    }
}