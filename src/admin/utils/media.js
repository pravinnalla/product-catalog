import { brandImageUrl, productImageUrl } from "../../utils/paths.js";

const managedPatterns = {
    product: /^prd-[a-f0-9]{24}\.(?:jpg|png|webp)$/,
    supplier: /^sup-[a-f0-9]{24}\.(?:jpg|png|webp)$/,
};

export const mediaLimits = { product: 5 * 1024 * 1024, supplier: 2 * 1024 * 1024 };

export function isRuntimeMedia(kind, filename) {
    return managedPatterns[kind]?.test(String(filename)) || false;
}

export function mediaUrl(kind, filename) {
    if (!filename) return "";
    return kind === "product" ? productImageUrl(filename) : brandImageUrl(filename);
}

export function validateImageFile(file, kind) {
    if (!(file instanceof File) || file.size < 1) return "Choose an image file.";
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) return "Choose a JPEG, PNG, or WebP image.";
    if (file.size > mediaLimits[kind]) return `Image must be ${kind === "product" ? "5 MB" : "2 MB"} or smaller.`;
    if (!/^[^.\\/][^.\\/]*\.(?:jpe?g|png|webp)$/i.test(file.name)) return "Choose an image with one valid file extension.";
    return "";
}

export function attachImagePreview(input, image) {
    let objectUrl = "";
    const fallbackUrl = image.getAttribute("src") || "";
    const clear = () => { if (objectUrl) URL.revokeObjectURL(objectUrl); objectUrl = ""; };
    input.addEventListener("change", () => {
        clear();
        const file = input.files?.[0];
        if (!file) { image.src = fallbackUrl; image.hidden = fallbackUrl === ""; return; }
        objectUrl = URL.createObjectURL(file);
        image.src = objectUrl;
        image.hidden = false;
    });
    return clear;
}
