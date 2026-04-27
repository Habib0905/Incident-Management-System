<?php

namespace App\Services;

class LogNormalizationService
{
    public function normalize(array|string $payload): array
    {
        if (is_array($payload)) {
            return $this->normalizeJson($payload);
        }

        return $this->normalizeText($payload);
    }

    private function normalizeJson(array $data): array
    {
        $message = $this->extractField($data, 'message', 'Unknown error');
        $level = $this->extractField($data, 'level', null);
        $timestamp = $this->extractField($data, 'timestamp', null);
        $source = $this->extractField($data, 'source', null);

        if (!$level) {
            $level = $this->detectLogLevelFromText($message);
        }

        $normalizedLevel = $this->normalizeLogLevel($level);

        if (!$source) {
            $source = $this->detectSourceFromText($message);
        }

        return [
            'message' => $message,
            'log_level' => $normalizedLevel,
            'source' => $source,
            'timestamp' => $this->parseTimestamp($timestamp),
            'raw_payload' => $data,
        ];
    }

    private function extractField(array $data, string $field, $default)
    {
        $aliases = match ($field) {
            'message' => ['message', 'msg', '@m', 'text', 'log', 'description'],
            'level' => ['level', 'severity', 'log_level', 'loglevel', '@l', 'priority', 'log_level'],
            'timestamp' => ['timestamp', 'time', '@t', 'ts', 'datetime', 'date', 'created_at'],
            'source' => ['source', 'service', 'component', 'app', 'logger', 'channel', 'facility'],
            default => [$field],
        };

        foreach ($aliases as $alias) {
            if (isset($data[$alias]) && $data[$alias] !== '') {
                return $data[$alias];
            }
        }

        return $default;
    }

    private function normalizeText(string $text): array
    {
        $logLevel = $this->detectLogLevelFromText($text);
        $source = $this->detectSourceFromText($text);

        return [
            'message' => trim($text),
            'log_level' => $logLevel,
            'source' => $source,
            'timestamp' => now()->utc(),
            'raw_payload' => ['raw' => $text],
        ];
    }

    private function normalizeLogLevel(?string $level): ?string
    {
        if (!$level) {
            return null;
        }

        $level = strtolower($level);

        return match ($level) {
            'error', 'err' => 'error',
            'warning', 'warn' => 'warn',
            'info', 'information' => 'info',
            'debug', 'dbg' => 'debug',
            'critical', 'crit', 'fatal' => 'error',
            default => null,
        };
    }

    private function detectLogLevelFromText(string $text): ?string
    {
        $upperText = strtoupper($text);

        if (str_contains($upperText, 'CRITICAL') || str_contains($upperText, 'FATAL')) {
            return 'error';
        }
        if (str_contains($upperText, 'ERROR') || str_contains($upperText, 'FAILED') || str_contains($upperText, 'FAILURE')) {
            return 'error';
        }
        if (str_contains($upperText, 'WARN')) {
            return 'warn';
        }
        if (str_contains($upperText, 'INFO')) {
            return 'info';
        }
        if (str_contains($upperText, 'DEBUG')) {
            return 'debug';
        }

        return 'info';
    }

