/**
 * ============================================================
 * Product Catalog
 * ------------------------------------------------------------
 * File      : app.js
 * Purpose   : Application Entry Point
 * Version   : 1.2.0
 * ============================================================
 */

import { renderHomePage } from "../pages/home.js";
import { renderProductsPage } from "../pages/products.js";
import { renderProductPage } from "../pages/product.js";
import { renderContactPage } from "../pages/contact.js";

/**
 * ------------------------------------------------------------
 * Application Initializer
 * ------------------------------------------------------------
 */

function initializeApp() {

    const app = document.querySelector("#app");

    if (!app) {

        console.error("Application root (#app) not found.");

        return;

    }

    const currentPage = getCurrentPage();

    switch (currentPage) {

        case "index":

            renderHomePage(app);

            break;

        case "products":

            renderProductsPage(app);

            break;

        case "product":

            renderProductPage(app);

            break;

        case "contact":

            renderContactPage(app);

            break;

        default:

            renderNotImplemented(app);

            break;

    }

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

/**
 * ------------------------------------------------------------
 * Temporary Placeholder
 * ------------------------------------------------------------
 */

function renderNotImplemented(app) {

    app.innerHTML = `

        <div class="container py-5">

            <div class="alert alert-warning">

                <h4 class="mb-3">

                    Page Under Construction

                </h4>

                <p class="mb-0">

                    This page has not been implemented yet.

                </p>

            </div>

        </div>

    `;

}

/**
 * ------------------------------------------------------------
 * Start Application
 * ------------------------------------------------------------
 */

document.addEventListener(
    "DOMContentLoaded",
    initializeApp
);