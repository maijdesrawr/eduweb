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

$totalParentsQuery = "
    SELECT COUNT(*) AS total
    FROM parents
";

$totalParentsResult = $conn->query($totalParentsQuery);

$totalParents = 0;

if ($totalParentsResult) {
    $row = $totalParentsResult->fetch_assoc();
    $totalParents = $row["total"];
}

$parentsQuery = "
    SELECT
        p.parent_id,
        p.Fname,
        p.first_name,
        p.middle_name,
        p.last_name,
        p.suffix,
        p.email,
        GROUP_CONCAT(
            s.student_num
            ORDER BY s.student_num
            SEPARATOR ','
        ) AS linked_students
    FROM parents p

    LEFT JOIN parent_student_link l
        ON p.parent_id = l.parent_id

    LEFT JOIN students s
        ON l.student_num = s.student_num

    GROUP BY
        p.parent_id,
        p.Fname,
        p.first_name,
        p.middle_name,
        p.last_name,
        p.suffix,
        p.email

    ORDER BY p.parent_id DESC
";

$parents = $conn->query($parentsQuery);

if (!$parents) {
    die("Error loading parents: " . $conn->error);
}

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
        Parents / Guardians
    </title>

    <link
        rel="stylesheet"
        href="style.css?v=<?php echo time(); ?>"
    >

    <style>
        .parent-id-link {
            color: var(--red);
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
        }

        .parent-id-link:hover {
            text-decoration: underline;
        }

        .no-search-results {
            text-align: center;
            padding: 30px;
            color: #777;
        }
    </style>

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
                class="active"
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

    <main>

        <div class="topbar">

            <div>

                <div class="eyebrow">
                    User Management
                </div>

                <h1>
                    Parents / Guardians
                </h1>

            </div>


            <div class="top-actions">

                <!-- SEARCH -->

                <input
                    type="search"
                    id="parentSearch"
                    class="search"
                    placeholder="Search parent name or ID..."
                    autocomplete="off"
                >


                <!-- NOTIFICATION -->

                <button
                    class="notification"
                >
                    🔎︎
                </button>

            </div>

        </div>



        <div class="student-summary-row">

            <div class="single-total-card">

                <div class="single-total-info">

                    <div class="stat-label">
                        Total Parents
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

        </div>



        <section class="card table-card">

            <div class="card-header">

                <h2>
                    Registered Parent / Guardian Accounts
                </h2>

            </div>


            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>

                            <th>
                                Parent
                            </th>

                            <th>
                                Parent ID
                            </th>

                            <th>
                                Email
                            </th>

                            <th>
                                Linked Students
                            </th>

                            <th>
                                Actions
                            </th>

                        </tr>

                    </thead>


                    <tbody id="parentsTable">

                        <?php

                        if ($parents && $parents->num_rows > 0) {

                            while ($parent = $parents->fetch_assoc()) {

                                $parentID = $parent["parent_id"];

                                $name = $parent["Fname"];

                                $email = !empty($parent["email"])
                                    ? $parent["email"]
                                    : "—";


                                /* GET LINKED STUDENTS */

                                $linkedStudents = [];

                                if (!empty($parent["linked_students"])) {

                                    $linkedStudents =
                                        explode(
                                            ",",
                                            $parent["linked_students"]
                                        );

                                }


                                /* GET INITIALS */

                                $words = explode(
                                    " ",
                                    trim($name)
                                );

                                $initials = "";

                                foreach ($words as $word) {

                                    if (!empty($word)) {

                                        $initials .= strtoupper(
                                            substr($word, 0, 1)
                                        );

                                    }

                                }

                                $initials = substr(
                                    $initials,
                                    0,
                                    2
                                );

                        ?>

                            <tr>

                                <!-- PARENT -->

                                <td>

                                    <div class="user-cell">

                                        <div class="avatar">

                                            <?php

                                            echo htmlspecialchars(
                                                $initials
                                            );

                                            ?>

                                        </div>


                                        <div>

                                            <strong>

                                                <?php

                                                echo htmlspecialchars(
                                                    $name
                                                );

                                                ?>

                                            </strong>

                                        </div>

                                    </div>

                                </td>



                                <!-- PARENT ID -->

                                <td>

                                    <a
                                    >
                                        <?php
                                        echo htmlspecialchars(
                                            $parentID
                                        );
                                        ?>
                                    </a>

                                </td>



                                <!-- EMAIL -->

                                <td>

                                    <?php

                                    echo htmlspecialchars(
                                        $email
                                    );

                                    ?>

                                </td>



                                <!-- LINKED STUDENTS -->

                                <td>

                                    <?php

                                    if (!empty($linkedStudents)) {

                                        foreach ($linkedStudents as $studentNum) {

                                            $studentNum =
                                                trim($studentNum);

                                            if (!empty($studentNum)) {

                                    ?>

                                                <div
                                                    style="
                                                        margin-bottom: 4px;
                                                    "
                                                >

                                                    <?php

                                                    echo htmlspecialchars(
                                                        $studentNum
                                                    );

                                                    ?>

                                                </div>

                                    <?php

                                            }

                                        }

                                    } else {

                                        echo "—";

                                    }

                                    ?>

                                </td>



                                <!-- ACTIONS -->

                                <td class="action-buttons">

                                    <button
                                        class="view-btn"
                                        onclick="viewParent('<?php echo htmlspecialchars($parentID); ?>')"
                                    >
                                        View
                                    </button>


                                    <button
                                        class="delete-btn"
                                        onclick="deleteParent('<?php echo htmlspecialchars($parentID); ?>')"
                                    >
                                        Delete
                                    </button>

                                </td>

                            </tr>

                        <?php

                            }

                        } else {

                        ?>

                            <tr>

                                <td
                                    colspan="5"
                                    style="text-align: center;"
                                >
                                    No parent accounts found.
                                </td>

                            </tr>

                        <?php

                        }

                        ?>

                    </tbody>

                </table>

            </div>

        </section>

    </main>

