<?php

namespace Drupal\sheepdog\Drush\Commands;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Drush\Commands\AutowireTrait;

/**
 * Sheepdog Drush commands for migration log analysis and monitoring.
 *
 * Like a faithful sheepdog, these commands help you keep track of your
 * migration data and ensure nothing goes astray.
 */
final class SheepdogCommands extends DrushCommands {

  use AutowireTrait;

  /**
   * Constructs a SheepdogCommands object.
   */
  public function __construct(
    private readonly Connection $database,
    private readonly LoggerChannelFactoryInterface $loggerFactory,
    private readonly ConfigFactoryInterface $configFactory,
  ) {
    parent::__construct();
  }

  /**
   * Display help for Sheepdog commands - your migration monitoring companion.
   *
   * Usage: drush sheepdog --help.
   */
  #[CLI\Command(name: 'sheepdog')]
  #[CLI\Usage(name: 'sheepdog --help', description: 'Show Sheepdog commands help and shortcuts')]
  public function helpRoot(array $options = []): void {
    $commands = [
      ['name' => 'sheepdog logs:export (sle)', 'desc' => 'Export migration logs to JSON/NDJSON/CSV'],
      ['name' => 'sheepdog logs:analyze (sla)', 'desc' => 'Show statistical analysis of migration logs'],
      ['name' => 'sheepdog logs:tail (slt)', 'desc' => 'Tail migration logs in real time'],
    ];

    $this->output()->writeln('🐕 Sheepdog Migration Monitor — commands and options:');
    $this->output()->writeln('');

    foreach ($commands as $cmd) {
      $this->output()->writeln(sprintf('  %-35s %s', $cmd['name'], $cmd['desc']));
    }

    $this->output()->writeln('');
    $this->output()->writeln('Sheepdog keeps your migrations on track with detailed logging and monitoring.');
    $this->output()->writeln('For detailed options for a subcommand run: drush <subcommand> --help');
  }

  /**
   * Display help for Sheepdog logs commands and flags.
   *
   * Usage: drush sheepdog logs --help.
   */
  #[CLI\Command(name: 'sheepdog logs')]
  #[CLI\Option(name: 'output', description: 'Output file path. When omitted a filename migration-logs-TIMESTAMP.json is generated')]
  #[CLI\Option(name: 'hours', description: 'Include logs from the last N hours (integer). Default: 24')]
  #[CLI\Option(name: 'migration', description: 'Filter logs by migration id or pattern')]
  #[CLI\Option(name: 'create', description: 'Filter by create operations only')]
  #[CLI\Option(name: 'update', description: 'Filter by update operations only')]
  #[CLI\Option(name: 'no-change', description: 'Filter by no-change operations only')]
  #[CLI\Option(name: 'error', description: 'Filter by error operations only')]
  #[CLI\Option(name: 'entity-id', description: 'Filter logs for a specific entity id (integer)')]
  #[CLI\Option(name: 'primary-id', description: 'Filter logs by primary ID value (replaces id10)')]
  #[CLI\Option(name: 'json', description: 'Output in JSON format')]
  #[CLI\Option(name: 'ndjson', description: 'Output in NDJSON format (one JSON object per line)')]
  #[CLI\Option(name: 'csv', description: 'Output in CSV format')]
  #[CLI\Option(name: 'pretty', description: 'Pretty print JSON output')]
  #[CLI\Usage(name: 'sheepdog logs --help', description: 'Show Sheepdog logs commands and options')]
  public function helpLogs(array $options = []): void {
    $this->output()->writeln('🐕 Sheepdog:logs - subcommands and options:');
    $this->output()->writeln('');

    // Export.
    $this->output()->writeln('sheepdog logs:export (sle)  Export migration logs to JSON/NDJSON/CSV');
    $this->output()->writeln('  --output[=OUTPUT]        Output file path. [default: migration-logs-TIMESTAMP.json]');
    $this->output()->writeln('  --hours[=HOURS]          Include logs from the last N hours. [default: 24]');
    $this->output()->writeln('  --migration[=MIGRATION]  Filter logs by migration id or pattern');
    $this->output()->writeln('  --create                 Filter by create operations only');
    $this->output()->writeln('  --update                 Filter by update operations only');
    $this->output()->writeln('  --no-change              Filter by no-change operations only');
    $this->output()->writeln('  --error                  Filter by error operations only');
    $this->output()->writeln('  --entity-id[=ENTITY-ID]  Filter logs for a specific entity id');
    $this->output()->writeln('  --primary-id[=PRIMARY-ID] Filter logs by primary ID value');
    $this->output()->writeln('  --json                   Output in JSON format');
    $this->output()->writeln('  --ndjson                 Output in NDJSON format');
    $this->output()->writeln('  --csv                    Output in CSV format');
    $this->output()->writeln('  --pretty                 Pretty print JSON output');
    $this->output()->writeln('');

    // Analyze.
    $this->output()->writeln('sheepdog logs:analyze (sla)  Analyze migration logs and show summary stats');
    $this->output()->writeln('  --hours[=HOURS]           Analyze logs from the last N hours. [default: 24]');
    $this->output()->writeln('  --migration[=MIGRATION]   Filter by migration ID pattern');
    $this->output()->writeln('');

    // Tail.
    $this->output()->writeln('sheepdog logs:tail (slt)  Tail migration logs in real time');
    $this->output()->writeln('  --json                    Output in JSON format');
    $this->output()->writeln('');

    $this->output()->writeln('Examples:');
    $this->output()->writeln('  drush sheepdog logs:export --hours=2 --update --pretty');
    $this->output()->writeln('  drush sheepdog logs:analyze --hours=1');
    $this->output()->writeln('  drush sheepdog logs:tail --json');
    $this->output()->writeln('');
    $this->output()->writeln('<info>Global Options</info>');
    $this->output()->writeln('  -v|vv|vvv, --verbose     Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug');
    $this->output()->writeln('  -y, --yes                Auto-accept the default for all user prompts. Equivalent to --no-interaction.');
    $this->output()->writeln('  -l, --uri=URI            A base URL for building links and selecting a multi-site. Defaults to https://default.');
    $this->output()->writeln('                           To see all global options, run `drush topic` and pick the first choice.');
    $this->output()->writeln('');
    $this->output()->writeln('<info>Aliases</info>');
    $this->output()->writeln('  sle, sla, slt');
    $this->output()->writeln('');
    $this->output()->writeln('🐕 <info>Good dog! To see this help from the top-level: drush sheepdog --help</info>');
  }

