/**
 * ============================================================
 * Product Catalog
 * ------------------------------------------------------------
 * File      : product-card.js
 * Purpose   : Reusable Product Card Component
 * Version   : 2.3.0
 * ============================================================
 */

const PRODUCTS_IMAGE_PATH =
    "/product-catalog/src/assets/images/products/";


/**
 * ------------------------------------------------------------
 * Product Card
 * ------------------------------------------------------------
 */

export function productCard(product) {

    const imagePath =
        product.image
            ? PRODUCTS_IMAGE_PATH + product.image
            : "";


    const productName =
        product.name ||
        product.title ||
        "Safety Product";


    return `

<article class="card h-100 border-0 shadow-sm">

    <!-- ========================================= -->
    <!-- Product Image -->
    <!-- ========================================= -->

    <div class="position-relative bg-white">

        <img
            src="${imagePath}"
            class="card-img-top"
            alt="${productName}"
            loading="lazy"
            style="
                height: 180px;
                object-fit: contain;
                padding: 12px;
            ">

    </div>


    <!-- ========================================= -->
    <!-- Product Details -->
    <!-- ========================================= -->

    <div
        class="
            card-body
            d-flex
            flex-column
            p-3
        ">

        <h6
            class="
                card-title
                fw-semibold
                mb-3
            ">

            ${productName}

        </h6>


        <!-- ===================================== -->
        <!-- Request Quote -->
        <!-- ===================================== -->

        <div class="mt-auto">

            <div class="d-grid">

                <a
                    href="/contact.html?product=${product.id}"
                    class="
                        btn
                        btn-sm
                        btn-danger
                    ">

                    <i
                        class="
                            bi
                            bi-envelope-paper
                            me-1
                        ">
                    </i>

                    Request Quote

                </a>

            </div>

        </div>

    </div>

</article>

`;

}