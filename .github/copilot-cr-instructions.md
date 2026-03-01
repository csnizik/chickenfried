# Code Review Process Instructions for GitHub Copilot

These instructions supplement `.github/copilot-instructions.md` and apply specifically to code review workflows via CLI.

## Overview

This document defines how GitHub Copilot should assist with code reviews via the command line interface, coordinating multiple specialized agents, running intelligent tests, and providing comprehensive feedback.

## When These Instructions Apply

These instructions activate when:
- Running code review commands via CLI: `copilot review pr <number>`
- Executing review workflows: `./scripts/code-review.sh`
- Working with PR review context
- Coordinating specialized agent reviews
- Running automated PR tests

## Core Principles for Code Review

1. **Intelligence Over Exhaustiveness:** Determine minimal scope needed for confident review
2. **Agent Orchestration:** Coordinate specialized agents based on change context
3. **Actionable Feedback:** Provide specific, fixable guidance
4. **DDEV Compliance:** All testing must use DDEV (never host machine)
5. **Efficiency:** Set up only what's needed, clean up after
6. **Comprehensiveness:** Cover all relevant dimensions (security, a11y, docs, etc.)
7. **Config Exclusion:** Files in `config/sync/` are excluded from code review (Drupal-managed configuration)

## Review Workflow

### Phase 1: Scope Detection

**Analyze PR to determine review requirements:**
````bash
# Get changed files (excluding config/sync)
git fetch origin
git diff --name-only origin/main...HEAD | grep -v "^config/sync/" > changed_files.txt

# Categorize changes
MODULES=$(grep "web/modules/custom/" changed_files.txt | cut -d'/' -f4 | sort -u)
THEMES=$(grep "web/themes/custom/" changed_files.txt | cut -d'/' -f4 | sort -u)
TESTS=$(grep "tests/" changed_files.txt | wc -l)
WORKFLOWS=$(grep ".github/workflows/" changed_files.txt | wc -l)

# Note: config/sync/ is excluded from code review

# Determine required agents
REQUIRED_AGENTS=""
[[ -n "$MODULES" ]] && REQUIRED_AGENTS="$REQUIRED_AGENTS arsapps"
grep -qE "\.(twig|theme|scss)$" changed_files.txt && REQUIRED_AGENTS="$REQUIRED_AGENTS uswds a11y"
grep -qE "(Form|Controller|.*\.php)" changed_files.txt && REQUIRED_AGENTS="$REQUIRED_AGENTS security"
[[ $WORKFLOWS -gt 0 ]] && REQUIRED_AGENTS="$REQUIRED_AGENTS azure"
# docs-agent ALWAYS runs
REQUIRED_AGENTS="$REQUIRED_AGENTS docs"
````

### Phase 2: Environment Setup

**Set up minimal DDEV environment:**
````bash
# Start DDEV (if not running)
ddev start

# Import only changed modules
if [[ -n "$MODULES" ]]; then
  for module in $MODULES; do
    if [[ -f "web/modules/custom/$module/$module.info.yml" ]]; then
      ddev drush en $module -y
    fi
  done
fi

# Note: config/sync changes are excluded from review

# Clear caches
ddev drush cr

# Warm up environment
ddev drush status
````

### Phase 3: Automated Testing

**Run context-appropriate tests:**
````bash
#!/bin/bash
# scripts/run-pr-tests.sh

set -e

CHANGED_FILES="$1"
EXIT_CODE=0

echo "=== Running Automated Tests ==="

# 1. Coding Standards (always run on PHP files)
if grep -q "\.php$" <<< "$CHANGED_FILES"; then
  echo "→ Running coding standards check..."
  if ! ddev exec vendor/bin/phpcs --standard=Drupal,DrupalPractice web/modules/custom/; then
    echo "❌ Coding standards violations found"
    EXIT_CODE=1
  else
    echo "✅ Coding standards passed"
  fi
fi

# 2. Security Scan
echo "→ Running security scan..."
if ! ddev composer audit; then
  echo "⚠️ Security vulnerabilities found in dependencies"
  EXIT_CODE=1
else
  echo "✅ No dependency vulnerabilities"
fi

if ! ddev drush pm:security 2>/dev/null | grep -q "There are no outstanding"; then
  echo "⚠️ Drupal module security updates available"
  EXIT_CODE=1
else
  echo "✅ All modules up to date"
fi

