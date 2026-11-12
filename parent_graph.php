
<?php
session_start();

if (!isset($_SESSION['parent_id'])) {
    header("Location: index.php");
    exit();
}

require_once "db.php";

$parentId = (string) $_SESSION['parent_id'];
$parentName = (string) ($_SESSION['parent_name'] ?? 'Parent');

// Generate parent initials
$nameParts = preg_split('/\s+/', $parentName);
$parentInitials = '';

foreach ($nameParts as $part) {
    if ($part !== '') {
        $parentInitials .= strtoupper(substr($part, 0, 1));
    }
}

$parentInitials = substr($parentInitials, 0, 2);


// =====================================
// GET LINKED STUDENTS
// =====================================

$studentsStmt = $conn->prepare("
    SELECT DISTINCT s.student_num, s.Fname
    FROM students AS s
    INNER JOIN parent_student_link AS l
        ON l.student_num = s.student_num
    WHERE l.parent_id = ?
    ORDER BY s.Fname ASC
");

if (!$studentsStmt) {
    die("Student list query preparation failed: " . $conn->error);
}

$studentsStmt->bind_param("s", $parentId);
$studentsStmt->execute();

$studentsResult = $studentsStmt->get_result();

$linkedStudents = [];

while ($row = $studentsResult->fetch_assoc()) {
    $linkedStudents[] = $row;
}

$studentsStmt->close();


// =====================================
// VALIDATE STUDENT FILTER
// =====================================

$selectedStudent = trim($_GET['student_num'] ?? 'all');

$validStudent = ($selectedStudent === 'all');

foreach ($linkedStudents as $linkedStudent) {
    if ((string)$linkedStudent['student_num'] === $selectedStudent) {
        $validStudent = true;
        break;
    }
}

if (!$validStudent) {
    $selectedStudent = 'all';
}


// =====================================
// VALIDATE CHART PERIOD
// =====================================

$chartPeriod = strtolower(trim($_GET['chart_period'] ?? 'monthly'));

$allowedChartPeriods = [
    'daily',
    'weekly',
    'monthly',
    'yearly'
];

if (!in_array($chartPeriod, $allowedChartPeriods, true)) {
    $chartPeriod = 'monthly';
}


// =====================================
// TOTAL EXPENSES
// Follows selected student filter
// =====================================

$expensesQuery = "
    SELECT COALESCE(SUM(t.amount), 0) AS total_expenses
    FROM transactions AS t
    WHERE LOWER(TRIM(t.transaction_type)) = 'payment'
      AND EXISTS (
          SELECT 1
          FROM parent_student_link AS l
          WHERE l.student_num = t.student_num
            AND l.parent_id = ?
      )
";

$expensesParams = [$parentId];
$expensesTypes = "s";

if ($selectedStudent !== 'all') {
    $expensesQuery .= " AND t.student_num = ?";
    $expensesParams[] = $selectedStudent;
    $expensesTypes .= "s";
}

$expensesStmt = $conn->prepare($expensesQuery);

if (!$expensesStmt) {
    die("Expenses query preparation failed: " . $conn->error);
}

$expensesStmt->bind_param($expensesTypes, ...$expensesParams);

if (!$expensesStmt->execute()) {
    die("Expenses query execution failed: " . $expensesStmt->error);
}

$expensesResult = $expensesStmt->get_result();
$expensesData = $expensesResult->fetch_assoc();

$totalExpenses = (float)($expensesData['total_expenses'] ?? 0);


// =====================================
// TOTAL BALANCE
// Follows selected student filter
// =====================================

$balanceQuery = "
    SELECT COALESCE(SUM(s.balance), 0) AS total_balance
    FROM students AS s
    INNER JOIN parent_student_link AS l
        ON l.student_num = s.student_num
    WHERE l.parent_id = ?
";

$balanceParams = [$parentId];
$balanceTypes = "s";

if ($selectedStudent !== 'all') {
    $balanceQuery .= " AND s.student_num = ?";
    $balanceParams[] = $selectedStudent;
    $balanceTypes .= "s";
}

$balanceStmt = $conn->prepare($balanceQuery);

if (!$balanceStmt) {
    die("Balance query preparation failed: " . $conn->error);
}

$balanceStmt->bind_param($balanceTypes, ...$balanceParams);

if (!$balanceStmt->execute()) {
    die("Balance query execution failed: " . $balanceStmt->error);
}

$balanceResult = $balanceStmt->get_result();
$balanceData = $balanceResult->fetch_assoc();

$totalBalance = (float)($balanceData['total_balance'] ?? 0);


// =====================================
// PREPARE CHART DATA
// =====================================

$chartLabels = [];
$topUpTotals = [];
$expenseTotals = [];

if ($chartPeriod === 'daily') {

    for ($hour = 0; $hour < 24; $hour++) {
        $chartLabels[] = date('g A', mktime($hour, 0, 0));

        $topUpTotals[$hour] = 0;
        $expenseTotals[$hour] = 0;
    }

} elseif ($chartPeriod === 'weekly') {

    $chartLabels = [
        'Monday',
        'Tuesday',
        'Wednesday',
        'Thursday',
        'Friday',
        'Saturday',
        'Sunday'
    ];

    for ($day = 1; $day <= 7; $day++) {
        $topUpTotals[$day] = 0;
        $expenseTotals[$day] = 0;
    }

} elseif ($chartPeriod === 'monthly') {

    for ($day = 1; $day <= (int)date('t'); $day++) {
        $chartLabels[] = (string)$day;

        $topUpTotals[$day] = 0;
        $expenseTotals[$day] = 0;
    }

} else {

    $chartLabels = [
        'January',
        'February',
        'March',
        'April',
        'May',
        'June',
        'July',
        'August',
        'September',
        'October',
        'November',
        'December'
    ];

    for ($month = 1; $month <= 12; $month++) {
        $topUpTotals[$month] = 0;
        $expenseTotals[$month] = 0;
    }
}


// =====================================
// GET CHART TRANSACTIONS
// =====================================

$chartQuery = "
    SELECT
        t.student_num,
        t.amount,
        t.transaction_type,
        t.date_time
    FROM transactions AS t
    WHERE EXISTS (
        SELECT 1
        FROM parent_student_link AS l
        WHERE l.student_num = t.student_num
          AND l.parent_id = ?
    )
";

$chartParams = [$parentId];
$chartTypes = "s";

if ($selectedStudent !== 'all') {
    $chartQuery .= " AND t.student_num = ?";
    $chartParams[] = $selectedStudent;
    $chartTypes .= "s";
}

$chartStmt = $conn->prepare($chartQuery);

if (!$chartStmt) {
    die("Chart query preparation failed: " . $conn->error);
}

$chartStmt->bind_param($chartTypes, ...$chartParams);

if (!$chartStmt->execute()) {
    die("Chart query execution failed: " . $chartStmt->error);
}

$chartResult = $chartStmt->get_result();


// =====================================
// GROUP TRANSACTIONS BY PERIOD
// =====================================

$today = new DateTime('today');

$weekStart = (clone $today)->modify('monday this week');
$weekEnd = (clone $weekStart)->modify('+7 days');

while ($chartTransaction = $chartResult->fetch_assoc()) {

    if (empty($chartTransaction['date_time'])) {
        continue;
    }

    $timestamp = strtotime($chartTransaction['date_time']);

    if ($timestamp === false) {
        continue;
    }

    $type = strtolower(trim($chartTransaction['transaction_type'] ?? ''));
    $amount = (float)$chartTransaction['amount'];

    if ($chartPeriod === 'daily') {

        if (date('Y-m-d', $timestamp) !== date('Y-m-d')) {
            continue;
        }

        $bucket = (int)date('G', $timestamp);

    } elseif ($chartPeriod === 'weekly') {

        $transactionDate = new DateTime(date('Y-m-d', $timestamp));

        if ($transactionDate < $weekStart || $transactionDate >= $weekEnd) {
            continue;
        }

        $bucket = (int)date('N', $timestamp);

    } elseif ($chartPeriod === 'monthly') {

        if (date('Y-m', $timestamp) !== date('Y-m')) {
            continue;
        }

        $bucket = (int)date('j', $timestamp);

    } else {

        if (date('Y', $timestamp) !== date('Y')) {
            continue;
        }

        $bucket = (int)date('n', $timestamp);
    }

    if (in_array($type, ['top-up', 'topup', 'top_up'], true)) {

        $topUpTotals[$bucket] += $amount;

    } elseif ($type === 'payment') {

        $expenseTotals[$bucket] += $amount;
    }
}

$chartStmt->close();

$topUpChartData = array_values($topUpTotals);
$expenseChartData = array_values($expenseTotals);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Parent Financial Graph</title>

    <link
        rel="stylesheet"
        href="style.css?v=<?php echo time(); ?>"
    >

</head>

<body>

<div class="app">

    <!-- SIDEBAR -->
    <aside class="sidebar" id="sidebar">

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
                    Parent Dashboard
                </span>

            </div>

        </div>


        <!-- USER MANAGEMENT -->
        <div class="nav-title">
            User Management
        </div>

        <nav class="nav">

            <button
                onclick="window.location.href='parent_dashboard.php'"
            >
                <span class="nav-icon">⌂</span>
                Dashboard
            </button>

            <button
                onclick="window.location.href='parent_student.php'"
            >
                <span class="nav-icon"></span>
                Students
            </button>

            <button
                class="active"
                onclick="window.location.href='parent_graph.php'"
            >
                <span class="nav-icon"></span>
                Graphs
            </button>

        </nav>


        <!-- TRANSACTION RECORDS -->
        <div class="nav-title finance-title">
            Transaction Records
        </div>

        <nav class="nav">

            <button
                onclick="window.location.href='parent_topup.php'"
            >
                <span class="nav-icon">₱</span>
                Top-Ups
            </button>

            <button
                onclick="window.location.href='parent_transactions.php'"
            >
                <span class="nav-icon">▥</span>
                Transactions
            </button>

        </nav>


        <!-- PARENT PROFILE -->
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
                aria-expanded="false"
            >

                <span class="avatar">
                    <?php echo htmlspecialchars($parentInitials); ?>
                </span>

                <span class="admin-profile-details">

                    <strong>
                        <?php echo htmlspecialchars($parentName); ?>
                    </strong>

                    <small>Parent</small>

                </span>

                <span class="admin-profile-chevron">
                    ⌃
                </span>

            </button>

        </div>

    </aside>


    <!-- MAIN CONTENT -->
    <main>

        <!-- TOP BAR -->
        <div class="topbar">

            <div class="topbar-left">

                <button
                    class="mobile-menu"
                    onclick="toggleSidebar()"
                >
                    ☰
                </button>

                <div>

                    <div class="eyebrow">
                        Transaction Records
                    </div>

                    <h1 id="pageTitle">
                        Student Financial Overview
                    </h1>

                </div>

            </div>

            <div class="top-actions"></div>

        </div>

        <!-- FILTERS -->
        <form
            method="GET"
            action="parent_graph.php"
            style="
                display:flex;
                gap:12px;
                align-items:flex-end;
                flex-wrap:wrap;
                margin-bottom:22px;
                margin-top:15px;
            "
        >

            <!-- STUDENT FILTER -->
            <div class="form-group" style="margin:0;">

                <label for="student_num">
                    Student Name
                </label>

                <select
                    id="student_num"
                    name="student_num"
                    style="min-width:220px;"
                >

                    <option
                        value="all"
                        <?php echo $selectedStudent === 'all' ? 'selected' : ''; ?>
                    >
                        All Students
                    </option>

                    <?php foreach ($linkedStudents as $linkedStudent): ?>

                        <option
                            value="<?php echo htmlspecialchars($linkedStudent['student_num'], ENT_QUOTES, 'UTF-8'); ?>"
                            <?php echo $selectedStudent === (string)$linkedStudent['student_num'] ? 'selected' : ''; ?>
                        >

                            <?php echo htmlspecialchars($linkedStudent['Fname'], ENT_QUOTES, 'UTF-8'); ?>

                            (<?php echo htmlspecialchars($linkedStudent['student_num'], ENT_QUOTES, 'UTF-8'); ?>)

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- TIME PERIOD FILTER -->
            <div class="form-group" style="margin:0;">

                <label for="chart_period">
                    Time Period
                </label>

                <select
                    id="chart_period"
                    name="chart_period"
                    style="min-width:150px;"
                >

                    <option
                        value="daily"
                        <?php echo $chartPeriod === 'daily' ? 'selected' : ''; ?>
                    >
                        Daily
                    </option>

                    <option
                        value="weekly"
                        <?php echo $chartPeriod === 'weekly' ? 'selected' : ''; ?>
                    >
                        Weekly
                    </option>

                    <option
                        value="monthly"
                        <?php echo $chartPeriod === 'monthly' ? 'selected' : ''; ?>
                    >
                        Monthly
                    </option>

                    <option
                        value="yearly"
                        <?php echo $chartPeriod === 'yearly' ? 'selected' : ''; ?>
                    >
                        Yearly
                    </option>

                </select>

            </div>


            <button
                type="submit"
                class="add-student-btn"
            >
                Apply Filter
            </button>


            <a
                href="parent_graph.php"
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
                Reset
            </a>

        </form>

        <!-- FINANCIAL SUMMARY -->
        <div style="
            display:flex;
            grid-template-columns:repeat(auto-fit, minmax(240px, 1fr));
            gap:18px;
            margin-bottom:18px;
        ">

            <!-- TOTAL BALANCE -->
            <div class="single-total-card">

                <div class="single-total-info">

                    <div class="stat-label">
                        Total Balance
                    </div>

                    <div class="stat-number">
                        ₱<?php echo number_format($totalBalance, 2); ?>
                    </div>

                    <div class="stat-trend">

                        <?php
                        echo $selectedStudent === 'all'
                            ? 'Combined balance from all linked student accounts'
                            : 'Current balance for the selected student';
                        ?>

                    </div>

                </div>

            </div>


            <!-- TOTAL EXPENSES -->
            <div class="single-total-card">

                <div class="single-total-info">

                    <div class="stat-label">
                        Total Expenses
                    </div>

                    <div class="stat-number">
                        ₱<?php echo number_format($totalExpenses, 2); ?>
                    </div>

                    <div class="stat-trend">

                        <?php
                        echo $selectedStudent === 'all'
                            ? 'Combined payment expenses from all linked student accounts'
                            : 'Total payment expenses for the selected student';
                        ?>

                    </div>

                </div>

            </div>

        </div>


        <!-- STUDENT FINANCIAL GRAPH -->
        <section
            class="card"
            style="margin-top:20px; padding:20px;"
        >

            <div style="margin-bottom:18px;">

                <h2 style="margin:0 0 5px;">
                    Top-Ups and Expenses
                </h2>

                <p style="margin:0; color:#777; font-size:13px;">

                    Compare the amount added to student accounts with the amount spent.

                </p>

            </div>


            <!-- CHART LEGEND -->
            <div style="
                display:flex;
                gap:20px;
                flex-wrap:wrap;
                margin-bottom:12px;
                font-size:13px;
            ">

                <span style="
                    display:flex;
                    align-items:center;
                    gap:7px;
                ">

                    <span style="
                        width:12px;
                        height:12px;
                        border-radius:3px;
                        background:#22a06b;
                        display:inline-block;
                    "></span>

                    Top-Ups

                </span>


                <span style="
                    display:flex;
                    align-items:center;
                    gap:7px;
                ">

                    <span style="
                        width:12px;
                        height:12px;
                        border-radius:3px;
                        background:#f59e0b;
                        display:inline-block;
                    "></span>

                    Expenses

                </span>

            </div>


            <!-- CHART -->
            <div style="
                position:relative;
                width:100%;
                height:360px;
            ">

                <canvas
                    id="parentFinanceChart"
                    role="img"
                    aria-label="Bar chart comparing top-ups and expenses"
                ></canvas>

            </div>


            <?php if (count($linkedStudents) === 0): ?>

                <p style="
                    text-align:center;
                    color:#777;
                    margin-top:15px;
                ">

                    No student accounts are currently linked to your parent account.

                </p>

            <?php endif; ?>

        </section>

    </main>

</div>


<!-- JAVASCRIPT -->
<script src="script.js"></script>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>


<script>

// =====================================
// FINANCIAL BAR CHART
// =====================================

const chartCanvas = document.getElementById('parentFinanceChart');

if (chartCanvas && typeof Chart !== 'undefined') {

    new Chart(chartCanvas, {

        type: 'bar',

        data: {

            labels: <?php echo json_encode($chartLabels); ?>,

            datasets: [

                {
                    label: 'Top-Ups',

                    data: <?php echo json_encode($topUpChartData); ?>,

                    backgroundColor: '#22a06b',

                    borderRadius: 4
                },

                {
                    label: 'Expenses',

                    data: <?php echo json_encode($expenseChartData); ?>,

                    backgroundColor: '#f59e0b',

                    borderRadius: 4
                }

            ]

        },

        options: {

            responsive: true,

            maintainAspectRatio: false,

            interaction: {
                mode: 'index',
                intersect: false
            },

            plugins: {

                legend: {
                    display: false
                },

                tooltip: {

                    callbacks: {

                        label: function(context) {

                            return context.dataset.label + ': ₱' +

                                Number(context.raw).toLocaleString('en-PH', {

                                    minimumFractionDigits: 2,

                                    maximumFractionDigits: 2

                                });

                        }

                    }

                }

            },

            scales: {

                x: {
                    grid: {
                        display: false
                    }
                },

                y: {

                    beginAtZero: true,

                    ticks: {

                        callback: function(value) {

                            return '₱' + Number(value).toLocaleString('en-PH');

                        }

                    }

                }

            }

        }

    });

}

</script>


<script>

// =====================================
// PARENT PROFILE DROPDOWN
// =====================================

function toggleAdminMenu(event) {

    event.stopPropagation();

    const menu = document.getElementById("adminLogoutMenu");

    const profile = document.getElementById("adminProfileButton");

    menu.classList.toggle("show");

    const isOpen = menu.classList.contains("show");

    profile.setAttribute("aria-expanded", isOpen);

}


// =====================================
// CLOSE DROPDOWN WHEN CLICKING OUTSIDE
// =====================================

document.addEventListener("click", function(event) {

    const menu = document.getElementById("adminLogoutMenu");

    const profile = document.getElementById("adminProfileButton");

    if (
        !menu.contains(event.target) &&
        !profile.contains(event.target)
    ) {

        menu.classList.remove("show");

        profile.setAttribute("aria-expanded", "false");

    }

});

</script>


</body>
</html>


<?php

$expensesStmt->close();
$balanceStmt->close();

$conn->close();

?>
