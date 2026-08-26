import { apiRequest } from "./api.service.js";

const path = (dataset) => `admin/${dataset}.php`;
export const getCollection = (dataset) => apiRequest(path(dataset)).then((r) => r.data);
export const createRecord = (dataset, data) => apiRequest(path(dataset), { method: "POST", body: data }).then((r) => r.data);
export const updateRecord = (dataset, data) => apiRequest(path(dataset), { method: "PATCH", body: data }).then((r) => r.data);
export const deleteRecord = (dataset, id) => apiRequest(path(dataset), { method: "DELETE", body: { id } });

export const getCategories = () => getCollection("categories");
export const getSubcategories = () => getCollection("subcategories");
export const getSuppliers = () => getCollection("suppliers");
export const getProducts = () => getCollection("products");
