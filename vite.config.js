import { defineConfig } from "vite";

export default defineConfig({

    base: "/product-catalog/",

    server: {

        port: 5173,

        open: true

    },

    build: {

        outDir: "dist",

        emptyOutDir: true

    }

});