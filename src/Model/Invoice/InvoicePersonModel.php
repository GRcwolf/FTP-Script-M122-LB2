<?php


namespace App\Model\Invoice;

/**
 * Interface InvoicePersonModel
 *
 * @package App\Model\Invoice
 */
interface InvoicePersonModel
{
  /**
   * Indicates if the object has all necessary values and is valid.
   *
   * @return bool
   */
  public function isValid();
}
