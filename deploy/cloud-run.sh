#!/usr/bin/env bash
# Deploy NET RECOVERY to Google Cloud Run
# Requisitos: gcloud CLI, proyecto GCP, Docker disponible.
# Uso: bash deploy/cloud-run.sh <PROJECT_ID>
set -euo pipefail

PROJECT_ID="${1:?Uso: bash deploy/cloud-run.sh <PROJECT_ID>}"
REGION="us-central1"
SERVICE_NAME="net-recovery-app"
IMAGE="gcr.io/${PROJECT_ID}/${SERVICE_NAME}:latest"
APP_KEY="base64:Gya0/U7CTc8D9jwKA536jyaclCn0xJJXMJuF/8GCjOw="

echo "==> Configurando proyecto GCP: ${PROJECT_ID}"
gcloud config set project "${PROJECT_ID}"

echo "==> Habilitando APIs necesarias..."
gcloud services enable run.googleapis.com containerregistry.googleapis.com

echo "==> Construyendo imagen Docker..."
docker build -t "${IMAGE}" .

echo "==> Subiendo imagen a Container Registry..."
docker push "${IMAGE}"

echo "==> Desplegando en Cloud Run..."
gcloud run deploy "${SERVICE_NAME}" \
  --image "${IMAGE}" \
  --platform managed \
  --region "${REGION}" \
  --memory 512Mi \
  --cpu 1 \
  --min-instances 0 \
  --max-instances 2 \
  --allow-unauthenticated \
  --port 8080 \
  --timeout 300 \
  --set-env-vars "APP_ENV=production,APP_DEBUG=false,APP_KEY=${APP_KEY},DB_CONNECTION=sqlite,DB_DATABASE=/tmp/database.sqlite,QUEUE_CONNECTION=sync,LOG_CHANNEL=stderr" \
  --set-env-vars "ZAVU_API_KEY=zv_live_3892d8aec663831de302a709b2841ca184871cb9e22e434a,ZAVU_BASE_URL=https://api.zavu.dev,ZAVU_SENDER=kd7eyphnd8t74e2mf9g2jqw2th8c7khm,ZAVU_TEMPLATE_ID=ks71hmj5vr9b0a68r5vs3k92y18c6smf"

echo "==> Listo. URL del servicio:"
gcloud run services describe "${SERVICE_NAME}" --region "${REGION}" --format="value(status.url)"
