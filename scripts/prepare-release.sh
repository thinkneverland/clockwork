#!/bin/bash

# Tapped Release Preparation Script
# =================================
# This script helps prepare a new release of the Tapped package

# Set colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BLUE='\033[0;34m'
BOLD='\033[1m'
NC='\033[0m' # No Color

echo -e "${BLUE}${BOLD}Tapped Release Preparation Tool${NC}\n"

# Get current version from composer.json
CURRENT_VERSION=$(grep -o '"version": "[^"]*"' composer.json | cut -d'"' -f4)
if [ -z "$CURRENT_VERSION" ]; then
    CURRENT_VERSION="0.0.0"
    echo -e "${YELLOW}No version found in composer.json. Assuming ${CURRENT_VERSION}${NC}"
fi

# Ask for new version
echo -e "${YELLOW}Current version is: ${CURRENT_VERSION}${NC}"
read -p "Enter new version (without v prefix): " NEW_VERSION

if [ -z "$NEW_VERSION" ]; then
    echo -e "${RED}No version provided. Exiting.${NC}"
    exit 1
fi

echo -e "\n${BLUE}Preparing release v${NEW_VERSION}...${NC}"

# Validation
echo -e "\n${YELLOW}Running pre-release validation...${NC}"

# Check if composer.json is valid
echo -e "Validating composer.json..."
composer validate
if [ $? -ne 0 ]; then
    echo -e "${RED}composer.json validation failed. Please fix errors before proceeding.${NC}"
    exit 1
fi

# Run tests
echo -e "\nRunning tests..."
composer test
if [ $? -ne 0 ]; then
    echo -e "${RED}Tests failed. Do you want to continue anyway? (y/N)${NC}"
    read -p "" CONTINUE_AFTER_TEST_FAILURE
    if [ "$CONTINUE_AFTER_TEST_FAILURE" != "y" ] && [ "$CONTINUE_AFTER_TEST_FAILURE" != "Y" ]; then
        echo -e "${RED}Exiting due to test failures.${NC}"
        exit 1
    fi
fi

# Update version in files
echo -e "\n${YELLOW}Updating version in files...${NC}"

# Update composer.json
if grep -q '"version":' composer.json; then
    sed -i '' "s/\"version\": \".*\"/\"version\": \"$NEW_VERSION\"/" composer.json
else
    # If version doesn't exist, add it after name
    sed -i '' "s/\"name\": \"thinkneverland\/tapped\",/\"name\": \"thinkneverland\/tapped\",\n    \"version\": \"$NEW_VERSION\",/" composer.json
fi
echo -e "Updated version in composer.json"

# Update extension manifest files
if [ -f "extension/chrome/manifest.json" ]; then
    sed -i '' "s/\"version\": \".*\"/\"version\": \"$NEW_VERSION\"/" extension/chrome/manifest.json
    echo -e "Updated version in Chrome extension manifest"
fi

if [ -f "extension/firefox/manifest.json" ]; then
    sed -i '' "s/\"version\": \".*\"/\"version\": \"$NEW_VERSION\"/" extension/firefox/manifest.json
    echo -e "Updated version in Firefox extension manifest"
fi

# Update VS Code extension
if [ -f "ide-integrations/vscode/package.json" ]; then
    sed -i '' "s/\"version\": \".*\"/\"version\": \"$NEW_VERSION\"/" ide-integrations/vscode/package.json
    echo -e "Updated version in VS Code extension package.json"
fi

# Update JetBrains plugin
if [ -f "ide-integrations/jetbrains/src/main/resources/META-INF/plugin.xml" ]; then
    sed -i '' "s/<version>.*<\/version>/<version>$NEW_VERSION<\/version>/" ide-integrations/jetbrains/src/main/resources/META-INF/plugin.xml
    echo -e "Updated version in JetBrains plugin.xml"
fi

# Check CHANGELOG.md
if [ -f "CHANGELOG.md" ]; then
    if grep -q "\[$NEW_VERSION\]" CHANGELOG.md; then
        echo -e "CHANGELOG.md already contains entry for version $NEW_VERSION"
    else
        echo -e "${YELLOW}CHANGELOG.md does not contain an entry for version $NEW_VERSION.${NC}"
        echo -e "${YELLOW}Please update CHANGELOG.md before proceeding.${NC}"
        read -p "Open CHANGELOG.md for editing? (Y/n): " EDIT_CHANGELOG
        if [ "$EDIT_CHANGELOG" != "n" ] && [ "$EDIT_CHANGELOG" != "N" ]; then
            ${EDITOR:-vi} CHANGELOG.md
        fi
    fi
fi

# Commit changes
echo -e "\n${YELLOW}Ready to commit changes for version $NEW_VERSION${NC}"
read -p "Commit changes? (Y/n): " COMMIT_CHANGES
if [ "$COMMIT_CHANGES" != "n" ] && [ "$COMMIT_CHANGES" != "N" ]; then
    git add composer.json
    [ -f "extension/chrome/manifest.json" ] && git add extension/chrome/manifest.json
    [ -f "extension/firefox/manifest.json" ] && git add extension/firefox/manifest.json
    [ -f "ide-integrations/vscode/package.json" ] && git add ide-integrations/vscode/package.json
    [ -f "ide-integrations/jetbrains/src/main/resources/META-INF/plugin.xml" ] && git add ide-integrations/jetbrains/src/main/resources/META-INF/plugin.xml
    [ -f "CHANGELOG.md" ] && git add CHANGELOG.md
    
    git commit -m "Prepare release v$NEW_VERSION"
    echo -e "${GREEN}Changes committed.${NC}"
    
    # Create tag
    echo -e "\n${YELLOW}Creating git tag v$NEW_VERSION${NC}"
    read -p "Create tag? (Y/n): " CREATE_TAG
    if [ "$CREATE_TAG" != "n" ] && [ "$CREATE_TAG" != "N" ]; then
        git tag -a "v$NEW_VERSION" -m "Release v$NEW_VERSION"
        echo -e "${GREEN}Tag created.${NC}"
        
        # Push changes
        echo -e "\n${YELLOW}Ready to push changes and tags${NC}"
        read -p "Push to remote? (Y/n): " PUSH_CHANGES
        if [ "$PUSH_CHANGES" != "n" ] && [ "$PUSH_CHANGES" != "N" ]; then
            git push origin "v$NEW_VERSION"
            git push
            echo -e "${GREEN}Changes and tags pushed to remote.${NC}"
        fi
    fi
fi

echo -e "\n${GREEN}${BOLD}Release v$NEW_VERSION preparation complete!${NC}"
echo -e "\n${YELLOW}Next steps:${NC}"
echo -e "1. Create a GitHub release at https://github.com/thinkneverland/tapped/releases/new"
echo -e "2. Upload extension packages if applicable"
echo -e "3. Publish extensions to respective marketplaces"
echo -e "4. Announce the release to users\n"
