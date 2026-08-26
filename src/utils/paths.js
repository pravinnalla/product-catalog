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
    ] || runtimeMediaUrl("products", filename);

}

export function brandImageUrl(filename) {

    return brandImages[
        `../assets/images/brands/${filename}`
    ] || runtimeMediaUrl("suppliers", filename);

}

const runtimeMediaBase = (
    import.meta.env.VITE_RUNTIME_MEDIA_BASE_URL
    || (import.meta.env.DEV ? "http://localhost:8000/public/uploads" : "/uploads")
).replace(/\/$/, "");

export function runtimeMediaUrl(directory, filename) {
    if (!filename || !["products", "suppliers"].includes(directory)) return "";
    return `${runtimeMediaBase}/${directory}/${encodeURIComponent(filename)}`;
}

const missingImage = `data:image/svg+xml,${encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 180"><rect width="320" height="180" fill="#f1f3f5"/><path d="M115 120l30-35 22 24 16-18 30 29H115z" fill="#adb5bd"/><circle cx="188" cy="65" r="12" fill="#adb5bd"/><text x="160" y="150" text-anchor="middle" font-family="sans-serif" font-size="14" fill="#6c757d">Image unavailable</text></svg>')}`;

export function installImageFallbacks(root) {
    root.addEventListener("error", (event) => {
        const image = event.target;
        if (!(image instanceof HTMLImageElement) || !image.hasAttribute("data-catalogue-image")) return;
        image.removeAttribute("data-catalogue-image");
        image.src = missingImage;
    }, true);
}

export function heroImageUrl(filename) {

    return heroImages[
        `../assets/images/hero/${filename}`
    ] || "";

}
