/**
 * ============================================================
 * Product Catalog
 * ------------------------------------------------------------
 * File      : statistics.js
 * Purpose   : Statistics Section
 * Version   : 2.0.0
 * ============================================================
 */

import {
    getProducts,
    getProductCategories,
    getProductSubcategories
} from "../services/product.service.js";


/**
 * ------------------------------------------------------------
 * Render Statistics
 * ------------------------------------------------------------
 */

export function renderStatistics() {

    const statistics =
        document.querySelector("#statistics");

    if (!statistics) return;


    /**
     * --------------------------------------------------------
     * Dynamic Catalogue Counts
     * --------------------------------------------------------
     */

    const productCount =
        getProducts().length;

    const categoryCount =
        getProductCategories()
            .filter(Boolean)
            .length;

    const subcategoryCount =
        getProductSubcategories()
            .filter(Boolean)
            .length;


    /**
     * --------------------------------------------------------
     * Statistics Section
     * --------------------------------------------------------
     */

    statistics.innerHTML = `

<section class="py-5 bg-dark text-white">

    <div class="container">

        <!-- ========================================= -->
        <!-- Section Heading -->
        <!-- ========================================= -->

        <div class="row justify-content-center mb-4">

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

                    LAXMIKANT TRADERS AT A GLANCE

                </span>


                <h2 class="display-6 fw-bold mb-3">

                    Our Product Range

                </h2>


                <p class="lead text-white-50 mb-0">

                    A wide range of industrial safety products
                    for workplaces, businesses and professional
                    requirements.

                </p>

            </div>

        </div>


        <!-- ========================================= -->
        <!-- Statistics -->
        <!-- ========================================= -->

        <div class="row g-0">


            ${statisticCard(
                "bi-box-seam",
                productCount,
                "",
                "Products",
                true
            )}


            ${statisticCard(
                "bi-clock",
                "24",
                " × 7",
                "Availability",
                true
            )}


            ${statisticCard(
                "bi-grid-3x3-gap",
                categoryCount,
                "",
                "Product Categories",
                true
            )}


            ${statisticCard(
                "bi-shield-check",
                subcategoryCount,
                "",
                "Safety Solutions",
                false
            )}


        </div>

    </div>

</section>

`;


    initializeCounters();

}


/**
 * ------------------------------------------------------------
 * Statistic Card
 * ------------------------------------------------------------
 */

function statisticCard(
    icon,
    value,
    suffix,
    label,
    hasDivider
) {

    return `

<div class="col-lg-3 col-md-6">

    <div
        class="
            h-100
            text-center
            px-3
            py-4
            ${hasDivider
                ? "border-end border-secondary"
                : ""}
        ">


        <i
            class="
                bi
                ${icon}
                fs-3
                text-danger
                mb-2
            ">
        </i>


        <div
            class="
                display-6
                fw-bold
                lh-1
                mb-2
            "
            data-stat-value="${value}"
            data-stat-suffix="${suffix}">

            0${suffix}

        </div>


        <p class="text-white-50 mb-0">

            ${label}

        </p>

    </div>

</div>

`;

}


/**
 * ------------------------------------------------------------
 * Counter Animation
 * ------------------------------------------------------------
 */

function initializeCounters() {

    const counters =
        document.querySelectorAll(
            "[data-stat-value]"
        );


    if (!counters.length) return;


    const animateCounters = () => {

        counters.forEach(counter => {

            const target =
                Number(
                    counter.dataset.statValue
                );


            const suffix =
                counter.dataset.statSuffix || "";


            const duration = 1600;


            const startTime =
                performance.now();


            function updateCounter(
                currentTime
            ) {

                const elapsed =
                    currentTime -
                    startTime;


                const progress =
                    Math.min(
                        elapsed / duration,
                        1
                    );


                /*
                 * Ease-out effect.
                 * Starts quickly and slows down
                 * near the target.
                 */

                const easedProgress =
                    1 -
                    Math.pow(
                        1 - progress,
                        3
                    );


                const currentValue =
                    Math.floor(
                        target *
                        easedProgress
                    );


                counter.textContent =
                    currentValue.toLocaleString() +
                    suffix;


                if (progress < 1) {

                    requestAnimationFrame(
                        updateCounter
                    );

                }

            }


            requestAnimationFrame(
                updateCounter
            );

        });

    };


    /**
     * --------------------------------------------------------
     * Start Animation When Visible
     * --------------------------------------------------------
     */

    if ("IntersectionObserver" in window) {

        const observer =
            new IntersectionObserver(
                entries => {

                    entries.forEach(
                        entry => {

                            if (
                                !entry.isIntersecting
                            ) {
                                return;
                            }


                            animateCounters();


                            observer.disconnect();

                        }
                    );

                },
                {
                    threshold: 0.35
                }
            );


        const section =
            document.querySelector(
                "#statistics"
            );


        if (section) {

            observer.observe(section);

        }

    } else {

        animateCounters();

    }

}