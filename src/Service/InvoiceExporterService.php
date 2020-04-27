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
    $this->addXmlInvoiceDetails($xml, $invoice);
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
    $xml->Invoice_Header->$payingConditionsName->addChild('BV.020_Zahlungsbedingungen_Zusatzwert', $this->formatDate($dueDate));
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

  /**
   * Add single invoice item to the xml.
   *
   * @param SimpleXMLElement $xml
   * @param InvoiceJobModel $invoice
   */
  private function addXmlInvoiceDetails(SimpleXMLElement $xml, InvoiceJobModel $invoice): void {
    // Add the single items.
    foreach ($invoice->getInvoiceItems() as $item) {
      $this->addXmlInvoiceItem($xml, $item, $invoice);
    }
  }

  /**
   * Adds a single invoice item.
   *
   * @param SimpleXMLElement $xml
   * @param InvoiceItemModel $item
   * @param InvoiceJobModel $invoice
   */
  private function addXmlInvoiceItem(SimpleXMLElement $xml, InvoiceItemModel $item, InvoiceJobModel $invoice): void {
    $this->addXmlInvoiceItemBasicData($xml, $item, $invoice);
    $this->addXmlInvoiceItemPriceAndAmount($xml, $item);
    $this->addXmlInvoiceItemsTaxes($xml, $item);
  }

  /**
   * Add the basic data to the invoice item.
   *
   * @param SimpleXMLElement $xml
   * @param InvoiceItemModel $item
   * @param InvoiceJobModel $invoice
   */
  private function addXmlInvoiceItemBasicData(SimpleXMLElement $xml, InvoiceItemModel $item, InvoiceJobModel $invoice): void {
    $xmlName = 'I.D.010_Basisdaten';
    $index = $item->getIndex() - 1;
    $xml->Invoice_Detail->Invoice_Items->addChild($xmlName);
    $xml->Invoice_Detail->Invoice_Items->$xmlName[$index]->addChild('BV.010_Positions_Nr_in_der_Rechnung', $item->getIndex());
    // @TODO: Add item number.
    $xml->Invoice_Detail->Invoice_Items->$xmlName[$index]->addChild('BV.070_Artikel_Beschreibung', $this->xmlEscapeString($item->getItemDescription()));
    $xml->Invoice_Detail->Invoice_Items->$xmlName[$index]->addChild('BV.140_Abschlussdatum_der_Lieferung_Ausfuehrung', $this->formatDate($invoice->getDateTime()));
  }

  /**
   * Adds the amount and prices for the invoice item.
   *
   * @param SimpleXMLElement $xml
   * @param InvoiceItemModel $item
   */
  private function addXmlInvoiceItemPriceAndAmount(SimpleXMLElement $xml, InvoiceItemModel $item): void {
    $xmlName = 'I.D.020_Preise_und_Mengen';
    $index = $item->getIndex() - 1;
    $xml->Invoice_Detail->Invoice_Items->addChild($xmlName);
    $xml->Invoice_Detail->Invoice_Items->$xmlName[$index]->addChild('BV.010_Verrechnete_Menge', $item->getCount());
    $xml->Invoice_Detail->Invoice_Items->$xmlName[$index]->addChild('BV.020_Mengeneinheit_der_verrechneten_Menge', 'BLL');
    $xml->Invoice_Detail->Invoice_Items->$xmlName[$index]->addChild('BV.030_Verrechneter_Einzelpreis_des_Artikels', $item->getPricePerUnit());
    $xml->Invoice_Detail->Invoice_Items->$xmlName[$index]->addChild('BV.040_Waehrung_des_Einzelpreises', 'CHF');
    $xml->Invoice_Detail->Invoice_Items->$xmlName[$index]->addChild('BV.070_Bestaetigter_Gesamtpreis_der_Position_netto', $item->getTotalPrice());
    // @TODO: Calculate price including VAT.
    $xml->Invoice_Detail->Invoice_Items->$xmlName[$index]->addChild('BV.080_Bestaetigter_Gesamtpreis_der_Position_brutto', $item->getTotalPrice());
    $xml->Invoice_Detail->Invoice_Items->$xmlName[$index]->addChild('BV.090_Waehrung_des_Gesamtpreises', 'CHF');
  }

  /**
   * Add tax information of the item to the xml file.
   *
   * @param SimpleXMLElement $xml
   * @param InvoiceItemModel $item
   */
  private function addXmlInvoiceItemsTaxes(SimpleXMLElement $xml, InvoiceItemModel $item): void {
    $xmlName = 'I.D.030_Steuern';
    $index = $item->getIndex() - 1;
    $xml->Invoice_Detail->Invoice_Items->addChild($xmlName);
    $xml->Invoice_Detail->Invoice_Items->$xmlName[$index]->addChild('BV.010_Funktion_der_Steuer', 'Steuer');
    $xml->Invoice_Detail->Invoice_Items->$xmlName[$index]->addChild('BV.020_Steuersatz_Kategorie', 'Standard Satz');
    $xml->Invoice_Detail->Invoice_Items->$xmlName[$index]->addChild('BV.030_Steuersatz', $item->getVatRate());
    $xml->Invoice_Detail->Invoice_Items->$xmlName[$index]->addChild('BV.040_Zu_versteuernder_Betrag', $item->getTotalPrice());
    $xml->Invoice_Detail->Invoice_Items->$xmlName[$index]->addChild('BV.050_Steuerbetrag', $this->calculateTotalItemPrice($item));
  }

  /**
   * @param InvoiceItemModel $item
   *
   * @return float
   */
  private function calculateTotalItemPrice(InvoiceItemModel $item): float {
    return $item->getTotalPrice() * $item->getVatRate();
  }

  /**
   * Escapes a string for usage in xml.
   *
   * @param string $str
   * @return string
   */
  private function xmlEscapeString(string $str): string {
    return str_replace(['"', '\'', '<', '>', '&'], ['&quot;', '&apos;', '&lt;', '&gt;', '&amp;'], $str);
  }
}
