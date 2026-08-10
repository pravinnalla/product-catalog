/**
 * ============================================================
 * Product Catalog
 * ------------------------------------------------------------
 * File      : about.js
 * Purpose   : About Us Page
 * Version   : 1.0.0
 * ============================================================
 */

import { renderNavbar } from "../components/navbar.js";
import { renderFooter } from "../components/footer.js";

/**
 * ------------------------------------------------------------
 * Render About Page
 * ------------------------------------------------------------
 */

export function renderAboutPage(app) {

    if (!app) return;

    app.innerHTML = `

        <div id="navbar"></div>

        <main>

            <section id="about-header"></section>

            <section id="about-company"></section>

            <section id="about-values"></section>

            <section id="about-industries"></section>

            <section id="about-commitment"></section>

            <section id="about-cta"></section>

        </main>

        <div id="footer"></div>

    `;

    renderNavbar();

    renderAboutHeader();

    renderAboutCompany();

    renderAboutValues();

    renderAboutIndustries();

    renderAboutCommitment();

    renderAboutCTA();

    renderFooter();

}

/**
 * ------------------------------------------------------------
 * About Header
 * ------------------------------------------------------------
 */

function renderAboutHeader() {

    const header =
        document.querySelector("#about-header");

    if (!header) return;

    header.innerHTML = `

        <section class="py-5">

            <div class="container">

                <div class="row justify-content-center text-center">

                    <div class="col-lg-8">

                        <span
                            class="badge rounded-pill text-bg-danger
                                   px-3 py-2 mb-3">

                            ABOUT SAFETYMART

                        </span>

                        <h1 class="display-5 fw-bold mb-3">

                            Industrial Safety Solutions
                            You Can Trust

                        </h1>

                        <p class="lead text-secondary mb-0">

                            We provide reliable industrial safety products
                            to help businesses create safer and more
                            protected workplaces.

                        </p>

                    </div>

                </div>

            </div>

        </section>

    `;

}

/**
 * ------------------------------------------------------------
 * Company Introduction
 * ------------------------------------------------------------
 */

function renderAboutCompany() {

    const section =
        document.querySelector("#about-company");

    if (!section) return;

    section.innerHTML = `

        <section class="py-5 bg-body-tertiary">

            <div class="container">

                <div class="row align-items-center g-5">

                    <!-- ===================================== -->
                    <!-- Content -->
                    <!-- ===================================== -->

                    <div class="col-lg-7">

                        <span
                            class="badge rounded-pill text-bg-light
                                   border text-dark px-3 py-2 mb-3">

                            WHO WE ARE

                        </span>

                        <h2 class="fw-bold mb-3">

                            Your Partner in Workplace Safety

                        </h2>

                        <p class="text-secondary">

                            SafetyMart is focused on providing dependable
                            industrial safety equipment for businesses,
                            workplaces and professional applications.

                        </p>

                        <p class="text-secondary">

                            Our product range covers essential personal
                            protective equipment and industrial safety
                            products from trusted brands.

                        </p>

                        <p class="text-secondary mb-0">

                            We aim to make it easier for customers to
                            identify suitable safety products based on
                            their workplace and operational requirements.

                        </p>

                    </div>

                    <!-- ===================================== -->
                    <!-- Highlight -->
                    <!-- ===================================== -->

                    <div class="col-lg-5">

                        <div class="card border-0 shadow-sm">

                            <div class="card-body p-4 p-lg-5">

                                <div
                                    class="d-flex align-items-center mb-4">

                                    <div
                                        class="bg-danger-subtle
                                               text-danger rounded-circle
                                               d-flex align-items-center
                                               justify-content-center"
                                        style="width: 56px; height: 56px;">

                                        <i
                                            class="bi bi-shield-check fs-3">
                                        </i>

                                    </div>

                                    <div class="ms-3">

                                        <h5 class="fw-bold mb-1">

                                            Safety First

                                        </h5>

                                        <p
                                            class="text-secondary small mb-0">

                                            Protection for every workplace

                                        </p>

                                    </div>

                                </div>

                                <p class="text-secondary mb-0">

                                    From personal protective equipment to
                                    industrial safety essentials, we focus
                                    on products that support safer working
                                    environments.

                                </p>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </section>

    `;

}

/**
 * ------------------------------------------------------------
 * Values
 * ------------------------------------------------------------
 */

function renderAboutValues() {

    const section =
        document.querySelector("#about-values");

    if (!section) return;

    section.innerHTML = `

        <section class="py-5">

            <div class="container">

                <div class="text-center mb-5">

                    <span
                        class="badge rounded-pill text-bg-danger
                               px-3 py-2 mb-3">

                        WHY CHOOSE US

                    </span>

                    <h2 class="fw-bold mb-3">

                        Safety Backed by Reliability

                    </h2>

                    <p class="text-secondary mb-0">

                        We focus on quality products, dependable brands
                        and practical customer support.

                    </p>

                </div>

                <div class="row g-4">

                    ${valueCard(
                        "bi-patch-check",
                        "Quality Products",
                        "Reliable safety products selected for industrial and professional applications."
                    )}

                    ${valueCard(
                        "bi-award",
                        "Trusted Brands",
                        "Access safety products from established and recognised brands."
                    )}

                    ${valueCard(
                        "bi-person-check",
                        "Expert Guidance",
                        "We help customers identify products suitable for their workplace requirements."
                    )}

                    ${valueCard(
                        "bi-headset",
                        "Customer Support",
                        "Clear communication and responsive support for product enquiries and quotations."
                    )}

                </div>

            </div>

        </section>

    `;

}

