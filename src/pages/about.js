/**
 * ============================================================
 * Laxmikant Traders
 * ------------------------------------------------------------
 * File      : about.js
 * Purpose   : About Us Page
 * Version   : 2.1.0
 * ============================================================
 */

import { renderNavbar } from "../components/navbar.js";
import { renderFooter } from "../components/footer.js";
import { pageUrl } from "../utils/paths.js";


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

                            ABOUT LAXMIKANT TRADERS

                        </span>

                        <h1 class="display-5 fw-bold mb-3">

                            Industrial Safety Products
                            for Workplace Protection

                        </h1>

                        <p class="lead text-secondary mb-0">

                            Laxmikant Traders supplies industrial
                            safety products for workplace protection,
                            fire safety and personal safety requirements.

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

                            Your Partner in Industrial Safety

                        </h2>

                        <p class="text-secondary">

                            Laxmikant Traders deals in industrial
                            safety products for workplaces,
                            businesses and industrial applications.

                        </p>

                        <p class="text-secondary">

                            Our product range includes fire safety
                            equipment, personal protective equipment,
                            safety gloves and other industrial safety
                            products.

                        </p>

                        <p class="text-secondary mb-0">

                            We help customers identify suitable
                            products based on their workplace,
                            operational and safety requirements.

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

                                            Products for workplace protection

                                        </p>

                                    </div>

                                </div>

                                <p class="text-secondary mb-0">

                                    We provide products covering fire
                                    safety, personal protection and
                                    other workplace safety requirements.

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

                        Focused on Your Safety Requirements

                    </h2>

                    <p class="text-secondary mb-0">

                        We focus on suitable products, dependable
                        suppliers and responsive customer support.

                    </p>

                </div>

                <div class="row g-4">

                    ${valueCard(
                        "bi-patch-check",
                        "Suitable Products",
                        "Industrial safety products for workplace and operational requirements."
                    )}

                    ${valueCard(
                        "bi-shield-check",
                        "Fire Safety",
                        "Fire safety equipment and related products for workplace protection."
                    )}

                    ${valueCard(
                        "bi-hand-index-thumb",
                        "Hand Protection",
                        "Safety gloves and hand protection products for industrial applications."
                    )}

                    ${valueCard(
                        "bi-headset",
                        "Customer Support",
                        "Support for product enquiries, quotations and safety product requirements."
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
 * Product Areas
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

                        PRODUCT AREAS

                    </span>

                    <h2 class="fw-bold mb-3">

                        Industrial Safety Product Areas

                    </h2>

                    <p class="text-secondary mb-0">

                        Our product range covers important areas of
                        industrial and workplace safety.

                    </p>

                </div>

                <div class="row g-4">

                    ${industryCard(
                        "bi-fire",
                        "Fire Safety",
                        "Fire extinguishers and other fire safety equipment and accessories."
                    )}

                    ${industryCard(
                        "bi-hand-index-thumb",
                        "Hand Protection",
                        "Safety gloves and related products for industrial hand protection."
                    )}

                    ${industryCard(
                        "bi-shield-check",
                        "Personal Protection",
                        "Personal protective equipment for workplace safety requirements."
                    )}

                    ${industryCard(
                        "bi-tools",
                        "Industrial Safety",
                        "Safety products and equipment for industrial and workplace applications."
                    )}

                </div>

            </div>

        </section>

    `;

}


/**
 * ------------------------------------------------------------
 * Product Area Card
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

                            <div
                                class="
                                    card-body
                                    text-center
                                    p-4
                                    p-lg-5
                                ">

                                <i
                                    class="
                                        bi
                                        bi-shield-fill-check
                                        text-danger
                                        display-5
                                        mb-4
                                    ">
                                </i>

                                <h2 class="fw-bold mb-3">

                                    Our Commitment to Safety

                                </h2>

                                <p class="text-secondary mb-0">

                                    We are committed to providing
                                    suitable industrial safety products
                                    and responsive support to help
                                    customers meet their workplace
                                    safety requirements.

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
                    class="
                        bg-danger
                        rounded-4
                        p-4
                        p-lg-5
                        text-center
                        text-white
                    ">

                    <h2 class="fw-bold mb-3">

                        Looking for Industrial Safety Products?

                    </h2>

                    <p class="lead mb-0 text-white">

                        Explore our product range or contact
                        Laxmikant Traders for your safety
                        product requirements.

                    </p>

                    <div
                        class="
                            d-flex
                            flex-column
                            flex-sm-row
                            justify-content-center
                            gap-3
                        ">

                        <a
                            href="${pageUrl("products.html")}"
                            class="btn btn-light">

                            <i
                                class="
                                    bi
                                    bi-grid-3x3-gap
                                    me-2
                                ">
                            </i>

                            Explore Products

                        </a>

                        <a
                            href="${pageUrl("contact.html")}"
                            class="btn btn-outline-light">

                            <i
                                class="
                                    bi
                                    bi-envelope-paper
                                    me-2
                                ">
                            </i>

                            Request Quote

                        </a>

                    </div>

                </div>

            </div>

        </section>

    `;

}
