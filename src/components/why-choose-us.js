/**
 * ============================================================
 * Laxmikant Traders
 * ------------------------------------------------------------
 * File      : why-choose-us.js
 * Purpose   : Why Choose Us Section
 * Version   : 1.1.0
 * ============================================================
 */


export function renderWhyChooseUs() {


    const whyChooseUs =
        document.querySelector(
            "#why-choose-us"
        );


    if (!whyChooseUs) return;


    whyChooseUs.innerHTML = `


<section class="py-5">


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


                WHY CHOOSE US


            </span>


            <h2 class="fw-bold mb-3">


                Your Industrial Safety Partner


            </h2>


            <p class="text-secondary mb-0">


                Suitable safety products, a wide product range
                and responsive support for your workplace requirements.


            </p>


        </div>


        <!-- ========================================= -->
        <!-- Features -->
        <!-- ========================================= -->


        <div class="row g-4">


            ${featureCard(
                "bi-patch-check-fill",
                "Quality Products",
                "We provide safety products selected for quality, durability and workplace protection."
            )}


            ${featureCard(
                "bi-grid-3x3-gap-fill",
                "Wide Product Range",
                "Find fire safety equipment, personal protective equipment and other industrial safety products in one place."
            )}


            ${featureCard(
                "bi-shield-check",
                "Safety-Focused Selection",
                "We help customers identify suitable products based on their workplace and safety requirements."
            )}


            ${featureCard(
                "bi-headset",
                "Responsive Support",
                "We assist customers with product enquiries, quotations and safety product requirements."
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


function featureCard(
    icon,
    title,
    description
) {


    return `


<div class="col-lg-3 col-md-6">


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


            <div class="mb-4">


                <i
                    class="
                        bi
                        ${icon}
                        display-5
                        text-danger
                    ">
                </i>


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