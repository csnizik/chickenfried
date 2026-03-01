import { chromium } from 'playwright'
import fs from 'fs'
import path from 'path'
import { createRequire } from 'module'
const require = createRequire(import.meta.url)
const axe = require('axe-playwright')
const { createHtmlReport } = require('axe-html-reporter')

// Crawl configuration
const maxDepth = parseInt(process.env.CRAWL_DEPTH || '2', 10)
const includeDownloads = process.env.CRAWL_INCLUDE_DOWNLOADS === 'true'

// Axe scan level (controls which rules/tags run)
const scanLevel = process.env.AXE_SCAN_LEVEL || 'strict'
// supported: 'basic', 'standard', 'strict'

// Starting URL
const startUrl = process.env.CRAWL_START_URL || 'https://ars-apps-drupal.ddev.site/'

// Output directory (inside web root so DDEV can serve it)
const outputDir = path.resolve('web', 'axe-results')

// Helper: crawl URLs recursively up to maxDepth
async function crawlUrls(page, baseUrl, maxDepth, includeDownloads) {
  const visited = new Set()
  const queue = [{ url: baseUrl, depth: 0 }]
  const urls = []

  while (queue.length > 0) {
    const { url, depth } = queue.shift()
    if (visited.has(url) || depth > maxDepth) continue
    visited.add(url)
    urls.push(url)

    try {
      await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 30000 })
      const links = await page.$$eval('a[href]', (anchors) =>
        anchors
          .map((a) => a.href)
          .filter(
            (href) =>
              href &&
              !href.startsWith('mailto:') &&
              !href.startsWith('javascript:')
          )
      )

      for (const link of links) {
        try {
          const u = new URL(link, baseUrl)
          if (!u.href.startsWith(baseUrl)) continue // stay within same site
          const ext = path.extname(u.pathname).toLowerCase()
          const isDownload = [
            '.pdf',
            '.zip',
            '.xls',
            '.xlsx',
            '.csv',
            '.doc',
            '.docx',
            '.ppt',
            '.pptx',
          ].includes(ext)
          if (isDownload && !includeDownloads) continue
          if (!visited.has(u.href))
            queue.push({ url: u.href, depth: depth + 1 })
        } catch {
          continue
        }
      }
    } catch (e) {
      console.warn(`Failed to crawl ${url}: ${e.message}`)
    }
  }

  return urls
}

// Helper: choose rule filters based on scanLevel
function getAxeOptions(level) {
  switch (level) {
    case 'basic':
      // fast baseline checks
      return { runOnly: { type: 'tag', values: ['wcag2a'] } }
    case 'strict':
      // everything including best practices
      return {
        runOnly: {
          type: 'tag',
          values: ['wcag2a', 'wcag2aa', 'wcag21aa', 'best-practice'],
        },
      }
    default:
      // normal balanced scan
      return { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa'] } }
  }
}

;(async () => {
  if (!fs.existsSync(outputDir)) fs.mkdirSync(outputDir, { recursive: true })

  const browser = await chromium.launch({
    headless: true,
    args: ['--ignore-certificate-errors'],
  })
  const context = await browser.newContext({ ignoreHTTPSErrors: true })
  const page = await context.newPage()

  console.log(
    `Starting crawl at ${startUrl} (maxDepth=${maxDepth}, includeDownloads=${includeDownloads}, scanLevel=${scanLevel})`
  )
  const urls = await crawlUrls(page, startUrl, maxDepth, includeDownloads)
  console.log(`Found ${urls.length} URLs to scan`)

  const axeOptions = getAxeOptions(scanLevel)
  const allResults = []

  for (const url of urls) {
    console.log(`Scanning ${url}...`)
    await page.goto(url, { waitUntil: 'networkidle', timeout: 60000 })

    // Inject axe-core and run analysis
    await axe.injectAxe(page)
    const results = await axe.getAxeResults(page, axeOptions)
    const safeName = url.replace(/[^a-z0-9]/gi, '_')

    // JSON
    fs.writeFileSync(
      path.join(outputDir, `axe-results-${safeName}.json`),
      JSON.stringify(results, null, 2)
    )

    // HTML report per page
    const reportHtml = createHtmlReport({
      results,
      options: {
        projectKey: 'ars-stage',
        reportFileName: `axe-report-${safeName}.html`,
        outputDir,
      },
    })
    fs.writeFileSync(
      path.join(outputDir, `axe-report-${safeName}.html`),
      reportHtml
    )

    allResults.push({
      url,
      violationCount: results.violations.length,
      rules: results.violations.map((v) => ({
        id: v.id,
        impact: v.impact,
        description: v.description,
        nodes: v.nodes.length,
      })),
    })
    console.log(` ${results.violations.length} violations on ${url}`)
  }

  // Summary JSON
  const summaryPath = path.join(outputDir, 'axe-results-summary.json')
  fs.writeFileSync(summaryPath, JSON.stringify(allResults, null, 2))

  // Summary HTML dashboard
  const summaryHtml = `
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Accessibility Scan Summary</title>
  <style>
    body { font-family: system-ui, sans-serif; margin: 2rem; background: #fafafa; color: #222; }
    table { border-collapse: collapse; width: 100%; margin-top: 1rem; }
    th, td { padding: 0.5rem 0.75rem; border-bottom: 1px solid #ddd; text-align: left; }
    th { background: #f0f0f0; }
    tr:hover { background: #f9f9f9; }
    .low { color: #4a7; }
    .moderate { color: #c80; }
    .serious { color: #e55; }
    .critical { color: #b00; font-weight: bold; }
  </style>
</head>
<body>
  <h1>ARS-Apps Local/DDEV Accessibility Scan Summary</h1>
  <p>Generated: ${new Date().toLocaleString()}</p>
  <table>
    <thead>
      <tr>
        <th>Page URL</th>
        <th>Violations</th>
        <th>Details</th>
      </tr>
    </thead>
    <tbody>
      ${allResults
        .map(
          (res) => `
          <tr>
            <td><a href="${res.url}" target="_blank">${res.url}</a></td>
            <td>${res.violationCount}</td>
            <td><a href="axe-report-${res.url.replace(
              /[^a-z0-9]/gi,
              '_'
            )}.html">View report</a></td>
          </tr>`
        )
        .join('\n')}
    </tbody>
  </table>
</body>
</html>
`
  fs.writeFileSync(path.join(outputDir, 'axe-summary-report.html'), summaryHtml)

  await browser.close()

  const site = process.env.DDEV_PRIMARY_URL || 'https://ars-apps.ddev.site'
  console.log(`
Scan complete!
View the combined report in your browser:
${site}/axe-results/axe-summary-report.html
`)
})()
