/**
 * ============================================================
 * Product Catalog
 * ------------------------------------------------------------
 * File      : product.service.js
 * Purpose   : Product Data Service
 * Version   : 2.0.0
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
 * Get Main Product Categories
 * ------------------------------------------------------------
 *
 * Returns only the two top-level categories:
 *
 * Fire Extinguishers
 * Safety Equipment
 *
 * ------------------------------------------------------------
 */

export function getProductCategories() {

    return [
        ...new Set(
            products.map(
                product =>
                    product.category
            )
        )
    ];

}

/**
 * ------------------------------------------------------------
 * Get Product Subcategories
 * ------------------------------------------------------------
 *
 * Returns unique subcategories.
 *
 * ------------------------------------------------------------
 */

export function getProductSubcategories() {

    return [
        ...new Set(
            products.map(
                product =>
                    product.subcategory
            )
        )
    ];

}
