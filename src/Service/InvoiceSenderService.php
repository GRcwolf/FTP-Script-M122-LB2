<?php


namespace App\Service;


use App\Exception\InvalidInvoiceFileNameException;
use App\Exception\MissingInvoiceFileException;
use App\Exception\ZipArchiveNotCreatableException;
use App\Model\InvoiceJobModel;
use Exception;
use Ijanki\Bundle\FtpBundle\Ftp;
use Swift_Attachment;
use Swift_Mailer;
use Swift_Message;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use ZipArchive;

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

  /**
   * @var Finder
   */
  private $finder;

  /**
   * @var Swift_Mailer
   */
  private $mailer;

  /**
   * @var CleanUpService
   */
  private $cleanUpService;

  /**
   * @var Environment
   */
  private $renderer;

  /**
   * @var Ftp|null|bool
   */
  private $ftpClient;

  /**
   * @var CustomerFtpService
   */
  private $ftpService;

  /**
   * InvoiceSenderService constructor.
   *
   * @param Filesystem $filesystem
   * @param LoggerService $logger
   * @param ContainerParametersHelper $helper
   * @param Swift_Mailer $mailer
   * @param CleanUpService $cleanUpService
   * @param Environment $twig
   */
  public function __construct(Filesystem $filesystem, LoggerService $logger, ContainerParametersHelper $helper, Swift_Mailer $mailer, CleanUpService $cleanUpService, Environment $twig, CustomerFtpService $ftpService)
  {
    $this->helper = $helper;
    $this->ftpService = $ftpService;
    $this->renderer = $twig;
    $this->cleanUpService = $cleanUpService;
    $this->filesystem = $filesystem;
    $this->logger = $logger;
    $this->mailer = $mailer;
    $this->finder = new Finder();
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

  /**
   * Sends all invoices.
   */
  public function sendAllInvoices(): void
  {
    $receipts = $this->collectReceipts();
    foreach ($receipts as $receipt) {
      $invoiceNumbers = [];
      $invoices = $this->getInvoiceFileNamesFromReceipt($receipt);
      foreach ($invoices as $invoice) {
        try {
          $invoiceNumbers[] = $this->getInvoiceNumberByFileName($invoice);
        } catch (InvalidInvoiceFileNameException $e) {
          $this->logger->notice('Invalid invoice filename provided: ' . $invoice);
        }
      }
      $emails = $this->getInvoiceEmailsFromInvoiceNumbers($invoiceNumbers);
      // Send the emails.
      foreach ($emails as $email => $invoiceNumbers) {
        try {
          $this->notify($email, $invoiceNumbers, $receipt);
        } catch (ZipArchiveNotCreatableException $exception) {
          $this->logger->error('Could not create zip archive for ' . $receipt->getFilename());
        } catch (Exception $exception) {
          $this->logger->error('An error occurred while trying to send the invoice email: ' . $exception->getMessage());
        }
      }
    }
  }

  /**
   * Notifies the specified email.
   *
   * @param string $email
   * @param array $invoiceNumbers
   * @param SplFileInfo $receipt
   * @throws ZipArchiveNotCreatableException
   * @throws LoaderError
   * @throws RuntimeError
   * @throws SyntaxError
   */
  private function notify(string $email, array $invoiceNumbers, SplFileInfo $receipt): void
  {
    $archive = $this->createZip($invoiceNumbers, $receipt);
    $attachment = Swift_Attachment::fromPath($archive);
    $message = new Swift_Message("Invoices and Receipt " . $receipt->getFilenameWithoutExtension());
    $message->setFrom($_ENV['SITE_MAIL'])
      ->setTo($email)
      ->setBcc($_ENV['ADMIN_EMAIL'])
      ->setBody(
        $this->renderer->render(
          'emails/invoice.html.twig',
          ['receipt_no' => $receipt->getFilenameWithoutExtension()]
        ),
        'text/html'
      )
      ->addPart(
        $this->renderer->render(
          'emails/invoice.txt.twig',
          ['receipt_no' => $receipt->getFilenameWithoutExtension()]
        ),
        'text/plain'
      )
      ->attach($attachment);
    $this->mailer->send($message);
    $this->logger->info('The email about the receipt ' . $receipt->getFilename() . ' has been send');
    // Delete all files which are no longer used.
    $this->uploadZipFile($archive);
    $this->cleanUpService->deleteFile($receipt->getPathname());
    foreach ($invoiceNumbers as $invoiceNumber) {
      $dataPath = $this->helper->getTempFilesFolder() . '/data/' . $invoiceNumber;
      $this->cleanUpService->deleteFile($dataPath);
      try {
        $file = $this->getInvoiceFileFromNumber($invoiceNumber);
      } catch (MissingInvoiceFileException $exception) {
        $this->logger->warning($exception->getMessage());
        continue;
      }
      $this->cleanUpService->deleteFile($file->getPathname());
    }
  }

  private function uploadZipFile(string $filePath): void
  {
    if (is_null($this->ftpClient)) {
      try {
        $this->ftpClient = $this->ftpService->getClient();
        $this->ftpClient->chdir($_ENV['FTP_SCHOOLER_IN']);
      } catch (Exception $e) {
        $this->ftpClient = FALSE;
        $this->logger->error('Could not get ftp client to upload zip files: ' . $e->getMessage());
      }
    }
    if ($this->ftpClient instanceof Ftp) {
      $fileName = $this->getFileNameFromFullPath($filePath);
      $this->ftpClient->put($fileName, $filePath, FTP_BINARY);
      $this->cleanUpService->deleteFile($filePath);
    }
  }

  /**
   * Return the file name from a path.
   *
   * @param string $path
   * @return string
   */
  private function getFileNameFromFullPath(string $path): string
  {
    $pos = strrpos($path, '/');
    if ($pos === FALSE) {
      return $path;
    }
    return substr($path, $pos + 1);
  }

  /**
   * Returns the path to the zip archive.
   *
   * @param array $invoiceNumbers
   * @param SplFileInfo $receipt
   * @return string
   * @throws ZipArchiveNotCreatableException
   */
  private function createZip(array $invoiceNumbers, SplFileInfo $receipt): string
  {
    $zip = new ZipArchive();
    $path = $this->helper->getTempFilesFolder() . '/zip';
    $this->filesystem->mkdir($path);
    $filename = $path . '/' . sha1($receipt->getFilenameWithoutExtension() . time() . rand()) . '.zip';
    if ($zip->open($filename, ZipArchive::CREATE) !== TRUE) {
      $this->logger->error('Could not create zip archive ' . $filename);
      throw new ZipArchiveNotCreatableException();
    }
    // Add the receipt to the archive.
    $zip->addFile($receipt->getPathname(), $receipt->getFilename());
    foreach ($invoiceNumbers as $invoiceNumber) {
      try {
        // Get the invoice file.
        $file = $this->getInvoiceFileFromNumber($invoiceNumber);
      } catch (MissingInvoiceFileException $e) {
        $this->logger->warning($e->getMessage());
        continue;
      }
      // Add the invoice file.
      $zip->addFile($file->getPathname(), 'invoices/' . $file->getFilename());
    }
    $zip->close();
    return $filename;
  }

  /**
   * Returns an invoice file by its number.
   *
   * @param int $invoiceNumber
   * @return SplFileInfo
   * @throws MissingInvoiceFileException
   */
  private function getInvoiceFileFromNumber(int $invoiceNumber): SplFileInfo
  {
    $InvoicePath = $this->helper->getTempFilesFolder() . '/invoices';
    foreach ($this->finder->in($InvoicePath) as $file) {
      if (preg_match('/_' . $invoiceNumber . '_/', $file->getFilename())) {
        return $file;
      }
    }
    throw new MissingInvoiceFileException('Could not find invoice file with number ' . $invoiceNumber);
  }

  /**
   * Returns a list of all emails which should be notified about this receipt.
   *
   * @param array $invoiceNumbers
   * @return array
   */
  private function getInvoiceEmailsFromInvoiceNumbers(array $invoiceNumbers): array
  {
    $emails = [];
    foreach ($invoiceNumbers as $invoiceNumber) {
      try {
        $invoice = $this->getInvoiceFromNumber($invoiceNumber);
      } catch (Exception $e) {
        $this->logger->warning('Couldn\'t get invoice from file, reason: ' . $e->getMessage());
        continue;
      }
      $emails[$invoice->getSender()->getEmail()][] = $invoiceNumber;
    }
    return $emails;
  }

  /**
   * Gets the invoice number from an invoice filename.
   *
   * @param string $fileName
   * @return int
   * @throws InvalidInvoiceFileNameException
   */
  private function getInvoiceNumberByFileName(string $fileName): int
  {
    $matches = [];
    preg_match('/(?<=_)\d+(?=_invoice)/', $fileName, $matches);
    if (count($matches) === 1) {
      return $matches[0];
    }
    throw new InvalidInvoiceFileNameException();
  }

  /**
   * Returns an invoice.
   *
   * @param int $invoiceNumber
   * @return InvoiceJobModel
   */
  private function getInvoiceFromNumber(int $invoiceNumber): InvoiceJobModel
  {
    $dataPath = $this->helper->getTempFilesFolder() . '/data';
    $file = $dataPath . '/' . $invoiceNumber;
    $serializedData = file_get_contents($file);
    /** @var InvoiceJobModel $invoice */
    return unserialize($serializedData);
  }

  /**
   * Returns an array of the file names of the txt invoices present in the receipt.
   *
   * @param SplFileInfo $file
   * @return array
   */
  private function getInvoiceFileNamesFromReceipt(SplFileInfo $file): array
  {
    $handle = fopen($file->getPathname(), 'r');
    $fileNames = [];
    if ($handle) {
      while (($line = fgets($handle)) !== false) {
        preg_match('/K\d+_\d+_invoice\.txt/', $line, $fileNames);
      }
    }
    return $fileNames;
  }

  /**
   * Collects all local receipts.
   *
   * @return SplFileInfo[]
   */
  private function collectReceipts(): array
  {
    $files = [];
    // Get files.
    $receiptsPath = $this->helper->getTempFilesFolder() . '/receipts';
    $finderFiles = $this->finder->in($receiptsPath);
    // Save the files in an array.
    foreach ($finderFiles as $file) {
      $files[] = $file;
    }
    return $files;
  }
}
