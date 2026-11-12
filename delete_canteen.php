<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "db.php";


$canteenID = trim(
    $_GET["canteen_id"] ?? ""
);


if ($canteenID === "") {
    die("Canteen ID is missing.");
}


/* =========================================================
   CHECK CANTEEN
========================================================= */

$stmt = $conn->prepare("
    SELECT canteen_id
    FROM canteens
    WHERE canteen_id = ?
");

if (!$stmt) {
    die("Database error: " . $conn->error);
}

$stmt->bind_param(
    "s",
    $canteenID
);

$stmt->execute();

$result =
    $stmt->get_result();


if ($result->num_rows === 0) {

    $stmt->close();

    die("Canteen not found.");

}

$stmt->close();


/* =========================================================
   DELETE CANTEEN
========================================================= */

$stmt = $conn->prepare("
    DELETE FROM canteens
    WHERE canteen_id = ?
");

if (!$stmt) {
    die("Database error: " . $conn->error);
}

$stmt->bind_param(
    "s",
    $canteenID
);


if (!$stmt->execute()) {

    die(
        "Unable to delete canteen: "
        . $stmt->error
    );

}


$stmt->close();

$conn->close();


header(
    "Location: pos_cashiers.php?deleted_canteen=1"
);

exit;

?>