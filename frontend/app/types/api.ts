// app/types/api.ts
// 統一 API 回應型別定義

export interface ApiResponse<T = unknown> {
  success: boolean
  message: string
  data: T
  errors: Record<string, string[]> | null
}

export interface PaginatedResponse<T = unknown> {
  success: boolean
  message: string
  data: T[]
  errors: Record<string, string[]> | null
  pagination: Pagination
}

export interface Pagination {
  current_page: number
  per_page: number
  total: number
  total_pages: number
}

// ── Domain Types ──────────────────────────────────────────────────

export interface Category {
  id: number
  parent_id: number | null
  name: string
  slug: string
  description: string | null
  sort_order: number
  is_active: boolean
  created_at: string
  updated_at: string
  children?: Category[]
}

export interface Unit {
  id: number
  name: string
  symbol: string
  description: string | null
  is_active: boolean
  created_at: string
  updated_at: string
}

export interface ItemSkuForm {
  sku_code: string
  attributes: Record<string, string>
  cost_price: number
  selling_price: number
}

export interface Item {
  id: number
  category_id: number
  unit_id: number
  code: string
  name: string
  description: string | null
  tax_type: 'taxable' | 'zero' | 'exempt'
  reorder_point: number
  safety_stock: number
  lead_time_days: number
  image_path: string | null
  is_active: boolean
  category_name?: string
  unit_name?: string
  skus?: ItemSku[]
  created_at: string
  updated_at: string
}

export interface ItemSku {
  id: number
  item_id: number
  sku_code: string
  barcode: string | null
  attributes: Record<string, string>
  cost_price: number
  selling_price: number
  is_active: boolean
  item_name?: string
  created_at: string
  updated_at: string
}

export interface Inventory {
  id: number
  sku_id: number
  warehouse_id: number
  on_hand_qty: number
  reserved_qty: number
  on_order_qty: number
  avg_cost: number
  available_qty: number  // 計算欄位：on_hand - reserved
  sku_code?: string
  item_name?: string
  warehouse_name?: string
}

// ── 供應商 ────────────────────────────────────────────────────────

export interface Supplier {
  id: number
  code: string
  name: string
  contact_name: string | null
  contact_phone: string | null
  contact_email: string | null
  address: string | null
  tax_id: string | null
  payment_terms: string | null
  lead_time_days: number
  is_active: boolean
  notes: string | null
  created_at: string
  updated_at: string
}

// ── 採購單 ────────────────────────────────────────────────────────

export type PurchaseOrderStatus = 'draft' | 'pending' | 'approved' | 'partial' | 'received' | 'cancelled'
export type PurchasePaymentStatus = 'unpaid' | 'partial' | 'paid'
export type PurchasePaymentMethod = 'bank_transfer' | 'cash' | 'check' | 'other'

export interface PurchaseOrder {
  id: number
  po_number: string
  supplier_id: number
  warehouse_id: number
  status: PurchaseOrderStatus
  subtotal: number
  tax_rate: number
  tax_amount: number
  total_amount: number
  payment_status: PurchasePaymentStatus
  paid_amount: number
  payment_due_date: string | null
  expected_date: string | null
  notes: string | null
  approved_by: number | null
  approved_at: string | null
  created_by: number
  created_at: string
  updated_at: string
  // 關聯欄位（後端可能附帶）
  supplier_name?: string
  lines?: PurchaseOrderLine[]
}

export interface PurchaseOrderLine {
  id: number
  purchase_order_id: number
  sku_id: number
  ordered_qty: number
  received_qty: number
  unit_price: number
  line_total: number
  notes: string | null
  // 關聯欄位
  sku_code?: string
  item_name?: string
}

// 採購單建立表單中的明細
export interface PurchaseOrderLineForm {
  sku_id: number
  ordered_qty: number
  unit_price: number
  notes?: string
  // 顯示用
  sku_code?: string
  item_name?: string
}

// ── 進貨驗收 ──────────────────────────────────────────────────────

export interface GoodsReceipt {
  id: number
  gr_number: string
  purchase_order_id: number
  warehouse_id: number
  received_by: number
  received_at: string
  notes: string | null
  created_at: string
}

export interface GoodsReceiptLine {
  id: number
  goods_receipt_id: number
  purchase_order_line_id: number
  sku_id: number
  received_qty: number
  unit_cost: number
  batch_number: string | null
  expiry_date: string | null
  notes: string | null
}

