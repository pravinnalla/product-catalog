import { createFormDialog, confirmAction } from "./dialogs.js";
import { showPageMessage } from "./admin-shell.js";
import { createRecord, deleteRecord, getCollection, updateRecord } from "../services/catalog-admin.service.js";
import { escapeHtml, normalizeSearch } from "../utils/formatters.js";
import { attachImagePreview, isRuntimeMedia, mediaUrl, validateImageFile } from "../utils/media.js";
import { deleteUnusedMedia } from "../services/upload.service.js";

const fieldMarkup = (field) => `<div class="mb-3"><label class="form-label" for="field-${field.name}">${escapeHtml(field.label)}</label><input class="form-control" id="field-${field.name}" name="${field.name}" maxlength="${field.max || 1024}" required></div>`;

export async function mountEntityPage(root, config) {
    let records = [];
    root.innerHTML = `<div class="admin-card"><div class="admin-toolbar"><input id="entity-search" class="form-control admin-search" type="search" placeholder="Search ${escapeHtml(config.label.toLowerCase())}" aria-label="Search"></div><div id="entity-table"></div></div>`;
    document.querySelector("#admin-page-actions").innerHTML = `<button id="entity-add" class="btn btn-danger"><i class="bi bi-plus-lg me-1"></i>Add ${escapeHtml(config.singular)}</button>`;
    const table = root.querySelector("#entity-table");
    const render = () => {
        const term = normalizeSearch(root.querySelector("#entity-search").value);
        const filtered = records.filter((item) => config.fields.some((field) => normalizeSearch(item[field.name]).includes(term))).sort((a, b) => String(a[config.sortField]).localeCompare(String(b[config.sortField])));
        if (!filtered.length) { table.innerHTML = `<div class="admin-empty"><i class="bi bi-inbox fs-2"></i><p class="mb-0">No ${escapeHtml(config.label.toLowerCase())} found.</p></div>`; return; }
        const cell = (item, column) => config.media?.field === column.key
            ? `<img class="admin-media-thumb" src="${escapeHtml(mediaUrl(config.media.kind, item[column.key]))}" alt="${escapeHtml(item[config.sortField])} ${escapeHtml(config.media.label.toLowerCase())}">`
            : escapeHtml(item[column.key]);
        table.innerHTML = `<div class="table-responsive"><table class="table admin-table align-middle"><thead><tr>${config.columns.map((c) => `<th>${escapeHtml(c.label)}</th>`).join("")}<th class="text-end">Actions</th></tr></thead><tbody>${filtered.map((item) => `<tr>${config.columns.map((c) => `<td>${cell(item, c)}</td>`).join("")}<td class="admin-actions text-end"><button class="btn btn-sm btn-outline-primary edit" data-id="${item.id}" aria-label="Edit ${escapeHtml(item[config.sortField])}"><i class="bi bi-pencil"></i></button> <button class="btn btn-sm btn-outline-danger delete" data-id="${item.id}" aria-label="Delete ${escapeHtml(item[config.sortField])}"><i class="bi bi-trash"></i></button></td></tr>`).join("")}</tbody></table></div>`;
    };
    async function load() { table.innerHTML = `<div class="admin-loading"><span class="spinner-border text-danger" aria-hidden="true"></span><span>Loading…</span></div>`; records = await getCollection(config.dataset); render(); }
    async function openEditor(record = null) {
        const mediaMarkup = config.media ? `<div><label class="form-label" for="entity-media">${record ? `Choose new ${escapeHtml(config.media.label.toLowerCase())}` : escapeHtml(config.media.label)}</label><input class="form-control" id="entity-media" name="media" type="file" accept="image/jpeg,image/png,image/webp" ${record ? "" : "required"}><div class="form-text">JPEG, PNG, or WebP. Maximum ${config.media.kind === "product" ? "5 MB" : "2 MB"}.</div><img class="admin-media-preview mt-3" src="${record ? escapeHtml(mediaUrl(config.media.kind, record[config.media.field])) : ""}" alt="${escapeHtml(config.media.label)} preview" ${record ? "" : "hidden"}></div>` : "";
        const dialog = createFormDialog(`${record ? "Edit" : "Add"} ${config.singular}`, config.fields.map(fieldMarkup).join("") + mediaMarkup);
        const clearPreview = config.media ? attachImagePreview(dialog.querySelector("#entity-media"), dialog.querySelector(".admin-media-preview")) : () => {};
        if (record) config.fields.forEach((field) => { dialog.querySelector(`[name="${field.name}"]`).value = record[field.name]; });
        dialog.querySelector("form").addEventListener("submit", async (event) => {
            event.preventDefault(); const form = event.currentTarget;
            if (!form.checkValidity()) { form.classList.add("was-validated"); return; }
            const button = form.querySelector(".submit-button"); button.disabled = true;
            const data = Object.fromEntries(config.fields.map((field) => [field.name, form.elements[field.name].value.trim()]));
            let uploaded = null;
            try {
                if (config.media) {
                    const file = form.elements.media.files?.[0];
                    if (file) {
                        const validationError = validateImageFile(file, config.media.kind);
                        if (validationError) throw new Error(validationError);
                        button.textContent = "Uploading…";
                        uploaded = await config.media.upload(file);
                        data[config.media.field] = uploaded.filename;
                    } else if (record) data[config.media.field] = record[config.media.field];
                    else throw new Error(`${config.media.label} is required.`);
                }
                button.textContent = "Saving…";
                record ? await updateRecord(config.dataset, { id: record.id, ...data }) : await createRecord(config.dataset, data);
                if (record && uploaded && isRuntimeMedia(config.media.kind, record[config.media.field])) {
                    deleteUnusedMedia(config.media.kind, record[config.media.field]).catch(() => {});
                }
                dialog.close(); await load(); showPageMessage(`${config.singular} ${record ? "updated" : "created"}.`);
            } catch (error) {
                if (uploaded) await deleteUnusedMedia(config.media.kind, uploaded.filename).catch(() => {});
                dialog.querySelector(".dialog-message").innerHTML = `<div class="alert alert-danger">${escapeHtml(error.message)}</div>`; button.disabled = false; button.textContent = "Save";
            }
        });
        dialog.addEventListener("close", () => { clearPreview(); dialog.remove(); }, { once: true }); dialog.showModal();
    }
    root.querySelector("#entity-search").addEventListener("input", render);
    document.querySelector("#entity-add").addEventListener("click", () => openEditor());
    table.addEventListener("click", async (event) => {
        const button = event.target.closest("button[data-id]"); if (!button) return;
        const record = records.find((item) => item.id === button.dataset.id); if (!record) return;
        if (button.classList.contains("edit")) return openEditor(record);
        if (!await confirmAction(`Delete ${config.singular}?`, `Delete “${record[config.sortField]}”? This cannot be undone.`)) return;
        try { await deleteRecord(config.dataset, record.id); if (config.media && isRuntimeMedia(config.media.kind, record[config.media.field])) await deleteUnusedMedia(config.media.kind, record[config.media.field]).catch(() => {}); await load(); showPageMessage(`${config.singular} deleted.`); }
        catch (error) { showPageMessage(error.message, "danger"); }
    });
    try { await load(); } catch (error) { table.innerHTML = `<div class="alert alert-danger">${escapeHtml(error.message)}</div>`; }
}
