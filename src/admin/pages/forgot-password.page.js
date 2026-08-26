import "bootstrap/dist/css/bootstrap.min.css";
import "../../assets/css/main.css";
import { forgotPassword } from "../services/auth.service.js";

const form = document.querySelector("#forgot-password-form");
const button = form.querySelector("button[type=submit]");
const buttonLabel = button.querySelector(".button-label");
const spinner = button.querySelector(".spinner-border");
const message = document.querySelector("#form-message");

form.addEventListener("submit", async (event) => {
    event.preventDefault();
    button.disabled = true;
    buttonLabel.textContent = "Sending…";
    spinner.classList.remove("d-none");
    message.hidden = true;
    try {
        const result = await forgotPassword();
        message.textContent = result.message;
        message.className = "alert alert-success";
    } catch (error) {
        message.textContent = error.message || "Unable to process the request. Please try again later.";
        message.className = "alert alert-danger";
    } finally {
        message.hidden = false;
        button.disabled = false;
        buttonLabel.textContent = "Send Password Reset Link";
        spinner.classList.add("d-none");
    }
});
