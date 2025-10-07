# BRAG book Gallery Release Process

This document explains how to create releases for the BRAG book Gallery plugin using GitHub Actions automation with multi-channel release support.

## Release Channels

The plugin supports three release channels to allow phased rollouts and testing:

### Stable (Production)
- **Tag Format**: `v3.3.0`, `v3.3.1`
- **Audience**: All users (default channel)
- **Purpose**: Production-ready, fully tested releases
- **GitHub**: Marked as stable release (`prerelease: false`)

### Release Candidate (RC)
- **Tag Format**: `v3.3.1-rc1`, `v3.3.1-rc2`
- **Audience**: Users who opt-in to RC channel
- **Purpose**: Near-final versions for testing before stable release
- **GitHub**: Marked as prerelease (`prerelease: true`)

### Beta (Early Testing)
- **Tag Format**: `v3.3.1-beta1`, `v3.3.1-beta2`
- **Audience**: Users who opt-in to beta channel
- **Purpose**: Early access to new features for testing
- **GitHub**: Marked as prerelease (`prerelease: true`)

## How the Auto-Update System Works

The plugin includes an updater class (`includes/core/class-updater.php`) that:
- Checks the GitHub repository `bragbook2/brag-book-gallery` for new releases
- Filters releases based on user's selected channel preference
- Compares versions with the currently installed plugin
- Allows updates directly from the WordPress admin panel
- Downloads the `brag-book-gallery.zip` file automatically created by GitHub Actions

### Channel Filtering Logic

- **Stable Channel**: Only shows stable releases (e.g., `v3.3.0`)
- **RC Channel**: Shows RC and stable releases (e.g., `v3.3.1-rc1`, `v3.3.0`)
- **Beta Channel**: Shows beta, RC, and stable releases (e.g., `v3.3.1-beta1`, `v3.3.1-rc1`, `v3.3.0`)

Users can configure their preferred channel in **BRAG book → Settings → General → Plugin Update Channel**.

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

The tag format determines the release channel:

**For Stable Release:**
```bash
# Create stable release tag
git tag v3.3.1

# Push tag - this triggers the GitHub Actions workflow
git push origin v3.3.1
```

**For Release Candidate:**
```bash
# Create RC release tag
git tag v3.3.1-rc1

# Push tag
git push origin v3.3.1-rc1
```

**For Beta Release:**
```bash
# Create beta release tag
git tag v3.3.1-beta1

# Push tag
git push origin v3.3.1-beta1
```

### What Happens Next (Automated)

Once you push the tag, GitHub Actions automatically:
1. **Detects release type** - Based on tag suffix (-beta, -rc, or none)
2. **Builds production assets** - Runs `npm run build`
3. **Creates distribution** - Removes dev files, source files, tests
4. **Generates zip file** - Creates `brag-book-gallery.zip`
5. **Creates GitHub release** - Marked as prerelease if beta/RC
6. **Attaches zip file** - Adds the plugin zip as a release asset

The release will be available at:
`https://github.com/bragbook2/brag-book-gallery/releases/tag/vX.X.X`

## Release Workflow Examples

### Example 1: Beta → RC → Stable Release

```bash
# 1. Create beta for early testing
git tag v3.4.0-beta1
git push origin v3.4.0-beta1
# → Only beta channel users see this

# 2. After testing, create RC
git tag v3.4.0-rc1
git push origin v3.4.0-rc1
# → Beta and RC channel users see this

# 3. After final testing, create stable
git tag v3.4.0
git push origin v3.4.0
# → All users see this
```

### Example 2: Hotfix (Skip Testing Channels)

```bash
# For critical bugfixes, go straight to stable
git tag v3.3.2
git push origin v3.3.2
# → All users see this immediately
```

### Example 3: Multiple Beta Iterations

```bash
# First beta
git tag v3.4.0-beta1
git push origin v3.4.0-beta1

# Fix issues, release beta2
git tag v3.4.0-beta2
git push origin v3.4.0-beta2

# More fixes, release beta3
git tag v3.4.0-beta3
git push origin v3.4.0-beta3

# Ready for RC
git tag v3.4.0-rc1
git push origin v3.4.0-rc1
```

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

Follow semantic versioning (MAJOR.MINOR.PATCH) with optional prerelease suffix:
- **MAJOR**: Breaking changes
- **MINOR**: New features (backward compatible)
- **PATCH**: Bug fixes
- **Prerelease Suffix**: `-beta#` or `-rc#` for testing versions

