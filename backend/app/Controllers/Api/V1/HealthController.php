<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\RESTful\ResourceController;

class HealthController extends ResourceController
{
    public function index(): ResponseInterface
    {
        return $this->respond([
            'status'  => 'ok',
            'version' => 'v1',
            'time'    => date('Y-m-d H:i:s'),
        ]);
    }
}
