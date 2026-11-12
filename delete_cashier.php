<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "db.php";


/* =========================================
   GET CASHIER ID
========================================= */

$cashierID = trim(
    $_GET["cashier_id"] ?? ""
);

if ($cashierID === "") {

    die("Cashier ID is missing.");

}


/* =========================================
   CHECK CASHIER EXISTS
========================================= */

$stmt = $conn->prepare("
    SELECT cashier_id
    FROM cashiers
    WHERE cashier_id = ?
");

if (!$stmt) {

    die(
        "Database error: " .
        $conn->error
    );

}

$stmt->bind_param(
    "s",
    $cashierID
);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {

    $stmt->close();

    die("Cashier account not found.");

}

$stmt->close();


/* =========================================
   DELETE CASHIER
========================================= */

$stmt = $conn->prepare("
    DELETE FROM cashiers
    WHERE cashier_id = ?
");

if (!$stmt) {

    die(
        "Database error: " .
        $conn->error
    );

}

$stmt->bind_param(
    "s",
    $cashierID
);


if (!$stmt->execute()) {

    die(
        "Unable to delete cashier: " .
        $stmt->error
    );

}

$stmt->close();

$conn->close();


/* =========================================
   RETURN TO POS PAGE
========================================= */

header(
    "Location: pos_cashiers.php?deleted=1"
);

exit;

?>