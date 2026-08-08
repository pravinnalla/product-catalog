/**
 * ============================================================
 * Product Catalog
 * ------------------------------------------------------------
 * File      : product.js
 * Purpose   : Product Detail Page
 * Version   : 1.1.0
 * ============================================================
 */

import { renderNavbar } from "../components/navbar.js";
import { renderFooter } from "../components/footer.js";
import {
    getProductById,
    getProductsByCategory
} from "../services/product.service.js";

/**
 * ------------------------------------------------------------
 * Render Product Detail Page
 * ------------------------------------------------------------
 */

export function renderProductPage(app) {

    const productId = getProductId();

    const product = getProductById(productId);

    app.innerHTML = `

        <div id="navbar"></div>

        <main>

            <section id="product-detail"></section>

        </main>

        <div id="footer"></div>

    `;

    renderNavbar();

    renderProductDetail(product);

    renderFooter();

}

/**
 * ------------------------------------------------------------
 * Get Product ID From URL
 * ------------------------------------------------------------
 */

function getProductId() {

    const params =
        new URLSearchParams(
            window.location.search
        );

    return params.get("id");

}

/**
 * ------------------------------------------------------------
 * Render Product Detail
 * ------------------------------------------------------------
 */

function renderProductDetail(product) {

    const detail =
        document.querySelector("#product-detail");

    if (!detail) return;

    if (!product) {

        renderProductNotFound(detail);

        return;

    }

    detail.innerHTML = `

<section class="py-5">

    <div class="container">

        <!-- ========================================= -->
        <!-- Breadcrumb -->
        <!-- ========================================= -->

        <nav
            aria-label="breadcrumb"
            class="mb-4">

            <ol class="breadcrumb">

                <li class="breadcrumb-item">

                    <a
                        href="/index.html"
                        class="text-decoration-none">

                        Home

                    </a>

                </li>

                <li class="breadcrumb-item">

                    <a
                        href="/products.html"
                        class="text-decoration-none">

                        Products

                    </a>

                </li>

                <li
                    class="breadcrumb-item active"
                    aria-current="page">

                    ${product.title}

                </li>

            </ol>

        </nav>

        <!-- ========================================= -->
        <!-- Main Product Information -->
        <!-- ========================================= -->

        <div class="row g-5 align-items-start">

            <!-- ===================================== -->
            <!-- Product Image -->
            <!-- ===================================== -->

            <div class="col-lg-6">

                <div class="card border-0 shadow-sm overflow-hidden">

                    <div class="position-relative bg-body-tertiary">

                        <img
                            src="${product.image}"
                            class="img-fluid w-100"
                            alt="${product.title}">

                        <span
                            class="badge text-bg-danger position-absolute top-0 start-0 m-3 px-3 py-2">

                            ${product.category}

                        </span>

                    </div>

                </div>

            </div>

            <!-- ===================================== -->
            <!-- Product Information -->
            <!-- ===================================== -->

            <div class="col-lg-6">

                <span class="badge rounded-pill text-bg-light border text-dark px-3 py-2 mb-3">

                    ${product.category}

                </span>

                <h1 class="display-5 fw-bold mb-3">

                    ${product.title}

                </h1>

                <!-- Brand -->

                <div class="mb-4">

                    <span class="text-secondary">

                        Brand:

                    </span>

                    <strong class="ms-2">

                        ${product.brand}

                    </strong>

                </div>

                <p class="lead text-secondary mb-4">

                    ${product.description}

                </p>

                <!-- ================================= -->
                <!-- Product Information -->
                <!-- ================================= -->

                <div class="card border-0 bg-body-tertiary mb-4">

                    <div class="card-body p-4">

                        <h5 class="fw-bold mb-3">

                            Product Information

                        </h5>

                        <div class="row g-3">

                            ${renderSpecifications(
                                product.specifications
                            )}

                        </div>

                    </div>

                </div>

                <!-- ================================= -->
                <!-- Actions -->
                <!-- ================================= -->

                <div class="d-flex flex-wrap gap-3 mb-4">

                    <a
                        href="/contact.html"
                        class="btn btn-danger btn-lg">

                        <i class="bi bi-envelope-paper me-2"></i>

                        Request Quote

                    </a>

                    <a
                        href="/products.html"
                        class="btn btn-outline-dark btn-lg">

                        <i class="bi bi-arrow-left me-2"></i>

                        Back to Products

                    </a>

                </div>

                <!-- ================================= -->
                <!-- Trust Information -->
                <!-- ================================= -->

                <div class="row g-3">

                    <div class="col-sm-4">

                        <div class="d-flex align-items-center">

                            <i class="bi bi-patch-check-fill text-success fs-4 me-2"></i>

                            <small>

                                Genuine Products

                            </small>

                        </div>

                    </div>

                    <div class="col-sm-4">

                        <div class="d-flex align-items-center">

                            <i class="bi bi-shield-check text-primary fs-4 me-2"></i>

                            <small>

                                Quality Assured

                            </small>

                        </div>

                    </div>

                    <div class="col-sm-4">

                        <div class="d-flex align-items-center">

                            <i class="bi bi-headset text-danger fs-4 me-2"></i>

                            <small>

                                Expert Support

                            </small>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

</section>

<!-- =========================================================
     Related Products
========================================================== -->

${renderRelatedProducts(product)}

`;

}

