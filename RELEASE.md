# BRAGBook Gallery Release Process

This document explains how to create releases for the BRAGBook Gallery plugin using GitHub CLI.

## How the Auto-Update System Works

The plugin includes an updater class (`includes/core/class-updater.php`) that:
- Checks the GitHub repository `bragbook2/brag-book-gallery` for new releases
- Compares versions with the currently installed plugin
- Allows updates directly from the WordPress admin panel

## Prerequisites

1. **GitHub Repository Access**: Push access to `bragbook2/brag-book-gallery`
2. **GitHub CLI**: Install from https://cli.github.com/
3. **Node.js & NPM**: For building assets

## Standard Release Process Using GitHub CLI

### Step 1: Update Version Numbers

Update version in these files:
```bash
# brag-book-gallery.php - Plugin header
* Version: X.X.X

# package.json - NPM package
"version": "X.X.X"
```

### Step 2: Build Assets (if needed)

```bash
npm run build
```

### Step 3: Commit Changes

```bash
git add .
git commit -m "Release version X.X.X

- Brief description of changes
- Another change
- Another change"
```

### Step 4: Create Git Tag

```bash
# Use semantic versioning without 'v' prefix
git tag -a X.X.X -m "Version X.X.X - Brief description"
```

### Step 5: Push to Remote

```bash
# Push commits
git push origin main

# Push tag
git push origin X.X.X
```

### Step 6: Create GitHub Release

```bash
gh release create X.X.X --title "BRAGBook Gallery vX.X.X" --notes "$(cat <<'EOF'
## Version X.X.X

### Changes
- Feature or change description
- Another change
- Another change

### Bug Fixes
- Fixed issue description
- Another fix

### Details
Additional information about this release.
EOF
)"
```

## Release Standards

### Version Format
- **Tag**: Use numbers only (e.g., `3.0.5`)
- **Title**: Include 'v' prefix (e.g., `BRAGBook Gallery v3.0.5`)

### Release Notes Template
```markdown
## Version X.X.X

### Changes
- New features or modifications

### Bug Fixes  
- Resolved issues

### Breaking Changes
- Incompatible changes (if any)

### Details
Migration instructions or additional context
```

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
# Complete release process using GitHub CLI
# 1. Update version in files
# 2. Build assets
npm run build

# 3. Commit, tag, and release
git add .
git commit -m "Release version 3.0.6"
git tag -a 3.0.6 -m "Version 3.0.6"
git push origin main
git push origin 3.0.6
gh release create 3.0.6 --title "BRAGBook Gallery v3.0.6" --notes "Release notes here"
```

## Fixing Duplicate or Incorrect Releases

```bash
# Delete duplicate releases
gh release delete X.X.X --yes

# Delete and recreate tag
git push origin --delete X.X.X
git tag -d X.X.X
git tag -a X.X.X -m "Version X.X.X"
git push origin X.X.X

# Create clean release
gh release create X.X.X --title "BRAGBook Gallery vX.X.X" --notes "..."
```