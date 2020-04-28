<?php


namespace App\Service;


use App\Exception\MissingCsvDataLineException;
use App\Exception\WrongCsvDataException;
use App\Model\Invoice\InvoiceItemModel;
use App\Model\Invoice\InvoiceReceiverModel;
use App\Model\Invoice\InvoiceSenderModel;
use App\Model\InvoiceJobModel;
use DateTime;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class InvoiceParserService
{
  /**
   * @var Finder
   */
  private $finder;

  /**
   * @var string
   */
  private $filePattern = '/^\w+\d+\.data$/';

  /**
   * @var InvoiceExporterService
   */
  private $exporter;

  /**
   * @var LoggerService
   */
  private $logger;

  /**
   * InvoiceParserService constructor.
   * @param InvoiceExporterService $exporter
   */
  public function __construct(InvoiceExporterService $exporter, LoggerService $logger)
  {
    $this->finder = new Finder();
    $this->exporter = $exporter;
    $this->logger = $logger;
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
      try {
        $invoiceJob = $this->createInvoiceJobFromFile($file);
        // Add the generated invoice the the array of invoices.
        $invoiceJobs[] = $invoiceJob;
      } catch (MissingCsvDataLineException $exception) {
        $this->logger->error('The file ' . $file->getFilename() . ' has not all necessary data lines, aborting further processing.');
      } catch (WrongCsvDataException $exception) {
        $this->logger->error('The file ' . $file->getFilename() . ' has a data line with too many or not enough data, aborting further processing.');
      }
    }
    return $invoiceJobs;
  }

  /**
   * Creates an invoice job from a file.
   *
   * @param SplFileInfo $file
   * @return InvoiceJobModel
   *
   * @throws MissingCsvDataLineException
   *  Thrown if a data line is missing.
   * @throws WrongCsvDataException
   *  Thrown if a data line has to many of not enough values.
   */
  private function createInvoiceJobFromFile(SplFileInfo $file): InvoiceJobModel
  {
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
    $processedLines = [];
    $patternsToBePresent = ['/^Herkunft$/', '/^Endkunde$/', '/^RechnPos$/', '/^Rechnung_\d+$/'];
    /** @var string[] $fileValue */
    foreach ($fileValues as $fileValue) {
      // Add line identification to processed lines.
      $processedLines[] = $fileValue[0];
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
      // Inform about lines that don't match any of the above patterns.
      $this->logger->warning('Encountered an unexpected line identification (' . $fileValue[0] . ') in invoice ' . $file->getFilename() . '. The application doesn\'t know how to handle it, ignoring...');
    }
    // Check if all patterns are present.
    foreach ($patternsToBePresent as $pattern) {
      $isPresent = FALSE;
      foreach ($processedLines as $line) {
        // Check every pattern.
        if (preg_match($pattern, $line)) {
          $isPresent = TRUE;
          break;
        }
      }
      // Throw an exception if not all data lines are present.
      if (!$isPresent) {
        throw new MissingCsvDataLineException();
      }
    }
    return $invoiceJob;
  }

  /**
   * Parses the line containing the meta information about the invoice.
   * This method directly applies does values on the passed invoice model.
   *
   * @param InvoiceJobModel $invoiceJobModel
   *  An invoice model to which the values will be applied.
   * @param array $lineValues
   *  The single values of a line in the order that they are in the file.
   *
   * @throws WrongCsvDataException
   */
  private function parseMetaLine(InvoiceJobModel &$invoiceJobModel, array $lineValues)
  {
    // Validate the necessary amount of values are present.
    if (count($lineValues) !== 6) {
      throw new WrongCsvDataException();
    }
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
   *
   * @throws WrongCsvDataException
   */
  private function parseInvoiceSender(array $lineValues): InvoiceSenderModel
  {
    // Validate the necessary amount of values are present.
    if (count($lineValues) !== 8) {
      throw new WrongCsvDataException();
    }
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
   *
   * @throws WrongCsvDataException
   */
  private function parseInvoiceReceiver(array $lineValues): InvoiceReceiverModel
  {
    // Validate the necessary amount of values are present.
    if (count($lineValues) !== 5) {
      throw new WrongCsvDataException();
    }
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
   *
   * @throws WrongCsvDataException
   */
  private function parseInvoiceItem(array $lineValues): InvoiceItemModel
  {
    // Validate the necessary amount of values are present.
    if (count($lineValues) !== 7) {
      throw new WrongCsvDataException();
    }
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
      if (!$this->validateInvoice($invoice)) {
        return;
      }
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
    if (!$handleException || $isValid === TRUE) {
      return $isValid;
    }
    $invoiceNumber = $invoiceJobModel->getInvoiceNumber();
    $this->logger->alert('The invoice ' . $invoiceNumber . ' is unexpectedly missing some values.');
    return false;
  }
}
