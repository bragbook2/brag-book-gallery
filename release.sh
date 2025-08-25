#!/bin/bash

# BRAGBook Gallery GitHub Release Script
# This script creates a GitHub release with the plugin package

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
GITHUB_USER="bragbook2"
GITHUB_REPO="brag-book-gallery"
PLUGIN_SLUG="brag-book-gallery"

# Check if gh CLI is installed
if ! command -v gh &> /dev/null; then
    echo -e "${RED}GitHub CLI (gh) is not installed!${NC}"
    echo "Please install it from: https://cli.github.com/"
    exit 1
fi

# Check if we're in a git repository
if [ ! -d .git ]; then
    echo -e "${RED}This is not a git repository!${NC}"
    echo "Please run this script from the plugin root directory."
    exit 1
fi

# Get version from plugin file
VERSION=$(grep "Version:" brag-book-gallery.php | awk '{print $2}' | tr -d ' ')

if [ -z "$VERSION" ]; then
    echo -e "${RED}Could not determine plugin version!${NC}"
    exit 1
fi

echo -e "${BLUE}╔════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║   BRAGBook Gallery Release Creator     ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════╝${NC}"
echo ""
echo -e "${GREEN}Current version: ${VERSION}${NC}"
echo ""

# Ask for new version
read -p "Enter new version number (or press Enter to use ${VERSION}): " NEW_VERSION
if [ -z "$NEW_VERSION" ]; then
    NEW_VERSION=$VERSION
fi

# Ask for release notes
echo ""
echo -e "${YELLOW}Enter release notes (press Ctrl+D when done):${NC}"
RELEASE_NOTES=$(cat)

# Update version in files
echo ""
echo -e "${YELLOW}Updating version numbers...${NC}"
sed -i.bak "s/Version: .*/Version: ${NEW_VERSION}/" brag-book-gallery.php
sed -i.bak "s/\"version\": \".*\"/\"version\": \"${NEW_VERSION}\"/" package.json
if [ -f "readme.txt" ]; then
    sed -i.bak "s/Stable tag: .*/Stable tag: ${NEW_VERSION}/" readme.txt
fi

# Clean up backup files
rm -f *.bak

# Build the plugin
echo -e "${YELLOW}Building plugin package...${NC}"
./build.sh > /dev/null 2>&1

if [ $? -ne 0 ]; then
    echo -e "${RED}Build failed!${NC}"
    exit 1
fi

# Rename zip file to match expected name
cp ${PLUGIN_SLUG}-${NEW_VERSION}.zip ${PLUGIN_SLUG}.zip

# Commit version changes
echo -e "${YELLOW}Committing version changes...${NC}"
git add brag-book-gallery.php package.json readme.txt 2>/dev/null
git commit -m "Bump version to ${NEW_VERSION}" 2>/dev/null

# Create and push tag
echo -e "${YELLOW}Creating git tag v${NEW_VERSION}...${NC}"
git tag -a "v${NEW_VERSION}" -m "Release version ${NEW_VERSION}"

# Push changes and tag
echo -e "${YELLOW}Pushing to GitHub...${NC}"
git push origin main
git push origin "v${NEW_VERSION}"

# Create GitHub release
echo -e "${YELLOW}Creating GitHub release...${NC}"

# Create release notes file
cat > release_notes.md << EOF
## 🎉 BRAGBook Gallery v${NEW_VERSION}

### What's New
${RELEASE_NOTES}

### Installation
1. Download \`brag-book-gallery.zip\` from the assets below
2. In WordPress admin, go to Plugins → Add New → Upload Plugin
3. Choose the downloaded zip file and click "Install Now"
4. Activate the plugin

### Update Notes
- The plugin will automatically check for updates from this repository
- You can update directly from your WordPress admin when new versions are available

### Requirements
- WordPress: 6.0 or higher
- PHP: 8.2 or higher

### Support
For issues or questions, please visit: https://github.com/${GITHUB_USER}/${GITHUB_REPO}/issues

---

**Full Changelog**: https://github.com/${GITHUB_USER}/${GITHUB_REPO}/compare/v3.0.0...v${NEW_VERSION}
EOF

# Create release using GitHub CLI
gh release create "v${NEW_VERSION}" \
    --repo "${GITHUB_USER}/${GITHUB_REPO}" \
    --title "BRAGBook Gallery v${NEW_VERSION}" \
    --notes-file release_notes.md \
    "${PLUGIN_SLUG}.zip#BRAGBook Gallery Plugin (ZIP)"

if [ $? -eq 0 ]; then
    echo ""
    echo -e "${GREEN}═══════════════════════════════════════════${NC}"
    echo -e "${GREEN}✓ Release v${NEW_VERSION} created successfully!${NC}"
    echo -e "${GREEN}═══════════════════════════════════════════${NC}"
    echo ""
    echo -e "View release: ${BLUE}https://github.com/${GITHUB_USER}/${GITHUB_REPO}/releases/tag/v${NEW_VERSION}${NC}"
    echo ""
    echo -e "${YELLOW}The plugin will now be available for auto-updates in WordPress!${NC}"
else
    echo -e "${RED}Failed to create GitHub release!${NC}"
    echo "You can create it manually at: https://github.com/${GITHUB_USER}/${GITHUB_REPO}/releases/new"
fi

# Clean up
rm -f release_notes.md
rm -f ${PLUGIN_SLUG}-${NEW_VERSION}.zip
rm -f ${PLUGIN_SLUG}.zip