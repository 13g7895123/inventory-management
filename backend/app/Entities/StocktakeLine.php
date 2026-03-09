<?php

declare(strict_types=1);

namespace App\Entities;

class StocktakeLine extends BaseEntity
{
    protected $casts = [
        'id'             => 'integer',
        'stocktake_id'   => 'integer',
        'sku_id'         => 'integer',
        'system_qty'     => 'float',
        'actual_qty'     => 'float',
        'difference_qty' => 'float',
    ];

    /**
     * 計算差異量
     */
    public function calculateDifference(): void
    {
        if ($this->actual_qty !== null) {
            $this->difference_qty = (float) $this->actual_qty - (float) $this->system_qty;
            $this->counted_at     = date('Y-m-d H:i:s');
        }
    }

    /**
     * 是否有差異
     */
    public function hasDifference(): bool
    {
        return $this->difference_qty !== null && abs((float) $this->difference_qty) > 0.0001;
    }
}
