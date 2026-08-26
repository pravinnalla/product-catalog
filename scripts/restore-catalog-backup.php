<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/api/includes/catalog-storage.php';

function restore_usage(): void
{
    fwrite(STDOUT, <<<'TEXT'
Usage:
  php scripts/restore-catalog-backup.php --list [--dataset=<dataset>]
  php scripts/restore-catalog-backup.php --dataset=<dataset>
  php scripts/restore-catalog-backup.php --dataset=<dataset> --backup=<filename> --dry-run
  php scripts/restore-catalog-backup.php --dataset=<dataset> --backup=<filename> [--force]

An actual restore requires typing RESTORE interactively. Use --force only for an
intentional non-interactive restore. Backup filenames must come from --list.
TEXT
    );
    fwrite(STDOUT, PHP_EOL);
}

function restore_list(?string $dataset): void
{
    $backups = catalog_list_backups($dataset);
    if ($backups === []) {
        fwrite(STDOUT, "No catalogue backups found.\n");
        return;
    }

    fwrite(STDOUT, "Dataset       UTC timestamp       Backup filename\n");
    foreach ($backups as $backup) {
        $timestamp = DateTimeImmutable::createFromFormat('!Ymd-His', $backup['timestamp'], new DateTimeZone('UTC'));
        fwrite(STDOUT, sprintf(
            "%-13s %-19s %s\n",
            $backup['dataset'],
            $timestamp instanceof DateTimeImmutable ? $timestamp->format('Y-m-d H:i:s') : $backup['timestamp'],
            $backup['filename']
        ));
    }
}

try {
    $options = getopt('', ['list', 'dataset:', 'backup:', 'dry-run', 'force', 'help']);
    if (isset($options['help'])) {
        restore_usage();
        exit(0);
    }

    $dataset = isset($options['dataset']) && is_string($options['dataset'])
        ? $options['dataset']
        : null;
    $backup = isset($options['backup']) && is_string($options['backup'])
        ? $options['backup']
        : null;

    if (isset($options['list']) || ($dataset !== null && $backup === null)) {
        restore_list($dataset);
        exit(0);
    }

    if ($dataset === null || $backup === null) {
        restore_usage();
        throw new InvalidArgumentException('Both --dataset and --backup are required for validation or restore.');
    }

    $summary = backup_domain_restore_dry_run('catalog', $dataset, $backup);
    fwrite(STDOUT, sprintf(
        "Dataset: %s\nSelected backup: %s\nCurrent records: %d\nBackup records: %d\n",
        $summary['dataset'],
        $summary['backup'],
        $summary['currentCount'],
        $summary['backupCount']
    ));

    if (isset($options['dry-run'])) {
        fwrite(STDOUT, "Dry run PASS. Full-catalogue integrity is valid; no files were changed.\n");
        exit(0);
    }

    if (!isset($options['force'])) {
        $interactive = function_exists('stream_isatty') && stream_isatty(STDIN);
        if (!$interactive) {
            throw new RuntimeException('Non-interactive restore refused. Re-run intentionally with --force.');
        }
        fwrite(STDOUT, "Type RESTORE to continue: ");
        $confirmation = fgets(STDIN);
        if (!is_string($confirmation) || trim($confirmation) !== 'RESTORE') {
            throw new RuntimeException('Restore cancelled; confirmation did not match RESTORE.');
        }
    }

    $result = backup_domain_restore('catalog', $dataset, $backup);
    fwrite(STDOUT, sprintf(
        "Restore PASS. Dataset %s now has %d records.\nRollback backup: %s\nPost-restore catalogue validation: PASS\n",
        $result['dataset'],
        $result['recordCount'],
        $result['rollbackBackup']
    ));
} catch (Throwable $exception) {
    fwrite(STDERR, 'Restore failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
