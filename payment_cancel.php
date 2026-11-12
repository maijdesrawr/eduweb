
<?php

ini_set('display_errors', '0');
error_reporting(0);

require_once __DIR__ . '/backend_audit_common.php';
ba_register_request_audit();

$topupUrl = 'parent_topup.php';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment Cancelled</title>

    <style>
        :root {
            color-scheme: light;
        }

        * {
            box-sizing: border-box;
        }

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
            background: #fdebec;
            color: #c62828;
            font-size: 36px;
            font-weight: 800;
        }

        h1 {
            margin: 0 0 14px;
            font-size: 28px;
        }

        p {
            margin: 8px 0;
            color: #5f6368;
            line-height: 1.5;
        }

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
        <div class="mark" aria-hidden="true">!</div>

        <h1>Payment Cancelled</h1>

        <p>Your payment was not completed.</p>

<p>No wallet balance was changed. You may return to the top-up page and try again.</p>

        <a href="<?= htmlspecialchars($topupUrl, ENT_QUOTES, 'UTF-8') ?>">
            Back to Top Up
        </a>
    </main>
</body>
</html>
