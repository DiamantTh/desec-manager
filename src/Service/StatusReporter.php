<?php

namespace App\Service;

use App\Database\DatabaseConnection;
use Doctrine\DBAL\Connection;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Throwable;

class StatusReporter
{
    private ?array $config;
    private SystemHealthService $systemHealth;
    private ?array $aggregateMetrics = null;
    private const STATUS_VALUES = [
        'critical' => 0,
        'degraded' => 1,
        'ok' => 2,
    ];

    public function __construct(?array $config)
    {
        $this->config = $config;
        $this->systemHealth = new SystemHealthService();
    }

    public function generate(): array
    {
        $checks = [];

        $checks['configuration'] = $this->checkConfiguration();
        $checks['cache'] = $this->checkCaches();
        $checks['database'] = $this->checkDatabase();
        $checks['desecApi'] = $this->checkDeSecApi();

        $overall = 'ok';
        foreach ($checks as $check) {
            if (($check['status'] ?? 'ok') === 'critical') {
                $overall = 'critical';
                break;
            }
            if ($overall !== 'critical' && ($check['status'] ?? 'ok') === 'degraded') {
                $overall = 'degraded';
            }
        }

        return [
            'timestamp' => gmdate('c'),
            'application' => [
                'name' => $this->config['application']['name'] ?? 'deSEC Manager',
                'environment' => $this->config['application']['environment'] ?? null,
            ],
            'overall_status' => $overall,
            'checks' => $checks,
            'metrics' => $this->aggregateMetrics,
        ];
    }

    public function renderPrometheus(array $report): string
    {
        $lines = [];
        $lines[] = '# HELP desec_status Status of application checks (0=critical,1=degraded,2=ok).';
        $lines[] = '# TYPE desec_status gauge';

        foreach ($report['checks'] as $name => $check) {
            $status = $this->statusValue($check['status'] ?? 'ok');
            $label = $this->formatPrometheusLabel($name);
            $message = $check['message'] ?? '';
            $messageLabel = $message !== '' ? ',message="' . $this->escapeLabel($message) . '"' : '';
            $lines[] = sprintf('desec_status{check="%s"%s} %d', $label, $messageLabel, $status);
        }

        $overall = $this->statusValue($report['overall_status'] ?? 'ok');
        $lines[] = '# HELP desec_overall Overall application status.';
        $lines[] = '# TYPE desec_overall gauge';
        $lines[] = sprintf('desec_overall %d', $overall);

        if (!empty($report['metrics']) && is_array($report['metrics'])) {
            $lines[] = '# HELP desec_metric Aggregated application metrics.';
            $lines[] = '# TYPE desec_metric gauge';
            foreach ($report['metrics'] as $metric => $value) {
                if (!is_numeric($value)) {
                    continue;
                }
                $lines[] = sprintf('desec_metric{metric="%s"} %s', $this->formatPrometheusLabel($metric), (string) $value);
            }
        }

        $lines[] = ''; // trailing newline
        return implode("\n", $lines);
    }

    private function checkConfiguration(): array
    {
        if ($this->config === null) {
            return [
                'status' => 'critical',
                'message' => 'config/config.php nicht gefunden – bitte install.php ausführen.',
            ];
        }

        return [
            'status' => 'ok',
        ];
    }

    private function checkCaches(): array
    {
        $cacheStatus = $this->systemHealth->getCacheStatus();
        $issues = array_filter($cacheStatus, static fn(array $item) => !empty($item['message']));
        $status = 'ok';
        if (!empty($issues)) {
            $status = 'degraded';
        }

        return [
            'status' => $status,
            'details' => $cacheStatus,
        ];
    }

    private function checkDatabase(): array
    {
        try {
            $connection = DatabaseConnection::getConnection();
            $this->assertConnection($connection);
            $this->aggregateMetrics = $this->collectAggregateMetrics($connection);
            return [
                'status' => 'ok',
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'critical',
                'message' => $e->getMessage(),
            ];
        }
    }

    private function collectAggregateMetrics(Connection $connection): array
    {
        $metrics = [];

        try {
            $metrics['domains_total'] = (int) $connection->executeQuery('SELECT COUNT(*) FROM domains')->fetchOne();
        } catch (Throwable $e) {
            // ignore table missing
        }

        try {
            $metrics['api_keys_total'] = (int) $connection->executeQuery('SELECT COUNT(*) FROM api_keys')->fetchOne();
        } catch (Throwable $e) {
            // ignore table missing
        }

        return $metrics;
    }

    private function assertConnection(Connection $connection): void
    {
        $connection->executeQuery('SELECT 1')->fetchOne();
    }

    private function checkDeSecApi(): array
    {
        if ($this->config === null) {
            return [
                'status' => 'degraded',
                'message' => 'Keine Konfiguration vorhanden – API nicht geprüft.',
            ];
        }

        $client = new Client([
            'base_uri' => 'https://desec.io/api/v1/',
            'timeout' => 5,
            'http_errors' => false,
        ]);

        try {
            $response = $client->get('');
            $code = $response->getStatusCode();
            $status = $code >= 200 && $code < 400 ? 'ok' : 'degraded';
            return [
                'status' => $status,
                'http_status' => $code,
            ];
        } catch (GuzzleException $exception) {
            return [
                'status' => 'critical',
                'message' => $exception->getMessage(),
            ];
        }
    }

    private function statusValue(string $status): int
    {
        return self::STATUS_VALUES[$status] ?? self::STATUS_VALUES['critical'];
    }

    private function formatPrometheusLabel(string $value): string
    {
        return preg_replace('/[^a-zA-Z0-9_:\-\.]/', '_', $value);
    }

    private function escapeLabel(string $value): string
    {
        $value = str_replace(['\\', "\n", '"'], ['\\\\', '\\n', '\\"'], $value);
        return $value;
    }
}
