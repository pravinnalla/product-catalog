/**
 * ============================================================
 * Product Catalog
 * ------------------------------------------------------------
 * File      : enquiry-form.js
 * Purpose   : Product Enquiry Form
 * Version   : 1.1.0
 * ============================================================
 */

import { getProductById } from "../services/product.service.js";

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

        </label>

        ${
            product
                ? `
                    <input
                        type="text"
                        id="enquiryProduct"
                        class="form-control"
                        value="${product.title}"
                        readonly>

                    <input
                        type="hidden"
                        name="productId"
                        value="${product.id}">
                `
                : `
                    <input
                        type="text"
                        id="enquiryProduct"
                        name="product"
                        class="form-control"
                        placeholder="Enter product name">
                `
        }

    </div>

    <!-- ========================================= -->
    <!-- Name / Company -->
    <!-- ========================================= -->

    <div class="row g-3 mb-4">

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
                required>

            <div class="invalid-feedback">

                Please enter your name.

            </div>

        </div>

        <div class="col-md-6">

            <label
                for="enquiryCompany"
                class="form-label fw-semibold">

                Company Name

            </label>

            <input
                type="text"
                id="enquiryCompany"
                name="company"
                class="form-control"
                placeholder="Company name">

        </div>

    </div>

    <!-- ========================================= -->
    <!-- Phone / Email -->
    <!-- ========================================= -->

    <div class="row g-3 mb-4">

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
                placeholder="+91 98765 43210"
                required>

            <div class="invalid-feedback">

                Please enter your phone number.

            </div>

        </div>

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
                placeholder="you@example.com"
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

        </label>

        <input
            type="number"
            id="enquiryQuantity"
            name="quantity"
            class="form-control"
            min="1"
            placeholder="Enter quantity">

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
            placeholder="Tell us about your requirement..."></textarea>

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

                    Your enquiry has been recorded.
                    Our team will contact you shortly.

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

    if (!form) return;

    form.addEventListener(
        "submit",
        event => {

            event.preventDefault();

            if (!form.checkValidity()) {

                event.stopPropagation();

                form.classList.add(
                    "was-validated"
                );

                return;

            }

            form.classList.remove(
                "was-validated"
            );

            successMessage?.classList.remove(
                "d-none"
            );

            form.reset();

            successMessage?.scrollIntoView({

                behavior: "smooth",

                block: "center"

            });

        }
    );

}