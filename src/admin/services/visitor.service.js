import { apiRequest } from "./api.service.js";

export function getVisitors(page = 1) {
    return apiRequest(`admin/visitors.php?page=${encodeURIComponent(page)}`);
}