# 3. PHPUnit Tests (if test files changed or module PHP changed)
if grep -qE "(tests/.*\.php|web/modules/custom/.*\.php)" <<< "$CHANGED_FILES"; then
  echo "→ Running PHPUnit tests..."
  for module in $(echo "$CHANGED_FILES" | grep "web/modules/custom/" | cut -d'/' -f4 | sort -u); do
    if [[ -d "web/modules/custom/$module/tests" ]]; then
      if ! ddev exec vendor/bin/phpunit web/modules/custom/$module/tests; then
        echo "❌ PHPUnit tests failed for $module"
        EXIT_CODE=1
      else
        echo "✅ PHPUnit tests passed for $module"
      fi
    fi
  done
fi

# 4. Run accessibility tests (if UI changed)
if [[ $UI_CHANGED -gt 0 ]]; then
  echo "→ Running accessibility tests..."

  # Use project's axe-scan.js (basic scan for CI speed)
  if [[ -f "scripts/axe-scan.js" ]]; then
    echo "Running axe-scan with basic WCAG 2.0 A checks..."
    if AXE_SCAN_LEVEL=basic CRAWL_DEPTH=1 ddev exec node scripts/axe-scan.js 2>&1 | tee axe-scan.log; then
      # Count violations from summary
      VIOLATIONS=$(grep -c '"violationCount":' web/axe-results/axe-results-summary.json 2>/dev/null || echo "0")
      if [[ $VIOLATIONS -gt 0 ]]; then
        echo "⚠️ Found $VIOLATIONS pages with accessibility issues"
        echo "View detailed report: web/axe-results/axe-summary-report.html"
      else
        echo "✅ No accessibility violations detected"
      fi
    else
      echo "⚠️ Axe scan encountered errors (check axe-scan.log)"
    fi
  fi

  # Drupal security review for additional checks
  if ddev drush pm-list --status=enabled | grep -q security_review; then
    ddev drush secrev:run || echo "⚠️ Security review found issues"
  fi
fi


  # Drupal security review for additional checks
  if ddev drush pm-list --status=enabled | grep -q security_review; then
    ddev drush secrev:run || echo "⚠️ Security review found issues"
  fi
fi


# 5. Configuration Files
# Note: config/sync/ files are excluded from code review (Drupal-managed configuration)
echo "✅ Configuration files (config/sync/) excluded from review"

# 6. Documentation Checks
if grep -qE "\.(md|README|CHANGELOG)" <<< "$CHANGED_FILES"; then
  echo "→ Running documentation checks..."

  # Markdown lint
  if ! ddev exec npx markdownlint docs/**/*.md README.md CHANGELOG.md; then
    echo "⚠️ Markdown linting issues found"
    # Don't fail build for markdown style
  fi

  # Link check
  if ! ddev exec npx markdown-link-check docs/**/*.md README.md; then
    echo "❌ Broken links found in documentation"
    EXIT_CODE=1
  else
    echo "✅ Documentation links valid"
  fi
fi

echo ""
echo "=== Test Summary ==="
if [[ $EXIT_CODE -eq 0 ]]; then
  echo "✅ All automated tests passed"
else
  echo "❌ Some tests failed (see above)"
fi

exit $EXIT_CODE
````

### Phase 4: Agent Review Coordination

**Invoke specialized agents based on scope:**
````bash
#!/bin/bash
# scripts/run-agent-reviews.sh

REQUIRED_AGENTS="$1"
CHANGED_FILES="$2"
REVIEW_OUTPUT="review_results.md"

echo "# Code Review Results" > $REVIEW_OUTPUT
echo "" >> $REVIEW_OUTPUT
echo "**Automated Review** • $(date -u +"%Y-%m-%d %H:%M UTC")" >> $REVIEW_OUTPUT
echo "" >> $REVIEW_OUTPUT

# Security Agent Review
if [[ $REQUIRED_AGENTS == *"security"* ]]; then
  echo "## 🔒 Security Review" >> $REVIEW_OUTPUT
  echo "" >> $REVIEW_OUTPUT

  # Run security-specific checks
  echo "Running @security-agent review..."

  # Check for common vulnerabilities
  SECURITY_ISSUES=""

  # SQL injection check
  if grep -r "->query(\"" web/modules/custom/ | grep -v "->condition"; then
    SECURITY_ISSUES="${SECURITY_ISSUES}\n❌ Potential SQL injection: String concatenation in queries detected"
  fi

  # XSS check
  if grep -r "print \$" web/modules/custom/; then
    SECURITY_ISSUES="${SECURITY_ISSUES}\n❌ Potential XSS: Raw print statements found"
  fi

  # eval() check
  if grep -r "eval(" web/modules/custom/; then
    SECURITY_ISSUES="${SECURITY_ISSUES}\n❌ Critical: eval() usage detected"
  fi

  if [[ -n "$SECURITY_ISSUES" ]]; then
    echo "**Status:** ❌ ISSUES FOUND" >> $REVIEW_OUTPUT
    echo "" >> $REVIEW_OUTPUT
    echo "**Findings:**" >> $REVIEW_OUTPUT
    echo -e "$SECURITY_ISSUES" >> $REVIEW_OUTPUT
  else
    echo "**Status:** ✅ PASSED" >> $REVIEW_OUTPUT
    echo "" >> $REVIEW_OUTPUT
    echo "✅ No obvious security issues detected" >> $REVIEW_OUTPUT
  fi

  echo "" >> $REVIEW_OUTPUT
