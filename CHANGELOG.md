# Changelog

All notable changes to the LooseGallery for WooCommerce plugin will be documented in this file.

## [1.0.1] - 2025-11-10

### Changed
- **BREAKING**: Updated API endpoint from REST to GraphQL
- API base URL changed to `https://api.loosegallery.com/graphql`
- Converted all API methods to use GraphQL queries and mutations
- Improved error handling for GraphQL responses

### Technical
- Renamed `make_request()` to `make_graphql_request()`
- Updated `test_connection()` to use GraphQL query
- Updated `get_domain_info()` to use GraphQL query
- Updated `get_design_preview()` to use GraphQL query with variables
- Updated `lock_design()` to use GraphQL mutation
- Updated `get_design_info()` to use GraphQL query with variables
- Enhanced error parsing for GraphQL error arrays

## [1.0.0] - 2025-11-10

### Added
- Initial release of LooseGallery for WooCommerce plugin
- Multiple LooseGallery domain support via API keys
- Admin settings page with complete configuration options
- Product-level customization settings (meta box)
- GraphQL API integration with LooseGallery (domain info, preview, lock)
- Session management for design persistence
- Frontend product display with "Customize Me" tags
- "Start Design" button integration
- Editor return handling with design serial capture
- Cart integration with design previews
- "Edit Your Design" buttons in cart
- Removal warning for customized cart items
- Checkout copyright agreement checkbox
- Automatic design locking on order completion
- Order item meta storage for designs
- Design preview in order emails
- Mobile responsive design
- Theme compatibility CSS
- Admin color pickers for customization
- API connection testing
- Complete documentation (README.md)
- Installation guide (INSTALLATION.md)

### Features
- **Admin Panel**
  - Multi-domain API key management
  - Connection testing with domain name display
  - URL configuration (return URL, editor base URL)
  - Button customization (text, colors, font size)
  - Tag customization (text, colors, font size)
  - Copyright text customization
  - Product meta box for per-product settings

- **Frontend**
  - Visual "Customize Me" tags on products
  - Customizable design buttons
  - Seamless editor integration
  - Design state persistence across sessions
  - Live design previews in cart/checkout
  - Edit capability before order finalization
  - Mobile responsive interface

- **Checkout & Orders**
  - Required copyright agreement checkbox
  - Design locking post-purchase
  - Design serial in order meta
  - Preview images in order details
  - Email integration with design previews
  - Order notes for design lock status

### Technical
- WordPress 5.8+ compatibility
- WooCommerce 5.0+ compatibility
- PHP 7.4+ support
- RESTful API integration
- Secure API key storage
- AJAX for admin functionality
- Session and user meta synchronization
- Proper sanitization and escaping
- Nonce verification for security
- Theme-agnostic implementation

### Files Structure
```
loosegallery-woocommerce.php           - Main plugin file
includes/
  class-lg-api.php                     - API handler
  class-lg-session.php                 - Session management
  admin/
    class-lg-admin-settings.php        - Settings page
    class-lg-product-meta.php          - Product meta box
  frontend/
    class-lg-product-display.php       - Product page integration
    class-lg-cart.php                  - Cart integration
    class-lg-checkout.php              - Checkout integration
assets/
  css/
    frontend.css                       - Frontend styles
    admin.css                          - Admin styles
  js/
    frontend.js                        - Frontend scripts
    admin.js                           - Admin scripts
  images/
    customize-tag.svg                  - Tag icon
README.md                              - Full documentation
INSTALLATION.md                        - Installation guide
CHANGELOG.md                           - Version history
```

### Security
- All user inputs sanitized
- Output properly escaped
- Nonce verification on AJAX calls
- API keys stored securely
- SQL injection prevention
- XSS protection

### Browser Support
- Chrome/Edge (latest)
- Firefox (latest)
- Safari (latest)
- Mobile browsers (iOS/Android)

---

## Future Versions (Planned)

### [1.1.0] - Planned
- [ ] Bulk product customization enablement
- [ ] Design library/gallery view for users
- [ ] Advanced caching for API responses
- [ ] Webhook support for real-time updates
- [ ] Design template suggestions
- [ ] Analytics dashboard for custom designs

### [1.2.0] - Planned
- [ ] Multi-language support (WPML/Polylang)
- [ ] Design collaboration features
- [ ] Revision history for designs
- [ ] Export design data to CSV
- [ ] Advanced order filtering by design status

---

## Version Numbering

This plugin follows [Semantic Versioning](https://semver.org/):
- MAJOR version for incompatible API changes
- MINOR version for new functionality in a backward-compatible manner
- PATCH version for backward-compatible bug fixes

---

## Upgrade Notes

### Upgrading to 1.0.0
- Initial installation, no upgrade needed
- All settings stored in `loosegallery_woocommerce_settings` option
- Product meta keys: `_lg_is_customizable`, `_lg_domain_id`, `_lg_template_serial`, `_lg_api_key`
- Order meta keys: `_lg_design_serial`, `_lg_design_locked`, `_lg_design_preview_url`
- User meta key: `_lg_user_designs`

---

## Support & Contributions

For bug reports, feature requests, or contributions:
- GitHub: [Repository URL]
- Email: support@loosegallery.com
- Documentation: See README.md

---

## License

GPL v2 or later - See LICENSE file for details
