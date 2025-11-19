# Loose Gallery for WooCommerce

A comprehensive WordPress/WooCommerce plugin that integrates the Loose Gallery design editor with your online store, allowing customers to customize products and complete purchases seamlessly.

## Features

### Admin Features
- **Multiple Domain Support**: Connect multiple Loose Gallery accounts via API keys
- **Product Customization Settings**: Enable customization per product with template selection
- **Flexible Styling**: Customize button text, colors, and tag appearance
- **Domain Management**: View and test API connections with domain name display
- **Automatic Updates**: One-click updates from GitHub releases

### Customer Features
- **Visual Customization Tags**: "Customize Me" badges on customizable products
- **Design Editor Integration**: Seamless redirect to Loose Gallery editor
- **Design Persistence**: Designs saved across sessions and user accounts
- **Live Previews**: See custom designs in cart and checkout
- **Edit Capability**: Modify designs before finalizing order

### Checkout & Order Management
- **Copyright Agreement**: Required checkbox for legal compliance
- **Design Locking**: Automatic design lock after order completion
- **Order Integration**: Design information included in order details and emails
- **Warning System**: Alert customers before removing customized items

## Requirements

- WordPress 5.8 or higher
- WooCommerce 5.0 or higher
- PHP 7.4 or higher
- Loose Gallery account(s) with API access

## Installation

1. **Upload Plugin**
   - Upload the `loosegallery-woocommerce` folder to `/wp-content/plugins/`
   - Or install via WordPress admin: Plugins → Add New → Upload Plugin

2. **Activate Plugin**
   - Navigate to Plugins in WordPress admin
   - Find "Loose Gallery for WooCommerce"
   - Click "Activate"

3. **Configure Settings**
   - Go to Loose Gallery → Settings in admin menu
   - Add your API keys
   - Test connections to verify
   - Configure URLs and styling options

4. **Set Up Products**
   - Edit any WooCommerce product
   - Find "Loose Gallery Customization" meta box (sidebar)
   - Check "Enable Loose Gallery Customization"
   - Select domain and enter template serial number
   - Save product

## Configuration

### API Configuration

1. **Add API Keys**
   - Navigate to Loose Gallery → Settings
   - Enter your Loose Gallery API key(s)
   - Click "Test Connection" to verify
   - Domain name will display if successful

