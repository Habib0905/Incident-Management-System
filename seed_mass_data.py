#!/usr/bin/env python3
"""
Mass Data Seeder using CSV + PostgreSQL COPY
Seeds 1,000,000 logs + ~5,000 incidents + timeline events.
Approach: Generate CSV files → COPY into DB → Link via SQL
Run time: ~30-60 seconds.
"""

import csv
import os
import random
import tempfile
import time
from datetime import datetime, timedelta, timezone

import psycopg2
from psycopg2.extras import execute_values

DB_CONFIG = {
    "dbname": "logsystem",
    "user": "postgres",
    "password": "postgres",
    "host": "127.0.0.1",
    "port": 5432,
}

TARGET_LOGS = 1_000_000
TARGET_INCIDENTS = 5_000
TIME_SPAN_DAYS = 30

MESSAGE_POOLS = {
    "database": [
        "Connection pool exhausted - max connections reached",
        "Deadlock detected on table users - transaction rolled back",
        "Replication lag exceeded 30s on replica db-replica-01",
        "Slow query: SELECT * FROM orders WHERE ... (took 12.5s)",
        "SQLSTATE[HY000]: General error: 1045 Access denied",
        "Too many connections: max_connections=500 reached",
        "Connection refused to PostgreSQL primary at 10.0.0.5:5432",
        "Query timeout exceeded: statement canceled after 30s",
        "FATAL: too many connections for role app_user",
        "ERROR: relation sessions does not exist at character 15",
        "Connection lost to primary database server - failover initiated",
        "Write conflict detected on table payments - retrying",
        "Checkpoint not complete: could not flush dirty data",
        "WAL archiving failed: archive command failed with exit code 1",
        "Index scan on users_idx took 8.2s - consider REINDEX",
        "Connection pool utilization at 95% (190/200 in use)",
        "Database disk usage at 88% on /var/lib/postgresql/data",
        "Vacuum process killed: autovacuum: vacuuming public.logs",
        "Lock wait timeout exceeded: try restarting transaction",
        "FATAL: the database system is shutting down",
    ],
    "nginx": [
        "upstream timed out (110: Connection timed out) while connecting to upstream 10.0.0.5:8080",
        "no live upstreams while connecting to upstream backend_pool",
        "client intended to send too large body: 52428800 bytes",
        "recv() failed (104: Connection reset by peer) while reading response header",
        "open() /var/www/html/missing-page failed (2: No such file or directory)",
        "worker process 4521 exited with fatal code 1 and restarted",
        "upstream prematurely closed connection while reading response header",
        "SSL_do_handshake() failed: ssl3_read_bytes:sslv3 alert handshake failure",
        "connect() failed (111: Connection refused) while connecting to upstream",
        "client closed connection while waiting for request",
        "upstream sent too big header while reading response header from upstream",
        'limiting requests, excess: 150.5 by zone "api_limit"',
        "upstream server temporarily disabled while connecting to upstream",
        '10.0.0.15 - - [29/Apr/2026:14:30:00 +0000] "GET /api/payments HTTP/1.1" 502',
        "worker_connections are not enough while connecting to upstream",
        'cache file "/var/cache/nginx/a3f8b2c1" has md5 collision',
        "SSL certificate problem: unable to get local issuer certificate",
        "upstream response is buffered to a temporary file /var/cache/nginx/proxy_temp/1",
        '10.0.0.22 - - [29/Apr/2026:14:30:00 +0000] "POST /api/orders HTTP/1.1" 504',
        "bind() to 0.0.0.0:80 failed (98: Address already in use)",
    ],
    "apache": [
        "Apache segfault in mod_ssl worker process (PID 3421)",
        "AH00124: Request exceeded the limit of 10 internal redirects",
        "AH00491: caught SIGTERM, shutting down",
        "mod_rewrite: maximum number of internal redirects reached",
        "AH01630: client denied by server configuration: /var/www/html/admin",
        "AH01071: Got error 'PHP message: Out of memory (allocated 256MB)'",
        "worker process 8821 still did not exit, sending a SIGKILL",
        "AH00558: Could not reliably determine the server FQDN",
        "mod_fcgid: process 4412 exit (communication error), return code: 1",
        "AH01276: Cannot serve directory /var/www/html/uploads/: No DirectoryIndex",
        "server reached MaxRequestWorkers setting, consider raising it",
        "AH00060: seg fault or similar nasty error detected for root process",
        "mod_ssl: SSL handshake failed: HTTP spoken on HTTPS port",
        "AH01144: No protocol handler was valid for the URL /api/v2",
        "child process 1234 still did not exit, sending a SIGTERM",
        "AH00161: server reached MaxClients setting, consider raising it",
        "mod_proxy: Error reading from remote server returned by /api/users",
        "AH01215: SuexecUserPolicy: target uid/gid (1000/1000) mismatch",
        "mod_wsgi (pid=5521): Call exception handler for application api",
        "AH02429: Response header name X-Custom-Header contains invalid chars",
    ],
    "auth": [
        "Brute force detected: 150 failed login attempts from 192.168.1.100",
        "JWT token validation failed: signature mismatch for user session a3f8b2c1d4e5f6a7",
        "Rate limit exceeded for IP 10.0.0.55: 150/100 requests",
        "OAuth2 token refresh failed: invalid_grant",
        "LDAP bind failed: invalid credentials for user admin01",
        "Session expired for user 4521 - token issued 24h ago",
        "CSRF token mismatch for request from 172.16.0.50",
        "Account locked after 10 consecutive failed attempts: user jdoe",
        "MFA verification failed: invalid TOTP code for user 8832",
        "API key revoked: key_id=a1b2c3d4e5f6 used from unauthorized IP 10.0.0.99",
        "Password reset token expired for user user123@example.com",
        "SAML assertion validation failed: audience restriction not met",
        "Kerberos authentication failed: KRB5KDC_ERR_C_PRINCIPAL_UNKNOWN",
        "Invalid X.509 certificate presented by client 10.0.0.77",
        "Session hijacking detected: user 5521 accessed from new location 203.0.113.5",
        "Two-factor authentication bypassed for user 3312 - security alert",
        "OAuth authorization code expired before token exchange completed",
        "Certificate pinning violation: unexpected certificate for api.example.com",
        "User admin logged in from new device: Mozilla/5.0",
        "Authentication service unavailable: connection to auth-provider timed out",
    ],
    "cache": [
        "Redis connection timeout after 5s on host cache-primary:6379",
        "Cache eviction spike: 500 keys evicted in last 60s",
        "Redis CLUSTERDOWN: not all slots covered",
        "Memcached connection refused on 127.0.0.1:11211",
        "Cache key user_profile_4521 expired unexpectedly",
        "Redis maxmemory reached: no evictable keys, OOM command not allowed",
        "Cache miss rate increasing: 45% (normal: <10%)",
        "Redis sentinel failover initiated: master cache-primary down",
        "Cache serialization error: unable to deserialize key session_a3f8b2c1",
        "Varnish backend server cache-01 marked as sick",
        "Redis persistence failed: RDB snapshot could not save to disk",
        "Cache stampede detected: 200 concurrent requests for key hot_data",
        "Memcached slab allocation failure: out of memory for class 12",
        "Redis cluster node 10.0.0.30:6379 disconnected",
        "Cache warm-up failed: unable to preload 5000 keys from database",
        "Redis AOF rewrite failed: background save error",
        "Cache invalidation storm: 1000 keys purged in 2s",
        "Redis sentinel error: unable to resolve master for service cache-primary",
        "Cache backend timeout: Redis operation exceeded 10s threshold",
        "ElastiCache node cache-01 experiencing high memory utilization: 92%",
    ],
    "cloud": [
        "EC2 instance i-0a1b2c3d4e5f6a7b8 terminated unexpectedly",
        "S3 PutObject failed: AccessDenied on bucket prod-data-01",
        "Lambda function fn-process-orders invocation timeout after 30s",
        "RDS failover initiated: primary db-01 unavailable",
        "ECS task task-a1b2c3d4 stopped: essential container exited with code 1",
        "CloudWatch alarm triggered: CPU utilization > 90% for 5 minutes",
        "Elastic Load Balancer health check failed for target 10.0.0.5:8080",
        "Auto Scaling group asg-web-01 unable to launch instances: InsufficientInstanceCapacity",
        "S3 bucket prod-backup-01 replication lag exceeded 1 hour",
        "Route 53 health check failed for endpoint api.example.com",
        "CloudFormation stack stack-prod-01 rollback triggered: CREATE_FAILED",
        "EKS node group ng-workers node node-01 NotReady",
        "DynamoDB throughput exceeded: ProvisionedThroughputExceededException on table orders",
        "SQS queue queue-orders message age exceeded threshold: 500 messages older than 1 hour",
        "CloudFront distribution d1a2b3c4d5e6 origin origin-api returned 502",
        "IAM role role-lambda-exec policy evaluation denied: AccessDenied",
        "VPC peering connection pcx-a1b2c3d4 deleted unexpectedly",
        "EBS volume vol-0a1b2c3d4e5f6a7b8 IOPS limit reached: 4500/3000",
        "Lambda function fn-send-email cold start latency: 2500ms",
        "AWS Secrets Manager rotation failed for secret secret/db-credentials",
    ],
    "container": [
        "Container api-worker CrashLoopBackOff - restart count 5",
        "Image pull failed for registry.internal.io/api:v2.1.3: manifest not found",
        "Pod pod-api-5f8a2b evicted: The node was low on resource: memory",
        "Container container-worker OOMKilled - memory limit 512Mi exceeded",
        "Kubernetes node node-worker-01 NotReady: kubelet stopped posting node status",
        "Deployment deploy-api rollout stuck: ProgressDeadlineExceeded",
        "Pod pod-db-3c4d5e pending: 0/3 nodes are available: insufficient cpu",
        "Container container-api failed liveness probe: HTTP probe failed with status 503",
        "PersistentVolumeClaim pvc-data bound failed: no persistent volumes available",
        "Service svc-backend endpoint not ready: 0/3 pods available",
        "Container container-worker image pull backoff: rpc error: code = Unknown",
        "Pod pod-cache-7a8b9c terminated with exit code 1: Error",
        "HorizontalPodAutoscaler unable to calculate metrics",
        "Container container-api readiness probe failed: connection refused on port 8080",
        "Node node-worker-02 disk pressure: kubelet has disk pressure",
        "Ingress ingress-main sync failed: error obtaining service endpoints",
        "Container container-db failed to start: create container failed: volume not found",
        "PodDisruptionBudget pdb-api violated: current 1 < desired 3",
        "Container container-worker volume mount failed: hostPath /data does not exist",
        "Cluster autoscaler unable to scale up: no available instance types in us-east-1",
    ],
    "api": [
        "502 Bad Gateway from payment-service endpoint /api/v1/payments",
        "503 Service Unavailable from inventory-service (circuit breaker open)",
        "Rate limit exceeded: 250 requests/minute for client client-abc123",
        "API response time degraded: /api/v1/orders averaged 1200ms (threshold: 500ms)",
        "GraphQL query complexity exceeded maximum: 1500/1000",
        "API gateway timeout: upstream service did not respond within 30s",
        "Invalid API key used: key_id=key-xyz789 from IP 10.0.0.88",
        "Webhook delivery failed: POST to https://webhook.example.com/callback returned 500",
        "API schema validation error: field email is required but missing",
        "Request payload too large: 10485760 bytes exceeds limit of 5242880 bytes",
        "API version v1 deprecated: client client-old still using old version",
        "CORS preflight failed: origin https://evil.com not in allowed origins list",
        "API rate limit configuration error: bucket bucket-api misconfigured",
        "gRPC status UNAVAILABLE: connection to auth-service:50051 refused",
        "API request validation failed: date must be a valid ISO 8601 timestamp",
        "Service mesh sidecar proxy failed: envoy connection to upstream reset",
        "API response serialization error: circular reference in order_model",
        "Batch API request failed: 5/100 operations returned errors",
        "API cache invalidation failed: unable to purge CDN cache for /api/v1/users",
        "API documentation generation failed: OpenAPI spec validation error at path /orders",
    ],
    "queue": [
        "RabbitMQ connection lost - job job-process-payment retry 3/5",
        "Celery worker dead: task task-send-email timed out after 60s",
        "Queue depth growing: queue-orders has 500 pending jobs",
        "Dead letter queue overflow: 200 messages rejected after max retries",
        "Redis queue connection timeout: unable to dequeue job from queue-notifications",
        "Kafka consumer lag exceeded threshold: 1000 messages behind on topic orders",
        "Message queue broker broker-01 unreachable: connection refused on port 5672",
        "Job job-generate-report failed: TimeoutError - connection refused",
        "Queue worker worker-01 memory usage critical: 95% of 1024MB",
        "SQS queue queue-events approximate age of oldest message: 2 hours",
        "RabbitMQ channel closed unexpectedly: channel 5 on connection a1b2c3d4",
        "Message serialization failed: unable to encode job payload for queue-orders",
        "Queue consumer rate limiting: processing 50/sec (max: 100/sec)",
        "Job scheduler error: cron expression */5 * * * * invalid for task task-cleanup",
        "Message deduplication failed: duplicate message ID msg-a1b2c3d4 in queue-events",
        "Queue partition rebalancing: consumer group group-orders reassigned 10 partitions",
        "RabbitMQ disk alarm triggered: free disk space below 10% threshold",
        "Job retry exhaustion: job-process-payment failed 5 times, moved to dead letter queue",
        "Queue connection pool exhausted: 50/50 connections in use",
        "Message TTL expired: 100 messages in queue-temp exceeded 3600s time-to-live",
    ],
    "file": [
        "Permission denied: cannot write to /var/www/uploads/report.pdf",
        "File upload failed: exceeded max size 50MB for /api/v1/documents",
        "Disk space critical: /var/data at 95% capacity (95GB/100GB)",
        "File not found: /etc/app/config/database.yml",
        "Symbolic link loop detected: /var/log/app/current -> /var/log/app/current",
        "Unable to open file descriptor: too many open files (limit: 1024)",
        "File corruption detected: checksum mismatch for /data/backup/db-backup.sql",
        "NFS mount stale: /mnt/shared not responding for 30s",
        "File lock timeout: unable to acquire lock on /var/run/app/app.lock",
        "Temporary directory full: /tmp has 50MB available, need 200MB",
        "File encoding error: invalid UTF-8 sequence in data.csv at byte 4521",
        "Storage quota exceeded: user 5521 using 10GB of 5GB quota",
        "File sync failed: rsync to backup server backup-01 returned exit code 1",
        "Inode exhaustion: /var has 950000/1000000 inodes used (95%)",
        "File permission changed unexpectedly: /etc/app/secrets.key mode 0644 -> 0777",
        "Archive extraction failed: tar: backup.tar: Cannot open: No space left on device",
        "File watch limit reached: inotify max_user_watches (8192) exceeded",
        "S3 file download failed: report.pdf - NoSuchKey: The specified key does not exist",
        "File backup failed: unable to create snapshot of /var/data/vol-01",
        "Log rotation failed: unable to compress /var/log/app/app-abc123.log",
    ],
    "email": [
        "SMTP connection rejected by mx.example.com: 550 Authentication required",
        "Email delivery failed: recipient bounced user123@example.com",
        "SendGrid API error: rate limit exceeded - 150/100 emails per second",
        "SES bounce notification: hard bounce for user456@example.com - user unknown",
        "Mail queue backlog: 500 messages pending delivery for 2 hours",
        "SMTP TLS handshake failed: certificate expired for mail.example.com",
        "Email template rendering error: variable $order_id undefined in template invoice",
        "DKIM signature verification failed for message from noreply@example.com",
        "SPF check failed: 10.0.0.55 is not authorized to send mail for example.com",
        "Email attachment rejected: report.pdf exceeds maximum size of 10MB",
        "Mail server mail-01 connection timeout after 30s",
        "Email rate limiting: 200 messages rejected in last 30 minutes",
        "Bounce rate alert: 15% bounce rate for campaign camp-001 exceeds 10%",
        "Email parsing error: malformed MIME message from sender@example.com",
        "SMTP relay denied: 10.0.0.99 not in allowed relay hosts list",
        "Email delivery delayed: message queued for 4 hours due to greylisting",
        "Mailbox full: user admin@example.com quota exceeded (500MB/500MB)",
        "Email unsubscribe processing failed: invalid token for user 8832",
        "DMARC policy violation: message from noreply@example.com failed alignment check",
        "Email service health check failed: SMTP probe to mail-01:25 returned 500",
    ],
    "network": [
        "Connection refused to upstream server 10.0.0.5:8080",
        "DNS resolution failed for api.example.com: NXDOMAIN",
        "SSL handshake failed with mail-01:465: certificate verify failed",
        "Network interface eth0 down: link lost on port 1",
        "TCP connection timeout: 10.0.0.55:3306 did not respond within 30s",
        "Firewall rule blocked: DROP from 192.168.1.100 to 10.0.0.5:443",
        "BGP session lost with peer 10.0.0.1: hold timer expired",
        "ARP table overflow: 5000 entries exceeds maximum of 4096",
        "Network latency spike: 10.0.0.5 average RTT 250ms (threshold: 100ms)",
        "Packet loss detected: 15% loss on interface bond0 for last 10 minutes",
        "VLAN 100 trunk misconfigured: native VLAN mismatch on switch sw-01",
        "DHCP lease pool exhausted: no available addresses in subnet 10.0.1.0/24",
        "Network route flapping: route to 10.0.2.0/24 changed 50 times in 30 minutes",
        "MTU mismatch detected: interface eth0 MTU 1500 != peer MTU 9000",
        "Load balancer health check failed: backend backend-01 unhealthy for 60s",
        "Network congestion: bandwidth utilization on eth1 at 95%",
        "DNS cache poisoning attempt detected: spoofed response for example.com",
        "TCP SYN flood detected: 5000 half-open connections from 192.168.1.100",
        "Network interface eth1 CRC errors: 100 in last 2 hours",
        "CDN origin server origin-api unreachable: all edge nodes returning 502",
    ],
    "system": [
        "OOM killer activated: process java (PID 4521) killed",
        "Disk full on /var/log - no space left on device",
        "CPU spike detected: 98% sustained for 300s",
        "Kernel panic - not syncing: Fatal exception in interrupt",
        "Segmentation fault (core dumped) in process nginx (PID 3421)",
        "Memory usage critical: 15GB/16GB (94%) on host prod-web-01",
        "System load average: 12.5, 10.2, 8.1 - exceeds threshold 8.0",
        "Inode exhaustion on /var: 950000/1000000 inodes used (95%)",
        "Thermal throttling: CPU temperature 105C exceeds threshold 95C",
        "Zombie process detected: python3 (PID 8821) parent 1 not reaping",
        "File descriptor limit reached: 1020/1024 open files",
        "System clock drift detected: 250ms offset from NTP server ntp.example.com",
        "RAID array degraded: /dev/md0 - disk sdb failed",
        "Swap usage critical: 7GB/8GB (88%) - performance degradation expected",
        "Process mysqld (PID 1234) running for 30 days - possible runaway process",
        "Systemd service redis-server failed to start: exit code 1",
        "Kernel module mod_custom load failed: unknown symbol symbol_init",
        "Disk I/O bottleneck: /dev/sda awaiting 500 pending I/O operations",
        "System reboot detected: uptime reset to 50s - investigating cause",
        "Cron job cron-backup failed: exit code 1 - output: disk full",
    ],
    "general": [
        "Unknown error in worker thread 5: unexpected null reference",
        "Unhandled exception in module mod_payments: TimeoutError",
        "Configuration reload triggered for service api-gateway",
        "Application heartbeat missed: last check-in 120s ago",
        "Graceful shutdown initiated: draining 50 active connections",
        "Background job job-cleanup completed in 15s (expected: 10s)",
        "Feature flag feature-dark-mode toggled: enabled=true by user 5521",
        "Cache invalidation completed: 500 keys purged across 5 servers",
        "Scheduled maintenance window started: estimated duration 2 hours",
        "Data migration mig-001 in progress: 75% complete (7500/10000 records)",
        "Health check endpoint returned degraded status: dependency auth-service slow",
        "Application version 2.1.3 deployed to production: 5 instances updated",
        "Rate limiter reset: all buckets cleared for new time window",
        "Batch processing completed: 5000 records in 30s, 2 errors",
        "System configuration changed: config.cache_ttl updated from disabled to enabled",
        "Log rotation completed: 10 files archived, 500MB freed",
        "Service discovery update: 15 instances registered, 2 deregistered",
        "Database connection pool resized: 50 -> 100 connections",
        "API documentation regenerated: 50 endpoints documented in 5s",
        "Metrics export failed: unable to push 1000 data points to monitoring backend",
    ],
}

