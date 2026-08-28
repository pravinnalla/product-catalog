import { bootstrapAdmin } from "../app.js";
import { createFormDialog } from "../components/dialogs.js";
import { showPageMessage } from "../components/admin-shell.js";
import { createRefillingItem, getRefillingItems, updateRefillingItem } from "../services/refilling-items.service.js";
import { escapeHtml, normalizeSearch } from "../utils/formatters.js";

const root = await bootstrapAdmin("Refilling Items", "refilling-items", "Manage reusable refilling item names and active status");
const pageSize = 10; let items = []; let currentPage = 1;

root.innerHTML = `<section class="admin-card" aria-labelledby="refilling-items-heading"><h2 id="refilling-items-heading" class="visually-hidden">Refilling Items</h2><div class="admin-toolbar"><input id="refilling-item-search" class="form-control admin-search" type="search" placeholder="Search refilling items" aria-label="Search refilling items"></div><div id="refilling-item-table"></div></section>`;
document.querySelector("#admin-page-actions").innerHTML = `<button id="refilling-item-add" class="btn btn-danger"><i class="bi bi-plus-lg me-1"></i>Add Refilling Item</button>`;
const table = root.querySelector("#refilling-item-table");
const statusBadge = (active) => `<span class="badge text-bg-${active ? "success" : "secondary"}">${active ? "Active" : "Inactive"}</span>`;

function filteredItems() {
    const term = normalizeSearch(root.querySelector("#refilling-item-search").value).replace(/\s+/g, " ");
    return items.filter((item) => normalizeSearch(item.name).replace(/\s+/g, " ").includes(term))
        .sort((left, right) => left.name.localeCompare(right.name));
}

function render() {
    const filtered = filteredItems(); const totalPages = Math.max(1, Math.ceil(filtered.length / pageSize)); currentPage = Math.min(currentPage, totalPages);
    const rows = filtered.slice((currentPage - 1) * pageSize, currentPage * pageSize).map((item) => `<tr><td>${escapeHtml(item.name)}</td><td>${statusBadge(item.isActive)}</td><td class="admin-actions text-end"><button class="btn btn-sm btn-outline-primary edit" data-id="${escapeHtml(item.id)}" aria-label="Edit ${escapeHtml(item.name)}"><i class="bi bi-pencil"></i></button></td></tr>`).join("");
    table.innerHTML = `${rows ? `<div class="table-responsive"><table class="table admin-table align-middle"><thead><tr><th>Refilling Item Name</th><th>Status</th><th class="text-end">Actions</th></tr></thead><tbody>${rows}</tbody></table></div>` : `<div class="admin-empty"><i class="bi bi-arrow-repeat fs-2"></i><p class="mb-0">No refilling items found.</p></div>`}<nav class="admin-pagination" aria-label="Refilling Item pages"><button class="btn btn-outline-secondary previous" ${currentPage <= 1 ? "disabled" : ""}>Previous</button><span>Page ${currentPage} of ${totalPages}</span><button class="btn btn-outline-secondary next" ${currentPage >= totalPages ? "disabled" : ""}>Next</button></nav>`;
    table.querySelector(".previous").addEventListener("click", () => { currentPage -= 1; render(); });
    table.querySelector(".next").addEventListener("click", () => { currentPage += 1; render(); });
}

async function load() {
    table.innerHTML = `<div class="admin-loading"><span class="spinner-border text-danger" aria-hidden="true"></span><span>Loading refilling items…</span></div>`;
    items = await getRefillingItems(); render();
}

function openEditor(item = null) {
    const fields = `<div><label class="form-label" for="refilling-item-name">Item Name *</label><input class="form-control" id="refilling-item-name" name="name" type="text" maxlength="160" required></div><div class="form-check"><input class="form-check-input" id="refilling-item-isActive" name="isActive" type="checkbox"><label class="form-check-label" for="refilling-item-isActive">Active</label></div>`;
    const dialog = createFormDialog(item ? "Edit Refilling Item" : "Add Refilling Item", fields); const form = dialog.querySelector("form");
    form.querySelector(".submit-button").textContent = item ? "Save Changes" : "Save Refilling Item";
    form.elements.name.value = item?.name ?? ""; form.elements.isActive.checked = item?.isActive ?? true;
    form.addEventListener("submit", async (event) => {
        event.preventDefault(); form.elements.name.value = form.elements.name.value.trim();
        if (!form.checkValidity()) { form.classList.add("was-validated"); return; }
        const data = { name: form.elements.name.value, isActive: form.elements.isActive.checked };
        const button = form.querySelector(".submit-button"); button.disabled = true; button.textContent = "Saving…";
        try {
            item ? await updateRefillingItem(item.id, data) : await createRefillingItem(data);
            dialog.close(); await load(); showPageMessage(`Refilling item ${item ? "updated" : "created"}.`);
        } catch (error) {
            dialog.querySelector(".dialog-message").innerHTML = `<div class="alert alert-danger">${escapeHtml(error.message)}</div>`;
            button.disabled = false; button.textContent = item ? "Save Changes" : "Save Refilling Item";
        }
    });
    dialog.addEventListener("close", () => dialog.remove(), { once: true }); dialog.showModal();
}

root.querySelector("#refilling-item-search").addEventListener("input", () => { currentPage = 1; render(); });
document.querySelector("#refilling-item-add").addEventListener("click", () => openEditor());
table.addEventListener("click", (event) => { const button = event.target.closest("button[data-id]"); if (!button) return; const item = items.find((entry) => entry.id === button.dataset.id); if (item) openEditor(item); });
try { await load(); } catch (error) { table.innerHTML = `<div class="alert alert-danger m-3" role="alert">${escapeHtml(error.message)}</div>`; }
