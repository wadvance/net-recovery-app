/**
 * Relay anti-bot para netrecovery.unaux.com (profreehost/iFastNet).
 *
 * El hosting muestra un reto JS (aes.js) que exige la cookie __test a toda
 * peticion sin navegador. Esta cookie se calcula con AES-128-CBC:
 *   cookie = hex(AES_CBC_decrypt(c, key=a, iv=b))   (sin padding)
 * donde a, b, c vienen incrustados en la pagina del reto.
 *
 * Este Worker:
 * 1. Resuelve el reto anti-bot y reenvia peticiones /api/v1/* al origin
 * 2. Resuelve el webhook de Meta directamente (sin ir al origin)
 * 3. Sirve la politica de privacidad para Meta App Review
 * 4. Provee un health check en /api/v1/health
 * 5. Agrega headers CORS para el admin panel en Firebase
 */

const ORIGIN = 'https://netrecovery.unaux.com';

// Cache global por invocacion (Cloudflare Workers mantiene estado en modules)
const cookieStore = {
  value: null,
  expiresAt: 0,
};

// CORS headers para el admin panel en Firebase
const CORS_HEADERS = {
  'Access-Control-Allow-Origin': '*',
  'Access-Control-Allow-Methods': 'GET, POST, PUT, DELETE, OPTIONS',
  'Access-Control-Allow-Headers': 'Content-Type, Authorization, Accept, X-Requested-With',
  'Access-Control-Max-Age': '86400',
};

function corsResponse(response) {
  const newResponse = new Response(response.body, response);
  for (const [key, value] of Object.entries(CORS_HEADERS)) {
    newResponse.headers.set(key, value);
  }
  newResponse.headers.set('Cache-Control', 'no-store, no-cache, must-revalidate');
  return newResponse;
}

async function solveCookie(url) {
  const res = await fetch(url, {
    headers: { 'User-Agent': 'Mozilla/5.0 (compatible; NetRecoveryRelay/1.0)' },
    redirect: 'manual',
  });
  const html = await res.text();
  const m = html.match(/a=toNumbers\("([0-9a-f]+)"\),b=toNumbers\("([0-9a-f]+)"\),c=toNumbers\("([0-9a-f]+)"\)/);
  if (!m) {
    throw new Error('No se encontro el reto anti-bot en la respuesta del origin');
  }
  const [, a, b, c] = m;
  const key = hexToBytes(a);
  const iv = hexToBytes(b);
  const ct = hexToBytes(c);
  const pt = aes128CbcDecrypt(key, iv, ct);
  const cookie = bytesToHex(pt);

  cookieStore.value = cookie;
  cookieStore.expiresAt = Date.now() + 5 * 60 * 60 * 1000;
  return cookie;
}

async function getCookie(url) {
  if (cookieStore.value && Date.now() < cookieStore.expiresAt) {
    return cookieStore.value;
  }
  return solveCookie(url);
}

/* --- Politica de privacidad (Meta App) --- */
const PRIVACY_HTML = `<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Politica de Privacidad - NET RECOVERY SOLUTIONS</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>body{font-family:Arial,sans-serif;max-width:800px;margin:40px auto;padding:0 20px;line-height:1.6;color:#222}h1{font-size:1.5em}h2{font-size:1.15em;margin-top:28px}</style>
</head>
<body>
<h1>Politica de Privacidad - NET RECOVERY SOLUTIONS</h1>
<p>Ultima actualizacion: agosto de 2026</p>

<h2>1. Quienes somos</h2>
<p>NET RECOVERY SOLUTIONS es un servicio de recuperacion y entrega de equipos para clientes de companias de telecomunicaciones en Panama. Contacto: wadvancetech@gmail.com</p>

<h2>2. Datos que recopilamos</h2>
<ul>
<li>Nombre del cliente</li>
<li>Numero de telefono y telefono alternativo</li>
<li>Direccion (provincia, distrito, corregimiento, barrio)</li>
<li>Numero de cuenta o suscriptor proporcionado por la compania</li>
<li>Mensajes intercambiados por WhatsApp relacionados con la recuperacion de equipos</li>
</ul>

<h2>3. Como usamos los datos</h2>
<ul>
<li>Notificar al cliente sobre procesos de recuperacion de equipos pendientes</li>
<li>Coordinar la entrega o devolucion de equipos</li>
<li>Gestion interna de tareas de agentes autorizados</li>
</ul>

<h2>4. Uso de WhatsApp</h2>
<p>Utilizamos la API oficial de WhatsApp Business para enviar notificaciones y recibir respuestas de clientes. Los mensajes se procesan unicamente para fines del servicio de recuperacion. El cliente puede solicitar dejar de recibir mensajes respondiendo "CANCELAR" en cualquier momento.</p>

<h2>5. Comparticion de datos</h2>
<p>No vendemos ni compartimos datos personales con terceros con fines comerciales. Los datos se comparten unicamente con la compania proveedora de servicios que origino el proceso de recuperacion y con proveedores tecnologicos necesarios para el envio de mensajes (WhatsApp/Meta).</p>

<h2>6. Conservacion y eliminacion</h2>
<p>Los datos se conservan mientras dure la relacion comercial. Puede solicitar la eliminacion de sus datos escribiendo a wadvancetech@gmail.com</p>

<h2>7. Contacto</h2>
<p>Para cualquier consulta sobre esta politica: wadvancetech@gmail.com</p>
</body>
</html>`;

