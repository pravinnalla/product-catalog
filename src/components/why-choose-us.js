/**
 * ============================================================
 * Product Catalog
 * ------------------------------------------------------------
 * File      : why-choose-us.js
 * Purpose   : Why Choose Us Section
 * Version   : 1.0.0
 * ============================================================
 */

export function renderWhyChooseUs() {

    const whyChooseUs = document.querySelector("#why-choose-us");

    if (!whyChooseUs) return;

    whyChooseUs.innerHTML = `

<section class="py-5">

    <div class="container">

        <!-- ========================================= -->
        <!-- Section Heading -->
        <!-- ========================================= -->

        <div class="text-center mb-5">

            <span class="badge rounded-pill text-bg-danger px-3 py-2 mb-3">

                Why Choose Us

            </span>

            <h2 class="fw-bold mb-3">

                Your Trusted Safety Partner

            </h2>

            <p class="text-secondary mb-0">

                Quality products, trusted brands and reliable service
                for all your industrial safety requirements.

            </p>

        </div>

        <!-- ========================================= -->
        <!-- Features -->
        <!-- ========================================= -->

        <div class="row g-4">

            ${featureCard(
                "bi-patch-check-fill",
                "Quality Products",
                "We provide reliable safety products selected for quality, durability and workplace protection."
            )}

            ${featureCard(
                "bi-award-fill",
                "Trusted Brands",
                "Choose from products supplied by established and trusted safety equipment brands."
            )}

            ${featureCard(
                "bi-grid-3x3-gap-fill",
                "Wide Product Range",
                "Find PPE, fire safety equipment, footwear, gloves, helmets and more in one place."
            )}

            ${featureCard(
                "bi-headset",
                "Reliable Support",
                "Our team helps you identify suitable safety products for your specific requirements."
            )}

        </div>

    </div>

</section>

`;

}

/**
 * ------------------------------------------------------------
 * Feature Card
 * ------------------------------------------------------------
 */

function featureCard(icon, title, description) {

    return `

<div class="col-lg-3 col-md-6">

    <div class="card h-100 border-0 shadow-sm">

        <div class="card-body d-flex flex-column text-center p-4">

            <div class="mb-4">

                <i class="bi ${icon} display-5 text-danger"></i>

            </div>

            <h5 class="fw-semibold mb-3">

                ${title}

            </h5>

            <p class="text-secondary small mb-0">

                ${description}

            </p>

        </div>

    </div>

</div>

`;

}