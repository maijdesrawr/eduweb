/* =====================================
   TOGGLE MOBILE SIDEBAR
===================================== */

function toggleSidebar() {

    const sidebar =
        document.getElementById("sidebar");

    sidebar.classList.toggle("open");

}


/* =====================================
   CHANGE PAGE TITLE / ACTIVE NAV BUTTON
===================================== */

function showPage(page, button = null) {

    const pageTitle =
        document.getElementById("pageTitle");


    if (page === "Dashboard") {

        pageTitle.textContent =
            "School Superadmin Dashboard";

    } else {

        pageTitle.textContent = page;

    }


    /* Remove active class from all buttons */

    document
        .querySelectorAll(".nav button")
        .forEach(function(item) {

            item.classList.remove("active");

        });


    /* Add active class to clicked button */

    if (button) {

        button.classList.add("active");

    }


    /* Close sidebar on mobile */

    if (window.innerWidth <= 760) {

        document
            .getElementById("sidebar")
            .classList.remove("open");

    }

}


/* =====================================
   SEARCH REGISTERED ACCOUNTS
===================================== */

function searchUsers(searchText) {

    const query =
        searchText.toLowerCase();


    const rows =
        document.querySelectorAll(
            "#usersTable tr"
        );


    rows.forEach(function(row) {

        const rowText =
            row.innerText.toLowerCase();


        if (rowText.includes(query)) {

            row.style.display = "";

        } else {

            row.style.display = "none";

        }

    });

    

}