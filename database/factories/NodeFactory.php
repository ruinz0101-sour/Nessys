<?php

namespace Database\Factories;

use App\Enums\FileCategory;
use App\Enums\NodeType;
use App\Enums\RootType;
use App\Models\Department;
use App\Models\Node;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Node>
 */
class NodeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $uuid = (string) Str::uuid();
        $extension = fake()->randomElement(['pdf', 'docx', 'xlsx', 'png', 'mp4', 'zip']);
        $category = match ($extension) {
            'png' => FileCategory::Image,
            'mp4' => FileCategory::Video,
            'pdf', 'docx', 'xlsx' => FileCategory::Document,
            default => FileCategory::Other,
        };
        $mimeType = match ($extension) {
            'pdf' => 'application/pdf',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'png' => 'image/png',
            'mp4' => 'video/mp4',
            default => 'application/zip',
        };

        return [
            'uuid' => $uuid,
            'parent_id' => null,
            'type' => NodeType::File,
            'name' => fake()->unique()->words(2, true).'.'.$extension,
            'root_type' => RootType::Personal,
            'department_id' => null,
            'owner_id' => User::factory(),
            'storage_path' => 'files/'.$uuid.'.'.$extension,
            'size_bytes' => fake()->numberBetween(1024, 50 * 1024 * 1024),
            'mime_type' => $mimeType,
            'extension' => $extension,
            'category' => $category,
            'sha256' => hash('sha256', $uuid),
            'is_important' => false,
            'is_dropzone' => false,
        ];
    }

    public function folder(?string $name = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => NodeType::Folder,
            'name' => $name ?? ucfirst(fake()->unique()->words(2, true)),
            'storage_path' => null,
            'size_bytes' => 0,
            'mime_type' => null,
            'extension' => null,
            'category' => null,
            'sha256' => null,
        ]);
    }

    public function file(?string $name = null, ?string $extension = 'pdf'): static
    {
        return $this->state(function (array $attributes) use ($name, $extension): array {
            $uuid = $attributes['uuid'] ?? (string) Str::uuid();
            $ext = $extension ?? 'pdf';
            $category = match ($ext) {
                'png', 'jpg', 'jpeg' => FileCategory::Image,
                'mp4', 'mov' => FileCategory::Video,
                'pdf', 'docx', 'xlsx', 'pptx' => FileCategory::Document,
                default => FileCategory::Other,
            };
            $mimeType = match ($ext) {
                'pdf' => 'application/pdf',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'png' => 'image/png',
                'jpg', 'jpeg' => 'image/jpeg',
                'mp4' => 'video/mp4',
                default => 'application/octet-stream',
            };

            return [
                'uuid' => $uuid,
                'type' => NodeType::File,
                'name' => $name ?? (fake()->unique()->words(2, true).'.'.$ext),
                'storage_path' => 'files/'.$uuid.'.'.$ext,
                'mime_type' => $mimeType,
                'extension' => $ext,
                'category' => $category,
                'sha256' => hash('sha256', $uuid),
            ];
        });
    }

    public function shared(): static
    {
        return $this->state(fn (array $attributes): array => [
            'root_type' => RootType::Shared,
            'department_id' => null,
        ]);
    }

    public function department(?Department $department = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'root_type' => RootType::Department,
            'department_id' => $department?->id ?? Department::factory(),
        ]);
    }

    public function personal(): static
    {
        return $this->state(fn (array $attributes): array => [
            'root_type' => RootType::Personal,
            'department_id' => null,
        ]);
    }

    public function important(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_important' => true,
        ]);
    }

    public function dropzone(?string $name = 'ที่ส่งงานส่วนกลาง'): static
    {
        return $this->folder($name)->shared()->state(fn (array $attributes): array => [
            'is_dropzone' => true,
        ]);
    }
}
