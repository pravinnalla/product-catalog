/**
 * ============================================================
 * Product Catalog
 * ------------------------------------------------------------
 * File      : products.js
 * Purpose   : Products Page
 * Version   : 1.6.0
 * ============================================================
 */

import { renderNavbar } from "../components/navbar.js";
import { renderFooter } from "../components/footer.js";
import { productCard } from "../components/product-card.js";
import { getProducts } from "../services/product.service.js";

/**
 * ------------------------------------------------------------
 * Product Data
 * ------------------------------------------------------------
 */

const products = getProducts();

/**
 * ------------------------------------------------------------
 * Product Categories
 * ------------------------------------------------------------
 *
 * Only two main categories are required on the Products page.
 *
 * ------------------------------------------------------------
 */

const PRODUCT_CATEGORIES = [
    {
        id: "fire-extinguishers",
        name: "Fire Extinguishers"
    },
    {
        id: "safety-equipment",
        name: "Safety Equipment"
    }
];

/**
 * ------------------------------------------------------------
 * Catalogue Settings
 * ------------------------------------------------------------
 */

const PRODUCTS_PER_PAGE = 6;

/**
 * ------------------------------------------------------------
 * Current Catalogue State
 * ------------------------------------------------------------
 */

let selectedCategory = "All";

let searchTerm =
    getInitialSearchTerm();

let currentPage = 1;

/**
 * ------------------------------------------------------------
 * Read Search Parameter From URL
 * ------------------------------------------------------------
 */

function getInitialSearchTerm() {

    const params =
        new URLSearchParams(
            window.location.search
        );

    return (
        params.get("search") ||
        ""
    ).trim().toLowerCase();

}

/**
 * ------------------------------------------------------------
 * Render Products Page
 * ------------------------------------------------------------
 */

export function renderProductsPage(app) {

    app.innerHTML = `

        <div id="navbar"></div>

        <main>

            <section id="products-header"></section>

            <section id="products-catalogue"></section>

        </main>

        <div id="footer"></div>

    `;

    renderNavbar();

    renderProductsHeader();

    renderProductsCatalogue();

    renderFooter();

}

/**
 * ------------------------------------------------------------
 * Products Page Header
 * ------------------------------------------------------------
 */

function renderProductsHeader() {

    const header =
        document.querySelector("#products-header");

    if (!header) return;

    header.innerHTML = `

        <section class="py-5 bg-body-tertiary">

            <div class="container">

                <div class="row justify-content-center text-center">

                    <div class="col-lg-8">

                        <span
                            class="badge rounded-pill text-bg-danger px-3 py-2 mb-3">

                            OUR PRODUCTS

                        </span>

                        <h1 class="display-5 fw-bold mb-3">

                            Industrial Safety Products

                        </h1>

                        <p class="lead text-secondary mb-0">

                            Explore our range of quality safety products
                            designed to protect people across industries
                            and workplaces.

                        </p>

                    </div>

                </div>

            </div>

        </section>

    `;

}

/**
 * ------------------------------------------------------------
 * Products Catalogue
 * ------------------------------------------------------------
 */