  /**
   * Export migration logs in structured format for analysis.
   *
   * Creates comprehensive JSON output with searchable fields for
   * migration debugging. Each log entry includes timestamp,
   * migration_id, entity_id, operation_type, and diff data.
   * Like a good retriever, fetches exactly what you need!
   *
   * @param array $options
   *   Command options.
   */
  #[CLI\Command(name: 'sheepdog logs:export', aliases: ['sle'])]
  #[CLI\Option(name: 'output', description: 'Output file path (default: migration-logs-TIMESTAMP.json)')]
  #[CLI\Option(name: 'hours', description: 'Include logs from last N hours (default: 24)')]
  #[CLI\Option(name: 'migration', description: 'Filter by migration ID pattern')]
  #[CLI\Option(name: 'create', description: 'Filter by create operations only')]
  #[CLI\Option(name: 'update', description: 'Filter by update operations only')]
  #[CLI\Option(name: 'no-change', description: 'Filter by no-change operations only')]
  #[CLI\Option(name: 'error', description: 'Filter by error operations only')]
  #[CLI\Option(name: 'entity-id', description: 'Filter by specific entity ID')]
  #[CLI\Option(name: 'primary-id', description: 'Filter by primary ID value')]
  #[CLI\Option(name: 'json', description: 'Output in JSON format')]
  #[CLI\Option(name: 'ndjson', description: 'Output in NDJSON format (one JSON object per line)')]
  #[CLI\Option(name: 'csv', description: 'Output in CSV format')]
  #[CLI\Option(name: 'pretty', description: 'Pretty print JSON output')]
  #[CLI\Usage(name: 'sheepdog logs:export', description: 'Export all migration logs from last 24 hours')]
  #[CLI\Usage(name: 'sheepdog logs:export --hours=2 --update', description: 'Export only update operations from last 2 hours')]
  #[CLI\Usage(name: 'sheepdog logs:export --output=/tmp/debug.json --pretty', description: 'Export to specific file with pretty formatting')]
  public function exportLogs(
    array $options = [
      'output' => NULL,
      'hours' => 24,
      'migration' => NULL,
      'create' => FALSE,
      'update' => FALSE,
      'no-change' => FALSE,
      'error' => FALSE,
      'entity-id' => NULL,
      'primary-id' => NULL,
      'json' => FALSE,
      'ndjson' => FALSE,
      'csv' => FALSE,
      'pretty' => FALSE,
    ],
  ): void {
    $startTime = microtime(TRUE);

    // Build query conditions.
    $conditions = $this->buildQueryConditions($options);
    $logs = $this->fetchLogs($conditions);

    $this->output()->writeln(sprintf('🐕 Found %d log entries to fetch and export...', count($logs)));

    // Process logs into structured format.
    $structuredLogs = $this->processLogsToStructured($logs);

    // Determine output format from new boolean flags.
    $format = $this->determineOutputFormat($options);

    // Generate output filename if not provided.
    $outputFile = $options['output'] ?? $this->generateOutputFilename($format);

    // Export in requested format.
    $this->exportToFile($structuredLogs, $outputFile, array_merge($options, ['format' => $format]));

    $duration = round(microtime(TRUE) - $startTime, 2);
    $this->logger()->success(sprintf(
      '🐕 Good dog! Exported %d log entries to %s in %s seconds',
      count($structuredLogs),
      $outputFile,
      $duration
    ));

    // Show usage examples.
    $this->displayUsageExamples($outputFile, $format);
  }

