<?php

namespace Database\Seeders;

use App\Events\LowStockDetected;
use App\Models\Part;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SmartFixDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $lowStockParts = DB::transaction(function (): array {
            $technicianData = [
                [
                    'name' => 'Teknisi Utama',
                    'email' => 'teknisi@smartfix.com',
                ],
                [
                    'name' => 'Andi Pratama',
                    'email' => 'andi@smartfix.com',
                ],
                [
                    'name' => 'Siti Rahma',
                    'email' => 'siti@smartfix.com',
                ],
            ];

            $technicians = [];

            foreach ($technicianData as $technicianAttributes) {
                $technicians[$technicianAttributes['email']] = User::query()->updateOrCreate(
                    ['email' => $technicianAttributes['email']],
                    [
                        'name' => $technicianAttributes['name'],
                        'password' => Hash::make('password123'),
                        'role' => 'technician',
                        'email_verified_at' => now(),
                    ],
                );
            }

            $partData = [
                ['serial_number' => 'SF-IP14-LCD-001', 'name' => 'LCD OLED iPhone 14 Pro', 'category' => 'LCD', 'stock_quantity' => 2, 'min_stock_level' => 3, 'base_price_idr' => 3450000],
                ['serial_number' => 'SF-IP11-BAT-001', 'name' => 'Baterai iPhone 11', 'category' => 'Baterai', 'stock_quantity' => 8, 'min_stock_level' => 3, 'base_price_idr' => 425000],
                ['serial_number' => 'SF-S22-LCD-001', 'name' => 'LCD Dynamic AMOLED Samsung Galaxy S22', 'category' => 'LCD', 'stock_quantity' => 5, 'min_stock_level' => 2, 'base_price_idr' => 2850000],
                ['serial_number' => 'SF-S22-BAT-001', 'name' => 'Baterai Samsung Galaxy S22', 'category' => 'Baterai', 'stock_quantity' => 1, 'min_stock_level' => 2, 'base_price_idr' => 390000],
                ['serial_number' => 'SF-SAM-A54-FLEX-001', 'name' => 'Flexible Charger Samsung Galaxy A54', 'category' => 'Flexible Charger', 'stock_quantity' => 3, 'min_stock_level' => 4, 'base_price_idr' => 185000],
                ['serial_number' => 'SF-XRN12-LCD-001', 'name' => 'LCD Xiaomi Redmi Note 12', 'category' => 'LCD', 'stock_quantity' => 0, 'min_stock_level' => 2, 'base_price_idr' => 675000],
                ['serial_number' => 'SF-XRN10-BAT-001', 'name' => 'Baterai Xiaomi Redmi Note 10', 'category' => 'Baterai', 'stock_quantity' => 7, 'min_stock_level' => 3, 'base_price_idr' => 210000],
                ['serial_number' => 'SF-XRN11-CAM-001', 'name' => 'Kamera belakang Xiaomi Redmi Note 11', 'category' => 'Kamera', 'stock_quantity' => 4, 'min_stock_level' => 2, 'base_price_idr' => 310000],
                ['serial_number' => 'SF-OPR8-LCD-001', 'name' => 'LCD AMOLED Oppo Reno 8', 'category' => 'LCD', 'stock_quantity' => 1, 'min_stock_level' => 2, 'base_price_idr' => 1250000],
                ['serial_number' => 'SF-OPA78-FLEX-001', 'name' => 'Flexible Charger Oppo A78', 'category' => 'Flexible Charger', 'stock_quantity' => 9, 'min_stock_level' => 3, 'base_price_idr' => 165000],
                ['serial_number' => 'SF-OPA57-IC-001', 'name' => 'IC Power Oppo A57', 'category' => 'IC Power', 'stock_quantity' => 6, 'min_stock_level' => 2, 'base_price_idr' => 275000],
                ['serial_number' => 'SF-IP12-CAM-001', 'name' => 'Kamera belakang iPhone 12', 'category' => 'Kamera', 'stock_quantity' => 3, 'min_stock_level' => 1, 'base_price_idr' => 875000],
            ];

            $parts = collect($partData)->map(function (array $partAttributes): Part {
                return Part::query()->updateOrCreate(
                    ['serial_number' => $partAttributes['serial_number']],
                    $partAttributes + ['base_price_usd' => null],
                );
            });

            $workOrderData = [
                ['wo_number' => 'WO-SF-2026-0001', 'technician_email' => 'teknisi@smartfix.com', 'customer_name' => 'Budi Santoso', 'device_model' => 'iPhone 13', 'device_issue' => 'Layar retak dan sentuhan tidak merespons di sisi kanan.', 'status' => 'PENDING', 'estimated_cost' => 1450000],
                ['wo_number' => 'WO-SF-2026-0002', 'technician_email' => 'andi@smartfix.com', 'customer_name' => 'Nadia Putri', 'device_model' => 'Samsung Galaxy S22', 'device_issue' => 'Baterai cepat habis dan perangkat terasa panas saat mengisi daya.', 'status' => 'PENDING', 'estimated_cost' => 690000],
                ['wo_number' => 'WO-SF-2026-0003', 'technician_email' => 'teknisi@smartfix.com', 'customer_name' => 'Rizky Firmansyah', 'device_model' => 'Samsung Galaxy A54', 'device_issue' => 'Tidak dapat mengisi daya kecuali kabel ditekan pada posisi tertentu.', 'status' => 'IN_PROGRESS', 'estimated_cost' => 385000],
                ['wo_number' => 'WO-SF-2026-0004', 'technician_email' => 'andi@smartfix.com', 'customer_name' => 'Dewi Anggraini', 'device_model' => 'Xiaomi Redmi Note 12', 'device_issue' => 'Layar menampilkan garis hijau dan sesekali mati sendiri.', 'status' => 'IN_PROGRESS', 'estimated_cost' => 925000],
                ['wo_number' => 'WO-SF-2026-0005', 'technician_email' => 'siti@smartfix.com', 'customer_name' => 'Fajar Maulana', 'device_model' => 'Oppo Reno 8', 'device_issue' => 'Layar tidak menyala setelah perangkat terjatuh.', 'status' => 'IN_PROGRESS', 'estimated_cost' => 1590000],
                ['wo_number' => 'WO-SF-2026-0006', 'technician_email' => 'siti@smartfix.com', 'customer_name' => 'Lina Kartika', 'device_model' => 'iPhone 12', 'device_issue' => 'Kamera belakang bergetar dan hasil foto tidak fokus.', 'status' => 'COMPLETED', 'estimated_cost' => 1125000],
                ['wo_number' => 'WO-SF-2026-0007', 'technician_email' => 'teknisi@smartfix.com', 'customer_name' => 'Agus Setiawan', 'device_model' => 'Xiaomi Poco X5', 'device_issue' => 'Speaker bawah mengeluarkan suara pecah saat volume tinggi.', 'status' => 'COMPLETED', 'estimated_cost' => 325000],
                ['wo_number' => 'WO-SF-2026-0008', 'technician_email' => 'andi@smartfix.com', 'customer_name' => 'Maya Lestari', 'device_model' => 'Oppo A78', 'device_issue' => 'Port pengisian daya longgar dan koneksi sering terputus.', 'status' => 'CANCELLED', 'estimated_cost' => 275000],
            ];

            $workOrdersByTechnician = [];

            foreach ($workOrderData as $workOrderAttributes) {
                $workOrder = WorkOrder::query()->updateOrCreate(
                    ['wo_number' => $workOrderAttributes['wo_number']],
                    [
                        'technician_id' => $technicians[$workOrderAttributes['technician_email']]->id,
                        'customer_name' => $workOrderAttributes['customer_name'],
                        'device_model' => $workOrderAttributes['device_model'],
                        'device_issue' => $workOrderAttributes['device_issue'],
                        'status' => $workOrderAttributes['status'],
                        'estimated_cost' => $workOrderAttributes['estimated_cost'],
                    ],
                );

                if ($workOrder->status === 'IN_PROGRESS') {
                    $workOrdersByTechnician[$workOrderAttributes['technician_email']] = $workOrder;
                }
            }

            $checkoutPartSerialNumbers = [
                'teknisi@smartfix.com' => 'SF-SAM-A54-FLEX-001',
                'andi@smartfix.com' => 'SF-XRN12-LCD-001',
                'siti@smartfix.com' => 'SF-OPR8-LCD-001',
            ];

            foreach ($technicians as $email => $technician) {
                $workOrder = $workOrdersByTechnician[$email];
                $part = Part::query()
                    ->where('serial_number', $checkoutPartSerialNumbers[$email])
                    ->firstOrFail();

                WorkOrderItem::query()->updateOrCreate(
                    [
                        'work_order_id' => $workOrder->id,
                        'part_id' => $part->id,
                    ],
                    ['quantity' => 1],
                );

                UserNotification::query()->updateOrCreate(
                    [
                        'user_id' => $technician->id,
                        'type' => 'part_checkout',
                        'title' => 'Pengeluaran suku cadang',
                    ],
                    [
                        'message' => "1 unit {$part->name} telah dikeluarkan untuk tiket {$workOrder->wo_number}.",
                        'read_at' => null,
                    ],
                );

                UserNotification::query()->updateOrCreate(
                    [
                        'user_id' => $technician->id,
                        'type' => 'sla_reminder',
                        'title' => 'Pengingat SLA 5 jam',
                    ],
                    [
                        'message' => "Tiket {$workOrder->wo_number} masih dalam proses setelah 5 jam.",
                        'read_at' => null,
                    ],
                );
            }

            return $parts
                ->filter(fn (Part $part): bool => $part->stock_quantity <= $part->min_stock_level)
                ->all();
        });

        foreach ($lowStockParts as $part) {
            event(new LowStockDetected($part));
        }
    }
}
