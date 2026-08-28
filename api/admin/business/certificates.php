<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/business-storage.php';

final class CertificateRequestException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 400) { parent::__construct($message); }
}

function certificate_response(array $body, int $status = 200): never { header('X-Content-Type-Options: nosniff'); json_response($body, $status); }

function certificate_read_json(): array
{
    if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== 0) throw new CertificateRequestException('JSON request required.', 415);
    if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 131072) throw new CertificateRequestException('Request is too large.', 413);
    $raw = file_get_contents('php://input', false, null, 0, 131073);
    if ($raw === false || strlen($raw) > 131072) throw new CertificateRequestException('Request is too large.', 413);
    try { $value = json_decode($raw, true, 64, JSON_THROW_ON_ERROR); } catch (JsonException) { throw new CertificateRequestException('Invalid request data.'); }
    if (!is_array($value) || array_is_list($value)) throw new CertificateRequestException('Invalid request data.'); return $value;
}

function certificate_text(mixed $value, string $field, int $maximum, bool $required = false): string
{
    if (!is_string($value)) throw new CertificateRequestException($required ? "$field is required." : "Invalid $field.");
    $value = trim($value); $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    if (($required && $value === '') || $length > $maximum) throw new CertificateRequestException("Invalid $field."); return $value;
}

function certificate_date(mixed $value, string $field): string
{
    if (!is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) !== 1) throw new CertificateRequestException("Invalid $field.");
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if ($date === false || $date->format('Y-m-d') !== $value) throw new CertificateRequestException("Invalid $field."); return $value;
}

function certificate_stored_date(mixed $value): bool
{
    if (!is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) !== 1) return false;
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value); return $date !== false && $date->format('Y-m-d') === $value;
}

function certificate_assert_dataset(array $records): void
{
    $fields = ['id', 'certificateNumber', 'customerId', 'invoiceNumber', 'certificateDate', 'items', 'remarks'];
    $itemFields = ['id', 'refillingItemId', 'capacity', 'quantity', 'serialNumbers', 'refillingDate', 'nextRefillingDate', 'remark']; $ids = [];
    foreach ($records as $record) {
        if (!is_array($record) || array_keys($record) !== $fields || !is_string($record['id']) || preg_match('/^CERT\d{4,}$/D', $record['id']) !== 1 || isset($ids[$record['id']])
            || !is_string($record['certificateNumber']) || trim($record['certificateNumber']) === '' || !is_string($record['customerId']) || preg_match('/^CUS\d{4,}$/D', $record['customerId']) !== 1
            || !is_string($record['invoiceNumber']) || trim($record['invoiceNumber']) === '' || !certificate_stored_date($record['certificateDate'])
            || !is_array($record['items']) || !array_is_list($record['items']) || count($record['items']) < 1 || !is_string($record['remarks'])) throw new BusinessStorageException('The certificates business dataset failed validation.');
        $itemIds = [];
        foreach ($record['items'] as $item) {
            if (!is_array($item) || array_keys($item) !== $itemFields || !is_string($item['id']) || preg_match('/^CIT\d{4,}$/D', $item['id']) !== 1 || isset($itemIds[$item['id']])
                || !is_string($item['refillingItemId']) || preg_match('/^RFI\d{4,}$/D', $item['refillingItemId']) !== 1 || !is_string($item['capacity']) || trim($item['capacity']) === ''
                || !is_int($item['quantity']) || $item['quantity'] < 1 || !is_array($item['serialNumbers']) || !array_is_list($item['serialNumbers'])
                || !certificate_stored_date($item['refillingDate']) || !certificate_stored_date($item['nextRefillingDate']) || $item['nextRefillingDate'] < $item['refillingDate'] || !is_string($item['remark'])) throw new BusinessStorageException('The certificates business dataset failed validation.');
            foreach ($item['serialNumbers'] as $serial) if (!is_string($serial) || trim($serial) === '') throw new BusinessStorageException('The certificates business dataset failed validation.');
            $itemIds[$item['id']] = true;
        }
        $ids[$record['id']] = true;
    }
}

function certificate_assert_masters(array $records, string $kind): void
{
    foreach ($records as $record) if (!is_array($record) || !is_string($record['id'] ?? null) || !is_string($record['name'] ?? null) || !is_bool($record['isActive'] ?? null)) throw new BusinessStorageException("The $kind business dataset failed validation.");
}

function certificate_master(array $records, string $id): ?array { $index = business_find_record_index($records, $id); return $index === null ? null : $records[$index]; }