function renderProductsCatalogue() {

    const catalogue =
        document.querySelector("#products-catalogue");

    if (!catalogue) return;

    catalogue.innerHTML = `

        <section class="py-5">

            <div class="container">

                <div class="row g-4">

                    <!-- ========================================= -->
                    <!-- Category Sidebar -->
                    <!-- ========================================= -->

                    <aside class="col-lg-3">

                        <div class="card border-0 shadow-sm">

                            <div class="card-body">

                                <div
                                    class="d-flex justify-content-between
                                           align-items-center mb-4">

                                    <h5 class="fw-bold mb-0">

                                        Product Categories

                                    </h5>

                                    <button
                                        type="button"
                                        id="clearCategory"
                                        class="btn btn-sm
                                               btn-outline-secondary">

                                        Clear

                                    </button>

                                </div>

                                <div
                                    id="categoryList"
                                    class="list-group list-group-flush">

                                    ${renderCategoryLinks()}

                                </div>

                            </div>

                        </div>

                    </aside>

                    <!-- ========================================= -->
                    <!-- Products Area -->
                    <!-- ========================================= -->

                    <div class="col-lg-9">

                        <!-- Search / Sort -->

                        <div class="row g-3 mb-4">

                            <div class="col-md-8">

                                <div class="input-group">

                                    <span class="input-group-text">

                                        <i class="bi bi-search"></i>

                                    </span>

                                   <input
    id="productSearch"
    type="search"
    class="form-control"
    placeholder="Search products..."
    autocomplete="off"
    value="${searchTerm}">

                                </div>

                            </div>

                            <div class="col-md-4">

                                <select
                                    id="productSort"
                                    class="form-select">

                                    <option
                                        value="default"
                                        selected>

                                        Sort Products

                                    </option>

                                    <option value="name-asc">

                                        Name: A to Z

                                    </option>

                                    <option value="name-desc">

                                        Name: Z to A

                                    </option>

                                    <option value="category">

                                        Category

                                    </option>

                                </select>

                            </div>

                        </div>

                        <!-- ===================================== -->
                        <!-- Results Information -->
                        <!-- ===================================== -->

                        <div
                            class="d-flex justify-content-between
                                   align-items-center mb-4">

                            <p
                                id="productResultCount"
                                class="text-secondary small mb-0">
                            </p>

                            <span
                                id="activeCategory"
                                class="badge text-bg-light
                                       border text-dark d-none">
                            </span>

                        </div>

                        <!-- ===================================== -->
                        <!-- Product Grid -->
                        <!-- ===================================== -->

                        <div
                            id="productGrid"
                            class="row g-4">
                        </div>

                        <!-- ===================================== -->
                        <!-- No Results -->
                        <!-- ===================================== -->

                        <div
                            id="noProducts"
                            class="d-none text-center py-5">

                            <i
                                class="bi bi-search
                                       display-5 text-secondary">
                            </i>

                            <h4 class="fw-semibold mt-3">

                                No Products Found

                            </h4>

                            <p class="text-secondary mb-0">

                                Try another search term or category.

                            </p>

                        </div>

                        <!-- ===================================== -->
                        <!-- Pagination -->
                        <!-- ===================================== -->

                        <nav
                            id="productPagination"
                            class="mt-5"
                            aria-label="Product pagination">
                        </nav>

                    </div>

                </div>

            </div>

        </section>

    `;

    initializeProductControls();

    renderProductGrid();

}

/**
 * ------------------------------------------------------------
 * Render Category Links
 * ------------------------------------------------------------
 */

function renderCategoryLinks() {

    let html =
        categoryLink(
            "All",
            "All"
        );

    PRODUCT_CATEGORIES.forEach(category => {

        html +=
            categoryLink(
                category.name,
                category.id
            );

    });

    return html;

}

/**
 * ------------------------------------------------------------
 * Category Link
 * ------------------------------------------------------------
 */

function categoryLink(
    categoryName,
    categoryId
) {

    const active =
        selectedCategory === categoryId;

    return `

        <button
            type="button"
            class="
                list-group-item
                list-group-item-action
                border-0
                px-0
                py-2
                ${active
                    ? "fw-semibold text-danger"
                    : ""}
            "
            data-category="${categoryId}">

            <i
                class="
                    bi
                    ${active
                        ? "bi-check-circle-fill"
                        : "bi-circle"}
                    me-2
                ">
            </i>

            ${categoryName}

        </button>

    `;

}

/**
 * ------------------------------------------------------------
 * Initialize Product Controls
 * ------------------------------------------------------------
 */