// 驗收表單中的明細
export interface ReceiveLineForm {
  line_id: number
  received_qty: number
  unit_cost?: number
  batch_number?: string
  expiry_date?: string
  notes?: string
  // 顯示用
  sku_id?: number
  sku_code?: string
  item_name?: string
  ordered_qty?: number
  already_received?: number
}

// ── 付款記錄 ──────────────────────────────────────────────────────

export interface PurchasePayment {
  id: number
  purchase_order_id: number
  amount: number
  payment_date: string
  payment_method: PurchasePaymentMethod
  reference_no: string | null
  notes: string | null
  created_by: number
  created_at: string
  updated_at: string
}

export interface PurchasePaymentForm {
  amount: number
  payment_date: string
  payment_method: PurchasePaymentMethod
  reference_no?: string
  notes?: string
}

// ── 採購退貨 ──────────────────────────────────────────────────────

export type PurchaseReturnStatus = 'draft' | 'confirmed' | 'cancelled'

export interface PurchaseReturn {
  id: number
  return_number: string
  purchase_order_id: number
  status: PurchaseReturnStatus
  reason: string | null
  notes: string | null
  created_by: number
  confirmed_by: number | null
  confirmed_at: string | null
  created_at: string
  updated_at: string
  lines?: PurchaseReturnLine[]
}

export interface PurchaseReturnLine {
  id: number
  purchase_return_id: number
  purchase_order_line_id: number
  sku_id: number
  return_qty: number
  unit_cost: number | null
  return_reason: string | null
  batch_number: string | null
  notes: string | null
  sku_code?: string
  item_name?: string
}

export interface PurchaseReturnLineForm {
  purchase_order_line_id: number
  sku_id: number
  return_qty: number
  unit_cost?: number
  return_reason?: string
  batch_number?: string
  notes?: string
  // 顯示用
  sku_code?: string
  item_name?: string
  received_qty?: number
}

// ── 客戶 ──────────────────────────────────────────────────────────

export interface Customer {
  id: number
  code: string
  name: string
  tax_id: string | null
  contact_name: string | null
  contact_phone: string | null
  contact_email: string | null
  credit_limit: number
  payment_terms: string | null
  notes: string | null
  is_active: boolean
  created_at: string
  updated_at: string
}

export interface CustomerAddress {
  id: number
  customer_id: number
  label: string | null
  contact_name: string | null
  contact_phone: string | null
  address_line1: string
  address_line2: string | null
  city: string | null
  postal_code: string | null
  country: string
  is_default: boolean
  created_at: string
  updated_at: string
}

// ── 銷售訂單 ──────────────────────────────────────────────────────

export type SalesOrderStatus = 'draft' | 'confirmed' | 'partial' | 'shipped' | 'cancelled'
export type SalesPaymentStatus = 'unpaid' | 'partial' | 'paid'

export interface SalesOrder {
  id: number
  so_number: string
  customer_id: number
  warehouse_id: number
  status: SalesOrderStatus
  shipping_address_id: number | null
  shipping_name: string | null
  shipping_phone: string | null
  shipping_address: string | null
  order_date: string
  expected_ship_date: string | null
  tax_rate: number
  subtotal: number
  tax_amount: number
  total_amount: number
  discount_amount: number
  payment_status: SalesPaymentStatus
  paid_amount: number
  payment_due_date: string | null
  is_dropship: boolean
  notes: string | null
  created_by: number
  confirmed_by: number | null
  confirmed_at: string | null
  closed_at: string | null
  created_at: string
  updated_at: string
  // 關聯欄位（後端可能附帶）
  customer_name?: string
  lines?: SalesOrderLine[]
}

export interface SalesOrderLine {
  id: number
  sales_order_id: number
  sku_id: number
  ordered_qty: number
  shipped_qty: number
  unit_price: number
  discount_rate: number
  line_total: number
  notes: string | null
  // 關聯欄位
  sku_code?: string
  item_name?: string
}

export interface SalesOrderLineForm {
  sku_id: number
  ordered_qty: number
  unit_price: number
  discount_rate?: number
  notes?: string
  // 顯示用
  sku_code?: string
  item_name?: string
}

// ── 出貨單 ────────────────────────────────────────────────────────

export type ShipmentStatus = 'pending' | 'shipped' | 'cancelled'

export interface Shipment {
  id: number
  shipment_number: string
  sales_order_id: number
  warehouse_id: number
  status: ShipmentStatus
  carrier: string | null
  tracking_number: string | null
  shipped_at: string | null
  notes: string | null
  created_by: number
  created_at: string
  updated_at: string
  // 關聯欄位
  so_number?: string
  customer_name?: string
}

