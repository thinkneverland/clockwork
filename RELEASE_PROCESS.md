# Tapped Release Process

This document outlines the process for preparing and releasing new versions of the Tapped package.

## 1. Pre-Release Checklist

Before proceeding with a release, ensure all the following items are complete:

- [ ] All planned features implemented
- [ ] All tests passing (unit, feature, browser)
- [ ] Code review completed
- [ ] Documentation updated and accurate
- [ ] CHANGELOG.md updated with all changes
- [ ] Version numbers updated in relevant files

## 2. Code Quality Verification

Run the following checks to ensure code quality:

```bash
# Run all tests
composer test

# Check code style
composer cs:check

# Run static analysis
composer analyze

# Check for security vulnerabilities
composer security:check
```

## 3. Documentation Review

- [ ] Verify all documentation is up-to-date
- [ ] Check for broken links
- [ ] Ensure installation instructions work
- [ ] Verify configuration options are accurate
- [ ] Check API documentation completeness

## 4. Version Update

1. Update version numbers in:
   - [ ] `composer.json`
   - [ ] Browser extension `manifest.json`
   - [ ] VS Code extension `package.json`
   - [ ] JetBrains plugin `plugin.xml`

2. Update CHANGELOG.md:
   - [ ] Add new version section
   - [ ] Document all changes, enhancements, and bug fixes
   - [ ] Add links to relevant issues and PRs
   - [ ] Include upgrade instructions if necessary

## 5. Tag and Release

1. Create a new release branch:
   ```bash
   git checkout -b release/v1.0.0
   ```

2. Commit version changes:
   ```bash
   git add .
   git commit -m "Prepare release v1.0.0"
   ```

3. Create a pull request for the release branch
   - Have the PR reviewed by at least one team member
   - Merge to main once approved

4. Tag the release:
   ```bash
   git checkout main
   git pull
   git tag -a v1.0.0 -m "Release v1.0.0"
   git push origin v1.0.0
   ```

5. Create a GitHub release:
   - Go to Releases on GitHub
   - Create a new release from the tag
   - Copy release notes from CHANGELOG.md
   - Upload compiled extensions and any relevant assets

## 6. Package Publication

1. Verify the package before submission:
   ```bash
   composer validate
   ```

2. Publish to Packagist:
   - Ensure GitHub webhook to Packagist is set up
   - Packagist should automatically update when the new tag is pushed
   - Verify package appears on Packagist with correct version

## 7. Extension Publication

1. Browser Extension:
   - Prepare the extension package
   - Submit to Chrome Web Store
   - Submit to Firefox Add-ons
   - Submit to Microsoft Edge Add-ons

2. VS Code Extension:
   - Prepare VSIX package
   - Publish to VS Code Marketplace

3. JetBrains Plugin:
   - Prepare plugin ZIP
   - Submit to JetBrains Marketplace

## 8. Post-Release

1. Announcement:
   - Blog post about the new release
   - Social media announcements
   - Email newsletter if applicable

2. Monitor:
   - Watch for issues reported by early adopters
   - Be prepared to release patch versions for critical bugs

3. Planning:
   - Start planning for next release
   - Gather feedback for future improvements

## Hotfix Process

For critical issues that need immediate attention:

1. Create a hotfix branch from the release tag:
   ```bash
   git checkout -b hotfix/v1.0.1 v1.0.0
   ```

2. Fix the issue and commit:
   ```bash
   git add .
   git commit -m "Fix critical issue"
   ```

3. Update version and CHANGELOG.md
   
4. Tag and release following steps 4-8 above

## Version Numbering

Tapped follows Semantic Versioning (SemVer):

- **MAJOR** version for incompatible API changes
- **MINOR** version for backwards-compatible functionality
- **PATCH** version for backwards-compatible bug fixes