export default {
  async fetch(request, env, ctx) {
    const url = new URL(request.url);

    // Handle CORS preflight
    if (request.method === 'OPTIONS') {
      return new Response(null, { status: 204, headers: CORS_HEADERS });
    }

    // Politica de privacidad publica (para validacion de Meta App Review)
    if (url.pathname === '/privacidad' || url.pathname === '/privacy') {
      return corsResponse(new Response(PRIVACY_HTML, {
        status: 200,
        headers: { 'Content-Type': 'text/html; charset=utf-8' },
      }));
    }

    // Health check - verifica que el backend este operativo
    if (url.pathname === '/api/v1/health') {
      try {
        const cookie = await getCookie(ORIGIN + '/api/v1/health');
        const healthRes = await fetch(ORIGIN + '/api/v1/health', {
          headers: {
            'Cookie': '__test=' + cookie,
            'User-Agent': 'Mozilla/5.0 (compatible; NetRecoveryRelay/1.0)',
          },
        });
        const body = await healthRes.text();
        return corsResponse(new Response(body, {
          status: healthRes.status,
          headers: { 'Content-Type': 'application/json' },
        }));
      } catch (e) {
        return corsResponse(new Response(JSON.stringify({
          status: 'error',
          message: 'Backend no disponible: ' + e.message,
        }), {
          status: 502,
          headers: { 'Content-Type': 'application/json' },
        }));
      }
    }

    // Verificacion de webhook de Meta resuelta EN el worker (evita cache
    // desactualizada del hosting gratuito en el GET de hub_challenge).
    if (url.pathname === '/api/v1/whatsapp/meta/webhook' && request.method === 'GET') {
      const mode = url.searchParams.get('hub_mode');
      const token = url.searchParams.get('hub_verify_token');
      const challenge = url.searchParams.get('hub_challenge');
      if (mode === 'subscribe' && token === 'netrecovery2026') {
        return corsResponse(new Response(challenge || '', {
          status: 200,
          headers: { 'Content-Type': 'text/plain' },
        }));
      }
      return corsResponse(new Response('Forbidden', { status: 403 }));
    }

    // Solo reenviamos peticiones hacia /api/v1/...
    if (!url.pathname.startsWith('/api/v1')) {
      return corsResponse(new Response(JSON.stringify({ error: 'Ruta no encontrada' }), {
        status: 404,
        headers: { 'Content-Type': 'application/json' },
      }));
    }

    let cookie;
    try {
      cookie = await getCookie(ORIGIN + url.pathname + url.search);
    } catch (e) {
      return corsResponse(new Response(JSON.stringify({
        error: 'No se pudo resolver el reto anti-bot: ' + e.message,
      }), {
        status: 502,
        headers: { 'Content-Type': 'application/json' },
      }));
    }

    const target = ORIGIN + url.pathname + url.search;

    const headers = new Headers(request.headers);
    headers.set('Host', new URL(ORIGIN).host);
    headers.set('Cookie', '__test=' + cookie);
    headers.set('User-Agent', 'Mozilla/5.0 (compatible; NetRecoveryRelay/1.0)');

    // Quitar cabeceras que no deben reenviarse
    headers.delete('cf-connecting-ip');
    headers.delete('cf-ray');
    headers.delete('x-forwarded-for');
    headers.delete('x-forwarded-proto');
    headers.delete('x-forwarded-host');
    headers.delete('x-real-ip');

    let upstream;
    try {
      upstream = await fetch(target, {
        method: request.method,
        headers,
        body: ['GET', 'HEAD'].includes(request.method) ? undefined : await request.arrayBuffer(),
        redirect: 'manual',
      });
    } catch (e) {
      return corsResponse(new Response(JSON.stringify({
        error: 'Error conectando al backend: ' + e.message,
      }), {
        status: 502,
        headers: { 'Content-Type': 'application/json' },
      }));
    }

    // Si el origin vuelve a servir el reto, resolvemos la cookie y
    // seguimos la cadena de redirect como un navegador.
    const ct = upstream.headers.get('content-type') || '';
    if (upstream.status === 200 && ct.includes('text/html')) {
      const bodyText = await upstream.clone().text();
      if (bodyText.includes('aes.js') || bodyText.includes('slowAES')) {
        cookieStore.value = null;
        try {
          cookie = await solveCookie(target);
          headers.set('Cookie', '__test=' + cookie);
          // Seguir el redirect de la cadena anti-bot
          const rm = bodyText.match(/location\.href="([^"]+)"/);
          const redirectUrl = rm ? rm[1] : target;
          upstream = await fetch(redirectUrl, {
            method: request.method,
            headers,
            body: ['GET', 'HEAD'].includes(request.method) ? undefined : await request.arrayBuffer(),
            redirect: 'manual',
          });
        } catch (e) {
          return corsResponse(new Response(JSON.stringify({
            error: 'Re-challenge fallido: ' + e.message,
          }), {
            status: 502,
            headers: { 'Content-Type': 'application/json' },
          }));
        }
      }
    }

    // Si retorna 500 con cuerpo vacio, reintentar con cookie fresca
    const ct2 = upstream.headers.get('content-type') || '';
    if (upstream.status === 500) {
      const bodyText = await upstream.clone().text();
      if (bodyText.length === 0) {
        cookieStore.value = null;
        try {
          cookie = await solveCookie(target);
          headers.set('Cookie', '__test=' + cookie);
          upstream = await fetch(target, {
            method: request.method,
            headers,
            body: ['GET', 'HEAD'].includes(request.method) ? undefined : await request.arrayBuffer(),
            redirect: 'manual',
          });
        } catch (e) { /* ignorar */ }
      }
    }

    const respBody = await upstream.arrayBuffer();
    const respHeaders = new Headers(upstream.headers);
    respHeaders.delete('set-cookie');
    respHeaders.delete('location');

    // Agregar CORS y no-cache a la respuesta
    for (const [key, value] of Object.entries(CORS_HEADERS)) {
      respHeaders.set(key, value);
    }
    respHeaders.set('Cache-Control', 'no-store, no-cache, must-revalidate');

    return new Response(respBody, {
      status: upstream.status,
      headers: respHeaders,
    });
  },
};

