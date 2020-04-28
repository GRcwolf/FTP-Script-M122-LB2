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
   * InvoiceSystemFtpService constructor.
   *
   * @param LoggerService $logger
   * @param ContainerParametersHelper $helper
   * @param Ftp $ftp
   * @param CleanUpService $cleanUpService
   */
  public function __construct(LoggerService $logger, ContainerParametersHelper $helper, Ftp $ftp, CleanUpService $cleanUpService)
  {
    $this->logger = $logger;
    $this->cleanUpService = $cleanUpService;
    $this->finder = new Finder();
    $this->helper = $helper;
    $this->ftp = $ftp;
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
}
