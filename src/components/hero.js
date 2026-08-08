/**
 * ============================================================
 * Product Catalog
 * ------------------------------------------------------------
 * File      : hero.js
 * Purpose   : Home Page Hero Section
 * Version   : 1.0.0
 * ============================================================
 */

export function renderHero() {

    const hero = document.querySelector("#hero");

    if (!hero) return;

    hero.innerHTML = heroTemplate();

}

function heroTemplate() {

    return `


<section class="py-5 bg-body-tertiary">

    <div class="container">

        <div class="row align-items-center gy-5">

            <!-- ========================================= -->
            <!-- Left Column -->
            <!-- ========================================= -->

            <div class="col-lg-6">

                <span class="badge rounded-pill text-bg-danger px-3 py-2 mb-3">

    <i class="bi bi-shield-check me-2"></i>

    Trusted Industrial Safety Supplier

</span>

                <h1 class="display-4 fw-bold mb-4">

    Complete Industrial

    <span class="text-danger">
        Safety Solutions
    </span>

    <br>

    For Every Workplace

</h1>

                <p class="lead text-secondary mb-4">

                    We supply certified Personal Protective Equipment (PPE), fire safety equipment, industrial footwear, helmets, gloves and workplace safety solutions for manufacturing, engineering and construction industries.

                </p>

                <div class="d-flex flex-wrap gap-3 mb-5">

                    <a
                        href="/products.html"
                        class="btn btn-danger btn-lg">

                        <i class="bi bi-grid me-2"></i>

Explore Products

                    </a>

                    <a
                        href="/contact.html"
                        class="btn btn-outline-dark btn-lg">

                       <i class="bi bi-envelope-paper me-2"></i>

Request Quote

                    </a>

                </div>

                <div class="row g-3">

                    <div class="col-sm-4">

                        <div class="d-flex align-items-center">

                            <i class="bi bi-patch-check-fill text-success fs-4 me-2"></i>

                            <span>

                                Genuine Products

                            </span>

                        </div>

                    </div>

                    <div class="col-sm-4">

                        <div class="d-flex align-items-center">

                            <i class="bi bi-box-seam text-primary fs-4 me-2"></i>

                            <span>

                                500+ Products

                            </span>

                        </div>

                    </div>

                    <div class="col-sm-4">

                        <div class="d-flex align-items-center">

                            <i class="bi bi-truck text-warning fs-4 me-2"></i>

                            <span>

                                Fast Delivery

                            </span>

                        </div>

                    </div>

                </div>

            </div>

            <!-- ========================================= -->
            <!-- Right Column -->
            <!-- ========================================= -->

            <div class="col-lg-6 text-center">

                <img
                    src="https://picsum.photos/700/600"
                    alt="Industrial Safety Products"
                    class="img-fluid rounded shadow">

            </div>

        </div>

    </div>

</section>

`;

}