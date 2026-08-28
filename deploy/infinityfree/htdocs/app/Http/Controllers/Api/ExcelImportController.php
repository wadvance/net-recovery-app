<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\ExcelImport;
use App\Models\Client;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\User;
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
        $errors = [];
        $tasks = [];
        $notifiable = [];

        $replace = $request->boolean('replace', true);
        $clearAll = $request->boolean('clear_all', false);

        DB::beginTransaction();
        try {
            // --- Limpiar por día: al re-importar, el día anterior no
            //     queda y se empieza de cero (replace=true, default). ---
            if ($replace && $import->company_id) {
                $taskWipe = Task::where('company_id', $import->company_id);
                if ($request->scheduled_date) {
                    if ($clearAll) {
                        $taskWipe->whereDate('scheduled_date', '>=', $request->scheduled_date);
                        $scope = "a partir de {$request->scheduled_date}";
                    } else {
                        $taskWipe->whereDate('scheduled_date', $request->scheduled_date);
                        $scope = "el {$request->scheduled_date}";
                    }
                    $wiped = $taskWipe->delete();
                    $errors[] = "Antes de importar: {$wiped} tareas previas del {$scope} eliminadas (replace=true)";
                } elseif ($clearAll) {
                    $wiped = $taskWipe->delete();
                    $errors[] = "Antes de importar: {$wiped} tareas previas de la empresa eliminadas (clear_all=true)";
                } else {
                    $errors[] = "replace=true pero sin scheduled_date: no se limpiaron tareas previas (envíe date para limpiar el día)";
                }
            }

            foreach ($rows as $index => $row) {
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

                    $corregimiento = $this->stripInvalid($data['corregimiento'] ?? '');
                    $distrito = $this->stripInvalid($data['distrito'] ?? '');
                    $provincia = $this->stripInvalid($data['provincia'] ?? '');
                    $barrio = $this->stripInvalid($data['barrio'] ?? '');

                    $address = collect([$corregimiento, $distrito, $provincia])
                        ->filter(fn($v) => $v !== '')->unique()->implode(', ');

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

                    if (!empty($usuario)) {
                        $assignedUser = $this->findUserByName($usuario);
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
                        } else {
                            $errors[] = "Fila " . ($index + 2) . ": Usuario '{$usuario}' no encontrado (tarea creada sin asignar)";
                        }
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

        // Notificar a los clientes importados via WhatsApp (Zavu) una vez
        // persistidos. Funciona cuando haya un sender de WhatsApp conectado y
        // la plantilla aprobada; si no, el servicio informa el motivo en errors.
        $notified = 0;
        $notifyFailed = 0;
        $companiesById = [];
        $whatsapp = new WhatsAppService();
        foreach ($notifiable as $item) {
            $companyId = $item['companyId'];
            if (!isset($companiesById[$companyId])) {
                $companiesById[$companyId] = Company::find($companyId);
            }
            $result = $whatsapp->sendToClient(
                $item['client'],
                $companiesById[$companyId],
                'equipment_recovery_notification'
            );
            if ($result['ok']) {
                $notified++;
            } else {
                $notifyFailed++;
                $errors[] = "Aviso no enviado a {$item['client']->full_name}: {$result['error']}";
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
            'full_name' => '/nombres?|first\s*name|cliente\s*nombre/i',
            'cliente' => '/^cliente|client\s*id|^id$|^code$|codigo/i',
            'cedula' => '/ced|identif|dni|^ci$/i',
            'cuenta' => '/cuenta|account|^cu$|nro|numero|number|order|pedido/i',
            'telefono_residencia_1' => '/residencia\s*1|residencial\s*1|t\.residencia\s*1|tel\.?\s*res\s*1|fono.*1|phone.*1/i',
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

        $order = [
            0 => 'suscriptor', 1 => 'full_name', 2 => 'cliente', 3 => 'cedula',
            4 => 'cuenta', 5 => 'telefono_residencia_1', 6 => 'telefono_residencia_2',
            7 => 'provincia', 8 => 'distrito', 9 => 'corregimiento', 10 => 'barrio', 11 => 'usuario',
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
            ['SUSCRIPTOR', 'NOMBRE', 'CLIENTE', 'CEDULA', 'CUENTA', 'T.RESIDENCIA 1', 'T.RESIDENCIA 2', 'PROVINCIA', 'DISTRITO', 'CORREGIMIENTO', 'BARRIO', 'USUARIO'],
            ['95257623', 'Juan Pérez', 'CLI-0001', '0102030405', 'CU-0001', '0991234567', '', 'Azuay', 'Cuenca', 'Sucre', 'El Vecino', 'Juan Perez'],
            ['96312695', 'María García', 'CLI-0002', '0987654321', 'CU-0002', '0987654321', '', 'Guayas', 'Guayaquil', 'Tarqui', 'Los Ceibos', 'Maria Garcia'],
            ['97520348', 'Carlos López', 'CLI-0003', '0104050617', 'CU-0003', '0976543210', '0976500011', 'El Oro', 'Machala', 'Puerto Bolivar', '9 De Octubre', 'Carlos Lopez'],
        ];

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($data, null, 'A1');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $tempFile = tempnam(sys_get_temp_dir(), 'template_') . '.xlsx';
        $writer->save($tempFile);

        return response()->download($tempFile, 'plantilla_clientes.xlsx')->deleteFileAfterSend(true);
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