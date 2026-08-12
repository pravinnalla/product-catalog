/**
 * ============================================================
 * Product Catalog
 * ------------------------------------------------------------
 * File      : categories.js
 * Purpose   : Product Categories Section
 * Version   : 2.0.0
 * ============================================================
 */

import products from "../data/products.json";
import { pageUrl } from "../utils/paths.js";

/**
 * ------------------------------------------------------------
 * Configuration
 * ------------------------------------------------------------
 */

const MAIN_CATEGORIES = [
    "Fire Extinguishers",
    "Safety Equipment"
];

const TOP_SUBCATEGORIES = 4;

/**
 * ------------------------------------------------------------
 * Render Product Categories
 * ------------------------------------------------------------
 */

export function renderCategories() {

    const categories =
        document.querySelector("#categories");

    if (!categories) return;

    const fireCategories =
        getTopSubcategories(
            "Fire Extinguishers"
        );

    const safetyCategories =
        getTopSubcategories(
            "Safety Equipment"
        );

    const selectedCategories = [
        ...fireCategories,
        ...safetyCategories
    ];

    categories.innerHTML = `

        <div class="container">

            <!-- ========================================= -->
            <!-- Section Heading -->
            <!-- ========================================= -->

            <div class="text-center mb-5">

                <h2 class="fw-bold">

                    Product Categories

                </h2>

                <p class="text-secondary mb-0">

                    Explore our complete range of industrial safety products.

                </p>

            </div>

            <!-- ========================================= -->
            <!-- Categories Grid -->
            <!-- ========================================= -->

            <div class="row g-4">

                ${selectedCategories
                    .map(
                        category =>
                            categoryCard(
                                category
                            )
                    )
                    .join("")}

                ${viewAllCategoriesCard()}

            </div>

        </div>

    `;

}

/**
 * ------------------------------------------------------------
 * Get Top Subcategories
 * ------------------------------------------------------------
 *
 * Groups products by subcategory and sorts them by
 * product count in descending order.
 *
 * ------------------------------------------------------------
 */

function getTopSubcategories(
    mainCategory
) {

    const counts = {};

    products
        .filter(
            product =>
                product.category ===
                mainCategory
        )
        .forEach(
            product => {

                const subcategory =
                    String(
                        product.subcategory || ""
                    ).trim();

                if (!subcategory) return;

                if (!counts[subcategory]) {

                    counts[subcategory] = 0;

                }

                counts[subcategory]++;

            }
        );

    return Object.entries(counts)

        .sort(
            (a, b) =>
                b[1] - a[1]
        )

        .slice(
            0,
            TOP_SUBCATEGORIES
        )

        .map(
            ([name, count]) => ({

                category:
                    mainCategory,

                name,

                count

            })
        );

}

/**
 * ------------------------------------------------------------
 * Category Card
 * ------------------------------------------------------------
 */

function categoryCard(
    category
) {

    const searchValue =
        encodeURIComponent(
            category.name
        );

    const icon =
        getCategoryIcon(
            category.name
        );

    return `

        <div class="col-md-6 col-lg-3">

            <div class="card h-100 border-0 shadow-sm">

                <div
                    class="
                        card-body
                        d-flex
                        flex-column
                        text-center
                        p-4
                    ">

                    <i
                        class="
                            bi
                            ${icon}
                            display-4
                            text-danger
                            mb-3
                        ">
                    </i>

                    <h5
                        class="
                            fw-semibold
                            mb-3
                        ">

                        ${category.name}

                    </h5>

                    <p
                        class="
                            text-secondary
                            small
                            mb-3
                        ">

                        ${category.category}

                    </p>

                    <p
                        class="
                            fw-semibold
                            text-dark
                            mb-4
                        ">

                        ${category.count}+ Products

                    </p>

                    <div class="mt-auto">

                        <a
                          href="${pageUrl("products.html")}?search=${searchValue}"
                            class="
                                btn
                                btn-outline-danger
                                w-100
                            ">

                            View Products

                        </a>

                    </div>

                </div>

            </div>

        </div>

    `;

}

/**
 * ------------------------------------------------------------
 * View All Categories Card
 * ------------------------------------------------------------
 */

function viewAllCategoriesCard() {

    return `

        <div class="col-md-6 col-lg-3">

            <div
                class="
                    card
                    h-100
                    border-0
                    shadow-sm
                ">

                <div
                    class="
                        card-body
                        d-flex
                        flex-column
                        text-center
                        p-4
                    ">

                    <i
                        class="
                            bi
                            bi-grid-3x3-gap
                            display-4
                            text-danger
                            mb-3
                        ">
                    </i>

                    <h5
                        class="
                            fw-semibold
                            mb-3
                        ">

                        View All Product Categories

                    </h5>

                    <p
                        class="
                            text-secondary
                            small
                            mb-4
                        ">

                        Explore all available product categories.

                    </p>

                    <div class="mt-auto">

                        <a
                            href="${pageUrl("products.html")}"
                            class="
                                btn
                                btn-danger
                                w-100
                            ">

                            View All Products

                        </a>

                    </div>

                </div>

            </div>

        </div>

    `;

}

/**
 * ------------------------------------------------------------
 * Category Icon
 * ------------------------------------------------------------
 */

function getCategoryIcon(
    subcategory
) {

    const value =
        subcategory
            .toLowerCase();

    if (
        value.includes("hand")
        ||
        value.includes("glove")
    ) {

        return "bi-hand-index-thumb";

    }

    if (
        value.includes("arm")
        ||
        value.includes("sleeve")
    ) {

        return "bi-person-arms-up";

    }

    if (
        value.includes("body")
        ||
        value.includes("wear")
        ||
        value.includes("clothing")
    ) {

        return "bi-person-badge";

    }

    if (
        value.includes("hydrant")
        ||
        value.includes("fire")
    ) {

        return "bi-fire";

    }

    if (
        value.includes("co2")
    ) {

        return "bi-fire";

    }

    if (
        value.includes("abc")
    ) {

        return "bi-fire";

    }

    if (
        value.includes("dcp")
    ) {

        return "bi-fire";

    }

    return "bi-shield-check";

}
