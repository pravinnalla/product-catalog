import "bootstrap/dist/css/bootstrap.min.css";
import "bootstrap-icons/font/bootstrap-icons.css";
import "../assets/css/main.css";
import "./assets/admin.css";
import { requireAdminSession } from "./services/session.service.js";
import { renderAdminShell } from "./components/admin-shell.js";

export async function bootstrapAdmin(title, active, helper = "") {
    document.body.classList.add("admin-body");
    await requireAdminSession();
    return renderAdminShell(document.querySelector("#admin-root"), title, active, helper);
}
