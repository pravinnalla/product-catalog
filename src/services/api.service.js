const configuredBase = import.meta.env.VITE_API_BASE_URL
    || import.meta.env.VITE_API_BASE
    || (import.meta.env.DEV ? "http://localhost:8000/api" : "/api");
export const PUBLIC_API_BASE = configuredBase.replace(/\/$/, "");

export function apiUrl(path) {
    return `${PUBLIC_API_BASE}/${String(path).replace(/^\//, "")}`;
}

export async function fetchPublicJson(path) {
    let response;
    try {
        response = await fetch(apiUrl(path), { headers: { Accept: "application/json" } });
    } catch {
        throw new Error("Catalogue information is temporarily unavailable. Please try again.");
    }
    if (!response.ok) throw new Error("Catalogue information is temporarily unavailable. Please try again.");
    try { return await response.json(); }
    catch { throw new Error("Catalogue information is temporarily unavailable. Please try again."); }
}
