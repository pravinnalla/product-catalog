import { bootstrapAdmin } from "../app.js";
import { confirmAction, createFormDialog } from "../components/dialogs.js";
import { showPageMessage } from "../components/admin-shell.js";
import { getCustomers } from "../services/customers.service.js";
import { addPayment, createReceivable, deletePayment, deleteReceivable, getReceivable, getReceivables, updatePayment, updateReceivable } from "../services/payments.service.js";
import { escapeHtml, normalizeSearch } from "../utils/formatters.js";

const root = await bootstrapAdmin("Payment Tracking", "payments", "Track invoice references, receipts, balances and due dates");
const pageSize = 10; let receivables = []; let customers = []; let currentPage = 1;
const money = new Intl.NumberFormat("en-IN", { style: "currency", currency: "INR", minimumFractionDigits: 2 });
const formatMoney = (value) => money.format(Number(value));
const formatDate = (value) => value ? new Intl.DateTimeFormat("en-IN", { day: "2-digit", month: "short", year: "numeric" }).format(new Date(`${value}T00:00:00`)) : "—";
const localDateValue = () => { const date = new Date(); const pad = (value) => String(value).padStart(2, "0"); return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`; };
const statusText = (status) => status === "PARTLY_PAID" ? "PARTLY PAID" : status;
const statusBadge = (item) => item.overdue
    ? `<span class="badge text-bg-danger">OVERDUE</span><span class="d-block small text-body-secondary mt-1">${statusText(item.paymentStatus)}</span>`
    : `<span class="badge text-bg-${item.paymentStatus === "PAID" ? "success" : item.paymentStatus === "PARTLY_PAID" ? "warning" : "secondary"}">${statusText(item.paymentStatus)}</span>`;

root.innerHTML = `<section class="admin-card" aria-labelledby="payments-heading"><h2 id="payments-heading" class="visually-hidden">Payment Tracking records</h2><div class="admin-toolbar"><input id="payment-search" class="form-control admin-search" type="search" placeholder="Search invoice or customer" aria-label="Search invoice or customer"><select id="payment-status" class="form-select admin-filter" aria-label="Payment status"><option value="ALL">All</option><option value="UNPAID">Unpaid</option><option value="PARTLY_PAID">Partly Paid</option><option value="PAID">Paid</option><option value="OVERDUE">Overdue</option></select><select id="business-type" class="form-select admin-filter" aria-label="Business type"><option value="ALL">All Types</option><option value="PRODUCT">Product</option><option value="REFILLING">Refilling</option></select></div><div id="payment-table"></div></section>`;
document.querySelector("#admin-page-actions").innerHTML = `<button id="receivable-add" class="btn btn-danger"><i class="bi bi-plus-lg me-1"></i>Add Invoice for Tracking</button>`;
const table = root.querySelector("#payment-table");

function filteredReceivables() {
    const term = normalizeSearch(root.querySelector("#payment-search").value).replace(/\s+/g, " ");
    const status = root.querySelector("#payment-status").value; const type = root.querySelector("#business-type").value;
    return receivables.filter((item) => [item.invoiceNumber, item.customerName].some((value) => normalizeSearch(value).replace(/\s+/g, " ").includes(term)))
        .filter((item) => status === "ALL" || (status === "OVERDUE" ? item.overdue : item.paymentStatus === status))
        .filter((item) => type === "ALL" || item.businessType === type)
        .sort((left, right) => right.invoiceDate.localeCompare(left.invoiceDate) || left.invoiceNumber.localeCompare(right.invoiceNumber));
}

function render() {
    const filtered = filteredReceivables(); const totalPages = Math.max(1, Math.ceil(filtered.length / pageSize)); currentPage = Math.min(currentPage, totalPages);
    const rows = filtered.slice((currentPage - 1) * pageSize, currentPage * pageSize).map((item) => `<tr><td>${escapeHtml(item.invoiceNumber)}</td><td>${escapeHtml(item.customerName)}</td><td>${formatDate(item.invoiceDate)}</td><td>${formatMoney(item.invoiceAmount)}</td><td>${formatMoney(item.amountPaid)}</td><td>${formatMoney(item.balance)}</td><td>${formatDate(item.dueDate)}</td><td>${statusBadge(item)}</td><td class="admin-actions text-end"><button class="btn btn-sm btn-outline-secondary view" data-id="${escapeHtml(item.id)}" aria-label="View ${escapeHtml(item.invoiceNumber)}"><i class="bi bi-eye"></i></button> <button class="btn btn-sm btn-outline-primary edit" data-id="${escapeHtml(item.id)}" aria-label="Edit ${escapeHtml(item.invoiceNumber)}"><i class="bi bi-pencil"></i></button></td></tr>`).join("");
    table.innerHTML = `${rows ? `<div class="table-responsive"><table class="table admin-table align-middle"><thead><tr><th>Invoice No.</th><th>Customer</th><th>Invoice Date</th><th>Invoice Value</th><th>Received</th><th>Balance</th><th>Due Date</th><th>Status</th><th class="text-end">Actions</th></tr></thead><tbody>${rows}</tbody></table></div>` : `<div class="admin-empty"><i class="bi bi-cash-coin fs-2"></i><p class="mb-0">No tracking records found.</p></div>`}<nav class="admin-pagination" aria-label="Payment Tracking pages"><button class="btn btn-outline-secondary previous" ${currentPage <= 1 ? "disabled" : ""}>Previous</button><span>Page ${currentPage} of ${totalPages}</span><button class="btn btn-outline-secondary next" ${currentPage >= totalPages ? "disabled" : ""}>Next</button></nav>`;
    table.querySelector(".previous").addEventListener("click", () => { currentPage -= 1; render(); }); table.querySelector(".next").addEventListener("click", () => { currentPage += 1; render(); });
}

async function load() {
    table.innerHTML = `<div class="admin-loading"><span class="spinner-border text-danger" aria-hidden="true"></span><span>Loading Payment Tracking…</span></div>`;
    [customers, receivables] = await Promise.all([getCustomers(), getReceivables()]); render();
}

const input = (name, label, { type = "text", required = false, maxlength = 160, step = "" } = {}) => `<div><label class="form-label" for="receivable-${name}">${label}${required ? " *" : ""}</label><input class="form-control" id="receivable-${name}" name="${name}" type="${type}" maxlength="${maxlength}" ${step ? `step="${step}"` : ""} ${required ? "required" : ""}></div>`;
function openEditor(item = null) {
    const available = customers.filter((customer) => customer.isActive || customer.id === item?.customerId);
    const customerOptions = available.sort((a, b) => a.name.localeCompare(b.name)).map((customer) => `<option value="${escapeHtml(customer.id)}">${escapeHtml(customer.name)}${customer.isActive ? "" : " (Inactive)"}</option>`).join("");
    const fields = `<div><label class="form-label" for="receivable-customerId">Customer *</label><select class="form-select" id="receivable-customerId" name="customerId" required><option value="">Select customer</option>${customerOptions}</select></div>${input("invoiceNumber", "Invoice Number", { required: true })}${input("invoiceDate", "Invoice Date", { type: "date", required: true })}${input("invoiceAmount", "Invoice Amount", { type: "number", required: true, step: "0.01" })}${input("dueDate", "Due Date", { type: "date" })}<div><label class="form-label" for="receivable-businessType">Business Type *</label><select class="form-select" id="receivable-businessType" name="businessType" required><option value="">Select type</option><option value="PRODUCT">Product</option><option value="REFILLING">Refilling</option></select></div><div><label class="form-label" for="receivable-remarks">Remarks</label><textarea class="form-control" id="receivable-remarks" name="remarks" rows="3" maxlength="2000"></textarea></div>`;
    const dialog = createFormDialog(item ? "Edit Tracking Record" : "Add Invoice for Tracking", fields); const form = dialog.querySelector("form");
    form.querySelector(".submit-button").textContent = item ? "Save Changes" : "Save Tracking Record";
    if (item) ["customerId", "invoiceNumber", "invoiceDate", "invoiceAmount", "dueDate", "businessType", "remarks"].forEach((field) => { form.elements[field].value = item[field] ?? ""; });
    form.addEventListener("submit", async (event) => {
        event.preventDefault(); if (!form.checkValidity()) { form.classList.add("was-validated"); return; }
        const data = Object.fromEntries(["customerId", "invoiceNumber", "invoiceDate", "dueDate", "businessType", "remarks"].map((field) => [field, form.elements[field].value.trim()])); data.invoiceAmount = Number(form.elements.invoiceAmount.value);
        const button = form.querySelector(".submit-button"); button.disabled = true; button.textContent = "Saving…";
        try { item ? await updateReceivable(item.id, data) : await createReceivable(data); dialog.close(); await load(); showPageMessage(`Tracking record ${item ? "updated" : "created"}.`); }
        catch (error) { dialog.querySelector(".dialog-message").innerHTML = `<div class="alert alert-danger">${escapeHtml(error.message)}</div>`; button.disabled = false; button.textContent = item ? "Save Changes" : "Save Tracking Record"; }
    }); dialog.addEventListener("close", () => dialog.remove(), { once: true }); dialog.showModal();
}

function openPaymentEditor(item, payment = null) {
    const remaining = Number(item.balance) + (payment ? Number(payment.amount) : 0);
    const fields = `<p class="alert alert-light border mb-0">Current Balance: <strong>${formatMoney(remaining)}</strong></p>${input("paymentDate", "Payment Date", { type: "date", required: true })}${input("amount", "Amount", { type: "number", required: true, step: "0.01" })}${input("paymentMode", "Payment Mode", { maxlength: 120 })}${input("reference", "Reference", { maxlength: 200 })}<div><label class="form-label" for="receivable-remarks">Remarks</label><textarea class="form-control" id="receivable-remarks" name="remarks" rows="3" maxlength="1000"></textarea></div>`;
    const dialog = createFormDialog(payment ? "Edit Payment" : "Add Payment", fields); const form = dialog.querySelector("form"); form.querySelector(".submit-button").textContent = payment ? "Save Changes" : "Add Payment";
    if (payment) ["paymentDate", "amount", "paymentMode", "reference", "remarks"].forEach((field) => { form.elements[field].value = payment[field] ?? ""; }); else form.elements.paymentDate.value = localDateValue();
    form.addEventListener("submit", async (event) => {
        event.preventDefault(); if (!form.checkValidity()) { form.classList.add("was-validated"); return; }
        const data = Object.fromEntries(["paymentDate", "paymentMode", "reference", "remarks"].map((field) => [field, form.elements[field].value.trim()])); data.amount = Number(form.elements.amount.value);
        const button = form.querySelector(".submit-button"); button.disabled = true;
        try { payment ? await updatePayment(item.id, payment.id, data) : await addPayment(item.id, data); dialog.close(); await load(); showPageMessage(`Payment ${payment ? "updated" : "added"}.`); await openView(item.id); }
        catch (error) { dialog.querySelector(".dialog-message").innerHTML = `<div class="alert alert-danger">${escapeHtml(error.message)}</div>`; button.disabled = false; button.textContent = payment ? "Save Changes" : "Add Payment"; }
    }); dialog.addEventListener("close", () => dialog.remove(), { once: true }); dialog.showModal();
}

async function openView(id) {
    try {
        const item = await getReceivable(id); const paymentRows = item.payments.map((payment) => `<tr><td>${formatDate(payment.paymentDate)}</td><td>${formatMoney(payment.amount)}</td><td>${escapeHtml(payment.paymentMode || "—")}</td><td>${escapeHtml(payment.reference || "—")}</td><td class="text-break">${escapeHtml(payment.remarks || "—")}</td><td class="admin-actions"><button type="button" class="btn btn-sm btn-outline-primary payment-edit" data-payment-id="${escapeHtml(payment.id)}" aria-label="Edit payment"><i class="bi bi-pencil"></i></button> <button type="button" class="btn btn-sm btn-outline-danger payment-delete" data-payment-id="${escapeHtml(payment.id)}" aria-label="Delete payment"><i class="bi bi-trash"></i></button></td></tr>`).join("");
        const details = `<dl class="row mb-0"><dt class="col-sm-5">Invoice Number</dt><dd class="col-sm-7">${escapeHtml(item.invoiceNumber)}</dd><dt class="col-sm-5">Customer</dt><dd class="col-sm-7">${escapeHtml(item.customerName)}${item.customerActive ? "" : ' <span class="badge text-bg-secondary">Inactive</span>'}</dd><dt class="col-sm-5">Invoice Date</dt><dd class="col-sm-7">${formatDate(item.invoiceDate)}</dd><dt class="col-sm-5">Business Type</dt><dd class="col-sm-7">${item.businessType === "PRODUCT" ? "Product" : "Refilling"}</dd><dt class="col-sm-5">Invoice Amount</dt><dd class="col-sm-7">${formatMoney(item.invoiceAmount)}</dd><dt class="col-sm-5">Amount Received</dt><dd class="col-sm-7">${formatMoney(item.amountPaid)}</dd><dt class="col-sm-5">Balance</dt><dd class="col-sm-7">${formatMoney(item.balance)}</dd><dt class="col-sm-5">Due Date</dt><dd class="col-sm-7">${formatDate(item.dueDate)}</dd><dt class="col-sm-5">Status</dt><dd class="col-sm-7">${statusBadge(item)}</dd><dt class="col-sm-5">Remarks</dt><dd class="col-sm-7 text-break">${escapeHtml(item.remarks || "—")}</dd></dl><div class="d-flex flex-wrap gap-2 my-3"><button type="button" id="view-add-payment" class="btn btn-danger"><i class="bi bi-plus-lg me-1"></i>Add Payment</button><button type="button" id="view-delete-record" class="btn btn-outline-danger">Delete Record</button></div><h3 class="h6">Payment History</h3>${paymentRows ? `<div class="table-responsive"><table class="table admin-table align-middle"><thead><tr><th>Payment Date</th><th>Amount</th><th>Mode</th><th>Reference</th><th>Remarks</th><th>Actions</th></tr></thead><tbody>${paymentRows}</tbody></table></div>` : '<p class="text-body-secondary">No payments recorded.</p>'}`;
        const dialog = createFormDialog("View Receivable", details); const form = dialog.querySelector("form"); form.querySelector(".submit-button").textContent = "Edit Tracking Record";
        form.addEventListener("submit", (event) => { event.preventDefault(); dialog.close(); openEditor(item); });
        dialog.querySelector("#view-add-payment").addEventListener("click", () => { dialog.close(); openPaymentEditor(item); });
        dialog.querySelector("#view-delete-record").addEventListener("click", async () => { dialog.close(); if (!await confirmAction("Delete this Payment Tracking record?", "This will permanently remove the tracked invoice reference and all payments recorded against it. This action cannot be undone.", "Delete Record")) return; try { await deleteReceivable(item.id); await load(); showPageMessage("Tracking record deleted."); } catch (error) { showPageMessage(error.message, "danger"); } });
        dialog.querySelectorAll(".payment-edit").forEach((button) => button.addEventListener("click", () => { const payment = item.payments.find((entry) => entry.id === button.dataset.paymentId); dialog.close(); if (payment) openPaymentEditor(item, payment); }));
        dialog.querySelectorAll(".payment-delete").forEach((button) => button.addEventListener("click", async () => { dialog.close(); if (!await confirmAction("Delete this payment?", "The received amount, balance and payment status will be recalculated.", "Delete Payment")) return; try { await deletePayment(item.id, button.dataset.paymentId); await load(); showPageMessage("Payment deleted."); await openView(item.id); } catch (error) { showPageMessage(error.message, "danger"); } }));
        dialog.addEventListener("close", () => dialog.remove(), { once: true }); dialog.showModal();
    } catch (error) { showPageMessage(error.message, "danger"); }
}

["#payment-search", "#payment-status", "#business-type"].forEach((selector) => root.querySelector(selector).addEventListener(selector === "#payment-search" ? "input" : "change", () => { currentPage = 1; render(); }));
document.querySelector("#receivable-add").addEventListener("click", () => openEditor());
table.addEventListener("click", (event) => { const button = event.target.closest("button[data-id]"); if (!button) return; const item = receivables.find((entry) => entry.id === button.dataset.id); if (!item) return; button.classList.contains("view") ? openView(item.id) : openEditor(item); });
try { await load(); } catch (error) { table.innerHTML = `<div class="alert alert-danger m-3" role="alert">${escapeHtml(error.message)}</div>`; }
