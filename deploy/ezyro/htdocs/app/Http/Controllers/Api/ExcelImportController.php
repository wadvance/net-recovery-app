<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\ExcelImport;
use App\Models\Client;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\User;
use App\Models\WhatsAppMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use App\Services\WhatsAppService;

class ExcelImportController extends Controller
{
    public function index(Request $request)
    {
        $query = ExcelImport::with(['company', 'importedBy'])
            ->orderBy('created_at', 'desc');
        if ($request->has('company_id')) $query->where('company_id', $request->company_id);
        if ($request->user()->role === 'agent') {
            $query->where('imported_by', $request->user()->id);
        }
        return response()->json($query->paginate($request->get('per_page', 15)));
    }

    public function show(Request $request, ExcelImport $import)
    {
        return response()->json($import->load(['company', 'importedBy']));
    }

    public function update(Request $request, ExcelImport $import)
    {
        if ($request->user()->role === 'agent' && $import->imported_by !== $request->user()->id) {
            return response()->json(['message' => 'No tienes permiso para editar esta importación'], 403);
        }

        $data = $request->validate([
            'company_id' => 'sometimes|exists:companies,id',
            'original_filename' => 'sometimes|string|max:255',
        ]);

        if (isset($data['company_id']) && $request->user()?->role !== 'agent') {
            $import->company_id = $data['company_id'];
        }
        if (isset($data['original_filename']) && $request->user()?->role !== 'agent') {
            $import->original_filename = $data['original_filename'];
        }
        $import->save();

        return response()->json($import->load('company'));
    }

    public function destroy(Request $request, ExcelImport $import)
    {
        if ($request->user()->role === 'agent' && $import->imported_by !== $request->user()->id) {
            return response()->json(['message' => 'No tienes permiso para eliminar esta importación'], 403);
        }

        $filePath = Storage::disk('local')->path($import->stored_filename);
        if (is_file($filePath)) {
            @unlink($filePath);
        }

        $import->delete();

        return response()->json(['message' => 'Importación eliminada correctamente']);
    }

