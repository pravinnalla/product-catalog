const ROOT_API_BASE = (
    import.meta.env.VITE_API_BASE_URL
    || import.meta.env.VITE_API_BASE
    || (import.meta.env.DEV ? "http://localhost:8000/api" : "/api")
).replace(/\/$/, "");
const API_BASE = (import.meta.env.VITE_AUTH_API_BASE || `${ROOT_API_BASE}/auth`).replace(/\/$/, "");

async function request(path, options = {}) {
    let response;
    try {
        response = await fetch(`${API_BASE}/${path}`, {
            credentials: "include",
            ...options,
            headers: {
                Accept: "application/json",
                ...options.headers,
            },
        });
    } catch {
        throw new Error("Unable to reach the authentication service.");
    }

    let data;
    try {
        data = await response.json();
    } catch {
        throw new Error("The authentication service returned an invalid response.");
    }

    if (!response.ok) {
        throw new Error(data.message || "Authentication request failed.");
    }
    return data;
}

export function login(password) {
    return request("login.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ password }),
    });
}

export function logout(csrfToken) {
    return request("logout.php", {
        method: "POST",
        headers: { "X-CSRF-Token": csrfToken },
    });
}

export function getSession() {
    return request("session.php");
}

export function forgotPassword() {
    return request("forgot-password.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({}),
    });
}

export function resetPassword(token, newPassword, confirmPassword) {
    return request("reset-password.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ token, newPassword, confirmPassword }),
    });
}

export function changePassword(currentPassword, newPassword, confirmPassword, csrfToken) {
    return request("change-password.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            "X-CSRF-Token": csrfToken,
        },
        body: JSON.stringify({ currentPassword, newPassword, confirmPassword }),
    });
}
