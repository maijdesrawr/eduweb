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

$topupId = trim($_GET['topup_id'] ?? '');
$cashierId = trim($_GET['cashier_id'] ?? '');
$date = trim($_GET['date'] ?? '');

$totalTopupsQuery = "
    SELECT COUNT(*) AS total_topups
    FROM topups
";

$totalTopupsResult = $conn->query($totalTopupsQuery);
$totalTopupsData = $totalTopupsResult->fetch_assoc();

$totalTopups = $totalTopupsData['total_topups'];

$topupsQuery = "
    SELECT
        t.topup_id,
        t.transaction_id,
        t.parent_id,
        tr.cashier_id,
        t.student_num,
        t.amount,
        t.method,
        t.status,
        t.date_time
    FROM topups t
    LEFT JOIN transactions tr
        ON t.transaction_id = tr.transaction_id
    WHERE LOWER(transaction_type) = 'top-up'
";

$params = [];
$types = "";

if ($topupId !== '') {
    $topupsQuery .= " AND t.topup_id = ?";
    $params[] = $topupId;
    $types .= "i";
}

if ($cashierId !== '') {
    $topupsQuery .= " AND tr.cashier_id = ?";
    $params[] = $cashierId;
    $types .= "s";
}

if ($date !== '') {
    $topupsQuery .= " AND DATE(t.date_time) = ?";
    $params[] = $date;
    $types .= "s";
}


$topupsQuery .= " ORDER BY t.date_time DESC";

$stmt = $conn->prepare($topupsQuery);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$topupsResult = $stmt->get_result();

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Top-Ups - EduPocket</title>

    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">

</head>


<body>


<div class="app">


    <!-- =========================
         SIDEBAR
    ========================== -->

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
                class="active"
                onclick="window.location.href='topups.php'"
            >
                <span class="nav-icon">₱</span>
                Top-Ups
            </button>


            <button
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

    <main style="width:100%;">


        <div class="topbar">

            <div>

                <div class="eyebrow">
                    Transaction Records
                </div>

                <h1>
                    Top-Up History
                </h1>

            </div>

        </div>


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
            
                <div
                        class="form-group"
                        style="margin:0;"
                    >

                        <label>
                            Top-Up ID
                        </label>

                        <input
                            type="number"
                            name="topup_id"
                            placeholder="Enter Top-Up ID"
                            value="<?php
                                echo htmlspecialchars(
                                    $topupId
                                );
                            ?>"
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
                        value="<?php echo htmlspecialchars($cashierId); ?>"
                    >
                </div>

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

                <button
                    type="submit"
                    class="add-student-btn"
                >
                    Filter
                </button>

                <a
                    href="topups.php"
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

        <section class="card table-card">

            <!-- =========================
                 TABLE
            ========================== -->

            <div class="table-wrapper">

                <table id="usersTable">

                    <thead>

                        <tr>

                            <th>
                                Top-Up ID
                            </th>

                            <th>
                                Parent ID
                            </th>

                            <th>
                                Student ID
                            </th>

                            <th>
                                Cashier ID
                            </th>

                            <th>
                                Amount
                            </th>

                            <th>
                                Method
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Date & Time
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php if ($topupsResult->num_rows > 0): ?>


                        <?php while (
                            $topup =
                                $topupsResult->fetch_assoc()
                        ): ?>

                        <?php
                                $type = strtolower(trim($topup['transaction_type'] ?? ''));

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


                                <!-- TOP-UP ID -->

                                <td>

                                    <?php

                                    echo htmlspecialchars(
                                        $topup['topup_id']
                                    );

                                    ?>

                                </td>



                                <!-- PARENT ID -->

                                <td>
                                    <?php
                                    $parentID = $topup['parent_id'] ?? '';
                                    if (strlen($parentID) > 4) {
                                        $maskedParentID =
                                        str_repeat('*', strlen($parentID) - 4)
                                        . substr($parentID, -4);
                                        } else {
                                            $maskedParentID = str_repeat('*', strlen($parentID));
                                            }
                                            echo htmlspecialchars($maskedParentID);
                                            ?>
                                </td>



                                <!-- STUDENT ID -->

                                <td>
                                    <?php
                                    $studentID = $topup['student_num'] ?? '';
                                    if (strlen($studentID) > 4) {
                                        $maskedStudentID =
                                        str_repeat('*', strlen($studentID) - 4)
                                        . substr($studentID, -4);
                                        } else {
                                            $maskedStudentID = str_repeat('*', strlen($studentID));
                                            }
                                            echo htmlspecialchars($maskedStudentID);
                                            ?>
                                </td>

                                <td>

                                    <?php

                                    echo htmlspecialchars($topup['cashier_id'] ?? 'N/A');

                                    ?>

                                </td>



                                <!-- AMOUNT -->

                                <td>

                                    PHP
                                    <?php

                                    echo number_format(
                                        (float)$topup['amount'],
                                        2
                                    );

                                    ?>

                                </td>



                                <!-- METHOD -->

                                <td>

                                    <?php

                                    echo htmlspecialchars(
                                        $topup['method']
                                    );

                                    ?>

                                </td>



                                <!-- STATUS -->

                                <td>

                                    <?php

                                    echo htmlspecialchars(
                                        $topup['status']
                                    );

                                    ?>

                                </td>



                                <!-- DATE & TIME -->

                                <td>

                                    <?php

                                    echo htmlspecialchars(
                                        $topup['date_time']
                                    );

                                    ?>

                                </td>


                            </tr>


                        <?php endwhile; ?>


                    <?php else: ?>


                        <tr>

                            <td
                                colspan="8"
                                style="
                                    text-align:center;
                                    padding:30px;
                                    color:#777;
                                "
                            >

                                No top-ups found.

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