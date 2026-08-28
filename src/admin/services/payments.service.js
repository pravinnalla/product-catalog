import { apiRequest } from "./api.service.js";

const path = "admin/business/receivables.php";
export const getReceivables = () => apiRequest(path).then((response) => response.data);
export const getReceivable = (id) => apiRequest(`${path}?id=${encodeURIComponent(id)}`).then((response) => response.data);
export const createReceivable = (data) => apiRequest(path, { method: "POST", body: data }).then((response) => response.data);
export const updateReceivable = (id, data) => apiRequest(path, { method: "PATCH", body: { id, ...data } }).then((response) => response.data);
export const deleteReceivable = (id) => apiRequest(path, { method: "DELETE", body: { id } }).then((response) => response.data);
export const addPayment = (id, data) => apiRequest(`${path}?action=payment`, { method: "POST", body: { id, ...data } }).then((response) => response.data);
export const updatePayment = (id, paymentId, data) => apiRequest(`${path}?action=payment`, { method: "PATCH", body: { id, paymentId, ...data } }).then((response) => response.data);
export const deletePayment = (id, paymentId) => apiRequest(`${path}?action=payment`, { method: "DELETE", body: { id, paymentId } }).then((response) => response.data);