</div>



<script>

function viewParent(parentID) {

    window.location.href =
        "view_parent.php?parent_id=" +
        encodeURIComponent(parentID);

}


function deleteParent(parentID) {

    if (!confirm(
        "Are you sure you want to delete this parent account?"
    )) {

        return;

    }

    window.location.href =
        "delete_parent.php?parent_id=" +
        encodeURIComponent(parentID);

}


function searchParents(searchValue) {

    searchValue = searchValue
        .toLowerCase()
        .trim();

    const tableBody =
        document.getElementById("parentsTable");

    if (!tableBody) {
        return;
    }

    const rows =
        tableBody.querySelectorAll("tr");

    let foundParents = 0;

    rows.forEach(function(row) {

        if (row.id === "noSearchResults") {
            return;
        }

        const text =
            row.textContent
                .toLowerCase()
                .trim();

        if (text.includes(searchValue)) {
            row.style.display = "";
            foundParents++;
        } else {
            row.style.display = "none";
        }
    });

    let noResults =
        document.getElementById("noSearchResults");

    if (
        foundParents === 0 &&
        searchValue !== ""
    ) {

        if (!noResults) {

            noResults =
                document.createElement("tr");

            noResults.id =
                "noSearchResults";

            noResults.innerHTML = `
                <td colspan="5" class="no-search-results">
                    No parents found for
                    "<strong>${escapeSearchText(searchValue)}</strong>"
                </td>
            `;

            tableBody.appendChild(noResults);
        }

    } else {

        if (noResults) {
            noResults.remove();
        }
    }
}


function escapeSearchText(text) {

    const div =
        document.createElement("div");

    div.textContent = text;

    return div.innerHTML;
}


const parentSearch =
    document.getElementById("parentSearch");

if (parentSearch) {

    parentSearch.addEventListener(
        "input",
        function() {

            searchParents(this.value);

        }
    );
}


function openParentModal() {

    const modal =
        document.getElementById("parentModal");

    if (modal) {

        modal.style.display = "flex";

    }

}


function closeParentModal() {

    const modal =
        document.getElementById("parentModal");

    if (modal) {

        modal.style.display = "none";

    }

}


function addLinkedStudent(select) {

    const studentNum = select.value;

    if (!studentNum) {
        return;
    }

    const studentName =
        select.options[select.selectedIndex]
        .getAttribute("data-name");

    const selectedContainer =
        document.getElementById("selectedStudents");

    // Prevent duplicate students
    if (document.getElementById("selected-" + studentNum)) {
        select.value = "";
        return;
    }

    const studentRow =
        document.createElement("div");

    studentRow.className = "selected-student";
    studentRow.id = "selected-" + studentNum;

    const studentText =
        document.createElement("span");

    const strong =
        document.createElement("strong");

    strong.textContent = studentNum;

    studentText.appendChild(strong);
    studentText.appendChild(
        document.createTextNode(" - " + studentName)
    );

    const removeButton =
        document.createElement("button");

    removeButton.type = "button";
    removeButton.textContent = "×";
    removeButton.title = "Remove student";

    removeButton.onclick = function () {
        removeLinkedStudent(studentNum);
    };

    const hiddenInput =
        document.createElement("input");

    hiddenInput.type = "hidden";
    hiddenInput.name = "linked_students[]";
    hiddenInput.value = studentNum;

    studentRow.appendChild(studentText);
    studentRow.appendChild(removeButton);
    studentRow.appendChild(hiddenInput);

    selectedContainer.appendChild(studentRow);

    // Reset dropdown
    select.value = "";
}

