<?php

class DatabaseService
{
    private static $pdo = null;

    public static function pdo()
    {
        if (self::$pdo === null) {
            $config = require dirname(__DIR__) . '/config.php';
            $db = $config['db'];
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ];

            self::$pdo = self::connectWithBootstrap($db, $options);
            self::ensureSchema(self::$pdo);
            self::mysqlUpgradeVictimOAuth(self::$pdo);
            self::mysqlUpgradeVictimNotifyEmail(self::$pdo);
            self::mysqlUpgradeVictimPasswordReset(self::$pdo);
            self::mysqlUpgradeVictimMessageSavedAt(self::$pdo);
        }

        return self::$pdo;
    }

    private static function connectWithBootstrap(array $db, array $options)
    {
        try {
            return new PDO($db['dsn'], $db['user'], $db['pass'], $options);
        } catch (PDOException $e) {
            if (!self::isUnknownDatabaseError($e) || !self::isMysqlWithDbname($db['dsn'])) {
                throw $e;
            }
            self::createMysqlDatabaseIfMissing($db, $options);

            return new PDO($db['dsn'], $db['user'], $db['pass'], $options);
        }
    }

    private static function isUnknownDatabaseError(PDOException $e)
    {
        $info = $e->errorInfo;
        if (isset($info[1]) && (int) $info[1] === 1049) {
            return true;
        }

        return strpos($e->getMessage(), '1049') !== false;
    }

    private static function isMysqlWithDbname($dsn)
    {
        if (stripos($dsn, 'mysql:') !== 0) {
            return false;
        }
        $params = self::parseMysqlDsn($dsn);

        return !empty($params['dbname']);
    }

    private static function parseMysqlDsn($dsn)
    {
        $params = [];
        $rest = substr($dsn, strlen('mysql:'));
        foreach (explode(';', $rest) as $pair) {
            $pair = trim($pair);
            if ($pair === '') {
                continue;
            }
            $eq = strpos($pair, '=');
            if ($eq === false) {
                continue;
            }
            $key = strtolower(trim(substr($pair, 0, $eq)));
            $params[$key] = trim(substr($pair, $eq + 1));
        }

        return $params;
    }

    private static function mysqlServerDsn(array $parts)
    {
        $keys = ['host', 'port', 'unix_socket', 'charset'];
        $segments = [];
        foreach ($keys as $key) {
            if (!empty($parts[$key])) {
                $segments[] = $key . '=' . $parts[$key];
            }
        }

        return 'mysql:' . implode(';', $segments);
    }

    private static function mysqlIdentifier($name)
    {
        return '`' . str_replace('`', '``', $name) . '`';
    }

    private static function mysqlCharsetForCreateDatabase(array $parts)
    {
        $charset = isset($parts['charset']) ? $parts['charset'] : 'utf8mb4';
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $charset)) {
            $charset = 'utf8mb4';
        }

        return $charset;
    }

    private static function createMysqlDatabaseIfMissing(array $db, array $options)
    {
        $parts = self::parseMysqlDsn($db['dsn']);
        $dbname = $parts['dbname'];
        $serverDsn = self::mysqlServerDsn($parts);
        if ($serverDsn === 'mysql:') {
            throw new PDOException('Cannot create database: DSN has no host or unix_socket.');
        }

        $charset = self::mysqlCharsetForCreateDatabase($parts);
        $collate = ($charset === 'utf8mb4') ? 'utf8mb4_unicode_ci' : null;

        $server = new PDO($serverDsn, $db['user'], $db['pass'], $options);
        $sql = 'CREATE DATABASE IF NOT EXISTS ' . self::mysqlIdentifier($dbname)
            . ' CHARACTER SET ' . $charset;
        if ($collate !== null) {
            $sql .= ' COLLATE ' . $collate;
        }
        $server->exec($sql);
    }

    private static function ensureSchema(PDO $pdo)
    {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            return;
        }
        $check = $pdo->query("SHOW TABLES LIKE 'victim'");
        if ($check !== false && $check->fetch() !== false) {
            return;
        }

        $path = dirname(__DIR__) . '/schema.sql';
        if (!is_readable($path)) {
            throw new RuntimeException('Schema file missing or unreadable: ' . $path);
        }
        $sql = file_get_contents($path);
        foreach (explode(';', $sql) as $chunk) {
            $statement = trim(preg_replace('/--[^\n]*/', '', $chunk));
            $statement = trim($statement);
            if ($statement === '') {
                continue;
            }
            $pdo->exec($statement);
        }
    }

    private static $victimOauthUpgraded = false;

    /**
     * Adds google_sub and nullable password for Google OAuth sign-in on existing databases.
     */
    private static function mysqlUpgradeVictimOAuth(PDO $pdo): void
    {
        if (self::$victimOauthUpgraded) {
            return;
        }
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            self::$victimOauthUpgraded = true;

            return;
        }
        try {
            $db = $pdo->query('SELECT DATABASE()')->fetchColumn();
            if ($db === false || $db === null || $db === '') {
                self::$victimOauthUpgraded = true;

                return;
            }
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
            );
            $stmt->execute([$db, 'victim', 'google_sub']);
            if ((int) $stmt->fetchColumn() === 0) {
                $pdo->exec('ALTER TABLE victim ADD COLUMN google_sub VARCHAR(255) NULL DEFAULT NULL');
            }
            $idx = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?'
            );
            $idx->execute([$db, 'victim', 'victim_google_sub_key']);
            if ((int) $idx->fetchColumn() === 0) {
                $pdo->exec('CREATE UNIQUE INDEX victim_google_sub_key ON victim (google_sub)');
            }
            $stmt->execute([$db, 'victim', 'password']);
            $stmt2 = $pdo->prepare(
                'SELECT IS_NULLABLE FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
            );
            $stmt2->execute([$db, 'victim', 'password']);
            $nullable = $stmt2->fetchColumn();
            if ($nullable === 'NO') {
                $pdo->exec('ALTER TABLE victim MODIFY password VARCHAR(255) NULL');
            }
        } catch (Throwable $e) {
            error_log('Harness OAuth migration: ' . $e->getMessage());
        }
        self::$victimOauthUpgraded = true;
    }

    private static $victimNotifyEmailUpgraded = false;

    private static function mysqlUpgradeVictimNotifyEmail(PDO $pdo): void
    {
        if (self::$victimNotifyEmailUpgraded) {
            return;
        }
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            self::$victimNotifyEmailUpgraded = true;

            return;
        }
        try {
            $db = $pdo->query('SELECT DATABASE()')->fetchColumn();
            if ($db === false || $db === null || $db === '') {
                self::$victimNotifyEmailUpgraded = true;

                return;
            }
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
            );
            $stmt->execute([$db, 'victim', 'notify_email_on_new_link']);
            if ((int) $stmt->fetchColumn() === 0) {
                $pdo->exec(
                    'ALTER TABLE victim ADD COLUMN notify_email_on_new_link TINYINT(1) NOT NULL DEFAULT 0'
                );
            }
        } catch (Throwable $e) {
            error_log('Harness notify_email migration: ' . $e->getMessage());
        }
        self::$victimNotifyEmailUpgraded = true;
    }

    private static $victimPasswordResetUpgraded = false;

    private static function mysqlUpgradeVictimPasswordReset(PDO $pdo): void
    {
        if (self::$victimPasswordResetUpgraded) {
            return;
        }
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            self::$victimPasswordResetUpgraded = true;

            return;
        }
        try {
            $db = $pdo->query('SELECT DATABASE()')->fetchColumn();
            if ($db === false || $db === null || $db === '') {
                self::$victimPasswordResetUpgraded = true;

                return;
            }
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
            );
            $stmt->execute([$db, 'victim', 'password_reset_token_hash']);
            if ((int) $stmt->fetchColumn() === 0) {
                $pdo->exec(
                    'ALTER TABLE victim ADD COLUMN password_reset_token_hash VARCHAR(64) NULL DEFAULT NULL'
                );
            }
            $stmt->execute([$db, 'victim', 'password_reset_expires']);
            if ((int) $stmt->fetchColumn() === 0) {
                $pdo->exec(
                    'ALTER TABLE victim ADD COLUMN password_reset_expires DATETIME NULL DEFAULT NULL'
                );
            }
        } catch (Throwable $e) {
            error_log('Harness password_reset migration: ' . $e->getMessage());
        }
        self::$victimPasswordResetUpgraded = true;
    }

    private static $victimMessageSavedAtUpgraded = false;

    /** Adds victim_message.saved_at (UTC) when missing; matches migrations/002_victim_message_saved_at.sql */
    private static function mysqlUpgradeVictimMessageSavedAt(PDO $pdo): void
    {
        if (self::$victimMessageSavedAtUpgraded) {
            return;
        }
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            self::$victimMessageSavedAtUpgraded = true;

            return;
        }
        try {
            $db = $pdo->query('SELECT DATABASE()')->fetchColumn();
            if ($db === false || $db === null || $db === '') {
                self::$victimMessageSavedAtUpgraded = true;

                return;
            }
            $tbl = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?'
            );
            $tbl->execute([$db, 'victim_message']);
            if ((int) $tbl->fetchColumn() === 0) {
                self::$victimMessageSavedAtUpgraded = true;

                return;
            }
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
            );
            $stmt->execute([$db, 'victim_message', 'saved_at']);
            if ((int) $stmt->fetchColumn() > 0) {
                self::$victimMessageSavedAtUpgraded = true;

                return;
            }
            $pdo->exec('ALTER TABLE victim_message ADD COLUMN saved_at DATETIME NULL');
            $pdo->exec('UPDATE victim_message SET saved_at = UTC_TIMESTAMP() WHERE saved_at IS NULL');
            $pdo->exec('ALTER TABLE victim_message MODIFY COLUMN saved_at DATETIME NOT NULL');
        } catch (Throwable $e) {
            error_log('Harness victim_message.saved_at migration: ' . $e->getMessage());
        }
        self::$victimMessageSavedAtUpgraded = true;
    }
}
