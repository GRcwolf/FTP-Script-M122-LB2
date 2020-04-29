<?php


namespace App\Service;


use App\Exception\FtpConnectionFailedException;
use Exception;
use Ijanki\Bundle\FtpBundle\Ftp;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class InvoiceSystemFtpService
{
  /**
   * @var LoggerService
   */
  private $logger;

  /**
   * @var ContainerParametersHelper
   */
  private $helper;

  /**
   * @var Ftp
   */
  private $ftp;

  /**
   * @var string
   */
  private $inDir;

  /**
   * @var string
   */
  private $outDir;

  /**
   * @var Finder
   */
  private $finder;

  /**
   * @var CleanUpService
   */
  private $cleanUpService;

  /**
   * @var Filesystem
   */
  private $filesystem;

  /**
   * InvoiceSystemFtpService constructor.
   *
   * @param LoggerService $logger
   * @param ContainerParametersHelper $helper
   * @param Ftp $ftp
   * @param CleanUpService $cleanUpService
   * @param Filesystem $filesystem
   */
  public function __construct(LoggerService $logger, ContainerParametersHelper $helper, Ftp $ftp, CleanUpService $cleanUpService, Filesystem $filesystem)
  {
    $this->logger = $logger;
    $this->cleanUpService = $cleanUpService;
    $this->finder = new Finder();
    $this->helper = $helper;
    $this->ftp = $ftp;
    $this->filesystem = $filesystem;
  }

  /**
   * Initializes the FTP connection
   *
   * @throws FtpConnectionFailedException
   */
  private function initializeConnection(): void
  {
    try {
      $host = $_ENV['FTP_INVOICE_HOST'];
      $user = $_ENV['FTP_INVOICE_USER'];
      $password = $_ENV['FTP_INVOICE_PASSWORD'];
      $this->inDir = $_ENV['FTP_INVOICE_IN'];
      $this->outDir = $_ENV['FTP_INVOICE_OUT'];
      $this->ftp->connect($host);
      $this->ftp->login($user, $password);
    } catch (Exception $e) {
      $this->logger->critical('Could not connect to invoice system on ' . $host . ' using the provided credentials. Error: ' . $e->getMessage());
      throw new FtpConnectionFailedException();
    }
  }

  /**
   * Uploads all invoice files to the ftp server.
   *
   * @throws FtpConnectionFailedException
   */
  public function uploadAllInvoiceFiles(): void
  {
    // Initialize the FTP connection.
    $this->initializeConnection();
    $files = $this->getFilesToUpload();
    $this->ftp->chdir($this->inDir);
    // Upload all files.
    foreach ($files as $file) {
      $this->uploadSingleFile($file);
    }
  }

  /**
   * Uploads a single file.
   *
   * @param SplFileInfo $file
   */
  private function uploadSingleFile(SplFileInfo $file): void
  {
    $this->ftp->put($file->getBasename(), $file->getPathname(), FTP_BINARY);
    if ($file->getExtension() === 'txt') {
      $this->cleanUpService->deleteTxtInvoice($file->getBasename());
    } elseif ($file->getExtension() === 'xml') {
      $this->cleanUpService->deleteXmlInvoice($file->getBasename());
    }
  }

  /**
   * Gets the invoice files.
   *
   * @return SplFileInfo[]
   */
  private function getFilesToUpload(): array
  {
    $tmpPath = $this->helper->getTempFilesFolder();
    $xmlPath = $tmpPath . '/xml';
    $txtPath = $tmpPath . '/txt';
    $fileNamesFound = [];
    foreach ($this->finder->in([$xmlPath, $txtPath]) as $file) {
      $fileNamesFound[$file->getFilenameWithoutExtension()][] = $file;
    }
    $files = [];
    // Validate that the xml and txt invoice is present.
    foreach ($fileNamesFound as $name => $values) {
      if (count($values) !== 2) {
        $this->logger->critical('Could not find txt and xml invoice of ' . $name);
        /** @var SplFileInfo $file */
        foreach ($values as $file) {
          // Check for the file and move it.
          if (substr($file->getBasename(), -3) === 'xml') {
            $this->cleanUpService->deleteXmlInvoice($file->getBasename(), TRUE);
          } elseif (substr($file->getFilename(), -3) === 'txt') {
            $this->cleanUpService->deleteTxtInvoice($file->getBasename(), TRUE);
          }
        }
      } else {
        // Add all files to the files array if they are valid.
        foreach ($values as $file) {
          $files[] = $file;
        }
      }
    }
    return $files;
  }

  /**
   * Downloads the receipt files and afterwards deletes them on the server.
   */
  public function downloadReceipts(): void
  {
    try {
      $this->initializeConnection();
    } catch (FtpConnectionFailedException $exception) {
      // Abort if connection could not be established.
      return;
    }
    // Navigate to output directory of ftp server.
    $this->ftp->chdir('/' . $_ENV['FTP_INVOICE_OUT']);
    // Create the path to store the receipts.
    $pathToStore = $this->helper->getTempFilesFolder() . '/receipts';
    $this->filesystem->mkdir($pathToStore);
    $ftpFiles = $this->ftp->nlist('.');
    foreach ($ftpFiles as $ftpFile) {
      // Check if the current file is a receipt.
      if (preg_match('/^quittungsfile\d+_\d+\.txt$/', $ftpFile)) {
        // Download the file.
        $this->downloadSingleReceipt($ftpFile, $pathToStore);
      }
    }
  }

  /**
   * Downloads a single receipt to a defined local directory.
   *
   * @param string $name
   * @param string $storageDir
   */
  private function downloadSingleReceipt(string $name, string $storageDir): void
  {
    try {
      $localFile = $storageDir . '/' . $name;
      $this->ftp->get($localFile, $name, FTP_BINARY);
      $this->logger->info('Downloaded the receipt ' . $name);
      $this->deleteSingleRemoteReceipt($name);
    } catch (Exception $exception) {
      $this->logger->error('An error occurred while downloading the receipt ' . $name . ' Error: ' . $exception->getMessage());
    }
  }

  /**
   * Deletes a single remote receipt.
   *
   * @param string $name
   */
  private function deleteSingleRemoteReceipt(string $name): void
  {
    try {
      $this->ftp->delete($name);
      $this->logger->info('The remote receipt ' . $name . ' has been deleted.');
    } catch (Exception $exception) {
      $this->logger->error('Could not delete the remote file ' . $name . ', reason: ' . $exception->getMessage());
    }
  }
}
