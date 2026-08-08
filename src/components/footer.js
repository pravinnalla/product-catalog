/**
 * ============================================================
 * Product Catalog
 * ------------------------------------------------------------
 * File      : footer.js
 * Purpose   : Website Footer
 * Version   : 1.0.0
 * ============================================================
 */

export function renderFooter() {

    const footer = document.querySelector("#footer");

    if (!footer) return;

    footer.innerHTML = `

<footer class="bg-dark text-white mt-0">

    <div class="container py-5">

        <div class="row gy-4">

            <!-- Company -->

            <div class="col-lg-4">

                <h4 class="fw-bold mb-3">

                    <i class="bi bi-shield-check me-2"></i>

                    SAFETYMART

                </h4>

                <p class="text-light">

                    Complete Industrial Safety Solutions for
                    Manufacturing, Construction and Engineering Industries.

                </p>

            </div>

            <!-- Quick Links -->

            <div class="col-lg-2">

                <h5 class="mb-3">
                    Quick Links
                </h5>

                <ul class="list-unstyled">

                    <li><a class="text-light text-decoration-none" href="/index.html">Home</a></li>

                    <li><a class="text-light text-decoration-none" href="/about.html">About</a></li>

                    <li><a class="text-light text-decoration-none" href="/products.html">Products</a></li>

                    <li><a class="text-light text-decoration-none" href="/contact.html">Contact</a></li>

                </ul>

            </div>

            <!-- Categories -->

            <div class="col-lg-3">

                <h5 class="mb-3">
                    Categories
                </h5>

                <ul class="list-unstyled">

                    <li>Safety Gloves</li>
                    <li>Safety Helmets</li>
                    <li>Safety Shoes</li>
                    <li>Fire Safety</li>
                    <li>Respiratory Protection</li>

                </ul>

            </div>

            <!-- Contact -->

            <div class="col-lg-3">

                <h5 class="mb-3">
                    Contact
                </h5>

                <p>

                    <i class="bi bi-geo-alt me-2"></i>

                    Pune, Maharashtra

                </p>

                <p>

                    <i class="bi bi-envelope me-2"></i>

                    info@example.com

                </p>

                <p>

                    <i class="bi bi-telephone me-2"></i>

                    +91 98765 43210

                </p>

            </div>

        </div>

        <hr class="border-secondary my-4">

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center">

            <small>

                © 2026 SAFETYMART. All Rights Reserved.

            </small>

            <button
                id="backToTop"
                class="btn btn-outline-light btn-sm">

                <i class="bi bi-arrow-up"></i>

            </button>

        </div>

    </div>

</footer>

`;

    document
        .querySelector("#backToTop")
        ?.addEventListener("click", () => {

            window.scrollTo({

                top: 0,

                behavior: "smooth"

            });

        });

}