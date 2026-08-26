import { bootstrapAdmin } from "../app.js";
import { mountEntityPage } from "../components/entity-page.js";
import { uploadSupplierLogo } from "../services/upload.service.js";
const root = await bootstrapAdmin("Suppliers", "suppliers", "Manage product suppliers and logo references");
mountEntityPage(root, { dataset: "suppliers", label: "Suppliers", singular: "Supplier", sortField: "name", fields: [{ name: "name", label: "Name", max: 160 }], media: { field: "logo", kind: "supplier", label: "Logo", upload: uploadSupplierLogo }, columns: [{ key: "name", label: "Name" }, { key: "logo", label: "Logo" }, { key: "id", label: "ID" }] });
