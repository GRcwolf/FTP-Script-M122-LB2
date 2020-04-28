<?php


namespace App\Service;

use App\Exception\NotANumberException;
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
   * @var LoggerService
   */
  private $logger;

  /**
   * @var ContainerParametersHelper
   */
  private $helper;

  /**
   * InvoiceExporterService constructor.
   *
   * @param LoggerService $logger
   * @param ContainerParametersHelper $helper
   * @param Filesystem $filesystem
   */
  public function __construct(LoggerService $logger, ContainerParametersHelper $helper, Filesystem $filesystem)
  {
    $this->finder = new Finder();
    $this->helper = $helper;
    $this->filesystem = $filesystem;
    $this->getTemplate();
    $this->logger = $logger;
    $this->generateFolderStructure();
  }

  private function generateFolderStructure()
  {
    $tmpPath = $this->helper->getTempFilesFolder();
    $this->filesystem->mkdir($tmpPath . '/txt');
    $this->filesystem->mkdir($tmpPath . '/xml');
  }

  /**
   * Generates a xml file containing the invoice information.
   *
   * @param InvoiceJobModel $invoice
   * @return SimpleXMLElement
   * @throws NotANumberException
   */
  public function getXml(InvoiceJobModel $invoice): SimpleXMLElement
  {
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
  public function saveInvoiceXml(InvoiceJobModel $invoice): void
  {
    try {
      $xml = $this->getXml($invoice);
    } catch (NotANumberException $exception) {
      return;
    }
    $path = $this->helper->getTempFilesFolder() . '/xml';
    $fileName = $this->generateFileName($invoice) . '.xml';
    $xml->saveXML($path . '/' . $fileName);
    $this->logger->info('Generated file ' . $fileName . '.');
  }

  /**
   * Generate a file name.
   *
   * @param InvoiceJobModel $invoice
   * @return string
   */
  private function generateFileName(InvoiceJobModel $invoice): string
  {
    $name = '';
    $name .= $invoice->getSender()->getCustomerNumber() . '_';
    $name .= $invoice->getInvoiceNumber() . '_invoice';
    return $name;
  }

  /**
   * Get the base xml file.
   */
  private function getTemplate(): void
  {
    $path = $this->helper->getApplicationRootDir() . '/private/template';
    $xmlFile = null;
    /** @var SplFileInfo $file */
    foreach ($this->finder->in($path) as $file) {
      if (preg_match('/^invoice\.xml$/', $file->getFilename())) {
        $xmlFile = $file;
        break;
      }
    }
    $handle = fopen($xmlFile->getPathname(), 'r');
    if ($handle) {
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
   *
   * @throws NotANumberException
   */
  private function addXmlInvoiceHeaderData(SimpleXMLElement $xml, InvoiceJobModel $invoice)
  {
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
  private function addXmlMetaData(SimpleXMLElement $xml, InvoiceJobModel $invoice): void
  {
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
  private function formatDate(DateTime $dateTime): string
  {
    return $dateTime->format('YmdHis') . '000';
  }

  /**
   * Sets the origin of the invoice to the xml.
   *
   * @param SimpleXMLElement $xml
   * @param InvoiceJobModel $invoice
   */
  private function addXmlOriginData(SimpleXMLElement $xml, InvoiceJobModel $invoice): void
  {
    $buyerName = 'I.H.020_Einkaeufer_Identifikation';
    $sender = $invoice->getSender();
    $xml->Invoice_Header->$buyerName->addChild('BV.020_Nr_Kaeufer_beim_Kaeufer', $invoice->getSender()->getCustomerNumber());
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
  private function addXmlInvoiceAddress(SimpleXMLElement $xml, InvoiceJobModel $invoice): void
  {
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
   *
   * @throws NotANumberException
   */
  private function addXmlPayingConditions(SimpleXMLElement $xml, InvoiceJobModel $invoice): void
  {
    $payingConditionsName = 'I.H.080_Zahlungsbedingungen';
    $dueDate = $invoice->getDateTime();
    $daysToPay = $invoice->getDaysToPay();
    try {
      $dueDate->add(new DateInterval('P' . $daysToPay . 'D'));
    } catch (Exception $e) {
      $this->logger->alert('The duration of the days to pay in do not seem to be a number in invoice ' . $invoice->getInvoiceNumber() . ', please check. Aborting further processing. Error: ' . $e->getMessage());
      throw new NotANumberException();
    }
    $xml->Invoice_Header->$payingConditionsName->addChild('BV.020_Zahlungsbedingungen_Zusatzwert', $this->formatDate($dueDate));
  }

  /**
   * Add the vat information the the xml.
   *
   * @param SimpleXMLElement $xml
   * @param InvoiceJobModel $invoice
   */
  private function addXmlVatInformation(SimpleXMLElement $xml, InvoiceJobModel $invoice): void
  {
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
  private function addXmlInvoiceDetails(SimpleXMLElement $xml, InvoiceJobModel $invoice): void
  {
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
  private function addXmlInvoiceItem(SimpleXMLElement $xml, InvoiceItemModel $item, InvoiceJobModel $invoice): void
  {
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
  private function addXmlInvoiceItemBasicData(SimpleXMLElement $xml, InvoiceItemModel $item, InvoiceJobModel $invoice): void
  {
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
  private function addXmlInvoiceItemPriceAndAmount(SimpleXMLElement $xml, InvoiceItemModel $item): void
  {
    $xmlName = 'I.D.020_Preise_und_Mengen';
    $index = $item->getIndex() - 1;
    $xml->Invoice_Detail->Invoice_Items->addChild($xmlName);
    $xml->Invoice_Detail->Invoice_Items->$xmlName[$index]->addChild('BV.010_Verrechnete_Menge', $item->getCount());
    $xml->Invoice_Detail->Invoice_Items->$xmlName[$index]->addChild('BV.020_Mengeneinheit_der_verrechneten_Menge', 'BLL');
    $xml->Invoice_Detail->Invoice_Items->$xmlName[$index]->addChild('BV.030_Verrechneter_Einzelpreis_des_Artikels', $item->getPricePerUnit());
    $xml->Invoice_Detail->Invoice_Items->$xmlName[$index]->addChild('BV.040_Waehrung_des_Einzelpreises', 'CHF');
    $xml->Invoice_Detail->Invoice_Items->$xmlName[$index]->addChild('BV.070_Bestaetigter_Gesamtpreis_der_Position_netto', $item->getTotalPrice());
    $xml->Invoice_Detail->Invoice_Items->$xmlName[$index]->addChild('BV.080_Bestaetigter_Gesamtpreis_der_Position_brutto', $this->calculateTotalItemPriceIncludingVat($item));
    $xml->Invoice_Detail->Invoice_Items->$xmlName[$index]->addChild('BV.090_Waehrung_des_Gesamtpreises', 'CHF');
  }

  /**
   * Calculates the whole price including VAT.
   *
   * @param InvoiceItemModel $item
   * @return float
   */
  private function calculateTotalItemPriceIncludingVat(InvoiceItemModel $item): float
  {
    $totalPrice = $item->getPricePerUnit() * $item->getCount();
    return $totalPrice * $item->getVatRate();
  }

  /**
   * Add tax information of the item to the xml file.
   *
   * @param SimpleXMLElement $xml
   * @param InvoiceItemModel $item
   */
  private function addXmlInvoiceItemsTaxes(SimpleXMLElement $xml, InvoiceItemModel $item): void
  {
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
  private function calculateTotalItemPrice(InvoiceItemModel $item): float
  {
    return $item->getTotalPrice() * $item->getVatRate();
  }

  /**
   * Escapes a string for usage in xml.
   *
   * @param string $str
   * @return string
   */
  private function xmlEscapeString(string $str): string
  {
    return str_replace(['"', '\'', '<', '>', '&'], ['&quot;', '&apos;', '&lt;', '&gt;', '&amp;'], $str);
  }

  /**
   * Parses the invoice summary part of the xml.
   *
   * @param SimpleXMLElement $xml
   * @param InvoiceJobModel $invoice
   */
  private function addXmlSummary(SimpleXMLElement $xml, InvoiceJobModel $invoice): void
  {
    $this->addXmlSummaryBasicData($xml, $invoice);
    $this->addXmlSummaryTaxes($xml, $invoice);
  }

  /**
   * Parses the basic data of the xml invoice summary.
   *
   * @param SimpleXMLElement $xml
   * @param InvoiceJobModel $invoice
   */
  private function addXmlSummaryBasicData(SimpleXMLElement $xml, InvoiceJobModel $invoice): void
  {
    $xmlName = 'I.S.010_Basisdaten';
    $xml->Invoice_Summary->$xmlName->addChild('BV.010_Anzahl_der_Rechnungspositionen', count($invoice->getInvoiceItems()));
    $xml->Invoice_Summary->$xmlName->addChild('BV.020_Gesamtbetrag_der_Rechnung_exkl_MwSt_exkl_Ab_Zuschlag', number_format($this->calculateInvoiceTotalPrice($invoice), 2, '.', ''));
    $xml->Invoice_Summary->$xmlName->addChild('BV.040_Gesamtbetrag_der_Rechnung_exkl_MwSt_inkl_Ab_Zuschlag', number_format($this->calculateTotalVatPrice($invoice), 2, '.', ''));
    $xml->Invoice_Summary->$xmlName->addChild('BV.080_Gesamtbetrag_der_Rechnung_inkl_MwSt_inkl_Ab_Zuschlag', number_format($this->calculateTotalPrice($invoice), 2, '.', ''));
    $xml->Invoice_Summary->$xmlName->addChild('BV.060_Steuerbetrag', $this->calculateInvoiceTotalPrice($invoice));
  }

  /**
   * Add information about taxes to summary.
   *
   * @param SimpleXMLElement $xml
   * @param InvoiceJobModel $invoice
   */
  private function addXmlSummaryTaxes(SimpleXMLElement $xml, InvoiceJobModel $invoice): void
  {
    $xmlName = 'I.S.020_Aufschluesselung_der_Steuern';
    // @TODO: Add real vat rate.
    $xml->Invoice_Summary->$xmlName->addChild('BV.030_Steuersatz', '0.00');
    $xml->Invoice_Summary->$xmlName->addChild('BV.040_Zu_versteuernder_Betrag', number_format($this->calculateInvoiceTotalPrice($invoice), 2, '.', ''));
    $xml->Invoice_Summary->$xmlName->addChild('BV.050_Steuerbetrag', number_format($this->calculateTotalVatPrice($invoice), 2, '.', ''));
  }

  /**
   * Calculates the total price of an invoice excluding VAT.
   *
   * @param InvoiceJobModel $invoice
   * @return float
   */
  private function calculateInvoiceTotalPrice(InvoiceJobModel $invoice): float
  {
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
  private function calculateTotalVatPrice(InvoiceJobModel $invoice): float
  {
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
  private function calculateTotalPrice(InvoiceJobModel $invoice): float
  {
    return $this->calculateInvoiceTotalPrice($invoice) + $this->calculateTotalVatPrice($invoice);
  }

  /**
   * Saves the invoice as txt file.
   *
   * @param InvoiceJobModel $invoice
   */
  public function saveTxtInvoice(InvoiceJobModel $invoice): void
  {
    $txt = $this->getTxt($invoice);
    $path = $path = $this->helper->getTempFilesFolder() . '/txt';
    $fileName = $this->generateFileName($invoice) . '.txt';
    $this->filesystem->dumpFile($path . '/' . $fileName, $txt);
    $this->logger->info('Generated file ' . $fileName . '.');
  }

  /**
   * Parses the invoice as txt file.
   *
   * @param InvoiceJobModel $invoice
   * @return string
   */
  public function getTxt(InvoiceJobModel $invoice): string
  {
    $txt = [];
    for ($i = 0; $i < 61; $i++) {
      $txt[] = '';
    }
    // Add elements to file.
    $this->addTxtSender($invoice, $txt);
    $this->addTxtLocationAndDate($invoice, $txt);
    $this->addTxtReceiver($invoice, $txt);
    $this->addTxtMetaData($invoice, $txt);
    $this->addTxtBody($invoice, $txt);
    $this->addTxtPaymentGoal($invoice, $txt);
    $this->addTxtPaymentSlip($invoice, $txt);
    return implode("\r\n", $txt);
  }

  /**
   * Adds the sender to the txt invoice.
   *
   * @param InvoiceJobModel $invoice
   * @param array $txt
   */
  private function addTxtSender(InvoiceJobModel $invoice, array &$txt): void
  {
    $sender = $invoice->getSender();
    $txt[0] = $sender->getSalutation();
    $txt[1] = $sender->getName();
    $txt[2] = $sender->getAddress();
    $txt[3] = $sender->getZipLocation();
    $txt[4] = $sender->getVatNumber();
  }

  /**
   * Adds the location and date to the txt invoice.
   *
   * @param InvoiceJobModel $invoice
   * @param array $txt
   */
  private function addTxtLocationAndDate(InvoiceJobModel $invoice, array &$txt): void
  {
    $currentDate = new DateTime();
    $spaces = $this->getSpaces(50);
    $txt[9] = $invoice->getLocation() . ', den ' . $currentDate->format('d.m.Y') . $spaces;
    $txt[9] = substr($txt[9], 0, 50);
  }

  /**
   * Adds the receiver to the txt invoice.
   *
   * @param InvoiceJobModel $invoice
   * @param array $txt
   */
  private function addTxtReceiver(InvoiceJobModel $invoice, array &$txt): void
  {
    $spaces = $this->getSpaces(49);
    $receiver = $invoice->getReceiver();
    $txt[9] .= $receiver->getName();
    $txt[10] .= $spaces . $receiver->getAddress();
    $txt[11] .= $spaces . $receiver->getZipLocation();
  }

  /**
   * Add the meta data to the txt invoice.
   *
   * @param InvoiceJobModel $invoice
   * @param array $txt
   */
  private function addTxtMetaData(InvoiceJobModel $invoice, array &$txt): void
  {
    $customerNumberLabel = 'Kundennummer:';
    $jobIdLabel = 'Auftragsnummer:';
    $txt[13] = $customerNumberLabel .
      $this->getSpaces(19 - strlen($customerNumberLabel)) .
      $invoice->getSender()->getCustomerNumber();
    $txt[14] = $jobIdLabel .
      $this->getSpaces(19 - strlen($jobIdLabel)) .
      $invoice->getJobId();
  }

  /**
   * Adds the main txt invoice body.
   *
   * @param InvoiceJobModel $invoice
   * @param array $txt
   */
  private function addTxtBody(InvoiceJobModel $invoice, array &$txt): void
  {
    $invoiceNumberLabel = 'Rechnung Nr';
    $txt[16] = $invoiceNumberLabel .
      $this->getSpaces(18 - strlen($invoiceNumberLabel)) .
      $invoice->getInvoiceNumber();
    $txt[17] = '-----------------------';
    $this->addTxtInvoiceItems($invoice, $txt);
  }

  /**
   * Adds the invoice items to the txt invoice.
   *
   * @param InvoiceJobModel $invoice
   * @param array $txt
   */
  private function addTxtInvoiceItems(InvoiceJobModel $invoice, array &$txt): void
  {
    $index = 0;
    $currency = 'CHF';
    $totalInvoicePrice = (string)number_format($this->calculateInvoiceTotalPrice($invoice), 2, '.', '');
    $totalVat = (string)number_format($this->calculateTotalVatPrice($invoice), 2, '.', '');
    foreach ($invoice->getInvoiceItems() as $invoiceItem) {
      $this->addTxtSingleItem($invoiceItem, $index, $txt);
      $index++;
    }
    $txt[18 + $index] = $this->getSpaces(62) . '-----------';
    $totalPriceLabel = 'Total ' . $currency;
    $txt[19 + $index] = $this->getSpaces(48) .
      $totalPriceLabel .
      $this->getSpaces(16 - strlen($totalInvoicePrice)) .
      $totalInvoicePrice;
    $vatLabel = 'MWST  ' . $currency;
    $txt[21 + $index] = $this->getSpaces(48) .
      $vatLabel .
      $this->getSpaces(16 - strlen($totalVat)) .
      $totalVat;
  }

  /**
   * Adds a single item to the txt invoice.
   *
   * @param InvoiceItemModel $invoiceItem
   * @param int $index
   * @param $txt
   */
  private function addTxtSingleItem(InvoiceItemModel $invoiceItem, int $index, &$txt): void
  {
    $currency = 'CHF';
    $description = $invoiceItem->getItemDescription();
    $count = (string)$invoiceItem->getCount();
    $vat = (string)number_format($invoiceItem->getVatRate(), 2, '.', '') . '%';
    $unitPrice = (string)number_format($invoiceItem->getPricePerUnit(), 2, '.', '');
    $totalPrice = (string)number_format($invoiceItem->getTotalPrice(), 2, '.', '');
    $txt[18 + $index] = $this->getSpaces(2) .
      $invoiceItem->getIndex() .
      $this->getSpaces(2) .
      $description .
      $this->getSpaces(39 - strlen($description)) .
      $count .
      $this->getSpaces(2 - strlen($count)) .
      $this->getSpaces(10 - strlen($unitPrice)) .
      $unitPrice .
      $this->getSpaces(2) .
      $currency .
      $this->getSpaces(4 - strlen($currency)) .
      $this->getSpaces(11 - strlen($totalPrice)) .
      $totalPrice .
      $this->getSpaces(1) .
      $this->getSpaces(6 - strlen($vat)) .
      $vat;
  }

  /**
   * Add the information about the payment goal to the txt invoice.
   *
   * @param InvoiceJobModel $invoice
   * @param array $txt
   */
  private function addTxtPaymentGoal(InvoiceJobModel $invoice, array &$txt): void
  {
    $date = new DateTime();
    $date->modify('+' . $invoice->getDaysToPay() . ' days');
    $txt[40] = 'Zahlungsziel ohne Abzug ' . $invoice->getDaysToPay() . ' Tage (' . $date->format('d.m.Y') . ')';
  }

  /**
   * Adds the payment slip to the txt invoice.
   *
   * @param InvoiceJobModel $invoice
   * @param array $txt
   */
  private function addTxtPaymentSlip(InvoiceJobModel $invoice, array &$txt): void
  {
    $txt[42] = 'Einzahlungsschein';
    $totalInvoicePrice = (string)number_format($this->calculateTotalPrice($invoice), 2, ' . ', '');
    $txt[54] = $this->getSpaces(13 - strlen($totalInvoicePrice)) .
      $totalInvoicePrice .
      $this->getSpaces(29 - strlen($totalInvoicePrice)) .
      $totalInvoicePrice .
      $this->getSpaces(5);
    $slipNumber = '0 00000 00000 00000';
    $txt[56] = $slipNumber . $this->getSpaces(47 - strlen($slipNumber));
    // Add address.
    $receiver = $invoice->getReceiver();
    $txt[54] .= $receiver->getName();
    $txt[55] = $this->getSpaces(47) . $receiver->getAddress();
    $txt[56] .= $receiver->getZipLocation();
    // Add address again.
    $txt[58] = $receiver->getName();
    $txt[59] = $receiver->getAddress();
    $txt[60] = $receiver->getZipLocation();
  }

  /**
   * Generates a specified amount of spaces.
   *
   * @param int $length
   * @return string
   */
  private function getSpaces(int $length): string
  {
    $spaces = '';
    for ($i = 0; $i < $length; $i++) {
      $spaces .= ' ';
    }
    return $spaces;
  }
}