/**
 * ------------------------------------------------------------
 * Render Specifications
 * ------------------------------------------------------------
 */

function renderSpecifications(specifications) {

    if (!specifications) {

        return `

            <div class="col-12">

                <p class="text-secondary mb-0">

                    Product specifications will be provided
                    on request.

                </p>

            </div>

        `;

    }

    return Object.entries(specifications)
        .map(
            ([key, value]) => `

                <div class="col-sm-6">

                    <div class="border rounded p-3 h-100 bg-white">

                        <small class="text-secondary d-block mb-1">

                            ${formatSpecificationName(key)}

                        </small>

                        <span class="fw-semibold">

                            ${value}

                        </span>

                    </div>

                </div>

            `
        )
        .join("");

}

/**
 * ------------------------------------------------------------
 * Format Specification Name
 * ------------------------------------------------------------
 */

function formatSpecificationName(name) {

    return name
        .replace(/([A-Z])/g, " $1")
        .replace(/^./, letter => letter.toUpperCase());

}

/**
 * ------------------------------------------------------------
 * Render Related Products
 * ------------------------------------------------------------
 */

function renderRelatedProducts(product) {

    const relatedProducts =
        getProductsByCategory(product.category)
            .filter(
                item => item.id !== product.id
            )
            .slice(0, 3);

    if (!relatedProducts.length) {

        return "";

    }

    return `

<section class="py-5 bg-body-tertiary">

    <div class="container">

        <div class="text-center mb-5">

            <span class="badge rounded-pill text-bg-danger px-3 py-2 mb-3">

                YOU MAY ALSO LIKE

            </span>

            <h2 class="fw-bold mb-3">

                Related Products

            </h2>

            <p class="text-secondary mb-0">

                Explore more products from the same category.

            </p>

        </div>

        <div class="row g-4">

            ${relatedProducts
                .map(product => relatedProductCard(product))
                .join("")}

        </div>

    </div>

</section>

`;

}

/**
 * ------------------------------------------------------------
 * Related Product Card
 * ------------------------------------------------------------
 */

function relatedProductCard(product) {

    return `

<div class="col-md-6 col-lg-4">

    <article class="card h-100 border-0 shadow-sm">

        <div class="position-relative">

            <img
                src="${product.image}"
                class="card-img-top"
                alt="${product.title}"
                loading="lazy">

            <span
                class="badge text-bg-danger position-absolute top-0 start-0 m-3">

                ${product.category}

            </span>

        </div>

        <div class="card-body d-flex flex-column p-4">

            <h5 class="fw-semibold mb-2">

                ${product.title}

            </h5>

            <p class="text-secondary small mb-3">

                ${product.description}

            </p>

            <p class="small mb-4">

                <span class="text-secondary">

                    Brand:

                </span>

                <strong>

                    ${product.brand}

                </strong>

            </p>

            <div class="mt-auto">

                <a
                    href="/product.html?id=${product.id}"
                    class="btn btn-outline-danger w-100">

                    <i class="bi bi-eye me-2"></i>

                    View Details

                </a>

            </div>

        </div>

    </article>

</div>

`;

}

/**
 * ------------------------------------------------------------
 * Product Not Found
 * ------------------------------------------------------------
 */

function renderProductNotFound(detail) {

    detail.innerHTML = `

<section class="py-5">

    <div class="container">

        <div class="row justify-content-center">

            <div class="col-lg-7">

                <div class="text-center py-5">

                    <i
                        class="bi bi-box-seam display-1 text-secondary">
                    </i>

                    <h1 class="fw-bold mt-4 mb-3">

                        Product Not Found

                    </h1>

                    <p class="text-secondary mb-4">

                        The product you are looking for
                        could not be found.

                    </p>

                    <a
                        href="/products.html"
                        class="btn btn-danger">

                        <i class="bi bi-grid-3x3-gap me-2"></i>

                        Browse Products

                    </a>

                </div>

            </div>

        </div>

    </div>

</section>

`;

}