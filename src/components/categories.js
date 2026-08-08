/**
 * ============================================================
 * Product Catalog
 * ------------------------------------------------------------
 * File      : categories.js
 * Purpose   : Product Categories Section
 * Version   : 1.2.0
 * ============================================================
 */

export function renderCategories() {

    const categories = document.querySelector("#categories");

    if (!categories) return;

    categories.innerHTML = `

<section class="py-5">

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

            ${categoryCard(
                "bi-shield-check",
                "Safety Helmets",
                "Industrial head protection solutions.",
                "12+ Products"
            )}

            ${categoryCard(
                "bi-hand-index-thumb",
                "Safety Gloves",
                "Reliable hand protection for every task.",
                "35+ Products"
            )}

            ${categoryCard(
                "bi-person-walking",
                "Safety Footwear",
                "Comfortable footwear with maximum protection.",
                "18+ Products"
            )}

            ${categoryCard(
                "bi-fire",
                "Fire Safety",
                "Essential fire prevention and protection products.",
                "22+ Products"
            )}

            ${categoryCard(
                "bi-mask",
                "Respiratory Protection",
                "Masks and respirators for clean breathing.",
                "15+ Products"
            )}

            ${categoryCard(
                "bi-eyeglasses",
                "Eye Protection",
                "Protective eyewear for industrial environments.",
                "20+ Products"
            )}

            ${categoryCard(
                "bi-person-badge",
                "Protective Clothing",
                "High-quality workwear for enhanced safety.",
                "16+ Products"
            )}

            ${categoryCard(
                "bi-life-preserver",
                "Fall Protection",
                "Safety harnesses and fall arrest systems.",
                "10+ Products"
            )}

        </div>

    </div>

</section>

`;

}

/**
 * ------------------------------------------------------------
 * Category Card
 * ------------------------------------------------------------
 */

function categoryCard(icon, title, description, count) {

    return `

<div class="col-lg-3 col-md-6">

    <div class="card h-100 border-0 shadow-sm">

        <div class="card-body d-flex flex-column text-center p-4">

            <i class="bi ${icon} display-4 text-danger mb-3"></i>

            <h5 class="fw-semibold mb-3">

                ${title}

            </h5>

            <p class="text-secondary small mb-3">

                ${description}

            </p>

            <p class="fw-semibold text-dark mb-4">

                ${count}

            </p>

            <div class="mt-auto">

                <a
                    href="/products.html"
                    class="btn btn-outline-danger w-100">

                    View Products

                </a>

            </div>

        </div>

    </div>

</div>

`;

}