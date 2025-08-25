# BRAGBook Gallery Release Process

This document explains how to create releases for the BRAGBook Gallery plugin that work with the built-in auto-updater.

## How the Auto-Update System Works

The plugin includes an updater class (`includes/core/class-updater.php`) that:
- Checks the GitHub repository `bragbook2/brag-book-gallery` for new releases
- Compares versions with the currently installed plugin
- Allows updates directly from the WordPress admin panel

## Prerequisites

1. **GitHub Repository Access**: Push access to `bragbook2/brag-book-gallery`
2. **GitHub CLI** (for manual releases): Install from https://cli.github.com/
3. **Node.js & NPM**: For building assets

## Release Methods

### Method 1: GitHub Actions (Automated) - Recommended

1. **Update version** in the main plugin file:
   ```php
   // brag-book-gallery.php
   * Version: 3.0.1
   ```

2. **Commit and push** your changes:
   ```bash
   git add .
   git commit -m "Prepare release 3.0.1"
   git push origin main
   ```

3. **Create and push a tag**:
   ```bash
   git tag v3.0.1
   git push origin v3.0.1
   ```

   The GitHub Action will automatically:
   - Build the plugin assets
   - Create a distribution package
   - Create a GitHub release
   - Upload the plugin zip file

### Method 2: Manual Release Script

1. **Run the release script**:
   ```bash
   ./release.sh
   ```

2. **Follow the prompts**:
   - Enter the new version number
   - Add release notes
   - The script will handle everything else

### Method 3: Manual GitHub Release

1. **Build the plugin**:
   ```bash
   ./build.sh
   # or
   npm run release
   ```

2. **Create a GitHub release**:
   - Go to https://github.com/bragbook2/brag-book-gallery/releases/new
   - Create a tag: `v3.0.1`
   - Title: `BRAGBook Gallery v3.0.1`
   - Upload the `brag-book-gallery.zip` file
   - Publish the release

## Version Numbering

Follow semantic versioning (MAJOR.MINOR.PATCH):
- **MAJOR**: Breaking changes
- **MINOR**: New features (backward compatible)
- **PATCH**: Bug fixes

Examples:
- `3.0.0` → `3.0.1` (bug fix)
- `3.0.1` → `3.1.0` (new feature)
- `3.1.0` → `4.0.0` (breaking change)

## Release Checklist

Before creating a release:

- [ ] All code changes committed
- [ ] Tests pass (if applicable)
- [ ] Version number updated in:
  - [ ] `brag-book-gallery.php`
  - [ ] `package.json`
  - [ ] `readme.txt` (if exists)
- [ ] CHANGELOG updated (if maintained)
- [ ] Build tested locally
- [ ] No sensitive information in code

## Auto-Update Testing

To test if auto-updates work:

1. **Install an older version** of the plugin on a test site
2. **Create a new release** on GitHub
3. **Check for updates** in WordPress admin:
   - Go to Dashboard → Updates
   - Or Plugins page
4. **Verify** the new version appears and can be updated

## Troubleshooting

### Updates Not Showing

1. **Clear transients**:
   ```sql
   DELETE FROM wp_options WHERE option_name LIKE '%_transient_%brag_book%';
   ```

2. **Check GitHub API**:
   ```bash
   curl https://api.github.com/repos/bragbook2/brag-book-gallery/releases/latest
   ```

3. **Verify version format**:
   - Plugin file: `Version: 3.0.1`
   - GitHub tag: `v3.0.1` or `3.0.1`

### Build Issues

1. **Install dependencies**:
   ```bash
   npm install
   ```

2. **Clean and rebuild**:
   ```bash
   npm run clean
   npm run build
   ```

## Important Notes

1. **GitHub API Rate Limits**: The updater caches API responses for 1 hour to avoid rate limits
2. **Private Repositories**: If the repository becomes private, you'll need to add an access token
3. **Asset Names**: The release must include a `brag-book-gallery.zip` file
4. **Version Consistency**: Ensure version numbers match across all files

## Support

For issues with the release process:
- Check GitHub Actions logs
- Review the updater class debug output
- Contact the development team

---

## Quick Release Commands

```bash
# Full automated release (after updating version in files)
git add .
git commit -m "Release v3.0.1"
git tag v3.0.1
git push origin main --tags

# Manual release with script
./release.sh

# Manual build only
npm run release
# Creates: dist/brag-book-gallery.zip
```