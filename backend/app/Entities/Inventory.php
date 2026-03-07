<?php

declare(strict_types=1);

namespace App\Entities;

/**
 * Inventory Entity — 庫存領域物件
 *
 * 代表某個 SKU 在特定倉庫內的庫存狀態。
 * 核心業務規則封裝於此，避免在 Service 中散落。
 */
class Inventory extends BaseEntity
{
    protected $casts = [
        'id'           => 'integer',
        'sku_id'       => 'integer',
        'warehouse_id' => 'integer',
        'on_hand_qty'  => 'float',
        'reserved_qty' => 'float',
        'on_order_qty' => 'float',
        'avg_cost'     => 'float',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
    ];

    protected array $hidden = [];

    // ──────────────────────────────────────────────
    // 計算屬性（Computed Properties）
    // ──────────────────────────────────────────────

    /**
     * 可用數量 = 在庫量 - 已預留量
     * 這是可以被新訂單使用的數量
     */
    public function getAvailableQty(): float
    {
        return max(0.0, (float) $this->attributes['on_hand_qty'] - (float) $this->attributes['reserved_qty']);
    }

    /**
     * 判斷指定數量是否可被出庫（含允許負庫存設定）
     */
    public function canDeduct(float $quantity, bool $allowNegative = false): bool
    {
        if ($allowNegative) {
            return true;
        }
        return $this->getAvailableQty() >= $quantity;
    }

    // ──────────────────────────────────────────────
    // 狀態變更方法（均回傳 $this 以支援鏈式呼叫）
    // ──────────────────────────────────────────────

    /**
     * 預留庫存（SO 確認時呼叫）
     *
     * @throws \DomainException 可用量不足時拋出
     */
    public function reserve(float $quantity): static
    {
        if (!$this->canDeduct($quantity)) {
            throw new \DomainException(
                sprintf('庫存不足：可用量 %.4f，需求量 %.4f', $this->getAvailableQty(), $quantity)
            );
        }
        $this->attributes['reserved_qty'] = (float) $this->attributes['reserved_qty'] + $quantity;
        return $this;
    }

    /**
     * 釋放預留（SO 取消時呼叫）
     */
    public function releaseReservation(float $quantity): static
    {
        $this->attributes['reserved_qty'] = max(
            0.0,
            (float) $this->attributes['reserved_qty'] - $quantity
        );
        return $this;
    }

    /**
     * 入庫（進貨驗收時呼叫）
     * 同時更新加權平均成本
     */
    public function addStock(float $quantity, float $unitCost): static
    {
        $currentOnHand = (float) $this->attributes['on_hand_qty'];
        $currentCost   = (float) $this->attributes['avg_cost'];

        // 加權平均成本公式
        if ($currentOnHand + $quantity > 0) {
            $newAvgCost = (($currentOnHand * $currentCost) + ($quantity * $unitCost))
                          / ($currentOnHand + $quantity);
            $this->attributes['avg_cost'] = round($newAvgCost, 4);
        }

        $this->attributes['on_hand_qty'] = $currentOnHand + $quantity;
        return $this;
    }

    /**
     * 出庫（出貨時呼叫）
     * 同時釋放對應的預留量
     */
    public function deductStock(float $quantity, bool $allowNegative = false): static
    {
        if (!$this->canDeduct($quantity, $allowNegative)) {
            throw new \DomainException(
                sprintf('庫存不足：可用量 %.4f，需求量 %.4f', $this->getAvailableQty(), $quantity)
            );
        }
        $this->attributes['on_hand_qty']  = (float) $this->attributes['on_hand_qty'] - $quantity;
        $this->attributes['reserved_qty'] = max(
            0.0,
            (float) $this->attributes['reserved_qty'] - $quantity
        );
        return $this;
    }

    /**
     * 設定在途數量（採購單下訂後增加，到貨後扣減）
     */
    public function adjustOnOrder(float $delta): static
    {
        $this->attributes['on_order_qty'] = max(
            0.0,
            (float) $this->attributes['on_order_qty'] + $delta
        );
        return $this;
    }
}
