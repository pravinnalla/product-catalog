/**
 * ============================================================
 * Laxmikant Traders
 * ------------------------------------------------------------
 * File      : navbar.js
 * Purpose   : Responsive Navigation Bar
 * Version   : 1.2.0
 * ============================================================
 */

import { pageUrl } from "../utils/paths.js";
import companyLogo from "../assets/images/brands/laxmikant-traders-logo.png";

export function renderNavbar() {

    const navbar = document.querySelector("#navbar");

    if (!navbar) return;

    const currentPage = getCurrentPage();

    navbar.innerHTML = `

<header class="sticky-top shadow-sm">

    <nav class="navbar navbar-expand-lg bg-white py-2">

        <div class="container">

            <!-- ========================================= -->
            <!-- Company Logo -->
            <!-- ========================================= -->

            <a
                class="navbar-brand p-0"
                href="${pageUrl("index.html")}"
                aria-label="Laxmikant Traders Home">

                <img
                    src="${companyLogo}"
                    alt="Laxmikant Traders"
                    style="
                        width: clamp(200px, 22vw, 320px);
                        height: auto;
                        display: block;
                    ">

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
                            href="${pageUrl("index.html")}">
                            Home
                        </a>
                    </li>

                    <li class="nav-item">
                        <a
                            class="nav-link ${currentPage === "about" ? "active fw-semibold" : ""}"
                            href="${pageUrl("about.html")}">
                            About
                        </a>
                    </li>

                    <li class="nav-item">
                        <a
                            class="nav-link ${currentPage === "products" ? "active fw-semibold" : ""}"
                            href="${pageUrl("products.html")}">
                            Products
                        </a>
                    </li>

                    <li class="nav-item">
                        <a
                            class="nav-link ${currentPage === "contact" ? "active fw-semibold" : ""}"
                            href="${pageUrl("contact.html")}">
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
                                    href="${pageUrl("contact.html")}"
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

function getCurrentPage() {

    const page = window.location.pathname
        .split("/")
        .pop()
        .replace(".html", "");

    return page || "index";

}