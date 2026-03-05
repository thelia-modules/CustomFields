# Custom Fields

A Thelia module to add custom fields to products, content, categories, and folders with multi-language support.

## Features

- Create custom fields with different types (text, textarea)
- Assign custom fields to multiple sources (product, content, category, folder)
- Multi-language support for field values
- Tab integration in back-office edit pages
- Twig function for front-office display

## Installation

1. `composer require thelia/custom-fields`
2. Activate the module in the back-office

## Usage

### Back-Office

1. **Create Custom Fields**: Go to Tools > Custom Fields
   - Enter a title and unique code (e.g., `warranty_period`)
   - Select field type (text or textarea)
   - Choose which sources can use this field (product, content, category, folder)

2. **Edit Field Values**: When editing a product/content/category/folder:
   - Navigate to the "Custom Fields" tab
   - Enter values for each language using the language selector
   - Save changes

### Front-Office (Twig Templates)

Use the `custom_field_value` function to display custom field values:

```twig
{* Display custom field for current locale *}
{{ custom_field_value('warranty_period', 'product', product_id) }}

{* Display custom field for specific locale *}
{{ custom_field_value('warranty_period', 'product', product_id, 'en_US') }}
```

**Parameters:**
- `code`: The custom field code
- `source`: Source type (`product`, `content`, `category`, `folder`)
- `source_id`: The entity ID
- `locale` (optional): Specific locale (defaults to current session locale)

## Example

```twig
{* In a product template *}
{if custom_field_value('warranty_period', 'product', $PRODUCT_ID)}
    <div class="warranty">
        <strong>Warranty:</strong>
        {custom_field_value('warranty_period', 'product', $PRODUCT_ID)}
    </div>
{/if}
```