function searchLinkedStudents(searchValue) {

    const results =
        document.getElementById("studentSearchResults");

    if (!results) {
        return;
    }

    const search =
        searchValue.trim().toLowerCase();

    results.innerHTML = "";

    const students = [
        <?php
        $studentSearchOptions = $conn->query("
    SELECT s.student_num, s.Fname
    FROM students s
    LEFT JOIN parent_student_link psl
        ON s.student_num = psl.student_num
    WHERE (s.parent_id IS NULL OR s.parent_id = '')
      AND psl.student_num IS NULL
    ORDER BY s.student_num ASC
");

        if ($studentSearchOptions) {

            while ($student = $studentSearchOptions->fetch_assoc()) {

                echo json_encode([
                    "student_num" => $student["student_num"],
                    "name" => $student["Fname"]
                ]) . ",";
            }
        }
        ?>
    ];

    const filteredStudents =
        students.filter(function(student) {

            const alreadySelected =
                document.getElementById(
                    "selected-" + student.student_num
                );

            if (alreadySelected) {
                return false;
            }

            return (
                student.student_num
                    .toLowerCase()
                    .includes(search)
                ||
                student.name
                    .toLowerCase()
                    .includes(search)
            );
        });

    if (filteredStudents.length === 0) {

        if (search !== "") {

            results.innerHTML =
                '<div class="no-student-results">' +
                'No students found.' +
                '</div>';
        }

        return;
    }

    filteredStudents.forEach(function(student) {

        const result =
            document.createElement("button");

        result.type = "button";
        result.className =
            "student-search-result";

        const number =
            document.createElement("strong");

        number.textContent =
            student.student_num;

        const name =
            document.createElement("span");

        name.textContent =
            " - " + student.name;

        result.appendChild(number);
        result.appendChild(name);

        result.onclick = function() {

            addLinkedStudent(
                student.student_num,
                student.name
            );
        };

        results.appendChild(result);
    });
}


function addLinkedStudent(studentNum, studentName) {

    const selectedContainer =
        document.getElementById(
            "selectedStudents"
        );

    if (!selectedContainer) {
        return;
    }

    if (
        document.getElementById(
            "selected-" + studentNum
        )
    ) {
        return;
    }

    const studentRow =
        document.createElement("div");

    studentRow.className =
        "selected-student";

    studentRow.id =
        "selected-" + studentNum;


    const studentText =
        document.createElement("span");

    const strong =
        document.createElement("strong");

    strong.textContent =
        studentNum;

    studentText.appendChild(strong);

    studentText.appendChild(
        document.createTextNode(
            " - " + studentName
        )
    );


    const removeButton =
        document.createElement("button");

    removeButton.type = "button";

    removeButton.textContent = "×";

    removeButton.title =
        "Remove student";

    removeButton.onclick = function() {

        removeLinkedStudent(
            studentNum
        );
    };


    const hiddenInput =
        document.createElement("input");

    hiddenInput.type = "hidden";

    hiddenInput.name =
        "linked_students[]";

    hiddenInput.value =
        studentNum;


    studentRow.appendChild(
        studentText
    );

    studentRow.appendChild(
        removeButton
    );

    studentRow.appendChild(
        hiddenInput
    );

    selectedContainer.appendChild(
        studentRow
    );


    const searchInput =
        document.getElementById(
            "studentSearch"
        );

    const results =
        document.getElementById(
            "studentSearchResults"
        );

    searchInput.value = "";

    results.innerHTML = "";

    searchInput.focus();
}


function removeLinkedStudent(studentNum) {

    const student =
        document.getElementById(
            "selected-" + studentNum
        );

    if (student) {
        student.remove();
    }
}

function removeLinkedStudent(studentNum) {

    const student =
        document.getElementById("selected-" + studentNum);

    if (student) {
        student.remove();
    }

}


// Close modal when clicking outside it

window.addEventListener(
    "click",
    function(event) {

        const modal =
            document.getElementById("parentModal");

        if (event.target === modal) {

            modal.style.display = "none";

        }

    }
);

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