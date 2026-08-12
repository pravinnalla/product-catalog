/**
 * ============================================================
 * Product Catalog
 * ------------------------------------------------------------
 * File      : featured-products.js
 * Purpose   : Featured Products Section
 * Version   : 2.0.0
 * ============================================================
 */

import { getProducts } from "../services/product.service.js";


/**
 * ------------------------------------------------------------
 * Render Featured Products
 * ------------------------------------------------------------
 */

export function renderFeaturedProducts() {

    const featuredProducts =
        document.querySelector(
            "#featured-products"
        );

    if (!featuredProducts) return;


    const products =
        getProducts()
            .slice(0, 8);


    featuredProducts.innerHTML = `

<section class="py-5 bg-body-tertiary">

    <div class="container">

        <!-- ========================================= -->
        <!-- Section Heading -->
        <!-- ========================================= -->

        <div class="text-center mb-5">

            <span
                class="
                    badge
                    rounded-pill
                    text-bg-danger
                    px-3
                    py-2
                    mb-3
                ">

                FEATURED PRODUCTS

            </span>


            <h2 class="fw-bold mb-3">

                Our Safety Products

            </h2>


            <p class="text-secondary mb-0">

                Explore selected industrial safety products
                available from Laxmikant Traders.

            </p>

        </div>


        <!-- ========================================= -->
        <!-- Product Grid -->
        <!-- ========================================= -->

        <div class="row g-4">

            ${products
                .map(
                    product =>
                        featuredProductCard(
                            product
                        )
                )
                .join("")}

        </div>


        <!-- ========================================= -->
        <!-- View All Products -->
        <!-- ========================================= -->

        <div class="text-center mt-5">

            <a
                href="/products.html"
                class="btn btn-danger">

                <i
                    class="
                        bi
                        bi-grid-3x3-gap
                        me-2
                    ">
                </i>

                View All Products

            </a>

        </div>

    </div>

</section>

`;

}


/**
 * ------------------------------------------------------------
 * Featured Product Card
 * ------------------------------------------------------------
 */

function featuredProductCard(product) {

    const imagePath =
    product.image
        ? `/product-catalog/src/assets/images/products/${product.image}`
        : "";


    const productName =
        product.name ||
        product.title ||
        "Safety Product";


    const category =
        product.subcategory ||
        product.category ||
        "Safety Equipment";


    return `

<div class="col-xl-3 col-lg-4 col-md-6">

    <article
        class="
            card
            h-100
            border-0
            shadow-sm
        ">

        <!-- ========================================= -->
        <!-- Product Image -->
        <!-- ========================================= -->

        <div
            class="
                position-relative
                bg-white
            ">

            <img
                src="${imagePath}"
                class="
                    card-img-top
                    w-100
                "
                alt="${productName}"
                loading="lazy"
                style="
                    height: 200px;
                    object-fit: contain;
                    padding: 15px;
                ">


            <span
                class="
                    badge
                    text-bg-danger
                    position-absolute
                    top-0
                    start-0
                    m-3
                ">

                ${category}

            </span>

        </div>


        <!-- ========================================= -->
        <!-- Product Details -->
        <!-- ========================================= -->

        <div
            class="
                card-body
                d-flex
                flex-column
                p-4
            ">


            <h5
                class="
                    card-title
                    fw-semibold
                    mb-3
                ">

                ${productName}

            </h5>


            <!-- ===================================== -->
            <!-- Request Quote -->
            <!-- ===================================== -->

            <div class="mt-auto">

                <a
                    href="/contact.html?product=${product.id}"
                    class="
                        btn
                        btn-danger
                        w-100
                    ">

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

    </article>

</div>

`;

}