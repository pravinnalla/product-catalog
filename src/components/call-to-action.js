/**
 * ============================================================
 * Product Catalog
 * ------------------------------------------------------------
 * File      : call-to-action.js
 * Purpose   : Home Page Call To Action Section
 * Version   : 1.1.0
 * ============================================================
 */


export function renderCallToAction() {


    const callToAction =
        document.querySelector("#call-to-action");


    if (!callToAction) return;


    callToAction.innerHTML = `


<section class="py-5 bg-danger text-white">


    <div class="container">


        <!-- ========================================= -->
        <!-- CTA Content -->
        <!-- ========================================= -->


        <div class="row align-items-center g-4">


            <div class="col-lg-8">


                <span class="badge bg-white text-danger px-3 py-2 mb-3">


                    NEED SAFETY PRODUCTS?


                </span>


                <h2 class="display-6 fw-bold mb-3">


                    Let's Help You Find the Right Safety Products


                </h2>


                <p class="lead mb-0 text-white">


                    Tell us what you need. Laxmikant Traders can help
                    you find suitable products for your workplace and
                    safety requirements.


                </p>


            </div>


            <!-- ========================================= -->
            <!-- CTA Actions -->
            <!-- ========================================= -->


            <div class="col-lg-4">


                <div class="d-grid gap-3">


                    <a
                        href="/contact.html"
                        class="btn btn-light btn-lg">


                        <i class="bi bi-envelope-paper me-2"></i>


                        Request a Quote


                    </a>


                    <a
                        href="/products.html"
                        class="btn btn-outline-light btn-lg">


                        <i class="bi bi-grid-3x3-gap me-2"></i>


                        Explore Products


                    </a>


                </div>


            </div>


        </div>


        <!-- ========================================= -->
        <!-- Contact Information -->
        <!-- ========================================= -->


        <div class="border-top border-white border-opacity-25 mt-5 pt-4">


            <div class="row g-4 text-center text-lg-start">


                <!-- ===================================== -->
                <!-- Call Us -->
                <!-- ===================================== -->


                <div class="col-md-4">


                    <div class="d-flex align-items-center justify-content-center justify-content-lg-start">


                        <i class="bi bi-telephone fs-4 text-white me-3"></i>


                        <div>


                            <small class="d-block text-white mb-1">


                                Call Us


                            </small>


                            <strong class="text-white">


                                7020209306 / 9325337307


                            </strong>


                        </div>


                    </div>


                </div>


                <!-- ===================================== -->
                <!-- Email Us -->
                <!-- ===================================== -->


                <div class="col-md-4">


                    <div class="d-flex align-items-center justify-content-center">


                        <i class="bi bi-envelope fs-4 text-white me-3"></i>


                        <div>


                            <small class="d-block text-white mb-1">


                                Email Us


                            </small>


                            <strong class="text-white">


                                laxmikantj96@yahoo.in


                            </strong>


                        </div>


                    </div>


                </div>


                <!-- ===================================== -->
                <!-- Support -->
                <!-- ===================================== -->


                <div class="col-md-4">


                    <div class="d-flex align-items-center justify-content-center justify-content-lg-end">


                        <i class="bi bi-clock fs-4 text-white me-3"></i>


                        <div>


                            <small class="d-block text-white mb-1">


                                Support


                            </small>


                            <strong class="text-white">


                                Available 24 × 7


                            </strong>


                        </div>


                    </div>


                </div>


            </div>


        </div>


    </div>


</section>


`;

}