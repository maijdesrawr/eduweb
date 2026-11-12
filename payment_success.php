
<?php

ini_set('display_errors', '0');
error_reporting(0);

http_response_code(503);
require_once __DIR__ . '/db.php';
http_response_code(200);
require_once __DIR__ . '/paymongo_payment_common.php';

$result = null;
$errorMessage = null;

try {
    $checkoutId = pm_checkout_id_for_callback(
        $conn,
        (string)($_GET['reference'] ?? '')
    );

    $result = pm_verify_and_fulfill($conn, $checkoutId);

} catch (Throwable $error) {
    error_log('payment_success.php failed: ' . $error->getMessage());
    http_response_code(409);
    $errorMessage = 'PayMongo has not confirmed this payment. Please check your transaction history before trying again.';

} finally {
    if ($conn instanceof mysqli) {
        $conn->close();
    }
}

$isSuccess = is_array($result);
$amount = $isSuccess ? (string)$result['amount'] : '0.00';
$transactionId = $isSuccess ? (int)$result['transaction_id'] : 0;

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $isSuccess ? 'Payment confirmed' : 'Payment not confirmed' ?></title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            background: #f7f7f8;
            color: #242424;
            font-family: Arial, sans-serif;
        }
        main {
            width: min(100%, 440px);
            padding: 36px 28px;
            border-radius: 24px;
            background: #fff;
            text-align: center;
            box-shadow: 0 18px 60px rgba(0, 0, 0, .10);
        }
        .mark {
            width: 72px;
            height: 72px;
            margin: 0 auto 20px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            background: <?= $isSuccess ? '#e8f7ed' : '#fdebec' ?>;
            color: <?= $isSuccess ? '#18843d' : '#c62828' ?>;
            font-size: 36px;
            font-weight: 800;
        }
        h1 { margin: 0 0 14px; font-size: 28px; }
        .amount {
            margin: 12px 0 4px;
            color: #18843d;
            font-size: 32px;
            font-weight: 800;
        }
        p { margin: 8px 0; color: #5f6368; line-height: 1.5; }
        a {
            display: inline-block;
            margin-top: 24px;
            padding: 13px 22px;
            border-radius: 12px;
            background: #c62828;
            color: #fff;
            font-weight: 700;
            text-decoration: none;
        }
    </style>
</head>
<body>
<main>
    <div class="mark" aria-hidden="true"><?= $isSuccess ? '&#10003;' : '!' ?></div>

    <?php if ($isSuccess): ?>
        <h1>Payment confirmed</h1>
        <div class="amount">&#8369;<?= htmlspecialchars($amount, ENT_QUOTES, 'UTF-8') ?></div>
        <p>Your wallet top-up has been successfully recorded in EduPocket.</p>
        <p>Transaction #<?= $transactionId ?></p>
        <a href="parent_topup.php">Return to Parent Top-Up</a>
    <?php else: ?>
        <h1>Payment not confirmed</h1>
        <p><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></p>
        <p>Please check your transaction history before attempting another payment.</p>
        <a href="parent_topup.php">Return to Parent Top-Up</a>
    <?php endif; ?>
</main>
</body>
</html>
