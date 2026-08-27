import { bootstrapAdmin } from "../app.js";
import { getVisitors } from "../services/visitor.service.js";
import { escapeHtml } from "../utils/formatters.js";

const root = await bootstrapAdmin("Visitor Reports", "visitors", "Public website visitor log");
let currentPage = 1;

function formatDate(value) {
    const date = new Date(value);
    return Number.isNaN(date.valueOf()) ? "Unknown" : new Intl.DateTimeFormat("en-IN", {
        dateStyle: "medium", timeStyle: "short", timeZone: "Asia/Kolkata",
    }).format(date);
}

function render(data) {
    const rows = data.items.map((item) => `<tr><td class="text-nowrap">${escapeHtml(formatDate(item.timestamp))}</td><td>${escapeHtml(item.ip)}</td><td>${escapeHtml(item.location)}</td><td>${escapeHtml(item.device)}</td></tr>`).join("");
    const pagination = data.pagination;
    root.innerHTML = `<section class="admin-card" aria-labelledby="visitor-table-heading"><h2 id="visitor-table-heading" class="visually-hidden">Visitor report</h2>${rows ? `<div class="table-responsive"><table class="table admin-table align-middle"><thead><tr><th>Date &amp; Time</th><th>IP Address</th><th>Approximate Location</th><th>Device</th></tr></thead><tbody>${rows}</tbody></table></div>` : `<div class="admin-empty"><i class="bi bi-people fs-2"></i><span>No visitor records are available.</span></div>`}<nav class="admin-pagination" aria-label="Visitor report pages"><button id="visitor-previous" class="btn btn-outline-secondary" ${pagination.page <= 1 ? "disabled" : ""}>Previous</button><span>Page ${pagination.page} of ${pagination.totalPages}</span><button id="visitor-next" class="btn btn-outline-secondary" ${pagination.page >= pagination.totalPages ? "disabled" : ""}>Next</button></nav></section><p class="small text-body-secondary mt-3 mb-0">Visitor records older than 45 days are automatically removed and are not recoverable through this report.</p>`;
    root.querySelector("#visitor-previous").addEventListener("click", () => load(pagination.page - 1));
    root.querySelector("#visitor-next").addEventListener("click", () => load(pagination.page + 1));
}

async function load(page = 1) {
    root.innerHTML = `<div class="admin-loading"><span class="spinner-border text-danger"></span><span>Loading visitor reports…</span></div>`;
    try { const data = await getVisitors(page); currentPage = data.pagination.page; render(data); }
    catch (error) { root.innerHTML = `<div class="alert alert-danger" role="alert">${escapeHtml(error.message)}</div>`; }
}

await load(currentPage);
