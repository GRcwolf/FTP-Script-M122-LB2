<?php


namespace App\Service;


use App\Model\InvoiceJobModel;
use Symfony\Component\Filesystem\Filesystem;

class InvoiceSenderService
{
  /**
   * @var LoggerService
   */
  private $logger;

  /**
   * @var Filesystem
   */
  private $filesystem;

  /**
   * @var ContainerParametersHelper
   */
  private $helper;

  public function __construct(Filesystem $filesystem, LoggerService $logger, ContainerParametersHelper $helper)
  {
    $this->helper = $helper;
    $this->filesystem = $filesystem;
    $this->logger = $logger;
  }

  /**
   * Saves the data of an invoice.
   *
   * @param InvoiceJobModel $invoice
   */
  public function saveInvoiceInformation(InvoiceJobModel $invoice): void
  {
    $pathToStore = $this->helper->getTempFilesFolder() . '/data';
    $fileName = $invoice->getInvoiceNumber();
    $data = serialize($invoice);
    $this->filesystem->dumpFile($pathToStore . '/' . $fileName, $data);
  }
}