function initializeProductControls() {

    const searchInput =
        document.querySelector("#productSearch");

    const categoryList =
        document.querySelector("#categoryList");

    const clearCategory =
        document.querySelector("#clearCategory");

    const sortSelect =
        document.querySelector("#productSort");

    /**
     * --------------------------------------------------------
     * Search
     * --------------------------------------------------------
     */

    searchInput?.addEventListener(
        "input",
        event => {

            searchTerm =
                event.target.value
                    .trim()
                    .toLowerCase();

            currentPage = 1;

            renderProductGrid();

        }
    );

    /**
     * --------------------------------------------------------
     * Category
     * --------------------------------------------------------
     */

    categoryList?.addEventListener(
    "click",
    event => {

        const button =
            event.target.closest(
                "[data-category]"
            );

        if (!button) return;

        selectedCategory =
            button.dataset.category;

        /*
         * Clear the search box whenever a main
         * product category is selected.
         */

        searchTerm = "";

        const searchInput =
            document.querySelector(
                "#productSearch"
            );

        if (searchInput) {

            searchInput.value = "";

        }

        currentPage = 1;

        renderCategoryList();

        renderProductGrid();

    }
);

    /**
     * --------------------------------------------------------
     * Clear Category
     * --------------------------------------------------------
     */

    clearCategory?.addEventListener(
    "click",
    () => {

        selectedCategory = "All";

        searchTerm = "";

        const searchInput =
            document.querySelector(
                "#productSearch"
            );

        if (searchInput) {

            searchInput.value = "";

        }

        currentPage = 1;

        renderCategoryList();

        renderProductGrid();

    }
);

    /**
     * --------------------------------------------------------
     * Sort
     * --------------------------------------------------------
     */

    sortSelect?.addEventListener(
        "change",
        () => {

            currentPage = 1;

            renderProductGrid();

        }
    );

}

/**
 * ------------------------------------------------------------
 * Render Category List
 * ------------------------------------------------------------
 */

function renderCategoryList() {

    const categoryList =
        document.querySelector("#categoryList");

    if (!categoryList) return;

    categoryList.innerHTML =
        renderCategoryLinks();

}

/**
 * ------------------------------------------------------------
 * Convert Text To Comparable Category
 * ------------------------------------------------------------
 *
 * Example:
 *
 * Fire Extinguishers
 * fire-extinguishers
 * FIRE EXTINGUISHERS
 *
 * All become:
 *
 * fire-extinguishers
 *
 * ------------------------------------------------------------
 */

function normalizeCategory(value) {

    return String(value || "")
        .trim()
        .toLowerCase()
        .replace(/[_\s]+/g, "-")
        .replace(/[^a-z0-9-]/g, "")
        .replace(/-+/g, "-");

}

/**
 * ------------------------------------------------------------
 * Get Product Name
 * ------------------------------------------------------------
 *
 * Our simplified products.json may use "name".
 *
 * The fallback to "title" keeps compatibility with
 * older product records.
 *
 * ------------------------------------------------------------
 */

function getProductName(product) {

    return (
        product.name ||
        product.title ||
        ""
    );

}

/**
 * ------------------------------------------------------------
 * Filter Products
 * ------------------------------------------------------------
 */

function getFilteredProducts() {

    let filteredProducts =
        [...products];

    /**
     * --------------------------------------------------------
     * Category Filter
     * --------------------------------------------------------
     */

    if (selectedCategory !== "All") {

        const selectedCategoryObject =
            PRODUCT_CATEGORIES.find(
                category =>
                    category.id ===
                    selectedCategory
            );

        const selectedCategoryName =
            selectedCategoryObject
                ? selectedCategoryObject.name
                : selectedCategory;

        const selectedCategoryNormalized =
            normalizeCategory(
                selectedCategoryName
            );

        filteredProducts =
            filteredProducts.filter(
                product => {

                    const productCategory =
                        normalizeCategory(
                            product.category
                        );

                    return (
                        productCategory ===
                        selectedCategoryNormalized
                    );

                }
            );

    }

    /**
     * --------------------------------------------------------
     * Search Filter
     * --------------------------------------------------------
     */

    if (searchTerm) {

        filteredProducts =
            filteredProducts.filter(
                product => {

                    const searchableText = [

    getProductName(product),

    product.category,

    product.subcategory,

    product.description,

    product.brand,

    product.id

]
                        .filter(Boolean)
                        .join(" ")
                        .toLowerCase();

                    return searchableText.includes(
                        searchTerm
                    );

                }
            );

    }

    return filteredProducts;

}

/**
 * ------------------------------------------------------------
 * Sort Products
 * ------------------------------------------------------------
 */

