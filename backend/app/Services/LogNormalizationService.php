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
        return [
            'message' => $data['message'] ?? 'Unknown error',
            'log_level' => $this->normalizeLogLevel($data['level'] ?? null),
            'source' => $this->detectSourceFromText($data['message'] ?? ''),
            'timestamp' => $this->parseTimestamp($data['timestamp'] ?? null),
            'raw_payload' => $data,
        ];
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
            'kubelet error', 'kubelet:', 'pod failed', 'pod evicted', 'pod pending',
            'docker error', 'docker daemon', 'docker:', 'containerd error',
            'crashloopbackoff', 'imagepullbackoff', 'image pull failed',
            'k8s error', 'kubernetes error', 'kube-', 'etcd error',
            'scheduler error', 'deployment failed', 'replicaset error',
            'helm error', 'pvc error', 'ingress error', 'crictl error',
            'node not ready', 'node out of', 'cordon', 'drain',
            'container killed', 'container exited', 'container restart',
            'pod terminating', 'pod deleting', 'evicting pod',
            'crio error', 'containerd:', 'podman error',
            'docker-compose', 'docker swarm', 'kustomize',
            'istio error', 'envoy error', 'service mesh',
            'kubeadm', 'minikube', 'kubeproxy',
            'runc error', 'registry error', 'pull image',
            'create container', 'start container', 'stop container',
            'devicemapper', 'overlay2', 'cgroup', 'namespace',
        ])) {
            return 'container';
        }

        if ($this->matchesAny($lowerText, [
            'ec2 error', 'ec2:', 'instance terminated', 'instance stopped',
            'aws error', 'aws:', 's3 error', 's3:',
            'dynamodb', 'rds error', 'lambda error',
            'azure error', 'azure:', 'gcp error', 'gcp:',
            'cloudformation', 'cloudwatch', 'route53',
            'vpc error', 'elb error', 'alb error',
            'eks cluster', 'eks error', 'ecs task',
            'cloud provider', 'cloud platform',
            'digitalocean', 'linode', 'vultr',
            'cloudflare', 'akamai',
            'terraform', 'ansible',
            'iam_policy', 'sts_error', 'iam error',
            's3_error', 's3_timeout',
            'lightsail', 'heroku', 'render', 'vercel',
            'netlify', 'elastic beanstalk',
            'aws', 'azure', 'gcp', 'cloud',
            'ec2', 's3', 'rds', 'lambda',
            'vpc', 'subnet', 'security group',
            'load balancer', 'target group',
            'health check', 'asg', 'autoscaling',
            'fargate', 'batch', 'sam',
            'serverless',
        ])) {
            return 'cloud';
        }

        if ($this->matchesAny($lowerText, [
            'sqlstate', 'sql error', 'sql syntax', 'sql query error',
            'postgresql', 'postgres error', 'postgres:', 'postgresq',
            'mysql error', 'mysql:', 'mariadb', 'mongodb', 'mongo',
            'oracle error', 'sqlite error', 'mssql',
            'database connection refused', 'database unavailable',
            'db connection refused', 'db pool',
            'elasticsearch', 'solr', 'timeseries',
            'influxdb', 'cassandra', 'cockroachdb',
            'firestore', 'dynamodb', 'neo4j',
            'sql timeout', 'query timeout', 'connection pool exhausted',
            'too many connections', 'max connections',
            'database deadlock', 'db deadlock', 'lock wait timeout',
            'constraint violation', 'unique constraint', 'foreign key',
            'db error', 'db constraint', 'db lock', 'db timeout',
            'mysql_connect', 'postgres_connect', 'sqlstate',
            'elastic database', 'opensearch', 'redshift',
            'postgres', 'mysql', 'mariadb',
        ])) {
            return 'database';
        }

        if ($this->matchesAny($lowerText, [
            'ldap error', 'ldap:', 'oauth error', 'oauth:', 'oauth2',
            'jwt invalid', 'jwt error', 'jwt expired', 'jwt_error',
            'jwt token', 'jwt validation', 'jwt signature',
            'session expired', 'session timeout', 'csrf token',
            'csrf error', 'xss detected', 'xss:', 'sql injection',
            'authentication error', 'authentication failed',
            'authorization error', 'authorization failed',
            'saml error', 'openid error', 'kerberos', 'ntlm',
            'basic auth', 'bearer token', 'mfa error', 'otp error',
            'permission_error', 'brute_force', 'ip_blocked',
            'password_expired', 'account_suspended', 'captcha',
            'auth failed', 'auth error', 'auth:',
            'login failed', 'login error', 'login refused',
            'session invalid', 'session error', 'token expired',
            'token invalid', 'token error', 'invalid credentials',
            'wrong password', 'password mismatch', 'unauthorized',
            'access denied', 'permission denied', 'forbidden',
            'account locked', 'account disabled', 'rate limit',
            'authenticatin', 'authoriz', 'loginfailed', 'authetication',
            'ldap_bind', 'ldap_search', 'gssapi', 'ntlm error',
            'access_token', 'refresh_token', 'id_token',
            'api_key', 'apikey error', 'secret key',
        ])) {
            return 'auth';
        }

        if ($this->matchesAny($lowerText, [
            'apache error', 'apache:', 'apache2:',
            'httpd: error', 'httpd: warning',
            'apache error', 'suexec', 'script not found',
            'httpd_worker', 'mod_rewrite', 'mod_ssl',
            'apr_error', 'worker_mpm', 'prefork',
            '.htaccess',
            'apache segfault', 'apache bus error', 'apache child exited',
            'apache parent child', 'apache scoreboard',
            'apache mutex', 'apache lock',
            'apache access', 'apache log',
            'apache worker process',
        ])) {
            return 'apache';
        }

        if ($this->matchesAny($lowerText, [
            'kernel panic', 'kernel:', 'panic:', 'panic',
            'segmentation fault', 'segfault', 'core dumped',
            'oom killer', 'oom:', 'out of memory', 'kill process',
            'memory exhausted', 'memory allocation failed',
            'fatal error', 'fatal:', 'abort', 'abort',
            'signal 9', 'sigkill', 'sigsegv',
            'syslog', 'dmesg', 'memory leak',
            'cpu spike', 'load spike', 'inode',
            'inode full', 'inode limit', 'mount error',
            'unmount error', 'process zombie', 'process orphaned',
            'service crash', 'daemon crash', 'disk error',
            'io error', 'temperature', 'thermal',
            'hardware error', 'memory', 'cpu', 'disk', 'storage',
            'system error', 'system:', 'process killed',
            'process failed', 'service failed', 'service stopped',
            'daemon error', 'daemon failed', 'worker error',
            'worker failed', 'thread error', 'thread:',
            'init failed', 'bootstrap failed', 'startup failed',
            'disk full', 'no space left', 'storage full',
            'inodes', 'too many open files',
            'file descriptor', 'ulimit', 'memory usage',
            'cpu usage', 'disk usage', 'load average',
            'swap', 'heap', 'systemd:', 'eth0:',
            'resource exhausted', 'resource limit',
            'coredump', 'cored', 'dumped', 'killed',
            'sigabrt', 'sigbus', 'sigill', 'sigterm',
            'fpe', 'float error', 'illop', 'illegal',
            'bus error', 'alignment error', 'page fault',
            'vm fault', 'pfault', 'bad page',
            'generic segfault', 'generic bus error', 'non-apache segfault',
        ])) {
            return 'system';
        }

        if ($this->matchesAny($lowerText, [
            'cache', 'memcached', 'varnish',
            'opcache', 'apcu', 'xcache', 'wincache',
            'cache miss', 'cache error', 'cache expired',
            'cache full', 'cache evicted', 'cache invalidated',
            'cache unavailable', 'cache read', 'cache write',
            'redis_cluster', 'redis_sentinel',
            'memcached_error', 'varnish_error',
            'nginx_cache', 'ehcache', 'jcache',
            'cache_key_error', 'cache hit', 'cache missed',
            'key not found', 'key expired', 'key invalid',
            'cache key', 'invalid key', 'expired key',
            'flush failed', 'eviction policy',
            'LRU', 'FIFO', 'LFU',
            'redis timeout', 'redis error',
            'memcached timeout', 'memcached error',
            'cache timeout', 'cache connection',
            'redis:', 'redis error', 'redis connection',
            'apc', 'apcu', 'wincache',
            'opcache', 'zend', 'accelerator',
        ])) {
            return 'cache';
        }

        if ($this->matchesAny($lowerText, [
            'dns error', 'dns lookup', 'dns:', 'dns_error',
            'ssl certificate', 'ssl handshake', 'tls handshake',
            'socket error', 'socket timeout', 'socket closed',
            'network unreachable', 'network timeout',
            'host unreachable', 'no route to host',
            'ssl_verify', 'certificate verify',
            'icmp', 'ping', 'traceroute',
            'port closed', 'port blocked', 'firewall',
            'iptables', 'bandwidth', 'latency', 'packet loss',
            'vpn error', 'tunnel error', 'dns error',
            'resolve error', 'ssl error', 'tls error',
            'connection_refused', 'host_unreachable',
            'network error', 'network connection',
            'connection refused', 'connection failed',
            'connection timeout', 'connection reset',
            'connection closed', 'broken pipe',
            'http error', 'http timeout', 'http:',
            'tcp error', 'tcp:', 'udp error',
            'remote error', 'remote connection',
            'network connectivity', 'eof error',
            'read error', 'write error', 'curl error',
            'request timeout', 'operation timed out',
            'timeout exceeded', 'timeout after',
            'socket', 'connect error', 'bind error',
            'listen error', 'accept error',
            'sendmail', 'mailq', 'postqueue',
            'smtp error', 'smtp:', 'pop3', 'imap',
            'proxy error', 'proxy timeout',
            'gateway error', 'route error',
        ])) {
            return 'network';
        }

        if ($this->matchesAny($lowerText, [
            'queue full', 'queue timeout', 'queue error',
            'job failed', 'job timeout', 'job retry',
            'worker error', 'worker crashed', 'worker timeout',
            'dead letter', 'max retries', 'retry exhausted',
            'sidekiq error', 'celery error', 'resque error',
            'rabbitmq', 'beanstalkd', 'gearman',
            'async error', 'async:', 'cron error', 'batch failed',
            'bullmq', 'bull', 'bee', 'sqs', 'google pubsub',
            'nsq', 'nats', 'zeromq',
            'kafka producer', 'kafka consumer',
            'job timeout', 'worker timeout',
            'message error', 'consumer error',
            'publisher error', 'partition error',
            'offset error', 'queue',
            'async', 'batch', 'cron',
            'redis queue', 'kafka',
            'delayed job', 'background',
            'message rejected', 'message lost',
            'backoff', 'retry', 'attempt failed',
            'exhausted', 'message queue',
            'task failed', 'task timeout',
            'scheduled task', 'scheduled job',
            'background processing', 'worker process',
            'job queue', 'delayed job',
            'Activemq', 'activemq', 'artemis',
            'qpid', 'Stomp', 'stomp',
        ])) {
            return 'queue';
        }

        if ($this->matchesAny($lowerText, [
            'ec2 error', 'ec2:', 'instance terminated', 'instance stopped',
            'aws error', 'aws:', 's3 error', 's3:',
            'dynamodb', 'rds error', 'lambda error',
            'azure error', 'azure:', 'gcp error', 'gcp:',
            'cloudformation', 'cloudwatch', 'route53',
            'vpc error', 'elb error', 'alb error',
            'eks cluster', 'eks error', 'ecs task',
            'cloud provider', 'cloud platform',
            'digitalocean', 'linode', 'vultr',
            'cloudflare', 'akamai',
            'terraform', 'ansible', 'cloudformation',
            'iam_policy', 'sts_error', 'iam error',
            's3_error', 's3_timeout', 'glacier',
            'backup_error', 'snowball', 'datasync',
            'eks_cluster', 'ecs_cluster',
            'lambda_timeout', 'cloudwatch_error',
            'lightsail', 'heroku', 'render', 'vercel',
            'netlify', 'elastic beanstalk',
            'cloud', 'aws', 'azure', 'gcp',
            'ec2', 's3', 'rds', 'lambda',
            'instance', 'server terminated',
            'vpc', 'subnet', 'security group',
            'load balancer', 'target group',
            'health check', 'asg', 'autoscaling',
            'route53', 'cloudwatch', 'iam',
            'fargate', 'batch', 'sam',
            'serverless', 'lambda',
        ])) {
            return 'cloud';
        }

        if ($this->matchesAny($lowerText, [
            'nginx error', 'nginx:', 'upstream prematurely',
            'upstream timed out', 'no live upstreams', 'connect() failed',
            'upstream sent too big', 'client too large body',
            'worker process exiting', 'ngx_http',
            'worker_rlimit_nofile', 'proxy_pass', 'fastcgi_pass',
            'upstream_keepalive', 'ngx_var', 'access_log_error',
            'error_log', 'upstream error', 'upstream timeout',
            'upstream connection', 'upstream header',
            'fastcgi error', 'scgi error', 'uwsgi error',
            'recv() failed', 'send() failed',
            'client_body', 'client_header',
            'request entity too large',
            'proxy error', 'uwsgi',
            'gunicorn', 'unicorn', 'puma', 'passenger',
            'nginx upstream', 'nginx backend',
        ])) {
            return 'nginx';
        }

        if ($this->matchesAny($lowerText, [
            'apache error', 'apache:', 'apache2:',
            'httpd: error', 'httpd: warning',
            'apache error', 'suexec', 'script not found',
            'httpd_worker', 'mod_rewrite', 'mod_ssl',
            'apr_error', 'worker_mpm', 'prefork',
            '.htaccess',
            'apache segfault', 'apache bus error', 'apache child exited',
            'apache parent child', 'apache scoreboard',
            'apache mutex', 'apache lock',
            'apache access', 'apache log',
        ])) {
            return 'apache';
        }

        if ($this->matchesAny($lowerText, [
            'api error', 'api timeout', 'api rate limit',
            'rest error', 'graphql error', 'graphql:',
            'endpoint error', 'route error', 'middleware error',
            '500 internal server', '502 bad gateway', '504 gateway timeout',
            '503 service unavailable', '503',
            '400 bad request', '404 not found', '405 method not allowed',
            'webhook failed', 'callback failed', 'api gateway error',
            'http_client', 'axios', 'rest_api', 'soap_api',
            'http_502', 'http_503', 'rate_limit', 'throttling',
            'payload_too_large', 'invalid_json',
            'schema_validation', 'swagger_error', 'openapi_error',
            'cors_error', 'timeout_error',
            'api', 'rest', 'graphql', 'endpoint',
            'http request', 'http response',
            'bad request', 'not found', 'method not allowed',
            'internal error', 'gateway error',
            'webhook', 'callback', 'integration',
            'pagination', 'serialization', 'validation error',
            'request error', 'response error',
            'invalid request', 'malformed request',
            'unknown endpoint', 'undefined route',
            '405', '406', '408', '409', '410', '429',
            '422', '421', '413', '414', '415', '416',
        ])) {
            return 'api';
        }

        if ($this->matchesAny($lowerText, [
            'smtp error', 'smtp:', 'mail delivery failed',
            'mail bounced', 'email rejected', 'email:',
            'sendgrid error', 'ses error', 'mailgun error',
            'imap error', 'pop3 error', 'SPF', 'DKIM', 'DMARC',
            'mandrill', 'sendinblue', 'mailchimp',
            'email_service', 'email error',
            'deferred', 'delayed', 'smtp_timeout',
            'dkim_error', 'spf_error', 'recipient_error',
            'mta error', 'message queue full',
            'email too large', 'attachment error',
            'bounce', 'rejected', 'attachment',
            'email', 'mail', 'smtp', 'imap', 'pop3',
            'sendgrid', 'ses', 'mailgun', 'postmark',
            'mail error', 'mailer error',
            'smtp connect', 'smtp auth',
        ])) {
            return 'email';
        }

        if ($this->matchesAny($lowerText, [
            'file not found', 'file too', 'file error',
            'file missing', 'upload failed', 'download failed',
            'read error', 'write error', 'permission denied',
            'path not found', 'invalid path',
            'parse error', 'parse:', 'encoding error',
            'invalid format', 'unsupported format',
            'max size exceeded', 'temp file', 'stream error',
            'checksum', 'md5', 'sha',
            'pdf error', 'image error', 'json parse',
            'xml parse', 'csv parse', 'yaml parse',
            'config error', 'log file',
            'backup failed', 'restore failed', 'export', 'import',
            's3_download', 's3_upload', 'gcs', 'azure_blob',
            'temp_file', 'temp_dir', 'file_lock',
            'file_permission', 'directory_error',
            'path_traversal', 'archive_error',
            'mime_type_error', 'filetype',
            's3 upload', 's3 download',
            'blob storage', 'file storage',
            'file not accessible', 'disk read', 'disk write',
            'disk error', 'io error',
            'input file', 'output file',
            'unable to open', 'cannot open',
            'failed to open', 'could not open',
            'ENOENT', 'EACCES', 'EISDIR', 'ENOTDIR',
            'EBADF', 'EINVAL', 'EIO',
            'file upload', 'upload file', 'file download',
            'file too large', 'file size limit',
        ])) {
            return 'file';
        }

if ($this->matchesAny($lowerText, [
            'cache', 'redis connection', 'memcached', 'varnish',
            'opcache', 'apcu', 'xcache', 'wincache',
            'cache miss', 'cache error', 'cache expired',
            'cache full', 'cache evicted', 'cache invalidated',
            'cache unavailable', 'cache read', 'cache write',
            'redis_cluster', 'redis_sentinel',
            'memcached_error', 'varnish_error',
            'nginx_cache', 'ehcache', 'jcache',
            'cache_key_error', 'cache hit', 'cache missed',
            'key not found', 'key expired', 'key invalid',
            'cache key', 'invalid key', 'expired key',
            'flush failed', 'eviction policy',
            'LRU', 'FIFO', 'LFU',
            'redis timeout', 'redis error',
            'memcached timeout', 'memcached error',
            'cache timeout', 'cache connection',
        ])) {
            return 'cache';
        }

        if ($this->matchesAny($lowerText, [
            'database', 'db ', 'db:', 'sql',
            'db connection', 'db error', 'db deadlock',
            'connection pool', 'too many connections',
            'max connections', 'database error',
        ])) {
            return 'database';
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