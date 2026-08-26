import { bootstrapAdmin } from "../app.js";
import { mountEntityPage } from "../components/entity-page.js";
const root = await bootstrapAdmin("Categories", "categories", "Manage catalogue categories");
mountEntityPage(root, { dataset: "categories", label: "Categories", singular: "Category", sortField: "name", fields: [{ name: "name", label: "Name", max: 160 }], columns: [{ key: "name", label: "Name" }, { key: "id", label: "ID" }] });
