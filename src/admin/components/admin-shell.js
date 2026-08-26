import logoUrl from "../../assets/images/brands/laxmikant-traders-logo.png";
import { logout } from "../services/auth.service.js";
import { getCsrfToken, redirectToLogin } from "../services/session.service.js";
import { escapeHtml } from "../utils/formatters.js";
import { pageUrl } from "../../utils/paths.js";

const links = [
    ["dashboard", "Dashboard", "speedometer2"], ["categories", "Categories", "tags"],
    ["subcategories", "Subcategories", "diagram-3"], ["suppliers", "Suppliers", "building"],
    ["products", "Products", "box-seam"], ["backup", "Backup & Restore", "archive"],
    ["change-password", "Change Password", "key"],
];

export function renderAdminShell(root, title, active, helper = "") {
    const nav = links.map(([key, label, icon]) => `<a class="nav-link ${key === active ? "active" : ""}" ${key === active ? 'aria-current="page"' : ""} href="${pageUrl(`admin/${key === "change-password" ? "change-password" : key}.html`)}"><i class="bi bi-${icon} me-2"></i>${label}</a>`).join("");
    root.innerHTML = `<header class="admin-topbar sticky-top"><a class="admin-brand" href="${pageUrl("admin/dashboard.html")}" aria-label="Laxmikant Traders admin dashboard"><img class="admin-brand-logo" src="${logoUrl}" alt="Laxmikant Traders"><span class="admin-panel-label">Admin Panel</span></a><button id="admin-logout" class="btn btn-outline-danger btn-sm admin-logout"><i class="bi bi-box-arrow-right me-1"></i>Logout</button></header><nav class="admin-mobile-nav d-lg-none" aria-label="Admin navigation">${nav}</nav><aside class="admin-sidebar d-none d-lg-block"><nav class="nav flex-column gap-1" aria-label="Admin navigation">${nav}<hr class="border-secondary"><span class="text-uppercase text-secondary small px-2">Coming soon</span><span class="text-secondary small px-2 py-1">Invoicing · Quotations · Reports</span></nav></aside><main class="admin-main"><header class="admin-page-header"><div><p class="admin-eyebrow">Catalogue administration</p><h1 class="admin-page-title">${escapeHtml(title)}</h1>${helper ? `<p class="admin-page-helper">${escapeHtml(helper)}</p>` : ""}</div><div id="admin-page-actions" class="admin-page-actions"></div></header><div id="admin-page-message" role="status" aria-live="polite"></div><div id="admin-page-content"></div></main>`;
    root.querySelector("#admin-logout").addEventListener("click", async (event) => {
        event.currentTarget.disabled = true;
        try { await logout(getCsrfToken()); redirectToLogin(); }
        catch { event.currentTarget.disabled = false; showPageMessage("Unable to log out. Please retry.", "danger"); }
    });
    return root.querySelector("#admin-page-content");
}

export function showPageMessage(text, type = "success") {
    const node = document.querySelector("#admin-page-message");
    node.innerHTML = `<div class="alert alert-${type} alert-dismissible" role="alert">${escapeHtml(text)}<button type="button" class="btn-close" aria-label="Close"></button></div>`;
    node.querySelector("button").addEventListener("click", () => { node.innerHTML = ""; });
}
