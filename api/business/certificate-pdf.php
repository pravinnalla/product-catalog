<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/business-storage.php';
require_once __DIR__ . '/../includes/company-profile.php';

function certificate_pdf_error(string $message, int $status): never
{
    header('X-Content-Type-Options: nosniff'); json_response(['success' => false, 'message' => $message], $status);
}

function certificate_pdf_date_valid(mixed $value): bool
{
    if (!is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) !== 1) return false;
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value); return $date !== false && $date->format('Y-m-d') === $value;
}

function certificate_pdf_date(string $value): string
{
    [$year, $month, $day] = explode('-', $value); return "$day/$month/$year";
}

function certificate_pdf_find(array $records, string $id): ?array
{
    $index = business_find_record_index($records, $id); return $index === null ? null : $records[$index];
}

/** @return array{certificate:array<string,mixed>,customer:array<string,mixed>,items:list<array<string,mixed>>} */
function certificate_pdf_load(string $id): array
{
    $certificates = business_read_dataset('certificates'); $customers = business_read_dataset('customers'); $masters = business_read_dataset('refilling-items');
    $certificate = certificate_pdf_find($certificates, $id); if ($certificate === null) throw new OutOfBoundsException('Certificate not found.');
    $required = ['id','certificateNumber','customerId','invoiceNumber','certificateDate','items','remarks'];
    if (array_keys($certificate) !== $required || !is_string($certificate['certificateNumber']) || trim($certificate['certificateNumber']) === ''
        || !is_string($certificate['customerId']) || !is_string($certificate['invoiceNumber']) || trim($certificate['invoiceNumber']) === ''
        || !certificate_pdf_date_valid($certificate['certificateDate']) || !is_array($certificate['items']) || !array_is_list($certificate['items']) || count($certificate['items']) < 1 || !is_string($certificate['remarks'])) throw new UnexpectedValueException('Certificate data is invalid.');
    $customer = certificate_pdf_find($customers, $certificate['customerId']);
    if ($customer === null || !is_string($customer['name'] ?? null) || trim($customer['name']) === '' || !is_string($customer['address'] ?? null)) throw new UnexpectedValueException('Certificate customer reference is invalid.');
    $resolved = []; $itemFields = ['id','refillingItemId','capacity','quantity','serialNumbers','refillingDate','nextRefillingDate','remark'];
    foreach ($certificate['items'] as $item) {
        if (!is_array($item) || array_keys($item) !== $itemFields || !is_string($item['id']) || !is_string($item['refillingItemId']) || !is_string($item['capacity']) || trim($item['capacity']) === ''
            || !is_int($item['quantity']) || $item['quantity'] < 1 || !is_array($item['serialNumbers']) || !array_is_list($item['serialNumbers'])
            || !certificate_pdf_date_valid($item['refillingDate']) || !certificate_pdf_date_valid($item['nextRefillingDate']) || $item['nextRefillingDate'] < $item['refillingDate'] || !is_string($item['remark'])) throw new UnexpectedValueException('Certificate Item data is invalid.');
        foreach ($item['serialNumbers'] as $serial) if (!is_string($serial) || trim($serial) === '') throw new UnexpectedValueException('Certificate serial-number data is invalid.');
        $master = certificate_pdf_find($masters, $item['refillingItemId']); if ($master === null || !is_string($master['name'] ?? null) || trim($master['name']) === '') throw new UnexpectedValueException('Certificate Refilling Item reference is invalid.');
        $resolved[] = [...$item, 'name' => $master['name']];
    }
    return ['certificate' => $certificate, 'customer' => $customer, 'items' => $resolved];
}

