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
APP_URL="${APP_URL:-https://net-recovery-app.web.app}"

echo "==> Configurando proyecto GCP: ${PROJECT_ID}"
gcloud config set project "${PROJECT_ID}"

echo "==> Habilitando APIs necesarias..."
gcloud services enable run.googleapis.com containerregistry.googleapis.com cloudbuild.googleapis.com

echo "==> Construyendo y subiendo imagen con Cloud Build (no requiere Docker local)..."
gcloud builds submit --config cloudbuild.yaml .

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
  --set-env-vars "APP_ENV=production,APP_DEBUG=false,APP_KEY=${APP_KEY},APP_URL=${APP_URL},APP_LOCALE=es,APP_FALLBACK_LOCALE=en,DB_CONNECTION=sqlite,DB_DATABASE=/tmp/database.sqlite,SESSION_DRIVER=database,SESSION_LIFETIME=120,CACHE_STORE=database,QUEUE_CONNECTION=sync,LOG_CHANNEL=stderr,LOG_LEVEL=warning" \
  --set-env-vars "ZAVU_API_KEY=zv_live_3892d8aec663831de302a709b2841ca184871cb9e22e434a,ZAVU_BASE_URL=https://api.zavu.dev,ZAVU_SENDER=kd7eyphnd8t74e2mf9g2jqw2th8c7khm,ZAVU_TEMPLATE_ID=ks71hmj5vr9b0a68r5vs3k92y18c6smf" \
  --set-env-vars "WHATSAPP_VERSION=v21.0,WHATSAPP_BASE_URL=https://graph.facebook.com,WHATSAPP_TOKEN=${WHATSAPP_TOKEN:-},WHATSAPP_PHONE_NUMBER_ID=${WHATSAPP_PHONE_NUMBER_ID:-},WHATSAPP_WEBHOOK_SECRET=${WHATSAPP_WEBHOOK_SECRET:-}"

echo "==> Listo. URL del servicio:"
gcloud run services describe "${SERVICE_NAME}" --region "${REGION}" --format="value(status.url)"
