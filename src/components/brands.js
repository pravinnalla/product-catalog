/**
 * ============================================================
 * Product Catalog
 * ------------------------------------------------------------
 * File      : brands.js
 * Purpose   : Trusted Brands Section
 * Version   : 1.0.0
 * ============================================================
 */

export function renderBrands() {

    const brands = document.querySelector("#brands");

    if (!brands) return;

    brands.innerHTML = `

<section class="py-5 bg-body-tertiary">

    <div class="container">

        <!-- ========================================= -->
        <!-- Section Heading -->
        <!-- ========================================= -->

        <div class="row justify-content-center mb-5">

            <div class="col-lg-8 text-center">

                <span class="badge rounded-pill text-bg-danger px-3 py-2 mb-3">

                    TRUSTED BRANDS

                </span>

                <h2 class="display-6 fw-bold mb-3">

                    Brands You Can Trust

                </h2>

                <p class="lead text-secondary mb-0">

                    We offer safety products from established and
                    trusted brands in the industry.

                </p>

            </div>

        </div>

        <!-- ========================================= -->
        <!-- Brands Grid -->
        <!-- ========================================= -->

        <div class="row g-4 justify-content-center">

            ${brandCard(
                "KARAM",
                "Personal safety and fall protection solutions."
            )}

            ${brandCard(
                "3M",
                "Safety, health and industrial protection products."
            )}

            ${brandCard(
                "Udyogi",
                "Complete personal protective equipment solutions."
            )}

            ${brandCard(
                "Allen Cooper",
                "Industrial safety footwear and workwear."
            )}

            ${brandCard(
                "Safex",
                "Fire safety and protection equipment."
            )}

            ${brandCard(
                "Honeywell",
                "Advanced personal and industrial safety solutions."
            )}

            ${brandCard(
                "MSA",
                "Professional safety equipment and protection systems."
            )}

            ${brandCard(
                "Delta Plus",
                "Personal protective equipment for multiple industries."
            )}

        </div>

    </div>

</section>

`;

}

/**
 * ------------------------------------------------------------
 * Brand Card
 * ------------------------------------------------------------
 */

function brandCard(name, description) {

    return `

<div class="col-xl-3 col-lg-4 col-md-6 col-6">

    <div class="card h-100 border-0 shadow-sm">

        <div class="card-body d-flex flex-column align-items-center justify-content-center text-center p-4">

            <!-- ===================================== -->
            <!-- Brand Name -->
            <!-- ===================================== -->

            <div class="border border-2 border-secondary-subtle rounded-3 px-4 py-3 mb-3">

                <h4 class="fw-bold mb-0">

                    ${name}

                </h4>

            </div>

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