<?php


namespace App\Service;


use Ijanki\Bundle\FtpBundle\Exception\FtpException;
use Ijanki\Bundle\FtpBundle\Ftp;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ImportJobsFtp
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
     * ImportJobsFtp constructor.
     *
     * @param LoggerInterface $logger
     * @param Ftp $ftp
     *
     * @throws FtpException
     */
    public function __construct(LoggerInterface $logger, Ftp $ftp)
    {
        $this->ftpClient = $ftp;
        $this->logger = $logger;
        // Ftp settings.
        $this->ftpHost = $_ENV['FTP_HOST'];
        $this->ftpUser = $_ENV['FTP_USER'];
        $this->ftpPassword = $_ENV['FTP_PASSWORD'];
        $this->ftpDirectory = $_ENV['FTP_SCHOOLER_OUT'];

        $this->jobDir = $_ENV['JOB_DIR'];

        // Configure the FTP client.
        $this->configureFtpConnection();
        $this->getJobs();
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
     * @throws FtpException
     */
    protected function configureFtpConnection()
    {
        try {
            $this->ftpClient->connect($this->ftpHost);
            $this->ftpClient->login($this->ftpUser, $this->ftpPassword);
        } catch (FtpException $exception) {
            $this->logger->error('Could not connect to ' . $this->ftpHost . ' with the provided credentials. Error: ' . $exception->getMessage());
            throw $exception;
        }
    }

    /**
     * Gets all job files and downloads the locally.
     */
    public function getJobs()
    {
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

        // Download file.
        $this->ftpClient->get($localFile, $remoteFile, FTP_BINARY);
    }
}
