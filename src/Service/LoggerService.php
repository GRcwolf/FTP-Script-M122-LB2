<?php


namespace App\Service;


use Exception;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Swift_Mailer;
use Swift_Message;
use Swift_Mime_SimpleMessage;
use Symfony\Component\Filesystem\Filesystem;
use Twig\Environment;

/**
 * Class LoggerService
 * @package App\Service
 */
class LoggerService implements LoggerInterface
{
  /**
   * @var string
   */
  private $adminEmail;

  /**
   * @var Swift_Mailer
   */
  private $mailer;

  /**
   * @var string
   */
  private $baseDir;

  /**
   * @var Filesystem
   */
  private $filesystem;

  /**
   * @var LoggerInterface
   */
  private $logger;

  /**
   * @var Environment
   */
  private $renderer;

  /**
   * LoggerService constructor.
   *
   * @param LoggerInterface $logger
   * @param Swift_Mailer $mailer
   * @param ContainerParametersHelper $helper
   * @param Filesystem $filesystem
   * @param Environment $twig
   */
  public function __construct(LoggerInterface $logger, Swift_Mailer $mailer, ContainerParametersHelper $helper, Filesystem $filesystem, Environment $twig)
  {
    $this->adminEmail = $_ENV['ADMIN_EMAIL'];
    $this->mailer = $mailer;
    $this->filesystem = $filesystem;
    $this->logger = $logger;
    $this->renderer = $twig;
    $this->baseDir = $helper->getApplicationRootDir();
  }

  /**
   * {@inheritDoc}
   */
  public function log($level, $message, array $context = [])
  {
    $this->logger->log($level, $message, $context);
    $this->sendEmail($level, $message);
  }

  /**
   * Sends an additional email if the log level is at least a warning.
   *
   * @param string $level
   * @param string $message
   */
  private function sendEmail(string $level, string $message)
  {
    if ($level < Logger::WARNING) {
      return;
    }
    try {
      $swiftMessage = new Swift_Message('Invoice Service Error');
      $swiftMessage->setFrom($_ENV['SITE_MAIL'])
        ->setTo($this->adminEmail)
        ->setBody(
          $this->renderer->render('emails/error-report.html.twig', ['message' => $message]),
          'text/html'
        )
        ->addPart(
          $this->renderer->render('emails/error-report.txt.twig', ['message' => $message]),
          'text/plain'
        );

      // Set message priority.
      switch ($level) {
        case Logger::EMERGENCY:
          $swiftMessage->setPriority(Swift_Mime_SimpleMessage::PRIORITY_HIGHEST);
          break;
        case Logger::CRITICAL:
        case Logger::ALERT:
          $swiftMessage->setPriority(Swift_Mime_SimpleMessage::PRIORITY_HIGH);
          break;
      }
      $this->mailer->send($swiftMessage);
    } catch (Exception $exception) {
      $this->logger->critical('No email could be send: ' . $exception->getMessage());
    }
  }

  /**
   * {@inheritDoc}
   */
  public function emergency($message, array $context = array())
  {
    $this->logger->emergency($message, $context);
    $this->sendEmail(Logger::EMERGENCY, $message);
  }

  /**
   * {@inheritDoc}
   */
  public function alert($message, array $context = array())
  {
    $this->logger->alert($message, $context);
    $this->sendEmail(Logger::ALERT, $message);
  }

  /**
   * {@inheritDoc}
   */
  public function critical($message, array $context = array())
  {
    $this->logger->critical($message, $context);
    $this->sendEmail(Logger::CRITICAL, $message);
  }

  /**
   * {@inheritDoc}
   */
  public function error($message, array $context = array())
  {
    $this->logger->error($message, $context);
    $this->sendEmail(Logger::ERROR, $message);
  }

  /**
   * {@inheritDoc}
   */
  public function warning($message, array $context = array())
  {
    $this->logger->warning($message, $context);
    $this->sendEmail(Logger::WARNING, $message);
  }

  /**
   * {@inheritDoc}
   */
  public function notice($message, array $context = array())
  {
    $this->logger->notice($message, $context);
    $this->sendEmail(Logger::NOTICE, $message);
  }

  /**
   * {@inheritDoc}
   */
  public function info($message, array $context = array())
  {
    $this->logger->info($message, $context);
    $this->sendEmail(Logger::INFO, $message);
  }

  /**
   * {@inheritDoc}
   */
  public function debug($message, array $context = array())
  {
    $this->logger->debug($message, $context);
    $this->sendEmail(Logger::DEBUG, $message);
  }
}
