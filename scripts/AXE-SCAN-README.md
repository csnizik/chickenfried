# AXE Accessibility Scan Script (`axe-scan.js`)

**TODO** Fix the path that the results are written to. Currently going to ./scripts/web/axe-results but should be going to ./web/axe-results

## What is `axe-scan.js`?

`axe-scan.js` is an automated script that checks web pages for accessibility issues using the [axe-core](https://www.deque.com/axe/) accessibility engine. It helps ensure that your website is usable by people with disabilities and meets federal Section 508 accessibility standards.

## What does it do?

- Visits one or more web pages you specify.
- Runs a series of accessibility tests on each page.
- Collects and saves a detailed report of any accessibility issues found (such as missing alt text, low color contrast, missing labels, etc.).
- Saves the results in easy-to-read files for later review.

## Who should use this script?

- **Site owners** who want to check their website for accessibility problems.
- **Content editors** who want to make sure their pages are accessible.
- **Developers** who want to catch accessibility issues early.
- **Anyone** interested in improving web accessibility.

## How do I run the script?

1. **Open a shell into your running container** (if DDEV: `ddev ssh`).
2. **Navigate to the project folder** (where this script lives):
   ```bash
   cd /path/to/ars-apps-drupal/scripts
   ```
3. **Install dependencies** (only needed the first time):
   ```bash
   npm install && npx playwright install
   ```
   (Note: playwright is resource-heavy and takes a few minutes to install)
4. **Run the script**:
   ```bash
   node axe-scan.js
   ```
   - By default, it will scan a set of pre-configured URLs.
   - To scan a specific URL, you may be able to run:
     ```bash
     node axe-scan.js https://example.com/page
     ```
     (Check the script for custom usage.)


## How do I view the results?

1. In your web browser, go to:

  `http://arsapps.ddev.site/axe-results/`

  (If your project uses a different DDEV name, replace `arsapps` with your project’s name.)

2. You will see a list of accessibility report files. Click any `.html` file (for example, `axe-report-https___apps_stage_ars_usda_gov_.html`) to view the accessibility results for that page.

That’s it! You can now review the accessibility findings in your browser, no technical steps required.

## How do I read the results?

- **Open the HTML report** for a page in your browser. It will show:
  - A list of accessibility issues found.
  - The type of issue (e.g., missing label, color contrast).
  - Where on the page the issue occurs.
  - Suggestions for how to fix each issue.
- **Share the report** with your web team or content editors to help fix problems.

## Why is this important?

- Accessibility is required by law for federal websites.
- Accessible sites are easier for everyone to use, including people with disabilities.
- Regular scans help catch problems early and improve your site's quality.

## Need help?

- If you have questions about the scan or how to fix issues, contact your web team or accessibility coordinator.
- For technical help, ask a developer familiar with this project.

---

**Summary:**
- `axe-scan.js` helps you find and fix accessibility issues on your website.
- Run it from the command line, then review the reports in the `axe-results/` folder.
- Use the results to make your site more accessible for everyone!
