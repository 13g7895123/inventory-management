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

export interface SalesOrder {
  id: number
  so_number: string
  customer_id: number
  status: 'DRAFT' | 'CONFIRMED' | 'PARTIAL' | 'SHIPPED' | 'CANCELLED'
  requested_ship_date: string
  total_amount: number
  created_by: number
  customer_name?: string
  line_items?: SalesOrderLine[]
}

export interface SalesOrderLine {
  id: number
  so_id: number
  sku_id: number
  warehouse_id: number
  ordered_qty: number
  shipped_qty: number
  unit_price: number
  sku_code?: string
  item_name?: string
}

// ── Auth ──────────────────────────────────────────────────────────

export interface LoginPayload {
  username: string
  password: string
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
