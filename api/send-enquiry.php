<?php

declare(strict_types=1);


/**
 * ============================================================
 * Laxmikant Traders
 * ------------------------------------------------------------
 * File      : send-enquiry.php
 * Purpose   : Secure Product Enquiry API
 * Version   : 1.3.0
 * ============================================================
 */


use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/includes/paths.php';


/**
 * ------------------------------------------------------------
 * Response Headers
 * ------------------------------------------------------------
 */

header(
    "Content-Type: application/json; charset=UTF-8"
);

$origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
if ($origin !== '' && app_origin_is_allowed($origin)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
}

header(
    "Access-Control-Allow-Methods: POST, OPTIONS"
);

header(
    "Access-Control-Allow-Headers: Content-Type"
);


/**
 * ------------------------------------------------------------
 * Handle CORS Preflight
 * ------------------------------------------------------------
 */

if (
    $_SERVER["REQUEST_METHOD"] === "OPTIONS"
) {

    if ($origin !== '' && !app_origin_is_allowed($origin)) {
        http_response_code(403);
        exit;
    }

    http_response_code(204);

    exit;

}


/**
 * ------------------------------------------------------------
 * Only POST Requests
 * ------------------------------------------------------------
 */

if (
    $_SERVER["REQUEST_METHOD"] !== "POST"
) {

    respond(
        false,
        "Invalid request method.",
        405
    );

}


/**
 * ------------------------------------------------------------
 * Request Size Protection
 * ------------------------------------------------------------
 */

$contentLength =
    isset($_SERVER["CONTENT_LENGTH"])
        ? (int)$_SERVER["CONTENT_LENGTH"]
        : 0;


if ($contentLength > 10240) {

    respond(
        false,
        "Request is too large.",
        413
    );

}


/**
 * ------------------------------------------------------------
 * Load PHPMailer
 * ------------------------------------------------------------
 */

require_once
    __DIR__ .
    "/../vendor/autoload.php";


/**
 * ------------------------------------------------------------
 * Load SMTP Configuration
 * ------------------------------------------------------------
 */

$config =
    require __DIR__ .
    "/config.php";


/**
 * ------------------------------------------------------------
 * Read JSON Request
 * ------------------------------------------------------------
 */

$rawInput =
    file_get_contents(
        "php://input"
    );


if (
    $rawInput === false ||
    $rawInput === ""
) {

    respond(
        false,
        "Invalid request data.",
        400
    );

}


$data =
    json_decode(
        $rawInput,
        true
    );


if (
    !is_array($data) ||
    json_last_error() !== JSON_ERROR_NONE
) {

    respond(
        false,
        "Invalid request data.",
        400
    );

}


/**
 * ------------------------------------------------------------
 * Honeypot Protection
 * ------------------------------------------------------------
 */

$website =
    trim(
        (string)(
            $data["website"] ?? ""
        )
    );


if ($website !== "") {

    /*
     * Do not tell bots that the honeypot was triggered.
     */

    respond(
        true,
        "Your enquiry has been received."
    );

}


/**
 * ------------------------------------------------------------
 * Helper: Read String
 * ------------------------------------------------------------
 */

function readString(
    array $data,
    string $key
): string {

    return trim(
        (string)(
            $data[$key] ?? ""
        )
    );

}


/**
 * ------------------------------------------------------------
 * Read Form Fields
 * ------------------------------------------------------------
 */

$product =
    readString(
        $data,
        "product"
    );


$productId =
    readString(
        $data,
        "productId"
    );


$name =
    readString(
        $data,
        "name"
    );


$company =
    readString(
        $data,
        "company"
    );


$phone =
    readString(
        $data,
        "phone"
    );


$email =
    readString(
        $data,
        "email"
    );


$quantity =
    readString(
        $data,
        "quantity"
    );


$message =
    readString(
        $data,
        "message"
    );


/**
 * ------------------------------------------------------------
 * Required Field Validation
 * ------------------------------------------------------------
 *
 * Required:
 *
 * Product
 * Name
 * Company
 * Phone
 * Email
 * Quantity
 *
 * Optional:
 *
 * Message
 *
 * ------------------------------------------------------------
 */

if ($product === "") {

    respond(
        false,
        "Please select or enter a product.",
        422
    );

}


if ($name === "") {

    respond(
        false,
        "Please enter your name.",
        422
    );

}


if ($company === "") {

    respond(
        false,
        "Please enter your company name.",
        422
    );

}


if ($phone === "") {

    respond(
        false,
        "Please enter your phone number.",
        422
    );

}


if ($email === "") {

    respond(
        false,
        "Please enter your email address.",
        422
    );

}


if ($quantity === "") {

    respond(
        false,
        "Please enter the required quantity.",
        422
    );

}


/**
 * ------------------------------------------------------------
 * Length Validation
 * ------------------------------------------------------------
 */

if (
    mb_strlen($product) > 200
) {

    respond(
        false,
        "Product name is too long.",
        422
    );

}


if (
    mb_strlen($productId) > 100
) {

    respond(
        false,
        "Invalid product information.",
        422
    );

}


if (
    mb_strlen($name) > 100
) {

    respond(
        false,
        "Name is too long.",
        422
    );

}


if (
    mb_strlen($company) > 150
) {

    respond(
        false,
        "Company name is too long.",
        422
    );

}