fi

# Accessibility Agent Review
if [[ $REQUIRED_AGENTS == *"a11y"* ]]; then
  echo "## ♿ Accessibility Review" >> $REVIEW_OUTPUT
  echo "" >> $REVIEW_OUTPUT

  echo "Running @a11y-agent review..."

  A11Y_ISSUES=""

  # Check for images without alt
  if grep -r "<img" web/themes/custom/ web/modules/custom/ | grep -v "alt="; then
    A11Y_ISSUES="${A11Y_ISSUES}\n⚠️ Images without alt attributes found"
  fi

  # Check for buttons without labels
  if grep -rE "<button[^>]*>" web/themes/custom/ web/modules/custom/ | grep -v "aria-label" | grep -v ">.*<"; then
    A11Y_ISSUES="${A11Y_ISSUES}\n⚠️ Buttons without accessible labels found"
  fi

  if [[ -n "$A11Y_ISSUES" ]]; then
    echo "**Status:** ⚠️ WARNINGS" >> $REVIEW_OUTPUT
    echo "" >> $REVIEW_OUTPUT
    echo "**Findings:**" >> $REVIEW_OUTPUT
    echo -e "$A11Y_ISSUES" >> $REVIEW_OUTPUT
  else
    echo "**Status:** ✅ PASSED" >> $REVIEW_OUTPUT
    echo "" >> $REVIEW_OUTPUT
    echo "✅ Basic accessibility checks passed" >> $REVIEW_OUTPUT
    echo "" >> $REVIEW_OUTPUT
    echo "*Note: Run full accessibility test suite for complete validation*" >> $REVIEW_OUTPUT
  fi

  echo "" >> $REVIEW_OUTPUT
fi

# Documentation Agent Review (always runs)
echo "## 📚 Documentation Review" >> $REVIEW_OUTPUT
echo "" >> $REVIEW_OUTPUT

echo "Running @docs-agent review..."

DOCS_ISSUES=""

# Check for PHPDoc on new public methods
NEW_PHP_FILES=$(echo "$CHANGED_FILES" | grep "\.php$")
if [[ -n "$NEW_PHP_FILES" ]]; then
  for file in $NEW_PHP_FILES; do
    # Simple check: public functions should have /** above them
    if grep -E "^\s*public function" "$file" | head -1 > /dev/null; then
      PREV_LINE=$(grep -B1 -E "^\s*public function" "$file" | head -1)
      if [[ ! "$PREV_LINE" =~ "/\*\*" ]]; then
        DOCS_ISSUES="${DOCS_ISSUES}\n⚠️ $file: Public methods may be missing PHPDoc"
      fi
    fi
  done
fi

# Check if CHANGELOG updated
if [[ -n "$NEW_PHP_FILES" ]] || [[ -n "$(echo "$CHANGED_FILES" | grep config/sync/)" ]]; then
  if ! git diff --name-only origin/main...HEAD | grep -q "CHANGELOG.md"; then
    DOCS_ISSUES="${DOCS_ISSUES}\n❌ CHANGELOG.md not updated for functional changes"
  fi
fi

# Check for README in new modules
NEW_MODULES=$(echo "$CHANGED_FILES" | grep "web/modules/custom/.*/.*\.info\.yml" | cut -d'/' -f4 | sort -u)
for module in $NEW_MODULES; do
  if [[ ! -f "web/modules/custom/$module/README.md" ]]; then
    DOCS_ISSUES="${DOCS_ISSUES}\n❌ New module $module missing README.md"
  fi
done

if [[ -n "$DOCS_ISSUES" ]]; then
  echo "**Status:** ⚠️ ISSUES FOUND" >> $REVIEW_OUTPUT
  echo "" >> $REVIEW_OUTPUT
  echo "**Findings:**" >> $REVIEW_OUTPUT
  echo -e "$DOCS_ISSUES" >> $REVIEW_OUTPUT
else
  echo "**Status:** ✅ PASSED" >> $REVIEW_OUTPUT
  echo "" >> $REVIEW_OUTPUT
  echo "✅ Documentation appears complete" >> $REVIEW_OUTPUT
fi

echo "" >> $REVIEW_OUTPUT

# Output summary
echo "=== Agent Reviews Complete ==="
cat $REVIEW_OUTPUT
````