2. **URL Settings**
   - **Return URL**: Where customers return after editing (default: home page)
   - **Editor Base URL**: Loose Gallery editor URL (default: https://editor.loosegallery.com)

### Customization Options

**Start Design Button**
- Text: Customize button label
- Background Color: Button color
- Text Color: Button text color
- Font Size: Button text size

**Customize Me Tag**
- Text: Tag label
- Background Color: Tag color
- Text Color: Tag text color
- Font Size: Tag text size

**Copyright Agreement**
- Custom text for checkout agreement checkbox

### Product Setup

For each customizable product:

1. **Enable Customization**
   - Check "Enable Loose Gallery Customization" in product meta box

2. **Select Domain**
   - Choose which Loose Gallery account/domain to use
   - Only configured API keys appear in dropdown

3. **Template Serial**
   - Enter the template serial number from Loose Gallery
   - This determines which template customers start with

4. **Test Link** (optional)
   - Click "Test Editor Link" to preview the editor experience

## How It Works

### Customer Journey

1. **Browse Products**
   - Customizable products show "Customize Me" tag
   - "Start Design" button appears on product page

2. **Design Creation**
   - Click button to open Loose Gallery editor
   - Create/edit design in editor
   - Return to product page with design saved

3. **Add to Cart**
   - Design preview replaces product image
   - Add customized product to cart
   - Design thumbnail shows in cart

4. **Checkout**
   - Design preview visible throughout checkout
   - Copyright agreement checkbox required
   - Complete order

5. **Order Completion**
   - Design automatically locked (no further edits)
   - Design serial included in order details
   - Preview image in order emails

### Technical Flow

```
Product Page → Editor (external) → Return URL → Product Page
                                         ↓
                                    Save Design
                                         ↓
                                    Add to Cart
                                         ↓
                                      Checkout
                                         ↓
                                   Order Complete
                                         ↓
                                    Lock Design
```

## API Integration

The plugin communicates with Loose Gallery GraphQL API at `https://api.loosegallery.com/graphql` for:

- **Domain Info**: Retrieve domain name and details
- **Design Preview**: Get preview and thumbnail images
- **Design Lock**: Prevent editing after purchase
- **Connection Test**: Verify API key validity

### GraphQL Queries & Mutations Used

**Get Domain Information:**
```graphql
query {
  domain {
    id
    name
  }
}
```

**Get Asset/Design Preview:**
```graphql
query GetAsset($serial: String!) {
  asset(serial: $serial) {
    serial
    previewUrl
    thumbnailUrl
    locked
  }
}
```

**Lock Asset/Design:**
```graphql
mutation LockAsset($serial: String!) {
  lockAsset(serial: $serial) {
    serial
    locked
  }
}
```

## Data Storage

### Session Data
- Design serials stored in PHP sessions
- Synced with user meta on login
- Persists across page loads

### User Meta
- `_lg_user_designs` - User's saved designs
- Synced with session data
- Preserved across sessions

### Product Meta
- `_lg_is_customizable` - Customization enabled (yes/no)
- `_lg_domain_id` - Selected domain ID
- `_lg_template_serial` - Template serial number
- `_lg_api_key` - Associated API key (cached)

### Order Item Meta
- `_lg_design_serial` - Design serial number
- `_lg_design_locked` - Lock status (yes/no)
- `_lg_design_preview_url` - Preview image URL
- `_lg_design_ordered_at` - Order timestamp
- `_lg_design_locked_at` - Lock timestamp

## Hooks & Filters

### Actions
- `woocommerce_before_shop_loop_item_title` - Add customize tag (loop)
- `woocommerce_before_single_product_summary` - Add customize tag (single)
- `woocommerce_after_add_to_cart_button` - Add design button
- `woocommerce_review_order_before_submit` - Add copyright checkbox
- `woocommerce_thankyou` - Lock designs on order
- `woocommerce_order_status_completed` - Lock designs on completion

### Filters
- `woocommerce_add_cart_item_data` - Add design to cart item
- `woocommerce_get_item_data` - Display design in cart
- `woocommerce_cart_item_thumbnail` - Replace cart thumbnail
- `woocommerce_single_product_image_thumbnail_html` - Replace product image

## Shortcodes

Currently, no shortcodes are implemented. All functionality is automatic based on product settings.

## Troubleshooting

### Design Not Saving
- Check browser console for JavaScript errors
- Verify API key is valid
- Ensure return URL is correct
- Check PHP session is working

### Preview Not Showing
- Verify API connection
- Check design serial format
- Ensure preview URL is accessible
- Check image permissions

### Design Not Locking
- Verify order status is "completed" or reached thank you page
- Check API key permissions
- Review order notes for error messages

### Style Conflicts
- Plugin uses minimal CSS with high specificity
- Check for theme conflicts in browser dev tools
- CSS classes are prefixed with `lg-`

## Browser Compatibility

- Chrome/Edge (latest)
- Firefox (latest)
- Safari (latest)
- Mobile browsers (iOS Safari, Chrome Mobile)

## Security

- API keys stored in WordPress options (encrypted by WordPress)
- Nonces used for AJAX requests
- Input sanitization on all user inputs
- SQL injection prevention via WordPress functions
- XSS protection via escaping functions

## Support

For issues or questions:
1. Check this documentation
2. Review WordPress debug logs
3. Contact Loose Gallery support with plugin version and error details

## Changelog

### Version 1.0.0
- Initial release
- Multiple domain support
- Design editor integration
- Cart and checkout functionality
- Design locking on order completion
- Mobile responsive design

## License

GPL v2 or later

## Credits

Developed for Loose Gallery
Compatible with WooCommerce
Built on WordPress
