<?php


namespace App\Service;

use App\Model\Invoice\InvoiceItemModel;
use App\Model\InvoiceJobModel;
use DateInterval;
use DateTime;
use Exception;
use SimpleXMLElement;
use SplFileInfo;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * Class InvoiceExporterService
 *
 * @package App\Service
 */
class InvoiceExporterService
{
  /**
   * @var Finder
   */
  private $finder;

  /**
   * @var string
   */
  private $template = '';

  /**
   * @var Filesystem
   */
  private $filesystem;

  /**
   * InvoiceExporterService constructor.
   */
  public function __construct()
  {
    $this->finder = new Finder();
    $this->getTemplate();
    $this->filesystem = new Filesystem();
  }

  /**
   * Generates a xml file containing the invoice information.
   *
   * @param InvoiceJobModel $invoice
   * @return SimpleXMLElement
   */
  public function getXml(InvoiceJobModel $invoice): SimpleXMLElement {
    $xml = new SimpleXMLElement($this->template);
    $this->addXmlInvoiceHeaderData($xml, $invoice);
    $this->addXmlInvoiceDetails($xml, $invoice);
    $this->addXmlSummary($xml, $invoice);
    return $xml;
  }

  /**
   * Saves the xml invoice.
   *
   * @param InvoiceJobModel $invoice
   */
  public function saveInvoiceXml(InvoiceJobModel $invoice): void {
    $xml = $this->getXml($invoice);
    $path = $_ENV['PRIVATE_DIR'] . '/xml';
    $this->createXmlDirectory($path);
    $fileName = $this->generateFileName($invoice) . '.xml';
    $xml->saveXML($path . '/' . $fileName);
  }

  /**
   * Generate a file name.
   *
   * @param InvoiceJobModel $invoice
   * @return string
   */
  private function generateFileName(InvoiceJobModel $invoice): string {
    $name = '';
    $name .= $invoice->getInvoiceNumber();
    return $name;
  }

  /**
   * Creates the directory for the xml files is not present.
   *
   * @param string $path
   */
  private function createXmlDirectory(string $path): void {
    if (!$this->filesystem->exists($path)) {
      $this->filesystem->mkdir($path, 0775);
      $this->filesystem->dumpFile($path . '/.gitignore', "*.xml\n*.txt");
    }
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
    $xml->Invoice_Header->$buyerName->addChild('BV.040_Name1', $this->xmlEscapeString($sender->getName()));
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
    } catch (Exception $e) {
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
    $xml->Invoice_Header->$xmlName->addChild('BV.010_Eingetragener_Name_des_Lieferanten', $this->xmlEscapeString($invoice->getSender()->getName()));
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
    // @TODO: Add real item number.
    $xml->Invoice_Detail->Invoice_Items->$xmlName[$index]->addChild('BV.020_Artikel_Nr_des_Lieferanten', '0123456789');
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

  /**
   * Parses the invoice summary part of the xml.
   *
   * @param SimpleXMLElement $xml
   * @param InvoiceJobModel $invoice
   */
  private function addXmlSummary(SimpleXMLElement $xml, InvoiceJobModel $invoice): void {
    $this->addXmlSummaryBasicData($xml, $invoice);
    $this->addXmlSummaryTaxes($xml, $invoice);
  }

  /**
   * Parses the basic data of the xml invoice summary.
   *
   * @param SimpleXMLElement $xml
   * @param InvoiceJobModel $invoice
   */
  private function addXmlSummaryBasicData(SimpleXMLElement $xml, InvoiceJobModel $invoice): void {
    $xmlName = 'I.S.010_Basisdaten';
    $xml->Invoice_Summary->$xmlName->addChild('BV.010_Anzahl_der_Rechnungspositionen', count($invoice->getInvoiceItems()));
    $xml->Invoice_Summary->$xmlName->addChild('BV.020_Gesamtbetrag_der_Rechnung_exkl_MwSt_exkl_Ab_Zuschlag', number_format($this->calculateInvoiceTotalPrice($invoice),2, '.', ''));
    $xml->Invoice_Summary->$xmlName->addChild('BV.040_Gesamtbetrag_der_Rechnung_exkl_MwSt_inkl_Ab_Zuschlag', number_format($this->calculateTotalVatPrice($invoice),2, '.', ''));
    $xml->Invoice_Summary->$xmlName->addChild('BV.080_Gesamtbetrag_der_Rechnung_inkl_MwSt_inkl_Ab_Zuschlag', number_format($this->calculateTotalPrice($invoice),2, '.', ''));
    $xml->Invoice_Summary->$xmlName->addChild('BV.060_Steuerbetrag', $this->calculateInvoiceTotalPrice($invoice));
  }

  /**
   * Add information about taxes to summary.
   *
   * @param SimpleXMLElement $xml
   * @param InvoiceJobModel $invoice
   */
  private function addXmlSummaryTaxes(SimpleXMLElement $xml, InvoiceJobModel $invoice): void {
    $xmlName = 'I.S.020_Aufschluesselung_der_Steuern';
    // @TODO: Add real vat rate.
    $xml->Invoice_Summary->$xmlName->addChild('BV.030_Steuersatz', '0.00%');
    $xml->Invoice_Summary->$xmlName->addChild('BV.040_Zu_versteuernder_Betrag',  number_format($this->calculateInvoiceTotalPrice($invoice),2, '.', ''));
    $xml->Invoice_Summary->$xmlName->addChild('BV.050_Steuerbetrag',  number_format($this->calculateTotalVatPrice($invoice),2, '.', ''));
  }

  /**
   * Calculates the total price of an invoice excluding VAT.
   *
   * @param InvoiceJobModel $invoice
   * @return float
   */
  private function calculateInvoiceTotalPrice(InvoiceJobModel $invoice): float {
    $price = 0;
    foreach ($invoice->getInvoiceItems() as $item) {
      $price += $item->getTotalPrice();
    }
    return $price;
  }

  /**
   * Calculate total VAT amount for an invoice.
   *
   * @param InvoiceJobModel $invoice
   * @return float
   */
  private function calculateTotalVatPrice(InvoiceJobModel $invoice): float {
    $price = 0;
    foreach ($invoice->getInvoiceItems() as $item) {
      $price += $item->getTotalPrice() * $item->getVatRate();
    }
    return $price;
  }

  /**
   * Calculate the total invoice price.
   *
   * @param InvoiceJobModel $invoice
   * @return float
   */
  private function calculateTotalPrice(InvoiceJobModel $invoice): float {
    return $this->calculateInvoiceTotalPrice($invoice) + $this->calculateTotalVatPrice($invoice);
  }
}
