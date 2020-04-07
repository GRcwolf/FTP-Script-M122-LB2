<?php


namespace App\Service;

use App\Model\Invoice\InvoiceItemModel;
use App\Model\InvoiceJobModel;
use DateInterval;
use DateTime;
use SimpleXMLElement;
use SplFileInfo;
use Symfony\Component\Finder\Finder;

/**
 * Class InvoiceExporterService
 *
 * @package App\Service
 */
class InvoiceExporterService
{
  private $finder;

  private $template = '';

  public function __construct()
  {
    $this->finder = new Finder();
    $this->getTemplate();
  }

  public function getXml(InvoiceJobModel $invoice): SimpleXMLElement {
    $xml = new SimpleXMLElement($this->template);
    $this->addXmlInvoiceHeaderData($xml, $invoice);
    $x = 42;
  }

  /**
   * Get the base xml file.
   */
  private function getTemplate(): void {
    $path = $_ENV['TEMPLATE_PATH'];
    $xmlFile = null;
    /** @var SplFileInfo $file */
    foreach ($this->finder->in($path) as $file) {
      if (preg_match('/^invoice\.xml$/', $file->getFilename())) {
        $xmlFile = $file;
        break;
      }
    }
    $handle = fopen($xmlFile->getPathname(), 'r');
    if ($handle)  {
      while (($line = fgets($handle)) !== false) {
        $this->template .= $line;
      }
    }
  }

  /**
   * Sets the invoice header data.
   *
   * @param SimpleXMLElement $xml
   * @param InvoiceJobModel $invoice
   */
  private function addXmlInvoiceHeaderData(SimpleXMLElement $xml, InvoiceJobModel $invoice) {
    // Add the meta information.
    $this->addXmlMetaData($xml, $invoice);
    $this->addXmlOriginData($xml, $invoice);
    $this->addXmlInvoiceAddress($xml, $invoice);
    $this->addXmlPayingConditions($xml, $invoice);
    $this->addXmlVatInformation($xml, $invoice);
  }

  /**
   * Adds the meta data information to the xml object.
   *
   * @param SimpleXMLElement $xml
   * @param InvoiceJobModel $invoice
   */
  private function addXmlMetaData(SimpleXMLElement $xml, InvoiceJobModel $invoice): void {
    $metaName = 'I.H.010_Basisdaten';
    // Set the invoice number.
    $xml->Invoice_Header->$metaName->addChild('BV.010_Rechnungsnummer', $invoice->getInvoiceNumber());
    // Set the invoice date.
    $xml->Invoice_Header->$metaName->addChild('BV.020_Rechnungsdatum', $this->formatDate($invoice->getDateTime()));
  }

  /**
   * Formats a DateTime object to a string.
   *
   * @param DateTime $dateTime
   *
   * @return string
   */
  private function formatDate(DateTime $dateTime): string {
    return $dateTime->format('YmdHis') . '000';
  }

  /**
   * Sets the origin of the invoice to the xml.
   *
   * @param SimpleXMLElement $xml
   * @param InvoiceJobModel $invoice
   */
  private function addXmlOriginData(SimpleXMLElement $xml, InvoiceJobModel $invoice): void {
    $buyerName = 'I.H.020_Einkaeufer_Identifikation';
    $sender = $invoice->getSender();
    $xml->Invoice_Header->$buyerName->addChild('BV.040_Name1', $sender->getName());
    $xml->Invoice_Header->$buyerName->addChild('BV.070_Strasse', $sender->getAddress());
    $xml->Invoice_Header->$buyerName->addChild('BV.100_PLZ', $sender->getZip());
    $xml->Invoice_Header->$buyerName->addChild('BV.110_Stadt', $sender->getLocation());
  }

  /**
   * Sets the invoice address to the invoice header.
   *
   * @param SimpleXMLElement $xml
   * @param InvoiceJobModel $invoice
   */
  private function addXmlInvoiceAddress(SimpleXMLElement $xml, InvoiceJobModel $invoice): void {
    $addressName = 'I.H.040_Rechnungsadresse';
    $receiver = $invoice->getReceiver();
    $xml->Invoice_Header->$addressName->addChild('BV.040_Name1', $receiver->getName());
    $xml->Invoice_Header->$addressName->addChild('BV.100_PLZ', $receiver->getZip());
    $xml->Invoice_Header->$addressName->addChild('BV.110_Stadt', $receiver->getLocation());
  }

  /**
   * Adds the paying conditions.
   *
   * @param SimpleXMLElement $xml
   * @param InvoiceJobModel $invoice
   */
  private function addXmlPayingConditions(SimpleXMLElement $xml, InvoiceJobModel $invoice): void {
    $payingConditionsName = 'I.H.080_Zahlungsbedingungen';
    $dueDate = $invoice->getDateTime();
    $daysToPay = $invoice->getDaysToPay();
    try {
      $dueDate->add(new DateInterval('P' . $daysToPay . 'D'));
    } catch (\Exception $e) {
      // @TODO: Handle exception.
    }
    $xml->Invioce_Header->$payingConditionsName->addChild('BV.020_Zahlungsbedingungen_Zusatzwert', $this->formatDate($dueDate));
  }

  /**
   * Add the vat information the the xml.
   *
   * @param SimpleXMLElement $xml
   * @param InvoiceJobModel $invoice
   */
  private function addXmlVatInformation(SimpleXMLElement $xml, InvoiceJobModel $invoice): void {
    $xmlName = 'I.H.140_MwSt._Informationen';
    $xml->Invoice_Header->$xmlName->addChild('BV.010_Eingetragener_Name_des_Lieferanten', $invoice->getSender()->getName());
    $xml->Invoice_Header->$xmlName->addChild('BV.020_MwSt_Nummer_des_Lieferanten', $invoice->getSender()->getVatNumber());
  }

  private function addXmlInvoiceDetails(SimpleXMLElement $xml, InvoiceJobModel $invoice): void {
    // Add the single items.
    foreach ($invoice->getInvoiceItems() as $item) {
      $this->addXmlInvoiceItem($xml, $item);
    }
  }

  /**
   * Adds a single invoice item.
   *
   * @param SimpleXMLElement $xml
   * @param InvoiceItemModel $item
   */
  private function addXmlInvoiceItem(SimpleXMLElement $xml, InvoiceItemModel $item): void {
    $index = (string)$item->getIndex();
    if (strlen($index) == 1) {
      $index = '0' . $index;
    }
    $this->addXmlInvoiceItemBasicData($xml, $item, $index);
  }
  
  private function addXmlInvoiceItemBasicData(SimpleXMLElement $xml, InvoiceItemModel $item, int $index) {
    $xmlName = 'I.D.' . $index . '0_Basisdaten';
    $xml->Invoice_Data->Invoice_Items->$xmlName->addChild('BV.010_Positions_Nr_in_der_Rechnung', $item->getIndex());
  }
}
