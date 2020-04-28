<?php


namespace App\Service;


use App\Model\Invoice\InvoiceItemModel;
use App\Model\Invoice\InvoiceReceiverModel;
use App\Model\Invoice\InvoiceSenderModel;
use App\Model\InvoiceJobModel;
use DateTime;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class InvoiceParserService
{
  private $finder;

  private $filePattern = '/^\w+\d+\.data$/';

  private $exporter;

  /**
   * InvoiceParserService constructor.
   * @param InvoiceExporterService $exporter
   */
  public function __construct(InvoiceExporterService $exporter)
  {
    $this->finder = new Finder();
    $this->exporter = $exporter;
  }

  /**
   * Parses the xml and txt file of the invoices.
   */
  public function parseInvoices()
  {
    $invoiceFiles = $this->getInvoiceFiles();
    $invoiceModels = $this->createInvoiceJobs($invoiceFiles);
    $this->generateFiles($invoiceModels);
  }

  /**
   * Gets the invoice files and returns their path.
   *
   * @return SplFileInfo[]
   */
  private function getInvoiceFiles()
  {
    $invoiceFiles = [];
    // @TODO: Check if path is present.
    if (empty($_ENV['JOB_DIR'])) {
      return $invoiceFiles;
    }
    $path = $_ENV['JOB_DIR'];
    /** @var SplFileInfo $file */
    foreach ($this->finder->in($path) as $file) {
      if (preg_match($this->filePattern, $file->getFilename())) {
        $invoiceFiles[] = $file;
      }
    }
    return $invoiceFiles;
  }

  /**
   * Creates Invoice objects from the data of the files.
   *
   * @param array $files
   * @return array
   */
  private function createInvoiceJobs(array $files)
  {
    $invoiceJobs = [];
    /** @var SplFileInfo $file */
    foreach ($files as $file) {
      $handle = fopen($file->getPathname(), 'r');
      $fileValues = [];
      if ($handle) {
        while (($line = fgets($handle)) !== false) {
          $line = str_replace("\n", '', $line);
          $line = str_replace("\r", '', $line);
          $fileValues[] = explode(';', $line);
        }
      }
      $invoiceJob = new InvoiceJobModel();
      /** @var string[] $fileValue */
      foreach ($fileValues as $fileValue) {
        // @ TODO: Make sure all necessary lines are present.
        // Check if the line contains the basic information of the invoice.
        if (preg_match('/^Rechnung_\d+$/', $fileValue[0])) {
          $this->parseMetaLine($invoiceJob, $fileValue);
        } // Check if the line contains the information about the invoice sender.
        elseif (preg_match('/^Herkunft$/', $fileValue[0])) {
          // Parse the values to an InvoiceSenderModel.
          $invoiceSender = $this->parseInvoiceSender($fileValue);
          // Set the invoice sender.
          $invoiceJob->setSender($invoiceSender);
        } // Check if the line contains the information about the invoice receiver.
        elseif (preg_match('/^Endkunde$/', $fileValue[0])) {
          // Parse the values to an InvoiceReceiverModel.
          $invoiceReceiver = $this->parseInvoiceReceiver($fileValue);
          // Set the invoice receiver.
          $invoiceJob->setReceiver($invoiceReceiver);
        } // Check if the line contains the information about an invoice item.
        elseif (preg_match('/^RechnPos$/', $fileValue[0])) {
          // Create an invoice item with the corresponding values.
          $invoiceItem = $this->parseInvoiceItem($fileValue);
          // Add the invoice item to the invoices.
          $invoiceJob->addInvoiceItem($invoiceItem);
        }
        // @TODO: Figure out what to do if the code reaches this section.
      }
      // Add the generated invoice the the array of invoices.
      $invoiceJobs[] = $invoiceJob;
    }
    return $invoiceJobs;
  }

  /**
   * Parses the line containing the meta information about the invoice.
   * This method directly applies does values on the passed invoice model.
   *
   * @param InvoiceJobModel $invoiceJobModel
   *  An invoice model to which the values will be applied.
   * @param array $lineValues
   *  The single values of a line in the order that they are in the file.
   */
  private function parseMetaLine(InvoiceJobModel &$invoiceJobModel, array $lineValues)
  {
    // Get the invoice number.
    $invoiceNumber = str_replace('Rechnung_', '', $lineValues[0]);
    // Set the invoice number.
    $invoiceJobModel->setInvoiceNumber($invoiceNumber);
    // Get the job id.
    $jobId = str_replace('Auftrag_', '', $lineValues[1]);
    // Set the job id.
    $invoiceJobModel->setJobId($jobId);
    // Get the location.
    $location = $lineValues[2];
    // Set location.
    $invoiceJobModel->setLocation($location);
    // Concat date and time.
    $dateString = $lineValues[3] . '-' . $lineValues[4];
    // Create DateTime object.
    $date = DateTime::createFromFormat('d.m.Y-G:i:s', $dateString);
    // Get the days to pay.
    $daysToPay = str_replace('ZahlungszielInTagen_', '', $lineValues[5]);
    // Set days to pay to invoice.
    $invoiceJobModel->setDaysToPay($daysToPay);
    // Set the DateTime object to the invoice.
    $invoiceJobModel->setDateTime($date);
  }

  /**
   * Parses the invoice sender line.
   * Generates an InvoiceSender object with the line values and returns it.
   *
   * @param array $lineValues
   *  Array of the values in the line. Must be in the same order as in the file.
   *
   * @return InvoiceSenderModel
   *  An InvoiceSender object with the values specified in the line values.
   */
  private function parseInvoiceSender(array $lineValues)
  {
    // Create a new InvoiceSenderModel object.
    $invoiceSender = new InvoiceSenderModel();
    // Set the customer number.
    $invoiceSender->setCustomerNumber($lineValues[1]);
    // Set the salutation.
    $invoiceSender->setSalutation($lineValues[2]);
    // Set the name.
    $invoiceSender->setName($lineValues[3]);
    // Set the address.
    $invoiceSender->setAddress($lineValues[4]);
    // Set the zip code and the location.
    $invoiceSender->setZipLocation($lineValues[5]);
    // Set the VAT number.
    $invoiceSender->setVatNumber($lineValues[6]);
    // Set the email address.
    $invoiceSender->setEmail($lineValues[7]);
    // Return thr invoice sender.
    return $invoiceSender;
  }

  /**
   * Parses the invoice receiver line.
   * Creates an InvoiceReceiverModel object an populates it with the corresponding values.
   *
   * @param array $lineValues
   *   Array of the values in the line. Must be in the same order as in the file.
   *
   * @return InvoiceReceiverModel
   *  The invoice receiver with the populated values.
   */
  private function parseInvoiceReceiver(array $lineValues)
  {
    // Create a new InvoiceReceiverModel object.
    $invoiceReceiver = new InvoiceReceiverModel();
    // Set the customer id.
    $invoiceReceiver->setCustomerId($lineValues[1]);
    // Set the name.
    $invoiceReceiver->setName($lineValues[2]);
    // Set the address.
    $invoiceReceiver->setAddress($lineValues[3]);
    // Set the zip and the location
    $invoiceReceiver->setZipLocation($lineValues[4]);
    // Return the invoice receiver with the populated values.
    return $invoiceReceiver;
  }

  /**
   * Parses a invoice item line.
   * Creates an InvoiceItemModel object and populates it with the line values.
   *
   * @param array $lineValues
   *  Array of the values in the line. Must be in the same order as in the file.
   *
   * @return InvoiceItemModel
   *  A populated invoice item object.
   */
  private function parseInvoiceItem(array $lineValues)
  {
    // Create a new invoice item.
    $invoiceItem = new InvoiceItemModel();
    // Set item index.
    $invoiceItem->setIndex($lineValues[1]);
    // Set item description.
    $invoiceItem->setItemDescription($lineValues[2]);
    // Set the count.
    $invoiceItem->setCount($lineValues[3]);
    // Set price per unit.
    $invoiceItem->setPricePerUnit($lineValues[4]);
    // Set total price.
    $invoiceItem->setTotalPrice($lineValues[5]);
    // Get vat rate.
    $vatRate = [];
    preg_match('/\d+\.\d+/', $lineValues[6], $vatRate);
    // Set vat rate.
    $invoiceItem->setVatRate($vatRate[0]);
    // Return the populated invoice item.
    return $invoiceItem;
  }

  /**
   * Generates the xml and txt files for the invoices.
   *
   * @param InvoiceJobModel[] $invoices
   */
  private function generateFiles(array $invoices): void
  {
    foreach ($invoices as $invoice) {
      $this->exporter->saveInvoiceXml($invoice);
      $this->exporter->saveTxtInvoice($invoice);
    }
  }

  /**
   * Validates the invoice the invoice.
   *
   * @param InvoiceJobModel $invoiceJobModel
   * @param bool $handleException
   * @return bool
   */
  private function validateInvoice(InvoiceJobModel $invoiceJobModel, bool $handleException = TRUE): bool
  {
    $isValid = $invoiceJobModel->validate();
    if (!$handleException) {
      return $isValid;
    }
  }
}
