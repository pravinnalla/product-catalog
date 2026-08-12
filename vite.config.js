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
                contact: resolve(import.meta.dirname, "contact.html")
            }

        }

    }

});
