<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Course extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'thumbnail',
        'category_id',
        'instructor_id',
        'price',
        'level',
        'status',
        'duration_hours',
        'max_students',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'duration_hours' => 'integer',
        'max_students' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Course $course) {
            if (empty($course->slug)) {
                $course->slug = Str::slug($course->title);
            }
        });
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function instructor()
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function students()
    {
        return $this->belongsToMany(User::class, 'enrollments')
            ->withPivot('status', 'progress', 'completed_at')
            ->withTimestamps();
    }

    public function isFullyEnrolled(): bool
    {
        if ($this->max_students === null) {
            return false;
        }

        return $this->enrollments()->where('status', '!=', 'cancelled')->count() >= $this->max_students;
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeByLevel($query, string $level)
    {
        return $query->where('level', $level);
    }

    const STATUS_LABELS = [
        'draft'     => 'Bản nháp',
        'published' => 'Đã xuất bản',
        'archived'  => 'Đã lưu trữ',
    ];

    const PRICE_CHEAP   = 50000;
    const PRICE_NORMAL  = 200000;
    const PRICE_PREMIUM = 500000;

    const LEVEL_NUMBERS = [
        'beginner'     => 1,
        'intermediate' => 2,
        'advanced'     => 3,
    ];

    public function getStatusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? 'Không xác định';
    }

    public function getPriceCategory(): string
    {
        if ($this->price == 0) {
            return 'free';
        } elseif ($this->price < self::PRICE_CHEAP) {
            return 'cheap';
        } elseif ($this->price < self::PRICE_NORMAL) {
            return 'normal';
        } elseif ($this->price < self::PRICE_PREMIUM) {
            return 'premium';
        }
        return 'luxury';
    }

    public function getLevelNumber(): int
    {
        return self::LEVEL_NUMBERS[$this->level] ?? 0;
    }
}