/* --- AES-128-CBC (sin padding) en JS puro --- */

function hexToBytes(hex) {
  const out = new Uint8Array(hex.length / 2);
  for (let i = 0; i < out.length; i++) out[i] = parseInt(hex.substr(i * 2, 2), 16);
  return out;
}

function bytesToHex(bytes) {
  let s = '';
  for (let i = bytes.length - 1; i >= 0; i--) s += bytes[i].toString(16).padStart(2, '0');
  return s;
}

function aes128CbcDecrypt(key, iv, data) {
  const Nr = 10;
  const sBox = [
    0x63,0x7c,0x77,0x7b,0xf2,0x6b,0x6f,0xc5,0x30,0x01,0x67,0x2b,0xfe,0xd7,0xab,0x76,
    0xca,0x82,0xc9,0x7d,0xfa,0x59,0x47,0xf0,0xad,0xd4,0xa2,0xaf,0x9c,0xa4,0x72,0xc0,
    0xb7,0xfd,0x93,0x26,0x36,0x3f,0xf7,0xcc,0x34,0xa5,0xe5,0xf1,0x71,0xd8,0x31,0x15,
    0x04,0xc7,0x23,0xc3,0x18,0x96,0x05,0x9a,0x07,0x12,0x80,0xe2,0xeb,0x27,0xb2,0x75,
    0x09,0x83,0x2c,0x1a,0x1b,0x6e,0x5a,0xa0,0x52,0x3b,0xd6,0xb3,0x29,0xe3,0x2f,0x84,
    0x53,0xd1,0x00,0xed,0x20,0xfc,0xb1,0x5b,0x6a,0xcb,0xbe,0x39,0x4a,0x4c,0x58,0xcf,
    0xd0,0xef,0xaa,0xfb,0x43,0x4d,0x33,0x85,0x45,0xf9,0x02,0x7f,0x50,0x3c,0x9f,0xa8,
    0x51,0xa3,0x40,0x8f,0x92,0x9d,0x38,0xf5,0xbc,0xb6,0xda,0x21,0x10,0xff,0xf3,0xd2,
    0xcd,0x0c,0x13,0xec,0x5f,0x97,0x44,0x17,0xc4,0xa7,0x7e,0x3d,0x64,0x5d,0x19,0x73,
    0x60,0x81,0x4f,0xdc,0x22,0x2a,0x90,0x88,0x46,0xee,0xb8,0x14,0xde,0x5e,0x0b,0xdb,
    0xe0,0x32,0x3a,0x0a,0x49,0x06,0x24,0x5c,0xc2,0xd3,0xac,0x62,0x91,0x95,0xe4,0x79,
    0xe7,0xc8,0x37,0x6d,0x8d,0xd5,0x4e,0xa9,0x6c,0x56,0xf4,0xea,0x65,0x7a,0xae,0x08,
    0xba,0x78,0x25,0x2e,0x1c,0xa6,0xb4,0xc6,0xe8,0xdd,0x74,0x1f,0x4b,0xbd,0x8b,0x8a,
    0x70,0x3e,0xb5,0x66,0x48,0x03,0xf6,0x0e,0x61,0x35,0x57,0xb9,0x86,0xc1,0x1d,0x9e,
    0xe1,0xf8,0x98,0x11,0x69,0xd9,0x8e,0x94,0x9b,0x1e,0x87,0xe9,0xce,0x55,0x28,0xdf,
    0x8c,0xa1,0x89,0x0d,0xbf,0xe6,0x42,0x68,0x41,0x99,0x2d,0x0f,0xb0,0x54,0xbb,0x16,
  ];
  const rCon = [0x00,0x01,0x02,0x04,0x08,0x10,0x20,0x40,0x80,0x1b,0x36];
  const invSBox = [
    0x52,0x09,0x6a,0xd5,0x30,0x36,0xa5,0x38,0xbf,0x40,0xa3,0x9e,0x81,0xf3,0xd7,0xfb,
    0x7c,0xe3,0x39,0x82,0x9b,0x2f,0xff,0x87,0x34,0x8e,0x43,0x44,0xc4,0xde,0xe9,0xcb,
    0x54,0x7b,0x94,0x32,0xa6,0xc2,0x23,0x3d,0xee,0x4c,0x95,0x0b,0x42,0xfa,0xc3,0x4e,
    0x08,0x2e,0xa1,0x66,0x28,0xd9,0x24,0xb2,0x76,0x5b,0xa2,0x49,0x6d,0x8b,0xd1,0x25,
    0x72,0xf8,0xf6,0x64,0x86,0x68,0x98,0x16,0xd4,0xa4,0x5c,0xcc,0x5d,0x65,0xb6,0x92,
    0x6c,0x70,0x48,0x50,0xfd,0xed,0xb9,0xda,0x5e,0x15,0x46,0x57,0xa7,0x8d,0x9d,0x84,
    0x90,0xd8,0xab,0x00,0x8c,0xbc,0xd3,0x0a,0xf7,0xe4,0x58,0x05,0xb8,0xb3,0x45,0x06,
    0xd0,0x2c,0x1e,0x8f,0xca,0x3f,0x0f,0x02,0xc1,0xaf,0xbd,0x03,0x01,0x13,0x8a,0x6b,
    0x3a,0x91,0x11,0x41,0x4f,0x67,0xdc,0xea,0x97,0xf2,0xcf,0xce,0xf0,0xb4,0xe6,0x73,
    0x96,0xac,0x74,0x22,0xe7,0xad,0x35,0x85,0xe2,0xf9,0x37,0xe8,0x1c,0x75,0xdf,0x6e,
    0x47,0xf1,0x1a,0x71,0x1d,0x29,0xc5,0x89,0x6f,0xb7,0x62,0x0e,0xaa,0x18,0xbe,0x1b,
    0xfc,0x56,0x3e,0x4b,0xc6,0xd2,0x79,0x20,0x9a,0xdb,0xc0,0xfe,0x78,0xcd,0x5a,0xf4,
    0x1f,0xdd,0xa8,0x33,0x88,0x07,0xc7,0x31,0xb1,0x12,0x10,0x59,0x27,0x80,0xec,0x5f,
    0x60,0x51,0x7f,0xa9,0x19,0xb5,0x4a,0x0d,0x2d,0xe5,0x7a,0x9f,0x93,0xc9,0x9c,0xef,
    0xa0,0xe0,0x3b,0x4d,0xae,0x2a,0xf5,0xb0,0xc8,0xeb,0xbb,0x3c,0x83,0x53,0x99,0x61,
    0x17,0x2b,0x04,0x7e,0xba,0x77,0xd6,0x26,0xe1,0x69,0x14,0x63,0x55,0x21,0x0c,0x7d,
  ];

  function expandKey(key) {
    const Nk = 4;
    const w = [];
    for (let i = 0; i < Nk; i++) {
      w[i] = (key[4*i] << 24) | (key[4*i+1] << 16) | (key[4*i+2] << 8) | key[4*i+3];
    }
    for (let i = Nk; i < 4*(Nr+1); i++) {
      let temp = w[i-1];
      if (i % Nk === 0) {
        temp = ((temp << 8) | (temp >>> 24)) >>> 0;
        temp = ((sBox[(temp >>> 24) & 0xff] << 24) | (sBox[(temp >>> 16) & 0xff] << 16) | (sBox[(temp >>> 8) & 0xff] << 8) | sBox[temp & 0xff]) >>> 0;
        temp = (temp ^ (rCon[i/Nk] << 24)) >>> 0;
      }
      w[i] = (w[i-Nk] ^ temp) >>> 0;
    }
    return w;
  }

  function addRoundKey(state, w, round) {
    for (let c = 0; c < 4; c++) {
      for (let r = 0; r < 4; r++) {
        state[r][c] ^= (w[round*4 + c] >>> (24 - 8*r)) & 0xff;
      }
    }
  }

  function invShiftRows(state) {
    for (let r = 1; r < 4; r++) {
      const row = state[r].slice();
      for (let c = 0; c < 4; c++) state[r][c] = row[(c - r + 4) % 4];
    }
  }

  function invSubBytes(state) {
    for (let r = 0; r < 4; r++) for (let c = 0; c < 4; c++) state[r][c] = invSBox[state[r][c]];
  }

  function gfMul(a, b) {
    let p = 0;
    let hiBit;
    for (let i = 0; i < 8; i++) {
      if (b & 1) p ^= a;
      hiBit = a & 0x80;
      a = (a << 1) & 0xff;
      if (hiBit) a ^= 0x1b;
      b >>>= 1;
    }
    return p;
  }

  function invMixColumns(state) {
    for (let c = 0; c < 4; c++) {
      const a0 = state[0][c], a1 = state[1][c], a2 = state[2][c], a3 = state[3][c];
      state[0][c] = gfMul(a0,14) ^ gfMul(a1,11) ^ gfMul(a2,13) ^ gfMul(a3,9);
      state[1][c] = gfMul(a0,9) ^ gfMul(a1,14) ^ gfMul(a2,11) ^ gfMul(a3,13);
      state[2][c] = gfMul(a0,13) ^ gfMul(a1,9) ^ gfMul(a2,14) ^ gfMul(a3,11);
      state[3][c] = gfMul(a0,11) ^ gfMul(a1,13) ^ gfMul(a2,9) ^ gfMul(a3,14);
    }
  }

  const w = expandKey(key);
  const out = new Uint8Array(data.length);
  let prevBlock = iv;

  for (let blk = 0; blk < data.length; blk += 16) {
    const state = [];
    for (let r = 0; r < 4; r++) state[r] = [0,0,0,0];
    for (let c = 0; c < 4; c++) {
      for (let r = 0; r < 4; r++) {
        state[r][c] = data[blk + c*4 + r];
      }
    }

    addRoundKey(state, w, Nr);
    for (let round = Nr - 1; round >= 1; round--) {
      invShiftRows(state);
      invSubBytes(state);
      addRoundKey(state, w, round);
      invMixColumns(state);
    }
    invShiftRows(state);
    invSubBytes(state);
    addRoundKey(state, w, 0);

    for (let c = 0; c < 4; c++) {
      for (let r = 0; r < 4; r++) {
        out[blk + c*4 + r] = state[r][c] ^ prevBlock[c*4 + r];
      }
    }
    prevBlock = data.slice(blk, blk + 16);
  }

  return out;
}