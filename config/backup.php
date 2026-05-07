<?php

return [

    'backup' => [
        /*
         * The name of this application. You can use this name to monitor
         * the backups.
         *
         * Slug fijo sin espacios — ZipArchive en Windows tiene issues con paths que contienen espacios.
         * No usamos env('APP_NAME') porque ese valor sí tiene espacio ("CVAUP Tachira").
         */
        'name' => env('BACKUP_NAME', 'cvaup-tachira'),

        'source' => [
            'files' => [
                /*
                 * Solo respaldamos lo que NO se puede recuperar de git/composer:
                 *  - storage/app/fotos-privadas/fotos: las fotos privadas de reportes y técnicos
                 *
                 * SEGURIDAD (post-auditoría 2026-05-06, HIGH #3):
                 *  Ya NO incluimos `.env` en el backup. Razones:
                 *   1. Si el backup cae en manos equivocadas, las credenciales (DB_PASSWORD,
                 *      APP_KEY, MAIL_PASSWORD) van con él. Eso permite descifrar todo.
                 *   2. Los secretos deben gestionarse APARTE por TI institucional con un
                 *      password manager o KMS (Hashicorp Vault, AWS Secrets Manager, etc.).
                 *   3. La BD + las fotos son la data restaurable. El .env se reconstruye
                 *      desde el documento de despliegue de TI.
                 *
                 * El código (app/, resources/, vendor/, node_modules/) está en git/composer
                 * y se puede recrear; respaldarlo solo infla el .zip sin necesidad.
                 */
                'include' => [
                    storage_path('app/fotos-privadas/fotos'),
                ],

                /*
                 * No hace falta excluir nada porque solo incluimos directorios específicos arriba.
                 */
                'exclude' => [],

                /*
                 * Determines if symlinks should be followed.
                 */
                'follow_links' => false,

                /*
                 * Determines if it should avoid unreadable folders.
                 */
                'ignore_unreadable_directories' => false,

                /*
                 * This path is used to make directories in resulting zip-file relative
                 * Set to `null` to include complete absolute path
                 * Example: base_path()
                 */
                'relative_path' => null,
            ],

            /*
             * The names of the connections to the databases that should be backed up
             * MySQL, PostgreSQL, SQLite and Mongo databases are supported.
             *
             * The content of the database dump may be customized for each connection
             * by adding a 'dump' key to the connection settings in config/database.php.
             * E.g.
             * 'mysql' => [
             *       ...
             *      'dump' => [
             *           'exclude_tables' => [
             *                'table_to_exclude_from_backup',
             *                'another_table_to_exclude'
             *            ]
             *       ],
             * ],
             *
             * If you are using only InnoDB tables on a MySQL server, you can
             * also supply the useSingleTransaction option to avoid table locking.
             *
             * E.g.
             * 'mysql' => [
             *       ...
             *      'dump' => [
             *           'useSingleTransaction' => true,
             *       ],
             * ],
             *
             * For a complete list of available customization options, see https://github.com/spatie/db-dumper
             */
            'databases' => [
                env('DB_CONNECTION', 'mysql'),
            ],
        ],

        /*
         * The database dump can be compressed to decrease disk space usage.
         *
         * Out of the box Laravel-backup supplies
         * Spatie\DbDumper\Compressors\GzipCompressor::class.
         *
         * You can also create custom compressor. More info on that here:
         * https://github.com/spatie/db-dumper#using-compression
         *
         * If you do not want any compressor at all, set it to null.
         */
        'database_dump_compressor' => null,

        /*
         * If specified, the database dumped file name will contain a timestamp (e.g.: 'Y-m-d-H-i-s').
         */
        'database_dump_file_timestamp_format' => null,

        /*
         * The base of the dump filename, either 'database' or 'connection'
         *
         * If 'database' (default), the dumped filename will contain the database name.
         * If 'connection', the dumped filename will contain the connection name.
         */
        'database_dump_filename_base' => 'database',

        /*
         * The file extension used for the database dump files.
         *
         * If not specified, the file extension will be .archive for MongoDB and .sql for all other databases
         * The file extension should be specified without a leading .
         */
        'database_dump_file_extension' => '',

        'destination' => [
            /*
             * The compression algorithm to be used for creating the zip archive.
             *
             * If backing up only database, you may choose gzip compression for db dump and no compression at zip.
             *
             * Some common algorithms are listed below:
             * ZipArchive::CM_STORE (no compression at all; set 0 as compression level)
             * ZipArchive::CM_DEFAULT
             * ZipArchive::CM_DEFLATE
             * ZipArchive::CM_BZIP2
             * ZipArchive::CM_XZ
             *
             * For more check https://www.php.net/manual/zip.constants.php and confirm it's supported by your system.
             */
            'compression_method' => ZipArchive::CM_DEFAULT,

            /*
             * The compression level corresponding to the used algorithm; an integer between 0 and 9.
             *
             * Check supported levels for the chosen algorithm, usually 1 means the fastest and weakest compression,
             * while 9 the slowest and strongest one.
             *
             * Setting of 0 for some algorithms may switch to the strongest compression.
             */
            'compression_level' => 9,

            /*
             * The filename prefix used for the backup zip file.
             */
            'filename_prefix' => '',

            /*
             * The disk names on which the backups will be stored.
             */
            'disks' => [
                'local',
            ],
        ],

        /*
         * The directory where the temporary files will be stored.
         */
        'temporary_directory' => storage_path('app/backup-temp'),

        /*
         * The password to be used for archive encryption.
         *
         * SEGURIDAD (post-auditoría 2026-05-06, HIGH #3):
         *  La encriptación DEBE ser obligatoria, pero la validación se hace en
         *  el punto de USO (BackupController::generar y comando backup:run),
         *  NO aquí — porque config/*.php se carga eagerly por TODO el framework
         *  y un throw aquí rompería el CLI completo (artisan list, route:cache, etc.).
         *
         *  Para generar el secreto: openssl rand -base64 32
         *  Almacenarlo en gestor de secretos institucional (NO en el mismo server).
         */
        'password' => env('BACKUP_ARCHIVE_PASSWORD'),

        /*
         * The encryption algorithm to be used for archive encryption.
         * You can set it to `null` or `false` to disable encryption.
         *
         * When set to 'default', we'll use ZipArchive::EM_AES_256 if it is
         * available on your system.
         */
        'encryption' => 'default',

        /*
         * The number of attempts, in case the backup command encounters an exception
         */
        'tries' => 1,

        /*
         * The number of seconds to wait before attempting a new backup if the previous try failed
         * Set to `0` for none
         */
        'retry_delay' => 0,
    ],

    /*
     * You can get notified when specific events occur. Out of the box you can use 'mail' and 'slack'.
     * For Slack you need to install laravel/slack-notification-channel.
     *
     * You can also use your own notification classes, just make sure the class is named after one of
     * the `Spatie\Backup\Notifications\Notifications` classes.
     */
    'notifications' => [
        /*
         * Solo notificamos lo importante para evitar spam:
         *   - Backup falló: email crítico, requiere acción del admin
         *   - Cleanup falló: email crítico
         *   - Backup llevó muchos días sin generarse: alerta institucional
         *
         * Eventos de éxito NO se notifican (sería 1 email diario innecesario).
         * Si quieres recibir confirmación de éxito también, descomenta esas líneas.
         */
        'notifications' => [
            \Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification::class => ['mail'],
            \Spatie\Backup\Notifications\Notifications\UnhealthyBackupWasFoundNotification::class => ['mail'],
            \Spatie\Backup\Notifications\Notifications\CleanupHasFailedNotification::class => ['mail'],
            // \Spatie\Backup\Notifications\Notifications\BackupWasSuccessfulNotification::class => ['mail'],
            // \Spatie\Backup\Notifications\Notifications\HealthyBackupWasFoundNotification::class => ['mail'],
            // \Spatie\Backup\Notifications\Notifications\CleanupWasSuccessfulNotification::class => ['mail'],
        ],

        'notifiable' => \Spatie\Backup\Notifications\Notifiable::class,

        'mail' => [
            /*
             * Email destino de las alertas críticas.
             * Configurable vía .env con BACKUP_NOTIFICATIONS_EMAIL.
             * Default: admin del sistema según el seeder (admin@cvaup-tachira.com).
             */
            'to' => env('BACKUP_NOTIFICATIONS_EMAIL', 'admin@cvaup-tachira.com'),

            'from' => [
                'address' => env('MAIL_FROM_ADDRESS', 'sistema@cvaup-tachira.com'),
                'name' => env('MAIL_FROM_NAME', 'CVAUP Táchira — Sistema'),
            ],
        ],

        'slack' => [
            'webhook_url' => '',

            /*
             * If this is set to null the default channel of the webhook will be used.
             */
            'channel' => null,

            'username' => null,

            'icon' => null,
        ],

        'discord' => [
            'webhook_url' => '',

            /*
             * If this is an empty string, the name field on the webhook will be used.
             */
            'username' => '',

            /*
             * If this is an empty string, the avatar on the webhook will be used.
             */
            'avatar_url' => '',
        ],
    ],

    /*
     * Here you can specify which backups should be monitored.
     * If a backup does not meet the specified requirements the
     * UnHealthyBackupWasFound event will be fired.
     */
    'monitor_backups' => [
        [
            'name' => env('APP_NAME', 'laravel-backup'),
            'disks' => ['local'],
            'health_checks' => [
                \Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays::class => 1,
                \Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes::class => 5000,
            ],
        ],

        /*
        [
            'name' => 'name of the second app',
            'disks' => ['local', 's3'],
            'health_checks' => [
                \Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays::class => 1,
                \Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes::class => 5000,
            ],
        ],
        */
    ],

    'cleanup' => [
        /*
         * Estrategia personalizada: KeepLastNStrategy mantiene los últimos N backups
         * y borra el resto (FIFO). Cantidad configurada en `keep_last_n.count`.
         *
         * Aplicada por el comando `backup:clean` (cron diario en producción).
         * También se aplica al manual desde BackupController (con MAX_BACKUPS_RETAINED).
         *
         * Razón de NO usar DefaultStrategy de Spatie:
         *  La default mantiene 7d + 16 daily + 8 weekly + 4 monthly + 2 yearly
         *  (lógica temporal compleja). Para CVAUP Táchira preferimos lógica simple
         *  "últimos 10" — predecible y consistente con la UI.
         */
        'strategy' => \App\Backup\KeepLastNStrategy::class,

        /*
         * Cantidad de backups recientes a retener. Cuando se genera el #11,
         * el más antiguo se elimina automáticamente.
         */
        'keep_last_n' => [
            'count' => 10,
        ],

        /*
         * Config de DefaultStrategy de Spatie — IGNORADA mientras `strategy` apunte
         * a KeepLastNStrategy. Se mantiene aquí por si en el futuro se quiere
         * volver a la política temporal. NO eliminar.
         */

        'default_strategy' => [
            /*
             * The number of days for which backups must be kept.
             */
            'keep_all_backups_for_days' => 7,

            /*
             * After the "keep_all_backups_for_days" period is over, the most recent backup
             * of that day will be kept. Older backups within the same day will be removed.
             * If you create backups only once a day, no backups will be removed yet.
             */
            'keep_daily_backups_for_days' => 16,

            /*
             * After the "keep_daily_backups_for_days" period is over, the most recent backup
             * of that week will be kept. Older backups within the same week will be removed.
             * If you create backups only once a week, no backups will be removed yet.
             */
            'keep_weekly_backups_for_weeks' => 8,

            /*
             * After the "keep_weekly_backups_for_weeks" period is over, the most recent backup
             * of that month will be kept. Older backups within the same month will be removed.
             */
            'keep_monthly_backups_for_months' => 4,

            /*
             * After the "keep_monthly_backups_for_months" period is over, the most recent backup
             * of that year will be kept. Older backups within the same year will be removed.
             */
            'keep_yearly_backups_for_years' => 2,

            /*
             * After cleaning up the backups remove the oldest backup until
             * this amount of megabytes has been reached.
             * Set null for unlimited size.
             */
            'delete_oldest_backups_when_using_more_megabytes_than' => 5000,
        ],

        /*
         * The number of attempts, in case the cleanup command encounters an exception
         */
        'tries' => 1,

        /*
         * The number of seconds to wait before attempting a new cleanup if the previous try failed
         * Set to `0` for none
         */
        'retry_delay' => 0,
    ],

];
