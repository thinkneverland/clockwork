# Security Policy

## Supported Versions

Use this section to tell people about which versions of your project are currently being supported with security updates.

| Version | Supported          |
| ------- | ------------------ |
| 1.0.x   | :white_check_mark: |

## Reporting a Vulnerability

The Tapped team takes security issues seriously. We appreciate your efforts to responsibly disclose your findings and will make every effort to acknowledge your contributions.

To report a security vulnerability, please follow these steps:

1. **DO NOT** disclose the vulnerability publicly in GitHub issues, forums, or elsewhere
2. Email your findings to security@thinkneverland.com
3. Include the following details in your report:
   - Type of issue
   - Steps to reproduce the issue
   - Impact of the issue
   - Any potential solutions you have identified
   - Any relevant screenshots or proof-of-concept code

We will acknowledge receipt of your vulnerability report as soon as possible and will aim to provide an initial assessment within 48 hours.

## What to Expect

After receiving your report, we will:

1. Confirm the receipt of your vulnerability report
2. Assess the impact and severity of the issue
3. Develop and test a fix
4. Release a patch and credit you in the release notes (unless you prefer to remain anonymous)

We strive to keep you informed throughout this process.

## Security Considerations

### API Authentication

Tapped's API uses token-based authentication, which is configurable in the `.env` file. Always:

- Use strong, unique tokens for authentication
- Limit API access to authorized environments
- Store tokens securely
- Rotate tokens periodically

### WebSocket Security

The WebSocket server should be configured securely:

- Use SSL in production environments
- Implement proper authentication and validation
- Apply rate limiting
- Be cautious about exposing WebSocket ports publicly

### Data Privacy

Tapped collects debugging information including:

- Livewire component data
- Database queries
- Event information
- HTTP request data

Be aware that this may include sensitive information. Configure Tapped to:

- Filter sensitive data in production environments
- Limit storage of debugging data
- Implement proper access controls
- Configure webhook endpoints securely

## Disclosure Policy

When vulnerabilities are reported, we follow this disclosure timeline:

- Report Received: We aim to acknowledge within 24 hours
- Assessment: Initial assessment within 48 hours
- Fix Development: Depends on severity and complexity
- Fix Release: As soon as practical after development
- Public Disclosure: After fix is available and users have had time to update

We reserve the right to adjust this timeline based on the severity and complexity of the reported issues.

## Thank You

Security researchers are essential to keeping our users safe. We appreciate your efforts and are committed to working with you to address any security concerns.
