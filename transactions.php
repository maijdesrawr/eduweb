<?php

require_once "admin_session.php";

require_once "db.php";

$adminName = trim($_SESSION['admin_name'] ?? 'Super Administrator');

$nameParts = preg_split('/\s+/', $adminName);
$adminInitials = '';

foreach ($nameParts as $part) {
    if ($part !== '') {
        $adminInitials .= strtoupper(substr($part, 0, 1));
    }
}

$adminInitials = substr($adminInitials, 0, 2);

/* =========================
   FILTER VALUES
========================= */

$transactionId = trim($_GET['transaction_id'] ?? '');
$cashierId = trim($_GET['cashier_id'] ?? '');
$canteenId = trim($_GET['canteen_id'] ?? '');
$description = trim($_GET['description'] ?? '');
$date = trim($_GET['date'] ?? '');


/* =========================
   TOTAL TRANSACTIONS
========================= */

$totalTransactionsQuery = "
    SELECT COUNT(*) AS total_transactions
    FROM transactions
";

$totalTransactionsResult = $conn->query($totalTransactionsQuery);
$totalTransactionsData = $totalTransactionsResult->fetch_assoc();

$totalTransactions = $totalTransactionsData['total_transactions'];


/* =========================
   TRANSACTION QUERY
========================= */

$transactionsQuery = "
    SELECT
        transaction_id,
        student_num,
        amount,
        transaction_type,
        description,
        date_time,
        canteen_id,
        cashier_id
    FROM transactions
    WHERE LOWER(transaction_type) = 'payment'
";

$params = [];
$types = "";


/* Transaction ID filter */

if ($transactionId !== '') {

    $transactionsQuery .= "
        AND transaction_id = ?
    ";

    $params[] = $transactionId;
    $types .= "i";
}


/* Cashier ID filter */

if ($cashierId !== '') {

    $transactionsQuery .= "
        AND cashier_id = ?
    ";

    $params[] = $cashierId;
    $types .= "s";
}

if ($canteenId !== '') {

    $transactionsQuery .= "
        AND canteen_id = ?
    ";

    $params[] = $canteenId;
    $types .= "s";
}

/* Description filter */

if ($description !== '') {

    $transactionsQuery .= "
        AND description LIKE ?
    ";

    $params[] = "%" . $description . "%";
    $types .= "s";
}


/* Date filter */

if ($date !== '') {

    $transactionsQuery .= "
        AND DATE(date_time) = ?
    ";

    $params[] = $date;
    $types .= "s";
}


/* Newest transactions first */

$transactionsQuery .= "
    ORDER BY date_time DESC
";


$stmt = $conn->prepare($transactionsQuery);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();

$transactionsResult = $stmt->get_result();

?>


<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Transactions - EduPocket</title>

    <link
        link rel="stylesheet" href="style.css?v=<?php echo time(); ?>"
    >

</head>


<body>


<div class="app">

<aside class="sidebar">

        <div class="brand">

            <div class="logo">
                E
            </div>

            <div>

                <div class="brand-title">
                    EduPocket
                </div>

                <span class="brand-subtitle">
                    Admin Dashboard
                </span>

            </div>

        </div>


        <div class="nav-title">
            User Management
        </div>


        <nav class="nav">

            <button
                onclick="window.location.href='dashboard.php'"
            >
                <span class="nav-icon">⌂</span>
                Dashboard
            </button>


            <button
                onclick="window.location.href='students.php'"
            >
                <span class="nav-icon"></span>
                Students
            </button>


            <button
                onclick="window.location.href='parents.php'"
            >
                <span class="nav-icon"></span>
                Parents / Guardians
            </button>


            <button
                onclick="window.location.href='pos_cashiers.php'"
            >
                <span class="nav-icon"></span>
                POS / Cashiers
            </button>

        </nav>


        <div class="nav-title finance-title">
            Transaction Records
        </div>


        <nav class="nav">

            <button
                onclick="window.location.href='topups.php'"
            >
                <span class="nav-icon">₱</span>
                Top-Ups
            </button>


            <button
            class="active"
                onclick="window.location.href='transactions.php'"
            >
                <span class="nav-icon">▥</span>
                Transactions
            </button>

        </nav>


        <div class="sidebar-footer">

    <div class="admin-logout-menu" id="adminLogoutMenu">
        <a href="logout.php" class="admin-logout-link">
            ↪ Log Out
        </a>
    </div>

    <button
        type="button"
        class="admin-card"
        id="adminProfileButton"
        onclick="toggleAdminMenu(event)"
    >
        <span class="avatar">
            <?php echo htmlspecialchars($adminInitials); ?>
        </span>

        <span class="admin-profile-details">
            <strong>
                <?php echo htmlspecialchars($adminName); ?>
            </strong>

            <small>System Administrator</small>
        </span>

        <span class="admin-profile-chevron">⌃</span>
    </button>

