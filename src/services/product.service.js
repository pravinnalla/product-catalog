/**
 * ============================================================
 * Product Catalog
 * ------------------------------------------------------------
 * File      : product.service.js
 * Purpose   : Product Data Service
 * Version   : 1.0.0
 * ============================================================
 */

import products from "../data/products.json";

/**
 * ------------------------------------------------------------
 * Get All Products
 * ------------------------------------------------------------
 */

export function getProducts() {

    return products;

}

/**
 * ------------------------------------------------------------
 * Get Product By ID
 * ------------------------------------------------------------
 */

export function getProductById(id) {

    return products.find(
        product => product.id === id
    );

}

/**
 * ------------------------------------------------------------
 * Get Products By Category
 * ------------------------------------------------------------
 */

export function getProductsByCategory(category) {

    if (!category || category === "All") {

        return products;

    }

    return products.filter(
        product => product.category === category
    );

}

/**
 * ------------------------------------------------------------
 * Get Product Categories
 * ------------------------------------------------------------
 */

export function getProductCategories() {

    return [
        ...new Set(
            products.map(
                product => product.category
            )
        )
    ];

}