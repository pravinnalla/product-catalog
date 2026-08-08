/**
 * ============================================================
 * Product Catalog
 * ------------------------------------------------------------
 * File      : statistics.js
 * Purpose   : Statistics Section
 * Version   : 1.4.0
 * ============================================================
 */

export function renderStatistics() {

    const statistics = document.querySelector("#statistics");

    if (!statistics) return;

    statistics.innerHTML = `

<section class="py-5 bg-dark text-white">

    <div class="container">

        <!-- ========================================= -->
        <!-- Section Heading -->
        <!-- ========================================= -->

        <div class="row justify-content-center mb-4">

            <div class="col-lg-8 text-center">

                <span class="badge rounded-pill text-bg-danger px-3 py-2 mb-3">

                    SAFETYMART AT A GLANCE

                </span>

                <h2 class="display-6 fw-bold mb-3">

                    Safety Solutions You Can Count On

                </h2>

                <p class="lead text-white-50 mb-0">

                    Quality products, trusted brands and reliable
                    safety solutions for businesses and professionals.

                </p>

            </div>

        </div>

        <!-- ========================================= -->
        <!-- Statistics -->
        <!-- ========================================= -->

        <div class="row g-0">

            ${statisticCard(
                "bi-box-seam",
                500,
                "+",
                "Products",
                true
            )}

            ${statisticCard(
                "bi-award",
                50,
                "+",
                "Trusted Brands",
                true
            )}

            ${statisticCard(
                "bi-people",
                1000,
                "+",
                "Customers",
                true
            )}

            ${statisticCard(
                "bi-calendar-check",
                10,
                "+",
                "Years of Experience",
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
            ${hasDivider ? "border-end border-secondary" : ""}
        ">

        <i class="bi ${icon} fs-3 text-danger mb-2"></i>

        <div
            class="display-6 fw-bold lh-1 mb-2"
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

    const counters = document.querySelectorAll(
        "[data-stat-value]"
    );

    if (!counters.length) return;

    const animateCounters = () => {

        counters.forEach(counter => {

            const target = Number(
                counter.dataset.statValue
            );

            const suffix =
                counter.dataset.statSuffix || "";

            const duration = 1600;

            const startTime = performance.now();

            function updateCounter(currentTime) {

                const elapsed =
                    currentTime - startTime;

                const progress =
                    Math.min(elapsed / duration, 1);

                /*
                 * Ease-out effect.
                 * Starts quickly and slows down near the target.
                 */

                const easedProgress =
                    1 - Math.pow(1 - progress, 3);

                const currentValue =
                    Math.floor(target * easedProgress);

                counter.textContent =
                    currentValue.toLocaleString() + suffix;

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

    /*
     * Start animation only when the section
     * becomes visible.
     */

    if ("IntersectionObserver" in window) {

        const observer =
            new IntersectionObserver(
                entries => {

                    entries.forEach(entry => {

                        if (!entry.isIntersecting) return;

                        animateCounters();

                        observer.disconnect();

                    });

                },
                {
                    threshold: 0.35
                }
            );

        observer.observe(
            document.querySelector("#statistics")
        );

    } else {

        animateCounters();

    }

}