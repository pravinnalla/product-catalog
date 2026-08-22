/**
 * ============================================================
 * Laxmikant Traders
 * ------------------------------------------------------------
 * File      : enquiry-form.js
 * Purpose   : Secure Product Enquiry Form
 * Version   : 1.6.0
 * ============================================================
 */

import { getProductById } from "../services/product.service.js";


/**
 * ------------------------------------------------------------
 * API Configuration
 * ------------------------------------------------------------
 */

const API_URL =
    "http://localhost:8000/api/send-enquiry.php";


/**
 * ------------------------------------------------------------
 * Render Enquiry Form
 * ------------------------------------------------------------
 */

export function renderEnquiryForm() {

    const formContainer =
        document.querySelector("#enquiry-form");

    if (!formContainer) return;


    const productId =
        getProductIdFromUrl();


    const product =
        productId
            ? getProductById(productId)
            : null;


    formContainer.innerHTML =
        enquiryFormTemplate(product);


    initializeEnquiryForm();

}


/**
 * ------------------------------------------------------------
 * Get Product ID From URL
 * ------------------------------------------------------------
 */

function getProductIdFromUrl() {

    const params =
        new URLSearchParams(
            window.location.search
        );

    return params.get("product");

}


/**
 * ------------------------------------------------------------
 * Enquiry Form Template
 * ------------------------------------------------------------
 */

