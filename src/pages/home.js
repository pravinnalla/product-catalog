/**
 * ============================================================
 * Product Catalog
 * ------------------------------------------------------------
 * File      : home.js
 * Purpose   : Home Page
 * Version   : 1.3.0
 * ============================================================
 */

import { renderNavbar } from "../components/navbar.js";
import { renderFooter } from "../components/footer.js";
import { renderHero } from "../components/hero.js";
import { renderCategories } from "../components/categories.js";
import { renderFeaturedProducts } from "../components/featured-products.js";
import { renderWhyChooseUs } from "../components/why-choose-us.js";
import { renderStatistics } from "../components/statistics.js";
import { renderBrands } from "../components/brands.js";
import { renderGallery } from "../components/gallery.js";
import { renderCallToAction } from "../components/call-to-action.js";

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

    renderWhyChooseUs();

    renderStatistics();

    renderBrands();

    renderGallery();

    renderCallToAction();

    renderFooter();

}