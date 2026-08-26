import { getCsrfToken, redirectToLogin } from "./session.service.js";

const API_BASE = (
    import.meta.env.VITE_API_BASE_URL
    || import.meta.env.VITE_API_BASE
    || (import.meta.env.DEV ? "http://localhost:8000/api" : "/api")
).replace(/\/$/, "");

export class ApiError extends Error {
    constructor(message, status = 0, details = {}) { super(message); this.status = status; this.details = details; }
}

export async function apiRequest(path, { method = "GET", body } = {}) {
    const headers = { Accept: "application/json" };
    if (body !== undefined) headers["Content-Type"] = "application/json";
    if (["POST", "PATCH", "DELETE"].includes(method)) headers["X-CSRF-Token"] = getCsrfToken();

    let response;
    try {
        response = await fetch(`${API_BASE}/${path}`, {
            method, credentials: "include", headers,
            ...(body !== undefined ? { body: JSON.stringify(body) } : {}),
        });
    } catch {
        throw new ApiError("Unable to reach the server. Please try again.");
    }

    let data = {};
    try { data = await response.json(); } catch { throw new ApiError("The server returned an invalid response.", response.status); }
    if (response.status === 401) { redirectToLogin(true); throw new ApiError("Session expired.", 401); }
    if (!response.ok) throw new ApiError(data.message || friendlyStatus(response.status), response.status, data);
    return data;
}

export async function apiMultipartRequest(path, formData) {
    const headers = { Accept: "application/json", "X-CSRF-Token": getCsrfToken() };
    let response;
    try {
        response = await fetch(`${API_BASE}/${path}`, {
            method: "POST", credentials: "include", headers, body: formData,
        });
    } catch {
        throw new ApiError("Unable to reach the server. Please try again.");
    }
    let data = {};
    try { data = await response.json(); } catch { throw new ApiError("The server returned an invalid response.", response.status); }
    if (response.status === 401) { redirectToLogin(true); throw new ApiError("Session expired.", 401); }
    if (!response.ok) throw new ApiError(data.message || friendlyStatus(response.status), response.status, data);
    return data;
}

function friendlyStatus(status) {
    if (status === 403) return "Security validation failed. Please retry.";
    if (status === 409) return "This change conflicts with existing catalogue data.";
    if (status === 429) return "Too many requests. Please try again shortly.";
    if (status >= 500) return "Unable to complete the request. Please try again.";
    return "Unable to complete the request.";
}
