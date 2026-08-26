import { escapeHtml } from "../utils/formatters.js";

export function createFormDialog(title, fieldsHtml) {
    const dialog = document.createElement("dialog");
    dialog.className = "admin-dialog";
    dialog.innerHTML = `<form method="dialog" class="admin-dialog-form" novalidate><div class="admin-dialog-header"><h2 class="dialog-title">${escapeHtml(title)}</h2><button type="button" class="btn-close" data-dialog-cancel aria-label="Close"></button></div><div class="dialog-message" role="alert"></div><div class="admin-form-fields">${fieldsHtml}</div><div class="admin-dialog-actions"><button type="button" class="btn btn-outline-secondary" data-dialog-cancel>Cancel</button><button class="btn btn-danger submit-button" type="submit" value="default">Save</button></div></form>`;
    dialog.querySelectorAll("[data-dialog-cancel]").forEach((button) => button.addEventListener("click", () => dialog.close("cancel")));
    document.body.append(dialog);
    return dialog;
}

export function confirmAction(title, message, confirmLabel = "Delete") {
    return new Promise((resolve) => {
        const dialog = createFormDialog(title, `<p>${escapeHtml(message)}</p>`);
        const form = dialog.querySelector("form");
        form.querySelector(".submit-button").textContent = confirmLabel;
        form.addEventListener("submit", (event) => { event.preventDefault(); dialog.close("confirm"); });
        dialog.addEventListener("close", () => { const ok = dialog.returnValue === "confirm"; dialog.remove(); resolve(ok); }, { once: true });
        dialog.showModal();
    });
}
