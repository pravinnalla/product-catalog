/**
 * ============================================================
 * Product Catalog
 * ------------------------------------------------------------
 * File      : gallery.js
 * Purpose   : Product Gallery Section
 * Version   : 2.1.0
 * ============================================================
 */


import { getProducts } from "../services/product.service.js";
import { productImageUrl } from "../utils/paths.js";


const FEATURED_PRODUCTS_COUNT = 8;


const GALLERY_PRODUCTS_COUNT = 8;


/**
 * ------------------------------------------------------------
 * Render Product Gallery
 * ------------------------------------------------------------
 */


export function renderGallery() {


    const gallery =
        document.querySelector("#gallery");


    if (!gallery) return;


    const products =
        getProducts();


    /*
     * Featured Products uses the first 8 products.
     *
     * Gallery starts after those products so that the same
     * products are not displayed twice on the Home page.
     */


    const featuredProductIds =
        products
            .slice(
                0,
                FEATURED_PRODUCTS_COUNT
            )
            .map(
                product =>
                    product.id
            );


    const galleryProducts =
        products
            .filter(
                product =>
                    product.image &&
                    !featuredProductIds.includes(
                        product.id
                    )
            )
            .slice(
                0,
                GALLERY_PRODUCTS_COUNT
            );


    gallery.innerHTML = `


<section class="py-5">


    <div class="container">


        <!-- ========================================= -->
        <!-- Section Heading -->
        <!-- ========================================= -->


        <div class="row justify-content-center mb-5">


            <div class="col-lg-8 text-center">


                <span
                    class="
                        badge
                        rounded-pill
                        text-bg-danger
                        px-3
                        py-2
                        mb-3
                    ">


                    OUR PRODUCTS


                </span>


                <h2 class="display-6 fw-bold mb-3">


                    More Products


                </h2>


                <p class="lead text-secondary mb-0">


                    Explore more products from our industrial
                    safety equipment and workplace protection range.


                </p>


            </div>


        </div>


        <!-- ========================================= -->
        <!-- Gallery Grid -->
        <!-- ========================================= -->


        <div class="row g-4">


            ${galleryProducts
                .map(
                    product =>
                        galleryItem(product)
                )
                .join("")}


        </div>


    </div>


</section>


`;


}


/**
 * ------------------------------------------------------------
 * Gallery Item
 * ------------------------------------------------------------
 */


function galleryItem(product) {


    const imagePath =
        productImageUrl(product.image);


    const productName =
        product.name ||
        product.title ||
        "Safety Product";


    return `


<div class="col-xl-3 col-lg-3 col-md-6 col-12">


    <div
        class="
            card
            h-100
            border-0
            shadow-sm
            overflow-hidden
        ">


        <!-- ===================================== -->
        <!-- Product Image -->
        <!-- ===================================== -->


        <div
            class="
                d-flex
                align-items-center
                justify-content-center
                bg-body-tertiary
            "
            style="
                height: 220px;
            ">


            <img
                src="${imagePath}"
                class="img-fluid"
                alt="${productName}"
                loading="lazy"
                style="
                    max-height: 200px;
                    max-width: 90%;
                    object-fit: contain;
                ">


        </div>


        <!-- ===================================== -->
        <!-- Product Information -->
        <!-- ===================================== -->


        <div class="card-body text-center">


            <h6 class="fw-semibold mb-2">


                ${productName}


            </h6>


            ${
                product.subcategory
                    ? `
                        <p class="text-secondary small mb-0">

                            ${product.subcategory}

                        </p>
                    `
                    : ""
            }


        </div>


    </div>


</div>


`;

}
