# Product Catalog Website

## Project

This is a static product catalog website built with Vite.

## Technology

- HTML5
- Bootstrap 5.3
- Custom CSS
- Vanilla JavaScript using ES6 modules
- JSON data
- Bootstrap Icons
- Inter font
- Vite

Do not introduce React, Vue, Angular, jQuery, or another frontend framework.

## Project Structure

- Root HTML files: website pages
- src/assets/css: stylesheets
- src/assets/fonts: fonts
- src/assets/images: images
- src/assets/pdf: PDF documents
- src/assets/js: JavaScript where currently used
- src/components: reusable components
- src/data: JSON/product data
- src/pages: page-specific logic
- src/services: services such as enquiry/email handling
- src/ui: UI-related modules
- src/utils: utility functions

## Coding Rules

1. Preserve the existing project structure unless there is a clear reason to change it.
2. Use Bootstrap 5.3 components and utilities where appropriate.
3. Use custom CSS only where Bootstrap does not provide a suitable solution.
4. Use vanilla ES6 modules.
5. Do not add a JavaScript framework.
6. Keep product information in JSON data rather than duplicating product information in HTML.
7. Reuse existing components wherever possible.
8. Do not unnecessarily rename or move existing files.
9. Preserve the existing visual design unless the task specifically requests a design change.
10. Keep HTML semantic and accessible.
11. Use relative paths that work correctly with Vite.
12. Check all links to assets, CSS, JavaScript, images, PDFs, and pages after making changes.

## Product Data

Product information should be maintained in the appropriate JSON files.

Do not duplicate product data across multiple pages.

## Important Existing Issues

There is an existing inconsistency involving asset/page paths, including
`/product-catalog/...` versus root-relative paths.

Resolve this carefully without breaking existing pages.

## Enquiry Form

The product enquiry form ultimately needs to send enquiries directly to:

laxmikantj96@yahoo.in

Do not implement a fake submission or merely display a success message when the task specifically concerns actual email delivery.

## Before Making Changes

1. Inspect the existing files.
2. Understand how the relevant page currently works.
3. Identify dependencies between files.
4. Make the smallest appropriate change.
5. Check for broken paths and imports.
6. Run the project/build when appropriate.
7. Report exactly which files were changed.

## Important

Do not rewrite the entire project when a targeted change is sufficient.