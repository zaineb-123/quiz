<?php

namespace App\Models;

use Database\Factories\ClassroomFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Classroom extends Model
{
    /** @use HasFactory<ClassroomFactory> */
    use HasFactory, SoftDeletes;

    /** Characters without look-alikes (no 0/O, 1/I/L) so codes are easy to read aloud and type. */
    private const CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    private const CODE_LENGTH = 8;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'academic_year',
        'join_code',
        'join_enabled',
    ];

    protected function casts(): array
    {
        return [
            'join_enabled' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Classroom $classroom): void {
            if (blank($classroom->join_code)) {
                $classroom->join_code = static::generateJoinCode();
            }
        });
    }

    /**
     * Generate a join code that is unique, including among soft-deleted classrooms
     * (the database unique index also covers them).
     */
    public static function generateJoinCode(): string
    {
        do {
            $code = '';
            for ($i = 0; $i < self::CODE_LENGTH; $i++) {
                $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
            }
        } while (static::withTrashed()->where('join_code', $code)->exists());

        return $code;
    }

    public static function normalizeCode(string $code): string
    {
        return Str::upper(preg_replace('/[\s\-]+/', '', $code) ?? '');
    }

    public function regenerateJoinCode(): void
    {
        $this->update(['join_code' => static::generateJoinCode()]);
    }

    public function hasStudent(User $user): bool
    {
        return $this->students()->whereKey($user->getKey())->exists();
    }

    /** @return BelongsTo<User, $this> */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsToMany<User, $this> */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'classroom_student')
            ->withPivot('joined_at')
            ->withTimestamps();
    }

    /** @return HasMany<Subject, $this> */
    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class);
    }

    /** @return HasMany<ExamAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(ExamAssignment::class);
    }
}
