import { apiRequest } from "./api.service.js";

const path = "admin/business/refilling-items.php";
export const getRefillingItems = () => apiRequest(path).then((response) => response.data);
export const createRefillingItem = (data) => apiRequest(path, { method: "POST", body: data }).then((response) => response.data);
export const updateRefillingItem = (id, data) => apiRequest(path, { method: "PATCH", body: { id, ...data } }).then((response) => response.data);
