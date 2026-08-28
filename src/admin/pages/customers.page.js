import { bootstrapAdmin } from "../app.js";
import { createFormDialog } from "../components/dialogs.js";
import { showPageMessage } from "../components/admin-shell.js";
import { createCustomer, getCustomer, getCustomers, updateCustomer } from "../services/customers.service.js";
import { escapeHtml, normalizeSearch } from "../utils/formatters.js";

const root = await bootstrapAdmin("Customers", "customers", "Manage customer contact details and active status");
const pageSize = 10;
let customers = [];
let currentPage = 1;

root.innerHTML = `<section class="admin-card" aria-labelledby="customers-heading"><h2 id="customers-heading" class="visually-hidden">Customers</h2><div class="admin-toolbar"><input id="customer-search" class="form-control admin-search" type="search" placeholder="Search customers" aria-label="Search customers"></div><div id="customer-table"></div></section>`;
document.querySelector("#admin-page-actions").innerHTML = `<button id="customer-add" class="btn btn-danger"><i class="bi bi-plus-lg me-1"></i>Add Customer</button>`;
const table = root.querySelector("#customer-table");

const statusBadge = (active) => `<span class="badge text-bg-${active ? "success" : "secondary"}">${active ? "Active" : "Inactive"}</span>`;

function filteredCustomers() {
    const term = normalizeSearch(root.querySelector("#customer-search").value).replace(/\s+/g, " ");
    return customers.filter((customer) => [customer.name, customer.gstin, customer.contactPerson, customer.phone]
        .some((value) => normalizeSearch(value).replace(/\s+/g, " ").includes(term)))
        .sort((left, right) => left.name.localeCompare(right.name));
}

function render() {
    const filtered = filteredCustomers();
    const totalPages = Math.max(1, Math.ceil(filtered.length / pageSize));
    currentPage = Math.min(currentPage, totalPages);
    const visible = filtered.slice((currentPage - 1) * pageSize, currentPage * pageSize);
    const rows = visible.map((customer) => `<tr><td>${escapeHtml(customer.name)}</td><td>${escapeHtml(customer.gstin || "—")}</td><td>${escapeHtml(customer.contactPerson || "—")}</td><td>${escapeHtml(customer.phone || "—")}</td><td>${statusBadge(customer.isActive)}</td><td class="admin-actions text-end"><button class="btn btn-sm btn-outline-secondary view" data-id="${escapeHtml(customer.id)}" aria-label="View ${escapeHtml(customer.name)}"><i class="bi bi-eye"></i></button> <button class="btn btn-sm btn-outline-primary edit" data-id="${escapeHtml(customer.id)}" aria-label="Edit ${escapeHtml(customer.name)}"><i class="bi bi-pencil"></i></button></td></tr>`).join("");
    table.innerHTML = `${rows ? `<div class="table-responsive"><table class="table admin-table align-middle"><thead><tr><th>Customer Name</th><th>GSTIN</th><th>Contact Person</th><th>Phone</th><th>Status</th><th class="text-end">Actions</th></tr></thead><tbody>${rows}</tbody></table></div>` : `<div class="admin-empty"><i class="bi bi-person-vcard fs-2"></i><p class="mb-0">No customers found.</p></div>`}<nav class="admin-pagination" aria-label="Customer pages"><button class="btn btn-outline-secondary previous" ${currentPage <= 1 ? "disabled" : ""}>Previous</button><span>Page ${currentPage} of ${totalPages}</span><button class="btn btn-outline-secondary next" ${currentPage >= totalPages ? "disabled" : ""}>Next</button></nav>`;
    table.querySelector(".previous").addEventListener("click", () => { currentPage -= 1; render(); });
    table.querySelector(".next").addEventListener("click", () => { currentPage += 1; render(); });
}

async function load() {
    table.innerHTML = `<div class="admin-loading"><span class="spinner-border text-danger" aria-hidden="true"></span><span>Loading customers…</span></div>`;
    customers = await getCustomers();
    render();
}

const inputField = (name, label, { required = false, type = "text", maxlength = 1024 } = {}) => `<div><label class="form-label" for="customer-${name}">${label}${required ? " *" : ""}</label><input class="form-control" id="customer-${name}" name="${name}" type="${type}" maxlength="${maxlength}" ${required ? "required" : ""}></div>`;