function sortProducts(productList) {

    const sortSelect =
        document.querySelector("#productSort");

    const sortValue =
        sortSelect?.value || "default";

    const sortedProducts =
        [...productList];

    switch (sortValue) {

        case "name-asc":

            sortedProducts.sort(
                (a, b) =>
                    getProductName(a)
                        .localeCompare(
                            getProductName(b)
                        )
            );

            break;

        case "name-desc":

            sortedProducts.sort(
                (a, b) =>
                    getProductName(b)
                        .localeCompare(
                            getProductName(a)
                        )
            );

            break;

        case "category":

            sortedProducts.sort(
                (a, b) =>
                    String(a.category || "")
                        .localeCompare(
                            String(b.category || "")
                        )
            );

            break;

        default:

            break;

    }

    return sortedProducts;

}

/**
 * ------------------------------------------------------------
 * Paginate Products
 * ------------------------------------------------------------
 */

function paginateProducts(productList) {

    const totalProducts =
        productList.length;

    const totalPages =
        Math.ceil(
            totalProducts /
            PRODUCTS_PER_PAGE
        );

    if (
        currentPage > totalPages &&
        totalPages > 0
    ) {

        currentPage =
            totalPages;

    }

    const startIndex =
        (currentPage - 1) *
        PRODUCTS_PER_PAGE;

    const endIndex =
        startIndex +
        PRODUCTS_PER_PAGE;

    return {

        products:
            productList.slice(
                startIndex,
                endIndex
            ),

        totalPages,

        startIndex,

        endIndex

    };

}

/**
 * ------------------------------------------------------------
 * Render Product Grid
 * ------------------------------------------------------------
 */

function renderProductGrid() {

    const productGrid =
        document.querySelector(
            "#productGrid"
        );

    const resultCount =
        document.querySelector(
            "#productResultCount"
        );

    const activeCategory =
        document.querySelector(
            "#activeCategory"
        );

    const noProducts =
        document.querySelector(
            "#noProducts"
        );

    if (!productGrid) return;

    /**
     * Filter
     */

    let filteredProducts =
        getFilteredProducts();

    /**
     * Sort
     */

    filteredProducts =
        sortProducts(
            filteredProducts
        );

    /**
     * Pagination
     */

    const pagination =
        paginateProducts(
            filteredProducts
        );

    const visibleProducts =
        pagination.products;

    /**
     * Result Count
     */

    if (resultCount) {

        if (
            filteredProducts.length === 0
        ) {

            resultCount.textContent =
                "Showing 0 products";

        } else {

            const firstProduct =
                pagination.startIndex + 1;

            const lastProduct =
                Math.min(
                    pagination.endIndex,
                    filteredProducts.length
                );

            resultCount.textContent =
                `Showing ${firstProduct}-${lastProduct} of ${filteredProducts.length} products`;

        }

    }

    /**
     * Active Category
     */

    if (activeCategory) {

        if (selectedCategory === "All") {

            activeCategory.classList.add(
                "d-none"
            );

        } else {

            activeCategory.classList.remove(
                "d-none"
            );

            const selectedCategoryObject =
                PRODUCT_CATEGORIES.find(
                    category =>
                        category.id ===
                        selectedCategory
                );

            activeCategory.textContent =
                selectedCategoryObject
                    ? selectedCategoryObject.name
                    : selectedCategory;

        }

    }

    /**
     * No Results
     */

    if (!filteredProducts.length) {

        productGrid.innerHTML = "";

        noProducts?.classList.remove(
            "d-none"
        );

        renderPagination(0);

        return;

    }

    noProducts?.classList.add(
        "d-none"
    );

    /**
     * Render Product Cards
     * --------------------------------------------------------
     * 3 cards per row on desktop.
     * 2 cards per row on medium screens.
     * 1 card per row on small screens.
     * --------------------------------------------------------
     */

    productGrid.innerHTML =
        visibleProducts
            .map(
                product => `

                    <div class="col-md-6 col-xl-4">

                        ${productCard(product)}

                    </div>

                `
            )
            .join("");

    /**
     * Pagination
     */

    renderPagination(
        pagination.totalPages
    );

}

/**
 * ------------------------------------------------------------
 * Render Pagination
 * ------------------------------------------------------------
 */

