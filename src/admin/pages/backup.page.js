import { bootstrapAdmin } from "../app.js";
import { createFormDialog } from "../components/dialogs.js";
import { showPageMessage } from "../components/admin-shell.js";
import { createSnapshot, deleteBackup, downloadSnapshot, dryRunRestore, getBackups, restoreBackup } from "../services/backup.service.js";
import { escapeHtml } from "../utils/formatters.js";

const root = await bootstrapAdmin("Backup & Restore", "backup", "Protected Catalogue and Business snapshots and recovery tools");
let domain = "catalog";
let items = [];
let busy = false;

const countLabels = { categories: "Categories", subcategories: "Subcategories", suppliers: "Suppliers", products: "Products", customers: "Customers", receivables: "Receivables", payments: "Payments", refillingItems: "Refilling Items", certificates: "Certificates", certificateItems: "Certificate Items" };
const countText = (counts = {}) => Object.entries(counts).map(([key, value]) => `${countLabels[key] || key}: ${value}`).join(" · ") || "—";
const dateText = (value) => { const date = new Date(value); return Number.isNaN(date.valueOf()) ? value : date.toLocaleString(); };

function setBusy(value) {
    busy = value;
    root.setAttribute("aria-busy", String(value));
    root.querySelectorAll("button, select").forEach((control) => { control.disabled = value; });
}

function render(data) {
    items = data.items;
    const selected = data.domains.find((item) => item.id === domain) || data.domains[0];
    const domainLabel = selected?.label || domain;
    const isBusiness = domain === "business";
    const options = data.domains.map((item) => `<option value="${escapeHtml(item.id)}" ${item.id === domain ? "selected" : ""}>${escapeHtml(item.label)}</option>`).join("");
    const rows = items.map((item, index) => `<tr>
        <td><span class="text-nowrap">${escapeHtml(dateText(item.createdAt))}</span></td>
        <td>${item.type === "snapshot" ? `${escapeHtml(domainLabel)} snapshot` : `${escapeHtml(item.dataset)} backup`}</td>
        <td>${escapeHtml(domainLabel)}</td><td class="backup-counts">${escapeHtml(countText(item.counts))}</td>
        <td><span class="badge ${item.validation === "passed" ? "text-bg-success" : "text-bg-secondary"}">${item.validation === "passed" ? "Validated" : "Not checked"}</span></td>
        <td class="backup-actions"><button class="btn btn-sm btn-outline-secondary" data-action="download" data-index="${index}" ${item.downloadAvailable ? "" : "disabled"}><i class="bi bi-download me-1"></i>Download</button><button class="btn btn-sm btn-outline-primary" data-action="dry-run" data-index="${index}"><i class="bi bi-shield-check me-1"></i>Dry Run</button><button class="btn btn-sm btn-outline-danger" data-action="restore" data-index="${index}"><i class="bi bi-arrow-counterclockwise me-1"></i>Restore</button><button class="btn btn-sm btn-outline-danger" data-action="delete" data-index="${index}" aria-label="Delete ${escapeHtml(item.type === "snapshot" ? `${domainLabel} snapshot` : `${item.dataset} backup`)} ${escapeHtml(item.id)}"><i class="bi bi-trash me-1"></i>Delete</button></td>
    </tr>`).join("");
    root.innerHTML = `<section class="admin-card p-4 mb-4" aria-labelledby="backup-domain-heading"><div class="row g-3 align-items-end"><div class="col-md-7"><h2 id="backup-domain-heading" class="h5">Backup domain</h2><label class="form-label" for="backup-domain">Active registered domain</label><select id="backup-domain" class="form-select admin-filter">${options}</select><p class="small text-body-secondary mt-2 mb-0">${escapeHtml(selected?.description || "Only server-registered domains are available.")}</p></div><div class="col-md-5 text-md-end"><button id="create-snapshot" class="btn btn-danger"><i class="bi bi-archive me-1"></i>Create ${escapeHtml(domainLabel)} Snapshot</button></div></div></section>
    <section class="admin-card mb-4" aria-labelledby="available-backups-heading"><div class="p-4 border-bottom"><h2 id="available-backups-heading" class="h5 mb-1">Available snapshots and backups</h2><p class="text-body-secondary mb-0">Restore uses only validated files already held in protected server storage.</p></div>${rows ? `<div class="table-responsive"><table class="table admin-table align-middle"><thead><tr><th>Created</th><th>Type</th><th>Domain</th><th>Records</th><th>Status</th><th>Actions</th></tr></thead><tbody>${rows}</tbody></table></div>` : `<div class="admin-empty"><i class="bi bi-archive fs-2"></i><span>No ${escapeHtml(domainLabel)} snapshots are available.</span></div>`}</section>
    <section class="admin-card p-4 border-start border-4 border-warning" aria-labelledby="recovery-guidance-heading"><h2 id="recovery-guidance-heading" class="h5">Recovery guidance</h2><p>Always run <strong>Dry Run</strong> before restoring. The server repeats validation during the actual restore and creates a rollback snapshot first.</p><p class="mb-0">${isBusiness ? "A Business restore replaces Customers, Payment Tracking, Refilling Items, and Certificates only. Generated PDFs are not stored." : "<strong>Media safety:</strong> Restoring Catalogue JSON does not automatically restore or delete runtime images. After a restore, run <code>php scripts/audit-runtime-media.php</code> from the private maintenance checkout."}</p></section>`;
    root.querySelector("#backup-domain").addEventListener("change", async (event) => { domain = event.target.value; await load(); });
    root.querySelector("#create-snapshot").addEventListener("click", onCreate);
    root.querySelectorAll("[data-action]").forEach((button) => button.addEventListener("click", onAction));
}

