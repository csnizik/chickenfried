# Version History

## v1.0.4 — 2026-02-05

Maintenance release. CI/CD fixes and tenant UX improvements.

- **CI/CD:** Disabled `generate-changelog` dependency in deploy workflow; was blocking `create-release` job on API failures. Release Drafter auto-labeling disabled pending token permission fixes.
- **Permissions:** Fixed group role permissions for Tenant Access Group members. Users now correctly inherit view/edit access to content within their assigned tenant without requiring site-wide roles.
- **Admin theme:** Added production favicon and logo assets to Gin theme configuration.

No schema changes. No database updates.

---

## v1.0.3 — 2026-02-05

Drupal core upgrade release.

- **Core:** Drupal 11.2.10 → 11.3.2
- **Contrib updates (19 modules):**
  - Gin theme 4.x → 5.x (major version bump, required for 11.3 compatibility)
  - Paragraphs 1.19 → 1.20
  - Group, Token, Pathauto, Metatag, and others — patch/minor updates
- **Compatibility:** Verified all custom modules and themes against 11.3 API changes
- **Testing:** Full regression on staging before production push

Includes all upstream security patches through 2026-02-04.

---

## v1.0.2 — 2026-01-29

Admin experience overhaul. Switched default admin theme and added tenant dashboard tooling.

- **Admin theme:** Replaced Claro with Gin as default admin theme
  - Modernized UI with improved density and navigation
  - Dark mode support (user-selectable)
  - Better mobile/responsive behavior in admin context
- **Gin Toolbar:** Enabled Gin Toolbar module with custom menu integration
  - Each tenant's dashboard now accessible via toolbar shortcut
  - Configured per-tenant menu items within Tenant Access Groups
- **Dashboard module:** Initial setup of Dashboards module with Layout Builder integration for tenant-specific landing pages
- **Config:** Exported all theme and toolbar settings to config sync

First production release on new container-based Azure deployment architecture.

---

## v1.0.1 — 2026-01-29

Baseline production release. Stabilization of multi-tenant architecture.

- Established Tenant Access Group structure via Group module
- Initial content entity types: Nematode specimens, NRRL records
- OpenID Connect integration with Microsoft Entra ID (PIV authentication)
- Azure App Service container deployment with `drush deploy` entrypoint
- Config sync workflow validated across dev → staging → prod

---

## v1.0.0 — 2026-01-15

Initial release. Platform foundation.

- Drupal 11.2.x installation on Azure Government Cloud
- Multi-tenant architecture design with Group module
- CI/CD pipeline via GitHub Actions → Azure Container Registry → App Service
- UI Suite USWDS theme integration for Section 508 compliance
- MySQL Flexible Server, Azure Files storage, Application Gateway routing
