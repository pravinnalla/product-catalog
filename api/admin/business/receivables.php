<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/business-storage.php';

final class ReceivableRequestException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 400) { parent::__construct($message); }
}

function receivable_response(array $body, int $status = 200): never
{
    header('X-Content-Type-Options: nosniff');
    json_response($body, $status);
}

function receivable_read_json(): array
{
    if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== 0) throw new ReceivableRequestException('JSON request required.', 415);
    if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 32768) throw new ReceivableRequestException('Request is too large.', 413);
    $raw = file_get_contents('php://input', false, null, 0, 32769);
    if ($raw === false || strlen($raw) > 32768) throw new ReceivableRequestException('Request is too large.', 413);
    try { $decoded = json_decode($raw, false, 48, JSON_THROW_ON_ERROR); }
    catch (JsonException) { throw new ReceivableRequestException('Invalid request data.'); }
    if (!is_object($decoded)) throw new ReceivableRequestException('Invalid request data.');
    return get_object_vars($decoded);
}

function receivable_date(mixed $value, string $field, bool $required): ?string
{
    if (!$required && ($value === null || $value === '')) return null;
    if (!is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) !== 1) throw new ReceivableRequestException("Invalid $field.");
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if ($date === false || $date->format('Y-m-d') !== $value) throw new ReceivableRequestException("Invalid $field.");
    return $value;
}

function receivable_stored_date_is_valid(mixed $value, bool $optional = false): bool
{
    if ($optional && $value === null) return true;
    if (!is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) !== 1) return false;
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
}

function receivable_text(mixed $value, string $field, int $maximum, bool $required = false): string
{
    if (!is_string($value)) throw new ReceivableRequestException($required ? "$field is required." : "Invalid $field.");
    $value = trim($value); $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    if (($required && $value === '') || $length > $maximum) throw new ReceivableRequestException("Invalid $field.");
    return $value;
}

function receivable_money(mixed $value, string $field): int
{
    if ((!is_int($value) && !is_float($value)) || !is_finite((float) $value)) throw new ReceivableRequestException("Invalid $field.");
    $cents = (int) round((float) $value * 100);
    if ($cents <= 0 || abs(((float) $value * 100) - $cents) > 0.00001) throw new ReceivableRequestException("Invalid $field.");
    return $cents;
}

function receivable_assert_customers(array $records): void
{
    foreach ($records as $record) {
        if (!is_array($record) || !is_string($record['id'] ?? null) || !is_string($record['name'] ?? null) || !is_bool($record['isActive'] ?? null)) {
            throw new BusinessStorageException('The customers business dataset failed validation.');
        }
    }
}

function receivable_customer(array $customers, string $id): array
{
    $index = business_find_record_index($customers, $id);
    if ($index === null) throw new ReceivableRequestException('Customer not found.', 400);
    return $customers[$index];
}

function receivable_payment_cents(array $payments): int
{
    $total = 0;
    foreach ($payments as $payment) $total += (int) round((float) $payment['amount'] * 100);
    return $total;
}

