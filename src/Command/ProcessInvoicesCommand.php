<?php


namespace App\Command;


use App\Service\InvoiceParserService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ProcessInvoicesCommand extends Command
{
  protected static $defaultName = 'app:invoices:process';

  /**
   * InvoiceParserService object.
   *
   * @var InvoiceParserService
   */
  private $invoiceParser;

  /**
   * ProcessInvoicesCommand constructor.
   *
   * @param InvoiceParserService $invoiceParser
   */
  public function __construct(InvoiceParserService $invoiceParser)
  {
    parent::__construct();
    $this->invoiceParser = $invoiceParser;
  }


  protected function configure()
    {
        // Set command description.
        $this->setDescription('Processes the invoices');

        // Set command help.
        $this->setHelp('Processes the invoices. Generates an invoice as txt an as xml');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
      $this->invoiceParser->parseInvoices();
      return 0;
    }
}
