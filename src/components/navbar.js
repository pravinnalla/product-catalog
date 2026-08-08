/**
 * ============================================================
 * Product Catalog
 * ------------------------------------------------------------
 * File      : navbar.js
 * Purpose   : Responsive Navigation Bar
 * Version   : 1.0.0
 * ============================================================
 */

export function renderNavbar() {

    const navbar = document.querySelector("#navbar");

    if (!navbar) return;

    const currentPage = getCurrentPage();

    navbar.innerHTML = `

<header class="sticky-top shadow-sm">

    
    <nav class="navbar navbar-expand-lg bg-white py-3">

        <div class="container">

            <!-- ========================================= -->
            <!-- Logo -->
            <!-- ========================================= -->

            <a class="navbar-brand fw-bold text-danger fs-3"
               href="/index.html">

               
                <i class="bi bi-shield-fill-check me-2"></i>

                SAFETYMART

            </a>

            <!-- ========================================= -->
            <!-- Mobile Toggle -->
            <!-- ========================================= -->

            <button
                class="navbar-toggler"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#mainNavbar"
                aria-controls="mainNavbar"
                aria-expanded="false"
                aria-label="Toggle navigation">

                <span class="navbar-toggler-icon"></span>

            </button>

            <!-- ========================================= -->
            <!-- Navigation -->
            <!-- ========================================= -->

            <div
                class="collapse navbar-collapse"
                id="mainNavbar">

                <ul class="navbar-nav mx-auto">

                    <li class="nav-item">

                        
                            <a
    class="nav-link ${currentPage === "index" ? "active fw-semibold" : ""}"
    ${currentPage === "index" ? 'aria-current="page"' : ""}
    href="/index.html">

                            Home

                        </a>

                    </li>

                    <li class="nav-item">

                        <a
                            class="nav-link ${currentPage === "about" ? "active fw-semibold" : ""}"
                            href="/about.html">

                            About

                        </a>

                    </li>

                    <li class="nav-item">

                        <a
                            class="nav-link ${currentPage === "products" ? "active fw-semibold" : ""}"
                            href="/products.html">

                            Products

                        </a>

                    </li>

                    <li class="nav-item">

                        <a
                            class="nav-link ${currentPage === "contact" ? "active fw-semibold" : ""}"
                            href="/contact.html">

                            Contact

                        </a>

                    </li>

                </ul>

                <!-- ===================================== -->
<!-- CTA -->
<!-- ===================================== -->

${
    currentPage !== "contact"
        ? `
            <div class="d-flex">

                <a
                    href="/contact.html"
                    class="btn btn-danger">

                    <i class="bi bi-envelope-paper me-2"></i>

                    Request Quote

                </a>

            </div>
        `
        : ""
}

            </div>

        </div>

    </nav>

</header>

`;

}

/**
 * ------------------------------------------------------------
 * Returns Current Page
 * ------------------------------------------------------------
 */

function getCurrentPage() {

    const page = window.location.pathname
        .split("/")
        .pop()
        .replace(".html", "");

    return page || "index";

}