export interface ShipmentLine {
  id: number
  shipment_id: number
  sales_order_line_id: number
  sku_id: number
  shipped_qty: number
  batch_number: string | null
  notes: string | null
  // 關聯欄位
  sku_code?: string
  item_name?: string
}

export interface ShipmentLineForm {
  sales_order_line_id: number
  sku_id: number
  shipped_qty: number
  batch_number?: string
  notes?: string
  // 顯示用
  sku_code?: string
  item_name?: string
  ordered_qty?: number
  shipped_qty_so_far?: number
  pending_qty?: number
}

// ── Auth ──────────────────────────────────────────────────────────

export interface LoginPayload {
  username: string
  password: string
}

// ── 銷售收款 ──────────────────────────────────────────────────────

export type SalesPaymentMethod = 'bank_transfer' | 'cash' | 'check' | 'credit_card' | 'other'

export interface SalesPayment {
  id: number
  sales_order_id: number
  amount: number
  payment_date: string
  payment_method: SalesPaymentMethod
  reference_no: string | null
  notes: string | null
  created_by: number
  created_at: string
  updated_at: string
}

export interface SalesPaymentForm {
  amount: number
  payment_date: string
  payment_method: SalesPaymentMethod
  reference_no?: string
  notes?: string
}

// ── 銷售退貨 ──────────────────────────────────────────────────────

export type SalesReturnStatus = 'draft' | 'confirmed' | 'cancelled'

export interface SalesReturn {
  id: number
  return_number: string
  sales_order_id: number
  warehouse_id: number
  status: SalesReturnStatus
  reason: string | null
  refund_amount: number
  notes: string | null
  created_by: number
  confirmed_by: number | null
  confirmed_at: string | null
  created_at: string
  updated_at: string
  // 關聯欄位
  so_number?: string
  customer_name?: string
}

export interface SalesReturnLine {
  id: number
  sales_return_id: number
  sales_order_line_id: number
  sku_id: number
  return_qty: number
  unit_price: number | null
  return_reason: string | null
  batch_number: string | null
  notes: string | null
  // 關聯欄位
  sku_code?: string
  item_name?: string
}

export interface SalesReturnLineForm {
  sales_order_line_id: number
  sku_id: number
  return_qty: number
  unit_price?: number
  return_reason?: string
  batch_number?: string
  notes?: string
  // 顯示用
  sku_code?: string
  item_name?: string
  shipped_qty?: number
  pending_return_qty?: number
}

// ── 倉庫 ──────────────────────────────────────────────────────────

export interface Warehouse {
  id: number
  name: string
  code: string
  location: string | null
  is_active: boolean
  notes: string | null
  created_at: string
  updated_at: string
}

// ── 庫存異動日誌 ──────────────────────────────────────────────────

export type InventoryTxType = 'DEDUCT' | 'REPLENISH' | 'ADJUST' | 'TRANSFER_IN' | 'TRANSFER_OUT'

export interface InventoryTransaction {
  id: number
  sku_id: number
  warehouse_id: number
  tx_type: InventoryTxType
  qty_change: number
  qty_after: number
  unit_cost: number | null
  source_type: string | null
  source_id: number | null
  operator_id: number | null
  note: string | null
  occurred_at: string
  created_at: string
  // 關聯欄位
  sku_code?: string
  item_name?: string
  warehouse_name?: string
}

// ── 庫存調撥 ──────────────────────────────────────────────────────

export type StockTransferStatus = 'draft' | 'confirmed' | 'cancelled'

export interface StockTransfer {
  id: number
  transfer_number: string
  from_warehouse_id: number
  to_warehouse_id: number
  status: StockTransferStatus
  reason: string | null
  notes: string | null
  created_by: number
  confirmed_by: number | null
  confirmed_at: string | null
  created_at: string
  updated_at: string
  // 關聯欄位
  from_warehouse_name?: string
  to_warehouse_name?: string
}

export interface StockTransferLine {
  id: number
  stock_transfer_id: number
  sku_id: number
  qty: number
  batch_number: string | null
  notes: string | null
  // 關聯欄位
  sku_code?: string
  item_name?: string
}

export interface StockTransferLineForm {
  sku_id: number
  qty: number
  batch_number?: string
  notes?: string
  // 顯示用
  sku_code?: string
  item_name?: string
}

// ── 盤點 ──────────────────────────────────────────────────────────

export type StocktakeStatus = 'draft' | 'in_progress' | 'confirmed' | 'cancelled'

export interface Stocktake {
  id: number
  stocktake_number: string
  warehouse_id: number
  status: StocktakeStatus
  notes: string | null
  created_by: number
  confirmed_by: number | null
  confirmed_at: string | null
  created_at: string
  updated_at: string
  // 關聯欄位
  warehouse_name?: string
}