function certificate_item_data(array $input): array
{
    $allowed = ['id', 'refillingItemId', 'capacity', 'quantity', 'serialNumbers', 'refillingDate', 'nextRefillingDate', 'remark'];
    if (array_diff(array_keys($input), $allowed) !== [] || array_diff(['refillingItemId', 'capacity', 'quantity', 'refillingDate', 'nextRefillingDate'], array_keys($input)) !== []) throw new CertificateRequestException('Invalid Certificate Item fields.');
    $refillingItemId = certificate_text($input['refillingItemId'], 'refillingItemId', 32, true);
    if (preg_match('/^RFI\d{4,}$/D', $refillingItemId) !== 1) throw new CertificateRequestException('Invalid refillingItemId.');
    if (!is_int($input['quantity']) || $input['quantity'] < 1) throw new CertificateRequestException('Quantity must be a positive integer.');
    $serials = $input['serialNumbers'] ?? []; if (!is_array($serials) || !array_is_list($serials)) throw new CertificateRequestException('Invalid serialNumbers.'); $normalized = [];
    foreach ($serials as $serial) { if (!is_string($serial)) throw new CertificateRequestException('Invalid serialNumbers.'); $serial = trim($serial); if ($serial !== '') $normalized[] = $serial; }
    $refillingDate = certificate_date($input['refillingDate'], 'refillingDate'); $next = certificate_date($input['nextRefillingDate'], 'nextRefillingDate');
    if ($next < $refillingDate) throw new CertificateRequestException('Next Refilling Date cannot be earlier than Refilling Date.');
    return ['refillingItemId' => $refillingItemId, 'capacity' => certificate_text($input['capacity'], 'capacity', 120, true), 'quantity' => $input['quantity'], 'serialNumbers' => $normalized,
        'refillingDate' => $refillingDate, 'nextRefillingDate' => $next, 'remark' => certificate_text($input['remark'] ?? '', 'remark', 1000)];
}

function certificate_header_data(array $input): array
{
    $allowed = ['certificateNumber', 'customerId', 'invoiceNumber', 'certificateDate', 'items', 'remarks'];
    if (array_diff(array_keys($input), $allowed) !== [] || array_diff(['certificateNumber', 'customerId', 'invoiceNumber', 'certificateDate', 'items'], array_keys($input)) !== []) throw new CertificateRequestException('Invalid Certificate fields.');
    $customerId = certificate_text($input['customerId'], 'customerId', 32, true); if (preg_match('/^CUS\d{4,}$/D', $customerId) !== 1) throw new CertificateRequestException('Invalid customerId.');
    if (!is_array($input['items']) || !array_is_list($input['items']) || count($input['items']) < 1) throw new CertificateRequestException('At least one Certificate Item is required.');
    return ['certificateNumber' => certificate_text($input['certificateNumber'], 'certificateNumber', 160, true), 'customerId' => $customerId,
        'invoiceNumber' => certificate_text($input['invoiceNumber'], 'invoiceNumber', 160, true), 'certificateDate' => certificate_date($input['certificateDate'], 'certificateDate'),
        'items' => $input['items'], 'remarks' => certificate_text($input['remarks'] ?? '', 'remarks', 2000)];
}

function certificate_assert_unique(array $records, string $number, ?string $exclude = null): void
{
    $normalized = business_normalize_text($number); foreach ($records as $record) if ($record['id'] !== $exclude && business_normalize_text($record['certificateNumber']) === $normalized) throw new CertificateRequestException('That Certificate Number already exists.', 409);
}

function certificate_status(string $date): ?string
{
    $today = new DateTimeImmutable('today', new DateTimeZone('Asia/Kolkata')); $todayText = $today->format('Y-m-d');
    if ($date < $todayText) return 'OVERDUE'; if ($date === $todayText) return 'DUE_TODAY'; if ($date <= $today->modify('+30 days')->format('Y-m-d')) return 'UPCOMING'; return null;
}

function certificate_enrich(array $record, array $customers, array $masters): array
{
    $customer = certificate_master($customers, $record['customerId']); if ($customer === null) throw new BusinessStorageException('Certificate customer reference is unavailable.');
    $priority = ['OVERDUE' => 3, 'DUE_TODAY' => 2, 'UPCOMING' => 1]; $best = null; $items = [];
    foreach ($record['items'] as $item) { $master = certificate_master($masters, $item['refillingItemId']); if ($master === null) throw new BusinessStorageException('Certificate refilling item reference is unavailable.');
        $status = certificate_status($item['nextRefillingDate']); if ($status !== null && ($best === null || $priority[$status] > $priority[$best])) $best = $status;
        $items[] = [...$item, 'refillingItemName' => $master['name'], 'refillingItemActive' => $master['isActive'], 'nextRefillStatus' => $status]; }
    $dates = array_column($record['items'], 'nextRefillingDate'); sort($dates);
    return [...$record, 'items' => $items, 'customerName' => $customer['name'], 'customerAddress' => $customer['address'] ?? '', 'customerActive' => $customer['isActive'], 'itemCount' => count($items), 'earliestNextRefillingDate' => $dates[0], 'nextRefillStatus' => $best];
}

auth_apply_cors(['GET', 'POST', 'PATCH', 'DELETE']); header('Allow: GET, POST, PATCH, DELETE, OPTIONS'); $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'POST', 'PATCH', 'DELETE'], true)) certificate_response(['success' => false, 'message' => 'Method not allowed.'], 405); require_admin_auth();

