<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "db.php";

header("Content-Type: application/json");


if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    echo json_encode([
        "success" => false,
        "message" => "Invalid request method."
    ]);

    exit;

}

$category = trim(
    $_POST["category"] ?? ""
);

if ($category === "pos") {

    $canteenID = trim(
        $_POST["canteen_id"] ?? ""
    );

    $name = trim(
        $_POST["name"] ?? ""
    );

    $location = trim(
        $_POST["location"] ?? ""
    );

    $password = $_POST["password"] ?? "";

    if (
        $canteenID === "" ||
        $name === "" ||
        $password === ""
    ) {

        echo json_encode([
            "success" => false,
            "message" => "Please fill in all required POS fields."
        ]);

        exit;

    }

    $checkCanteen = $conn->prepare("
        SELECT canteen_id
        FROM canteens
        WHERE canteen_id = ?
        LIMIT 1
    ");

    if (!$checkCanteen) {

        echo json_encode([
            "success" => false,
            "message" => "Database error: " . $conn->error
        ]);

        exit;

    }

    $checkCanteen->bind_param(
        "s",
        $canteenID
    );

    $checkCanteen->execute();

    $checkCanteen->store_result();

    if ($checkCanteen->num_rows > 0) {

        $checkCanteen->close();

        echo json_encode([
            "success" => false,
            "message" => "That Canteen ID already exists."
        ]);

        exit;

    }

    $checkCanteen->close();

    $hashedPassword = password_hash(
        $password,
        PASSWORD_DEFAULT
    );

    $insertCanteen = $conn->prepare("
        INSERT INTO canteens
        (
            canteen_id,
            Fname,
            Password,
            location,
            balance
        )
        VALUES
        (?, ?, ?, ?, 0.00)
    ");

    if (!$insertCanteen) {

        echo json_encode([
            "success" => false,
            "message" => "Database error: " . $conn->error
        ]);

        exit;

    }

    $insertCanteen->bind_param(
        "ssss",
        $canteenID,
        $name,
        $hashedPassword,
        $location
    );

    if ($insertCanteen->execute()) {

        $insertCanteen->close();

        echo json_encode([
            "success" => true,
            "message" => "POS / Canteen created successfully.",
            "account_type" => "pos",
            "account_id" => $canteenID,
            "account_name" => $name,
            "location" => $location
        ]);

        exit;

    }

    $error = $insertCanteen->error;

    $insertCanteen->close();

    echo json_encode([
        "success" => false,
        "message" => "Failed to create POS / Canteen: " . $error
    ]);

    exit;

}

if ($category === "cashier") {

    $cashierID = trim($_POST["cashier_id"] ?? "");
    $name = trim($_POST["name"] ?? "");
    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";
    $pin = $_POST["pin"] ?? "";

    // Required fields (Canteen ID is NOT required)
    if (
        $cashierID === "" ||
        $name === "" ||
        $username === "" ||
        $password === ""
    ) {
        echo json_encode([
            "success" => false,
            "message" => "Please fill in all required cashier fields."
        ]);
        exit;
    }

    // Check duplicate Cashier ID
    $checkCashier = $conn->prepare("
        SELECT cashier_id
        FROM cashiers
        WHERE cashier_id = ?
        LIMIT 1
    ");

    if (!$checkCashier) {
        echo json_encode([
            "success" => false,
            "message" => "Database error: " . $conn->error
        ]);
        exit;
    }

    $checkCashier->bind_param("s", $cashierID);
    $checkCashier->execute();
    $checkCashier->store_result();

    if ($checkCashier->num_rows > 0) {
        $checkCashier->close();

        echo json_encode([
            "success" => false,
            "message" => "That Cashier ID already exists."
        ]);
        exit;
    }

    $checkCashier->close();

    // Check duplicate username
    $checkUsername = $conn->prepare("
        SELECT cashier_id
        FROM cashiers
        WHERE username = ?
        LIMIT 1
    ");

    if (!$checkUsername) {
        echo json_encode([
            "success" => false,
            "message" => "Database error: " . $conn->error
        ]);
        exit;
    }

    $checkUsername->bind_param("s", $username);
    $checkUsername->execute();
    $checkUsername->store_result();

    if ($checkUsername->num_rows > 0) {
        $checkUsername->close();

        echo json_encode([
            "success" => false,
            "message" => "That username is already being used."
        ]);
        exit;
    }

    $checkUsername->close();

    // Hash password
    $hashedPassword = password_hash(
        $password,
        PASSWORD_DEFAULT
    );

    // Hash PIN if provided
    $hashedPin = null;

    if ($pin !== "") {
        $hashedPin = password_hash(
            $pin,
            PASSWORD_DEFAULT
        );
    }

    // Create cashier without requiring a canteen ID
    $insertCashier = $conn->prepare("
        INSERT INTO cashiers
        (
            cashier_id,
            Fname,
            username,
            password,
            pin,
            canteen_id,
            balance
        )
        VALUES (?, ?, ?, ?, ?, NULL, 0.00)
    ");

    if (!$insertCashier) {
        echo json_encode([
            "success" => false,
            "message" => "Database error: " . $conn->error
        ]);
        exit;
    }

    $insertCashier->bind_param(
        "sssss",
        $cashierID,
        $name,
        $username,
        $hashedPassword,
        $hashedPin
    );

    if ($insertCashier->execute()) {

        $insertCashier->close();

        echo json_encode([
            "success" => true,
            "message" => "Cashier account created successfully.",
            "account_type" => "cashier",
            "account_id" => $cashierID,
            "account_name" => $name,
            "username" => $username
        ]);

        exit;
    }

    $error = $insertCashier->error;
    $insertCashier->close();

    echo json_encode([
        "success" => false,
        "message" => "Failed to create cashier account: " . $error
    ]);

    exit;
}