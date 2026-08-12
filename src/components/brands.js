/**
 * ============================================================
 * Product Catalog
 * ------------------------------------------------------------
 * File      : brands.js
 * Purpose   : Trusted Suppliers Section
 * Version   : 2.1.0
 * ============================================================
 */


import { brandImageUrl } from "../utils/paths.js";


export function renderBrands() {


    const brands =
        document.querySelector("#brands");


    if (!brands) return;


    brands.innerHTML = `


<section class="py-5 bg-body-tertiary">


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


                    OUR TRUSTED SUPPLIERS


                </span>


                <h2 class="display-6 fw-bold mb-3">


                    Trusted Suppliers


                </h2>


                <p class="lead text-secondary mb-0">


                    We source quality industrial safety products
                    from established suppliers to meet the safety
                    requirements of our customers.


                </p>


            </div>


        </div>


        <!-- ========================================= -->
        <!-- Supplier Logos -->
        <!-- ========================================= -->


        <div class="row g-4 justify-content-center">


            ${supplierCard(
                "shreefire.png",
                "Shree Fire Services",
                "Fire safety equipment and solutions."
            )}


            ${supplierCard(
                "safetygloves.png",
                "Safety Gloves / Sawalka KEL Pvt. Ltd.",
                "Industrial safety gloves and protective equipment."
            )}


        </div>


    </div>


</section>


`;

}


/**
 * ------------------------------------------------------------
 * Supplier Card
 * ------------------------------------------------------------
 */


function supplierCard(
    logo,
    name,
    description
) {


    return `


<div class="col-lg-5 col-md-6">


    <div class="card h-100 border-0 shadow-sm">


        <div
            class="
                card-body
                d-flex
                flex-column
                align-items-center
                justify-content-center
                text-center
                p-4
                p-lg-5
            ">


            <!-- ===================================== -->
            <!-- Supplier Logo -->
            <!-- ===================================== -->


            <div
                class="
                    d-flex
                    align-items-center
                    justify-content-center
                    w-100
                    mb-4
                "
                style="
                    height: 110px;
                ">


                <img
                    src="${brandImageUrl(logo)}"
                    alt="${name}"
                    class="img-fluid"
                    loading="lazy"
                    style="
                        max-width: 220px;
                        max-height: 90px;
                        width: auto;
                        height: auto;
                        object-fit: contain;
                    ">


            </div>


            <!-- ===================================== -->
            <!-- Supplier Name -->
            <!-- ===================================== -->


            <h4 class="fw-bold mb-2">


                ${name}


            </h4>


            <!-- ===================================== -->
            <!-- Description -->
            <!-- ===================================== -->


            <p class="text-secondary small mb-0">


                ${description}


            </p>


        </div>


    </div>


</div>


`;

}
