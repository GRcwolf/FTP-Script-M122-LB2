<?php


namespace App\Command;


use App\Service\InvoiceSystemFtpService;
use App\Service\LoggerService;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GetReceiptsCommand extends Command
{
  /**
   * {@inheritDoc}
   */
  protected static $defaultName = 'app:invoices:receipts';

  /**
   * @var InvoiceSystemFtpService
   */
  private $ftpService;

  /**
   * @var LoggerService
   */
  private $logger;

  public function __construct(InvoiceSystemFtpService $ftpService, LoggerService $logger)
  {
    $this->ftpService = $ftpService;
    $this->logger = $logger;
    // Parent constructor call.
    parent::__construct();
  }

  /**
   * {@inheritDoc}
   */
  protected function configure()
  {
    // Set the command description.
    $this->setDescription('Downloads receipts.');
    // Set the help text for the command.
    $this->setHelp('Downloads the invoice receipts from the configured ftp server');
  }

  /**
   * {@inheritDoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output)
  {
    // Get receipts.
    try {
      $this->ftpService->downloadReceipts();
    } catch (Exception $exception) {
      $this->logger->error('An unexpected error happened while trying to download receipts. Error: ' . $exception->getMessage());
    }
    return 0;
  }
}
