<?php

use PHPUnit\Framework\TestCase;

abstract class DatabaseTestCase extends TestCase {
    protected PDO $pdo;

    protected function setUp(): void {
        if (!testDbEnabled()) {
            $this->markTestSkipped('Set TEST_DB_ENABLED=1 and TEST_DB_* env vars to run integration tests.');
        }

        mapTestDbEnvToRuntime();
        $this->pdo = $this->createPdoFromTestEnv();
        $this->initializeSchema();
        $this->truncateTables();
    }

    protected function createPdoFromTestEnv(): PDO {
        $host = getenv('TEST_DB_HOST') ?: getenv('DB_HOST');
        $name = getenv('TEST_DB_NAME') ?: getenv('DB_NAME');
        $user = getenv('TEST_DB_USER') ?: getenv('DB_USER');
        $pass = getenv('TEST_DB_PASS') ?: getenv('DB_PASS');

        if (!$host || !$name || !$user) {
            $this->markTestSkipped('Missing TEST_DB_HOST/TEST_DB_NAME/TEST_DB_USER configuration.');
        }

        return new PDO(
            "mysql:host={$host};dbname={$name};charset=utf8mb4",
            $user,
            $pass ?: '',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    }

    protected function initializeSchema(): void {
        $schema = file_get_contents(dirname(__DIR__, 2) . '/setup/schema.sql');
        $this->pdo->exec($schema);
    }

    protected function truncateTables(): void {
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        $this->pdo->exec('TRUNCATE TABLE login_attempts');
        $this->pdo->exec('TRUNCATE TABLE flight_segments');
        $this->pdo->exec('TRUNCATE TABLE trip_directions');
        $this->pdo->exec('TRUNCATE TABLE trips');
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    }

    protected function runPhpScriptWithJsonInput(string $scriptPath, array $payload): array {
        $command = 'php ' . escapeshellarg($scriptPath);
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptorSpec, $pipes, dirname(__DIR__, 2), $_ENV);
        if (!is_resource($process)) {
            $this->fail('Failed to launch PHP helper process.');
        }

        fwrite($pipes[0], json_encode($payload));
        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);
        $this->assertSame(0, $exitCode, "Helper script failed: {$stderr}");

        $decoded = json_decode($stdout, true);
        $this->assertIsArray($decoded, "Invalid JSON output: {$stdout}");
        return $decoded;
    }
}

