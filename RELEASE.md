# BRAG book Gallery Release Process

This document explains how to create releases for the BRAG book Gallery plugin using GitHub Actions automation.

## How the Auto-Update System Works

The plugin includes an updater class (`includes/core/class-updater.php`) that:
- Checks the GitHub repository `bragbook2/brag-book-gallery` for new releases
- Compares versions with the currently installed plugin
- Allows updates directly from the WordPress admin panel
- Downloads the `brag-book-gallery.zip` file automatically created by GitHub Actions

## Prerequisites

1. **GitHub Repository Access**: Push access to `bragbook2/brag-book-gallery`
2. **Git**: Command line git installed and configured
3. **GitHub Actions**: Already configured in `.github/workflows/release.yml`

## Standard Release Process Using GitHub Actions

### Step 1: Update Version Numbers

Update version in these files:
```bash
# brag-book-gallery.php - Plugin header
* Version: X.X.X

# package.json - NPM package
"version": "X.X.X"
```

### Step 2: Commit Changes

```bash
git add .
git commit -m "Release version X.X.X

- Brief description of changes
- Another change
- Another change"
```

### Step 3: Push to Main Branch

```bash
git push origin main
```

### Step 4: Create and Push Tag (Triggers Release)

```bash
# Create tag with 'v' prefix (required for GitHub Actions)
git tag vX.X.X

# Push tag - this triggers the GitHub Actions workflow
git push origin vX.X.X
```

### What Happens Next (Automated)

Once you push the tag, GitHub Actions automatically:
1. **Builds production assets** - Runs `npm run build`
2. **Creates distribution** - Removes dev files, source files, tests
3. **Generates zip file** - Creates `brag-book-gallery.zip`
4. **Creates GitHub release** - With formatted release notes
5. **Attaches zip file** - Adds the plugin zip as a release asset

The release will be available at:
`https://github.com/bragbook2/brag-book-gallery/releases/tag/vX.X.X`

## Release Standards

### Version Format
- **Tag**: Must use 'v' prefix (e.g., `v3.0.5`) to trigger GitHub Actions
- **Title**: Automatically set to `BRAG book Gallery vX.X.X` by workflow

### Release Notes

The GitHub Actions workflow automatically generates release notes with:
- Installation instructions
- Update notes about auto-updates
- Compatibility requirements
- Link to full changelog

To customize release notes, edit `.github/workflows/release.yml` before tagging.

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

1. **Clear transients using WP-CLI**:
   ```bash
   wp transient delete --all
   ```
   
   Or via SQL:
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

1. **GitHub Actions Required**: The automated process requires the workflow file at `.github/workflows/release.yml`
2. **Tag Format**: Must use 'v' prefix (e.g., `v3.0.7`) to trigger the workflow
3. **Automatic Zip Creation**: GitHub Actions creates `brag-book-gallery.zip` automatically
4. **Version Consistency**: Ensure version numbers match in `brag-book-gallery.php` and `package.json`
5. **GitHub API Rate Limits**: The updater caches API responses for 1 hour
6. **Build Process**: Assets are built automatically - no need to run `npm run build` locally

## Support

For issues with the release process:
- Check GitHub Actions logs
- Review the updater class debug output
- Contact the development team

---

## Quick Release Commands

```bash
# Complete release process (example for version 3.0.8)
# 1. Update version in files (brag-book-gallery.php and package.json)
# 2. Commit changes
git add .
git commit -m "Release version 3.0.8"

# 3. Push and create release
git push origin main
git tag v3.0.8
git push origin v3.0.8

# GitHub Actions will now automatically:
# - Build production assets
# - Create brag-book-gallery.zip
# - Create the GitHub release
# - Attach the zip file
```

## Fixing Duplicate or Incorrect Releases

```bash
# Delete release and tag from GitHub
gh release delete vX.X.X --yes
git push origin --delete vX.X.X

# Delete local tag
git tag -d vX.X.X

# Recreate and push tag (this triggers workflow again)
git tag vX.X.X
git push origin vX.X.X
```

## Manual Release (Without GitHub Actions)

If GitHub Actions is unavailable, you can create a release manually:

```bash
# 1. Build assets locally
npm install
npm run build

# 2. Create zip file
mkdir -p dist/brag-book-gallery
rsync -av --exclude-from=.distignore ./ dist/brag-book-gallery/
cd dist
zip -r ../brag-book-gallery.zip brag-book-gallery/
cd ..

# 3. Create release with GitHub CLI
gh release create vX.X.X \
  --title "BRAG book Gallery vX.X.X" \
  --notes "Release notes here" \
  brag-book-gallery.zip
```