export interface StocktakeLine {
  id: number
  stocktake_id: number
  sku_id: number
  system_qty: number
  actual_qty: number | null
  difference_qty: number | null
  batch_number: string | null
  notes: string | null
  counted_at: string | null
  // 關聯欄位
  sku_code?: string
  item_name?: string
}

export interface AuthTokens {
  access_token: string
  refresh_token: string
  expires_in: number
  token_type: 'Bearer'
}

export interface AuthUser {
  id: number
  username: string
  name: string
  role: string
  permissions?: string[]
}

// ── 批號 / 序號 ────────────────────────────────────────────────────

export type BatchSerialType   = 'batch' | 'serial'
export type BatchSerialStatus = 'available' | 'reserved' | 'consumed' | 'expired'

export interface BatchSerial {
  id: number
  sku_id: number
  warehouse_id: number
  goods_receipt_line_id: number | null
  type: BatchSerialType
  batch_number: string
  serial_number: string | null
  quantity: number
  unit_cost: number
  manufactured_date: string | null
  expiry_date: string | null
  status: BatchSerialStatus
  created_at: string
  updated_at: string
  // 關聯欄位
  sku_code?: string
  item_name?: string
  warehouse_name?: string
}

// ── 報表 ──────────────────────────────────────────────────────────

/** 儀表板 KPI 彙整 */
export interface DashboardKpi {
  pending_sales_orders: number
  pending_purchase_orders: number
  low_stock_count: number
  monthly_sales_amount: number
  monthly_purchase_amount: number
  monthly_sales_orders: number
}

/** 每日銷售趨勢單筆 */
export interface SalesTrendItem {
  date: string
  amount: number
  order_count: number
}

/** 進銷存彙總報表單筆 */
export interface InventorySummaryItem {
  sku_id: number
  sku_code: string
  item_name: string
  warehouse_id: number
  warehouse_name: string
  opening_qty: number
  in_qty: number
  out_qty: number
  adjust_qty: number
  closing_qty: number
  avg_cost: number
  closing_value: number
}

/** 進銷存彙總報表回應 */
export interface InventorySummaryReport {
  items: InventorySummaryItem[]
  summary: {
    total_opening_value: number
    total_closing_value: number
  }
}

/** 銷售業績報表（依 SKU） */
export interface SalesReportSkuItem {
  sku_id: number
  sku_code: string
  item_name: string
  total_qty: number
  total_amount: number
  order_count: number
}

/** 銷售業績報表（依客戶） */
export interface SalesReportCustomerItem {
  customer_id: number
  customer_name: string
  order_count: number
  total_amount: number
}

/** 銷售業績報表回應 */
export interface SalesReport {
  by_sku: SalesReportSkuItem[]
  by_customer: SalesReportCustomerItem[]
  summary: {
    total_revenue: number
    total_orders: number
  }
}

/** 毛利分析：每日 */
export interface ProfitDailyItem {
  date: string
  revenue: number
  cogs: number
  gross_profit: number
  gross_margin: number
}

/** 毛利分析：依 SKU */
export interface ProfitSkuItem {
  sku_id: number
  sku_code: string
  item_name: string
  sold_qty: number
  revenue: number
  cogs: number
  gross_profit: number
  gross_margin: number
}

/** 毛利分析報表回應 */
export interface ProfitReport {
  daily: ProfitDailyItem[]
  by_sku: ProfitSkuItem[]
  summary: {
    total_revenue: number
    total_cogs: number
    gross_profit: number
    gross_margin: number
  }
}

/** 採購報表（依廠商） */
export interface PurchaseReportSupplierItem {
  supplier_id: number
  supplier_name: string
  order_count: number
  total_amount: number
  paid_amount: number
}

/** 採購報表（依品項） */
export interface PurchaseReportItemItem {
  sku_id: number
  sku_code: string
  item_name: string
  total_ordered_qty: number
  total_received_qty: number
  total_amount: number
}

/** 採購報表回應 */
export interface PurchaseReport {
  by_supplier: PurchaseReportSupplierItem[]
  by_item: PurchaseReportItemItem[]
  summary: {
    total_purchase: number
    total_orders: number
  }
}

/** 庫存週轉率單筆 */
export interface TurnoverRateItem {
  sku_id: number
  sku_code: string
  item_name: string
  warehouse_id: number
  warehouse_name: string
  cogs: number
  current_value: number
  turnover_rate: number | null
  days_turnover: number | null
}