</div>

    </aside>



    <!-- =========================
         MAIN CONTENT
    ========================== -->

    <main style="width:100%;">


        <!-- TOPBAR -->

        <div class="topbar">

            <div>

                <div class="eyebrow">
                    Transaction Records
                </div>

                <h1>
                    Transaction History
                </h1>

            </div>

        </div>



        <!-- =========================
             SUMMARY
        ========================== -->

            <!-- =========================
                 FILTERS
            ========================== -->

            <div
                style="
                    padding: 0px 0px 10px 0px;
                "
            >

                <form
                    method="GET"
                    style="
                        display:flex;
                        gap:10px;
                        align-items:flex-end;
                        flex-wrap:wrap;
                    "
                >


                    <!-- TRANSACTION ID -->

                    <div
                        class="form-group"
                        style="margin:0;"
                    >

                        <label>
                            Transaction ID
                        </label>

                        <input
                            type="number"
                            name="transaction_id"
                            placeholder="Enter Transaction ID"
                            value="<?php
                                echo htmlspecialchars(
                                    $transactionId
                                );
                            ?>"
                            style="width: 180px;"
                        >

                    </div>


                    <!-- CANTEEN ID -->

                    <div
                        class="form-group"
                        style="margin:0;"
                    >

                        <label>
                            Canteen ID
                        </label>

                        <input
                            type="text"
                            name="canteen_id"
                            placeholder="Enter Canteen ID"
                            value="<?php
                                echo htmlspecialchars(
                                    $canteenId
                                );
                            ?>"
                            style="width: 200px;"
                        >

                    </div>


                    <div
                        class="form-group"
                        style="margin:0;"
                    >

                        <label>
                            Cashier ID
                        </label>

                        <input
                            type="text"
                            name="cashier_id"
                            placeholder="Enter Cashier ID"
                            value="<?php
                                echo htmlspecialchars(
                                    $cashierId
                                );
                            ?>"
                            style="width: 200px;"
                        >

                    </div>

                    <!-- DESCRIPTION FILTER -->

<div class="form-group" style="margin:0;">

    <label for="description">
        Description
    </label>

    <input
        type="text"
        id="description"
        name="description"
        placeholder="Enter Description"
        value="<?php echo htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?>"
        style="width: 200px;"
    >

