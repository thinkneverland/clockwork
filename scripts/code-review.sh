#!/bin/bash

# Code Review Script for Tapped
# =============================
# This script performs automated code quality checks and assists with manual code review.

# Set colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${GREEN}Starting code review for Tapped...${NC}\n"

# Check if necessary tools are installed
echo -e "${YELLOW}Checking for required tools...${NC}"
command -v php >/dev/null 2>&1 || { echo -e "${RED}PHP is required but not installed.${NC}" >&2; exit 1; }
command -v composer >/dev/null 2>&1 || { echo -e "${RED}Composer is required but not installed.${NC}" >&2; exit 1; }

# Run PHPStan for static analysis
echo -e "\n${YELLOW}Running PHPStan static analysis...${NC}"
if [ -f vendor/bin/phpstan ]; then
    vendor/bin/phpstan analyse src --level=5
else
    echo -e "${RED}PHPStan not found. Please run 'composer require --dev phpstan/phpstan' first.${NC}"
fi

# Run PHP_CodeSniffer for coding standards
echo -e "\n${YELLOW}Checking coding standards with PHP_CodeSniffer...${NC}"
if [ -f vendor/bin/phpcs ]; then
    vendor/bin/phpcs --standard=PSR12 src
else
    echo -e "${RED}PHP_CodeSniffer not found. Please run 'composer require --dev squizlabs/php_codesniffer' first.${NC}"
fi

# Check for PHP syntax errors
echo -e "\n${YELLOW}Checking for PHP syntax errors...${NC}"
find src -name "*.php" -type f -exec php -l {} \; | grep -v "No syntax errors"

# Run PHPUnit tests
echo -e "\n${YELLOW}Running PHPUnit tests...${NC}"
if [ -f vendor/bin/phpunit ]; then
    vendor/bin/phpunit
else
    echo -e "${RED}PHPUnit not found. Please run 'composer require --dev phpunit/phpunit' first.${NC}"
fi

# Check for security vulnerabilities
echo -e "\n${YELLOW}Checking for security vulnerabilities...${NC}"
if [ -f vendor/bin/security-checker ]; then
    vendor/bin/security-checker security:check composer.lock
else
    echo -e "${YELLOW}Security Checker not found. Consider running 'composer require --dev enlightn/security-checker' for security checks.${NC}"
fi

# Code complexity checks
echo -e "\n${YELLOW}Analyzing code complexity...${NC}"
if [ -f vendor/bin/phploc ]; then
    vendor/bin/phploc src
else
    echo -e "${YELLOW}PHPLOC not found. Consider running 'composer require --dev phploc/phploc' for code complexity analysis.${NC}"
fi

# Manual review reminders
echo -e "\n${GREEN}Automated checks complete. Manual review checklist:${NC}"
echo -e "  ${YELLOW}1. Review all public API methods for usability and documentation${NC}"
echo -e "  ${YELLOW}2. Check error handling and edge cases${NC}"
echo -e "  ${YELLOW}3. Verify proper authentication and security measures${NC}"
echo -e "  ${YELLOW}4. Confirm that all configuration options work as expected${NC}"
echo -e "  ${YELLOW}5. Validate browser extension functionality${NC}"
echo -e "  ${YELLOW}6. Check WebSocket server performance${NC}"
echo -e "  ${YELLOW}7. Verify IDE integrations${NC}"
echo -e "  ${YELLOW}8. Ensure documentation matches implementation${NC}"

echo -e "\n${GREEN}Code review complete!${NC}"
