import { apiMultipartRequest, apiRequest } from "./api.service.js";

export async function uploadMedia(kind, file) {
    const formData = new FormData();
    formData.append("kind", kind);
    formData.append("image", file);
    return apiMultipartRequest("admin/upload.php", formData).then((response) => response.data);
}

export const uploadProductImage = (file) => uploadMedia("product", file);
export const uploadSupplierLogo = (file) => uploadMedia("supplier", file);

export function deleteUnusedMedia(kind, filename) {
    return apiRequest("admin/upload.php", { method: "DELETE", body: { kind, filename } });
}
