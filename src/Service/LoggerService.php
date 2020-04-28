<?php


namespace App\Service;


use Exception;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Swift_Mailer;
use Swift_Message;
use Symfony\Component\Filesystem\Filesystem;
use Twig\Environment;

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

  private $renderer;

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

  private function sendEmail(string $level, string $message)
  {
    if ($level < Logger::WARNING) {
      return;
    }
    try {
      $swiftMessage = new Swift_Message('Invoice Service Error');
      $swiftMessage->setFrom('chris.wolf@bluewin.ch')
        ->setTo($this->adminEmail)
        ->setBody(
          $this->renderer->render('emails/error-report.html.twig', ['message' => $message]),
          'text/html'
        )
        ->addPart(
          $this->renderer->render('emails/error-report.txt.twig', ['message' => $message]),
          'text/plain'
        );
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