</div>



                    <!-- DATE -->

                    <div
                        class="form-group"
                        style="margin:0;"
                    >

                        <label>
                            Date
                        </label>

                        <input
                            type="date"
                            name="date"
                            value="<?php
                                echo htmlspecialchars(
                                    $date
                                );
                            ?>"
                        >

                    </div>



                    <!-- FILTER BUTTON -->

                    <button
                        type="submit"
                        class="add-student-btn"
                    >
                        Filter
                    </button>



                    <!-- CLEAR BUTTON -->

                    <a
                        href="transactions.php"
                        class="cancel-btn"
                        style="
                            text-decoration:none;
                            display:flex;
                            align-items:center;
                            justify-content:center;
                            height:48px;
                            box-sizing:border-box;
                        "
                    >
                        Clear
                    </a>


                </form>

            </div>



        <!-- =========================
             TRANSACTION CARD
        ========================== -->

        <section class="card table-card">

            <!-- =========================
                 TABLE
            ========================== -->

            <div class="table-wrapper">

                <table id="usersTable">

                    <thead>

                        <tr>

                            <th>
                                Transaction ID
                            </th>

                            <th>
                                Student ID
                            </th>

                            <th>
                                Cashier ID
                            </th>

                            <th>
                                Canteen ID
                            </th>

                            <th>
                                Type
                            </th>

                            <th>
                                Description
                            </th>

                            <th>
                                Amount
                            </th>

                            <th>
                                Date & Time
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php if ($transactionsResult->num_rows > 0): ?>


                        <?php while (
                            $transaction =
                                $transactionsResult->fetch_assoc()
                        ): ?>

                        <?php
                                $type = strtolower(trim($transaction['transaction_type'] ?? ''));

                                if ($type === 'top-up') {
                                    $amountSign = '+';
                                    $amountColor = '#16834a';
                                    } elseif ($type === 'payment') {
                                        $amountSign = '-';
                                        $amountColor = '#cf302e';
                                        } else {
                                            $amountSign = '';
                                            $amountColor = '#333';
                                            }
                            ?>


                            <tr>


                                <!-- TRANSACTION ID -->

                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $transaction['transaction_id']
                                    );
                                    ?>

                                </td>



                                <!-- STUDENT ID -->

                                 <td>
                                    <?php
                                    $studentID = $transaction['student_num'] ?? '';
                                    if (strlen($studentID) > 4) {
                                        $maskedStudentID = str_repeat('*', strlen($studentID) - 4). substr($studentID, -4);
                                        } else {
                                            $maskedStudentID = str_repeat('*', strlen($studentID));
                                            }
                                            echo htmlspecialchars($maskedStudentID);
                                            ?>
                                            
                                </td>



                                <!-- CASHIER ID -->

                                <td>

                                    <?php

                                    echo htmlspecialchars(
                                        $transaction['cashier_id']
                                            ?? 'N/A'
                                    );

                                    ?>

                                </td>

                                <td>

                                    <?php

                                    echo htmlspecialchars(
                                        $transaction['canteen_id']
                                            ?? 'N/A'
                                    );

                                    ?>

                                </td>



                                <!-- TYPE -->

                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $transaction['transaction_type']
                                    );
                                    ?>

                                </td>



                                <!-- DESCRIPTION -->

                                <td>

                                    <?php

                                    echo htmlspecialchars(
                                        $transaction['description']
                                            ?? 'N/A'
                                    );

                                    ?>

                                </td>



                                <!-- AMOUNT -->

                                <td>

                                    PHP
                                    <?php

                                    echo number_format(
                                        (float)$transaction['amount'],
                                        2
                                    );

                                    ?>

                                </td>



                                <!-- DATE -->

                                <td>

                                    <?php

                                    echo htmlspecialchars(
                                        $transaction['date_time']
                                    );

                                    ?>

                                </td>


                            </tr>


                        <?php endwhile; ?>


                    <?php else: ?>


                        <tr>

                            <td
                                colspan="7"
                                style="
                                    text-align:center;
                                    padding:30px;
                                    color:#777;
                                "
                            >

                                No transactions found.

                            </td>

                        </tr>


                    <?php endif; ?>


                    </tbody>

                </table>

            </div>


        </section>


    </main>


</div>

<script>
    function toggleAdminMenu(event) {
    event.stopPropagation();

    const menu = document.getElementById("adminLogoutMenu");
    const profile = document.getElementById("adminProfileButton");

    menu.classList.toggle("show");

    const isOpen = menu.classList.contains("show");
    profile.setAttribute("aria-expanded", isOpen);
}

// Close dropdown when clicking outside
document.addEventListener("click", function(event) {

    const menu = document.getElementById("adminLogoutMenu");
    const profile = document.getElementById("adminProfileButton");

    if (!menu.contains(event.target) &&
        !profile.contains(event.target)) {

        menu.classList.remove("show");
        profile.setAttribute("aria-expanded", "false");
    }

});
</script>

</body>

</html>