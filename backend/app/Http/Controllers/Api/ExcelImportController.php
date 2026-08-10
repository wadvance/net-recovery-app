<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
        return response()->json($query->paginate($request->get('per_page', 15)));
    }

    public function show(Request $request, ExcelImport $import)
    {
        return response()->json($import->load(['company', 'importedBy']));
    }

    public function update(Request $request, ExcelImport $import)
    {
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
        if ($request->user()->role === 'agent') {
            return response()->json(['message' => 'Solo el administrador puede eliminar importaciones'], 403);
        }

        $filePath = Storage::disk('local')->path($import->stored_filename);
        if (is_file($filePath)) {
            @unlink($filePath);
        }

        $import->delete();

        return response()->json(['message' => 'Importación eliminada correctamente']);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:51200',
            'company_id' => 'required|exists:companies,id',
        ]);

        $file = $request->file('file');
        $storedFilename = 'imports/' . time() . '_' . $file->getClientOriginalName();
        $file->storeAs('imports', basename($storedFilename), 'local');

        $spreadsheet = Excel::toArray([], $file);
        $rows = $spreadsheet[0] ?? [];
        $totalRows = max(count($rows) - 1, 0);
        $headers = array_map(fn($h) => trim((string) $h), array_shift($rows) ?? []);

        $import = ExcelImport::create([
            'company_id' => $request->company_id,
            'imported_by' => $request->user()->id,
            'original_filename' => $file->getClientOriginalName(),
            'stored_filename' => $storedFilename,
            'total_rows' => $totalRows,
        ]);

        return response()->json([
            'import' => $import,
            'headers' => array_values(array_filter($headers, fn($h) => $h !== '')),
            'message' => 'Archivo subido',
        ], 201);
    }

    public function process(Request $request, ExcelImport $import)
    {
        $request->validate([
            'column_mapping' => 'required|array',
            'scheduled_date' => 'nullable|date',
        ]);

        $mapping = $request->column_mapping;
        $filePath = Storage::disk('local')->path($import->stored_filename);
        $rows = Excel::toArray([], $filePath)[0] ?? [];
        $excelHeaders = array_shift($rows);

        $successful = 0;
        $failed = 0;
        $errors = [];
        $tasks = [];
        $notifiable = [];

        DB::beginTransaction();
        try {
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

                    if ($fullName === '' || $cuenta === '') {
                        $errors[] = "Fila " . ($index + 2) . ": Faltan campos obligatorios (nombre, cuenta)";
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

                    $client = Client::create([
                        'company_id' => $companyId,
                        'order_number' => $cuenta,
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
                            'cuenta' => $cuenta,
                            'provincia' => $provincia,
                            'distrito' => $distrito,
                            'corregimiento' => $corregimiento,
                            'barrio' => $barrio,
                            'numero_celular' => $data['numero_celular'] ?? null,
                            'numero_contacto' => $data['numero_contacto'] ?? null,
                        ],
                    ]);

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
            'failed' => $failed,
            'notified' => $notified,
            'notify_failed' => $notifyFailed,
            'errors' => $errors,
        ]);
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