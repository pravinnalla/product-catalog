import { apiDownload, apiRequest } from "./api.service.js";

const selection = (domain, item) => ({
    domain, type: item.type, id: item.id,
    ...(item.dataset ? { dataset: item.dataset } : {}),
});

export function getBackups(domain = "catalog") {
    return apiRequest(`admin/backups.php?domain=${encodeURIComponent(domain)}`);
}

export function createSnapshot(domain) {
    return apiRequest("admin/backups.php", { method: "POST", body: { action: "create-snapshot", domain } });
}

export function dryRunRestore(domain, item) {
    return apiRequest("admin/backups.php", { method: "POST", body: { action: "dry-run", ...selection(domain, item) } });
}

export function restoreBackup(domain, item, confirmation) {
    return apiRequest("admin/backups.php", { method: "POST", body: { action: "restore", ...selection(domain, item), confirmation } });
}

export function deleteBackup(domain, item, confirmation) {
    return apiRequest("admin/backups.php", { method: "DELETE", body: { ...selection(domain, item), confirmation } });
}

export function downloadSnapshot(domain, item) {
    const query = new URLSearchParams({ action: "download", domain, id: item.id });
    return apiDownload(`admin/backups.php?${query}`);
}
