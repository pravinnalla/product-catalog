/**
 * Vite-aware URLs for runtime-rendered content.
 *
 * Product and brand file names come from data/runtime templates, so they
 * cannot be resolved from a static import at the call site. Eager glob
 * imports retain the existing src/assets image structure while allowing Vite
 * to emit production asset URLs with the configured base path.
 */

const productImages = import.meta.glob(
    "../assets/images/products/*",
    {
        eager: true,
        query: "?url",
        import: "default"
    }
);

const brandImages = import.meta.glob(
    "../assets/images/brands/*",
    {
        eager: true,
        query: "?url",
        import: "default"
    }
);

const heroImages = import.meta.glob(
    "../assets/images/hero/*",
    {
        eager: true,
        query: "?url",
        import: "default"
    }
);

export function pageUrl(path) {

    return `${import.meta.env.BASE_URL}${path}`;

}

export function productImageUrl(filename) {

    return productImages[
        `../assets/images/products/${filename}`
    ] || "";

}

export function brandImageUrl(filename) {

    return brandImages[
        `../assets/images/brands/${filename}`
    ] || "";

}

export function heroImageUrl(filename) {

    return heroImages[
        `../assets/images/hero/${filename}`
    ] || "";

}
