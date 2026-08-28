import { apiRequest, apiUrl } from "./api.service.js";

const path = "admin/business/certificates.php";
export const getCertificates = () => apiRequest(path).then((response) => response.data);
export const getCertificate = (id) => apiRequest(`${path}?id=${encodeURIComponent(id)}`).then((response) => response.data);
export const createCertificate = (data) => apiRequest(path, { method: "POST", body: data }).then((response) => response.data);
export const updateCertificate = (id, data) => apiRequest(path, { method: "PATCH", body: { id, ...data } }).then((response) => response.data);
export const deleteCertificate = (id) => apiRequest(path, { method: "DELETE", body: { id } }).then((response) => response.data);
export const getCertificatePdfUrl = (id) => apiUrl(`business/certificate-pdf.php?id=${encodeURIComponent(id)}`);