function openEditor(customer = null) {
    const fields = `${inputField("name", "Customer Name", { required: true, maxlength: 160 })}<div><label class="form-label" for="customer-address">Address *</label><textarea class="form-control" id="customer-address" name="address" rows="3" maxlength="2000" required></textarea></div>${inputField("gstin", "GSTIN", { maxlength: 32 })}${inputField("contactPerson", "Contact Person", { maxlength: 160 })}${inputField("phone", "Phone", { type: "tel", maxlength: 80 })}${inputField("email", "Email", { type: "email", maxlength: 254 })}<div class="form-check"><input class="form-check-input" id="customer-isActive" name="isActive" type="checkbox"><label class="form-check-label" for="customer-isActive">Active</label></div>`;
    const dialog = createFormDialog(customer ? "Edit Customer" : "Add Customer", fields);
    const form = dialog.querySelector("form");
    form.querySelector(".submit-button").textContent = customer ? "Save Changes" : "Save Customer";
    if (customer) {
        ["name", "address", "gstin", "contactPerson", "phone", "email"].forEach((field) => { form.elements[field].value = customer[field]; });
        form.elements.isActive.checked = customer.isActive;
    } else form.elements.isActive.checked = true;
    form.addEventListener("submit", async (event) => {
        event.preventDefault();
        ["name", "address", "gstin", "contactPerson", "phone", "email"].forEach((field) => { form.elements[field].value = form.elements[field].value.trim(); });
        if (!form.checkValidity()) { form.classList.add("was-validated"); return; }
        const data = Object.fromEntries(["name", "address", "gstin", "contactPerson", "phone", "email"].map((field) => [field, form.elements[field].value]));
        data.isActive = form.elements.isActive.checked;
        const button = form.querySelector(".submit-button"); button.disabled = true; button.textContent = "Saving…";
        try {
            customer ? await updateCustomer(customer.id, data) : await createCustomer(data);
            dialog.close(); await load(); showPageMessage(`Customer ${customer ? "updated" : "created"}.`);
        } catch (error) {
            dialog.querySelector(".dialog-message").innerHTML = `<div class="alert alert-danger">${escapeHtml(error.message)}</div>`;
            button.disabled = false; button.textContent = customer ? "Save Changes" : "Save Customer";
        }
    });
    dialog.addEventListener("close", () => dialog.remove(), { once: true });
    dialog.showModal();
}

async function openView(id) {
    try {
        const customer = await getCustomer(id);
        const value = (text) => escapeHtml(text || "—");
        const dialog = createFormDialog("Customer Details", `<dl class="row mb-0"><dt class="col-sm-4">Customer Name</dt><dd class="col-sm-8">${value(customer.name)}</dd><dt class="col-sm-4">Address</dt><dd class="col-sm-8 text-break">${value(customer.address)}</dd><dt class="col-sm-4">GSTIN</dt><dd class="col-sm-8">${value(customer.gstin)}</dd><dt class="col-sm-4">Contact Person</dt><dd class="col-sm-8">${value(customer.contactPerson)}</dd><dt class="col-sm-4">Phone</dt><dd class="col-sm-8">${value(customer.phone)}</dd><dt class="col-sm-4">Email</dt><dd class="col-sm-8 text-break">${value(customer.email)}</dd><dt class="col-sm-4">Status</dt><dd class="col-sm-8">${statusBadge(customer.isActive)}</dd></dl><hr><h3 class="h6">Payment Tracking</h3><p class="text-body-secondary">Invoice references and received payments are managed from the Payment Tracking page.</p><h3 class="h6">Certificates</h3><p class="text-body-secondary mb-0">Saved service Certificates for this Customer are managed from the Certificates page.</p>`);
        const form = dialog.querySelector("form");
        form.querySelector(".submit-button").textContent = "Edit Customer";
        form.addEventListener("submit", (event) => { event.preventDefault(); dialog.close(); openEditor(customer); });
        dialog.addEventListener("close", () => dialog.remove(), { once: true });
        dialog.showModal();
    } catch (error) { showPageMessage(error.message, "danger"); }
}

root.querySelector("#customer-search").addEventListener("input", () => { currentPage = 1; render(); });
document.querySelector("#customer-add").addEventListener("click", () => openEditor());
table.addEventListener("click", (event) => {
    const button = event.target.closest("button[data-id]"); if (!button) return;
    const customer = customers.find((item) => item.id === button.dataset.id); if (!customer) return;
    button.classList.contains("view") ? openView(customer.id) : openEditor(customer);
});

try { await load(); } catch (error) { table.innerHTML = `<div class="alert alert-danger m-3" role="alert">${escapeHtml(error.message)}</div>`; }