  /**
   * Analyze migration logs with statistical summaries.
   *
   * Provides insights into migration performance and issues.
   * Like a border collie analyzing the flock!
   */
  #[CLI\Command(name: 'sheepdog logs:analyze', aliases: ['sla'])]
  #[CLI\Option(name: 'hours', description: 'Analyze logs from last N hours (default: 24)')]
  #[CLI\Option(name: 'migration', description: 'Filter by migration ID pattern')]
  #[CLI\Usage(name: 'sheepdog logs:analyze', description: 'Analyze all migration logs from last 24 hours')]
  #[CLI\Usage(name: 'sheepdog logs:analyze --hours=1', description: 'Quick analysis of last hour')]
  public function analyzeLogs(
    array $options = [
      'hours' => 24,
      'migration' => NULL,
    ],
  ): void {
    $conditions = $this->buildQueryConditions($options);
    $logs = $this->fetchLogs($conditions);

    $analysis = $this->generateAnalysis($logs);

    $this->displayAnalysis($analysis);
  }

  /**
   * Determine output format from boolean flags.
   */
  private function determineOutputFormat(array $options): string {
    if ($options['csv']) {
      return 'csv';
    }
    if ($options['ndjson']) {
      return 'ndjson';
    }
    // Default to json (includes pretty flag handling)
    return 'json';
  }

  /**
   * Tail migration logs in real-time with structured output.
   *
   * Watch your migrations like a vigilant guard dog!
   */
  #[CLI\Command(name: 'sheepdog logs:tail', aliases: ['slt'])]
  #[CLI\Option(name: 'json', description: 'Output in JSON format')]
  #[CLI\Usage(name: 'sheepdog logs:tail', description: 'Tail migration logs in real-time')]
  public function tailLogs(
    array $options = [
      'json' => FALSE,
    ],
  ): void {
    $this->output()->writeln('🐕 Sheepdog on watch! Tailing migration logs (Ctrl+C to stop watching)...');

    $lastId = $this->getLatestLogId();

    while (TRUE) {
      $newLogs = $this->fetchLogsSince($lastId);

      if (!empty($newLogs)) {
        foreach ($newLogs as $log) {
          if ($options['json']) {
            $this->output()->writeln(json_encode($this->processLogToStructured($log)));
          }
          else {
            $this->displayPrettyLog($log);
          }
          $lastId = max($lastId, $log->wid);
        }
      }

      sleep(1);
    }
  }