function receivable_assert_dataset(array $records): void
{
    $recordFields = ['id', 'customerId', 'invoiceNumber', 'invoiceDate', 'invoiceAmount', 'dueDate', 'businessType', 'payments', 'remarks'];
    $paymentFields = ['id', 'paymentDate', 'amount', 'paymentMode', 'reference', 'remarks']; $ids = [];
    foreach ($records as $record) {
        if (!is_array($record) || array_keys($record) !== $recordFields || !is_string($record['id'])
            || preg_match('/^REC\d{4,}$/D', $record['id']) !== 1 || isset($ids[$record['id']])
            || !is_string($record['customerId']) || preg_match('/^CUS\d{4,}$/D', $record['customerId']) !== 1
            || !is_string($record['invoiceNumber']) || trim($record['invoiceNumber']) === ''
            || !receivable_stored_date_is_valid($record['invoiceDate']) || !is_numeric($record['invoiceAmount']) || (float) $record['invoiceAmount'] <= 0
            || !receivable_stored_date_is_valid($record['dueDate'], true)
            || !in_array($record['businessType'], ['PRODUCT', 'REFILLING'], true)
            || !is_array($record['payments']) || !array_is_list($record['payments']) || !is_string($record['remarks'])) {
            throw new BusinessStorageException('The receivables business dataset failed validation.');
        }
        $paymentIds = [];
        foreach ($record['payments'] as $payment) {
            if (!is_array($payment) || array_keys($payment) !== $paymentFields || !is_string($payment['id'])
                || preg_match('/^PAY\d{4,}$/D', $payment['id']) !== 1 || isset($paymentIds[$payment['id']])
                || !is_numeric($payment['amount']) || (float) $payment['amount'] <= 0
                || !receivable_stored_date_is_valid($payment['paymentDate']) || !is_string($payment['paymentMode'])
                || !is_string($payment['reference']) || !is_string($payment['remarks'])) {
                throw new BusinessStorageException('The receivables business dataset failed validation.');
            }
            $paymentIds[$payment['id']] = true;
        }
        if (receivable_payment_cents($record['payments']) > (int) round((float) $record['invoiceAmount'] * 100)) {
            throw new BusinessStorageException('The receivables business dataset failed validation.');
        }
        $ids[$record['id']] = true;
    }
}

function receivable_header_data(array $input): array
{
    $allowed = ['customerId', 'invoiceNumber', 'invoiceDate', 'invoiceAmount', 'dueDate', 'businessType', 'remarks'];
    if (array_diff(array_keys($input), $allowed) !== [] || array_diff(['customerId', 'invoiceNumber', 'invoiceDate', 'invoiceAmount', 'businessType'], array_keys($input)) !== []) {
        throw new ReceivableRequestException('Invalid tracking record fields.');
    }
    $customerId = receivable_text($input['customerId'], 'customerId', 32, true);
    if (preg_match('/^CUS\d{4,}$/D', $customerId) !== 1) throw new ReceivableRequestException('Invalid customerId.');
    $type = $input['businessType']; if (!is_string($type) || !in_array($type, ['PRODUCT', 'REFILLING'], true)) throw new ReceivableRequestException('Invalid businessType.');
    return ['customerId' => $customerId, 'invoiceNumber' => receivable_text($input['invoiceNumber'], 'invoiceNumber', 160, true),
        'invoiceDate' => receivable_date($input['invoiceDate'], 'invoiceDate', true), 'invoiceAmount' => receivable_money($input['invoiceAmount'], 'invoiceAmount') / 100,
        'dueDate' => receivable_date($input['dueDate'] ?? null, 'dueDate', false), 'businessType' => $type,
        'remarks' => receivable_text($input['remarks'] ?? '', 'remarks', 2000)];
}

function receivable_payment_data(array $input): array
{
    $allowed = ['paymentDate', 'amount', 'paymentMode', 'reference', 'remarks'];
    if (array_diff(array_keys($input), $allowed) !== [] || array_diff(['paymentDate', 'amount'], array_keys($input)) !== []) throw new ReceivableRequestException('Invalid payment fields.');
    return ['paymentDate' => receivable_date($input['paymentDate'], 'paymentDate', true), 'amount' => receivable_money($input['amount'], 'amount') / 100,
        'paymentMode' => receivable_text($input['paymentMode'] ?? '', 'paymentMode', 120), 'reference' => receivable_text($input['reference'] ?? '', 'reference', 200),
        'remarks' => receivable_text($input['remarks'] ?? '', 'remarks', 1000)];
}

function receivable_assert_unique(array $records, string $customerId, string $invoiceNumber, ?string $exclude = null): void
{
    $invoice = business_normalize_text($invoiceNumber);
    foreach ($records as $record) if ($record['id'] !== $exclude && $record['customerId'] === $customerId && business_normalize_text($record['invoiceNumber']) === $invoice) {
        throw new ReceivableRequestException('That invoice is already tracked for this customer.', 409);
    }
}