function certificate_pdf_note(array $items): string
{
    $dates = array_values(array_unique(array_column($items, 'nextRefillingDate')));
    return count($dates) === 1
        ? 'NOTE: NEXT REFILLING DUE FOR ALL FIRE EXTINGUISHERS ON ' . certificate_pdf_date($dates[0]) . '.'
        : 'NOTE: NEXT REFILLING DATES ARE AS MENTIONED AGAINST EACH ITEM ABOVE.';
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') { header('Allow: GET'); certificate_pdf_error('Method not allowed.', 405); }
require_admin_auth();
if (array_keys($_GET) !== ['id']) certificate_pdf_error('Certificate ID is required.', 400);
$id = $_GET['id']; if (!is_string($id) || preg_match('/^CERT\d{4,}$/D', $id) !== 1) certificate_pdf_error('Invalid Certificate ID.', 400);

try { $data = certificate_pdf_load($id); }
catch (OutOfBoundsException) { certificate_pdf_error('Certificate not found.', 404); }
catch (Throwable) { certificate_pdf_error('Certificate PDF cannot currently be generated because its saved reference data is invalid.', 500); }

$autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
if (!is_file($autoload)) certificate_pdf_error('Certificate PDF service is unavailable.', 500);
require_once $autoload;

final class CertificatePdf extends FPDF
{
    private array $profile; private ?string $logo; private string $certificateNumber = '';
    /** @var list<float> */ public array $widths = [12, 48, 20, 16, 27, 30, 32];
    public function __construct(array $profile, ?string $logo) { parent::__construct('P', 'mm', 'A4'); $this->profile = $profile; $this->logo = $logo; $this->SetMargins(12.5, 12, 12.5); $this->SetAutoPageBreak(true, 17); }
    public function setCertificateNumber(string $number): void { $this->certificateNumber = $number; }
    public function safe(string $text): string { $converted = @iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $text); return $converted === false ? preg_replace('/[^\x20-\x7E]/', '?', $text) : $converted; }
    public function Header(): void {
        if ($this->logo !== null) { try { $this->Image($this->logo, 13, 9, 49); } catch (Throwable) { /* text header remains */ } }
        $this->SetXY(66, 9); $this->SetFont('Arial', 'B', 13); $this->SetTextColor(38, 50, 56); $this->Cell(131, 6, $this->safe($this->profile['name']), 0, 1, 'R');
        $this->SetX(66); $this->SetFont('Arial', '', 8); $this->SetTextColor(80, 90, 96); $this->MultiCell(131, 4, $this->safe($this->profile['address']), 0, 'R');
        $this->SetX(66); $this->Cell(131, 4, $this->safe($this->profile['phones'] . ' | ' . $this->profile['email']), 0, 1, 'R');
        $this->SetDrawColor(190, 30, 45); $this->SetLineWidth(.5); $this->Line(12.5, 30, 197.5, 30); $this->SetY(34);
    }
    public function Footer(): void { $this->SetY(-12); $this->SetDrawColor(210); $this->Line(12.5, $this->GetY(), 197.5, $this->GetY()); $this->Ln(1.5); $this->SetFont('Arial', '', 7); $this->SetTextColor(110); $this->Cell(140, 4, $this->safe($this->profile['email'] . ' | ' . $this->profile['phones']), 0, 0); $this->Cell(45, 4, 'Page ' . $this->PageNo(), 0, 0, 'R'); }
    public function tableHeader(bool $continuation = false): void {
        if ($continuation) { $this->SetFont('Arial', 'B', 8); $this->SetTextColor(70); $this->Cell(185, 5, $this->safe('Certificate No.: ' . $this->certificateNumber . ' (continued)'), 0, 1, 'R'); }
        $labels = ['Sr. No.','Type of Fire Extinguisher','Capacity','Quantity','Refilling Date','Next Refilling Due','Remark']; $this->SetFillColor(38,50,56); $this->SetTextColor(255); $this->SetDrawColor(120); $this->SetFont('Arial','B',7.2);
        foreach ($labels as $i => $label) { $x=$this->GetX();$y=$this->GetY();$this->Rect($x,$y,$this->widths[$i],11,'DF');$this->SetXY($x,$y+1);$this->MultiCell($this->widths[$i],4,$this->safe($label),0,'C');$this->SetXY($x+$this->widths[$i],$y); } $this->Ln(11); $this->SetTextColor(30);
    }
    public function lines(float $width, string $text): int { $cw=$this->CurrentFont['cw'];$wmax=($width-2)*1000/$this->FontSize;$s=str_replace("\r",'', $this->safe($text));$nb=strlen($s);$sep=-1;$i=0;$j=0;$l=0;$nl=1;while($i<$nb){$c=$s[$i];if($c==="\n"){$i++;$sep=-1;$j=$i;$l=0;$nl++;continue;}if($c===' ')$sep=$i;$l+=$cw[$c]??600;if($l>$wmax){if($sep===-1){if($i===$j)$i++;}else$i=$sep+1;$sep=-1;$j=$i;$l=0;$nl++;}else$i++;}return $nl; }
    public function itemRow(array $cells, ?string $serials): void {
        $this->SetFont('Arial','',7.5);$height=6;foreach($cells as $i=>$cell)$height=max($height,$this->lines($this->widths[$i],(string)$cell)*4.2+2);$serialHeight=$serials===null?0:$this->lines(181,$serials)*4+3;
        if ($this->GetY()+$height+$serialHeight>275) { $this->AddPage(); $this->tableHeader(true); }
        $this->SetAutoPageBreak(false);$x0=$this->GetX();$y=$this->GetY();foreach($cells as $i=>$cell){$x=$this->GetX();$this->Rect($x,$y,$this->widths[$i],$height);$this->SetXY($x+1,$y+1);$this->MultiCell($this->widths[$i]-2,4.2,$this->safe((string)$cell),0,$i===0||$i===3?'C':'L');$this->SetXY($x+$this->widths[$i],$y);} $this->SetXY($x0,$y+$height);
        if ($serials !== null) { $this->SetFont('Arial','I',7.3);$this->SetFillColor(246,247,248);$this->Rect($x0,$this->GetY(),185,$serialHeight,'DF');$this->SetXY($x0+2,$this->GetY()+1);$this->MultiCell(181,4,$this->safe($serials)); }$this->SetAutoPageBreak(true,17);
    }
}

