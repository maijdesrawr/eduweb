<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

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


/* =========================================
   GET CASHIER ID
========================================= */

$cashierID = trim(
    $_GET["cashier_id"] ?? ""
);


if (empty($cashierID)) {
    die("Cashier ID is required.");
}


/* =========================================
   GET FILTERS
========================================= */
$description = trim($_GET['description'] ?? '');
$date = trim(
    $_GET["date"] ?? ""
);


/* =========================================
   GET CASHIER
========================================= */

$stmt = $conn->prepare("
    SELECT
        cashier_id,
        Fname,
        username,
        password,
        pin,
        canteen_id,
        balance,
        profile_photo
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

$cashier = $result->fetch_assoc();


if (!$cashier) {
    die("Cashier not found.");
}

$stmt->close();


/* =========================================
   GET CANTEEN NAME
========================================= */

$canteenName = "None";

if (!empty($cashier["canteen_id"])) {

    $canteenStmt = $conn->prepare("
        SELECT Fname
        FROM canteens
        WHERE canteen_id = ?
    ");

    if ($canteenStmt) {

        $canteenStmt->bind_param(
            "s",
            $cashier["canteen_id"]
        );

        $canteenStmt->execute();

        $canteenResult =
            $canteenStmt->get_result();

        $canteenData =
            $canteenResult->fetch_assoc();

        if ($canteenData) {

            $canteenName =
                $canteenData["Fname"];

        }

        $canteenStmt->close();

    }

}


/* =========================================
   INITIALS
========================================= */

$name = $cashier["Fname"];

$words = explode(
    " ",
    trim($name)
);

$initials = "";

foreach ($words as $word) {

    if (!empty($word)) {

        $initials .= strtoupper(
            substr(
                $word,
                0,
                1
            )
        );

    }

}

$initials = substr(
    $initials,
    0,
    2
);


/* =========================================
   TRANSACTION HISTORY

   ONLY TRANSACTIONS DONE BY THIS CASHIER
========================================= */

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
    WHERE cashier_id = ?
";

$params = [
    $cashierID
];

$types = "s";

/* Description filter */

if ($description !== '') {

    $transactionsQuery .= "
        AND description LIKE ?
    ";

    $params[] = "%" . $description . "%";
    $types .= "s";
}

/* =========================================
   DATE FILTER
========================================= */

if ($date !== "") {

    $transactionsQuery .= "
        AND DATE(date_time) = ?
    ";

    $params[] = $date;

    $types .= "s";
}


/* =========================================
   ORDER
========================================= */

$transactionsQuery .= "
    ORDER BY date_time DESC
";


$stmt = $conn->prepare(
    $transactionsQuery
);

if (!$stmt) {

    die(
        "Database error: " .
        $conn->error
    );

}


/* =========================================
   BIND PARAMETERS
========================================= */

$stmt->bind_param(
    $types,
    ...$params
);


$stmt->execute();

$transactionsResult =
    $stmt->get_result();

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        View Cashier
    </title>

    <link
        rel="stylesheet"
        href="style.css?v=<?php echo time(); ?>"
    >

</head>


<body>


<div class="app">


    <!-- =========================================
         SIDEBAR
    ========================================== -->

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

                <span class="nav-icon">
                    ⌂
                </span>

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
                class="active"
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

                <span class="nav-icon">
                    ₱
                </span>

                Top-Ups

            </button>


            <button
                onclick="window.location.href='transactions.php'"
            >

                <span class="nav-icon">
                    ▥
                </span>

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



    <!-- =========================================
         MAIN
    ========================================== -->

    <main style="width:100%;">


        <!-- =========================================
             TOPBAR
        ========================================== -->

        <div class="topbar">


            <div>

                <div class="eyebrow">
                    POS / Cashier Account
                </div>

                <h1>
                    Cashier Details
                </h1>

            </div>


        </div>



        <!-- =========================================
             CASHIER INFORMATION
        ========================================== -->

        <section class="card">


            <div style="padding:25px;">


                <div class="user-cell">


                    <div class="avatar">

                        <?php

                        echo htmlspecialchars(
                            $initials
                        );

                        ?>

                    </div>


                    <h2>

                        <?php

                        echo htmlspecialchars(
                            $cashier["Fname"]
                        );

                        ?>

                    </h2>


                </div>



                <p>

                    <strong>
                        Cashier ID:
                    </strong>

                    <?php

                    echo htmlspecialchars(
                        $cashier["cashier_id"]
                    );

                    ?>

                </p>



                <p>

                    <strong>
                        Cashier Name:
                    </strong>

                    <?php

                    echo htmlspecialchars(
                        $cashier["Fname"]
                    );

                    ?>

                </p>



                <p>

                    <strong>
                        Username:
                    </strong>

                    <?php

                    echo htmlspecialchars(
                        $cashier["username"]
                    );

                    ?>

                </p>



                <p>

                    <strong>
                        Canteen ID:
                    </strong>

                    <?php

                    echo htmlspecialchars(
                        $cashier["canteen_id"]
                            ?? "None"
                    );

                    ?>

                </p>



                <p>

                    <strong>
                        Assigned Canteen:
                    </strong>

                    <?php

                    echo htmlspecialchars(
                        $canteenName
                    );

                    ?>

                </p>



                <p>

                    <strong>
                        Balance:
                    </strong>

                    ₱<?php

                    echo number_format(
                        (float)$cashier["balance"],
                        2
                    );

                    ?>

                </p>



                <p>

                    <strong>
                        PIN:
                    </strong>

                    <?php

                    if (!empty($cashier["pin"])) {

                        echo "Set";

                    } else {

                        echo "Not Set";

                    }

                    ?>

                </p>



                <p>

                    <strong>
                        Account Status:
                    </strong>

                    <span style="color:green;">
                        Active
                    </span>

                </p>


            </div>


        </section>



        <!-- =========================================
             FILTERS
        ========================================== -->

        <div
            style="
                padding:20px 0px 10px 0px;
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


                <!-- CASHIER ID -->

                <input
                    type="hidden"
                    name="cashier_id"
                    value="<?php

                        echo htmlspecialchars(
                            $cashierID
                        );

                    ?>"
                >

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



                <!-- FILTER -->

                <button
                    type="submit"
                    class="add-student-btn"
                >
                    Filter
                </button>



                <!-- CLEAR -->

                <a
                    href="view_cashier.php?cashier_id=<?php

                        echo urlencode(
                            $cashierID
                        );

                    ?>"
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



        <!-- =========================================
             TRANSACTION HISTORY
        ========================================== -->

        <section
            class="card table-card"
            style="margin-top:20px;"
        >


            <div class="card-header">


                <div>

                    <h2>
                        Transaction History
                    </h2>

                    <p>
                        Transactions processed by this cashier.
                    </p>

                </div>


            </div>



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


                    <?php

                    if (
                        $transactionsResult->num_rows > 0
                    ):

                    ?>


                        <?php

                        while (
                            $transaction =
                                $transactionsResult->fetch_assoc()
                        ):

                        ?>
                        <?php
                                $type = strtolower(trim($transaction['transaction_type'] ?? ''));

                                if ($type === 'top-up') {
                                    $amountSign = '+';
                                    $amountColor = '#cf302e';
                                    } elseif ($type === 'payment') {
                                        $amountSign = '-';
                                        $amountColor = '#16834a';
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
                                        $transaction[
                                            "transaction_id"
                                        ]
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


                                <!-- TYPE -->

                                <td>

                                    <?php

                                    echo htmlspecialchars(
                                        $transaction[
                                            "transaction_type"
                                        ]
                                    );

                                    ?>

                                </td>



                                <!-- DESCRIPTION -->

                                <td>

                                    <?php

                                    echo htmlspecialchars(
                                        $transaction[
                                            "description"
                                        ]
                                            ?? "N/A"
                                    );

                                    ?>

                                </td>



                                <!-- AMOUNT -->

                                <td>

                                    ₱<?php

                                    echo number_format(
                                        (float)$transaction[
                                            "amount"
                                        ],
                                        2
                                    );

                                    ?>

                                </td>



                                <!-- DATE -->

                                <td>

                                    <?php

                                    echo htmlspecialchars(
                                        $transaction[
                                            "date_time"
                                        ]
                                    );

                                    ?>

                                </td>


                            </tr>


                        <?php

                        endwhile;

                        ?>


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

                                No transactions found for this cashier.

                            </td>

                        </tr>


                    <?php endif; ?>


                    </tbody>


                </table>


            </div>


        </section>



        <!-- =========================================
             BACK BUTTON
        ========================================== -->

        <div style="margin-top:20px;">

            <button
                class="save-btn"
                onclick="window.location.href='pos_cashiers.php'"
            >

                ← Back to POS / Cashiers

            </button>

        </div>


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