  /**
   * Build database query conditions from options.
   */
  private function buildQueryConditions(array $options): array {
    $conditions = [];
    $params = [];
    $config = $this->configFactory->get('sheepdog.settings');

    // Time filter.
    if (!empty($options['hours'])) {
      $conditions[] = 'timestamp >= :time_threshold';
      $params[':time_threshold'] = time() - ($options['hours'] * 3600);
    }

    // Channel filter - use configured channels.
    $mainChannel = $config->get('logging.main_channel') ?: 'sheepdog';
    $editsChannel = $config->get('logging.edits_channel') ?: 'sheepdog_edits';

    $conditions[] = "(type = :main_channel OR type = :edits_channel)";
    $params[':main_channel'] = $mainChannel;
    $params[':edits_channel'] = $editsChannel;

    // Migration ID filter.
    if (!empty($options['migration'])) {
      $conditions[] = 'message LIKE :migration_pattern';
      $params[':migration_pattern'] = '%' . $options['migration'] . '%';
    }

    // Operation type filters (now using boolean flags).
    $operationPatterns = [];
    if (!empty($options['create'])) {
      $operationPatterns[] = '%Created new entity%';
    }
    if (!empty($options['update'])) {
      $operationPatterns[] = '%Updated entity%';
    }
    if (!empty($options['no-change'])) {
      $operationPatterns[] = '%No changes detected%';
    }
    if (!empty($options['error'])) {
      $operationPatterns[] = '%could not be loaded%';
    }

    if (!empty($operationPatterns)) {
      $operationConds = [];
      foreach ($operationPatterns as $idx => $pattern) {
        $paramKey = ':operation_pattern_' . $idx;
        $operationConds[] = 'message LIKE ' . $paramKey;
        $params[$paramKey] = $pattern;
      }
      $conditions[] = '(' . implode(' OR ', $operationConds) . ')';
    }

    // Entity ID filter.
    if (!empty($options['entity-id'])) {
      $conditions[] = 'message LIKE :entity_pattern';
      $params[':entity_pattern'] = '%entity ' . $options['entity-id'] . ' %';
    }

    // Primary ID filter (generic replacement for ID10)
    if (!empty($options['primary-id'])) {
      $conditions[] = 'message LIKE :primary_pattern';
      $params[':primary_pattern'] = '%primary_id: ' . $options['primary-id'] . '%';
    }

    return ['conditions' => $conditions, 'params' => $params];
  }

  /**
   * Fetch logs based on conditions.
   */
  private function fetchLogs(array $queryData): array {
    $query = $this->database->select('watchdog', 'w')
      ->fields('w')
      ->orderBy('timestamp', 'DESC')
      ->range(0, 999999);

    if (!empty($queryData['conditions'])) {
      $whereClause = implode(' AND ', $queryData['conditions']);
      $query->where($whereClause, $queryData['params']);
    }

    return $query->execute()->fetchAll();
  }

  /**
   * Process logs into structured format.
   */
  private function processLogsToStructured(array $logs): array {
    return array_map([$this, 'processLogToStructured'], $logs);
  }

  /**
   * Process single log entry into structured format.
   */
  private function processLogToStructured($log): array {
    $message = $log->message;
    $variables = $log->variables ? unserialize($log->variables, ['allowed_classes' => FALSE]) : [];

    // Replace placeholders in message.
    if ($variables && is_array($variables)) {
      $message = strtr($message, $variables);
    }

    // Parse structured data from message.
    $structured = [
      '@timestamp' => date('c', $log->timestamp),
      'level' => $this->mapSeverityToLevel($log->severity),
      'message' => $message,
      'log' => [
        'logger' => 'sheepdog',
        'level' => $this->mapSeverityToLevel($log->severity),
      ],
      'event' => [
        'dataset' => 'sheepdog_migration',
        'kind' => 'event',
      ],
      'migration' => $this->extractMigrationData($message, $variables),
      'entity' => $this->extractEntityData($message, $variables),
      'operation' => $this->extractOperationType($message),
      'diff' => $this->extractDiffData($message),
      'host' => [
        'hostname' => $log->hostname ?? 'unknown',
      ],
      'user' => [
        'id' => $log->uid ?? 0,
      ],
      'meta' => [
        'watchdog_id' => $log->wid,
        'type' => $log->type,
        'location' => $log->location ?? '',
        'referer' => $log->referer ?? '',
      ],
    ];

    return array_filter($structured, fn($value) => $value !== NULL && $value !== '');
  }