function enquiryFormTemplate(product) {

    const productName =
        product
            ? (
                product.name ||
                product.title ||
                ""
            )
            : "";


    return `

<form
    id="productEnquiryForm"
    novalidate>


    <!-- ========================================= -->
    <!-- Product -->
    <!-- ========================================= -->

    <div class="mb-4">

        <label
            for="enquiryProduct"
            class="form-label fw-semibold">

            Product

            <span class="text-danger">*</span>

        </label>


        ${
            product
                ? `
                    <input
                        type="text"
                        id="enquiryProduct"
                        class="form-control bg-body-tertiary"
                        value="${escapeHTML(productName)}"
                        readonly
                        required>

                    <input
                        type="hidden"
                        id="enquiryProductId"
                        name="productId"
                        value="${escapeHTML(String(product.id))}">

                    <small class="text-secondary">

                        Product selected from the catalogue.

                    </small>

                `
                : `
                    <input
                        type="text"
                        id="enquiryProduct"
                        name="product"
                        class="form-control"
                        placeholder="Enter product name"
                        maxlength="200"
                        autocomplete="off"
                        required>

                    <div class="invalid-feedback">

                        Please enter the product name.

                    </div>
                `
        }

    </div>


    <!-- ========================================= -->
    <!-- Name / Company -->
    <!-- ========================================= -->

    <div class="row g-3 mb-4">


        <!-- Name -->

        <div class="col-md-6">

            <label
                for="enquiryName"
                class="form-label fw-semibold">

                Your Name

                <span class="text-danger">*</span>

            </label>


            <input
                type="text"
                id="enquiryName"
                name="name"
                class="form-control"
                placeholder="Enter your name"
                maxlength="100"
                autocomplete="name"
                required>


            <div class="invalid-feedback">

                Please enter your name.

            </div>

        </div>


        <!-- Company -->

        <div class="col-md-6">

            <label
                for="enquiryCompany"
                class="form-label fw-semibold">

                Company Name

                <span class="text-danger">*</span>

            </label>


            <input
                type="text"
                id="enquiryCompany"
                name="company"
                class="form-control"
                placeholder="Company name"
                maxlength="150"
                autocomplete="organization"
                required>


            <div class="invalid-feedback">

                Please enter your company name.

            </div>

        </div>

    </div>


    <!-- ========================================= -->
    <!-- Phone / Email -->
    <!-- ========================================= -->

    <div class="row g-3 mb-4">


        <!-- Phone -->

        <div class="col-md-6">

            <label
                for="enquiryPhone"
                class="form-label fw-semibold">

                Phone Number

                <span class="text-danger">*</span>

            </label>


            <input
                type="tel"
                id="enquiryPhone"
                name="phone"
                class="form-control"
                placeholder="Enter your phone number"
                maxlength="20"
                autocomplete="tel"
                inputmode="tel"
                required>


            <div class="invalid-feedback">

                Please enter a valid phone number.

            </div>

        </div>


        <!-- Email -->

        <div class="col-md-6">

            <label
                for="enquiryEmail"
                class="form-label fw-semibold">

                Email Address

                <span class="text-danger">*</span>

            </label>


            <input
                type="email"
                id="enquiryEmail"
                name="email"
                class="form-control"
                placeholder="Enter your email address"
                maxlength="254"
                autocomplete="email"
                required>


            <div class="invalid-feedback">

                Please enter a valid email address.

            </div>

        </div>

    </div>


    <!-- ========================================= -->
    <!-- Quantity -->
    <!-- ========================================= -->

    <div class="mb-4">

        <label
            for="enquiryQuantity"
            class="form-label fw-semibold">

            Required Quantity

            <span class="text-danger">*</span>

        </label>


        <input
            type="number"
            id="enquiryQuantity"
            name="quantity"
            class="form-control"
            min="1"
            max="1000000"
            step="1"
            value="1"
            placeholder="Enter quantity"
            inputmode="numeric"
            required>


        <div class="invalid-feedback">

            Please enter a valid quantity.

        </div>

    </div>


    <!-- ========================================= -->
    <!-- Message -->
    <!-- ========================================= -->

    <div class="mb-4">

        <label
            for="enquiryMessage"
            class="form-label fw-semibold">

            Message

        </label>


        <textarea
            id="enquiryMessage"
            name="message"
            class="form-control"
            rows="5"
            maxlength="2000"
            placeholder="Tell us about your requirement..."></textarea>


        <div class="invalid-feedback">

            Message is too long.

        </div>

    </div>


    <!-- ========================================= -->
    <!-- Honeypot -->
    <!-- ========================================= -->

    <div
        class="position-absolute"
        style="
            left: -10000px;
            width: 1px;
            height: 1px;
            overflow: hidden;
        "
        aria-hidden="true">


        <label for="website">

            Website

        </label>


        <input
            type="text"
            id="website"
            name="website"
            tabindex="-1"
            autocomplete="off">


    </div>


    <!-- ========================================= -->
    <!-- Privacy Notice -->
    <!-- ========================================= -->

    <div class="d-flex align-items-start mb-4">


        <i
            class="bi bi-shield-check text-success fs-5 me-2">
        </i>


        <small class="text-secondary">

            Your information will be used only to respond
            to your enquiry.

        </small>


    </div>


    <!-- ========================================= -->
    <!-- Submit -->
    <!-- ========================================= -->

    <button
        type="submit"
        class="btn btn-danger btn-lg w-100">


        <i class="bi bi-send me-2"></i>


        Send Enquiry


    </button>


    <!-- ========================================= -->
    <!-- Success Message -->
    <!-- ========================================= -->

    <div
        id="enquirySuccess"
        class="alert alert-success mt-4 d-none"
        role="alert">


        <div class="d-flex">


            <i
                class="bi bi-check-circle-fill fs-5 me-2">
            </i>


            <div>


                <strong>Thank you!</strong>


                <div class="small mt-1">

                    Your enquiry has been received.
                    Our team will contact you shortly.

                </div>


            </div>


        </div>


    </div>


    <!-- ========================================= -->
    <!-- Error Message -->
    <!-- ========================================= -->

    <div
        id="enquiryError"
        class="alert alert-danger mt-4 d-none"
        role="alert">


        <div class="d-flex">


            <i
                class="bi bi-exclamation-triangle-fill fs-5 me-2">
            </i>


            <div>


                <strong>Unable to send enquiry.</strong>


                <div
                    id="enquiryErrorText"
                    class="small mt-1">

                    Please try again later.

                </div>


            </div>


        </div>


    </div>


</form>

`;

}


/**
 * ------------------------------------------------------------
 * Initialize Enquiry Form
 * ------------------------------------------------------------
 */

