import { defineConfig } from "vite";
import { resolve } from "node:path";

export default defineConfig({

    base: "/",

    server: {

        port: 5173,

        open: true

    },

    build: {

        outDir: "dist",

        emptyOutDir: true,

        rollupOptions: {

            input: {
                index: resolve(import.meta.dirname, "index.html"),
                products: resolve(import.meta.dirname, "products.html"),
                about: resolve(import.meta.dirname, "about.html"),
                contact: resolve(import.meta.dirname, "contact.html"),
                adminLogin: resolve(import.meta.dirname, "admin-login.html"),
                adminForgotPassword: resolve(import.meta.dirname, "admin-forgot-password.html"),
                adminResetPassword: resolve(import.meta.dirname, "admin-reset-password.html"),
                adminChangePassword: resolve(import.meta.dirname, "admin/change-password.html"),
                adminDashboard: resolve(import.meta.dirname, "admin/dashboard.html"),
                adminBackup: resolve(import.meta.dirname, "admin/backup.html"),
                adminCategories: resolve(import.meta.dirname, "admin/categories.html"),
                adminSubcategories: resolve(import.meta.dirname, "admin/subcategories.html"),
                adminSuppliers: resolve(import.meta.dirname, "admin/suppliers.html"),
                adminProducts: resolve(import.meta.dirname, "admin/products.html")
            }

        }

    }

});
