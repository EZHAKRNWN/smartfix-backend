<?php

namespace App\Http\Controllers;

use App\Models\BlockchainLog;
use App\Models\Part;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    /**
     * List parts with optional search and category filter.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Part::query();

        // Search by name or serial_number
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('serial_number', 'LIKE', "%{$search}%");
            });
        }

        // Filter by category
        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        $parts = $query->latest()->get();

        return response()->json([
            'success' => true,
            'data' => $parts,
        ], 200);
    }

    /**
     * Store a new part and record a blockchain log entry.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'serial_number' => 'required|string|unique:parts,serial_number',
            'name' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'stock_quantity' => 'required|integer|min:0',
            'base_price_idr' => 'required|numeric|min:0',
        ]);

        // Create the part record
        $part = Part::create($validated);

        // --- Simple Blockchain Hashing ---
        // Retrieve the last block's current_hash, or use genesis hash
        $lastLog = BlockchainLog::orderBy('id', 'desc')->first();
        $previousHash = $lastLog
            ? $lastLog->current_hash
            : '0000000000000000000000000000000000000000000000000000000000000000';

        // Combine previous hash with part details and timestamp
        $dataToHash = implode('|', [
            $previousHash,
            $part->serial_number,
            $part->name,
            $part->category,
            $part->stock_quantity,
            $part->base_price_idr,
            $request->user()->id,
            now()->toIso8601String(),
        ]);

        // Generate SHA-256 hash
        $currentHash = hash('sha256', $dataToHash);

        // Persist the blockchain log entry
        $blockchainLog = BlockchainLog::create([
            'part_id' => $part->id,
            'action' => 'PART_REGISTERED',
            'previous_hash' => $previousHash,
            'current_hash' => $currentHash,
            'user_id' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Part berhasil ditambahkan dan diamankan dengan Blockchain.',
            'part' => $part,
            'blockchain_hash' => $currentHash,
        ], 201);
    }
}
