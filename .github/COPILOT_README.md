# GitHub Copilot Configuration for ARS Apps

This directory contains the GitHub Copilot configuration for the ARS Apps Drupal 11 platform, including specialized agents, custom instructions, and automated code review workflows.

---

## 🚀 TL;DR - Quick Start

**For developers joining the project:**

### Local DDEV Development

**Prerequisites:**
```bash
# Install Docker Desktop (or Docker Engine on Linux)
# Then install DDEV:
curl -fsSL https://ddev.com/install.sh | bash

# Install GitHub CLI + Copilot extension (optional but recommended)
gh auth login
gh extension install github/gh-copilot
```

**Start developing:**
```bash
# Clone and start
git clone <repository-url>
cd ars-apps-drupal
ddev start

# Install dependencies
ddev composer install

# Install Drupal (if fresh start)
ddev drush site:install minimal -y

# OR import existing database
ddev import-db --file=/path/to/dump.sql.gz
ddev drush cim -y

# Launch your site
ddev launch

# Run accessibility scan
ddev exec node scripts/axe-scan.js
```

**Use Copilot:**
```bash
# Copilot Chat in VS Code
# Press Ctrl+Shift+I (Cmd+Shift+I on Mac)

# Or use CLI
gh copilot suggest "create a custom block plugin"

# Agent-specific help
@security-agent "review this file for vulnerabilities"
```

### Key Rules
- ✅ **ALWAYS** use DDEV for local development commands
- ✅ **ALWAYS** export config after changes: `ddev drush cex -y`
- ✅ **ALWAYS** update documentation when changing functionality
- ❌ **NEVER** run composer/drush/php directly on host
- ❌ **NEVER** commit secrets or credentials

### 🎯 The Drupal Way - Solution Hierarchy

When solving problems, ALWAYS follow this order:
1. ✅ **Try config first** - Content types, fields, views, blocks
2. ✅ **Use contrib modules** - Thousands of free, maintained modules
3. ✅ **Extend with contrib** - Combine modules before custom code
4. ⚠️ **Custom code** - Only with documented business justification
5. 🛑 **Standalone custom** - Last resort, requires approval

**Example conversation:**
```
You: "I need a custom module to add a fancy animation to forms"

Copilot: "Let's follow the Drupal way:
1. Can this be done with CSS/Twig? (Probably yes ✅)
2. If not, is there a contrib module? (Check drupal.org)
3. Does this provide measurable business value? (Probably no ❌)

Recommendation: Use CSS/JS in theme instead of custom module."
```

