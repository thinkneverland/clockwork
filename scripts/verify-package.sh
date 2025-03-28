#!/bin/bash

# Package Verification Script for Tapped
# =====================================
# This script verifies that the package is ready for release

# Set colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}Tapped Package Verification${NC}\n"

# Check for required files
echo -e "${YELLOW}Checking for required files...${NC}"
required_files=(
    "composer.json"
    "LICENSE.md"
    "README.md"
    "CHANGELOG.md"
    "phpunit.xml"
    ".github/workflows/tests.yml"
    ".github/workflows/release-checks.yml"
    "docs/installation/README.md"
    "docs/api/README.md"
    "docs/configuration/README.md"
    "docs/user-guides/README.md"
    "docs/developer/README.md"
)

missing_files=0
for file in "${required_files[@]}"; do
    if [ ! -f "$file" ]; then
        echo -e "${RED}Missing required file: $file${NC}"
        missing_files=$((missing_files + 1))
    fi
done

if [ $missing_files -eq 0 ]; then
    echo -e "${GREEN}All required files present.${NC}"
else
    echo -e "${RED}$missing_files required files are missing.${NC}"
fi

# Check version consistency
echo -e "\n${YELLOW}Checking version consistency...${NC}"
composer_version=$(grep -o '"version": "[^"]*"' composer.json | cut -d'"' -f4)
echo -e "Composer version: ${GREEN}$composer_version${NC}"

if [ -f "extension/chrome/manifest.json" ]; then
    chrome_version=$(grep -o '"version": "[^"]*"' extension/chrome/manifest.json | cut -d'"' -f4)
    echo -e "Chrome extension version: ${GREEN}$chrome_version${NC}"
    if [ "$chrome_version" != "$composer_version" ]; then
        echo -e "${RED}Chrome extension version mismatch!${NC}"
    fi
fi

if [ -f "ide-integrations/vscode/package.json" ]; then
    vscode_version=$(grep -o '"version": "[^"]*"' ide-integrations/vscode/package.json | cut -d'"' -f4)
    echo -e "VS Code extension version: ${GREEN}$vscode_version${NC}"
    if [ "$vscode_version" != "$composer_version" ]; then
        echo -e "${RED}VS Code extension version mismatch!${NC}"
    fi
fi

# Check if version exists in CHANGELOG.md
if [ -f "CHANGELOG.md" ]; then
    if grep -q "\[$composer_version\]" CHANGELOG.md; then
        echo -e "CHANGELOG.md contains entry for version ${GREEN}$composer_version${NC}"
    else
        echo -e "${RED}CHANGELOG.md does not contain an entry for version $composer_version!${NC}"
    fi
fi

# Validate composer.json
echo -e "\n${YELLOW}Validating composer.json...${NC}"
composer validate
if [ $? -ne 0 ]; then
    echo -e "${RED}composer.json validation failed!${NC}"
else
    echo -e "${GREEN}composer.json is valid.${NC}"
fi

# Run tests if available
echo -e "\n${YELLOW}Running tests...${NC}"
if [ -f "vendor/bin/pest" ]; then
    vendor/bin/pest --no-coverage
    if [ $? -ne 0 ]; then
        echo -e "${RED}Tests failed!${NC}"
    else
        echo -e "${GREEN}All tests passed.${NC}"
    fi
else
    echo -e "${YELLOW}Pest not installed, skipping tests.${NC}"
fi

# Check code style
echo -e "\n${YELLOW}Checking code style...${NC}"
if [ -f "vendor/bin/pint" ]; then
    vendor/bin/pint --test
    if [ $? -ne 0 ]; then
        echo -e "${RED}Code style check failed!${NC}"
    else
        echo -e "${GREEN}Code style check passed.${NC}"
    fi
else
    echo -e "${YELLOW}Laravel Pint not installed, skipping code style check.${NC}"
fi

# Check for TODO, FIXME comments
echo -e "\n${YELLOW}Checking for TODO and FIXME comments...${NC}"
todos=$(grep -r "TODO\|FIXME" --include="*.php" src)
if [ -n "$todos" ]; then
    echo -e "${RED}Found TODO/FIXME comments:${NC}"
    echo "$todos"
else
    echo -e "${GREEN}No TODO/FIXME comments found.${NC}"
fi

# Summary
echo -e "\n${BLUE}Verification Summary${NC}"
echo -e "================================"
if [ $missing_files -eq 0 ] && [ $? -eq 0 ]; then
    echo -e "${GREEN}Package looks ready for release!${NC}"
else
    echo -e "${RED}Package has issues that should be addressed before release.${NC}"
fi
