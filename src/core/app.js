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
import { renderContactPage } from "../pages/contact.js";
import { renderAboutPage } from "../pages/about.js";
import { loadCatalogue } from "../services/product.service.js";
import { renderNavbar } from "../components/navbar.js";
import { renderFooter } from "../components/footer.js";
import { installImageFallbacks } from "../utils/paths.js";

/**
 * ------------------------------------------------------------
 * Application Initializer
 * ------------------------------------------------------------
 */

async function initializeApp() {

    const app = document.querySelector("#app");

    if (!app) {

        console.error("Application root (#app) not found.");

        return;

    }

    const currentPage = getCurrentPage();

    installImageFallbacks(app);

    if (["index", "products", "about"].includes(currentPage)) {
        renderCatalogueLoading(app);
        try {
            await loadCatalogue();
        } catch {
            renderCatalogueFailure(app, currentPage);
            return;
        }
    }

    switch (currentPage) {

        case "index":

            await renderHomePage(app);

            break;

        case "products":

            await renderProductsPage(app);

            break;

        case "contact":

            await renderContactPage(app);

            break;

        case "about":

            await renderAboutPage(app);

            break;

        default:

            renderNotImplemented(app);

            break;

    }

}

function renderCatalogueLoading(app) {
    app.innerHTML = `<main class="container py-5"><div class="d-flex justify-content-center align-items-center gap-3 py-5" role="status"><span class="spinner-border text-danger" aria-hidden="true"></span><span>Loading catalogue…</span></div></main>`;
}

function renderCatalogueFailure(app, currentPage) {
    app.innerHTML = `<div id="navbar"></div><main class="container py-5"><div class="alert alert-light border shadow-sm text-center py-5"><i class="bi bi-exclamation-circle text-danger fs-2"></i><h1 class="h4 mt-3">Catalogue information is temporarily unavailable.</h1><p class="text-secondary">Please check your connection and try again.</p><button id="catalogue-retry" class="btn btn-danger">Retry</button></div></main><div id="footer"></div>`;
    renderNavbar(); renderFooter();
    app.querySelector("#catalogue-retry").addEventListener("click", async () => {
        renderCatalogueLoading(app);
        try { await loadCatalogue({ force: true }); await renderPage(currentPage, app); }
        catch { renderCatalogueFailure(app, currentPage); }
    });
}

async function renderPage(page, app) {
    if (page === "index") return renderHomePage(app);
    if (page === "products") return renderProductsPage(app);
    if (page === "about") return renderAboutPage(app);
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
