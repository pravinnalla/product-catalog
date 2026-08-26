export function requiredText(value, label, maxLength) {
    const clean = String(value ?? "").trim();
    if (!clean) throw new Error(`${label} is required.`);
    if (clean.length > maxLength) throw new Error(`${label} must be ${maxLength} characters or fewer.`);
    return clean;
}
export function requiredSelect(value, label) {
    if (!value) throw new Error(`Select a ${label}.`);
    return value;
}
