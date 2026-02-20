<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'original_filename',
        'stored_path',
        'status',
        'invoice_number',
        'invoice_date',
        'due_date',
        'currency',
        'vendor_name',
        'vendor_address',
        'vendor_tax_id',
        'customer_name',
        'customer_address',
        'customer_tax_id',
        'subtotal',
        'tax_amount',
        'total',
        'error_message',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class, 'invoice_id');
    }
}