try {
    $customers = business_read_dataset('customers'); $masters = business_read_dataset('refilling-items'); certificate_assert_masters($customers, 'customers'); certificate_assert_masters($masters, 'refilling-items');
    if ($method === 'GET') { $records = business_read_dataset('certificates'); certificate_assert_dataset($records); $id = $_GET['id'] ?? null;
        if ($id === null || $id === '') certificate_response(['success' => true, 'data' => array_map(fn($record) => certificate_enrich($record, $customers, $masters), $records)]);
        if (!is_string($id) || preg_match('/^CERT\d{4,}$/D', $id) !== 1) throw new CertificateRequestException('Invalid Certificate ID.'); $index = business_find_record_index($records, $id);
        if ($index === null) throw new CertificateRequestException('Certificate not found.', 404); certificate_response(['success' => true, 'data' => certificate_enrich($records[$index], $customers, $masters)]); }
    if (!csrf_verify_request()) certificate_response(['success' => false, 'message' => 'Invalid security token.'], 403); $input = certificate_read_json();
    $result = business_mutate_dataset('certificates', function (array &$records) use ($method, $input, $customers, $masters): array {
        certificate_assert_dataset($records);
        if ($method === 'DELETE') { if (array_keys($input) !== ['id'] || !is_string($input['id']) || preg_match('/^CERT\d{4,}$/D', $input['id']) !== 1) throw new CertificateRequestException('Invalid delete request.');
            $index = business_find_record_index($records, $input['id']); if ($index === null) throw new CertificateRequestException('Certificate not found.', 404); $deleted = $records[$index]; array_splice($records, $index, 1); return $deleted; }
        if ($method === 'POST' && array_key_exists('id', $input)) throw new CertificateRequestException('Certificate IDs are server managed.');
        $id = $method === 'PATCH' ? ($input['id'] ?? null) : null; $current = null;
        if ($method === 'PATCH') { if (!is_string($id) || preg_match('/^CERT\d{4,}$/D', $id) !== 1) throw new CertificateRequestException('Invalid Certificate ID.'); $index = business_find_record_index($records, $id); if ($index === null) throw new CertificateRequestException('Certificate not found.', 404); $current = $records[$index]; }
        $payload = $input; unset($payload['id']); $data = certificate_header_data($payload); $customer = certificate_master($customers, $data['customerId']);
        if ($customer === null) throw new CertificateRequestException('Customer not found.'); if (!$customer['isActive'] && ($current === null || $data['customerId'] !== $current['customerId'])) throw new CertificateRequestException('Select an active Customer.');
        certificate_assert_unique($records, $data['certificateNumber'], $id); $oldItems = $current['items'] ?? []; $built = []; $usedItemIds = [];
        foreach ($data['items'] as $rawItem) { if (!is_array($rawItem)) throw new CertificateRequestException('Invalid Certificate Item.'); $itemId = $rawItem['id'] ?? null; $old = null;
            if ($itemId !== null) { if (!is_string($itemId) || preg_match('/^CIT\d{4,}$/D', $itemId) !== 1 || isset($usedItemIds[$itemId])) throw new CertificateRequestException('Invalid Certificate Item ID.'); $oldIndex = business_find_record_index($oldItems, $itemId); if ($oldIndex === null) throw new CertificateRequestException('Certificate Item ID does not belong to this Certificate.'); $old = $oldItems[$oldIndex]; }
            $item = certificate_item_data($rawItem); $master = certificate_master($masters, $item['refillingItemId']); if ($master === null) throw new CertificateRequestException('Refilling Item not found.');
            if (!$master['isActive'] && ($old === null || $item['refillingItemId'] !== $old['refillingItemId'])) throw new CertificateRequestException('Select an active Refilling Item.');
            if ($itemId === null) $itemId = business_next_record_id([...$oldItems, ...$built], 'CIT'); $usedItemIds[$itemId] = true; $built[] = ['id' => $itemId, ...$item]; }
        $recordId = $id ?? business_next_record_id($records, 'CERT'); $record = ['id' => $recordId, 'certificateNumber' => $data['certificateNumber'], 'customerId' => $data['customerId'], 'invoiceNumber' => $data['invoiceNumber'], 'certificateDate' => $data['certificateDate'], 'items' => $built, 'remarks' => $data['remarks']];
        if ($current === null) $records[] = $record; else $records[$index] = $record; return $record;
    }); certificate_response(['success' => true, 'data' => certificate_enrich($result, $customers, $masters)], $method === 'POST' ? 201 : 200);
} catch (CertificateRequestException $exception) { certificate_response(['success' => false, 'message' => $exception->getMessage()], $exception->status); }
catch (Throwable) { certificate_response(['success' => false, 'message' => $method === 'GET' ? 'Certificate data is temporarily unavailable.' : 'Unable to update Certificate data.'], 500); }
