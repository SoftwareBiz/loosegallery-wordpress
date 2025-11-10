# Plugin Updates via GitHub

This plugin automatically checks for updates from the GitHub repository.

## How It Works

The plugin includes an automatic updater that:
1. Checks GitHub releases for new versions
2. Compares the latest GitHub release version with the installed version
3. Shows update notifications in WordPress admin
4. Allows one-click updates from the WordPress plugins page

## For Developers: Creating a New Release

### 1. Update Version Number

Update the version in `loosegallery-woocommerce.php`:
```php
Version: 1.0.2
```

And in the constant:
```php
define('LG_WC_VERSION', '1.0.2');
```

### 2. Update CHANGELOG.md

Add your changes to `CHANGELOG.md`:
```markdown
## [1.0.2] - 2025-11-10
### Added
- New feature description
### Fixed
- Bug fix description
```

### 3. Commit and Push Changes

```bash
git add .
git commit -m "Release v1.0.2"
git push origin main
```

### 4. Create a Git Tag

```bash
git tag v1.0.2
git push origin v1.0.2
```

### 5. Automatic Release Creation

The GitHub Actions workflow (`.github/workflows/release.yml`) will automatically:
- Create a release on GitHub
- Generate a `.zip` file of the plugin
- Attach the `.zip` to the release
- Use the tag name as the release version

### 6. WordPress Will Detect the Update

Within 12 hours (or sooner if cache is cleared), WordPress sites will:
- See the update notification
- Show the new version number
- Display changelog/release notes
- Offer one-click update

## Manual Cache Clear

If you need to force WordPress to check for updates immediately:
1. Go to: `yoursite.com/wp-admin/plugins.php?lg_clear_update_cache`
2. Or deactivate and reactivate the plugin

## Version Numbering

Follow [Semantic Versioning](https://semver.org/):
- **MAJOR** version (1.x.x): Breaking changes
- **MINOR** version (x.1.x): New features, backward compatible
- **PATCH** version (x.x.1): Bug fixes, backward compatible

Examples:
- `v1.0.0` - Initial release
- `v1.0.1` - Bug fix
- `v1.1.0` - New feature
- `v2.0.0` - Breaking change

## Release Notes Best Practices

When creating a GitHub release, include:
- **What's New**: List new features
- **Bug Fixes**: List bugs fixed
- **Breaking Changes**: If any (for major versions)
- **Upgrade Notes**: Special instructions if needed

Example:
```markdown
## What's New
- Added support for product variations
- New design preview sizes

## Bug Fixes
- Fixed cart preview not showing
- Resolved API timeout issues

## Upgrade Notes
- No action required for this update
```

## Troubleshooting

### Update Not Showing
1. Clear WordPress transients: Visit `wp-admin/plugins.php?lg_clear_update_cache`
2. Check GitHub release is published (not draft)
3. Verify tag format is `vX.Y.Z` (with lowercase 'v')
4. Ensure `.zip` file is attached to the release

### Update Fails
1. Check file permissions on `/wp-content/plugins/`
2. Verify zip file was created correctly
3. Check WordPress error logs
4. Try manual update via FTP

### Version Not Detected
1. Ensure version number in plugin file matches Git tag
2. Tag must start with 'v' (e.g., `v1.0.2`)
3. GitHub release must be published, not draft

## GitHub Personal Access Token (Optional)

For private repositories, you may need to add authentication:

1. Create a Personal Access Token on GitHub
2. Add filter in your WordPress installation:

```php
add_filter('http_request_args', function($args, $url) {
    if (strpos($url, 'api.github.com') !== false) {
        $args['headers']['Authorization'] = 'token YOUR_GITHUB_TOKEN';
    }
    return $args;
}, 10, 2);
```

## Testing Updates

Before releasing to production:
1. Create a tag on a test branch
2. Create a pre-release on GitHub
3. Test update on staging site
4. Verify all functionality works
5. Then create official release

## Support

For issues with automatic updates:
- Check GitHub Actions workflow status
- Review WordPress debug log
- Contact support with version details
