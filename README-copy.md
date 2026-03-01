# Dev notes

## Dashboard

Create an Animal Dashboard (View or Layout Builder display) that mimics the folder metaphor:

- Tab: Summary
- Tab: Lifecycle records (Lambing, Weaning, Pedigree)
- Tab: Measurements
- Tab: Group Assignments
- Tab: Notes / Observations

## Field formatting

```md
| Format                                   | Entity | Field   |
|------------------------------------------|--------|---------|
| Duration, in years, no decimals          | lamb   | adam    |
| Julian day                               | lamb   | several |
| MMDDYY                                   | lamb   | bday    |
| MM/DD/YYYY                               | lamb   | datebn  |
| Last 2 digits of year                    | lamb   | YR      |
| Age, half and full if > 1, days when < 1 | inv    | age     |
|-------------------------------------------------------------|
```

## Migrations as config entities

- To maximize utility of the contrib module `migrate_plus` we are moving migration files from the module's `migration/` directory to its `config/install` directory.
- When adding a new migration config to `config/install`, it is best to generate and add your own UUID to the file; otherwise Drupal will add one for you and you may end up with duplicates in configs (and a mess). To generate your own, use: `ddev drush devel:uuid` and copy/paste the result into the first line of the .yml file with the key `uuid`.
- To sync changes (incl adding new), you don't have to uninstall and reinstall the module. You can run: `drush config:import --partial --source="path/to/config/install"`
