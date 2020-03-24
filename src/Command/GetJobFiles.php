<?php


namespace App\Command;


use App\Service\ImportJobsFtp;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GetJobFiles extends Command
{
    /**
     * {@inheritDoc}
     */
    protected static $defaultName = 'app:get-jobs';

    /**
     * \App\Service\ImportJobsFtp object.
     *
     * @var ImportJobsFtp
     */
    private $jobImporter;

    public function __construct(ImportJobsFtp $jobImporter)
    {
        $this->jobImporter = $jobImporter;

        // Parent constructor call.
        parent::__construct();
    }

    protected function configure()
    {
        // Set the command description.
        $this->setDescription('Imports invoice jobs.');
        // Set the help text for the command.
        $this->setHelp('Imports the invoice jobs from the configured ftp server');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->jobImporter->getJobs();
        return 0;
    }
}
