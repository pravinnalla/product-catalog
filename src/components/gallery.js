/**
 * ============================================================
 * Product Catalog
 * ------------------------------------------------------------
 * File      : gallery.js
 * Purpose   : Product Gallery Section
 * Version   : 1.0.0
 * ============================================================
 */

export function renderGallery() {

    const gallery = document.querySelector("#gallery");

    if (!gallery) return;

    gallery.innerHTML = `

<section class="py-5">

    <div class="container">

        <!-- ========================================= -->
        <!-- Section Heading -->
        <!-- ========================================= -->

        <div class="row justify-content-center mb-5">

            <div class="col-lg-8 text-center">

                <span class="badge rounded-pill text-bg-danger px-3 py-2 mb-3">

                    OUR GALLERY

                </span>

                <h2 class="display-6 fw-bold mb-3">

                    Safety In Action

                </h2>

                <p class="lead text-secondary mb-0">

                    Explore our range of industrial safety products
                    and workplace protection solutions.

                </p>

            </div>

        </div>

        <!-- ========================================= -->
        <!-- Gallery Grid -->
        <!-- ========================================= -->

        <div class="row g-4">

            ${galleryItem(
                "https://picsum.photos/800/600?random=201",
                "Industrial Safety Equipment"
            )}

            ${galleryItem(
                "https://picsum.photos/800/600?random=202",
                "Personal Protective Equipment"
            )}

            ${galleryItem(
                "https://picsum.photos/800/600?random=203",
                "Industrial Safety Helmets"
            )}

            ${galleryItem(
                "https://picsum.photos/800/600?random=204",
                "Safety Gloves"
            )}

            ${galleryItem(
                "https://picsum.photos/800/600?random=205",
                "Industrial Safety Footwear"
            )}

            ${galleryItem(
                "https://picsum.photos/800/600?random=206",
                "Fire Safety Equipment"
            )}

            ${galleryItem(
                "https://picsum.photos/800/600?random=207",
                "Workplace Protection"
            )}

            ${galleryItem(
                "https://picsum.photos/800/600?random=208",
                "Fall Protection Equipment"
            )}

        </div>

    </div>

</section>

`;

}

/**
 * ------------------------------------------------------------
 * Gallery Item
 * ------------------------------------------------------------
 */

function galleryItem(image, title) {

    return `

<div class="col-xl-3 col-lg-3 col-md-6 col-12">

    <div class="card border-0 shadow-sm overflow-hidden">

        <img
            src="${image}"
            class="img-fluid"
            alt="${title}"
            loading="lazy">

        <div class="card-body text-center">

            <h6 class="fw-semibold mb-0">

                ${title}

            </h6>

        </div>

    </div>

</div>

`;

}