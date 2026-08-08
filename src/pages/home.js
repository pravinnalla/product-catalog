/**
 * ============================================================
 * Product Catalog
 * ------------------------------------------------------------
 * File      : home.js
 * Purpose   : Home Page
 * Version   : 1.2.0
 * ============================================================
 */

import { renderNavbar } from "../components/navbar.js";
import { renderFooter } from "../components/footer.js";
import { renderHero } from "../components/hero.js";
import { renderCategories } from "../components/categories.js";
import { renderFeaturedProducts } from "../components/featured-products.js";

/**
 * ------------------------------------------------------------
 * Render Home Page
 * ------------------------------------------------------------
 */

export function renderHomePage(app) {

    app.innerHTML = `

        <div id="navbar"></div>

        <main>

            <section id="hero"></section>

            <section id="categories"></section>

            <section id="featured-products"></section>

            <section id="why-choose-us"></section>

            <section id="statistics"></section>

            <section id="brands"></section>

            <section id="gallery"></section>

            <section id="call-to-action"></section>

        </main>

        <div id="footer"></div>

    `;

    renderNavbar();

    renderHero();

    renderCategories();

    renderFeaturedProducts();

    renderPlaceholders();

    renderFooter();

}

/**
 * ------------------------------------------------------------
 * Temporary Placeholders
 * ------------------------------------------------------------
 */

function renderPlaceholders() {

    createPlaceholder(
        "why-choose-us",
        "Why Choose Us"
    );

    createPlaceholder(
        "statistics",
        "Statistics"
    );

    createPlaceholder(
        "brands",
        "Brands"
    );

    createPlaceholder(
        "gallery",
        "Gallery"
    );

    createPlaceholder(
        "call-to-action",
        "Call To Action"
    );

}

/**
 * ------------------------------------------------------------
 * Placeholder Generator
 * ------------------------------------------------------------
 */

function createPlaceholder(id, title) {

    const element = document.getElementById(id);

    if (!element) return;

    element.innerHTML = `

        <section class="container py-5">

            <div class="card border-secondary-subtle shadow-sm">

                <div class="card-body text-center py-5">

                    <h2 class="mb-3">

                        ${title}

                    </h2>

                    <p class="text-secondary mb-0">

                        This section will be implemented in the next milestone.

                    </p>

                </div>

            </div>

        </section>

    `;

}