<?php


namespace App\Model;


use App\Model\Invoice\InvoiceItemModel;
use App\Model\Invoice\InvoiceReceiverModel;
use App\Model\Invoice\InvoiceSenderModel;
use DateTime;

class InvoiceJobModel
{
  /**
   * Invoice number.
   *
   * @var int
   */
  private $invoiceNumber;

  /**
   * Id of the job which is handled in this invoice.
   *
   * @var string
   */
  private $jobId;

  /**
   * Location where the invoice was created.
   *
   * @var string
   */
  private $location;

  /**
   * Date and time of the invoice creation.
   *
   * @var DateTime
   */
  private $dateTime;

  /**
   * The sender of the invoice.
   *
   * @var InvoiceSenderModel
   */
  private $sender;

  /**
   * The days in which the invoice should be payed.
   *
   * @var int
   */
  private $daysToPay;

  /**
   * The receiver of the invoice.
   *
   * @var InvoiceReceiverModel
   */
  private $receiver;

  /**
   * Array containing the invoice items.
   *
   * @var InvoiceItemModel[]
   */
  private $invoiceItems;

  /**
   * Validates that all data is present.
   *
   * @return bool
   */
  public function validate(): bool
  {
    if (is_null($this->getDaysToPay()))
      return false;
    if (is_null($this->getInvoiceNumber()))
      return false;
    if (empty($this->getJobId()))
      return false;
    if (empty($this->getLocation()))
      return false;
    if (empty($this->getDateTime()))
      return false;
    if (empty($this->getSender()) || !$this->getSender()->isValid())
      return false;
    if (empty($this->getReceiver()) || !$this->getReceiver()->isValid())
      return false;
    if (empty($this->getInvoiceItems()))
      return false;
    return true;
  }

  /**
   * @return int
   */
  public function getInvoiceNumber(): int
  {
    return $this->invoiceNumber;
  }

  /**
   * @param int $invoiceNumber
   */
  public function setInvoiceNumber(int $invoiceNumber): void
  {
    $this->invoiceNumber = $invoiceNumber;
  }

  /**
   * @return string
   */
  public function getJobId(): string
  {
    return $this->jobId;
  }

  /**
   * @param string $jobId
   */
  public function setJobId(string $jobId): void
  {
    $this->jobId = $jobId;
  }

  /**
   * @return string
   */
  public function getLocation(): string
  {
    return $this->location;
  }

  /**
   * @param string $location
   */
  public function setLocation(string $location): void
  {
    $this->location = $location;
  }

  /**
   * @return DateTime
   */
  public function getDateTime(): DateTime
  {
    return $this->dateTime;
  }

  /**
   * @param DateTime $dateTime
   */
  public function setDateTime(DateTime $dateTime): void
  {
    $this->dateTime = $dateTime;
  }

  /**
   * @return InvoiceSenderModel
   */
  public function getSender(): InvoiceSenderModel
  {
    return $this->sender;
  }

  /**
   * @param InvoiceSenderModel $sender
   */
  public function setSender(InvoiceSenderModel $sender): void
  {
    $this->sender = $sender;
  }

  /**
   * @return InvoiceReceiverModel
   */
  public function getReceiver(): InvoiceReceiverModel
  {
    return $this->receiver;
  }

  /**
   * @param InvoiceReceiverModel $receiver
   */
  public function setReceiver(InvoiceReceiverModel $receiver): void
  {
    $this->receiver = $receiver;
  }

  /**
   * @return InvoiceItemModel[]
   */
  public function getInvoiceItems(): array
  {
    return $this->invoiceItems;
  }

  /**
   * @param InvoiceItemModel[] $invoiceItems
   */
  public function setInvoiceItems(array $invoiceItems): void
  {
    $this->invoiceItems = $invoiceItems;
  }

  /**
   * @return int
   */
  public function getDaysToPay(): int
  {
    return $this->daysToPay;
  }

  /**
   * @param int $daysToPay
   */
  public function setDaysToPay(int $daysToPay): void
  {
    $this->daysToPay = $daysToPay;
  }

  /**
   * Adds a single invoice item to the ones which are already set.
   *
   * @param InvoiceItemModel $invoiceItem
   *  InvoiceItemModel object to add.
   */
  public function addInvoiceItem(InvoiceItemModel $invoiceItem)
  {
    $this->invoiceItems[] = $invoiceItem;
  }
}
