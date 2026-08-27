import { apiUrl } from "./api.service.js";

const publicPages = new Set(["/", "/about.html", "/products.html", "/contact.html"]);
let logged = false;

export function logPublicVisit() {
    const page = window.location.pathname;
    if (logged || !publicPages.has(page)) return;
    logged = true;
    fetch(apiUrl("visitor.php"), {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ page }),
        keepalive: true,
    }).catch(() => { /* Visitor logging never blocks the public page. */ });
}
