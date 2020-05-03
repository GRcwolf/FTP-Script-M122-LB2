<?php


namespace App\Command;


use App\Service\InvoiceSenderService;
use App\Service\LoggerService;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SendInvoicesCommand extends Command
{
  /**
   * {@inheritDoc}
   */
  protected static $defaultName = 'app:invoices:send';

  /**
   * @var LoggerService
   */
  private $logger;

  /**
   * @var InvoiceSenderService|string|null
   */
  private $sender;

  /**
   * {@inheritDoc}
   */
  public function __construct(InvoiceSenderService $sender, LoggerService $logger)
  {
    parent::__construct();
    $this->sender = $sender;
    $this->logger = $logger;
  }

  /**
   * {@inheritDoc}
   */
  protected function configure()
  {
    // Set command description.
    $this->setDescription('Sends the invoices');

    // Set command help.
    $this->setHelp('Sends the invoices. Includes the invoice and the receipt as zip file.');
  }

  /**
   * {@inheritDoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output)
  {
    try {
      $this->sender->sendAllInvoices();
    } catch (Exception $exception) {
      $this->logger->error('An error occurred sending the invoices. Error: ' . $exception->getMessage());
    }
    return 0;
  }
}