  /**
   * Extract migration data from log message.
   */
  private function extractMigrationData(string $message, array $variables): ?array {
    $migrationData = [];

    // Extract primary ID if present (replaces ID10)
    $primaryId = NULL;
    if (preg_match('/primary_id:\s*([^\s,\)]+)/', $message, $matches)) {
      $primaryId = $matches[1];
    }

    // Extract from variables if available.
    if (isset($variables['primary_id'])) {
      $primaryId = $variables['primary_id'];
    }

    // Fallback to old ID10 pattern for backwards compatibility.
    if (!$primaryId && preg_match('/ID10:\s*([^\s,\)]+)/', $message, $matches)) {
      $primaryId = $matches[1];
    }

    if (isset($variables['@id10'])) {
      $primaryId = $variables['@id10'];
    }

    if ($primaryId) {
      $migrationData['primary_id'] = $primaryId;
    }

    // Extract migration ID from context.
    if (isset($variables['migration'])) {
      $migrationData['id'] = $variables['migration'];
    }

    return !empty($migrationData) ? $migrationData : NULL;
  }

  /**
   * Extract entity data from log message.
   */
  private function extractEntityData(string $message, array $variables): ?array {
    $entityData = [];

    // Extract entity ID.
    if (preg_match('/entity\s+(\d+)/', $message, $matches)) {
      $entityData['id'] = (int) $matches[1];
    }

    if (isset($variables['entity_id'])) {
      $entityData['id'] = (int) $variables['entity_id'];
    }

    // Entity type is now generic - don't hardcode.
    if (isset($variables['entity_type'])) {
      $entityData['type'] = $variables['entity_type'];
    }

    return !empty($entityData) ? $entityData : NULL;
  }

  /**
   * Extract operation type from message.
   */
  private function extractOperationType(string $message): string {
    if (strpos($message, 'Created new entity') !== FALSE) {
      return 'create';
    }
    if (strpos($message, 'Updated entity') !== FALSE) {
      return 'update';
    }
    if (strpos($message, 'No changes detected') !== FALSE) {
      return 'no-change';
    }
    if (strpos($message, 'could not be loaded') !== FALSE || strpos($message, 'warning') !== FALSE) {
      return 'error';
    }

    return 'info';
  }

  /**
   * Extract diff data from message.
   */
  private function extractDiffData(string $message): ?array {
    if (strpos($message, 'CHANGES') === FALSE) {
      return NULL;
    }

    // Extract the diff section.
    if (preg_match('/(\d+\s+CHANGES?)\n(.+)$/s', $message, $matches)) {
      $diffLines = explode("\n", trim($matches[2]));
      $changes = [];

      foreach ($diffLines as $line) {
        $line = trim($line);
        if (empty($line)) {
          continue;
        }

        // Parse diff line format: "  +field: value" or "  ~field: old → new".
        if (preg_match('/^\s*([+~-])(\w+):\s*(.+)$/', $line, $lineMatches)) {
          $operation = $lineMatches[1];
          $field = $lineMatches[2];
          $value = $lineMatches[3];

          $changeType = match($operation) {
            '+' => 'added',
            '-' => 'removed',
            '~' => 'changed',
            default => 'unknown',
          };

          $changes[] = [
            'field' => $field,
            'operation' => $changeType,
            'value' => $value,
          ];
        }
      }

      return !empty($changes) ? ['fields' => $changes] : NULL;
    }

    return NULL;
  }

  /**
   * Map Drupal severity to ELK levels.
   */
  private function mapSeverityToLevel(int $severity): string {
    return match($severity) {
      0, 1, 2 => 'error',
      3 => 'warning',
      4, 5 => 'info',
      6, 7 => 'debug',
      default => 'info',
    };
  }

  /**
   * Export structured logs to file.
   */
  private function exportToFile(array $logs, string $filename, array $options): void {
    $format = $options['format'] ?? 'json';

    switch ($format) {
      case 'ndjson':
        $content = implode("\n", array_map(fn($l) => json_encode($l, JSON_UNESCAPED_SLASHES), $logs));
        break;

      case 'csv':
        $content = $this->convertToCsv($logs);
        break;

      // Json.
      default:
        if (!empty($options['pretty'])) {
          $jsonOptions = JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT;
          $content = json_encode($logs, $jsonOptions);
        }
        else {
          $content = implode("\n", array_map(fn($l) => json_encode($l, JSON_UNESCAPED_SLASHES), $logs));
        }
        break;
    }

    file_put_contents($filename, $content, LOCK_EX);
  }