    private function detectSourceFromText(string $text): string
    {
        $lowerText = strtolower($text);

        if ($this->matchesAny($lowerText, [
            'kubelet error', 'pod failed', 'pod evicted', 'pod pending',
            'docker error', 'docker daemon error', 'containerd error',
            'crashloopbackoff', 'imagepullbackoff', 'image pull failed',
            'k8s error', 'kubernetes error', 'etcd error',
            'scheduler error', 'deployment failed', 'replicaset error',
            'helm error', 'pvc error', 'ingress error', 'crictl error',
            'node not ready', 'node out of',
            'container killed', 'container exited with error', 'container restart',
            'pod terminating', 'evicting pod',
            'crio error', 'podman error',
            'istio error', 'envoy error',
            'runc error', 'registry error',
            'cgroup memory limit', 'cgroup cpu limit',
        ])) {
            return 'container';
        }

        if ($this->matchesAny($lowerText, [
            'apache error', 'apache2 error',
            'httpd: error', 'httpd: warning',
            'suexec error', 'script not found',
            'httpd_worker error', 'mod_rewrite error', 'mod_ssl error',
            'apr_error', 'worker_mpm error',
            'apache segfault', 'apache bus error', 'apache child exited',
            'apache mutex error', 'apache lock error',
        ])) {
            return 'apache';
        }

        if ($this->matchesAny($lowerText, [
            'nginx error', 'upstream prematurely',
            'upstream timed out', 'no live upstreams', 'connect() failed',
            'upstream sent too big', 'client too large body',
            'worker process exiting', 'ngx_http error',
            'worker_rlimit_nofile exceeded', 'proxy_pass error',
            'fastcgi error', 'scgi error', 'uwsgi error',
            'recv() failed', 'send() failed',
            'request entity too large',
            'nginx upstream error', 'nginx backend error',
        ])) {
            return 'nginx';
        }

        if ($this->matchesAny($lowerText, [
            'redis error', 'redis timeout', 'redis connection refused',
            'redis connection error', 'redis connection lost',
            'redis connection timeout', 'redis cluster error',
            'redis sentinel error', 'redis maxmemory',
            'memcached error', 'memcached timeout', 'memcached connection error',
            'varnish error', 'varnish_backend_error',
            'cache miss rate high', 'cache miss rate', 'cache error',
            'cache expired unexpectedly', 'cache full', 'cache evicted',
            'cache invalidated', 'cache unavailable', 'cache connection error',
            'cache timeout', 'cache write failed', 'cache read failed',
            'cache_key_error', 'cache missed', 'cache eviction rate',
            'key not found in cache', 'key expired', 'key invalid',
            'flush failed', 'eviction rate high', 'eviction rate',
            'apcu error', 'wincache error', 'opcache error',
            'zend accelerator error',
            'ehcache error', 'jcache error',
        ])) {
            return 'cache';
        }

        if ($this->matchesAny($lowerText, [
            'sqlstate', 'sql error', 'sql syntax', 'sql query error',
            'postgres error', 'mysql error', 'mariadb error', 'mongodb error',
            'oracle error', 'sqlite error', 'mssql error',
            'database connection refused', 'database unavailable',
            'db connection refused', 'db pool exhausted',
            'elasticsearch error', 'solr error', 'influxdb error',
            'cassandra error', 'cockroachdb error', 'neo4j error',
            'sql timeout', 'query timeout', 'connection pool exhausted',
            'too many connections', 'max connections reached',
            'database deadlock', 'db deadlock', 'lock wait timeout',
            'constraint violation', 'foreign key constraint',
            'db error', 'db constraint', 'db lock', 'db timeout',
            'opensearch error', 'redshift error',
            'replication lag', 'database replication',
        ])) {
            return 'database';
        }

        if ($this->matchesAny($lowerText, [
            'ldap error', 'oauth error', 'oauth2 error',
            'jwt invalid', 'jwt error', 'jwt expired', 'jwt_error',
            'jwt validation failed', 'jwt signature',
            'session expired', 'session timeout', 'csrf error',
            'xss detected', 'sql injection',
            'authentication error', 'authentication failed',
            'authorization error', 'authorization failed',
            'saml error', 'openid error', 'kerberos error',
            'mfa error', 'otp error',
            'permission_error', 'brute_force', 'brute force',
            'ip_blocked', 'password_expired', 'account_suspended',
            'auth failed', 'auth error',
            'login failed', 'login error', 'login refused',
            'session invalid', 'session error', 'token expired',
            'token invalid', 'token error', 'invalid credentials',
            'wrong password', 'password mismatch',
            'access denied', 'permission denied',
            'account locked', 'account disabled',
            'ldap_bind error', 'ldap_search error', 'ntlm error',
            'apikey error', 'secret key invalid',
        ])) {
            return 'auth';
        }

        if ($this->matchesAny($lowerText, [
            'ec2 error', 'instance terminated unexpectedly', 'instance stopped unexpectedly',
            'aws error', 's3 error', 's3 upload failed', 's3 download failed',
            'dynamodb error', 'rds error', 'lambda error',
            'azure error', 'gcp error',
            'vpc error', 'elb error', 'alb error',
            'eks error', 'ecs task failed',
            'cloudformation error', 'cloudwatch error',
            'iam error', 'sts_error', 's3_error', 's3_timeout',
            'lambda_timeout', 'backup_error',
            'lightsail error', 'heroku error', 'render error', 'vercel error',
            'netlify error', 'elastic beanstalk error',
            'autoscaling error', 'asg error',
            'fargate error', 'batch error',
            'serverless error',
        ])) {
            return 'cloud';
        }

        if ($this->matchesAny($lowerText, [
            'queue full', 'queue timeout', 'queue error',
            'job failed', 'job timeout', 'job retry exhausted',
            'worker crashed', 'worker timeout',
            'dead letter', 'max retries exceeded', 'retry exhausted',
            'sidekiq error', 'celery error', 'resque error',
            'rabbitmq error', 'rabbitmq connection', 'rabbitmq',
            'beanstalkd error', 'gearman error',
            'async error', 'cron error', 'batch failed',
            'bullmq error', 'sqs error', 'google pubsub error',
            'nsq error', 'nats error', 'zeromq error',
            'kafka producer error', 'kafka consumer error', 'kafka',
            'message error', 'consumer error',
            'publisher error', 'partition error',
            'offset error',
            'message rejected', 'message lost',
            'backoff exhausted', 'message queue error',
            'task failed', 'task timeout',
            'scheduled task failed', 'scheduled job failed',
            'worker process error',
            'job queue error', 'delayed job error',
            'activemq error', 'artemis error',
            'qpid error', 'stomp error',
        ])) {
            return 'queue';
        }

        if ($this->matchesAny($lowerText, [
            'smtp error', 'smtp connection refused', 'smtp connection',
            'mail delivery failed', 'mail bounced', 'email rejected',
            'sendgrid error', 'ses error', 'mailgun error',
            'imap error', 'pop3 error',
            'spf error', 'dkim error', 'dmarc error',
            'mandrill error', 'sendinblue error', 'mailchimp error',
            'email_service error', 'email error',
            'smtp_timeout', 'dkim_error', 'spf_error', 'recipient_error',
            'mta error', 'message queue full',
            'email too large', 'attachment error',
            'postmark error',
            'mail error', 'mailer error',
            'smtp auth error',
        ])) {
            return 'email';
        }

        if ($this->matchesAny($lowerText, [
            'api error', 'api timeout', 'api rate limit exceeded',
            'api rate limit',
            'rest error', 'graphql error',
            'endpoint error', 'route error', 'middleware error',
            '500 internal server', '502 bad gateway', '504 gateway timeout',
            '503 service unavailable',
            'webhook failed', 'callback failed', 'api gateway error',
            'http_502', 'http_503', 'rate_limit exceeded', 'rate limit exceeded',
            'throttling', 'payload_too_large', 'invalid_json',
            'schema_validation error', 'swagger_error', 'openapi_error',
            'cors_error', 'timeout_error',
            'bad request', 'method not allowed',
            'internal error', 'gateway error',
            'validation error',
            'request error', 'response error',
            'invalid request', 'malformed request',
            'unknown endpoint', 'undefined route',
        ])) {
            return 'api';
        }

        if ($this->matchesAny($lowerText, [
            'file not found', 'file too large', 'file error',
            'file missing', 'upload failed', 'download failed',
            'read error', 'write error',
            'path not found', 'invalid path',
            'parse error', 'encoding error',
            'invalid format', 'unsupported format',
            'max size exceeded', 'stream error',
            'pdf error', 'image error', 'json parse error',
            'xml parse error', 'csv parse error', 'yaml parse error',
            'config error',
            'backup failed', 'restore failed', 'export failed', 'import failed',
            'file_lock error', 'file_permission error', 'directory_error',
            'path_traversal', 'archive_error',
            'mime_type_error',
            'file not accessible', 'disk read error', 'disk write error',
            'unable to open', 'cannot open',
            'failed to open', 'could not open',
            'ENOENT', 'EACCES', 'EISDIR', 'ENOTDIR',
            'EBADF', 'EINVAL', 'EIO',
            'file upload failed', 'file download failed',
            'file size limit exceeded',
        ])) {
            return 'file';
        }

        if ($this->matchesAny($lowerText, [
            'kernel panic', 'kernel: error', 'panic:',
            'segmentation fault', 'segfault', 'core dumped',
            'oom killer', 'oom:', 'out of memory', 'kill process',
            'memory exhausted', 'memory allocation failed',
            'fatal error', 'fatal:',
            'signal 9', 'sigkill', 'sigsegv',
            'memory leak detected',
            'cpu spike detected', 'cpu usage', 'load spike',
            'inode full', 'inode limit reached', 'mount error',
            'unmount error', 'process zombie', 'process orphaned',
            'service crash', 'daemon crash', 'disk error',
            'io error', 'temperature critical', 'thermal throttling',
            'hardware error',
            'system error', 'process killed',
            'process failed', 'service failed', 'service stopped unexpectedly',
            'daemon error', 'daemon failed', 'worker error',
            'worker failed', 'thread error',
            'init failed', 'bootstrap failed', 'startup failed',
            'disk full', 'no space left', 'storage full',
            'inodes exhausted', 'too many open files',
            'file descriptor limit', 'ulimit exceeded',
            'resource exhausted', 'resource limit exceeded',
            'coredump', 'dumped', 'killed',
            'sigabrt', 'sigbus', 'sigill', 'sigterm',
            'fpe', 'float error', 'bus error', 'alignment error',
            'page fault', 'vm fault', 'bad page',
        ])) {
            return 'system';
        }

        if ($this->matchesAny($lowerText, [
            'dns error', 'dns lookup failed', 'dns_error',
            'ssl certificate error', 'ssl certificate expiring',
            'ssl handshake failed', 'tls handshake failed',
            'socket error', 'socket timeout', 'socket closed unexpectedly',
            'network unreachable', 'network timeout',
            'host unreachable', 'no route to host',
            'ssl_verify failed', 'certificate verify failed',
            'port closed', 'port blocked', 'firewall blocked',
            'packet loss detected',
            'vpn error', 'tunnel error',
            'resolve error', 'ssl error', 'tls error',
            'connection_refused', 'host_unreachable',
            'network error', 'network connection failed',
            'connection refused', 'connection failed',
            'connection timeout', 'connection reset',
            'connection closed unexpectedly', 'broken pipe',
            'http error', 'http timeout',
            'tcp error', 'udp error',
            'remote error', 'remote connection failed',
            'network connectivity lost', 'eof error',
            'curl error',
            'request timeout', 'operation timed out',
            'timeout exceeded', 'timeout after',
            'connect error', 'bind error',
            'listen error', 'accept error',
            'proxy error', 'proxy timeout',
            'gateway error', 'route error',
        ])) {
            return 'network';
        }

        return 'unknown';
    }

    private function matchesAny(string $text, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (str_contains($text, $pattern)) {
                return true;
            }
        }
        return false;
    }

    private function parseTimestamp(?string $timestamp): \Carbon\Carbon
    {
        if (!$timestamp) {
            return now()->utc();
        }

        try {
            return \Carbon\Carbon::parse($timestamp);
        } catch (\Exception $e) {
            return now()->utc();
        }
    }
}
