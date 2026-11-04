<?php

// Initialize a fresh local installation without resetting existing application data.
function initialize_database(PDO $db, string $database): void {
    $quoted_name = '`' . str_replace('`', '``', $database) . '`';
    $lock_name = 'tu_sportshub_setup_' . substr(hash('sha256', $database), 0, 32);
    $lock = $db->prepare('SELECT GET_LOCK(:name, 30)');
    $lock->execute([':name' => $lock_name]);
    if ((int)$lock->fetchColumn() !== 1) {
        throw new RuntimeException('Database setup is busy. Please reload the page.');
    }

    try {
        try {
            $db->exec("USE $quoted_name");
        } catch (PDOException $e) {
            if ((int)($e->errorInfo[1] ?? 0) !== 1049) throw $e;
            $db->exec("CREATE DATABASE IF NOT EXISTS $quoted_name CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $db->exec("USE $quoted_name");
        }

        $tables = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('app_setup', $tables, true)) {
            $required = ['users', 'clubs', 'club_managers', 'sessions', 'registrations', 'refund_records'];
            if (!array_diff($required, $tables)) return;
            if ($tables) {
                throw new RuntimeException('Database has an incomplete or unrelated schema. Set DB_NAME in config/db.php to a new empty database.');
            }
            $db->exec('CREATE TABLE app_setup (id TINYINT PRIMARY KEY, completed TINYINT NOT NULL DEFAULT 0) ENGINE=InnoDB');
        }
        $db->exec('INSERT IGNORE INTO app_setup (id, completed) VALUES (1, 0)');
        if ((int)$db->query('SELECT completed FROM app_setup WHERE id = 1')->fetchColumn() === 1) return;

        $scripts = [];
        foreach (['schema', 'seed'] as $name) {
            $sql = file_get_contents(__DIR__ . '/../database/' . $name . '.sql');
            if ($sql === false) throw new RuntimeException('Missing database/' . $name . '.sql. Restore bundled database files and reload.');
            $scripts[$name] = preg_replace('/^(?:CREATE DATABASE[^\r\n]*|USE[^\r\n]*);\r?\n/m', '', $sql);
        }
        $db->exec($scripts['schema']);
        $db->beginTransaction();
        try {
            $db->exec($scripts['seed']);
            $db->exec('UPDATE app_setup SET completed = 1 WHERE id = 1');
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    } finally {
        $release = $db->prepare('SELECT RELEASE_LOCK(:name)');
        $release->execute([':name' => $lock_name]);
    }
}
