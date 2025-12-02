<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'type',
        'description',
        'is_active',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Relations avec l'utilisateur qui a modifié le paramètre
     */
    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    /**
     * Récupère un paramètre par sa clé et retourne la valeur typée
     */
    public static function get(string $key, $default = null)
    {
        $setting = static::where('key', $key)
            ->where('is_active', true)
            ->first();

        if (!$setting) {
            return $default;
        }

        return match ($setting->type) {
            'decimal', 'float' => (float) $setting->value,
            'integer', 'int' => (int) $setting->value,
            'boolean', 'bool' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
            default => $setting->value,
        };
    }

    /**
     * Définit ou met à jour un paramètre
     */
    public static function set(string $key, $value, string $type = 'string', ?string $description = null, ?int $userId = null): self
    {
        $setting = static::firstOrNew(['key' => $key]);
        $setting->value = (string) $value;
        $setting->type = $type;
        $setting->description = $description ?? $setting->description;
        $setting->updated_by_user_id = $userId;
        $setting->is_active = true;
        $setting->save();

        return $setting;
    }
}

