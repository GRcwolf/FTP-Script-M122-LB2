<?php


namespace App\Model\Invoice;

/**
 * Class InvoiceReceiverModel
 *
 * @package App\Model\Invoice
 */
class InvoiceReceiverModel implements InvoicePersonModel
{
  /**
   * Customer id.
   *
   * @var string
   */
  private $customerId;

  /**
   * Name of the customer.
   *
   * @var string
   */
  private $name;

  /**
   * Address of the customer.
   *
   * @var string
   */
  private $address;

  /**
   * Zip including location name.
   *
   * @var string
   */
  private $zipLocation;

  /**
   * {@inheritDoc}
   */
  public function isValid()
  {
    if (empty($this->customerId))
      return false;
    if (empty($this->name))
      return false;
    if (empty($this->address))
      return false;
    if (empty($this->zipLocation))
      return false;
    return true;
  }

  /**
   * @return string
   */
  public function getCustomerId(): string
  {
    return $this->customerId;
  }

  /**
   * @param string $customerId
   */
  public function setCustomerId(string $customerId): void
  {
    $this->customerId = $customerId;
  }

  /**
   * @return string
   */
  public function getName(): string
  {
    return $this->name;
  }

  /**
   * @param string $name
   */
  public function setName(string $name): void
  {
    $this->name = $name;
  }

  /**
   * @return string
   */
  public function getAddress(): string
  {
    return $this->address;
  }

  /**
   * @param string $address
   */
  public function setAddress(string $address): void
  {
    $this->address = $address;
  }

  /**
   * @return string
   */
  public function getZipLocation(): string
  {
    return $this->zipLocation;
  }

  /**
   * @param string $zipLocation
   */
  public function setZipLocation(string $zipLocation): void
  {
    $this->zipLocation = $zipLocation;
  }
}
