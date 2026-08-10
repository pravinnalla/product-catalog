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
 * Get Products By Category
 * ------------------------------------------------------------
 */

export function getProductsByCategory(category) {

    if (!category || category === "All") {

        return products;

    }

    return products.filter(
        product =>
            product.category === category
    );

}

/**
 * ------------------------------------------------------------
 * Get Products By Subcategory
 * ------------------------------------------------------------
 */

export function getProductsBySubcategory(subcategory) {

    if (!subcategory || subcategory === "All") {

        return products;

    }

    return products.filter(
        product =>
            product.subcategory === subcategory
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

/**
 * ------------------------------------------------------------
 * Get Subcategories By Category
 * ------------------------------------------------------------
 */

export function getSubcategoriesByCategory(category) {

    if (!category || category === "All") {

        return getProductSubcategories();

    }

    return [
        ...new Set(
            products
                .filter(
                    product =>
                        product.category === category
                )
                .map(
                    product =>
                        product.subcategory
                )
        )
    ];

}