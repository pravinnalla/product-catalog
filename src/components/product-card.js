/**
 * ============================================================
 * Product Catalog
 * ------------------------------------------------------------
 * File      : product-card.js
 * Purpose   : Reusable Product Card Component
 * Version   : 1.1.0
 * ============================================================
 */

export function productCard(product) {

    return `

<div class="col-md-6 col-xl-4">

    <article class="card h-100 border-0 shadow-sm">

        <!-- ========================================= -->
        <!-- Product Image -->
        <!-- ========================================= -->

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

        <!-- ========================================= -->
        <!-- Product Details -->
        <!-- ========================================= -->

        <div class="card-body d-flex flex-column p-4">

            <h5 class="card-title fw-semibold mb-2">

                ${product.title}

            </h5>

            <p class="card-text text-secondary small mb-3">

                ${product.description}

            </p>

            <!-- ===================================== -->
            <!-- Brand -->
            <!-- ===================================== -->

            <p class="small mb-4">

                <span class="text-secondary">

                    Brand:

                </span>

                <strong>

                    ${product.brand}

                </strong>

            </p>

            <!-- ===================================== -->
            <!-- Actions -->
            <!-- ===================================== -->

            <div class="mt-auto">

                <div class="d-grid gap-2">

                    <a
                        href="/product.html?id=${product.id}"
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

    </article>

</div>

`;

}