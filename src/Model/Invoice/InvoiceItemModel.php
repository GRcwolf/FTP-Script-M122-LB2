<?php


namespace App\Model\Invoice;


class InvoiceItemModel implements InvoicePersonModel
{
  /**
   * Index describing the position of the item on an invoice.
   *
   * @var int
   */
  private $index;

  /**
   * Item description.
   *
   * @var string
   */
  private $itemDescription;

  /**
   * Count of described labor/material.
   *
   * @var int
   */
  private $count;

  /**
   * Price per unit.
   *
   * @var float
   */
  private $pricePerUnit;

  /**
   * Total price.
   *
   * @var float
   */
  private $totalPrice;

  /**
   * Vat rate.
   *
   * @var float
   */
  private $vatRate;

  /**
   * {@inheritDoc}
   */
  public function isValid()
  {
    if (!isset($this->index))
      return false;
    if (empty($this->itemDescription))
      return false;
    if (!isset($this->count))
      return false;
    if (!isset($this->pricePerUnit))
      return false;
    if (!isset($this->totalPrice))
      return false;
    if (!isset($this->vatRate))
      return false;
    return true;
  }

  /**
   * @return int
   */
  public function getIndex(): int
  {
    return $this->index;
  }

  /**
   * @param int $index
   */
  public function setIndex(int $index): void
  {
    $this->index = $index;
  }

  /**
   * @return string
   */
  public function getItemDescription(): string
  {
    return $this->itemDescription;
  }

  /**
   * @param string $itemDescription
   */
  public function setItemDescription(string $itemDescription): void
  {
    $this->itemDescription = $itemDescription;
  }

  /**
   * @return int
   */
  public function getCount(): int
  {
    return $this->count;
  }

  /**
   * @param int $count
   */
  public function setCount(int $count): void
  {
    $this->count = $count;
  }

  /**
   * @return float
   */
  public function getPricePerUnit(): float
  {
    return $this->pricePerUnit;
  }

  /**
   * @param float $pricePerUnit
   */
  public function setPricePerUnit(float $pricePerUnit): void
  {
    $this->pricePerUnit = $pricePerUnit;
  }

  /**
   * @return float
   */
  public function getTotalPrice(): float
  {
    return $this->totalPrice;
  }

  /**
   * @param float $totalPrice
   */
  public function setTotalPrice(float $totalPrice): void
  {
    $this->totalPrice = $totalPrice;
  }

  /**
   * @return float
   */
  public function getVatRate(): float
  {
    return $this->vatRate;
  }

  /**
   * @param float $vatRate
   */
  public function setVatRate(float $vatRate): void
  {
    $this->vatRate = $vatRate;
  }
}