### Version Examples

**Stable Releases:**
- `v3.0.0` → `v3.0.1` (bug fix)
- `v3.0.1` → `v3.1.0` (new feature)
- `v3.1.0` → `v4.0.0` (breaking change)

**Prerelease Versions:**
- `v3.4.0-beta1` → First beta of upcoming 3.4.0
- `v3.4.0-beta2` → Second beta iteration
- `v3.4.0-rc1` → Release candidate before 3.4.0
- `v3.4.0` → Final stable release

### Version Precedence

When users are on different channels, they see different "latest" versions:
- User on **Stable**: Latest stable only (e.g., `v3.3.0`)
- User on **RC**: Latest RC or stable (e.g., `v3.4.0-rc1` > `v3.3.0`)
- User on **Beta**: Latest of any type (e.g., `v3.4.0-beta2` > `v3.4.0-rc1` > `v3.3.0`)

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

To test if auto-updates work across different channels:

### Testing Stable Channel (Default)

1. **Install an older version** of the plugin on a test site
2. **Ensure channel is set to Stable** (BRAG book → Settings → General → Plugin Update Channel)
3. **Create a new stable release** on GitHub (e.g., `v3.3.1`)
4. **Check for updates** in WordPress admin:
   - Go to Dashboard → Updates
   - Or Plugins page
5. **Verify** only stable versions appear

### Testing RC Channel

1. **Switch to RC channel** in plugin settings
2. **Create an RC release** (e.g., `v3.4.0-rc1`)
3. **Clear update cache**:
   ```bash
   wp transient delete brag_book_gallery_github_release_*
   delete_site_transient( 'update_plugins' );
   ```
4. **Check for updates** - should see RC releases
5. **Verify** beta releases don't appear

### Testing Beta Channel

1. **Switch to Beta channel** in plugin settings
2. **Create a beta release** (e.g., `v3.4.0-beta1`)
3. **Clear update cache** (same as above)
4. **Check for updates** - should see beta releases
5. **Verify** all release types appear in correct order

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
2. **Tag Format**:
   - Must use 'v' prefix (e.g., `v3.0.7`) to trigger the workflow
   - Stable: `vX.X.X` (e.g., `v3.3.1`)
   - RC: `vX.X.X-rcN` (e.g., `v3.4.0-rc1`)
   - Beta: `vX.X.X-betaN` (e.g., `v3.4.0-beta1`)
3. **Automatic Zip Creation**: GitHub Actions creates `brag-book-gallery.zip` automatically
4. **Version Consistency**: Ensure version numbers match in `brag-book-gallery.php` and `package.json`
5. **GitHub API Rate Limits**: The updater caches API responses for 1 hour (per channel)
6. **Build Process**: Assets are built automatically - no need to run `npm run build` locally
7. **Release Channels**:
   - Stable users only see stable releases
   - RC users see RC and stable releases
   - Beta users see all releases
8. **Prerelease Marking**: Beta and RC releases are automatically marked as `prerelease: true` on GitHub

## Support

For issues with the release process:
- Check GitHub Actions logs
- Review the updater class debug output
- Contact the development team

---

## Quick Release Commands

### Stable Release (Production)
```bash
# 1. Update version in files (brag-book-gallery.php and package.json)
# 2. Commit changes
git add .
git commit -m "Release version 3.3.1"

# 3. Push and create stable release
git push origin main
git tag v3.3.1
git push origin v3.3.1
# → All users will see this update
```

### Release Candidate
```bash
# 1. Update version in files to include -rc1
# 2. Commit changes
git add .
git commit -m "Release version 3.4.0-rc1"

# 3. Push and create RC release
git push origin main
git tag v3.4.0-rc1
git push origin v3.4.0-rc1
# → Only RC and Beta channel users will see this
```

### Beta Release
```bash
# 1. Update version in files to include -beta1
# 2. Commit changes
git add .
git commit -m "Release version 3.4.0-beta1"

# 3. Push and create beta release
git push origin main
git tag v3.4.0-beta1
git push origin v3.4.0-beta1
# → Only Beta channel users will see this
```

**GitHub Actions automatically:**
- Detects release type from tag
- Builds production assets
- Creates brag-book-gallery.zip
- Creates GitHub release (marked as prerelease for beta/RC)
- Attaches the zip file

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