<?php


namespace App\Service;

use Exception;
use Ijanki\Bundle\FtpBundle\Ftp;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class CleanUpService
 * @package App\Service
 */
class CleanUpService
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
   * @var Ftp
   */
  private $ftp;

  /**
   * @var string
   */
  private $ftpDirectory;

  /**
   * CleanUpService constructor.
   *
   * @param LoggerService $logger
   * @param Filesystem $filesystem
   * @param ContainerParametersHelper $helper
   * @param Ftp $ftp
   */
  public function __construct(LoggerService $logger, Filesystem $filesystem, ContainerParametersHelper $helper, Ftp $ftp)
  {
    $this->ftp = $ftp;
    $this->helper = $helper;
    $this->logger = $logger;
    $this->filesystem = $filesystem;
    $this->initiateFtpClient();
  }

  /**
   * Closes the ftp connection on destruction.
   */
  public function __destruct()
  {
    try {
      $this->ftp->close();
    } catch (Exception $e) {
      // Should only happen if the ftp client wasn't even initiated.
    }
  }

  /**
   * Initiates the ftp client.
   */
  private function initiateFtpClient(): void
  {
    // Ftp settings.
    $ftpHost = $_ENV['FTP_HOST'];
    $ftpUser = $_ENV['FTP_USER'];
    $ftpPassword = $_ENV['FTP_PASSWORD'];
    $this->ftpDirectory = $_ENV['FTP_SCHOOLER_OUT'];

    try {
      $this->ftp->connect($ftpHost);
      $this->ftp->login($ftpUser, $ftpPassword);
    } catch (Exception $exception) {
      $this->logger->critical('Could not connect to ' . $ftpHost . ' with the provided credentials. Error: ' . $exception->getMessage());
    }
  }

  /**
   * Deletes a specific data file by its invoice number.
   *
   * @param int $invoiceNumber
   */
  public function deleteLocalInvoiceData(int $invoiceNumber): void
  {
    $path = $this->helper->getTempFilesFolder() . '/jobs';
    $file = $path . '/rechnung' . $invoiceNumber . '.data';
    if (!$this->filesystem->exists($file)) {
      $this->logger->notice('Tried to delete file ' . $file . ', however file was not present.');
      return;
    } else {
      $this->logger->info('Deleted local data file ' . $file);
    }
    $this->filesystem->remove($file);
  }

  /**
   * Deletes or renames a data file on the ftp server.
   *
   * @param int $invoiceNumber
   * @param bool $move
   */
  public function deleteRemoteDataFile(int $invoiceNumber, $move = FALSE): void
  {
    try {
      $this->ftp->chdir('/');
      $this->ftp->chdir($this->ftpDirectory);
      $fileName = 'rechnung' . $invoiceNumber . '.data';
      if ($move) {
        if (!$this->ftp->rename($fileName, $fileName . '.broken')) {
          $this->logger->warning('Could not rename FTP file ' . $fileName);
        } else {
          $this->logger->info('Renamed remote file ' . $fileName . ' to ' . $fileName . '.broken');
        }
      } else {
        if (!$this->ftp->delete($fileName)) {
          $this->logger->warning('Could delete rename FTP file ' . $fileName);
        } else {
          $this->logger->info('Deleted remote file ' . $fileName);
        }
      }
    } catch (Exception $e) {
      $this->logger->alert('An error occurred while trying do delete/rename remote file. Error:' . $e->getMessage());
    }
  }

  /**
   * Deletes an xml invoice file by its name.
   *
   * @param string $fileName
   * @param bool $move
   */
  public function deleteXmlInvoice(string $fileName, $move = FALSE): void
  {
    $directory = $this->helper->getTempFilesFolder() . '/xml';
    $path = $directory . '/' . $fileName;
    $this->deleteFile($path);
  }

  /**
   * Deletes an txt invoice file by its name.
   *
   * @param string $fileName
   * @param bool $move
   */
  public function deleteTxtInvoice(string $fileName, $move = FALSE): void
  {
    $directory = $this->helper->getTempFilesFolder() . '/txt';
    $path = $directory . '/' . $fileName;
    $this->deleteFile($path);
  }

  /**
   * Delete a file or move it.
   *
   * @param string $path
   * @param bool $move
   */
  public function deleteFile(string $path, $move = FALSE): void
  {
    try {
      if ($move) {
        $this->filesystem->rename($path, $path . '.sav');
        $this->logger->info('Local file ' . $path . ' has been renamed to ' . $path . '.sav.');
      } else {
        $this->filesystem->remove($path);
        $this->logger->info('Local file ' . $path . ' has been removed.');
      }
    } catch (IOException $exception) {
      $this->logger->warning('Could not delete file ' . $path . '. Error: ' . $exception->getMessage());
    }
  }
}