/**
 * ------------------------------------------------------------
 * Render Pagination
 * ------------------------------------------------------------
 */

function renderPagination(totalPages) {

    const pagination =
        document.querySelector(
            "#productPagination"
        );

    if (!pagination) return;

    /**
     * --------------------------------------------------------
     * No Pagination Required
     * --------------------------------------------------------
     */

    if (totalPages <= 1) {

        pagination.innerHTML = "";

        return;

    }

    /**
     * --------------------------------------------------------
     * Build Page Numbers
     * --------------------------------------------------------
     *
     * Example when current page is 20:
     *
     * 1 ... 19 20 21 ... 48
     *
     * This prevents the pagination from becoming too wide.
     *
     * --------------------------------------------------------
     */

    const pages = [];

    pages.push(1);

    if (currentPage > 4) {

        pages.push("ellipsis-start");

    }

    const startPage =
        Math.max(
            2,
            currentPage - 1
        );

    const endPage =
        Math.min(
            totalPages - 1,
            currentPage + 1
        );

    for (
        let page = startPage;
        page <= endPage;
        page++
    ) {

        pages.push(page);

    }

    if (currentPage < totalPages - 3) {

        pages.push("ellipsis-end");

    }

    if (totalPages > 1) {

        pages.push(totalPages);

    }

    /**
     * --------------------------------------------------------
     * Previous Button
     * --------------------------------------------------------
     */

    let paginationItems = `

        <li
            class="page-item
                ${currentPage === 1
                    ? "disabled"
                    : ""}">

            <button
                type="button"
                class="page-link"
                data-page="${currentPage - 1}"
                aria-label="Previous page">

                <i class="bi bi-chevron-left"></i>

            </button>

        </li>

    `;

    /**
     * --------------------------------------------------------
     * Page Numbers
     * --------------------------------------------------------
     */

    pages.forEach(page => {

        /**
         * Ellipsis
         */

        if (
            page === "ellipsis-start" ||
            page === "ellipsis-end"
        ) {

            paginationItems += `

                <li
                    class="page-item disabled">

                    <span class="page-link">

                        &hellip;

                    </span>

                </li>

            `;

            return;

        }

        /**
         * Normal Page
         */

        paginationItems += `

            <li
                class="page-item
                    ${page === currentPage
                        ? "active"
                        : ""}">

                <button
                    type="button"
                    class="page-link"
                    data-page="${page}">

                    ${page}

                </button>

            </li>

        `;

    });

    /**
     * --------------------------------------------------------
     * Next Button
     * --------------------------------------------------------
     */

    paginationItems += `

        <li
            class="page-item
                ${currentPage === totalPages
                    ? "disabled"
                    : ""}">

            <button
                type="button"
                class="page-link"
                data-page="${currentPage + 1}"
                aria-label="Next page">

                <i class="bi bi-chevron-right"></i>

            </button>

        </li>

    `;

    /**
     * --------------------------------------------------------
     * Pagination HTML
     * --------------------------------------------------------
     */

    pagination.innerHTML = `

        <ul
            class="
                pagination
                justify-content-center
                flex-wrap
                mb-0
            ">

            ${paginationItems}

        </ul>

    `;

    /**
     * --------------------------------------------------------
     * Pagination Events
     * --------------------------------------------------------
     */

    pagination
        .querySelectorAll(
            "[data-page]"
        )
        .forEach(
            button => {

                button.addEventListener(
                    "click",
                    () => {

                        const page =
                            Number(
                                button.dataset.page
                            );

                        if (
                            page < 1 ||
                            page > totalPages ||
                            page === currentPage
                        ) {

                            return;

                        }

                        currentPage =
                            page;

                        renderProductGrid();

                        scrollToProductGrid();

                    }
                );

            }
        );

}
/**
 * ------------------------------------------------------------
 * Scroll To Product Grid
 * ------------------------------------------------------------
 */

function scrollToProductGrid() {

    const productGrid =
        document.querySelector(
            "#productGrid"
        );

    if (!productGrid) return;

    const position =
        productGrid
            .getBoundingClientRect()
            .top +
        window.scrollY -
        120;

    window.scrollTo({

        top: position,

        behavior: "smooth"

    });

}