/**
 * ------------------------------------------------------------
 * Value Card
 * ------------------------------------------------------------
 */

function valueCard(icon, title, description) {

    return `

        <div class="col-md-6 col-lg-3">

            <div class="card h-100 border-0 shadow-sm">

                <div class="card-body text-center p-4">

                    <div
                        class="bg-danger-subtle text-danger rounded-circle
                               d-flex align-items-center justify-content-center
                               mx-auto mb-4"
                        style="width: 64px; height: 64px;">

                        <i class="bi ${icon} fs-3"></i>

                    </div>

                    <h5 class="fw-bold mb-3">

                        ${title}

                    </h5>

                    <p class="text-secondary small mb-0">

                        ${description}

                    </p>

                </div>

            </div>

        </div>

    `;

}

/**
 * ------------------------------------------------------------
 * Industries
 * ------------------------------------------------------------
 */

function renderAboutIndustries() {

    const section =
        document.querySelector("#about-industries");

    if (!section) return;

    section.innerHTML = `

        <section class="py-5 bg-body-tertiary">

            <div class="container">

                <div class="text-center mb-5">

                    <span
                        class="badge rounded-pill text-bg-light border
                               text-dark px-3 py-2 mb-3">

                        INDUSTRIES WE SERVE

                    </span>

                    <h2 class="fw-bold mb-3">

                        Safety Solutions Across Industries

                    </h2>

                    <p class="text-secondary mb-0">

                        Our products are suitable for a wide range of
                        industrial and professional environments.

                    </p>

                </div>

                <div class="row g-4">

                    ${industryCard(
                        "bi-building",
                        "Construction",
                        "Safety equipment for construction sites and infrastructure projects."
                    )}

                    ${industryCard(
                        "bi-gear-wide-connected",
                        "Manufacturing",
                        "Personal protective equipment for manufacturing and production environments."
                    )}

                    ${industryCard(
                        "bi-tools",
                        "Engineering",
                        "Industrial safety products for engineering and technical workplaces."
                    )}

                    ${industryCard(
                        "bi-buildings",
                        "Infrastructure",
                        "Safety solutions for infrastructure, maintenance and field operations."
                    )}

                </div>

            </div>

        </section>

    `;

}

/**
 * ------------------------------------------------------------
 * Industry Card
 * ------------------------------------------------------------
 */

function industryCard(icon, title, description) {

    return `

        <div class="col-md-6 col-lg-3">

            <div class="d-flex align-items-start">

                <div
                    class="flex-shrink-0 bg-white text-danger rounded-3
                           shadow-sm d-flex align-items-center
                           justify-content-center"
                    style="width: 52px; height: 52px;">

                    <i class="bi ${icon} fs-4"></i>

                </div>

                <div class="ms-3">

                    <h5 class="fw-semibold mb-2">

                        ${title}

                    </h5>

                    <p class="text-secondary small mb-0">

                        ${description}

                    </p>

                </div>

            </div>

        </div>

    `;

}

/**
 * ------------------------------------------------------------
 * Commitment
 * ------------------------------------------------------------
 */

function renderAboutCommitment() {

    const section =
        document.querySelector("#about-commitment");

    if (!section) return;

    section.innerHTML = `

        <section class="py-5">

            <div class="container">

                <div class="row justify-content-center">

                    <div class="col-lg-9">

                        <div
                            class="card border-0 shadow-sm">

                            <div class="card-body text-center p-4 p-lg-5">

                                <i
                                    class="bi bi-shield-fill-check
                                           text-danger display-5 mb-4">
                                </i>

                                <h2 class="fw-bold mb-3">

                                    Our Commitment to Safety

                                </h2>

                                <p class="text-secondary mb-0">

                                    We believe that the right safety
                                    equipment can make a meaningful
                                    difference in protecting people at
                                    work. Our commitment is to provide
                                    dependable products and helpful
                                    information so customers can make
                                    informed safety decisions.

                                </p>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </section>

    `;

}

/**
 * ------------------------------------------------------------
 * Call To Action
 * ------------------------------------------------------------
 */

function renderAboutCTA() {

    const section =
        document.querySelector("#about-cta");

    if (!section) return;

    section.innerHTML = `

        <section class="py-5">

            <div class="container">

                <div
                    class="bg-danger rounded-4 p-4 p-lg-5
                           text-center text-white">

                    <h2 class="fw-bold mb-3">

                        Looking for the Right Safety Products?

                    </h2>

                    <p class="mb-4 text-white-50">

                        Explore our product range or contact us
                        for assistance with your requirements.

                    </p>

                    <div
                        class="d-flex flex-column flex-sm-row
                               justify-content-center gap-3">

                        <a
                            href="/products.html"
                            class="btn btn-light">

                            <i class="bi bi-grid-3x3-gap me-2"></i>

                            Explore Products

                        </a>

                        <a
                            href="/contact.html"
                            class="btn btn-outline-light">

                            <i class="bi bi-envelope-paper me-2"></i>

                            Request Quote

                        </a>

                    </div>

                </div>

            </div>

        </section>

    `;

}