function initializeEnquiryForm() {

    const form =
        document.querySelector(
            "#productEnquiryForm"
        );


    const successMessage =
        document.querySelector(
            "#enquirySuccess"
        );


    const errorMessage =
        document.querySelector(
            "#enquiryError"
        );


    const errorText =
        document.querySelector(
            "#enquiryErrorText"
        );


    const productInput =
        document.querySelector(
            "#enquiryProduct"
        );


    const phoneInput =
        document.querySelector(
            "#enquiryPhone"
        );


    const quantityInput =
        document.querySelector(
            "#enquiryQuantity"
        );


    if (!form) return;


    /**
     * --------------------------------------------------------
     * Phone Validation
     * --------------------------------------------------------
     */

    if (phoneInput) {

        phoneInput.addEventListener(
            "input",
            () => {

                const value =
                    phoneInput.value.trim();


                const phonePattern =
                    /^[0-9+()\-\s]{7,20}$/;


                phoneInput.setCustomValidity(

                    value !== "" &&
                    !phonePattern.test(value)

                        ? "Invalid phone number."

                        : ""

                );

            }
        );

    }


    /**
     * --------------------------------------------------------
     * Quantity Validation
     * --------------------------------------------------------
     */

    if (quantityInput) {

        quantityInput.addEventListener(
            "input",
            () => {

                const value =
                    quantityInput.value.trim();


                if (value === "") {

                    quantityInput.setCustomValidity(
                        "Quantity is required."
                    );

                    return;

                }


                const quantity =
                    Number(value);


                quantityInput.setCustomValidity(

                    Number.isInteger(quantity) &&
                    quantity >= 1 &&
                    quantity <= 1000000

                        ? ""

                        : "Invalid quantity."

                );

            }
        );

    }


    /**
     * --------------------------------------------------------
     * Submit
     * --------------------------------------------------------
     */

    form.addEventListener(
        "submit",
        async event => {

            event.preventDefault();


            successMessage?.classList.add(
                "d-none"
            );


            errorMessage?.classList.add(
                "d-none"
            );


            /**
             * -----------------------------------------------
             * Validate Product
             * -----------------------------------------------
             */

            if (
                !productInput ||
                productInput.value.trim() === ""
            ) {

                productInput?.setCustomValidity(
                    "Product is required."
                );

            } else {

                productInput?.setCustomValidity("");

            }


            /**
             * -----------------------------------------------
             * Validate Form
             * -----------------------------------------------
             */

            if (!form.checkValidity()) {

                event.stopPropagation();


                form.classList.add(
                    "was-validated"
                );


                return;

            }


            form.classList.add(
                "was-validated"
            );


            /**
             * -----------------------------------------------
             * Submit Button
             * -----------------------------------------------
             */

            const submitButton =
                form.querySelector(
                    'button[type="submit"]'
                );


            const originalButtonHTML =
                submitButton
                    ? submitButton.innerHTML
                    : "";


            if (submitButton) {

                submitButton.disabled = true;


                submitButton.innerHTML = `

                    <span
                        class="spinner-border spinner-border-sm me-2"
                        aria-hidden="true">
                    </span>

                    Sending...

                `;

            }


            /**
             * -----------------------------------------------
             * Collect Form Data
             * -----------------------------------------------
             */

            const formData =
                new FormData(form);


            const data = {

                product:
                    productInput
                        ? productInput.value.trim()
                        : "",

                productId:
                    formData.get("productId") || "",

                name:
                    formData.get("name") || "",

                company:
                    formData.get("company") || "",

                phone:
                    formData.get("phone") || "",

                email:
                    formData.get("email") || "",

                quantity:
                    formData.get("quantity") || "",

                message:
                    formData.get("message") || "",

                website:
                    formData.get("website") || ""

            };


            /**
             * -----------------------------------------------
             * Send Request
             * -----------------------------------------------
             */

            try {

                const response =
                    await fetch(
                        API_URL,
                        {
                            method: "POST",

                            headers: {
                                "Content-Type":
                                    "application/json"
                            },

                            body:
                                JSON.stringify(data)
                        }
                    );


                const result =
                    await response.json();


                if (
                    !response.ok ||
                    !result.success
                ) {

                    throw new Error(
                        result.message ||
                        "Unable to send your enquiry."
                    );

                }


                /**
                 * -------------------------------------------
                 * Success
                 * -------------------------------------------
                 */

                form.reset();


                /*
                 * Restore default quantity.
                 */

                if (quantityInput) {

                    quantityInput.value = "1";

                }


                /*
                 * Clear validation state.
                 */

                form.classList.remove(
                    "was-validated"
                );


                /*
                 * Show success message.
                 */

                successMessage?.classList.remove(
                    "d-none"
                );


                successMessage?.scrollIntoView({

                    behavior: "smooth",

                    block: "center"

                });


            } catch (error) {

                console.error(
                    "Enquiry submission error:",
                    error
                );


                if (errorText) {

                    errorText.textContent =
                        error.message ||
                        "Please try again later.";

                }


                errorMessage?.classList.remove(
                    "d-none"
                );


                errorMessage?.scrollIntoView({

                    behavior: "smooth",

                    block: "center"

                });


            } finally {

                if (submitButton) {

                    submitButton.disabled = false;


                    submitButton.innerHTML =
                        originalButtonHTML;

                }

            }

        }
    );

}


/**
 * ------------------------------------------------------------
 * Escape HTML
 * ------------------------------------------------------------
 */

function escapeHTML(value) {

    return String(value)

        .replaceAll(
            "&",
            "&amp;"
        )

        .replaceAll(
            "<",
            "&lt;"
        )

        .replaceAll(
            ">",
            "&gt;"
        )

        .replaceAll(
            '"',
            "&quot;"
        )

        .replaceAll(
            "'",
            "&#039;"
        );

}