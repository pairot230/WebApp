<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['dorm_id', 'academic_year_id', 'user_id', 'type', 'category', 'title', 'amount', 'transaction_date', 'description', 'status', 'is_public', 'created_by', 'voided_by', 'voided_at', 'void_reason'])]
class FinancialTransaction extends Model
{
    public const STATUS_LABELS = ['pending' => 'รอยืนยัน', 'posted' => 'บันทึกแล้ว', 'voided' => 'ยกเลิก'];

    public const TYPE_LABELS = ['income' => 'รายรับ', 'expense' => 'รายจ่าย'];

    public const INCOME_CATEGORIES = ['membership' => 'ค่าส่วนกลาง', 'other' => 'รายรับอื่น'];

    public const AUDIT_FIELDS = ['dorm_id', 'academic_year_id', 'user_id', 'type', 'category', 'title', 'amount', 'transaction_date', 'description', 'status', 'is_public', 'created_by', 'voided_by', 'voided_at', 'void_reason'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'is_public' => 'boolean',
            'transaction_date' => 'date',
            'voided_at' => 'datetime',
            'dorm_id' => 'integer', 'academic_year_id' => 'integer', 'user_id' => 'integer',
        ];
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->role === 'superadmin') {
            return $query;
        }
        $query->where('dorm_id', $user->dorm_id);
        if ($user->role === 'admin') {
            $query->where(function ($members): void {
                $members->whereNull('user_id')->orWhereHas('member', fn ($member) => $member->where('role', '!=', 'superadmin'));
            });
        }

        return $query;
    }

    public static function minorUnits(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    public static function formatMinorUnits(int $amount): string
    {
        $absolute = abs($amount);

        return ($amount < 0 ? '-' : '').intdiv($absolute, 100).'.'.str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function totals(Builder $query): array
    {
        $income = 0;
        $expense = 0;
        foreach ((clone $query)->where('status', 'posted')->select(['type', 'amount'])->cursor() as $record) {
            if ($record->type === 'income') {
                $income += self::minorUnits($record->amount);
            } elseif ($record->type === 'expense') {
                $expense += self::minorUnits($record->amount);
            }
        }

        return ['income' => self::formatMinorUnits($income), 'expense' => self::formatMinorUnits($expense), 'balance' => self::formatMinorUnits($income - $expense)];
    }

    public function dorm(): BelongsTo
    {
        return $this->belongsTo(Dorm::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function membershipPayment(): HasOne
    {
        return $this->hasOne(MembershipPayment::class);
    }
}
