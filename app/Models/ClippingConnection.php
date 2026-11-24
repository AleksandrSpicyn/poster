<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ClippingConnectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $owner_id
 * @property string $name
 * @property string $driver
 * @property string|null $api_key
 * @property bool $import_projects
 * @property bool $import_accounts
 * @property bool $import_archives
 * @property int $scope_version
 * @property bool $uses_server_key
 */
class ClippingConnection extends Model
{
    /** @use HasFactory<ClippingConnectionFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'driver',
        'api_key',
    ];

    protected $hidden = ['api_key'];

    protected $casts = [
        'catalog_checked_at' => 'datetime',
        'access_checked_at' => 'datetime',
        'access_result' => 'array',
        'api_key' => 'encrypted',
        'uses_server_key' => 'boolean',
        'import_projects' => 'boolean',
        'import_accounts' => 'boolean',
        'import_archives' => 'boolean',
        'scope_version' => 'integer',
    ];
}