SOURCES = list(MESSAGE_POOLS.keys())

SEVERITY_MAP = {
    "database": "critical", "system": "critical",
    "network": "high", "auth": "high", "container": "high",
    "cloud": "high", "nginx": "high", "apache": "high",
    "api": "medium", "queue": "medium", "file": "medium",
    "email": "medium", "general": "medium", "cache": "low",
}


def write_logs_csv(filepath, logs):
    with open(filepath, "w", newline="") as f:
        writer = csv.writer(f)
        for log in logs:
            writer.writerow(log)


def write_incidents_csv(filepath, incidents):
    with open(filepath, "w", newline="") as f:
        writer = csv.writer(f)
        for inc in incidents:
            writer.writerow(inc)


def main():
    overall_start = time.time()
    print("=" * 60)
    print("  Mass Data Seeder - CSV + COPY Method")
    print("=" * 60)
    print(f"  Target: {TARGET_LOGS:,} logs")
    print(f"  Target: {TARGET_INCIDENTS:,} incidents")
    print(f"  Time span: {TIME_SPAN_DAYS} days")
    print("=" * 60)
    print()

    tmpdir = tempfile.mkdtemp()
    logs_csv = os.path.join(tmpdir, "logs.csv")
    incidents_csv = os.path.join(tmpdir, "incidents.csv")

    conn = psycopg2.connect(**DB_CONFIG)
    cur = conn.cursor()

    cur.execute("SELECT id FROM servers ORDER BY id")
    server_ids = [row[0] for row in cur.fetchall()]
    if not server_ids:
        print("ERROR: No servers found. Run db:seed first.")
        return

    now = datetime.now(timezone.utc)
    start_time = now - timedelta(days=TIME_SPAN_DAYS)
    total_seconds = int((now - start_time).total_seconds())

    all_messages = {src: MESSAGE_POOLS[src] for src in SOURCES}
    msg_counts = {src: len(msgs) for src, msgs in all_messages.items()}

    # ─── Phase 1: Generate incidents ────────────────────────────────────
    print("Phase 1: Generating incidents...")
    t0 = time.time()

    incidents = []
    incident_meta = []  # (inc_idx, num_logs, inc_time, inc_type, server_id)
    inc_idx = 0

    cluster_start = start_time + timedelta(hours=6)
    cluster_count = 0

    while cluster_start < now - timedelta(hours=2) and len(incidents) < TARGET_INCIDENTS:
        num_in_cluster = random.randint(3, 5)
        cluster_base = cluster_start
        cluster_types = random.sample(SOURCES, min(num_in_cluster, len(SOURCES)))

        for i, inc_type in enumerate(cluster_types):
            if len(incidents) >= TARGET_INCIDENTS:
                break
            inc_time = cluster_base + timedelta(minutes=i * random.randint(1, 2))
            server_id = random.choice(server_ids)
            severity = SEVERITY_MAP.get(inc_type, "medium")
            title_msg = random.choice(all_messages[inc_type])
            title = title_msg[:120] if len(title_msg) > 120 else title_msg
            num_logs = random.randint(100, 200)

            created_at_str = inc_time.strftime("%Y-%m-%d %H:%M:%S")
            incidents.append((server_id, "", "", title, inc_type, severity, "open", "", created_at_str, created_at_str))
            incident_meta.append((inc_idx, num_logs, inc_time, inc_type, server_id))
            inc_idx += 1

        cluster_count += 1
        cluster_start += timedelta(hours=random.uniform(2, 4))

    write_incidents_csv(incidents_csv, incidents)
    print(f"  Generated {len(incidents)} incidents in {cluster_count} clusters ({time.time() - t0:.1f}s)")

    # ─── Phase 2: Generate logs ─────────────────────────────────────────
    print("Phase 2: Generating logs...")
    t0 = time.time()

    logs = []
    incident_log_ranges = []  # Track which log indices belong to which incident
    logs_inserted = 0

    # Incident-related logs
    for inc_idx, num_logs, inc_time, inc_type, server_id in incident_meta:
        msgs = all_messages[inc_type]
        msg_len = msg_counts[inc_type]
        start_idx = len(logs)

        for j in range(num_logs):
            log_time = inc_time + timedelta(seconds=random.randint(0, 600))
            level = random.choice(["error", "error", "warn"])
            message = msgs[j % msg_len]
            ts_str = log_time.strftime("%Y-%m-%d %H:%M:%S")
            logs.append((server_id, message, level, inc_type, ts_str, ""))

        incident_log_ranges.append((inc_idx, start_idx, len(logs)))
        logs_inserted = len(logs)

    # Orphan INFO logs
    orphan_count = TARGET_LOGS - len(logs)
    if orphan_count > 0:
        for i in range(orphan_count):
            ts = start_time + timedelta(seconds=random.randint(0, total_seconds))
            server_id = random.choice(server_ids)
            source = random.choice(SOURCES)
            message = random.choice(all_messages[source])
            ts_str = ts.strftime("%Y-%m-%d %H:%M:%S")
            logs.append((server_id, message, "info", source, ts_str, ""))

    write_logs_csv(logs_csv, logs)
    print(f"  Generated {len(logs):,} logs ({orphan_count:,} orphan) ({time.time() - t0:.1f}s)")
    print(f"  CSV size: {os.path.getsize(logs_csv) / 1024 / 1024:.1f} MB")

    # ─── Phase 3: COPY incidents into DB ────────────────────────────────
    print("Phase 3: Loading incidents via COPY...")
    t0 = time.time()

    with open(incidents_csv, "r") as f:
        cur.copy_expert("""
            COPY incidents (server_id, created_by, assigned_to, title, type, severity, status, summary, created_at, updated_at)
            FROM STDIN WITH CSV
        """, f)
    conn.commit()
    print(f"  Loaded {len(incidents)} incidents ({time.time() - t0:.1f}s)")

    # ─── Phase 4: COPY logs into DB ─────────────────────────────────────
    print("Phase 4: Loading logs via COPY...")
    t0 = time.time()

    with open(logs_csv, "r") as f:
        cur.copy_expert("""
            COPY logs (server_id, message, log_level, source, timestamp, raw_payload)
            FROM STDIN WITH CSV
        """, f)
    conn.commit()
    print(f"  Loaded {len(logs):,} logs ({time.time() - t0:.1f}s)")

    # ─── Phase 5: Link logs to incidents via SQL ────────────────────────
    print("Phase 5: Linking logs to incidents...")
    t0 = time.time()

    # Get incident IDs in order
    cur.execute("SELECT id FROM incidents ORDER BY created_at")
    incident_ids = [row[0] for row in cur.fetchall()]

    # For each incident, find matching logs and create links
    links_created = 0
    batch = []

    for i, (inc_idx, num_logs, inc_time, inc_type, server_id) in enumerate(incident_meta):
        inc_id = incident_ids[i]
        end_time = inc_time + timedelta(minutes=10)

        cur.execute("""
            SELECT id FROM logs 
            WHERE server_id = %s 
              AND timestamp BETWEEN %s AND %s
              AND log_level IN ('error', 'warn')
            ORDER BY timestamp
            LIMIT %s
        """, (server_id, inc_time.strftime("%Y-%m-%d %H:%M:%S"), end_time.strftime("%Y-%m-%d %H:%M:%S"), num_logs))

        for row in cur.fetchall():
            batch.append((inc_id, row[0]))
            links_created += 1

            if len(batch) >= 10000:
                cur.execute("""
                    INSERT INTO incident_logs (incident_id, log_id)
                    SELECT * FROM unnest(%s::bigint[], %s::bigint[])
                    ON CONFLICT DO NOTHING
                """, ([b[0] for b in batch], [b[1] for b in batch]))
                conn.commit()
                batch = []

    if batch:
        cur.execute("""
            INSERT INTO incident_logs (incident_id, log_id)
            SELECT * FROM unnest(%s::bigint[], %s::bigint[])
            ON CONFLICT DO NOTHING
        """, ([b[0] for b in batch], [b[1] for b in batch]))
        conn.commit()

    print(f"  Created {links_created:,} links ({time.time() - t0:.1f}s)")

    # ─── Phase 6: Timeline events ───────────────────────────────────────
    print("Phase 6: Creating timeline events...")
    t0 = time.time()

    cur.execute("SELECT id, created_at FROM incidents ORDER BY created_at")
    rows = cur.fetchall()

    batch = []
    for inc_id, inc_time in rows:
        batch.append((inc_id, None, "created", "Incident created", inc_time))

        if len(batch) >= 10000:
            execute_values(cur, """
                INSERT INTO activity_timeline (incident_id, user_id, event_type, note, created_at)
                VALUES %s
            """, batch)
            conn.commit()
            batch = []

    if batch:
        execute_values(cur, """
            INSERT INTO activity_timeline (incident_id, user_id, event_type, note, created_at)
            VALUES %s
        """, batch)
        conn.commit()

    print(f"  Created {len(rows)} timeline events ({time.time() - t0:.1f}s)")

    # ─── Cleanup ────────────────────────────────────────────────────────
    os.remove(logs_csv)
    os.remove(incidents_csv)
    os.rmdir(tmpdir)

    # ─── Summary ────────────────────────────────────────────────────────
    print()
    print("=" * 60)
    print("  Seeding Complete!")
    print("=" * 60)

    cur.execute("SELECT count(*) FROM logs")
    print(f"  Total logs: {cur.fetchone()[0]:,}")

    cur.execute("SELECT count(*) FROM incidents")
    print(f"  Total incidents: {cur.fetchone()[0]:,}")

    cur.execute("SELECT count(*) FROM incident_logs")
    print(f"  Incident-log links: {cur.fetchone()[0]:,}")

    cur.execute("SELECT count(*) FROM activity_timeline")
    print(f"  Timeline events: {cur.fetchone()[0]:,}")

    cur.execute("SELECT type, count(*) FROM incidents GROUP BY type ORDER BY count DESC")
    print()
    print("  Incidents by type:")
    for row in cur.fetchall():
        print(f"    {row[0]:15s} {row[1]:,}")

    cur.execute("SELECT severity, count(*) FROM incidents GROUP BY severity ORDER BY severity")
    print()
    print("  Incidents by severity:")
    for row in cur.fetchall():
        print(f"    {row[0]:15s} {row[1]:,}")

    print()
    print(f"  Total time: {time.time() - overall_start:.1f}s")

    cur.close()
    conn.close()


if __name__ == "__main__":
    main()
