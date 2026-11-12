<?php
session_start();


if (!isset($_SESSION['admin_id']) ||
    ($_SESSION['admin_role'] ?? '') !== 'system admin') {
    header("Location: index.php");
    exit();
}

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

$adminCountQuery = "
    SELECT COUNT(*) AS total_admins
    FROM admins
";

$adminCountResult = $conn->query($adminCountQuery);

$totalAdmins = 0;

if ($adminCountResult) {
    $row = $adminCountResult->fetch_assoc();
    $totalAdmins = $row["total_admins"];
}

/*TOTAL STUDENTS*/

$studentsQuery = "
    SELECT COUNT(*) AS total_students
    FROM students
";

$studentsResult = $conn->query($studentsQuery);

$studentData = $studentsResult->fetch_assoc();

$totalStudents = $studentData["total_students"];

/*TOTAL PARENTS*/

$parentsQuery = "
    SELECT COUNT(*) AS total_parents
    FROM parents
";

$parentsResult = $conn->query($parentsQuery);

$parentData = $parentsResult->fetch_assoc();

$totalParents = $parentData["total_parents"];

/*TOTAL CASHIERS*/

$cashiersQuery = "
    SELECT
        (SELECT COUNT(*) FROM cashiers) +
        (SELECT COUNT(*) FROM canteens) AS total_cashiers
";

$cashiersResult = $conn->query($cashiersQuery);
$cashierData = $cashiersResult->fetch_assoc();

$totalCashiers = $cashierData["total_cashiers"];


/*TOTAL TRANSACTION AMOUNT*/

$totalTransactionAmountQuery = "
    SELECT COALESCE(SUM(amount), 0) AS total_transaction_amount
    FROM transactions
    WHERE transaction_type = 'payment'
";

$totalTransactionAmountResult = $conn->query(
    $totalTransactionAmountQuery
);

$totalTransactionAmountData =
    $totalTransactionAmountResult->fetch_assoc();

$totalTransactionAmount =
    $totalTransactionAmountData["total_transaction_amount"];


/*RECENT TRANSACTIONS*/

$recentTransactionsQuery = "
    SELECT
        t.transaction_id,
        t.amount,
        t.transaction_type,
        t.description,
        t.date_time,
        s.Fname AS student_name
    FROM transactions t
    LEFT JOIN students s
        ON t.student_num = s.student_num
    ORDER BY t.date_time DESC
    LIMIT 5
";

$recentTransactions = $conn->query(
    $recentTransactionsQuery
);


/*RECENT STUDENTS*/

$recentStudentsQuery = "
    SELECT
        student_num,
        Fname,
        balance
    FROM students
    ORDER BY student_num DESC
    LIMIT 5
";

$recentStudents = $conn->query(
    $recentStudentsQuery
);


/*RECENT PARENTS*/

$recentParentsQuery = "
    SELECT
        parent_id,
        Fname,
        email
    FROM parents
    ORDER BY parent_id DESC
    LIMIT 5
";

$recentParents = $conn->query(
    $recentParentsQuery
);

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
        School Superadmin Dashboard
    </title>
    <link
        rel="stylesheet"
        href="style.css?v=<?php echo time(); ?>"
    >

</head>


<body>

<div class="app">

    <!-- SIDEBAR -->
    <aside
        class="sidebar"
        id="sidebar"
         >
        <!-- BRAND -->

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

        <!-- MANAGEMENT -->

        <div class="nav-title">
            User Management
        </div>

        <nav class="nav">

            <button
                class="active"
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
                <span class="nav-icon">    
                </span>
                Students
            </button>

            <button
                onclick="window.location.href='parents.php'"
            >
                <span class="nav-icon">
                </span>
                Parents / Guardians
            </button>

            <button
                onclick="window.location.href='pos_cashiers.php'"
            >
                <span class="nav-icon">
                </span>
                POS / Cashiers
            </button>

        </nav>

        <!--FINANCE AND SYSTEM-->

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

        <!--SUPERADMIN PROFILE-->

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

    <!--MAIN CONTENT-->

    <main>

        <!--TOP BAR-->

        <div class="topbar">
            <div class="topbar-left">

                <!-- MOBILE MENU -->

                <button
                    class="mobile-menu"
                    onclick="toggleSidebar()"
                >
                    ☰
                </button>

                <div>
                    <div class="eyebrow">
                        Welcome
                    </div>
                    <h1 id="pageTitle">
    <?php echo htmlspecialchars($adminName); ?>
