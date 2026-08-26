import { getSession } from "./auth.service.js";

let csrfToken = "";
let redirecting = false;

export async function requireAdminSession() {
    try {
        const session = await getSession();
        if (!session.authenticated || !session.csrfToken) throw new Error("Unauthenticated");
        csrfToken = session.csrfToken;
        return session;
    } catch {
        redirectToLogin();
        throw new Error("Authentication required.");
    }
}

export function getCsrfToken() { return csrfToken; }
export function clearSessionMemory() { csrfToken = ""; }

export function redirectToLogin(expired = false) {
    if (redirecting) return;
    redirecting = true;
    clearSessionMemory();
    if (expired) window.alert("Your admin session has expired. Please sign in again.");
    window.location.replace("/admin-login.html");
}