function receivable_enrich(array $record, array $customers): array
{
    $customer = receivable_customer($customers, $record['customerId']); $paid = receivable_payment_cents($record['payments']);
    $invoice = (int) round((float) $record['invoiceAmount'] * 100); $balance = max(0, $invoice - $paid);
    $status = $paid === 0 ? 'UNPAID' : ($balance === 0 ? 'PAID' : 'PARTLY_PAID');
    $today = (new DateTimeImmutable('today', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d');
    return [...$record, 'customerName' => $customer['name'], 'customerActive' => $customer['isActive'], 'amountPaid' => $paid / 100,
        'balance' => $balance / 100, 'paymentStatus' => $status, 'overdue' => $balance > 0 && $record['dueDate'] !== null && $record['dueDate'] < $today];
}

auth_apply_cors(['GET', 'POST', 'PATCH', 'DELETE']);
header('Allow: GET, POST, PATCH, DELETE, OPTIONS'); $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'POST', 'PATCH', 'DELETE'], true)) receivable_response(['success' => false, 'message' => 'Method not allowed.'], 405);
require_admin_auth();

try {
    $customers = business_read_dataset('customers'); receivable_assert_customers($customers);
    if ($method === 'GET') {
        $records = business_read_dataset('receivables'); receivable_assert_dataset($records); $id = $_GET['id'] ?? null;
        if ($id === null || $id === '') receivable_response(['success' => true, 'data' => array_map(fn($record) => receivable_enrich($record, $customers), $records)]);
        if (!is_string($id) || preg_match('/^REC\d{4,}$/D', $id) !== 1) throw new ReceivableRequestException('Invalid receivable ID.');
        $index = business_find_record_index($records, $id); if ($index === null) throw new ReceivableRequestException('Tracking record not found.', 404);
        receivable_response(['success' => true, 'data' => receivable_enrich($records[$index], $customers)]);
    }
    if (!csrf_verify_request()) receivable_response(['success' => false, 'message' => 'Invalid security token.'], 403);
    $input = receivable_read_json(); $action = $_GET['action'] ?? 'receivable';
    if (!is_string($action) || !in_array($action, ['receivable', 'payment'], true)) throw new ReceivableRequestException('Unsupported action.');
    $result = business_mutate_dataset('receivables', function (array &$records) use ($method, $action, $input, $customers): array {
        receivable_assert_dataset($records);
        if ($action === 'receivable' && $method === 'POST') {
            if (array_key_exists('id', $input) || array_key_exists('payments', $input)) throw new ReceivableRequestException('IDs and payments are server managed.');
            $data = receivable_header_data($input); $customer = receivable_customer($customers, $data['customerId']);
            if (!$customer['isActive']) throw new ReceivableRequestException('New tracking records require an active customer.');
            receivable_assert_unique($records, $data['customerId'], $data['invoiceNumber']);
            $record = ['id' => business_next_record_id($records, 'REC'), ...$data, 'payments' => []];
            $record = ['id' => $record['id'], 'customerId' => $record['customerId'], 'invoiceNumber' => $record['invoiceNumber'], 'invoiceDate' => $record['invoiceDate'], 'invoiceAmount' => $record['invoiceAmount'], 'dueDate' => $record['dueDate'], 'businessType' => $record['businessType'], 'payments' => [], 'remarks' => $record['remarks']];
            $records[] = $record; return $record;
        }
        $id = $input['id'] ?? null; if (!is_string($id) || preg_match('/^REC\d{4,}$/D', $id) !== 1) throw new ReceivableRequestException('Invalid receivable ID.');
        $index = business_find_record_index($records, $id); if ($index === null) throw new ReceivableRequestException('Tracking record not found.', 404);
        if ($action === 'receivable' && $method === 'PATCH') {
            $data = $input; unset($data['id']); $data = receivable_header_data($data); $current = $records[$index]; $customer = receivable_customer($customers, $data['customerId']);
            if (!$customer['isActive'] && $data['customerId'] !== $current['customerId']) throw new ReceivableRequestException('Select an active customer.');
            if (receivable_payment_cents($current['payments']) > (int) round($data['invoiceAmount'] * 100)) throw new ReceivableRequestException('Invoice amount cannot be less than payments already received.');
            receivable_assert_unique($records, $data['customerId'], $data['invoiceNumber'], $id);
            $records[$index] = ['id' => $id, 'customerId' => $data['customerId'], 'invoiceNumber' => $data['invoiceNumber'], 'invoiceDate' => $data['invoiceDate'], 'invoiceAmount' => $data['invoiceAmount'], 'dueDate' => $data['dueDate'], 'businessType' => $data['businessType'], 'payments' => $current['payments'], 'remarks' => $data['remarks']]; return $records[$index];
        }
        if ($action === 'receivable' && $method === 'DELETE') {
            if (array_keys($input) !== ['id']) throw new ReceivableRequestException('Invalid delete request.');
            $deleted = $records[$index]; array_splice($records, $index, 1); return $deleted;
        }
        if ($action === 'payment' && $method === 'POST') {
            if (array_key_exists('paymentId', $input) || array_key_exists('payment', $input) || array_key_exists('idOverride', $input)) throw new ReceivableRequestException('Payment IDs are server managed.');
            $data = $input; unset($data['id']); $payment = receivable_payment_data($data);
            if (receivable_payment_cents($records[$index]['payments']) + (int) round($payment['amount'] * 100) > (int) round($records[$index]['invoiceAmount'] * 100)) throw new ReceivableRequestException('Payment amount exceeds the outstanding balance.');
            $payment = ['id' => business_next_record_id($records[$index]['payments'], 'PAY'), ...$payment]; $records[$index]['payments'][] = $payment; return $records[$index];
        }
        $paymentId = $input['paymentId'] ?? null; if (!is_string($paymentId) || preg_match('/^PAY\d{4,}$/D', $paymentId) !== 1) throw new ReceivableRequestException('Invalid payment ID.');
        $paymentIndex = business_find_record_index($records[$index]['payments'], $paymentId); if ($paymentIndex === null) throw new ReceivableRequestException('Payment not found.', 404);
        if ($action === 'payment' && $method === 'PATCH') {
            $data = $input; unset($data['id'], $data['paymentId']); $payment = receivable_payment_data($data);
            $other = $records[$index]['payments']; array_splice($other, $paymentIndex, 1);
            if (receivable_payment_cents($other) + (int) round($payment['amount'] * 100) > (int) round($records[$index]['invoiceAmount'] * 100)) throw new ReceivableRequestException('Total payments cannot exceed the invoice amount.');
            $records[$index]['payments'][$paymentIndex] = ['id' => $paymentId, ...$payment]; return $records[$index];
        }
        if ($action === 'payment' && $method === 'DELETE') {
            if (array_diff(array_keys($input), ['id', 'paymentId']) !== [] || count($input) !== 2) throw new ReceivableRequestException('Invalid delete request.');
            array_splice($records[$index]['payments'], $paymentIndex, 1); return $records[$index];
        }
        throw new ReceivableRequestException('Method not allowed for this action.', 405);
    });
    receivable_response(['success' => true, 'data' => receivable_enrich($result, $customers)], $method === 'POST' ? 201 : 200);
} catch (ReceivableRequestException $exception) {
    receivable_response(['success' => false, 'message' => $exception->getMessage()], $exception->status);
} catch (Throwable) {
    receivable_response(['success' => false, 'message' => $method === 'GET' ? 'Payment Tracking data is temporarily unavailable.' : 'Unable to update Payment Tracking data.'], 500);
}