### Phase 5: Results Aggregation

**Synthesize findings:**
````bash
#!/bin/bash
# scripts/aggregate-review-results.sh

cat << 'EOF' > final_review.md
# 🤖 Automated Code Review

**PR:** #${PR_NUMBER}
**Branch:** ${BRANCH_NAME}
**Reviewed:** $(date -u +"%Y-%m-%d %H:%M UTC")

---

## 📊 Review Summary

$(cat review_summary.txt)

---

## 🔍 Detailed Findings

$(cat review_results.md)

---

## ✅ Action Items

$(cat action_items.md)

---

## 📝 Next Steps

1. Review findings above
2. Address any blocking issues (❌)
3. Consider warnings (⚠️) and suggestions (💡)
4. Update tests if needed
5. Request re-review after changes

---

*This automated review was coordinated by @cr-agent with specialized reviews from:*
$(echo $REQUIRED_AGENTS | tr ' ' '\n' | sed 's/^/- @/g' | sed 's/$/-agent/g')
EOF

# Display results
cat final_review.md
````

### Phase 6: Cleanup

**Clean up test environment:**
````bash
#!/bin/bash
# scripts/cleanup-review-env.sh

echo "=== Cleaning Up Review Environment ==="

# Disable test modules
if [[ -n "$TEST_MODULES" ]]; then
  for module in $TEST_MODULES; do
    ddev drush pmu $module -y 2>/dev/null || true
  done
fi

# Clear caches
ddev drush cr

# Remove temporary files
rm -f changed_files.txt review_results.md review_summary.txt action_items.md

# Optional: Stop DDEV if started just for review
# ddev stop

echo "✅ Cleanup complete"
````

## CLI Commands for Code Review

**Primary review command:**
````bash
# Review a PR
copilot review pr 123

# Review current branch against main
copilot review branch

# Review specific files
copilot review files web/modules/custom/mymodule/**
````

**Agent-specific reviews:**
````bash
# Security review only
copilot @security-agent review pr 123

# Accessibility review only
copilot @a11y-agent review pr 123

# Documentation review only
copilot @docs-agent review pr 123
````

**Testing commands:**
````bash
# Run automated tests for PR
./scripts/run-pr-tests.sh "$(git diff --name-only origin/main...HEAD)"

# Run specific test suite
ddev exec vendor/bin/phpunit web/modules/custom/mymodule/tests

# Run accessibility tests
ddev exec npm run test:a11y
````

## Review Response Format

**Standard review comment format:**
````markdown
## [Category] Finding Title

**Severity:** 🔴 Blocking / ⚠️ Warning / 💡 Suggestion
**File:** `path/to/file.php:123`
**Agent:** @agent-name

**Issue:**
[Clear description]

**Current Code:**
```php
[Code snippet showing issue]
```

**Recommended Fix:**
```php
[Code snippet showing fix]
```

**Rationale:**
[Why this matters and what could go wrong]

**References:**
- [Link to relevant documentation]
````

## Integration Points

### With GitHub Actions

This process integrates with `.github/workflows/codereview-setup-steps.yml`:
````yaml
# Workflow calls these scripts in sequence:
1. scripts/determine-review-scope.sh
2. scripts/setup-review-env.sh
3. scripts/run-pr-tests.sh
4. scripts/run-agent-reviews.sh
5. scripts/aggregate-review-results.sh
6. scripts/post-review-comment.sh
7. scripts/cleanup-review-env.sh
````

### With Specialized Agents

Load agent context when invoking:
````bash
# Example: Invoke security agent with full context
copilot --agent-file=.github/agents/security-agent.md \
  review pr 123 \
  --context="$(git diff origin/main...HEAD)"
````

## Quality Gates

**Reviews must verify:**
- [ ] All automated tests pass
- [ ] No security vulnerabilities
- [ ] No accessibility violations (Section 508)
- [ ] Documentation complete
- [ ] Coding standards met
- [ ] No secrets/credentials in code
- [ ] DDEV compliance throughout
- [ ] Configuration exportable

**Blocking conditions:**
- Failing tests
- Security vulnerabilities
- Accessibility violations (WCAG A failures)
- Missing critical documentation
- Secrets in code

## Continuous Improvement

**Track metrics:**
- Review time
- Issue detection rate
- False positive rate
- Agent effectiveness
- Test coverage

**Refine process:**
- Update scope detection based on misses
- Add checks for recurring issues
- Improve agent coordination
- Enhance feedback templates

---

Remember: These instructions work in tandem with `.github/copilot-instructions.md`. The main instructions define general behavior; these define code review workflows. When conflicts arise, code review context takes precedence during review operations.
