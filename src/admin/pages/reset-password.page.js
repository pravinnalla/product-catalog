import "bootstrap/dist/css/bootstrap.min.css";
import "../../assets/css/main.css";
import { resetPassword } from "../services/auth.service.js";

const form = document.querySelector("#reset-password-form");
const password = document.querySelector("#new-password");
const confirmation = document.querySelector("#confirm-password");
const button = form.querySelector("button[type=submit]");
const message = document.querySelector("#form-message");
const token = new URLSearchParams(window.location.search).get("token") || "";
const invalidLink = document.querySelector("#invalid-reset-link");
const tokenLooksValid = /^[A-Za-z0-9_-]{43}$/.test(token);

if (tokenLooksValid) {
    form.hidden = false;
} else {
    invalidLink.hidden = false;
}

function strong(value) {
    return value.length >= 12 && value.length <= 128 && /[a-z]/.test(value)
        && /[A-Z]/.test(value) && /\d/.test(value) && /[^a-zA-Z\d]/.test(value);
}

form.addEventListener("submit", async (event) => {
    event.preventDefault();
    if (!tokenLooksValid) return;
    if (!form.checkValidity() || !strong(password.value) || password.value !== confirmation.value) {
        show("The passwords do not meet the requirements or do not match.", "danger");
        return;
    }
    button.disabled = true;
    message.hidden = true;
    try {
        const result = await resetPassword(token, password.value, confirmation.value);
        form.reset();
        show(`${result.message} Redirecting to sign in…`, "success");
        window.setTimeout(() => window.location.replace("/admin-login.html"), 1200);
    } catch {
        show("The reset link is invalid or expired.", "danger");
    } finally {
        password.value = "";
        confirmation.value = "";
        button.disabled = false;
    }
});

function show(text, type) {
    message.textContent = text;
    message.className = `alert alert-${type}`;
    message.hidden = false;
}