async function load() {
    root.innerHTML = `<div class="admin-loading"><span class="spinner-border text-danger"></span><span>Loading protected backups…</span></div>`;
    try { render(await getBackups(domain)); }
    catch (error) { root.innerHTML = `<div class="alert alert-danger" role="alert">${escapeHtml(error.message)}</div>`; }
    finally { busy = false; root.setAttribute("aria-busy", "false"); }
}

async function onCreate() {
    if (busy) return;
    const label = domain === "business" ? "Business" : "Catalogue";
    showPageMessage(`Creating and validating ${label} snapshot…`, "info");
    setBusy(true);
    try { await createSnapshot(domain); showPageMessage(`${label} snapshot created and validated.`); await load(); }
    catch (error) { showPageMessage(error.message, "danger"); setBusy(false); }
}

async function onAction(event) {
    if (busy) return;
    const item = items[Number(event.currentTarget.dataset.index)];
    if (!item) return;
    if (event.currentTarget.dataset.action === "download") return onDownload(item);
    if (event.currentTarget.dataset.action === "dry-run") return onDryRun(item);
    if (event.currentTarget.dataset.action === "delete") return onDelete(item);
    return onRestore(item);
}

async function onDownload(item) {
    showPageMessage("Preparing validated snapshot download…", "info");
    setBusy(true);
    try {
        const download = await downloadSnapshot(domain, item);
        const url = URL.createObjectURL(download.blob);
        const link = Object.assign(document.createElement("a"), { href: url, download: download.filename });
        link.click(); URL.revokeObjectURL(url); showPageMessage("Snapshot download prepared.");
    } catch (error) { showPageMessage(error.message, "danger"); }
    finally { setBusy(false); }
}

async function onDryRun(item) {
    showPageMessage(`Running full ${domain === "business" ? "Business" : "Catalogue"} validation without making changes…`, "info");
    setBusy(true);
    try {
        const response = await dryRunRestore(domain, item);
        showPageMessage(`DRY RUN PASSED — Current: ${countText(response.result.currentCounts)}. Backup: ${countText(response.result.snapshotCounts)}.`);
    } catch (error) { showPageMessage(`DRY RUN FAILED — ${error.message}`, "danger"); }
    finally { setBusy(false); }
}