if (
    mb_strlen($phone) < 7 ||
    mb_strlen($phone) > 20
) {

    respond(
        false,
        "Please enter a valid phone number.",
        422
    );

}


if (
    mb_strlen($email) > 254
) {

    respond(
        false,
        "Email address is too long.",
        422
    );

}


if (
    mb_strlen($message) > 2000
) {

    respond(
        false,
        "Message is too long.",
        422
    );

}


/**
 * ------------------------------------------------------------
 * Phone Validation
 * ------------------------------------------------------------
 */

if (
    !preg_match(
        '/^[0-9+()\-\s]+$/',
        $phone
    )
) {

    respond(
        false,
        "Please enter a valid phone number.",
        422
    );

}


/**
 * ------------------------------------------------------------
 * Email Validation
 * ------------------------------------------------------------
 */

if (
    !filter_var(
        $email,
        FILTER_VALIDATE_EMAIL
    )
) {

    respond(
        false,
        "Please enter a valid email address.",
        422
    );

}


/**
 * ------------------------------------------------------------
 * Quantity Validation
 * ------------------------------------------------------------
 */

if (
    !ctype_digit($quantity) ||
    (int)$quantity < 1 ||
    (int)$quantity > 1000000
) {

    respond(
        false,
        "Please enter a valid quantity.",
        422
    );

}


/**
 * ------------------------------------------------------------
 * Header Injection Protection
 * ------------------------------------------------------------
 */

$userFields = [

    $product,
    $name,
    $company,
    $phone,
    $email

];


foreach (
    $userFields as $field
) {

    if (
        str_contains($field, "\r") ||
        str_contains($field, "\n")
    ) {

        respond(
            false,
            "Invalid form data.",
            422
        );

    }

}


/**
 * ------------------------------------------------------------
 * Rate Limiting
 * ------------------------------------------------------------
 *
 * One enquiry every 30 seconds per IP address.
 *
 * ------------------------------------------------------------
 */

$rateLimitDirectory =
    app_enquiry_rate_limit_directory();


if (
    !is_dir($rateLimitDirectory)
) {

    @mkdir(
        $rateLimitDirectory,
        0700,
        true
    );

}


$clientIp =
    $_SERVER["REMOTE_ADDR"] ??
    "unknown";


$rateLimitKey =
    hash(
        "sha256",
        $clientIp
    );


$rateLimitFile =
    $rateLimitDirectory .
    "/" .
    $rateLimitKey .
    ".txt";


$currentTime =
    time();


if (
    is_file($rateLimitFile)
) {

    $lastRequest =
        (int)@file_get_contents(
            $rateLimitFile
        );


    if (
        $lastRequest > 0 &&
        ($currentTime - $lastRequest) < 30
    ) {

        respond(
            false,
            "Please wait a few seconds before sending another enquiry.",
            429
        );

    }

}


@file_put_contents(
    $rateLimitFile,
    (string)$currentTime,
    LOCK_EX
);


/**
 * ------------------------------------------------------------
 * Send Email
 * ------------------------------------------------------------
 */

try {

    $mail =
        new PHPMailer(true);


    /**
     * SMTP
     */

    $mail->isSMTP();


    $mail->Host =
        $config["smtp_host"];


    $mail->SMTPAuth =
        true;


    $mail->Username =
        $config["smtp_username"];


    $mail->Password =
        $config["smtp_password"];


    $mail->SMTPSecure =
        ($config['smtp_encryption'] ?? 'tls') === 'ssl'
            ? PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer::ENCRYPTION_STARTTLS;


    $mail->Port =
        (int)$config["smtp_port"];


    /**
     * Character Set
     */

    $mail->CharSet =
        "UTF-8";


    /**
     * Sender
     */

    $mail->setFrom(
        $config["from_email"],
        $config["from_name"]
    );


    /**
     * Recipient
     */

    $mail->addAddress(
        $config["to_email"],
        $config["to_name"]
    );


    /**
     * Reply-To
     */

    $mail->addReplyTo(
        $email,
        $name
    );


    /**
     * Subject
     */

    $mail->Subject =
        "New Product Enquiry - Laxmikant Traders";


    /**
     * Plain Text Email
     */

    $mail->isHTML(false);


    $emailBody = <<<TEXT
New Product Enquiry
===================

Product:
{$product}

Customer Name:
{$name}

Company Name:
{$company}

Phone Number:
{$phone}

Email Address:
{$email}

Required Quantity:
{$quantity}

Message:
{$message}

-------------------

This enquiry was submitted through
the Laxmikant Traders website.
TEXT;


    $mail->Body =
        $emailBody;


    /**
     * Send
     */

    $mail->send();


    respond(
        true,
        "Your enquiry has been sent successfully."
    );


} catch (Exception $e) {

    /**
     * --------------------------------------------------------
     * Do not expose SMTP details to the visitor.
     * --------------------------------------------------------
     */

    error_log(
        "Laxmikant Traders PHPMailer Error: " .
        $e->getMessage()
    );


    respond(
        false,
        "Unable to send your enquiry at this time. Please try again later.",
        500
    );

}


/**
 * ------------------------------------------------------------
 * JSON Response
 * ------------------------------------------------------------
 */

function respond(
    bool $success,
    string $message,
    int $statusCode = 200
): never {

    http_response_code(
        $statusCode
    );


    echo json_encode(
        [
            "success" =>
                $success,

            "message" =>
                $message
        ],
        JSON_UNESCAPED_UNICODE
    );


    exit;

}
