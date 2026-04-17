<?php

namespace App\Models;

use Database\Factories\ConnectionFactory;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Connection extends Model
{
    /** @use HasFactory<ConnectionFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'server_id',
        'database',
        'username',
        'password',
        'sslmode',
        'last_error',
        'last_connected_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password'          => 'encrypted',
            'last_connected_at' => 'datetime',
        ];
    }

    /**
     * Plaintext password for PostgreSQL, empty string if none stored, or null if ciphertext exists but cannot be decrypted (e.g. APP_KEY changed).
     */
    public function tryDecryptPassword(): ?string
    {
        $raw = $this->getAttributes()['password'] ?? null;
        if ($raw === null || $raw === '') {
            return '';
        }

        try {
            return (string) $this->getAttribute('password');
        } catch (DecryptException) {
            return null;
        }
    }

    /**
     * @return BelongsTo<Server, $this>
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
