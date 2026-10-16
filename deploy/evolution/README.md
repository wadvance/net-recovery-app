# Evolution API propia (Baileys self-hosted) para NET RECOVERY

Todo el tráfico de WhatsApp queda en TU infraestructura. Ningún tercero
(ni Apis What, ni Zavu, ni YCloud) ve tus mensajes. Baileys/Evolution son
open-source (licencias MIT/Apache): usarlos es legal.

## Opción A — VPS con Docker (recomendado)

```bash
cd recovery-app/deploy/evolution
cp .env.example .env
# edita .env: SERVER_URL, EVOLUTION_API_KEY, POSTGRES_PASSWORD
docker compose up -d
# Evolution queda en http://tu-vps:8080 (ponle HTTPS con nginx/caddy)
```

## Opción B — Railway (segundo servicio)

1. Crea un **nuevo servicio** en tu proyecto Railway desde imagen Docker:
   `atendai/evolution-api:v2.2.1`
2. Agrega plugins **Postgres** y **Redis**, y estas variables:
   - `AUTHENTICATION_TYPE=apikey`
   - `AUTHENTICATION_API_KEY=<clave larga solo tuya>`
   - `DATABASE_ENABLED=true`, `DATABASE_PROVIDER=postgresql`
   - `DATABASE_CONNECTION_URI=<tu postgres de Railway>`
   - `REDIS_ENABLED=true`, `REDIS_URI=<tu redis de Railway>`
   - `CACHE_REDIS_ENABLED=true`, `CACHE_REDIS_URI=<tu redis>`
   - `SERVER_URL=https://<tu-evolution>.up.railway.app`
3. Haz deploy y verifica `https://<tu-evolution>.up.railway.app/` responde.

## Conectar tu backend Laravel

En Railway (servicio `net-recovery-backend`) define:

```env
WHATSAPP_PROVIDER=evolution
EVOLUTION_BASE_URL=https://<tu-evolution>.up.railway.app
EVOLUTION_API_KEY=<la misma clave del paso anterior>
EVOLUTION_INSTANCE=net-recovery
EVOLUTION_WEBHOOK_SECRET=<secreto largo solo tuyo>
```

## Parear el número del negocio (1 vez)

1. Entra al panel → WhatsApp (o `POST /api/v1/whatsapp/evolution/connect` con tu token).
2. Escanea el QR con **WhatsApp > Dispositivos vinculados > Vincular dispositivo**
   usando el número del negocio (+507...).
3. Verifica: `GET /api/v1/whatsapp/evolution/connection` → `{"state":"open"}`.
4. Prueba: `GET /api/v1/whatsapp/status` → `provider: evolution, has_session: true`.
5. Envía 1 mensaje de prueba a tu propio número desde el panel.

## Notas operativas

- **Un número = una sesión**: ese número ya no se usa en el teléfono como cuenta
  principal, queda como dispositivo vinculado (sí puedes seguir viendo los chats
  en el teléfono, a diferencia de Cloud API).
- Si cambias de teléfono o cierras sesión: `POST /whatsapp/evolution/logout`
  y vuelve a escanear el QR.
- Ritmo de masivos: la app ya envía encolado; no subas el volumen de golpe con
  un número nuevo (calienta la línea días antes).
- Webhook: `EvolutionController@connect` lo configura solo a
  `/api/v1/whatsapp/evolution/webhook?token=...`. Si cambias `APP_URL`, llama
  a `connect` de nuevo.
- Respaldo: el volumen `evolution-instances` guarda la sesión (no pierdes el
  pareo al reiniciar).
