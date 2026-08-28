<?php

namespace App\Exports;

use App\Models\EquipmentScan;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class EquipmentScansExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithTitle
{
    public function __construct(
        protected $query,
        protected string $sheetTitle = 'Escaneos',
    ) {
    }

    public function query()
    {
        return $this->query;
    }

    public function title(): string
    {
        return $this->sheetTitle;
    }

    public function headings(): array
    {
        return [
            'Fecha escaneo',
            'Hora escaneo',
            'Código equipo',
            'Cliente',
            'Cuenta / Suscriptor',
            'Teléfono',
            'Dirección',
            'Empresa',
            'Agente que escaneó',
            'Método',
            'Estado tarea',
            'Notas',
        ];
    }

    /**
     * @param EquipmentScan $scan
     */
    public function map($scan): array
    {
        $client = $scan->client;
        $task = $scan->task;

        return [
            optional($scan->scanned_at)->format('Y-m-d'),
            optional($scan->scanned_at)->format('H:i:s'),
            $scan->code,
            $client?->full_name ?? ($task?->title ?? '—'),
            $client?->order_number ?? ($task?->description ?? '—'),
            $client?->phone ?? '—',
            $client?->address ?? '—',
            $scan->company?->name ?? '—',
            $scan->scanner?->name ?? '—',
            $scan->method === 'manual' ? 'Manual' : 'Cámara',
            $task?->status ?? '—',
            $scan->notes,
        ];
    }
}
