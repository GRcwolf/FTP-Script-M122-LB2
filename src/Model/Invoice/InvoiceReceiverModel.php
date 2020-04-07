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
   * Returns the zip.
   *
   * @var string
   */
  private $zip;

  /**
   * Returns the location.
   *
   * @var string
   */
  private $location;

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
   * Updates the zip and location.
   */
  private function updateZipAndLocation(): void {
    $matches = [];
    if (preg_match('/\d+/', $this->getZipLocation(), $matches)) {
      $this->zip = $matches[0];
      $matches = [];
    }
    if (preg_match('/(?=\w+)\D+/', $this->getZipLocation(), $matches)) {
      $this->location = $matches[0];
      $matches = [];
    }
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
    $this->updateZipAndLocation();
  }

  /**
   * @return string
   */
  public function getZip(): string
  {
    return $this->zip;
  }

  /**
   * @return string
   */
  public function getLocation(): string
  {
    return $this->location;
  }
}