async function onRestore(item) {
    showPageMessage("Revalidating the selected backup before confirmation…", "info");
    setBusy(true);
    let dryRun;
    try { dryRun = (await dryRunRestore(domain, item)).result; }
    catch (error) { showPageMessage(`DRY RUN FAILED — ${error.message}`, "danger"); setBusy(false); return; }
    setBusy(false);
    const label = domain === "business" ? "Business" : "Catalogue";
    const impact = domain === "business" ? "Current Customers, Payment Tracking, Refilling Items, and Certificates will be replaced. This affects Business data only." : "Current Catalogue JSON will be replaced. Runtime media will not be changed.";
    const dialog = createFormDialog(`Confirm ${label} restore`, `<div class="alert alert-warning"><strong>Selected:</strong> ${escapeHtml(item.id)}<br><strong>Created:</strong> ${escapeHtml(dateText(item.createdAt))}</div><dl class="row small"><dt class="col-sm-4">Current records</dt><dd class="col-sm-8">${escapeHtml(countText(dryRun.currentCounts))}</dd><dt class="col-sm-4">Backup records</dt><dd class="col-sm-8">${escapeHtml(countText(dryRun.snapshotCounts))}</dd></dl><p>${escapeHtml(impact)} A coordinated pre-restore rollback snapshot will be created.</p><div><label class="form-label" for="restore-confirmation">Type <strong>RESTORE</strong> to continue</label><input id="restore-confirmation" name="confirmation" class="form-control" autocomplete="off" required pattern="RESTORE"></div>`);
    const form = dialog.querySelector("form");
    const submit = form.querySelector(".submit-button"); submit.textContent = `Restore ${label}`;
    form.addEventListener("submit", async (event) => {
        event.preventDefault();
        const confirmation = form.elements.confirmation.value;
        if (confirmation !== "RESTORE") { dialog.querySelector(".dialog-message").innerHTML = `<div class="alert alert-danger">Type RESTORE exactly to continue.</div>`; return; }
        submit.disabled = true; submit.textContent = "Restoring…";
        try { const response = await restoreBackup(domain, item, confirmation); dialog.close("restored"); showPageMessage(`Restore completed and validated. Result: ${countText(response.result.counts)}. Rollback backup created.`); await load(); }
        catch (error) { dialog.querySelector(".dialog-message").innerHTML = `<div class="alert alert-danger">${escapeHtml(error.message)}</div>`; submit.disabled = false; submit.textContent = `Restore ${label}`; }
    });
    dialog.addEventListener("close", () => { dialog.remove(); document.querySelector(`[data-action="restore"][data-index="${items.indexOf(item)}"]`)?.focus(); }, { once: true });
    dialog.showModal(); dialog.querySelector("#restore-confirmation").focus();
}

async function onDelete(item) {
    const label = domain === "business" ? "Business" : "Catalogue";
    const typeLabel = item.type === "snapshot" ? `Complete ${label} snapshot` : "Automatic dataset backup";
    const dialog = createFormDialog("Delete backup item", `<div class="alert alert-warning"><strong>This removes backup files only.</strong> Active ${escapeHtml(label)} data will not be changed.</div><dl class="row small mb-0"><dt class="col-sm-4">Type</dt><dd class="col-sm-8">${escapeHtml(typeLabel)}</dd><dt class="col-sm-4">Created</dt><dd class="col-sm-8">${escapeHtml(dateText(item.createdAt))}</dd><dt class="col-sm-4">Domain</dt><dd class="col-sm-8">${escapeHtml(label)}</dd>${item.dataset ? `<dt class="col-sm-4">Dataset</dt><dd class="col-sm-8">${escapeHtml(item.dataset)}</dd>` : ""}<dt class="col-sm-4">Records</dt><dd class="col-sm-8">${escapeHtml(countText(item.counts))}</dd><dt class="col-sm-4">Identifier</dt><dd class="col-sm-8 text-break"><code>${escapeHtml(item.id)}</code></dd></dl><div><label class="form-label" for="delete-confirmation">Type <strong>DELETE</strong> to continue</label><input id="delete-confirmation" name="confirmation" class="form-control" autocomplete="off" required pattern="DELETE"></div>`);
    const form = dialog.querySelector("form");
    const input = dialog.querySelector("#delete-confirmation");
    const submit = form.querySelector(".submit-button");
    submit.textContent = "Delete backup";
    submit.disabled = true;
    input.addEventListener("input", () => { submit.disabled = input.value !== "DELETE"; });
    form.addEventListener("submit", async (event) => {
        event.preventDefault();
        if (input.value !== "DELETE") return;
        input.disabled = true; submit.disabled = true; submit.textContent = "Deleting…";
        try {
            await deleteBackup(domain, item, input.value);
            dialog.close("deleted");
            showPageMessage(`Backup item deleted. Active ${label} data was unchanged.`);
            await load();
        } catch (error) {
            dialog.querySelector(".dialog-message").innerHTML = `<div class="alert alert-danger">${escapeHtml(error.message)}</div>`;
            input.disabled = false; submit.textContent = "Delete backup"; submit.disabled = input.value !== "DELETE"; input.focus();
        }
    });
    dialog.addEventListener("close", () => {
        dialog.remove();
        document.querySelector(`[data-action="delete"][data-index="${items.indexOf(item)}"]`)?.focus();
    }, { once: true });
    dialog.showModal(); input.focus();
}

await load();
