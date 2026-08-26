/**
 * ============================================================
 * Laxmikant Traders
 * ------------------------------------------------------------
 * File      : contact.js
 * Purpose   : Contact / Enquiry Page
 * Version   : 2.1.0
 * ============================================================
 */


import { renderNavbar } from "../components/navbar.js";
import { renderFooter } from "../components/footer.js";
import { renderEnquiryForm } from "../components/enquiry-form.js";


/**
 * ------------------------------------------------------------
 * Render Contact Page
 * ------------------------------------------------------------
 */


export async function renderContactPage(app) {


    if (!app) return;


    app.innerHTML = `


        <div id="navbar"></div>


        <main>


            <section id="contact-header"></section>


            <section id="contact-content"></section>


        </main>


        <div id="footer"></div>


    `;


    renderNavbar();


    renderContactHeader();


    renderContactContent();


    await renderEnquiryForm();


    renderFooter();


}


/**
 * ------------------------------------------------------------
 * Contact Page Header
 * ------------------------------------------------------------
 */


function renderContactHeader() {


    const header =
        document.querySelector("#contact-header");


    if (!header) return;


    header.innerHTML = `


<section class="py-5 bg-body-tertiary">


    <div class="container">


        <div class="row justify-content-center text-center">


            <div class="col-lg-8">


                <span
                    class="badge rounded-pill text-bg-danger
                           px-3 py-2 mb-3">


                    CONTACT US


                </span>


                <h1 class="display-5 fw-bold mb-3">


                    Get in Touch With Laxmikant Traders


                </h1>


                <p class="lead text-secondary mb-0">


                    Have a product enquiry or need a quotation?
                    Laxmikant Traders is available 24 × 7 to help
                    you with your industrial safety product
                    requirements.


                </p>


            </div>


        </div>


    </div>


</section>


`;


}


/**
 * ------------------------------------------------------------
 * Contact Content
 * ------------------------------------------------------------
 */


function renderContactContent() {


    const content =
        document.querySelector("#contact-content");


    if (!content) return;


    content.innerHTML = `


<section class="py-5">


    <div class="container">


        <div class="row g-5">


            <!-- ========================================= -->
            <!-- Contact Information -->
            <!-- ========================================= -->


            <div class="col-lg-5">


                <span
                    class="badge rounded-pill text-bg-light
                           border text-dark px-3 py-2 mb-3">


                    CONTACT INFORMATION


                </span>


                <h2 class="fw-bold mb-3">


                    We Are Here to Help


                </h2>


                <p class="text-secondary mb-4">


                    Contact Laxmikant Traders for product
                    information, quotations, bulk requirements
                    or any other industrial safety enquiry.


                </p>


                <!-- ===================================== -->
                <!-- Location -->
                <!-- ===================================== -->


                <div class="d-flex mb-4">


                    <div class="flex-shrink-0">


                        <div
                            class="bg-danger-subtle text-danger
                                   rounded-circle d-flex
                                   align-items-center
                                   justify-content-center"
                            style="width: 48px; height: 48px;">


                            <i class="bi bi-geo-alt fs-5"></i>


                        </div>


                    </div>


                    <div class="ms-3">


                        <h6 class="fw-bold mb-1">


                            Our Location


                        </h6>


                        <p class="text-secondary mb-0">


                            266/7, Raviwar Peth,<br>
                            Near Rajendra Chowk,<br>
                            Solapur, Maharashtra - 413005


                        </p>


                    </div>


                </div>


                <!-- ===================================== -->
                <!-- Phone -->
                <!-- ===================================== -->


                <div class="d-flex mb-4">


                    <div class="flex-shrink-0">


                        <div
                            class="bg-danger-subtle text-danger
                                   rounded-circle d-flex
                                   align-items-center
                                   justify-content-center"
                            style="width: 48px; height: 48px;">


                            <i class="bi bi-telephone fs-5"></i>


                        </div>


                    </div>


                    <div class="ms-3">


                        <h6 class="fw-bold mb-1">


                            Call Us


                        </h6>


                        <p class="text-secondary mb-0">


                            7020209306<br>
                            9325337307


                        </p>


                    </div>


                </div>


                <!-- ===================================== -->
                <!-- Email -->
                <!-- ===================================== -->


                <div class="d-flex mb-4">


                    <div class="flex-shrink-0">


                        <div
                            class="bg-danger-subtle text-danger
                                   rounded-circle d-flex
                                   align-items-center
                                   justify-content-center"
                            style="width: 48px; height: 48px;">


                            <i class="bi bi-envelope fs-5"></i>


                        </div>


                    </div>


                    <div class="ms-3">


                        <h6 class="fw-bold mb-1">


                            Email Us


                        </h6>


                        <p class="text-secondary mb-0">


                            laxmikantj96@yahoo.in


                        </p>


                    </div>


                </div>


                <!-- ===================================== -->
                <!-- Availability -->
                <!-- ===================================== -->


                <div class="d-flex mb-4">


                    <div class="flex-shrink-0">


                        <div
                            class="bg-danger-subtle text-danger
                                   rounded-circle d-flex
                                   align-items-center
                                   justify-content-center"
                            style="width: 48px; height: 48px;">


                            <i class="bi bi-clock fs-5"></i>


                        </div>


                    </div>


                    <div class="ms-3">


                        <h6 class="fw-bold mb-1">


                            Availability


                        </h6>


                        <p class="text-secondary mb-0">


                            Available 24 × 7


                        </p>


                    </div>


                </div>


                <!-- ===================================== -->
                <!-- Safety Products Message -->
                <!-- ===================================== -->


                <div
                    class="card border-0 bg-body-tertiary
                           shadow-sm mt-4">


                    <div class="card-body p-4">


                        <div class="d-flex">


                            <i
                                class="bi bi-shield-check
                                       text-danger fs-3 me-3">
                            </i>


                            <div>


                                <h6 class="fw-bold">


                                    Industrial Safety Products


                                </h6>


                                <p class="text-secondary small mb-0">


                                    We help customers identify suitable
                                    safety products for their workplace
                                    and industrial requirements.


                                </p>


                            </div>


                        </div>


                    </div>


                </div>


            </div>


            <!-- ========================================= -->
            <!-- Enquiry Area -->
            <!-- ========================================= -->


            <div class="col-lg-7">


                <div class="card border-0 shadow-sm">


                    <div class="card-body p-4 p-lg-5">


                        <div class="mb-4">


                            <span
                                class="badge rounded-pill text-bg-danger
                                       px-3 py-2 mb-3">


                                REQUEST A QUOTE


                            </span>


                            <h2 class="fw-bold mb-2">


                                Send Us Your Enquiry


                            </h2>


                            <p class="text-secondary mb-0">


                                Tell us what you need. Laxmikant Traders
                                can help you find suitable products for
                                your workplace and safety requirements.


                            </p>


                        </div>


                        <div id="enquiry-form"></div>


                    </div>


                </div>


            </div>


        </div>


    </div>


</section>


`;


}
