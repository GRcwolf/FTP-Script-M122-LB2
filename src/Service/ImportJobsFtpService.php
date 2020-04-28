<?php


namespace App\Service;


use App\Exception\FtpConnectionFailedException;
use Exception;
use Ijanki\Bundle\FtpBundle\Ftp;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;

class ImportJobsFtpService
{
  /**
   * The FTP-host.
   *
   * @var string
   *  IP or hostname of the ftp-server.
   */
  private $ftpHost = '';

  /**
   * The FTP-user.
   *
   * @var string
   */
  private $ftpUser = '';

  /**
   * The FTP-password.
   *
   * @var string
   */
  private $ftpPassword = '';

  /**
   * The FTP-client.
   *
   * @var Ftp
   */
  protected $ftpClient;

  /**
   * \Psr\Log\LoggerInterface definition.
   *
   * @var LoggerInterface
   */
  private $logger;

  /**
   * The Directory containing the invoice jobs.
   *
   * @var string
   */
  private $ftpDirectory = '';

  /**
   * The folder to store jobs.
   *
   * @var string
   */
  private $jobDir = '';

  /**
   * @var Filesystem
   */
  private $filesystem;

  /**
   * ImportJobsFtpService constructor.
   *
   * @param LoggerService $logger
   * @param Ftp $ftp
   * @param ContainerParametersHelper $helper
   * @param Filesystem $filesystem
   */
  public function __construct(LoggerService $logger, Ftp $ftp, ContainerParametersHelper $helper, Filesystem $filesystem)
  {
    $this->ftpClient = $ftp;
    $this->logger = $logger;
    // Ftp settings.
    $this->ftpHost = $_ENV['FTP_HOST'];
    $this->ftpUser = $_ENV['FTP_USER'];
    $this->ftpPassword = $_ENV['FTP_PASSWORD'];
    $this->ftpDirectory = $_ENV['FTP_SCHOOLER_OUT'];

    $this->jobDir = $helper->getTempFilesFolder() . '/jobs';

    $this->filesystem = $filesystem;
    $this->generateFolderStructure();
  }

  /**
   * Make sure the necessary folder structure is present.
   */
  private function generateFolderStructure()
  {
    $this->filesystem->mkdir($this->jobDir, 0775);
  }

  /**
   * Handle object destruction.
   */
  public function __destruct()
  {
    // Close the ftp connection.
    $this->ftpClient->close();
  }

  /**
   * Sets up the ftp-client with the configuration.
   *
   * @throws FtpConnectionFailedException
   */
  protected function configureFtpConnection()
  {
    try {
      $this->ftpClient->connect($this->ftpHost);
      $this->ftpClient->login($this->ftpUser, $this->ftpPassword);
    } catch (Exception $exception) {
      $this->logger->error('Could not connect to ' . $this->ftpHost . ' with the provided credentials. Error: ' . $exception->getMessage());
      throw new FtpConnectionFailedException();
    }
  }

  /**
   * Gets all job files and downloads the locally.
   */
  public function getJobs()
  {
    // Configure the FTP client.
    try {
      $this->configureFtpConnection();
    } catch (FtpConnectionFailedException $e) {
      return;
    }
    // Change directory to defined output directory.
    $this->ftpClient->chdir($this->ftpDirectory);
    $jobFiles = $this->getJobFileNames();
    foreach ($jobFiles as $jobFile) {
      $this->getJobFile($jobFile);
    }
    // Reset ftp client location.
    $this->ftpClient->chdir('/');
  }

  /**
   * Get the file names of the files which will be processed.
   *
   * @return array
   */
  private function getJobFileNames()
  {
    $filePattern = '/^\w+\d+\.data$/';

    $ftpFiles = $this->ftpClient->nlist('.');
    $filesToProcess = [];
    foreach ($ftpFiles as $file) {
      if (preg_match($filePattern, $file)) {
        $filesToProcess[] = $file;
      }
    }
    return $filesToProcess;
  }

  /**
   * Downloads a job file.
   *
   * @param string $fileName
   *  The file name of the job.
   */
  private function getJobFile(string $fileName)
  {
    // Set file locations.
    $remoteFile = $fileName;
    $localFile = $this->jobDir . '/' . $fileName;

    try {
      // Download file.
      if ($this->ftpClient->get($localFile, $remoteFile, FTP_BINARY)) {
        $this->logger->info('The file ' . $fileName . ' has been downloaded');
      } else {
        $this->logger->error('The file ' . $fileName . ' could not be downloaded.');
      }
    } catch (Exception $exception) {
      $this->logger->error('An error occurred file downloading ' . $fileName . ': ' . $exception->getMessage());
    }
  }
}
