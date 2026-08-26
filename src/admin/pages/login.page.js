import "bootstrap/dist/css/bootstrap.min.css";
import "bootstrap-icons/font/bootstrap-icons.css";
import "../../assets/css/main.css";
import { getSession, login } from "../services/auth.service.js";

const form = document.querySelector("#admin-login-form");
const passwordInput = document.querySelector("#admin-password");
const submitButton = document.querySelector("#admin-login-submit");
const message = document.querySelector("#admin-login-message");

function showMessage(text, type = "danger") {
    message.textContent = text;
    message.className = `alert alert-${type}`;
    message.hidden = false;
}

function setLoading(loading) {
    submitButton.disabled = loading;
    submitButton.querySelector(".spinner-border").classList.toggle("d-none", !loading);
    submitButton.querySelector(".button-label").textContent = loading ? "Signing in…" : "Sign in";
}

getSession()
    .then((session) => {
        if (session.authenticated) {
            window.location.replace("/admin/dashboard.html");
        }
    })
    .catch(() => {});

form.addEventListener("submit", async (event) => {
    event.preventDefault();
    message.hidden = true;
    const password = passwordInput.value;

    if (password === "") {
        form.classList.add("was-validated");
        return;
    }

    setLoading(true);
    try {
        await login(password);
        passwordInput.value = "";
        window.location.replace("/admin/dashboard.html");
    } catch {
        showMessage("Unable to sign in. Please try again.");
    } finally {
        setLoading(false);
    }
});
