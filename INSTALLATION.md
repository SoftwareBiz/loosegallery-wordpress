# Quick Installation Guide

## Step 1: Upload Plugin

### Option A: Via WordPress Admin
1. Zip the entire `loosegallery-wordpress` folder
2. Go to WordPress Admin → Plugins → Add New
3. Click "Upload Plugin"
4. Choose the zip file
5. Click "Install Now"
6. Click "Activate Plugin"

### Option B: Via FTP
1. Upload the `loosegallery-wordpress` folder to `/wp-content/plugins/`
2. Go to WordPress Admin → Plugins
3. Find "LooseGallery for WooCommerce"
4. Click "Activate"

## Step 2: Configure API Keys

1. Go to **LooseGallery → Settings** in WordPress admin
2. Under "API Configuration":
   - Enter your LooseGallery API key
   - Click "Test Connection"
   - Verify domain name appears
3. Add more API keys if needed (click "+ Add Another API Key")
4. Click "Save Settings"

## Step 3: Configure URLs (Optional)

1. **Return URL**: Leave as default (home page) or customize
2. **Editor Base URL**: Keep as `https://editor.loosegallery.com` unless using custom domain

## Step 4: Customize Appearance (Optional)

### Start Design Button
- Text: "Start Design" (or customize)
- Colors: Black background, white text (or customize)
- Font size: 16px (or adjust)

### Customize Me Tag
- Text: "Customize Me" (or customize)
- Colors: Red background, white text (or customize)
- Font size: 14px (or adjust)

### Copyright Text
- Edit the checkbox text customers see at checkout
- Default: "I agree to the copyright ownership and understand my design will be printed as is."

Click **Save Settings** after making changes.

## Step 5: Enable Products for Customization

1. Go to **Products** in WordPress admin
2. Edit a product you want to make customizable
3. Find the **"LooseGallery Customization"** box (usually in the right sidebar)
4. Check ✓ **"Enable LooseGallery Customization"**
5. Select a **Domain** from the dropdown
6. Enter the **Template Serial Number** (e.g., TEMP-12345)
7. Click "Update" to save the product

## Step 6: Test the Integration

1. Visit the product page on your store
2. You should see:
   - "Customize Me" tag on product image
   - "Start Design" button
3. Click "Start Design" button
4. You should be redirected to LooseGallery editor
5. Create a design and save
6. You should return to your store with the design saved

## Step 7: Test Checkout Flow

1. Add the customized product to cart
2. Go to cart page - verify design preview shows
3. Proceed to checkout
4. Verify copyright checkbox appears
5. Complete test order
6. Design should be locked after order

## Troubleshooting

### "LooseGallery for WooCommerce requires WooCommerce"
- Install and activate WooCommerce plugin first

### API Connection Fails
- Verify API key is correct
- Check that your LooseGallery account has API access enabled
- Ensure server can make outbound HTTPS requests

### Design Not Saving
- Check that PHP sessions are working (contact hosting if needed)
- Verify return URL is correct
- Check browser console for errors

### Styles Look Wrong
- Clear browser cache
- Clear WordPress cache (if using caching plugin)
- Check for theme conflicts in browser developer tools

## File Structure

```
loosegallery-wordpress/
├── loosegallery-woocommerce.php    (Main plugin file)
├── README.md                        (Full documentation)
├── INSTALLATION.md                  (This file)
├── includes/
│   ├── class-lg-api.php            (API handler)
│   ├── class-lg-session.php        (Session management)
│   ├── admin/
│   │   ├── class-lg-admin-settings.php
│   │   └── class-lg-product-meta.php
│   └── frontend/
│       ├── class-lg-product-display.php
│       ├── class-lg-cart.php
│       └── class-lg-checkout.php
└── assets/
    ├── css/
    │   ├── frontend.css
    │   └── admin.css
    ├── js/
    │   ├── frontend.js
    │   └── admin.js
    └── images/
        └── customize-tag.svg
```

## Next Steps

- Add more customizable products
- Configure multiple domains if needed
- Customize button and tag styling to match your brand
- Test the complete customer journey
- Review order management workflow

## Support

For technical support or questions about the plugin:
- Review the README.md for detailed documentation
- Check WordPress debug.log for errors
- Contact LooseGallery support

## Updates

To update the plugin:
1. Deactivate the current version
2. Delete the old plugin folder
3. Upload new version
4. Activate the plugin
5. Verify settings are preserved