**Why this matters:**
- 🚀 Faster development (don't reinvent the wheel)
- 🔒 Better security (contrib modules are reviewed)
- 💰 Lower maintenance cost (community maintains contrib)
- 📈 Easier upgrades (contrib modules update for new Drupal versions)

---

## 📋 Table of Contents

- [Overview](#overview)
- [File Structure](#file-structure)
- [Specialized Agents](#specialized-agents)
- [Using Agents](#using-agents)
- [Code Review Workflow](#code-review-workflow)
- [Custom Instructions](#custom-instructions)
- [Best Practices](#best-practices)
- [Troubleshooting](#troubleshooting)
- [Extending the System](#extending-the-system)

---

## Overview

Our GitHub Copilot setup is designed to:

1. **Enforce DDEV-first development** - All operations use DDEV containers
2. **Maintain high code quality** - Automated checks for standards, security, accessibility
3. **Ensure compliance** - Section 508, WCAG 2.1 AA, USWDS, federal requirements
4. **Provide specialized expertise** - Domain-specific agents for different concerns
5. **Automate code review** - Intelligent, scope-aware PR reviews

**Key Features:**
- 🤖 **7 specialized agents** for different review dimensions
- 🔍 **Intelligent scope detection** - Only runs relevant tests
- 🚀 **DDEV-based testing** - Isolated, reproducible environments
- 📚 **Comprehensive documentation** - Every agent has detailed guidance
- ⚙️ **Automated workflows** - CI/CD integration via GitHub Actions

---

## File Structure
```
.github/
├── COPILOT_README.md              # This file
├── copilot-instructions.md        # Main Copilot instructions
├── copilot-cr-instructions.md     # Code review supplement
├── agents/                        # Specialized agents
│   ├── a11y-agent.md             # Section 508 & accessibility
│   ├── arsapps-agent.md          # ARS Apps platform specifics
│   ├── azure-agent.md            # Azure deployment
│   ├── cr-agent.md               # Code review orchestration
│   ├── docs-agent.md             # Documentation quality
│   ├── security-agent.md         # Security & OWASP
│   └── uswds-agent.md            # USWDS design system
└── workflows/
    └── codereview-setup-steps.yml # Automated PR review
```

---

## Specialized Agents

Each agent is an expert in a specific domain and follows strict verification rules.

### 🔒 Security Agent (`@security-agent`)
**Purpose:** Identify security vulnerabilities and OWASP Top 10 issues

**Use for:**
- Reviewing code that handles user input
- Checking database queries for SQL injection
- Validating authentication/authorization logic
- Reviewing API integrations
- Checking file upload handling

**Example:**
```bash
@security-agent "Review this form for security issues" web/modules/custom/mymodule/src/Form/UserInputForm.php
```

### ♿ Accessibility Agent (`@a11y-agent`)
**Purpose:** Ensure Section 508 and WCAG 2.1 AA compliance

**Use for:**
- Reviewing Twig templates
- Checking form accessibility
- Validating keyboard navigation
- Reviewing color contrast
- Checking ARIA usage

**Example:**
```bash
@a11y-agent "Check this template for accessibility" web/themes/custom/mytheme/templates/page.html.twig
```

### 🏛️ USWDS Agent (`@uswds-agent`)
**Purpose:** Ensure U.S. Web Design System compliance

**Use for:**
- Reviewing theme files
- Checking component usage
- Validating design tokens
- Reviewing grid layouts
- Checking typography

**Example:**
```bash
@uswds-agent "Is this SCSS using USWDS correctly?" web/themes/custom/mytheme/scss/components/_header.scss
```

### 📚 Documentation Agent (`@docs-agent`)
**Purpose:** Ensure comprehensive, consistent documentation

**Use for:**
- Reviewing documentation completeness
- Checking for conflicting docs
- Validating code documentation
- Reviewing API documentation
- Checking for broken links

**Example:**
```bash
@docs-agent "Review documentation for this module" web/modules/custom/mymodule/
```

### 🏗️ ARS Apps Agent (`@arsapps-agent`)
**Purpose:** ARS Apps platform-specific guidance

**Use for:**
- Multi-tenant Group architecture questions
- DDEV workflow questions
- Configuration management
- Platform-specific patterns
- Module integration

**Example:**
```bash
@arsapps-agent "How do I add content to a Group programmatically?"
```

### ☁️ Azure Agent (`@azure-agent`)
**Purpose:** Azure App Service deployment and infrastructure

**Use for:**
- Deployment pipeline questions
- Azure configuration
- Environment variables
- Secrets management
- Infrastructure troubleshooting

**Example:**
```bash
@azure-agent "How do I add a new environment variable for production?"
```

### 🔍 Code Review Agent (`@cr-agent`)
**Purpose:** Orchestrate comprehensive code reviews

**Use for:**
- Understanding PR review failures
- Getting review scope analysis
- Coordinating multiple review concerns
- Understanding quality gates

**Example:**
```bash
@cr-agent "Why did my PR review fail?"
```

---

## Using Agents

### In VS Code Chat

Press `Ctrl+Shift+I` (or `Cmd+Shift+I` on Mac) to open Copilot Chat, then:

```
@arsapps-agent How do I create a custom block for Group members?

@security-agent Review this controller for vulnerabilities
[paste or select code]

@a11y-agent Is this form accessible?
[paste or select template]
```

### In Terminal (GitHub CLI)

```bash
# General suggestions
gh copilot suggest "How do I create a custom block in Drupal 11?"

# Project-specific help (uses our custom instructions)
gh copilot suggest "How do I add a new Group content type to ARS Apps?"

# Explain code
gh copilot explain "Review this file for vulnerabilities" -- web/modules/custom/mymodule/src/Form/MyForm.php
```

---

## Code Review Workflow

### Automated PR Review

When you open a PR, the automated review workflow:

1. **Analyzes changed files** to determine review scope
2. **Runs static analysis** (PHPCS, security scan)
3. **Spins up DDEV** for integration tests
4. **Invokes specialized agents** based on changes
5. **Posts review comment** with findings

### Manual Pre-PR Checks

Before submitting a PR:
```bash
# Run coding standards
ddev exec vendor/bin/phpcs --standard=Drupal,DrupalPractice web/modules/custom/

# Auto-fix issues
ddev exec vendor/bin/phpcbf --standard=Drupal,DrupalPractice web/modules/custom/

# Run security scan
ddev composer audit

# Run accessibility scan (if UI changes)
ddev exec node scripts/axe-scan.js

# Export configuration
ddev drush cex -y
```

---

## Best Practices

### Development Workflow

**1. Plan your approach:**
```bash
@arsapps-agent "What's the best approach to implement [feature]?"
```

**2. Generate Code:**
```bash
gh copilot suggest "Generate a custom block plugin that displays Group members"
```

**3. Review Code:**
```bash
@security-agent "Review this code for vulnerabilities"
@a11y-agent "Check accessibility of this form"
```

**4. Document:**
```bash
@docs-agent "Generate README for this module"
```

**5. Test:**
```bash
ddev exec vendor/bin/phpunit web/modules/custom/mymodule/tests/
```

### 📝 Documentation Workflow

**Before committing:**
```bash
# Check documentation completeness
@docs-agent "Review documentation for my changes in web/modules/custom/mymodule/"

# Generate missing docs
gh copilot suggest "Generate PHPDoc for this method"
gh copilot suggest "Write a README for this module"

# Update CHANGELOG
gh copilot suggest "What should I add to CHANGELOG.md for this feature?"
```

### 🔒 Security Workflow

**For any code handling:**
- User input
- Database queries
- File uploads
- Authentication
- External APIs

**Always run:**
```bash
@security-agent "Review security of this code" path/to/file.php
```

### ♿ Accessibility Workflow

**For any UI changes:**
```bash
# Before committing
@a11y-agent "Review accessibility" web/themes/custom/mytheme/templates/

# Run automated tests
ddev exec node scripts/axe-scan.js

# View results
ddev launch /axe-results/axe-summary-report.html
```

---

## Troubleshooting

### Common Issues

**Issue: "Copilot doesn't seem to know about our project"**

**Solution:** Make sure you're in the project directory. Copilot loads instructions from `.github/` automatically.
```bash
# Verify you're in the right directory
pwd
# Should show path to ARS Apps project root

# Check instructions are present
ls .github/copilot-instructions.md
```

---

**Issue: "Agent recommendations conflict with each other"**

**Solution:** This is normal. Agents have different priorities:
- Security agent prioritizes safety (may suggest more code)
- Performance agent prioritizes speed (may suggest less code)
- Accessibility agent prioritizes compliance (may suggest more markup)

Use your judgment to balance competing concerns, or ask the CR agent:
```bash
@cr-agent "How do I resolve this conflict between security and performance recommendations?"
```

---

**Issue: "Copilot suggested running commands on host, not DDEV"**

**Solution:** Remind Copilot about the DDEV requirement:
```bash
gh copilot suggest "How do I [task] using DDEV commands only?"
```

If it persists, report it as feedback - our instructions should prevent this.

---

**Issue: "Automated review workflow failed"**

**Solution:** Check the workflow logs:
1. Go to PR in GitHub
2. Click "Checks" tab
3. Click "Automated Code Review"
4. Review each job's output
5. Common failures:
   - Coding standards violations (run PHPCS locally)
   - Test failures (run tests locally in DDEV)
   - Configuration import errors (check config/sync/)

---

**Issue: "Agent can't find documentation for [topic]"**

**Solution:** Check if the topic is in approved sources:
- Drupal.org official docs
- PHP, Symfony, Composer official docs
- USWDS official docs

If it's a new/custom topic, the agent will tell you. You can:
1. Provide the documentation
2. Ask for general guidance
3. Create documentation for future reference

---

### Getting Help

**Within the project:**
```bash
# Ask the docs agent
@docs-agent "Where can I find information about [topic]?"

# Ask the ARS Apps agent
@arsapps-agent "How do I [task] in ARS Apps?"
```

**General Copilot help:**
```bash
# In terminal
gh copilot --help

# Or ask
gh copilot suggest "How do I use GitHub Copilot CLI?"
```

**For workflow issues:**
- Check `.github/workflows/codereview-setup-steps.yml`
- Review GitHub Actions logs
- Ask: `@cr-agent "Why did my PR review fail?"`

---

## Extending the System

### Adding a New Agent

**1. Create agent file:**
```bash
# Create new agent in .github/agents/
touch .github/agents/my-agent.md
```

**2. Follow the template:**
```markdown
---
name: my_agent
description: Short description of what this agent does
tools: ["read", "search", "edit"]
---

You are a specialist in [domain].

## Your Role
[Define the role and expertise]

## Boundaries
### ✅ Always Do:
[List requirements]

### 🚫 Never Do:
[List prohibitions]
```

**3. Add to code review workflow:**

Edit `.github/workflows/codereview-setup-steps.yml`:
```yaml
# In scope-detection job
- name: Analyze changed files
  run: |
    # Add logic to detect when your agent is needed
    if [[ condition ]]; then
      REQUIRED_AGENTS="$REQUIRED_AGENTS,my-agent"
    fi

# Add new review job
- name: My Agent Review
  if: contains(needs.scope-detection.outputs.required_agents, 'my-agent')
  run: |
    # Agent-specific review logic
```

### Modifying Existing Instructions

**To update main instructions:**
1. Edit `.github/copilot-instructions.md`
2. Test changes with queries
3. Document what changed
4. Commit with clear message

**To update an agent:**
1. Edit `.github/agents/[agent-name].md`
2. Test with: `@agent-name "test query"`
3. Verify boundaries are enforced
4. Commit changes

### Adding Custom Checks to Workflow

**Add to `.github/workflows/codereview-setup-steps.yml`:**
```yaml
- name: Custom Check
  run: |
    # Your custom validation
    if ! ./scripts/my-custom-check.sh; then
      echo "::error::Custom check failed"
      exit 1
    fi
```

---

## Resources

### Documentation
- **Drupal:** https://www.drupal.org/docs
- **USWDS:** https://designsystem.digital.gov/
- **Section 508:** https://www.section508.gov/
- **DDEV:** https://ddev.readthedocs.io/

### GitHub Copilot
- **CLI Docs:** https://docs.github.com/en/copilot/github-copilot-in-the-cli
- **Chat Docs:** https://docs.github.com/en/copilot/github-copilot-chat
- **Custom Agents:** https://docs.github.com/en/copilot/customizing-copilot/creating-custom-agents

### Internal Documentation
- **Setup Guide:** `/docs/setup.md`
- **Development Workflow:** `/docs/development.md`
- **Architecture:** `/docs/architecture.md`
- **Contributing:** `/docs/contributing.md`

---

## Version History

**Current Version:** 2.0.0

### 2.0.0 - DDEV-Only Refactor
- Removed GitHub Codespaces support
- Simplified to DDEV-only local development
- Fixed automated code review workflow bugs
- Streamlined documentation

### 1.0.0 - Initial Release
- Core Copilot instructions
- 7 specialized agents
- Automated code review workflow
- DDEV-first development enforcement
- Section 508 / WCAG 2.1 compliance
- USWDS integration
- Multi-tenant Group architecture support

---

## Feedback and Contributions

**Found an issue?**
```bash
# Use the docs agent to report gaps
@docs-agent "I found incorrect documentation at [location]. It says [X] but should say [Y]."
```

**Have a suggestion?**
- Create a GitHub issue
- Discuss in team meetings
- Submit a PR with proposed changes

**Questions?**
```bash
# Ask the appropriate agent
@arsapps-agent "I have a question about [topic]"
```

---

**Remember:** These instructions and agents are here to help you write better, safer, more accessible code faster. When in doubt, ask an agent! 🤖
