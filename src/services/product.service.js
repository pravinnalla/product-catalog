import { fetchPublicJson } from "./api.service.js";

const datasets = ["categories", "subcategories", "suppliers", "products"];
let catalogue = null;
let pendingLoad = null;

async function fetchDataset(name) {
    const response = await fetchPublicJson(`catalog/${name}.php`);
    if (response?.success !== true || !Array.isArray(response.data)) {
        throw new Error("Catalogue information is temporarily unavailable. Please try again.");
    }
    return response.data;
}

function normalizeCatalogue([categories, subcategories, suppliers, products]) {
    const categoryById = new Map(categories.map((item) => [item.id, item]));
    const subcategoryById = new Map(subcategories.map((item) => [item.id, item]));
    const supplierById = new Map(suppliers.map((item) => [item.id, item]));
    const hydratedProducts = products.map((product) => {
        const subcategory = subcategoryById.get(product.subcategoryId);
        const category = subcategory ? categoryById.get(subcategory.categoryId) : undefined;
        return {
            ...product,
            name: product.title,
            categoryId: category?.id || "",
            category: category?.name || "",
            subcategory: subcategory?.name || "",
            supplier: supplierById.get(product.supplierId)?.name || "",
        };
    });
    return { categories, subcategories, suppliers, products: hydratedProducts, categoryById, subcategoryById, supplierById };
}

export async function loadCatalogue({ force = false } = {}) {
    if (catalogue && !force) return catalogue;
    if (pendingLoad) return pendingLoad;
    if (force) catalogue = null;
    pendingLoad = Promise.all(datasets.map(fetchDataset))
        .then(normalizeCatalogue)
        .then((state) => { catalogue = state; return state; })
        .finally(() => { pendingLoad = null; });
    return pendingLoad;
}

export const getProducts = () => catalogue?.products || [];
export const getProductById = (id) => getProducts().find((product) => product.id === id);
export const getProductCategories = () => catalogue?.categories || [];
export const getProductSubcategories = (categoryId) => categoryId
    ? (catalogue?.subcategories || []).filter((subcategory) => subcategory.categoryId === categoryId)
    : (catalogue?.subcategories || []);
export const getSuppliers = () => catalogue?.suppliers || [];