</h1>
                </div>
            </div>

            <div class="top-actions">

            </div>

        </div>



        <!--STATISTICS-->

        <div class="total-cards">

        <!-- TOTAL STUDENTS -->

            <div class="total-card">

                <div class="total-info">

                    <div class="stat-label">
                        Total Students
                    </div>

                    <div class="stat-number">
                        <?php
                        echo number_format($totalStudents);
                        ?>
                    </div>

                    <div class="stat-trend">
                        Registered student accounts
                    </div>

                </div>

            </div>

            <!-- TOTAL PARENTS -->

            <div class="total-card">

                <div class="total-info">

                    <div class="stat-label">
                        Total Parents / Guardian
                    </div>

                    <div class="stat-number">
                        <?php
                        echo number_format($totalParents);
                        ?>
                    </div>

                    <div class="stat-trend">
                        Registered parent accounts
                    </div>

                </div>

            </div>

            <!-- TOTAL CASHIERS -->

            <div class="total-card">

                <div class="total-info">

                    <div class="stat-label">
                        Active POS / Cashiers
                    </div>

                    <div class="stat-number">
                        <?php
                        echo number_format($totalCashiers);
                        ?>
                    </div>

                    <div class="stat-trend">
                        POS / Cashier accounts in system
                    </div>

                </div>

            </div>

            <div class="total-card">

                <div class="total-info">

                    <div class="stat-label">
                        Active Admins
                    </div>

                    <div class="stat-number">
                        <?php
                        echo number_format($totalAdmins);
                        ?>
                    </div>

                    <div class="stat-trend">
                        Admin accounts in system
                    </div>

                </div>

            </div>


            <!-- TOTAL BALANCE -->

            <div class="total-card">

                <div class="total-info">

                    <div class="stat-label">
                        Total Credit
                    </div>

                    <div class="stat-number">
                        ₱<?php
                        echo number_format($totalTransactionAmount,2);
                        ?>
                    </div>

                    <div class="stat-trend">
                        Total amount received from cashiers & canteen
                    </div>

                </div>

            </div>

        </div>


        <!--RECENT TRANSACTIONS-->

        <section class="card recent-activity-card">
            <div class="card-header">
                <h2>
                    Recent Transactions
                </h2>
                <span
                    class="view-link"
                    onclick="window.location.href='transactions.php'"
                >
                    View All →
                </span>
            </div>

            <div class="activity-list">
                <?php
                if (
                    $recentTransactions &&
                    $recentTransactions->num_rows > 0
                ) {
                    while (
                        $transaction =
                        $recentTransactions->fetch_assoc()
                    ) {
                        $studentName = trim(
                            $transaction["student_name"] ?? "Unknown Student"
                        );

                        if ($studentName !== "" && $studentName !== "Unknown Student") {
                            $nameParts = preg_split('/\\s+/', $studentName);

                            $maskNamePart = function ($part) {
                                $firstLetter = function_exists("mb_substr")
                                    ? mb_substr($part, 0, 1, "UTF-8")
                                    : substr($part, 0, 1);

                                $length = function_exists("mb_strlen")
                                    ? mb_strlen($part, "UTF-8")
                                    : strlen($part);

                                return $firstLetter . str_repeat("*", max($length - 1, 0));
                            };

                            if (count($nameParts) >= 2) {
                                $firstName = array_shift($nameParts);
                                $lastName = array_pop($nameParts);

                                $maskedMiddleNames = array_map(function ($part) {
                                    $length = function_exists("mb_strlen")
                                        ? mb_strlen($part, "UTF-8")
                                        : strlen($part);

                                    return str_repeat("*", $length);
                                }, $nameParts);

                                $studentName = implode(" ", array_merge(
                                    [$maskNamePart($firstName)],
                                    $maskedMiddleNames,
                                    [$maskNamePart($lastName)]
                                ));
                            } else {
                                $studentName = $maskNamePart($nameParts[0]);
                            }
                        }

                        $amount =
                            number_format(
                                $transaction["amount"],
                                2
                            );
                        ?>
                        <div class="activity">
                            <div class="activity-icon">
                                ₱
                            </div>
                            <div class="activity-info">
                                <strong>
                                    <?php
                                    echo htmlspecialchars(
                                        $transaction[
                                            "description"
                                        ]
                                        ?: "Transaction"
                                    );
                                    ?>
                                </strong>
                                <span>
                                    Student:
                                    <?php
                                    echo htmlspecialchars(
                                        $studentName
                                    );
                                    ?>
                                    ·
                                    <?php
                                    echo date(
                                        "M d, Y h:i A",
                                        strtotime(
                                            $transaction[
                                                "date_time"
                                            ]
                                        )
                                    );
                                    ?>
                                </span>
                            </div>

                            <div class="activity-status">
                                ₱<?php
                                    echo $amount;
                                ?>
                            </div>
                        </div>
                        <?php
                    }
                } else {
                    ?>
                    <div class="activity">
                        <div class="activity-info">
                            <strong>
                                No transactions found
                            </strong>
                            <span>
                                Transaction activity will appear here.
                            </span>
                        </div>
                    </div>
                    <?php
                }
                ?>
            </div>
        </section>

        <!--MANAGE BUTTONS-->
        <section class="quick-actions">
            
        <!-- STUDENTS -->
            <div
                class="quick-action"
                onclick="window.location.href='students.php'"
            >
                <strong>
                    Manage Students
                </strong>
                <p>
                    View and manage student accounts.
                </p>
            </div>

            <!-- PARENTS -->
            <div
                class="quick-action"
                onclick="window.location.href='parents.php'"
            >
                <strong>
                    Manage Parents / Guardians
                </strong>
                <p>
                    View and manage parent accounts.
                </p>
            </div>

            <!-- CASHIERS -->
            <div
                class="quick-action"
                onclick="window.location.href='pos_cashiers.php'"
            >
                <strong>
                    Manage POS / Cashiers
                </strong>
                <p>
                    View cashier and POS accounts.
                </p>
            </div>
        </section>
    </main>
</div>

<!--JAVASCRIPT CONNECTION-->

<!-- Connect external JavaScript -->
<script src="script.js"></script>

<!-- Admin Profile Dropdown -->
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

<?php
$conn->close();
?>