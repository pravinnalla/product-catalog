/**
 * ============================================================
 * Product Catalog
 * ------------------------------------------------------------
 * File      : featured-products.js
 * Purpose   : Featured Products Section
 * Version   : 1.1.0
 * ============================================================
 */

export function renderFeaturedProducts() {

    const featuredProducts = document.querySelector("#featured-products");

    if (!featuredProducts) return;

    featuredProducts.innerHTML = `

<section class="py-5 bg-body-tertiary">

    <div class="container">

        <!-- ========================================= -->
        <!-- Section Heading -->
        <!-- ========================================= -->

        <div class="text-center mb-5">

            <span class="badge rounded-pill text-bg-danger px-3 py-2 mb-3">

                Featured Products

            </span>

            <h2 class="fw-bold mb-3">

                Our Popular Safety Products

            </h2>

            <p class="text-secondary mb-0">

                Explore some of our most popular industrial safety products.

            </p>

        </div>

        <!-- ========================================= -->
        <!-- Product Grid -->
        <!-- ========================================= -->

        <div class="row g-4">

            ${productCard(
                "https://picsum.photos/600/450?random=101",
                "Safety Helmets",
                "Industrial Safety Helmet",
                "Reliable head protection for industrial and construction environments.",
                "Karam"
            )}

            ${productCard(
                "https://picsum.photos/600/450?random=102",
                "Safety Gloves",
                "Industrial Safety Gloves",
                "Durable hand protection designed for demanding industrial applications.",
                "3M"
            )}

            ${productCard(
                "https://picsum.photos/600/450?random=103",
                "Safety Footwear",
                "Steel Toe Safety Shoes",
                "Protective industrial footwear offering comfort and reliable toe protection.",
                "Allen Cooper"
            )}

            ${productCard(
                "https://picsum.photos/600/450?random=104",
                "Fire Safety",
                "ABC Dry Chemical Fire Extinguisher",
                "Portable fire protection equipment suitable for multiple fire classes.",
                "Safex"
            )}

            ${productCard(
                "https://picsum.photos/600/450?random=105",
                "Eye Protection",
                "Industrial Safety Goggles",
                "Protective eyewear designed for industrial work environments.",
                "Udyogi"
            )}

            ${productCard(
                "https://picsum.photos/600/450?random=106",
                "Respiratory Protection",
                "Particulate Respirator",
                "Respiratory protection for dusty and particulate work environments.",
                "3M"
            )}

            ${productCard(
                "https://picsum.photos/600/450?random=107",
                "Protective Clothing",
                "Industrial Safety Coverall",
                "Protective workwear designed for industrial and engineering applications.",
                "Karam"
            )}

            ${productCard(
                "https://picsum.photos/600/450?random=108",
                "Fall Protection",
                "Full Body Safety Harness",
                "Fall protection equipment for work at height and industrial applications.",
                "Udyogi"
            )}

        </div>

        <!-- ========================================= -->
        <!-- View All Products -->
        <!-- ========================================= -->

        <div class="text-center mt-5">

            <a
                href="/products.html"
                class="btn btn-danger">

                <i class="bi bi-grid-3x3-gap me-2"></i>

                View All Products

            </a>

        </div>

    </div>

</section>

`;

}

/**
 * ------------------------------------------------------------
 * Product Card
 * ------------------------------------------------------------
 */

function productCard(
    image,
    category,
    title,
    description,
    brand
) {

    return `

<div class="col-xl-3 col-lg-4 col-md-6">

    <div class="card h-100 border-0 shadow-sm">

        <!-- ========================================= -->
        <!-- Product Image -->
        <!-- ========================================= -->

        <div class="position-relative">

            <img
                src="${image}"
                class="card-img-top"
                alt="${title}"
                loading="lazy">

            <span
                class="badge text-bg-danger position-absolute top-0 start-0 m-3">

                ${category}

            </span>

        </div>

        <!-- ========================================= -->
        <!-- Product Details -->
        <!-- ========================================= -->

        <div class="card-body d-flex flex-column p-4">

            <h5 class="card-title fw-semibold mb-2">

                ${title}

            </h5>

            <p class="text-secondary small mb-3">

                ${description}

            </p>

            <p class="small mb-4">

                <span class="text-secondary">

                    Brand:

                </span>

                <strong>

                    ${brand}

                </strong>

            </p>

            <!-- ===================================== -->
            <!-- Actions -->
            <!-- ===================================== -->

            <div class="mt-auto">

                <div class="d-grid gap-2">

                    <a
                        href="/product.html"
                        class="btn btn-outline-danger">

                        <i class="bi bi-eye me-2"></i>

                        View Details

                    </a>

                    <a
                        href="/contact.html"
                        class="btn btn-danger">

                        <i class="bi bi-envelope-paper me-2"></i>

                        Request Quote

                    </a>

                </div>

            </div>

        </div>

    </div>

</div>

`;

}