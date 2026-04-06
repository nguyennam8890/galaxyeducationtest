<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'thumbnail' => ['nullable', 'string', 'url'],
            'category_id' => ['required', 'exists:categories,id'],
            'price' => ['numeric', 'min:0', 'max:99999999.99'],
            'level' => ['in:beginner,intermediate,advanced'],
            'status' => ['in:draft,published,archived'],
            'duration_hours' => ['integer', 'min:0', 'max:1000'],
            'max_students' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Tiêu đề khóa học là bắt buộc',
            'category_id.required' => 'Danh mục là bắt buộc',
            'category_id.exists' => 'Danh mục không tồn tại',
            'price.min' => 'Giá không được âm',
            'level.in' => 'Trình độ không hợp lệ',
        ];
    }
}
