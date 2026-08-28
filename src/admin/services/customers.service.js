import { apiRequest } from "./api.service.js";

const path = "admin/business/customers.php";
export const getCustomers = () => apiRequest(path).then((response) => response.data);
export const getCustomer = (id) => apiRequest(`${path}?id=${encodeURIComponent(id)}`).then((response) => response.data);
export const createCustomer = (data) => apiRequest(path, { method: "POST", body: data }).then((response) => response.data);
export const updateCustomer = (id, data) => apiRequest(path, { method: "PATCH", body: { id, ...data } }).then((response) => response.data);