  /**
   * Generate output filename based on format and timestamp.
   */
  private function generateOutputFilename(string $format): string {
    $timestamp = date('Y-m-d_H-i-s');
    $extension = match($format) {
      'csv' => 'csv',
      'ndjson' => 'ndjson',
      default => 'json',
    };

    return "sheepdog-logs-{$timestamp}.{$extension}";
  }

  /**
   * Display usage examples for the exported file.
   */
  private function displayUsageExamples(string $filename, string $format): void {
    $this->output()->writeln('');
    $this->output()->writeln('<info>🐕 Usage examples (good dog!):</info>');

    switch ($format) {
      case 'ndjson':
        $this->output()->writeln("# Import into Elasticsearch:");
        $this->output()->writeln("curl -X POST 'localhost:9200/migration-logs/_bulk' -H 'Content-Type: application/json' --data-binary @{$filename}");
        break;

      case 'csv':
        $this->output()->writeln("# Open in Excel/LibreOffice or analyze with:");
        $this->output()->writeln("csvkit {$filename}");
        break;

      default:
        $this->output()->writeln("# Search with jq:");
        $this->output()->writeln("jq '.[] | select(.operation == \"update\")' {$filename}");
        $this->output()->writeln("jq '.[] | select(.migration.primary_id == \"12345\")' {$filename}");
        $this->output()->writeln("jq '[.[] | select(.diff)] | length' {$filename}  # Count entries with changes");
        break;
    }
  }

  /**
   * Generate analysis of logs.
   */
  private function generateAnalysis(array $logs): array {
    $analysis = [
      'total_entries' => count($logs),
      'operations' => [],
      'migrations' => [],
      'errors' => 0,
      'timespan' => [],
      'top_changed_fields' => [],
    ];

    $fieldChanges = [];
    $timestamps = [];
    $migrationIds = [];

    foreach ($logs as $log) {
      $structured = $this->processLogToStructured($log);

      // Count operations.
      $op = $structured['operation'];
      $analysis['operations'][$op] = ($analysis['operations'][$op] ?? 0) + 1;

      // Count errors.
      if ($op === 'error') {
        $analysis['errors']++;
      }

      // Track migrations.
      if (isset($structured['migration']['id'])) {
        $migId = $structured['migration']['id'];
        $migrationIds[$migId] = ($migrationIds[$migId] ?? 0) + 1;
      }

      // Track field changes.
      if (isset($structured['diff']['fields'])) {
        foreach ($structured['diff']['fields'] as $change) {
          $field = $change['field'];
          $fieldChanges[$field] = ($fieldChanges[$field] ?? 0) + 1;
        }
      }

      $timestamps[] = $log->timestamp;
    }

    // Calculate timespan.
    if (!empty($timestamps)) {
      $analysis['timespan'] = [
        'start' => date('Y-m-d H:i:s', min($timestamps)),
        'end' => date('Y-m-d H:i:s', max($timestamps)),
        'duration_hours' => round((max($timestamps) - min($timestamps)) / 3600, 2),
      ];
    }

    // Top changed fields.
    arsort($fieldChanges);
    $analysis['top_changed_fields'] = array_slice($fieldChanges, 0, 10, TRUE);

    // Top migrations.
    arsort($migrationIds);
    $analysis['migrations'] = array_slice($migrationIds, 0, 10, TRUE);

    return $analysis;
  }

