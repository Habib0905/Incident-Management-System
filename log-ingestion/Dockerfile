FROM alpine:latest

RUN apk add --no-cache bash python3 curl

WORKDIR /app

COPY ingest_logs.sh /app/ingest_logs.sh
RUN chmod +x /app/ingest_logs.sh

CMD ["/bin/sh", "-c", "while true; do bash /app/ingest_logs.sh once; sleep 2; done"]
