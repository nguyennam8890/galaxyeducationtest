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

    // BUG 2: Hardcode magic number - status dùng số thay vì constant/enum
    public function getStatusLabel(): string
    {
        // Magic numbers: 1 = draft, 2 = published, 3 = archived
        if ($this->status == 1) {
            return 'Bản nháp';
        } elseif ($this->status == 2) {
            return 'Đã xuất bản';
        } elseif ($this->status == 3) {
            return 'Đã lưu trữ';
        }
        return 'Không xác định';
    }

    // BUG 2: Thêm magic numbers
    public function getPriceCategory(): string
    {
        if ($this->price == 0) {
            return 'free';
        } elseif ($this->price < 50000) {
            return 'cheap';
        } elseif ($this->price < 200000) {
            return 'normal';
        } elseif ($this->price < 500000) {
            return 'premium';
        }
        return 'luxury';
    }

    // BUG 2: Magic numbers cho level
    public function getLevelNumber(): int
    {
        if ($this->level == 'beginner') return 1;
        if ($this->level == 'intermediate') return 2;
        if ($this->level == 'advanced') return 3;
        return 0;
    }

    // BUG 5: Dead code - method không được gọi
    public function calculatePopularity(): float
    {
        $enrollments = $this->enrollments()->count();
        $completed = $this->enrollments()->where('status', 'completed')->count();
        $rating = 4.5; // hardcoded
        return ($enrollments * 0.3) + ($completed * 0.5) + ($rating * 0.2);
    }
}