try {
    $certificate=$data['certificate'];$customer=$data['customer'];$items=$data['items'];$pdf=new CertificatePdf(company_public_profile(), company_pdf_logo_path());$pdf->SetCompression(false);$pdf->SetTitle('Certificate ' . $certificate['certificateNumber']);$pdf->SetAuthor('Laxmikant Traders');$pdf->setCertificateNumber($certificate['certificateNumber']);$pdf->AddPage();
    $pdf->SetTextColor(38,50,56);$pdf->SetFont('Arial','B',18);$pdf->Cell(185,10,'C E R T I F I C A T E',0,1,'C');$pdf->Ln(2);
    $pdf->SetFont('Arial','B',9);$pdf->Cell(34,6,'Certificate No.:');$pdf->SetFont('Arial','',9);$pdf->Cell(58,6,$pdf->safe($certificate['certificateNumber']));$pdf->SetFont('Arial','B',9);$pdf->Cell(31,6,'Certificate Date:');$pdf->SetFont('Arial','',9);$pdf->Cell(62,6,certificate_pdf_date($certificate['certificateDate']),0,1);
    $pdf->SetFont('Arial','B',9);$pdf->Cell(34,6,'Invoice Ref.:');$pdf->SetFont('Arial','',9);$pdf->Cell(151,6,$pdf->safe($certificate['invoiceNumber']),0,1);$pdf->Ln(2);
    $pdf->SetFillColor(246,247,248);$pdf->SetFont('Arial','B',9);$pdf->Cell(185,6,'Customer',0,1,'L',true);$pdf->SetFont('Arial','B',9);$pdf->Cell(185,5,$pdf->safe($customer['name']),0,1);$pdf->SetFont('Arial','',8.5);$pdf->MultiCell(185,4.5,$pdf->safe($customer['address']));$pdf->Ln(2);
    $pdf->SetFont('Arial','',8.8);$pdf->MultiCell(185,4.8,$pdf->safe('This is to certify that the fire extinguisher(s) listed below were refilled/serviced as recorded, with the next-refilling due dates shown against the respective items.'));$pdf->Ln(3);$pdf->tableHeader();
    foreach($items as $index=>$item){$serials=$item['serialNumbers']===[]?null:'Serial Nos.: '.implode(', ',$item['serialNumbers']);$pdf->itemRow([(string)($index+1),$item['name'],$item['capacity'],(string)$item['quantity'],certificate_pdf_date($item['refillingDate']),certificate_pdf_date($item['nextRefillingDate']),$item['remark']],$serials);}
    $pdf->Ln(4);if(trim($certificate['remarks'])!==''){$pdf->SetFont('Arial','B',8.5);$pdf->Cell(18,5,'Remarks:');$pdf->SetFont('Arial','',8.5);$pdf->MultiCell(167,5,$pdf->safe($certificate['remarks']));$pdf->Ln(2);}
    $note=certificate_pdf_note($items);$noteHeight=$pdf->lines(181,$note)*4.5+4;if($pdf->GetY()+$noteHeight+42>275)$pdf->AddPage();$pdf->SetFillColor(255,248,225);$pdf->SetFont('Arial','B',8.5);$pdf->MultiCell(185,5,$pdf->safe($note),1,'L',true);$pdf->Ln(5);
    $pdf->SetFont('Arial','B',9);$pdf->Cell(185,5,'For LAXMIKANT TRADERS',0,1,'R');$pdf->Ln(22);$pdf->SetFont('Arial','',9);$pdf->Cell(185,5,'Authorised Signatory',0,1,'R');
    $filename='certificate-'.preg_replace('/[^A-Za-z0-9_-]+/','-',$certificate['certificateNumber']).'.pdf';header('Content-Type: application/pdf');header('Content-Disposition: inline; filename="'.$filename.'"');header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');$pdf->Output('I',$filename);
} catch (Throwable) { if (!headers_sent()) certificate_pdf_error('Unable to generate Certificate PDF.',500); }
