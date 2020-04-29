<?php


namespace App\Command;


use App\Service\CustomerFtpService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GetJobFilesCommand extends Command
{
  /**
   * {@inheritDoc}
   */
  protected static $defaultName = 'app:invoices:import';

  /**
   * \App\Service\ImportJobsFtpService object.
   *
   * @var CustomerFtpService
   */
  private $jobImporter;

  /**
   * GetJobFilesCommand constructor.
   *
   * @param CustomerFtpService $jobImporter
   */
  public function __construct(CustomerFtpService $jobImporter)
  {
    $this->jobImporter = $jobImporter;

    // Parent constructor call.
    parent::__construct();
  }

  /**
   * {@inheritDoc}
   */
  protected function configure()
  {
    // Set the command description.
    $this->setDescription('Imports invoice jobs.');
    // Set the help text for the command.
    $this->setHelp('Imports the invoice jobs from the configured ftp server');
  }

  /**
   * {@inheritDoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output)
  {
    // Get all job files.
    $this->jobImporter->getJobs();
    return 0;
  }
}