    public function clearList(Request $request)
    {
        $query = ExcelImport::query();
        if ($request->user()->role === 'agent') {
            $query->where('imported_by', $request->user()->id);
        } elseif (!in_array($request->user()->role, ['admin', 'supervisor'], true)) {
            return response()->json(['message' => 'No tienes permiso para limpiar la lista'], 403);
        }

        $files = $query->pluck('stored_filename')->filter();
        foreach ($files as $stored) {
            $filePath = Storage::disk('local')->path($stored);
            if (is_file($filePath)) {
                @unlink($filePath);
            }
        }

        $deleted = $query->delete();

        return response()->json([
            'message' => 'Lista de archivos limpiada correctamente',
            'deleted' => $deleted,
        ]);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:51200',
            'company_id' => 'required|exists:companies,id',
            'scheduled_date' => 'nullable|date',
        ]);

        $file = $request->file('file');
        $storedFilename = 'imports/' . time() . '_' . $file->getClientOriginalName();
        $file->storeAs('imports', basename($storedFilename), 'local');

        $spreadsheet = Excel::toArray([], $file);
        $rows = $spreadsheet[0] ?? [];
        $totalRows = max(count($rows) - 1, 0);
        $headers = array_map(fn($h) => trim((string) $h), array_shift($rows) ?? []);
        $headersRaw = array_values(array_filter($headers, fn($h) => $h !== ''));

        $mapping = $this->autoMapHeaders($headersRaw);

        $import = ExcelImport::create([
            'company_id' => $request->company_id,
            'imported_by' => $request->user()->id,
            'original_filename' => $file->getClientOriginalName(),
            'stored_filename' => $storedFilename,
            'total_rows' => $totalRows,
            'status' => 'uploaded',
        ]);

        // Previsualización de las filas para que el agente revise el
        // contenido del archivo en pantalla antes de procesarlo y enviarlo.
        $preview = [];
        foreach ($rows as $index => $row) {
            if ($index >= 500) break;
            $previewRow = [];
            foreach ($headers as $i => $header) {
                if ($header === '') continue;
                $previewRow[$header] = $row[$i] ?? '';
            }
            $preview[] = $previewRow;
        }

        return response()->json([
            'import' => $import,
            'headers' => $headersRaw,
            'mapping' => $mapping,
            'preview' => $preview,
            'total_rows' => $totalRows,
            'message' => 'Archivo subido. Revisa la previsualización y presiona "Procesar y enviar" para crear las tareas y notificar a los clientes.',
        ], 201);
    }

    public function process(Request $request, ExcelImport $import)
    {
        $request->validate([
            'column_mapping' => 'required|array',
            'scheduled_date' => 'nullable|date',
            'replace' => 'boolean',
            'clear_all' => 'boolean',
        ]);

        if ($request->user()->role === 'agent' && $import->imported_by !== $request->user()->id) {
            return response()->json(['message' => 'No tienes permiso para procesar esta importación'], 403);
        }

        $mapping = $request->column_mapping;
        $filePath = Storage::disk('local')->path($import->stored_filename);
        $rows = Excel::toArray([], $filePath)[0] ?? [];
        $excelHeaders = array_shift($rows);

        $successful = 0;
        $skipped = 0;
        $failed = 0;
        $alreadyNotified = 0;
        $errors = [];
        $tasks = [];
        $notifiable = [];

        $replace = $request->boolean('replace', true);
        $clearAll = $request->boolean('clear_all', false);

        DB::beginTransaction();
        try {
            // --- Limpiar por día: al re-importar, el día anterior no
            //     queda y se empieza de cero (replace=true, default). ---
            if ($replace) {
                if ($request->scheduled_date) {
                    // Al re-importar el mismo día se reemplaza TODO el día,
                    // sin filtrar por empresa (el Excel puede traer varias
                    // empresas y los duplicados se detectan por teléfono+día).
                    $taskWipe = Task::whereDate('scheduled_date', $request->scheduled_date);
                    if ($clearAll) {
                        $taskWipe->whereDate('scheduled_date', '>=', $request->scheduled_date);
                        $scope = "a partir de {$request->scheduled_date}";
                    } else {
                        $scope = "el {$request->scheduled_date}";
                    }
                    $wiped = $taskWipe->delete();
                    $errors[] = "Antes de importar: {$wiped} tareas previas del {$scope} eliminadas (replace=true)";
                } elseif ($clearAll && $import->company_id) {
                    $wiped = Task::where('company_id', $import->company_id)->delete();
                    $errors[] = "Antes de importar: {$wiped} tareas previas de la empresa eliminadas (clear_all=true)";
                } else {
                    $errors[] = "replace=true pero sin scheduled_date: no se limpiaron tareas previas (envíe date para limpiar el día)";
                }
            }

            foreach ($rows as $index => $row) {
                // Omitir filas completamente vacías (trailing empty rows del Excel)
                if (empty(array_filter($row, fn($v) => $v !== null && trim((string)$v) !== ''))) {
                    continue;
                }
                try {
                    $data = [];
                    foreach ($mapping as $field => $colName) {
                        $colIndex = array_search($colName, $excelHeaders);
                        if ($colIndex !== false && isset($row[$colIndex])) {
                            $data[$field] = trim((string) $row[$colIndex]);
                        }
                    }

                    $fullName = $data['full_name'] ?? '';
                    $cuenta = $data['cuenta'] ?? '';
                    $suscriptor = $data['suscriptor'] ?? '';
                    $clienteCode = $data['cliente'] ?? '';
                    $usuario = $data['usuario'] ?? '';
                    $phone1 = $data['telefono_residencia_1'] ?? '';
                    $phone2 = $data['telefono_residencia_2'] ?? '';

                    $companyId = $import->company_id;
                    if (!empty($data['empresa'])) {
                        $matchedCompany = $this->findCompanyByName($data['empresa']);
                        if ($matchedCompany) {
                            $companyId = $matchedCompany->id;
                        } else {
                            $errors[] = "Fila " . ($index + 2) . ": Empresa '{$data['empresa']}' no reconocida (se usa '{$import->company?->name}')";
                        }
                    }

                    if ($fullName === '' || ($cuenta === '' && $suscriptor === '')) {
                        $errors[] = "Fila " . ($index + 2) . ": Faltan campos obligatorios (nombre, cuenta o suscriptor)";
                        $failed++;
                        continue;
                    }

                    $phone = preg_replace('/[^\d]/', '', $phone1);
                    if ($phone === '') {
                        $phone = preg_replace('/[^\d]/', '', $phone2);
                    }
                    if ($phone === '') {
                        $phone = preg_replace('/[^\d]/', '', $data['numero_celular'] ?? '');
                    }
                    if ($phone === '') {
                        $phone = preg_replace('/[^\d]/', '', $data['numero_contacto'] ?? '');
                    }
                    if ($phone === '') {
                        $errors[] = "Fila " . ($index + 2) . ": Sin teléfono en 'Telefono Residencia 1' ni 'Telefono Residencia 2'";
                        $failed++;
                        continue;
                    }
                    if (!str_starts_with($phone, '507')) $phone = '507' . ltrim($phone, '0');
                    $alternatePhone = preg_replace('/[^\d]/', '', $phone2);
                    if ($alternatePhone === '') {
                        $alternatePhone = preg_replace('/[^\d]/', '', $data['numero_celular'] ?? '');
                    }
                    if ($alternatePhone === '') {
                        $alternatePhone = preg_replace('/[^\d]/', '', $data['numero_contacto'] ?? '');
                    }
                    if ($alternatePhone !== '' && !str_starts_with($alternatePhone, '507')) $alternatePhone = '507' . ltrim($alternatePhone, '0');

                    $lugar = $this->stripInvalid($data['lugar'] ?? '');
                    $corregimiento = $this->stripInvalid($data['corregimiento'] ?? '');
                    $distrito = $this->stripInvalid($data['distrito'] ?? '');
                    $provincia = $this->stripInvalid($data['provincia'] ?? '');
                    $barrio = $this->stripInvalid($data['barrio'] ?? '');

                    // LUGAR es la dirección principal; si viene, se usa tal cual.
                    if ($lugar !== '') {
                        $address = $lugar;
                    } else {
                        $address = collect([$corregimiento, $distrito, $provincia])
                            ->filter(fn($v) => $v !== '')->unique()->implode(', ');
                    }

                    $clientData = [
                        'company_id' => $companyId,
                        'order_number' => $cuenta !== '' ? $cuenta : $suscriptor,
                        'full_name' => $fullName,
                        'phone' => $phone,
                        'alternate_phone' => $alternatePhone !== '' ? $alternatePhone : null,
                        'address' => $address,
                        'reference' => $data['cedula'] ?? null,
                        'status' => 'pending',
                        'metadata' => [
                            'suscriptor' => $suscriptor !== '' ? $suscriptor : null,
                            'cedula' => $data['cedula'] ?? null,
                            'cliente' => $clienteCode !== '' ? $clienteCode : null,
                            'cuenta' => $cuenta !== '' ? $cuenta : $suscriptor,
                            'lugar' => $lugar !== '' ? $lugar : null,
                            'provincia' => $provincia,
                            'distrito' => $distrito,
                            'corregimiento' => $corregimiento,
                            'barrio' => $barrio,
                            'numero_celular' => $data['numero_celular'] ?? null,
                            'numero_contacto' => $data['numero_contacto'] ?? null,
                        ],
                    ];

                    $client = Client::where('company_id', $companyId)
                        ->where('phone', $phone)
                        ->latest('id')
                        ->first();
                    if (!$client) {
                        $client = Client::create($clientData);
                    }

                    if ($request->scheduled_date
                        && Task::withoutTrashed()
                            ->where('client_id', $client->id)
                            ->whereDate('scheduled_date', $request->scheduled_date)
                            ->exists()) {
                        $errors[] = "Fila " . ($index + 2) . ": Teléfono duplicado ({$client->full_name}) para {$request->scheduled_date} - omitida";
                        $skipped++;
                        continue;
                    }

                    $task = Task::create([
                        'company_id' => $companyId,
                        'client_id' => $client->id,
                        'title' => "Recuperación - {$client->full_name}",
                        'description' => "Cuenta #{$client->order_number}",
                        'status' => 'pending',
                        'scheduled_date' => $request->scheduled_date,
                    ]);

                    $assignedUser = null;
                    if (!empty($usuario)) {
                        $assignedUser = $this->findUserByName($usuario);
                        if (!$assignedUser) {
                            $errors[] = "Fila " . ($index + 2) . ": Usuario '{$usuario}' no encontrado (se asigna al que sube el archivo)";
                        }
                    }
                    // Fallback: si no hay USUARIO en el Excel o no se encontró, asignar al usuario que sube el archivo
                    if (!$assignedUser) {
                        $assignedUser = $request->user();
                    }
                    if ($assignedUser) {
                        $task->update([
                            'assigned_to' => $assignedUser->id,
                            'status' => 'assigned',
                            'scheduled_date' => $request->scheduled_date,
                        ]);
                        $client->update(['status' => 'assigned']);
                        TaskAssignment::create([
                            'task_id' => $task->id,
                            'user_id' => $assignedUser->id,
                            'assigned_by' => $request->user()->id,
                            'assignment_type' => 'import',
                        ]);
                    }

                    // Protección anti-repetición: SOLO 1 mensaje por cliente/teléfono.
                    // - Si el mismo teléfono ya fue notificado alguna vez (BD), no se repite.
                    // - Si el mismo teléfono ya está en cola en ESTE import, no se duplica.
                    $phoneSuffix = substr(preg_replace('/\D/', '', $phone), -8);
                    $alreadyQueued = false;
                    foreach ($notifiable as $q) {
                        if (substr(preg_replace('/\D/', '', $q['client']->phone), -8) === $phoneSuffix) {
                            $alreadyQueued = true;
                            break;
                        }
                    }
                    if ($alreadyQueued) {
                        $errors[] = "Fila " . ($index + 2) . ": {$client->full_name} duplicado en archivo (mismo teléfono ya en cola) - no se repite";
                        $alreadyNotified++;
                        $successful++;
                        continue;
                    }
                    if (WhatsAppMessage::where('direction', 'outbound')
                        ->where(function ($q) use ($client, $phoneSuffix) {
                            $q->where('client_id', $client->id)
                              ->orWhere('to_phone', 'like', '%' . $phoneSuffix);
                        })
                        ->where('status', '!=', 'failed')
                        ->exists()) {
                        $errors[] = "Fila " . ($index + 2) . ": {$client->full_name} ya notificado por WhatsApp anteriormente - no se repite (1 por cliente)";
                        $alreadyNotified++;
                        $successful++; // la tarea sí se crea, solo no se re-notifica
                        continue;
                    }

                    $tasks[] = $task;
                    $notifiable[] = ['client' => $client, 'companyId' => $companyId];
                    $successful++;
                } catch (\Exception $e) {
                    $errors[] = "Fila " . ($index + 2) . ": " . $e->getMessage();
                    $failed++;
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        // Notificar a los clientes importados via WhatsApp una vez persistidos
        // PLAN B: todo sale de la línea central (Meta WABA); si un agente tiene línea
        // propia (whatsapp_sender_id) se usa esa. Respuestas -> reenvío al celular
        // del agente asignado (webhook).
        $notified = 0;
        $notifyFailed = 0;
        $companiesById = [];
        $whatsapp = new WhatsAppService();
        $sentPhones = [];
        foreach ($notifiable as $item) {
            $suffix = substr(preg_replace('/\D/', '', $item['client']->phone), -8);
            if (isset($sentPhones[$suffix])) {
                continue; // ya enviado en este mismo lote
            }
            $sentPhones[$suffix] = true;
            $companyId = $item['companyId'];
            if (!isset($companiesById[$companyId])) {
                $companiesById[$companyId] = Company::find($companyId);
            }

            // Línea del agente si la tiene; si no, la central (fallback en servicio)
            $latestTask = $item['client']->tasks()->latest('updated_at')->first();
            $senderId = null;
            if ($latestTask?->assigned_to) {
                $agent = \App\Models\User::find($latestTask->assigned_to);
                $senderId = $agent?->whatsappSenderId();
            }

            $result = $whatsapp->sendToClient(
                $item['client'],
                $companiesById[$companyId],
                'equipment_recovery_notification',
                $senderId
            );
            if ($result['ok']) {
                $notified++;
            } else {
                $notifyFailed++;
                $errors[] = "Aviso no enviado a {$item['client']->full_name}: {$result['error']}";
            }
        }

        // Resumen diario por agente (Plan B). Se puede apagar con
        // WHATSAPP_AGENT_SUMMARY=false en .env para no llenar los celulares.
        $agentNotified = 0;
        if (config('services.whatsapp.agent_summary', true)) {
            $agentsSummary = [];
            foreach ($notifiable as $i) {
                $t = $i['client']->tasks()->latest('updated_at')->first();
                if ($t?->assigned_to) {
                    $agentsSummary[$t->assigned_to][] = $i['client'];
                }
            }
            foreach ($agentsSummary as $agentId => $clients) {
                $agent = \App\Models\User::find($agentId);
                if (!$agent || empty($agent->phone)) continue;
                $agentPhone = '+' . ltrim(preg_replace('/\D/', '', $agent->phone), '+');
                if (!str_starts_with($agentPhone, '+507')) {
                    $d = preg_replace('/\D/', '', $agentPhone);
                    $d = ltrim($d, '0');
                    if (!str_starts_with($d, '507')) $d = '507' . $d;
                    $agentPhone = '+' . $d;
                }
                // Detalle: nombre + cuenta + teléfono de cada cliente asignado
                $lineas = collect($clients)->map(fn ($c) => '• ' . $c->full_name
                    . ' | Cta: ' . ($c->order_number ?? '—')
                    . ' | Tel: ' . $c->phone)->implode("\n");
                $textoAgente = "📋 *Tareas asignadas a {$agent->name}* (" . count($clients) . ")\n"
                    . "Línea que te notificará: central *+507 6083-2368*\n\n{$lineas}\n\n"
                    . "Cuando un cliente responda, te reenviamos su mensaje aquí con su nombre.";
                $resAgente = $whatsapp->sendTextReply($agentPhone, $textoAgente);
                if ($resAgente['ok']) $agentNotified++;
                else $errors[] = "Aviso al agente {$agent->name} no enviado: {$resAgente['error']}";
            }
        }

        $import->markCompleted($successful, $failed, $errors);

        return response()->json([
            'message' => 'Importación completada',
            'successful' => $successful,
            'skipped' => $skipped,
            'failed' => $failed,
            'notified' => $notified,
            'notify_failed' => $notifyFailed,
            'already_notified' => $alreadyNotified,
            'agent_notified' => $agentNotified,
            'errors' => $errors,
        ]);
    }

    public function clearAll(Request $request)
    {
        if ($request->user()->role === 'agent') {
            return response()->json(['message' => 'Solo el administrador puede limpiar los datos'], 403);
        }

        $tables = ['task_comments', 'task_evidence', 'task_assignments', 'whatsapp_messages', 'routes', 'tasks', 'clients'];
        $counts = [];

        DB::transaction(function () use (&$counts, $tables) {
            foreach ($tables as $table) {
                $counts[$table] = DB::table($table)->count();
                DB::table($table)->delete();
            }
            $counts['excel_imports'] = DB::table('excel_imports')->count();
            DB::table('excel_imports')->delete();
            $counts['reports'] = DB::table('reports')->count();
            DB::table('reports')->delete();

            if (DB::connection()->getDriverName() === 'sqlite') {
                DB::table('sqlite_sequence')
                    ->whereIn('name', array_merge($tables, ['excel_imports', 'reports']))
                    ->delete();
            }
        });

        return response()->json([
            'message' => 'Base de datos limpiada correctamente',
            'deleted' => $counts,
        ]);
    }

    private function autoMapHeaders(array $headers): array
    {
        $fields = [
            'suscriptor' => '/suscriptor|susc|subscriber/i',
            'full_name' => '/nombres?|first\s*name|cliente\s*nombre|nombre\s*del\s*cliente/i',
            'lugar' => '/lugar|sitio|sector|barrio|direccion|address/i',
            'cliente' => '/^cliente$|client\s*id|^id$|^code$|codigo/i',
            'cedula' => '/ced|identif|dni|^ci$/i',
            'cuenta' => '/^cuenta$|^account$|^cu$|^nro$|^numero$|^number$|^order$|^pedido$/i',
            'telefono_residencia_1' => '/residencia\s*1|residencial\s*1|t\.residencia\s*1|tel\.?\s*res\s*1|fono.*1|phone.*1|residencia/i',
            'telefono_residencia_2' => '/residencia\s*2|residencial\s*2|t\.residencia\s*2|tel\.?\s*res\s*2|tel.*2|fono.*2|phone.*2/i',
            'numero_celular' => '/celular|cel|mobile|num.*cel/i',
            'numero_contacto' => '/contacto|contact.*number|num.*contact/i',
            'provincia' => '/provincia|prov\b/i',
            'distrito' => '/distrito|district/i',
            'corregimiento' => '/corregimiento|correg|corr/i',
            'barrio' => '/barrio|neighbor/i',
            'usuario' => '/usuario|user|agente|agent/i',
            'empresa' => '/empresa|compan[ií]a|operador|proveedor|marca/i',
        ];

        $mapping = [];
        $used = [];
        foreach ($fields as $field => $pattern) {
            $mapping[$field] = '';
            foreach ($headers as $h) {
                if ($h === '' || isset($used[$h])) continue;
                if (preg_match($pattern, $h)) {
                    $mapping[$field] = $h;
                    $used[$h] = true;
                    break;
                }
            }
        }

        // Fallback posicional para la plantilla nueva de 7 columnas:
        // SUSCRIPTOR, NOMBRE, T.RESIDENCIA 1, T.RESIDENCIA 2, LUGAR, USUARIO, EMPRESA
        $order = [
            0 => 'suscriptor', 1 => 'full_name', 2 => 'telefono_residencia_1',
            3 => 'telefono_residencia_2', 4 => 'lugar', 5 => 'usuario', 6 => 'empresa',
        ];
        foreach ($order as $i => $field) {
            if ($mapping[$field] === '' && isset($headers[$i])) {
                $h = $headers[$i];
                if (!isset($used[$h])) {
                    $mapping[$field] = $h;
                    $used[$h] = true;
                }
            }
        }

        return $mapping;
    }

    public function downloadTemplate(Request $request)
    {
        $data = [
            ['SUSCRIPTOR', 'NOMBRE', 'T.RESIDENCIA 1', 'T.RESIDENCIA 2', 'LUGAR', 'USUARIO', 'EMPRESA'],
            ['95257623', 'Juan Pérez', '0991234567', '', 'El Vecino, Sucre, Cuenca', 'Juan Perez', 'Tigo'],
            ['96312695', 'María García', '0987654321', '', 'Los Ceibos, Tarqui, Guayaquil', 'Maria Garcia', 'Mas Movil'],
            ['97520348', 'Carlos López', '0976543210', '0976500011', '9 De Octubre, Puerto Bolivar, Machala', 'Carlos Lopez', 'Telca'],
        ];

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($data, null, 'A1');

        // Limpia cualquier salida previa y desactiva compresión para que el
        // binario .xlsx llegue intacto (en hosting gratuito el buffer/gzip
        // corrompe el archivo y Excel muestra "formato no válido").
        if (ob_get_level() > 0) {
            while (ob_get_level() > 0) { ob_end_clean(); }
        }
        if (function_exists('apache_setenv')) { @apache_setenv('no-gzip', '1'); }
        @ini_set('zlib.output_compression', 'Off');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        // En hosting gratuito sys_get_temp_dir() puede estar bloqueado por
        // open_basedir; usa storage/app que sí es escribible.
        $dir = storage_path('app');
        if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
        $tempFile = $dir . '/template_' . uniqid('', true) . '.xlsx';
        $writer->save($tempFile);

        return response()->download($tempFile, 'plantilla_clientes.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Length' => (string) filesize($tempFile),
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ])->deleteFileAfterSend(true);
    }

    private function stripInvalid(?string $value): string
    {
        if ($value === null || trim($value) === '' || in_array(strtolower(trim($value)), ['n/a', 'na', 'null', '-', 'ninguno'], true)) {
            return '';
        }
        return trim($value);
    }

    private function findUserByName(string $name): ?User
    {
        $needle = $this->fold($name);

        $exact = User::where('is_active', true)->get()
            ->first(fn(User $u) => $this->fold($u->name) === $needle);
        if ($exact) return $exact;

        return User::where('is_active', true)->get()
            ->first(fn(User $u) => str_contains($this->fold($u->name), $needle)
                || str_contains($needle, $this->fold($u->name)));
    }

    private function findCompanyByName(string $name): ?\App\Models\Company
    {
        $needle = $this->fold($name);

        foreach (\App\Models\Company::where('is_active', true)->get() as $company) {
            if ($this->fold((string) $company->name) === $needle
                || $this->fold((string) $company->code) === $needle) {
                return $company;
            }
        }

        foreach (\App\Models\Company::where('is_active', true)->get() as $company) {
            if (str_contains($needle, $this->fold((string) $company->name))
                || str_contains($this->fold((string) $company->name), $needle)) {
                return $company;
            }
        }

        return null;
    }

    private function fold(string $s): string
    {
        $s = strtr(mb_strtolower(trim($s)), [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ü' => 'u', 'ñ' => 'n', 'ä' => 'a', 'ë' => 'e', 'ï' => 'i',
            'ö' => 'o', 'ü' => 'u',
        ]);
        return preg_replace('/\s+/', ' ', trim($s));
    }
}