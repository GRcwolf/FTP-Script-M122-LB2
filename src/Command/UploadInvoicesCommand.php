<?php


namespace App\Command;


use App\Exception\FtpConnectionFailedException;
use App\Service\InvoiceSystemFtpService;
use App\Service\LoggerService;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UploadInvoicesCommand extends Command
{
  /**
   * {@inheritDoc}
   */
  protected static $defaultName = 'app:invoices:upload';

  /**
   * @var InvoiceSystemFtpService
   */
  private $uploadService;

  /**
   * @var LoggerService
   */
  private $logger;

  /**
   * {@inheritDoc}
   */
  public function __construct(InvoiceSystemFtpService $uploadService, LoggerService $logger)
  {
    parent::__construct();
    $this->uploadService = $uploadService;
    $this->logger = $logger;
  }

  /**
   * {@inheritDoc}
   */
  protected function configure()
  {
    // Set command description.
    $this->setDescription('Uploads the invoices');

    // Set command help.
    $this->setHelp('Uploads the invoices. Uploads the xml and the txt invoice');
  }

  /**
   * {@inheritDoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output)
  {
    try {
      $this->uploadService->uploadAllInvoiceFiles();
    } catch (FtpConnectionFailedException $exception) {
      // Exception has already been logged.
    } catch (Exception $exception) {
      $this->logger->error('An error occurred while uploading the invoice files. Error: ' . $exception->getMessage());
    }
    return 0;
  }
}
