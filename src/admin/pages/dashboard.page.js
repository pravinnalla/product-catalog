import { bootstrapAdmin } from "../app.js";
import { getCategories, getProducts, getSubcategories, getSuppliers } from "../services/catalog-admin.service.js";
import { escapeHtml } from "../utils/formatters.js";
import { pageUrl } from "../../utils/paths.js";

const root = await bootstrapAdmin("Dashboard", "dashboard", "Overview of the private runtime catalogue");
root.innerHTML = `<div class="admin-loading"><span class="spinner-border text-danger"></span><span>Loading catalogue summary…</span></div>`;
try {
    const values = await Promise.all([getCategories(), getSubcategories(), getSuppliers(), getProducts()]);
    const cards = [["Categories", values[0].length, "tags", "categories"], ["Subcategories", values[1].length, "diagram-3", "subcategories"], ["Suppliers", values[2].length, "building", "suppliers"], ["Products", values[3].length, "box-seam", "products"]];
    root.innerHTML = `<div class="row g-3">${cards.map(([label, count, icon, page]) => `<div class="col-sm-6 col-xl-3"><a class="admin-card dashboard-card p-4 text-decoration-none d-block h-100" href="${pageUrl(`admin/${page}.html`)}"><i class="bi bi-${icon} fs-3 text-danger"></i><div class="display-6 fw-semibold mt-3">${count}</div><div class="text-body-secondary">${escapeHtml(label)}</div></a></div>`).join("")}</div><div class="row g-3 mt-1"><div class="col-sm-6 col-xl-4"><a class="admin-card dashboard-card p-4 text-decoration-none d-block h-100" href="${pageUrl("admin/backup.html")}"><i class="bi bi-archive fs-3 text-danger"></i><h2 class="h5 mt-3 mb-1">Backup & Restore</h2><p class="text-body-secondary mb-0">Create, validate, download, and restore protected catalogue snapshots.</p></a></div></div><div class="admin-card p-4 mt-4"><h2 class="h5">Catalogue management</h2><p class="text-body-secondary mb-0">Choose a section to review and maintain the private runtime catalogue. Changes are validated and saved through the protected admin API.</p></div>`;
} catch (error) { root.innerHTML = `<div class="alert alert-danger">${escapeHtml(error.message)}</div>`; }
