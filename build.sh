#!/bin/bash

# Build script for wp-opengdpr plugin zip bundle

PLUGIN_SLUG="wp-opengdpr"
OUTPUT_DIR="./dist"
ZIP_FILE="${OUTPUT_DIR}/${PLUGIN_SLUG}.zip"

# Create output directory if it doesn't exist
mkdir -p "$OUTPUT_DIR"

# Remove previous zip if exists
rm -f "$ZIP_FILE"

echo "Building ${PLUGIN_SLUG} zip bundle..."

zip -r "$ZIP_FILE" . \
    --include \
        "wp-opengdpr.php" \
        "uninstall.php" \
        "readme.txt" \
        "admin/*" \
        "includes/*" \
        "languages/*" \
        "public/*" \
        "templates/*" \
    --exclude "*.git*"

echo "Done! Bundle created at: ${ZIP_FILE}"

