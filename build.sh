#!/bin/bash

# BRAGBook Gallery Plugin Build Script
# This script creates a production-ready WordPress plugin package
# suitable for submission to the WordPress.org plugin repository.

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

PLUGIN_SLUG="brag-book-gallery"
VERSION=$(grep "^ \* Version:" brag-book-gallery.php | awk '{print $3}')

echo -e "${GREEN}Building BRAGBook Gallery Plugin v${VERSION}${NC}"
echo "========================================"

# 1. Clean previous builds
echo -e "${YELLOW}Cleaning previous builds...${NC}"
rm -rf dist/
rm -f ${PLUGIN_SLUG}.zip
rm -f ${PLUGIN_SLUG}-*.zip

# 2. Build assets
echo -e "${YELLOW}Building production assets...${NC}"
npm run clean
npm run build

if [ $? -ne 0 ]; then
    echo -e "${RED}Asset build failed!${NC}"
    exit 1
fi

# 2b. Restore vendor JS files (npm clean wipes assets/js/*)
echo -e "${YELLOW}Restoring vendor assets...${NC}"
mkdir -p assets/js/vendor
cp node_modules/chart.js/dist/chart.umd.js assets/js/vendor/chart.min.js

# 3. Create dist directory
echo -e "${YELLOW}Creating distribution directory...${NC}"
mkdir -p dist/${PLUGIN_SLUG}

# 4. Copy files (excluding those in .distignore)
echo -e "${YELLOW}Copying plugin files...${NC}"
rsync -av --exclude-from=.distignore ./ dist/${PLUGIN_SLUG}/

# 5. Remove any remaining development files
echo -e "${YELLOW}Cleaning up development files...${NC}"
find dist/${PLUGIN_SLUG} -name "*.scss" -type f -delete
find dist/${PLUGIN_SLUG} -name "*.map" -type f -delete
find dist/${PLUGIN_SLUG} -name ".DS_Store" -type f -delete
find dist/${PLUGIN_SLUG} -name "Thumbs.db" -type f -delete
find dist/${PLUGIN_SLUG} -type d -name ".git" -exec rm -rf {} + 2>/dev/null
find dist/${PLUGIN_SLUG} -type d -name "node_modules" -exec rm -rf {} + 2>/dev/null
find dist/${PLUGIN_SLUG} -type d -name "src" -exec rm -rf {} + 2>/dev/null
find dist/${PLUGIN_SLUG} -type d -name "tests" -exec rm -rf {} + 2>/dev/null

# 6. Strip GitHub updater (not allowed on WordPress.org)
echo -e "${YELLOW}Stripping GitHub updater for WordPress.org distribution...${NC}"

# Remove the Updater class file
rm -f dist/${PLUGIN_SLUG}/includes/core/class-updater.php

# Remove the Updater import line from the main plugin file
sed -i '' '/^use BRAGBookGallery\\Includes\\Core\\Updater;$/d' dist/${PLUGIN_SLUG}/brag-book-gallery.php

# Remove the admin_init updater block (lines from add_action to closing });)
sed -i '' "/^add_action( 'admin_init', function () {$/,/^} );$/d" dist/${PLUGIN_SLUG}/brag-book-gallery.php

# Verify the updater was removed
if grep -q "Updater" dist/${PLUGIN_SLUG}/brag-book-gallery.php; then
    echo -e "${RED}WARNING: Updater references may still exist in the main plugin file.${NC}"
    echo -e "${RED}Please verify dist/${PLUGIN_SLUG}/brag-book-gallery.php manually.${NC}"
else
    echo -e "${GREEN}GitHub updater successfully stripped from distribution.${NC}"
fi

# 7. Create zip file
echo -e "${YELLOW}Creating plugin package...${NC}"
cd dist
zip -r ../${PLUGIN_SLUG}-${VERSION}.zip ${PLUGIN_SLUG}/ -x "*.DS_Store" -x "*__MACOSX*"
cd ..

# 8. Create a copy without version number for easy distribution
cp ${PLUGIN_SLUG}-${VERSION}.zip ${PLUGIN_SLUG}.zip

# 9. Report success
echo ""
echo -e "${GREEN}Build complete!${NC}"
echo "========================================"
echo -e "Plugin package created: ${GREEN}${PLUGIN_SLUG}-${VERSION}.zip${NC}"
echo -e "Also available as: ${GREEN}${PLUGIN_SLUG}.zip${NC}"
echo ""
echo "File size: $(du -h ${PLUGIN_SLUG}.zip | cut -f1)"
echo ""
echo -e "${YELLOW}Pre-submission checklist:${NC}"
echo "  1. Test the zip by installing on a clean WordPress site"
echo "  2. Run the Plugin Check plugin (https://wordpress.org/plugins/plugin-check/)"
echo "  3. Verify the updater code was fully removed from the dist"
echo "  4. Submit at https://wordpress.org/plugins/developers/add/"