  /**
   * Display analysis results.
   */
  private function displayAnalysis(array $analysis): void {
    $this->output()->writeln('<info>🐕 === Sheepdog Migration Log Analysis === 🐕</info>');
    $this->output()->writeln('');

    $this->output()->writeln(sprintf('<comment>Total Entries:</comment> %d', $analysis['total_entries']));

    if (!empty($analysis['timespan'])) {
      $this->output()->writeln(sprintf('<comment>Time Range:</comment> %s to %s (%s hours)',
        $analysis['timespan']['start'],
        $analysis['timespan']['end'],
        $analysis['timespan']['duration_hours']
      ));
    }

    $this->output()->writeln('');
    $this->output()->writeln('<comment>Operations:</comment>');
    foreach ($analysis['operations'] as $op => $count) {
      $icon = match($op) {
        'create' => '➕',
        'update' => '🔄',
        'no-change' => '⭕',
        'error' => '❌',
        default => '📝',
      };
      $this->output()->writeln(sprintf('  %s %s: %d', $icon, $op, $count));
    }

    if ($analysis['errors'] > 0) {
      $this->output()->writeln(sprintf('<error>❌ Errors: %d (Woof! Needs attention!)</error>', $analysis['errors']));
    }

    if (!empty($analysis['migrations'])) {
      $this->output()->writeln('');
      $this->output()->writeln('<comment>Top Migrations:</comment>');
      foreach ($analysis['migrations'] as $migration => $count) {
        $this->output()->writeln(sprintf('  🎯 %s: %d entries', $migration, $count));
      }
    }

    if (!empty($analysis['top_changed_fields'])) {
      $this->output()->writeln('');
      $this->output()->writeln('<comment>Most Changed Fields:</comment>');
      foreach ($analysis['top_changed_fields'] as $field => $count) {
        $this->output()->writeln(sprintf('  📝 %s: %d changes', $field, $count));
      }
    }

    $this->output()->writeln('');
    $this->output()->writeln('🐕 <info>Good dog! Analysis complete.</info>');
  }

  /**
   * Get latest log ID for tailing.
   */
  private function getLatestLogId(): int {
    $config = $this->configFactory->get('sheepdog.settings');
    $mainChannel = $config->get('logging.main_channel') ?: 'sheepdog';
    $editsChannel = $config->get('logging.edits_channel') ?: 'sheepdog_edits';

    return (int) $this->database->select('watchdog', 'w')
      ->condition('type', [$mainChannel, $editsChannel], 'IN')
      ->fields('w', ['wid'])
      ->orderBy('wid', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchField();
  }

  /**
   * Fetch logs since a specific ID.
   */
  private function fetchLogsSince(int $lastId): array {
    $config = $this->configFactory->get('sheepdog.settings');
    $mainChannel = $config->get('logging.main_channel') ?: 'sheepdog';
    $editsChannel = $config->get('logging.edits_channel') ?: 'sheepdog_edits';

    return $this->database->select('watchdog', 'w')
      ->fields('w')
      ->condition('wid', $lastId, '>')
      ->condition('type', [$mainChannel, $editsChannel], 'IN')
      ->orderBy('wid', 'ASC')
      ->execute()
      ->fetchAll();
  }

  /**
   * Display a log entry in pretty format for tailing.
   */
  private function displayPrettyLog($log): void {
    $structured = $this->processLogToStructured($log);
    $timestamp = date('H:i:s', $log->timestamp);

    $prefix = match($structured['operation']) {
      'create' => '<info>➕</info>',
      'update' => '<comment>🔄</comment>',
      'error' => '<error>❌</error>',
      default => '📝',
    };

    $entityInfo = '';
    if (isset($structured['entity']['id'])) {
      $entityInfo = sprintf(' (entity:%d)', $structured['entity']['id']);
    }

    $migrationInfo = '';
    if (isset($structured['migration']['primary_id'])) {
      $migrationInfo = sprintf(' ID:%s', $structured['migration']['primary_id']);
    }

    $this->output()->writeln(sprintf(
      '%s [%s] %s%s%s',
      $prefix,
      $timestamp,
      $structured['operation'],
      $entityInfo,
      $migrationInfo
    ));
  }

  /**
   * Convert logs to CSV format.
   */
  private function convertToCsv(array $logs): string {
    if (empty($logs)) {
      return '';
    }

    // Define CSV columns.
    $columns = [
      'timestamp', 'level', 'operation', 'entity_id', 'primary_id',
      'migration_id', 'changes_count', 'message_preview',
    ];

    $csv = [];
    $csv[] = implode(',', array_map(fn($col) => '"' . $col . '"', $columns));

    foreach ($logs as $log) {
      $row = [
        $log['@timestamp'],
        $log['level'],
        $log['operation'],
        $log['entity']['id'] ?? '',
        $log['migration']['primary_id'] ?? '',
        $log['migration']['id'] ?? '',
        isset($log['diff']['fields']) ? count($log['diff']['fields']) : 0,
        '"' . str_replace('"', '""', substr($log['message'], 0, 100)) . '"',
      ];

      $csv[] = implode(',', $row);
    }

    return implode("\n", $csv);
  }

}
