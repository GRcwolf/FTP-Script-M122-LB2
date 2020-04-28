<?php


namespace App\Model\Invoice;


class InvoiceSenderModel implements InvoicePersonModel
{
  /**
   * Customer number.
   *
   * @var string
   */
  private $customerNumber;

  /**
   * Salutation to use.
   *
   * @var string
   */
  private $salutation;

  /**
   * The sender's name.
   *
   * @var string
   */
  private $name;

  /**
   * Address of the sender.
   *
   * @var string
   */
  private $address;

  /**
   * Zip code with the location of the sender.
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
   * VAT number.
   *
   * @var string
   */
  private $vatNumber;

  /**
   * Email oft the sender.
   *
   * @var string
   */
  private $email;

  /**
   * {@inheritDoc}
   */
  public function isValid() {
    if (empty($this->customerNumber))
      return false;
    if (empty($this->salutation))
      return false;
    if (empty($this->name))
      return false;
    if (empty($this->address))
      return false;
    if (empty($this->zipLocation))
      return false;
    if (empty($this->vatNumber))
      return false;
    if (empty($this->email))
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
  public function getCustomerNumber(): string
  {
    return $this->customerNumber;
  }

  /**
   * @param string $customerNumber
   */
  public function setCustomerNumber(string $customerNumber): void
  {
    $this->customerNumber = $customerNumber;
  }

  /**
   * @return string
   */
  public function getSalutation(): string
  {
    return $this->salutation;
  }

  /**
   * @param string $salutation
   */
  public function setSalutation(string $salutation): void
  {
    $this->salutation = $salutation;
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
  public function getVatNumber(): string
  {
    return $this->vatNumber;
  }

  /**
   * @param string $vatNumber
   */
  public function setVatNumber(string $vatNumber): void
  {
    $this->vatNumber = $vatNumber;
  }

  /**
   * @return string
   */
  public function getEmail(): string
  {
    return $this->email;
  }

  /**
   * @param string $email
   */
  public function setEmail(string $email): void
  {
    $this->email = $email;
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
