#!/bin/bash
cd /home/habib-hussain/projects/Incident-Management-System/services/similarity-api
trap '' HUP
exec ./venv/bin/uvicorn main:app --host 127.0.0.1 --port 8001
